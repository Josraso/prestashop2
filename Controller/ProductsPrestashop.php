<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopProductsTemp;
use FacturaScripts\Plugins\Prestashop\Lib\Actions\ProductsDownload;

/**
 * Controlador para gestionar productos de PrestaShop
 */
class ProductsPrestashop extends Controller
{
    /** @var array */
    public $products = [];

    /** @var PrestashopConfig */
    public $config;

    /** @var bool */
    public $productsLoaded = false;

    /** @var int */
    public $totalProducts = 0;

    /** @var int */
    public $importedCount = 0;

    /** @var int */
    public $errorCount = 0;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'warehouse';
        $data['title'] = 'Productos PrestaShop';
        $data['icon'] = 'fas fa-box-open';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        // Asegurar que hay sesión iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Cargar configuración
        $this->config = PrestashopConfig::getActive();

        // Procesar acciones
        $action = $this->request->request->get('action', '');

        // Para peticiones JSON, obtener el action del cuerpo
        if (empty($action)) {
            $jsonInput = file_get_contents('php://input');
            if (!empty($jsonInput)) {
                $jsonData = json_decode($jsonInput, true);
                if (isset($jsonData['action'])) {
                    $action = $jsonData['action'];
                }
            }
        }

        switch ($action) {
            case 'download-products-direct':
                $this->downloadProductsDirectAction();
                return; // No renderizar vista, solo devolver JSON

            case 'get-total-products':
                $this->getTotalProductsAction();
                return; // No renderizar vista, solo devolver JSON

            case 'import-products-json':
                $this->importProductsJsonAction();
                return; // No renderizar vista, solo devolver JSON

            case 'update-ventasinstock':
                $this->updateVentaSinStockAction();
                return; // No renderizar vista, solo devolver JSON

            case 'update-products-stock':
                $this->updateProductsStockAction();
                return; // No renderizar vista, solo devolver JSON

            case 'update-products-prices':
                $this->updateProductsPricesAction();
                return; // No renderizar vista, solo devolver JSON

            case 'update-products-both':
                $this->updateProductsBothAction();
                return; // No renderizar vista, solo devolver JSON
        }
    }

    /**
     * Obtiene el total de productos (petición AJAX)
     */
    private function getTotalProductsAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        if (!$this->config) {
            $this->returnJson(['error' => 'PrestaShop no configurado']);
            return;
        }

        try {
            $downloader = new ProductsDownload();
            $total = $downloader->getTotalProducts();

            $this->returnJson([
                'success' => true,
                'total' => $total
            ]);

        } catch (\Exception $e) {
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Descarga productos por lotes (petición AJAX)
     */
    /**
     * Descarga productos DIRECTAMENTE sin tabla temporal
     * Los productos se acumulan en JavaScript
     */
    private function downloadProductsDirectAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        if (!$this->config) {
            $this->returnJson(['error' => 'PrestaShop no configurado']);
            return;
        }

        try {
            $offset = (int)$this->request->request->get('offset', 0);
            $limit = (int)$this->request->request->get('limit', 50);

            Tools::log()->critical("→ DESCARGA DIRECTA - Lote offset:{$offset}, limit:{$limit}");

            $downloader = new ProductsDownload();
            $result = $downloader->getAllProducts($offset, $limit);

            Tools::log()->critical("✓ Lote descargado: " . count($result['products']) . " productos devueltos a JavaScript");

            // Devolver productos directamente sin guardar en BD
            $this->returnJson([
                'success' => true,
                'products' => $result['products'],
                'offset' => $offset,
                'in_batch' => count($result['products'])
            ]);

        } catch (\Exception $e) {
            Tools::log()->error('ERROR en descarga directa: ' . $e->getMessage());
            Tools::log()->error('Stack trace: ' . $e->getTraceAsString());
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Devuelve respuesta JSON y termina la ejecución
     */
    private function returnJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    /**
     * Importa productos enviados como JSON desde JavaScript
     */
    private function importProductsJsonAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        try {
            // Obtener datos JSON del cuerpo de la petición
            $jsonInput = file_get_contents('php://input');
            $data = json_decode($jsonInput, true);

            if (!isset($data['products']) || !is_array($data['products'])) {
                $this->returnJson(['error' => 'Datos inválidos']);
                return;
            }

            $products = $data['products'];
            $downloadImages = $data['download_images'] ?? true; // Por defecto descargar imágenes

            Tools::log()->info('========================================');
            Tools::log()->info('IMPORTANDO PRODUCTOS DESDE JAVASCRIPT');
            Tools::log()->info('Total productos a importar: ' . count($products));
            Tools::log()->info('Descargar imágenes: ' . ($downloadImages ? 'SÍ' : 'NO'));
            Tools::log()->info('========================================');

            $downloader = new ProductsDownload();
            $downloader->setDownloadImages($downloadImages);
            $importedCount = 0;
            $errorCount = 0;
            $errorReferences = []; // Referencias de productos con error

            foreach ($products as $product) {
                // Verificar que tenga referencia
                if (empty($product['reference'])) {
                    Tools::log()->warning("Producto sin referencia omitido: {$product['name']}");
                    $errorCount++;
                    $errorReferences[] = $product['name'] ?? 'Sin nombre';
                    continue;
                }

                // Importar producto
                $reference = $product['reference'];
                $result = $downloader->importProduct($product);

                if ($result) {
                    $importedCount++;
                } else {
                    $errorCount++;
                    $errorReferences[] = $reference;
                }
            }

            Tools::log()->info('========================================');
            Tools::log()->info("IMPORTACIÓN COMPLETADA");
            Tools::log()->info("Productos importados: {$importedCount}");
            Tools::log()->info("Errores: {$errorCount}");
            Tools::log()->info('========================================');

            $this->returnJson([
                'success' => true,
                'imported' => $importedCount,
                'errors' => $errorCount,
                'error_references' => $errorReferences // Referencias de productos con error
            ]);

        } catch (\Exception $e) {
            Tools::log()->error('Error importando productos JSON: ' . $e->getMessage());
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualiza todos los productos en FacturaScripts para permitir venta sin stock
     */
    private function updateVentaSinStockAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        try {
            Tools::log()->info('========================================');
            Tools::log()->info('ACTUALIZANDO VENTASINSTOCK EN TODOS LOS PRODUCTOS');
            Tools::log()->info('========================================');

            $db = new \FacturaScripts\Core\Base\DataBase();

            // Actualizar todos los productos en la tabla productos
            $sql = "UPDATE productos SET ventasinstock = TRUE WHERE ventasinstock = FALSE OR ventasinstock IS NULL";

            if (!$db->exec($sql)) {
                throw new \Exception('Error actualizando productos: ' . $db->lastError());
            }

            // Obtener el número de productos actualizados
            $sqlCount = "SELECT COUNT(*) as total FROM productos WHERE ventasinstock = TRUE";
            $result = $db->select($sqlCount);
            $totalUpdated = $result[0]['total'] ?? 0;

            Tools::log()->info('========================================');
            Tools::log()->info("ACTUALIZACIÓN COMPLETADA");
            Tools::log()->info("Productos con ventasinstock=TRUE: {$totalUpdated}");
            Tools::log()->info('========================================');

            $this->returnJson([
                'success' => true,
                'updated' => $totalUpdated
            ]);

        } catch (\Exception $e) {
            Tools::log()->error('Error actualizando ventasinstock: ' . $e->getMessage());
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualiza solo el stock de productos seleccionados
     */
    private function updateProductsStockAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        try {
            $jsonInput = file_get_contents('php://input');
            $data = json_decode($jsonInput, true);

            if (!isset($data['products']) || !is_array($data['products'])) {
                $this->returnJson(['error' => 'Datos inválidos']);
                return;
            }

            $products = $data['products'];

            Tools::log()->info('========================================');
            Tools::log()->info('ACTUALIZANDO SOLO STOCK');
            Tools::log()->info('Total productos: ' . count($products));
            Tools::log()->info('========================================');

            $downloader = new ProductsDownload();
            $updated = 0;
            $errors = 0;

            foreach ($products as $product) {
                if (empty($product['reference'])) {
                    $errors++;
                    continue;
                }

                $result = $downloader->updateStockOnly($product);
                if ($result) {
                    $updated++;
                } else {
                    $errors++;
                }
            }

            Tools::log()->info("Stock actualizado: {$updated} productos, {$errors} errores");

            $this->returnJson([
                'success' => true,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Tools::log()->error('Error actualizando stock: ' . $e->getMessage());
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualiza solo los precios de productos seleccionados
     */
    private function updateProductsPricesAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        try {
            $jsonInput = file_get_contents('php://input');
            $data = json_decode($jsonInput, true);

            if (!isset($data['products']) || !is_array($data['products'])) {
                $this->returnJson(['error' => 'Datos inválidos']);
                return;
            }

            $products = $data['products'];

            Tools::log()->info('========================================');
            Tools::log()->info('ACTUALIZANDO SOLO PRECIOS');
            Tools::log()->info('Total productos: ' . count($products));
            Tools::log()->info('========================================');

            $downloader = new ProductsDownload();
            $updated = 0;
            $errors = 0;

            foreach ($products as $product) {
                if (empty($product['reference'])) {
                    $errors++;
                    continue;
                }

                $result = $downloader->updatePricesOnly($product);
                if ($result) {
                    $updated++;
                } else {
                    $errors++;
                }
            }

            Tools::log()->info("Precios actualizados: {$updated} productos, {$errors} errores");

            $this->returnJson([
                'success' => true,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Tools::log()->error('Error actualizando precios: ' . $e->getMessage());
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }

    /**
     * Actualiza stock y precios de productos seleccionados
     */
    private function updateProductsBothAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            $this->returnJson(['error' => 'Sin permisos']);
            return;
        }

        try {
            $jsonInput = file_get_contents('php://input');
            $data = json_decode($jsonInput, true);

            if (!isset($data['products']) || !is_array($data['products'])) {
                $this->returnJson(['error' => 'Datos inválidos']);
                return;
            }

            $products = $data['products'];

            Tools::log()->info('========================================');
            Tools::log()->info('ACTUALIZANDO STOCK Y PRECIOS');
            Tools::log()->info('Total productos: ' . count($products));
            Tools::log()->info('========================================');

            $downloader = new ProductsDownload();
            $updated = 0;
            $errors = 0;

            foreach ($products as $product) {
                if (empty($product['reference'])) {
                    $errors++;
                    continue;
                }

                $result = $downloader->updateStockAndPrices($product);
                if ($result) {
                    $updated++;
                } else {
                    $errors++;
                }
            }

            Tools::log()->info("Stock y precios actualizados: {$updated} productos, {$errors} errores");

            $this->returnJson([
                'success' => true,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Tools::log()->error('Error actualizando stock y precios: ' . $e->getMessage());
            $this->returnJson(['error' => $e->getMessage()]);
        }
    }
}
