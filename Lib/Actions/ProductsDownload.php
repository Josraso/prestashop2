<?php

namespace FacturaScripts\Plugins\Prestashop\Lib\Actions;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Prestashop\Lib\PrestashopConnection;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopTaxMap;

/**
 * Clase para descargar productos de PrestaShop y gestionarlos en FacturaScripts
 */
class ProductsDownload
{
    /** @var PrestashopConnection */
    private $connection;

    /** @var PrestashopConfig */
    private $config;

    /** @var array Cache de tax rates para evitar peticiones repetidas */
    private $taxRateCache = [];

    /** @var bool Control para descargar o no imágenes al importar productos existentes */
    private $downloadImages = true;

    public function __construct()
    {
        $this->config = PrestashopConfig::getActive();
        $this->connection = new PrestashopConnection($this->config);
    }

    /**
     * Establece si se deben descargar imágenes al importar/actualizar productos
     */
    public function setDownloadImages(bool $download): void
    {
        $this->downloadImages = $download;
    }

    /**
     * Obtiene productos de PrestaShop por lotes con sus combinaciones expandidas
     *
     * @param int $offset Desde qué producto empezar
     * @param int $limit Cuántos productos obtener
     * @return array Array con 'products', 'offset', 'limit'
     */
    /**
     * Descarga productos de PrestaShop de forma OPTIMIZADA
     * Reduce peticiones HTTP al mínimo necesario
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getAllProducts(int $offset = 0, int $limit = 50): array
    {
        if (!$this->config) {
            Tools::log()->error('PrestaShop: Configuración no encontrada');
            return ['products' => []];
        }

        if (!$this->connection->isConnected()) {
            Tools::log()->error('PrestaShop: No se pudo conectar con la tienda');
            return ['products' => []];
        }

        Tools::log()->critical("╔════════════════════════════════════════════════════════════╗");
        Tools::log()->critical("║      DESCARGA OPTIMIZADA - offset:{$offset} limit:{$limit}      ║");
        Tools::log()->critical("╚════════════════════════════════════════════════════════════╝");

        try {
            $webService = $this->connection->getWebService();
            $finalProducts = [];

            // ============================================================
            // PASO 1: Obtener productos con TODOS los campos necesarios
            // ============================================================
            Tools::log()->info("→ Obteniendo productos completos en UNA petición...");
            $params = [
                'display' => 'full',
                'limit' => "{$offset},{$limit}"
            ];

            $xmlString = $webService->get('products', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            if (!isset($xml->products->product)) {
                Tools::log()->warning('No se encontraron productos');
                return ['products' => []];
            }

            $productsList = [];
            foreach ($xml->products->product as $product) {
                $productsList[] = $product;
            }

            Tools::log()->info("✓ Obtenidos " . count($productsList) . " productos base");

            // ============================================================
            // PASO 2: Obtener TODAS las combinaciones del lote en UNA petición
            // ============================================================
            $productIds = array_map(function($p) { return (int)$p->id; }, $productsList);
            $allCombinations = $this->getAllCombinationsForProducts($productIds);
            Tools::log()->info("✓ Obtenidas " . count($allCombinations) . " combinaciones totales");

            // ============================================================
            // PASO 3: Procesar cada producto y expandir combinaciones
            // ============================================================
            foreach ($productsList as $product) {
                $productId = (int)$product->id;
                $productName = $this->extractMultilangField($product->name);
                $productReference = (string)$product->reference;
                $productPrice = (float)$product->price;
                $productActive = (int)$product->active === 1;
                $imageUrl = $this->getProductImageUrl($productId, (int)$product->id_default_image);

                // Guardar id_tax_rules_group para obtener IVA después (no durante descarga)
                $idTaxRulesGroup = (int)($product->id_tax_rules_group ?? 0);

                // Buscar combinaciones de este producto
                $productCombinations = array_filter($allCombinations, function($combo) use ($productId) {
                    return $combo['id_product'] == $productId;
                });

                if (empty($productCombinations)) {
                    // SIN COMBINACIONES: crear 1 producto simple
                    $stock = $this->getStockForSimpleProduct($productId);

                    // Si no tiene referencia, usar ID
                    $ref = !empty($productReference) ? $productReference : 'PS-' . $productId;

                    // Verificar si ya existe en FacturaScripts
                    $exists = $this->checkProductExists($ref);

                    $finalProducts[] = [
                        'ps_product_id' => $productId,
                        'ps_combination_id' => null,
                        'reference' => $ref,
                        'name' => $productName,
                        'price' => round($productPrice, 2),
                        'stock' => $stock,
                        'active' => $productActive,
                        'image_url' => $imageUrl,
                        'has_combination' => false,
                        'exists' => $exists,
                        'id_tax_rules_group' => $idTaxRulesGroup
                    ];

                } else {
                    // CON COMBINACIONES: crear 1 producto por cada combinación
                    foreach ($productCombinations as $combo) {
                        $comboReference = (string)$combo['reference'];
                        $comboPriceImpact = (float)$combo['price'];
                        $comboStock = (int)$combo['quantity'];
                        $comboAttributes = $combo['attributes'];

                        // Precio final = precio base + impacto
                        $finalPrice = $productPrice + $comboPriceImpact;

                        // Nombre concatenado
                        $combinedName = $productName;
                        if (!empty($comboAttributes)) {
                            $combinedName .= ' - ' . implode(' - ', $comboAttributes);
                        }

                        // Si combinación sin referencia, usar la del producto
                        $ref = !empty($comboReference) ? $comboReference : $productReference;
                        if (empty($ref)) {
                            $ref = 'PS-' . $productId . '-' . $combo['id'];
                        }

                        // Verificar si ya existe en FacturaScripts
                        $exists = $this->checkProductExists($ref);

                        $finalProducts[] = [
                            'ps_product_id' => $productId,
                            'ps_combination_id' => $combo['id'],
                            'reference' => $ref,
                            'name' => $combinedName,
                            'price' => round($finalPrice, 2),
                            'stock' => $comboStock,
                            'active' => $productActive,
                            'image_url' => $imageUrl,
                            'has_combination' => true,
                            'exists' => $exists,
                            'id_tax_rules_group' => $idTaxRulesGroup
                        ];
                    }
                }
            }

            Tools::log()->critical("✓ DESCARGA COMPLETADA: " . count($productsList) . " productos PrestaShop → " . count($finalProducts) . " productos FacturaScripts (con combinaciones expandidas)");

            return [
                'products' => $finalProducts,
                'offset' => $offset,
                'limit' => $limit
            ];

        } catch (\Exception $e) {
            Tools::log()->error('ERROR EN DESCARGA: ' . $e->getMessage());
            Tools::log()->error('Stack trace: ' . $e->getTraceAsString());
            return ['products' => []];
        }
    }

    /**
     * Obtiene TODAS las combinaciones de múltiples productos en UNA petición
     *
     * @param array $productIds
     * @return array
     */
    private function getAllCombinationsForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        try {
            $webService = $this->connection->getWebService();

            // Crear filtro: filter[id_product]=[1|2|3|4...]
            $filter = '[' . implode('|', $productIds) . ']';

            $params = [
                'filter[id_product]' => $filter,
                'display' => '[id,id_product,reference,price,quantity]'
            ];

            Tools::log()->info("  → Descargando combinaciones para " . count($productIds) . " productos...");

            $xmlString = $webService->get('combinations', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            if (!isset($xml->combinations->combination)) {
                return [];
            }

            $combinations = [];
            foreach ($xml->combinations->combination as $combo) {
                $comboId = (int)$combo->id;
                $productId = (int)$combo->id_product;

                // Obtener atributos de esta combinación
                $attributes = $this->getCombinationAttributeValues($comboId);

                $combinations[] = [
                    'id' => $comboId,
                    'id_product' => $productId,
                    'reference' => (string)$combo->reference,
                    'price' => (float)$combo->price,
                    'quantity' => (int)$combo->quantity,
                    'attributes' => $attributes
                ];
            }

            return $combinations;

        } catch (\Exception $e) {
            Tools::log()->error("Error obteniendo combinaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene stock de un producto simple (sin combinaciones)
     *
     * @param int $productId
     * @return int
     */
    private function getStockForSimpleProduct(int $productId): int
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id_product]' => $productId,
                'filter[id_product_attribute]' => 0,
                'display' => '[quantity]'
            ];

            $xmlString = @$webService->get('stock_availables', null, null, $params);
            if ($xmlString === false) {
                return 0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false || !isset($xml->stock_availables->stock_available)) {
                return 0;
            }

            $stock = $xml->stock_availables->stock_available;
            if (is_array($stock) || $stock instanceof \Traversable) {
                foreach ($stock as $s) {
                    return (int)$s->quantity;
                }
            }

            return (int)$stock->quantity;

        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtiene el total de productos en PrestaShop
     *
     * @return int
     */
    public function getTotalProducts(): int
    {
        if (!$this->config || !$this->connection->isConnected()) {
            return 0;
        }

        try {
            $webService = $this->connection->getWebService();

            // Obtener todos los IDs sin límite para contar el total
            $params = [
                'display' => '[id]'
            ];

            $xmlString = $webService->get('products', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            if (!isset($xml->products)) {
                return 0;
            }

            // Contar los productos que vienen en la respuesta
            if (isset($xml->products->product)) {
                $count = 0;
                foreach ($xml->products->product as $product) {
                    $count++;
                }
                return $count;
            }

            return 0;

        } catch (\Exception $e) {
            Tools::log()->error('Error obteniendo total de productos: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene los detalles completos de un producto
     *
     * @param int $productId
     * @return array|null
     */
    private function getProductDetails(int $productId): ?array
    {
        try {
            $webService = $this->connection->getWebService();

            // Obtener producto completo usando filtro
            $params = [
                'filter[id]' => '[' . $productId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $webService->get('products', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // Con filtro, el resultado viene en <products><product>
            if (!isset($xml->products->product)) {
                return null;
            }

            // Tomar el primer producto del resultado
            $product = $xml->products->product;
            if (is_array($product) || $product instanceof \Traversable) {
                foreach ($product as $p) {
                    $product = $p;
                    break;
                }
            }

            // Extraer nombre (primer idioma disponible)
            $name = $this->extractMultilangField($product->name);

            // Precio base
            $price = (float)$product->price;

            // Referencia
            $reference = (string)$product->reference;

            // Estado (activo/inactivo)
            $active = (int)$product->active === 1;

            // Imagen por defecto (si existe)
            $imageUrl = $this->getProductImageUrl($productId, (int)$product->id_default_image);

            // Obtener combinaciones
            $combinations = $this->getProductCombinations($productId);

            // Stock: Si tiene combinaciones, el stock se obtiene de cada combinación
            // Si NO tiene combinaciones, obtener stock desde stock_availables
            $stock = 0;
            if (empty($combinations)) {
                $stock = $this->getStockForProduct($productId);
            }

            return [
                'id' => $productId,
                'name' => $name,
                'reference' => $reference,
                'price' => $price,
                'stock' => $stock,
                'active' => $active,
                'image_url' => $imageUrl,
                'combinations' => $combinations
            ];

        } catch (\Exception $e) {
            Tools::log()->error("Error obteniendo detalles del producto {$productId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene las combinaciones de un producto con sus atributos
     *
     * @param int $productId
     * @return array
     */
    private function getProductCombinations(int $productId): array
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id_product]' => $productId,
                'display' => '[id,reference,price,quantity]'
            ];

            $xmlString = $webService->get('combinations', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            if (!isset($xml->combinations->combination)) {
                return [];
            }

            $combinations = [];
            foreach ($xml->combinations->combination as $combo) {
                $comboId = (int)$combo->id;
                $reference = (string)$combo->reference;
                $priceImpact = (float)$combo->price;

                // Obtener stock real desde stock_availables
                $quantity = $this->getStockForCombination($productId, $comboId);

                // Obtener atributos de esta combinación (necesario para identificar)
                $attributes = $this->getCombinationAttributeValues($comboId);

                $combinations[] = [
                    'id' => $comboId,
                    'reference' => $reference,
                    'price_impact' => $priceImpact,
                    'quantity' => $quantity,
                    'attributes' => $attributes
                ];
            }

            return $combinations;

        } catch (\Exception $e) {
            Tools::log()->error("Error obteniendo combinaciones del producto {$productId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el stock real de una combinación desde stock_availables
     *
     * @param int $productId
     * @param int $combinationId
     * @return int
     */
    private function getStockForCombination(int $productId, int $combinationId): int
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id_product]' => $productId,
                'filter[id_product_attribute]' => $combinationId,
                'display' => '[quantity]'
            ];

            $xmlString = @$webService->get('stock_availables', null, null, $params);
            if ($xmlString === false) {
                Tools::log()->debug("No se pudo obtener stock_availables para producto {$productId}, combinación {$combinationId}");
                return 0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false) {
                Tools::log()->debug("Error parseando XML de stock para combinación {$combinationId}");
                return 0;
            }

            if (isset($xml->stock_availables->stock_available)) {
                $stock = $xml->stock_availables->stock_available;

                // Si hay múltiples resultados, tomar el primero
                if (is_array($stock) || $stock instanceof \Traversable) {
                    foreach ($stock as $s) {
                        $quantity = (int)$s->quantity;
                        Tools::log()->debug("Stock para combinación {$combinationId}: {$quantity}");
                        return $quantity;
                    }
                } else {
                    $quantity = (int)$stock->quantity;
                    Tools::log()->debug("Stock para combinación {$combinationId}: {$quantity}");
                    return $quantity;
                }
            }

            Tools::log()->debug("No se encontró stock_available para combinación {$combinationId}, usando 0");
            return 0;

        } catch (\Exception $e) {
            Tools::log()->warning("Excepción obteniendo stock para combinación {$combinationId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene el stock real de un producto sin combinaciones desde stock_availables
     *
     * @param int $productId
     * @return int
     */
    private function getStockForProduct(int $productId): int
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id_product]' => $productId,
                'filter[id_product_attribute]' => 0,  // 0 = producto sin combinaciones
                'display' => '[quantity]'
            ];

            $xmlString = @$webService->get('stock_availables', null, null, $params);
            if ($xmlString === false) {
                Tools::log()->debug("No se pudo obtener stock_availables para producto {$productId}");
                return 0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false) {
                Tools::log()->debug("Error parseando XML de stock para producto {$productId}");
                return 0;
            }

            if (isset($xml->stock_availables->stock_available)) {
                $stock = $xml->stock_availables->stock_available;

                // Si hay múltiples resultados, tomar el primero
                if (is_array($stock) || $stock instanceof \Traversable) {
                    foreach ($stock as $s) {
                        $quantity = (int)$s->quantity;
                        Tools::log()->debug("Stock para producto {$productId}: {$quantity}");
                        return $quantity;
                    }
                } else {
                    $quantity = (int)$stock->quantity;
                    Tools::log()->debug("Stock para producto {$productId}: {$quantity}");
                    return $quantity;
                }
            }

            Tools::log()->debug("No se encontró stock_available para producto {$productId}, usando 0");
            return 0;

        } catch (\Exception $e) {
            Tools::log()->warning("Excepción obteniendo stock para producto {$productId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene los valores de atributos de una combinación (ej: "Talla M", "Color Rojo")
     *
     * @param int $combinationId
     * @return array
     */
    private function getCombinationAttributeValues(int $combinationId): array
    {
        try {
            $webService = $this->connection->getWebService();

            // Obtener combinación completa con asociaciones usando filtro
            $params = [
                'filter[id]' => '[' . $combinationId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $webService->get('combinations', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // Con filtro viene en <combinations><combination>
            if (!isset($xml->combinations->combination)) {
                return [];
            }

            // Tomar la primera combinación
            $combination = $xml->combinations->combination;
            if (is_array($combination) || $combination instanceof \Traversable) {
                foreach ($combination as $c) {
                    $combination = $c;
                    break;
                }
            }

            if (!isset($combination->associations->product_option_values->product_option_value)) {
                return [];
            }

            $attributes = [];
            foreach ($combination->associations->product_option_values->product_option_value as $optionValue) {
                $optionValueId = (int)$optionValue->id;

                // Obtener el nombre del valor de atributo
                $valueName = $this->getProductOptionValueName($optionValueId);
                if ($valueName) {
                    $attributes[] = $valueName;
                }
            }

            return $attributes;

        } catch (\Exception $e) {
            Tools::log()->error("Error obteniendo atributos de combinación {$combinationId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el nombre de un valor de opción de producto
     *
     * @param int $optionValueId
     * @return string|null
     */
    private function getProductOptionValueName(int $optionValueId): ?string
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id]' => '[' . $optionValueId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $webService->get('product_option_values', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // Con filtro viene en <product_option_values><product_option_value>
            if (!isset($xml->product_option_values->product_option_value)) {
                return null;
            }

            // Tomar el primer valor
            $optionValue = $xml->product_option_values->product_option_value;
            if (is_array($optionValue) || $optionValue instanceof \Traversable) {
                foreach ($optionValue as $v) {
                    $optionValue = $v;
                    break;
                }
            }

            if (!isset($optionValue->name)) {
                return null;
            }

            return $this->extractMultilangField($optionValue->name);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Expande un producto con sus combinaciones como productos independientes
     *
     * @param array $productDetails
     * @return array
     */
    private function expandProductCombinations(array $productDetails): array
    {
        $products = [];

        // Si el producto tiene combinaciones, expandir cada una
        if (!empty($productDetails['combinations'])) {
            foreach ($productDetails['combinations'] as $combo) {
                // Calcular precio SIN IVA (precio base + impacto de combinación)
                // FacturaScripts añade el IVA automáticamente según el codimpuesto
                $price = $productDetails['price'] + $combo['price_impact'];

                // Concatenar nombre con atributos: "Producto - Attr1 - Attr2"
                $fullName = $productDetails['name'];
                if (!empty($combo['attributes'])) {
                    $fullName .= ' - ' . implode(' - ', $combo['attributes']);
                }

                // Usar referencia de la combinación, o generar una si está vacía
                $reference = !empty($combo['reference']) ? $combo['reference'] : $productDetails['reference'] . '-' . $combo['id'];

                // Verificar si el producto ya existe en FacturaScripts
                $exists = $this->checkProductExists($reference);

                $products[] = [
                    'ps_product_id' => $productDetails['id'],
                    'ps_combination_id' => $combo['id'],
                    'reference' => $reference,
                    'name' => $fullName,
                    'price' => round($price, 2), // SIN IVA
                    'stock' => $combo['quantity'],
                    'image_url' => $productDetails['image_url'],
                    'active' => $productDetails['active'],
                    'has_combination' => true,
                    'exists' => $exists
                ];
            }
        } else {
            // Producto sin combinaciones - precio SIN IVA
            $price = $productDetails['price'];

            // Verificar si el producto ya existe en FacturaScripts
            $exists = $this->checkProductExists($productDetails['reference']);

            $products[] = [
                'ps_product_id' => $productDetails['id'],
                'ps_combination_id' => null,
                'reference' => $productDetails['reference'],
                'name' => $productDetails['name'],
                'price' => round($price, 2), // SIN IVA
                'stock' => $productDetails['stock'],
                'image_url' => $productDetails['image_url'],
                'active' => $productDetails['active'],
                'has_combination' => false,
                'exists' => $exists
            ];
        }

        return $products;
    }

    /**
     * Verifica si un producto existe en FacturaScripts por su referencia
     *
     * @param string $reference
     * @return bool
     */
    private function checkProductExists(string $reference): bool
    {
        if (empty($reference)) {
            return false;
        }

        try {
            $variante = new Variante();
            return $variante->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene la URL de la imagen de un producto
     *
     * @param int $productId
     * @param int $imageId
     * @return string|null
     */
    private function getProductImageUrl(int $productId, int $imageId): ?string
    {
        if ($imageId <= 0) {
            return null;
        }

        // URL del formato: https://tienda.com/api/images/products/{productId}/{imageId}
        $shopUrl = rtrim($this->config->shop_url, '/');
        return "{$shopUrl}/api/images/products/{$productId}/{$imageId}?ws_key={$this->config->api_key}";
    }

    /**
     * Extrae el valor de un campo multiidioma usando el idioma configurado
     *
     * @param \SimpleXMLElement $field
     * @return string
     */
    private function extractMultilangField(\SimpleXMLElement $field): string
    {
        if (isset($field->language)) {
            $idiomaConfigurado = $this->config->idioma_productos ?? 1;

            // Si hay múltiples idiomas, buscar el configurado
            if (is_array($field->language) || $field->language instanceof \Traversable) {
                $primerIdioma = null;

                foreach ($field->language as $lang) {
                    // Guardar el primer idioma como fallback
                    if ($primerIdioma === null) {
                        $primerIdioma = (string)$lang;
                    }

                    // Buscar el idioma configurado por su posición (id atributo)
                    $langId = (int)$lang->attributes()->id;
                    if ($langId === $idiomaConfigurado) {
                        return (string)$lang;
                    }
                }

                // Si no se encuentra el idioma configurado, usar el primero
                return $primerIdioma ?? '';
            } else {
                return (string)$field->language;
            }
        }

        return (string)$field;
    }

    /**
     * Descarga una imagen desde PrestaShop y la guarda en attached_files
     *
     * @param string $imageUrl
     * @param string $reference Referencia del producto (para nombrar el archivo)
     * @return array|null Array con ['idfile' => int, 'filename' => string] o null si falla
     */
    public function downloadImage(string $imageUrl, string $reference): ?array
    {
        if (empty($imageUrl)) {
            Tools::log()->warning("URL de imagen vacía para referencia: {$reference}");
            return null;
        }

        try {
            // Directorio donde se guardarán las imágenes
            $uploadDir = \FS_FOLDER . '/MyFiles/Product';

            // Crear directorio si no existe
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    Tools::log()->error("No se pudo crear directorio: {$uploadDir}");
                    return null;
                }
                chmod($uploadDir, 0755);
                Tools::log()->info("Directorio creado: {$uploadDir}");
            }

            // Verificar permisos de escritura
            if (!is_writable($uploadDir)) {
                Tools::log()->error("Directorio sin permisos de escritura: {$uploadDir}");
                return null;
            }

            Tools::log()->info("Descargando imagen desde: {$imageUrl}");

            // Descargar imagen con contexto para manejar SSL
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'FacturaScripts-Prestashop-Plugin'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $imageData = @file_get_contents($imageUrl, false, $context);
            if ($imageData === false) {
                Tools::log()->error("Error descargando imagen desde: {$imageUrl}");
                return null;
            }

            // Verificar que se descargó algo
            $fileSize = strlen($imageData);
            if ($fileSize < 100) {
                Tools::log()->error("Imagen descargada demasiado pequeña ({$fileSize} bytes): {$imageUrl}");
                return null;
            }

            Tools::log()->info("Imagen descargada correctamente: {$fileSize} bytes");

            // Detectar tipo MIME real de la imagen
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);

            // Determinar extensión basada en MIME type
            $extension = 'jpg'; // Por defecto
            switch ($mimeType) {
                case 'image/jpeg':
                    $extension = 'jpg';
                    break;
                case 'image/png':
                    $extension = 'png';
                    break;
                case 'image/gif':
                    $extension = 'gif';
                    break;
                case 'image/webp':
                    $extension = 'webp';
                    break;
                default:
                    Tools::log()->warning("Tipo MIME desconocido: {$mimeType}, usando .jpg");
            }

            Tools::log()->info("Tipo MIME detectado: {$mimeType}, extensión: {$extension}");

            // Nombre del archivo (sanitizar referencia y hacerlo único)
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reference);
            $timestamp = time();
            $filename = $safeName . '_' . $timestamp . '.' . $extension;
            $localPath = $uploadDir . '/' . $filename;

            // Si el archivo ya existe, añadir sufijo
            $counter = 1;
            while (file_exists($localPath) && $counter < 100) {
                $filename = $safeName . '_' . $timestamp . '_' . $counter . '.' . $extension;
                $localPath = $uploadDir . '/' . $filename;
                $counter++;
            }

            Tools::log()->info("Guardando imagen como: {$filename}");

            // Guardar imagen
            if (file_put_contents($localPath, $imageData) === false) {
                Tools::log()->error("Error guardando imagen en: {$localPath}");
                return null;
            }

            // Establecer permisos correctos
            chmod($localPath, 0644);

            // Verificar que el archivo existe y tiene el tamaño correcto
            if (!file_exists($localPath)) {
                Tools::log()->error("Archivo de imagen no existe después de guardar: {$localPath}");
                return null;
            }

            $savedSize = filesize($localPath);
            if ($savedSize != $fileSize) {
                Tools::log()->warning("Tamaño del archivo guardado ({$savedSize}) != descargado ({$fileSize})");
            }

            Tools::log()->info("✓ Imagen guardada correctamente: {$filename} ({$savedSize} bytes, permisos: 0644)");

            // Crear registro en attached_files
            $db = new \FacturaScripts\Core\Base\DataBase();

            // Ruta relativa desde FS_FOLDER
            $relativePath = 'MyFiles/Product/' . $filename;

            // Obtener fecha y hora actuales
            $now = new \DateTime();
            $date = $now->format('Y-m-d');
            $time = $now->format('H:i:s');

            $sql = "INSERT INTO attached_files (date, hour, filename, path, mimetype, size)
                    VALUES (" . $db->var2str($date) . ", " . $db->var2str($time) . ", " .
                    $db->var2str($filename) . ", " . $db->var2str($relativePath) . ", " .
                    $db->var2str($mimeType) . ", " . $db->var2str($savedSize) . ")";

            if (!$db->exec($sql)) {
                Tools::log()->error("Error insertando en attached_files: " . $db->lastError());
                return null;
            }

            // Obtener el ID auto-generado
            $idfile = $db->lastval();

            if (!$idfile) {
                Tools::log()->error("No se pudo obtener el ID del archivo insertado");
                return null;
            }

            Tools::log()->info("✓ Registro creado en attached_files: idfile={$idfile}, filename={$filename}");

            return [
                'idfile' => $idfile,
                'filename' => $filename
            ];

        } catch (\Exception $e) {
            Tools::log()->error("Excepción descargando imagen para {$reference}: " . $e->getMessage());
            Tools::log()->error("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Vincula un archivo a un producto mediante attached_files_rel
     *
     * @param int $idfile ID del archivo en attached_files
     * @param int $idproducto ID del producto
     * @param string $referencia Referencia del producto
     * @return bool
     */
    private function linkFileToProduct(int $idfile, int $idproducto, string $referencia): bool
    {
        try {
            $db = new \FacturaScripts\Core\Base\DataBase();

            // 1. ATTACHED_FILES_REL (para archivos adjuntos genéricos)
            $sqlCheck = "SELECT id FROM attached_files_rel
                         WHERE model = 'Producto' AND modelid = " . $db->var2str($idproducto);
            $existing = $db->select($sqlCheck);

            if (!empty($existing)) {
                // Actualizar relación existente
                $sqlUpdate = "UPDATE attached_files_rel SET idfile = " . $db->var2str($idfile) . ",
                              creationdate = NOW()
                              WHERE model = 'Producto' AND modelid = " . $db->var2str($idproducto);

                if (!$db->exec($sqlUpdate)) {
                    Tools::log()->error("Error actualizando attached_files_rel");
                    return false;
                }

                Tools::log()->info("✓ Relación actualizada en attached_files_rel: idproducto={$idproducto}, idfile={$idfile}");
            } else {
                // Crear nueva relación
                $sqlInsert = "INSERT INTO attached_files_rel (idfile, model, modelid, modelcode, creationdate)
                              VALUES (" . $db->var2str($idfile) . ", 'Producto', " .
                              $db->var2str($idproducto) . ", " . $db->var2str($referencia) . ", NOW())";

                if (!$db->exec($sqlInsert)) {
                    Tools::log()->error("Error insertando en attached_files_rel");
                    return false;
                }

                Tools::log()->info("✓ Relación creada en attached_files_rel: idproducto={$idproducto}, idfile={$idfile}");
            }

            // 2. PRODUCTOS_IMAGENES (para que aparezca en menú "Imagen")
            $sqlCheckImg = "SELECT id FROM productos_imagenes
                            WHERE idproducto = " . $db->var2str($idproducto) . " AND referencia = " . $db->var2str($referencia);
            $existingImg = $db->select($sqlCheckImg);

            if (!empty($existingImg)) {
                // Actualizar imagen existente
                $sqlUpdateImg = "UPDATE productos_imagenes SET idfile = " . $db->var2str($idfile) . "
                                 WHERE idproducto = " . $db->var2str($idproducto) . " AND referencia = " . $db->var2str($referencia);

                if (!$db->exec($sqlUpdateImg)) {
                    Tools::log()->error("Error actualizando productos_imagenes");
                    return false;
                }

                Tools::log()->critical("✓✓✓ IMAGEN ACTUALIZADA EN MENÚ 'Imagen': idproducto={$idproducto}, idfile={$idfile}");
            } else {
                // Crear nueva entrada en productos_imagenes
                $sqlInsertImg = "INSERT INTO productos_imagenes (idfile, idproducto, referencia, orden)
                                 VALUES (" . $db->var2str($idfile) . ", " . $db->var2str($idproducto) . ", " .
                                 $db->var2str($referencia) . ", 1)";

                if (!$db->exec($sqlInsertImg)) {
                    Tools::log()->error("Error insertando en productos_imagenes");
                    return false;
                }

                Tools::log()->critical("✓✓✓ IMAGEN CREADA EN MENÚ 'Imagen': idproducto={$idproducto}, idfile={$idfile}, ref={$referencia}");
            }

            return true;

        } catch (\Exception $e) {
            Tools::log()->error("Error vinculando archivo a producto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el stock de una variante en el almacén
     * Registra el movimiento en variantstock
     *
     * @param int $idvariante ID de la variante
     * @param int $cantidad Cantidad de stock
     * @param string $referencia Referencia (para logs)
     * @return bool
     */
    private function actualizarStockVariante(int $idvariante, int $cantidad, string $referencia): bool
    {
        try {
            // Obtener almacén por defecto
            $codalmacen = $this->config->codalmacen ?? null;

            if (empty($codalmacen)) {
                $db = new \FacturaScripts\Core\Base\DataBase();
                $sqlAlmacen = "SELECT codalmacen FROM almacenes ORDER BY codalmacen LIMIT 1";
                $almacenes = $db->select($sqlAlmacen);

                if (!empty($almacenes)) {
                    $codalmacen = $almacenes[0]['codalmacen'];
                    Tools::log()->warning("No hay almacén configurado, usando: {$codalmacen}");
                } else {
                    Tools::log()->error("No hay almacenes disponibles");
                    return false;
                }
            }

            Tools::log()->info("Registrando stock de {$referencia} en almacén {$codalmacen}: {$cantidad} unidades");

            // Intentar usar el modelo Stock de FacturaScripts (método correcto)
            try {
                $stockModel = new \FacturaScripts\Dinamic\Model\Stock();

                // Buscar stock existente
                $where = [
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $referencia),
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('codalmacen', $codalmacen)
                ];

                if ($stockModel->loadFromCode('', $where)) {
                    // Stock existe - actualizar
                    $stockModel->cantidad = $cantidad;
                    $stockModel->disponible = $cantidad;

                    if ($stockModel->save()) {
                        Tools::log()->info("✓ Stock actualizado mediante modelo FacturaScripts: {$referencia} → {$cantidad}");
                        return true;
                    }
                } else {
                    // Stock nuevo - crear
                    $stockModel->referencia = $referencia;
                    $stockModel->codalmacen = $codalmacen;
                    $stockModel->cantidad = $cantidad;
                    $stockModel->disponible = $cantidad;
                    $stockModel->reservada = 0;
                    $stockModel->pterecibir = 0;

                    if ($stockModel->save()) {
                        Tools::log()->info("✓ Stock creado mediante modelo FacturaScripts: {$referencia} → {$cantidad}");
                        return true;
                    }
                }

                Tools::log()->warning("No se pudo guardar stock mediante modelo, intentando SQL directo");

            } catch (\Exception $modelEx) {
                Tools::log()->warning("Modelo Stock no disponible, usando SQL directo: " . $modelEx->getMessage());
            }

            // Fallback: SQL directo si el modelo no funciona
            $db = new \FacturaScripts\Core\Base\DataBase();
            $sqlCheck = "SELECT cantidad FROM stocks WHERE referencia = " . $db->var2str($referencia) .
                       " AND codalmacen = " . $db->var2str($codalmacen);
            $existing = $db->select($sqlCheck);

            if (!empty($existing)) {
                $sqlUpdate = "UPDATE stocks SET cantidad = " . $db->var2str($cantidad) .
                           ", disponible = " . $db->var2str($cantidad) .
                           ", reservada = 0, pterecibir = 0 WHERE referencia = " . $db->var2str($referencia) .
                           " AND codalmacen = " . $db->var2str($codalmacen);

                if ($db->exec($sqlUpdate)) {
                    Tools::log()->info("✓ Stock actualizado via SQL: {$referencia} → {$cantidad}");
                    return true;
                }
            } else {
                $sqlInsert = "INSERT INTO stocks (referencia, codalmacen, cantidad, disponible, reservada, pterecibir) VALUES (" .
                           $db->var2str($referencia) . ", " . $db->var2str($codalmacen) . ", " .
                           $db->var2str($cantidad) . ", " . $db->var2str($cantidad) . ", 0, 0)";

                if ($db->exec($sqlInsert)) {
                    Tools::log()->info("✓ Stock creado via SQL: {$referencia} → {$cantidad}");
                    return true;
                }
            }

            Tools::log()->error("No se pudo registrar stock");
            return false;

        } catch (\Exception $e) {
            Tools::log()->error("Error actualizando stock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el stock REAL de un producto desde PrestaShop
     * Solo se llama en el momento de importar, no en descarga masiva
     *
     * @param int $productId
     * @return int
     */
    private function getRealStock(int $productId): int
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id_product]' => $productId,
                'display' => '[quantity]'
            ];

            $xmlString = @$webService->get('stock_availables', null, null, $params);
            if ($xmlString === false) {
                return 0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false || !isset($xml->stock_availables->stock_available)) {
                return 0;
            }

            // Sumar todo el stock disponible (puede haber varios registros)
            $totalStock = 0;
            foreach ($xml->stock_availables->stock_available as $stock) {
                $totalStock += (int)$stock->quantity;
            }

            return $totalStock;

        } catch (\Exception $e) {
            Tools::log()->warning("Error obteniendo stock real para producto {$productId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Importa un producto a FacturaScripts (crea o actualiza)
     *
     * @param array $productData Datos del producto
     * @return bool
     */
    public function importProduct(array $productData): bool
    {
        // VERSIÓN 6.0 - CÓDIGO QUE FUNCIONABA PERFECTO - RESTAURADO
        Tools::log()->critical("=== IMPORTANDO PRODUCTO - VERSIÓN 6.0 RESTAURADA ===");

        try {
            $reference = $productData['reference'];

            // Buscar si el producto ya existe por referencia
            $variante = new Variante();
            $productoExists = $variante->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)]);

            if ($productoExists) {
                // ========== ACTUALIZAR PRODUCTO EXISTENTE ==========
                Tools::log()->info("Actualizando producto existente: {$reference}");

                // Cargar el producto asociado
                $producto = new Producto();
                if (!$producto->loadFromCode($variante->idproducto)) {
                    Tools::log()->error("No se pudo cargar el producto con ID: {$variante->idproducto}");
                    return false;
                }

                // Descargar imagen solo si está habilitado (para productos existentes)
                $imageData = null;
                if ($this->downloadImages && !empty($productData['image_url'])) {
                    Tools::log()->info("Descargando imagen para actualizar: {$reference}");
                    $imageData = $this->downloadImage($productData['image_url'], $reference);
                    if ($imageData) {
                        Tools::log()->info("Imagen descargada: {$imageData['filename']} (idfile={$imageData['idfile']})");
                    } else {
                        Tools::log()->warning("No se pudo descargar imagen");
                    }
                } elseif (!$this->downloadImages) {
                    Tools::log()->info("Descarga de imágenes deshabilitada - omitiendo imagen para: {$reference}");
                }

                // Actualizar datos del producto - CON REFERENCIA
                $producto->referencia = $reference;
                $producto->descripcion = $productData['name'];
                $producto->precio = $productData['price']; // Precio SIN IVA
                $producto->stockfis = $productData['stock']; // Stock de la combinación
                $producto->nostock = false;
                $producto->ventasinstock = true;  // Permitir venta sin stock
                $producto->sevende = true;  // Se puede vender
                $producto->secompra = true;  // Se puede comprar
                $producto->bloqueado = false;  // NO bloquear (si no, no aparece en listados)

                // Obtener codimpuesto según el id_tax_rules_group de PrestaShop
                $idTaxRulesGroup = $productData['id_tax_rules_group'] ?? 0;
                $taxRate = $this->getTaxRateFromPrestashop($idTaxRulesGroup);
                $codimpuesto = PrestashopTaxMap::getCodImpuesto($taxRate);
                if ($codimpuesto) {
                    $producto->codimpuesto = $codimpuesto;
                    Tools::log()->info("IVA mapeado: {$taxRate}% → {$codimpuesto}");
                } else {
                    $producto->codimpuesto = 'IVA21'; // Fallback
                    Tools::log()->warning("IVA {$taxRate}% no mapeado, usando IVA21 por defecto");
                }

                // NO asignar producto.imagen - se usa la tabla productos_imagenes
                // La imagen se vinculará después mediante linkFileToProduct()

                // Guardar producto UNA SOLA VEZ
                if (!$producto->save()) {
                    Tools::log()->error("Error actualizando producto: {$reference}");
                    return false;
                }

                Tools::log()->info("Producto guardado con ID: {$producto->idproducto}");

                // Vincular imagen (attached_files_rel + productos_imagenes)
                if ($imageData) {
                    $this->linkFileToProduct($imageData['idfile'], $producto->idproducto, $reference);
                    Tools::log()->info("Imagen vinculada via attached_files_rel y productos_imagenes (idfile={$imageData['idfile']})");
                }

                // Recargar la variante para asegurar datos frescos
                if (!$variante->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)])) {
                    Tools::log()->error("No se pudo recargar variante después de guardar producto: {$reference}");
                    return false;
                }

                // Actualizar stock y precio en la variante
                $variante->stockfis = $productData['stock'];
                $variante->precio = $productData['price']; // Precio SIN IVA
                $variante->coste = 0;

                if (!$variante->save()) {
                    Tools::log()->error("Error actualizando stock de variante: {$reference}");
                    return false;
                }

                // REGISTRAR MOVIMIENTO DE STOCK en tabla stocks (mediante modelo)
                if (!$this->actualizarStockVariante($variante->idvariante, $productData['stock'], $reference)) {
                    Tools::log()->warning("No se pudo actualizar stock en almacén para: {$reference}");
                }

                // Verificar que se guardó correctamente
                $varianteCheck = new Variante();
                if ($varianteCheck->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)])) {
                    Tools::log()->info("Verificación BD - Stock: {$varianteCheck->stockfis}, Precio (sin IVA): {$varianteCheck->precio}, ID Producto: {$varianteCheck->idproducto}");
                }

                Tools::log()->info("✓ Producto actualizado: {$reference} | Stock: {$productData['stock']} | Precio (sin IVA): {$productData['price']} | Bloqueado: " . ($producto->bloqueado ? 'SÍ' : 'NO'));

            } else {
                // ========== CREAR NUEVO PRODUCTO ==========
                Tools::log()->critical(">>> INICIANDO CREACIÓN DE PRODUCTO: {$reference}");

                // 1. Descargar imagen PRIMERO
                $imageData = null;
                if (!empty($productData['image_url'])) {
                    $imageData = $this->downloadImage($productData['image_url'], $reference);
                }

                // 2. Crear Producto CON REFERENCIA (necesaria para buscadores)
                $producto = new Producto();
                $producto->referencia = $reference;  // ← NECESARIA para que aparezca en buscadores
                $producto->descripcion = $productData['name'];
                $producto->precio = $productData['price'];
                $producto->nostock = false;
                $producto->ventasinstock = true;  // Permitir venta sin stock
                $producto->sevende = true;  // Se puede vender
                $producto->secompra = true;  // Se puede comprar
                $producto->bloqueado = false;  // ← SIEMPRE NO bloqueado (si está bloqueado NO aparece en listados)

                // Obtener codimpuesto según el id_tax_rules_group de PrestaShop
                $idTaxRulesGroup = $productData['id_tax_rules_group'] ?? 0;
                $taxRate = $this->getTaxRateFromPrestashop($idTaxRulesGroup);
                $codimpuesto = PrestashopTaxMap::getCodImpuesto($taxRate);
                if ($codimpuesto) {
                    $producto->codimpuesto = $codimpuesto;
                    Tools::log()->info("IVA mapeado: {$taxRate}% → {$codimpuesto}");
                } else {
                    $producto->codimpuesto = 'IVA21'; // Fallback
                    Tools::log()->warning("IVA {$taxRate}% no mapeado, usando IVA21 por defecto");
                }

                Tools::log()->critical(">>> GUARDANDO PRODUCTO (save) con referencia: {$reference} - bloqueado=FALSE - IVA={$producto->codimpuesto}");

                // 3. GUARDAR PRODUCTO
                if (!$producto->save()) {
                    Tools::log()->critical("✗✗✗ ERROR: producto->save() DEVOLVIÓ FALSE");
                    return false;
                }

                $idProductoCreado = $producto->idproducto;
                Tools::log()->critical(">>> producto->save() OK - ID devuelto: {$idProductoCreado}");

                // 4. PAUSA y VERIFICACIÓN: ¿Existe realmente en la BD?
                $db = new \FacturaScripts\Core\Base\DataBase();
                $sqlCheck = "SELECT idproducto, referencia, descripcion, precio FROM productos WHERE idproducto = {$idProductoCreado}";
                $resultCheck = $db->select($sqlCheck);

                if (empty($resultCheck)) {
                    Tools::log()->critical("✗✗✗ PRODUCTO NO EXISTE EN BD - ID {$idProductoCreado} NO SE ENCUENTRA");
                    return false;
                }

                Tools::log()->critical("✓✓✓ PRODUCTO SÍ EXISTE EN BD:");
                Tools::log()->critical("    ID: {$resultCheck[0]['idproducto']}");
                Tools::log()->critical("    REF: {$resultCheck[0]['referencia']}");
                Tools::log()->critical("    Desc: {$resultCheck[0]['descripcion']}");
                Tools::log()->critical("    Precio: {$resultCheck[0]['precio']}");

                // 4b. Verificar también buscando por referencia
                $sqlCheckRef = "SELECT idproducto FROM productos WHERE referencia = '{$reference}'";
                $resultCheckRef = $db->select($sqlCheckRef);

                if (empty($resultCheckRef)) {
                    Tools::log()->critical("✗✗✗ PRODUCTO NO SE ENCUENTRA POR REFERENCIA");
                } else {
                    Tools::log()->critical("✓✓✓ PRODUCTO ENCONTRADO POR REFERENCIA: ID={$resultCheckRef[0]['idproducto']}");
                }

                // 5. Ahora SÍ continuar con la variante
                Tools::log()->critical(">>> Obteniendo variante auto-creada...");
                $variante = $producto->getVariants()[0] ?? null;

                if (!$variante) {
                    Tools::log()->critical("✗✗✗ NO HAY VARIANTE AUTO-CREADA");
                    return false;
                }

                Tools::log()->critical(">>> Variante obtenida - ID: {$variante->idvariante}");

                // 6. Asignar REFERENCIA a la VARIANTE
                $variante->referencia = $reference;
                $variante->stockfis = $productData['stock'];
                $variante->precio = $productData['price'];
                $variante->coste = 0;

                if (!$variante->save()) {
                    Tools::log()->critical("✗✗✗ ERROR guardando variante");
                    return false;
                }

                Tools::log()->critical("✓✓✓ Variante guardada con referencia: {$reference}");

                // 7. VERIFICAR que variante existe en BD
                $sqlVarCheck = "SELECT idvariante, referencia, idproducto FROM variantes WHERE referencia = '{$reference}'";
                $varResult = $db->select($sqlVarCheck);

                if (!empty($varResult)) {
                    Tools::log()->critical("✓✓✓ VARIANTE EN BD: ID={$varResult[0]['idvariante']}, Ref={$varResult[0]['referencia']}, IdProd={$varResult[0]['idproducto']}");
                } else {
                    Tools::log()->critical("✗✗✗ VARIANTE NO EN BD");
                }

                // 8. Vincular imagen
                if ($imageData) {
                    $this->linkFileToProduct($imageData['idfile'], $producto->idproducto, $reference);
                }

                // 9. Registrar stock
                $this->actualizarStockVariante($variante->idvariante, $productData['stock'], $reference);

                Tools::log()->critical(">>> CREACIÓN COMPLETADA");
            }

            return true;

        } catch (\Exception $e) {
            Tools::log()->error("Error importando producto {$productData['reference']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el porcentaje de IVA de un producto desde PrestaShop
     * Usa cache para evitar peticiones repetidas
     *
     * @param int $idTaxRulesGroup
     * @return float
     */
    private function getTaxRateFromPrestashop(int $idTaxRulesGroup): float
    {
        if ($idTaxRulesGroup <= 0) {
            return 21.0; // IVA por defecto
        }

        // Comprobar cache
        if (isset($this->taxRateCache[$idTaxRulesGroup])) {
            return $this->taxRateCache[$idTaxRulesGroup];
        }

        try {
            $webService = $this->connection->getWebService();

            // Obtener la regla de impuesto
            $xmlString = @$webService->get('tax_rules', null, null, [
                'filter[id_tax_rules_group]' => $idTaxRulesGroup,
                'display' => '[id_tax]',
                'limit' => 1
            ]);

            if ($xmlString === false) {
                $this->taxRateCache[$idTaxRulesGroup] = 21.0;
                return 21.0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false || !isset($xml->tax_rules->tax_rule)) {
                $this->taxRateCache[$idTaxRulesGroup] = 21.0;
                return 21.0;
            }

            $idTax = (int)$xml->tax_rules->tax_rule->id_tax;

            // Obtener el tax rate usando filtro (compatible con librería tipada)
            $xmlString = @$webService->get('taxes', null, null, [
                'filter[id]' => $idTax,
                'display' => '[rate]',
                'limit' => 1
            ]);
            if ($xmlString === false) {
                $this->taxRateCache[$idTaxRulesGroup] = 21.0;
                return 21.0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false || !isset($xml->taxes->tax)) {
                $this->taxRateCache[$idTaxRulesGroup] = 21.0;
                return 21.0;
            }

            // Manejar respuesta con múltiples taxes
            $tax = $xml->taxes->tax;
            if (is_array($tax) || $tax instanceof \Traversable) {
                foreach ($tax as $t) {
                    $tax = $t;
                    break;
                }
            }

            if (!isset($tax->rate)) {
                $this->taxRateCache[$idTaxRulesGroup] = 21.0;
                return 21.0;
            }

            $taxRate = (float)$tax->rate;

            // Guardar en cache
            $this->taxRateCache[$idTaxRulesGroup] = $taxRate;

            return $taxRate;

        } catch (\Exception $e) {
            Tools::log()->debug("No se pudo obtener tax rate para id_tax_rules_group {$idTaxRulesGroup}: " . $e->getMessage());
            $this->taxRateCache[$idTaxRulesGroup] = 21.0;
            return 21.0; // IVA por defecto
        }
    }

    /**
     * Actualiza SOLO el stock de un producto existente
     * NO toca precios, imágenes ni otros campos
     *
     * @param array $productData Datos del producto (debe incluir reference y stock)
     * @return bool
     */
    public function updateStockOnly(array $productData): bool
    {
        try {
            $reference = $productData['reference'];
            $stock = $productData['stock'] ?? 0;

            Tools::log()->info("Actualizando SOLO stock: {$reference} → {$stock}");

            // Buscar variante existente
            $variante = new Variante();
            if (!$variante->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)])) {
                Tools::log()->warning("Producto no existe, no se puede actualizar stock: {$reference}");
                return false;
            }

            // Actualizar stock en variante
            $variante->stockfis = $stock;

            if (!$variante->save()) {
                Tools::log()->error("Error actualizando stock de variante: {$reference}");
                return false;
            }

            // Registrar stock en almacén
            if (!$this->actualizarStockVariante($variante->idvariante, $stock, $reference)) {
                Tools::log()->warning("No se pudo actualizar stock en almacén para: {$reference}");
            }

            Tools::log()->info("✓ Stock actualizado: {$reference} → {$stock}");
            return true;

        } catch (\Exception $e) {
            Tools::log()->error("Error actualizando stock de {$productData['reference']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza SOLO los precios de un producto existente
     * NO toca stock, imágenes ni otros campos
     *
     * @param array $productData Datos del producto (debe incluir reference y price)
     * @return bool
     */
    public function updatePricesOnly(array $productData): bool
    {
        try {
            $reference = $productData['reference'];
            $price = $productData['price'] ?? 0;

            Tools::log()->info("Actualizando SOLO precio: {$reference} → {$price}");

            // Buscar variante existente
            $variante = new Variante();
            if (!$variante->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)])) {
                Tools::log()->warning("Producto no existe, no se puede actualizar precio: {$reference}");
                return false;
            }

            // Cargar el producto asociado
            $producto = new Producto();
            if (!$producto->loadFromCode($variante->idproducto)) {
                Tools::log()->error("No se pudo cargar el producto con ID: {$variante->idproducto}");
                return false;
            }

            // Actualizar precio en producto
            $producto->precio = $price;

            if (!$producto->save()) {
                Tools::log()->error("Error actualizando precio de producto: {$reference}");
                return false;
            }

            // Actualizar precio en variante
            $variante->precio = $price;

            if (!$variante->save()) {
                Tools::log()->error("Error actualizando precio de variante: {$reference}");
                return false;
            }

            Tools::log()->info("✓ Precio actualizado: {$reference} → {$price}");
            return true;

        } catch (\Exception $e) {
            Tools::log()->error("Error actualizando precio de {$productData['reference']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza stock Y precios de un producto existente
     * NO toca imágenes ni otros campos
     *
     * @param array $productData Datos del producto (debe incluir reference, stock y price)
     * @return bool
     */
    public function updateStockAndPrices(array $productData): bool
    {
        try {
            $reference = $productData['reference'];
            $stock = $productData['stock'] ?? 0;
            $price = $productData['price'] ?? 0;

            Tools::log()->info("Actualizando stock Y precio: {$reference} → Stock: {$stock}, Precio: {$price}");

            // Buscar variante existente
            $variante = new Variante();
            if (!$variante->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $reference)])) {
                Tools::log()->warning("Producto no existe, no se puede actualizar: {$reference}");
                return false;
            }

            // Cargar el producto asociado
            $producto = new Producto();
            if (!$producto->loadFromCode($variante->idproducto)) {
                Tools::log()->error("No se pudo cargar el producto con ID: {$variante->idproducto}");
                return false;
            }

            // Actualizar precio en producto
            $producto->precio = $price;

            if (!$producto->save()) {
                Tools::log()->error("Error actualizando producto: {$reference}");
                return false;
            }

            // Actualizar stock y precio en variante
            $variante->stockfis = $stock;
            $variante->precio = $price;

            if (!$variante->save()) {
                Tools::log()->error("Error actualizando variante: {$reference}");
                return false;
            }

            // Registrar stock en almacén
            if (!$this->actualizarStockVariante($variante->idvariante, $stock, $reference)) {
                Tools::log()->warning("No se pudo actualizar stock en almacén para: {$reference}");
            }

            Tools::log()->info("✓ Stock y precio actualizados: {$reference} → Stock: {$stock}, Precio: {$price}");
            return true;

        } catch (\Exception $e) {
            Tools::log()->error("Error actualizando stock y precio de {$productData['reference']}: " . $e->getMessage());
            return false;
        }
    }
}
