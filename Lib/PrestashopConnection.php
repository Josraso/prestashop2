<?php

namespace FacturaScripts\Plugins\Prestashop\Lib;

use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;

/**
 * Clase para gestionar la conexión con PrestaShop
 */
class PrestashopConnection
{
    /** @var \prestashop\prestashopWebserviceLib\Shared\Application\PrestaShopWebservice */
    private $webService;

    /** @var PrestashopConfig */
    private $config;

    public function __construct(?PrestashopConfig $config = null)
    {
        $this->config = $config ?? PrestashopConfig::getActive();

        if ($this->config && $this->config->shop_url && $this->config->api_key) {
            // El 4to parámetro determina si se usa ws_key como parámetro GET
            // true = enviar ws_key como parámetro (solución para servidores con problemas en .htaccess)
            // false = usar Basic Auth (método por defecto)
            $useWsKeyParam = isset($this->config->use_ws_key_param) ? (bool)$this->config->use_ws_key_param : false;

            $this->webService = new \prestashop\prestashopWebserviceLib\Shared\Application\PrestaShopWebservice(
                $this->config->shop_url,
                $this->config->api_key,
                false, // debug
                $useWsKeyParam // ws_key como parámetro GET
            );
        }
    }

    /**
     * Verifica si la conexión está disponible
     */
    public function isConnected(): bool
    {
        return $this->webService !== null;
    }

    /**
     * Obtiene el servicio web de PrestaShop
     */
    public function getWebService(): ?\prestashop\prestashopWebserviceLib\Shared\Application\PrestaShopWebservice
    {
        return $this->webService;
    }

    /**
     * Obtiene la configuración
     */
    public function getConfig(): ?PrestashopConfig
    {
        return $this->config;
    }

    /**
     * Obtiene los estados de pedidos de PrestaShop
     */
    public function getOrderStates(): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            // Obtener todos los estados con información completa
            $xmlString = $this->webService->get('order_states');
            $xml = simplexml_load_string($xmlString);

            $states = [];

            // Verificar si hay estados en la respuesta
            if (isset($xml->order_states->order_state)) {
                foreach ($xml->order_states->order_state as $state) {
                    $id = (int)$state->id;
                    $nombreEstado = '';

                    // Intentar obtener el nombre del estado
                    if (isset($state->name->language)) {
                        // Si hay múltiples idiomas, tomar el primero
                        if (is_array($state->name->language) || $state->name->language instanceof \Traversable) {
                            foreach ($state->name->language as $lang) {
                                $nombreEstado = (string)$lang;
                                break;
                            }
                        } else {
                            // Solo un idioma
                            $nombreEstado = (string)$state->name->language;
                        }
                    } elseif (isset($state->name)) {
                        // Fallback: usar directamente el nombre si no hay idiomas
                        $nombreEstado = (string)$state->name;
                    } else {
                        // Último recurso: usar el ID como nombre
                        $nombreEstado = 'Estado ' . $id;
                    }

                    // Añadir ID entre corchetes al principio del nombre
                    $states[$id] = '[' . $id . '] ' . $nombreEstado;
                }
            }

            return $states;
        } catch (\Exception $e) {
            // Log del error para debug
            \FacturaScripts\Core\Tools::log()->error('Error obteniendo estados de PrestaShop: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los pedidos de PrestaShop filtrados por estado
     *
     * @param int|null $limit Límite de resultados
     * @param int|null $sinceId Filtro por ID mínimo (desde qué pedido)
     * @param array $customFilters Filtros adicionales personalizados
     */
    public function getOrders(?int $limit = null, ?int $sinceId = null, array $customFilters = []): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            $params = [
                'sort' => 'id_ASC',
                'display' => 'full' // Obtener datos completos de una vez
            ];

            // Filtrar por estados si están configurados
            $estadosImportar = $this->config->getEstadosArray();
            if (!empty($estadosImportar)) {
                $params['filter[current_state]'] = '[' . implode('|', $estadosImportar) . ']';
            }

            if ($limit) {
                $params['limit'] = $limit;
            }

            // Filtro por ID mínimo
            if ($sinceId) {
                $params['filter[id]'] = '[' . $sinceId . ',999999999]';
            }

            // NOTA: PrestaShop API NO acepta múltiples filtros complejos (ej: current_state + date_add)
            // Los customFilters se ignoran - filtrar en PHP después de obtener resultados
            // NO añadir customFilters aquí porque genera error 400

            // Llamar a la API con parámetros correctos
            $xmlString = $this->webService->get('orders', null, null, $params);
            $xml = simplexml_load_string($xmlString);
            $orders = [];

            if (isset($xml->orders->order)) {
                // Con display=full, ya tenemos todos los datos
                foreach ($xml->orders->order as $order) {
                    // Verificar que el pedido tiene ID válido
                    $orderId = isset($order->id) ? (int)$order->id : (int)$order['id'];
                    if ($orderId > 0) {
                        $orders[] = $order;
                    }
                }
            }

            return $orders;
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener pedidos: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene el detalle de un pedido específico
     */
    public function getOrder(int $orderId): ?\SimpleXMLElement
    {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            // SOLUCIÓN: En versiones 1.7.6.9 y 1.7.7.8, pedir orders/$id devuelve solo atributos
            // En su lugar, pedimos la lista con filtro por ID para obtener datos completos
            $params = [
                'filter[id]' => '[' . $orderId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $this->webService->get('orders', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // La respuesta viene como <orders><order>...</order></orders>
            if (isset($xml->orders->order)) {
                $order = $xml->orders->order;

                // Si es un array de pedidos, tomar el primero
                if (is_array($order) || $order instanceof \Traversable) {
                    foreach ($order as $o) {
                        return $o;
                    }
                } else {
                    // Es un solo pedido
                    return $order;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene los productos de un pedido
     */
    public function getOrderProducts(int $orderId): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            $xmlString = $this->webService->get('orders/' . $orderId);
            $xml = simplexml_load_string($xmlString);

            $products = [];
            if (isset($xml->order->associations->order_rows->order_row)) {
                foreach ($xml->order->associations->order_rows->order_row as $row) {
                    $unitPriceTaxIncl = (float)$row->unit_price_tax_incl;
                    $unitPriceTaxExcl = (float)$row->unit_price_tax_excl;

                    // Calcular el tax_rate (porcentaje de IVA)
                    $taxRate = 0;
                    if ($unitPriceTaxExcl > 0) {
                        $taxRate = (($unitPriceTaxIncl / $unitPriceTaxExcl) - 1) * 100;
                        $taxRate = round($taxRate, 2); // Redondear a 2 decimales
                    }

                    $products[] = [
                        'product_id' => (int)$row->product_id,
                        'product_reference' => (string)$row->product_reference,
                        'product_name' => (string)$row->product_name,
                        'product_quantity' => (int)$row->product_quantity,
                        'unit_price_tax_incl' => $unitPriceTaxIncl,
                        'unit_price_tax_excl' => $unitPriceTaxExcl,
                        'tax_rate' => $taxRate, // Añadir el tax_rate calculado
                    ];
                }
            }

            return $products;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene un producto específico con ecotax desde ps_product.ecotax
     *
     * @param int $productId ID del producto
     * @return array|null Datos del producto con ecotax o null si falla
     */
    public function getProduct(int $productId): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            // Usar JSON en lugar de XML - más rápido y simple
            // Intentar con parámetro en array (4º argumento)
            $jsonString = $this->webService->get("products/{$productId}", null, null, ['output_format' => 'JSON']);
            $data = json_decode($jsonString, true);

            if (!$data) {
                \FacturaScripts\Core\Tools::log()->error("getProduct({$productId}): Error al parsear JSON");
                \FacturaScripts\Core\Tools::log()->error("getProduct({$productId}): Respuesta raw: " . substr($jsonString, 0, 500));
                return null;
            }

            // DEBUG: Ver estructura del JSON recibido
            \FacturaScripts\Core\Tools::log()->warning("getProduct({$productId}): Claves raíz JSON: " . implode(', ', array_keys($data)));

            // COMPATIBILIDAD: Manejar diferentes estructuras de respuesta
            $product = null;

            // Opción 1: {"products": [{"id": ..., "ecotax": ...}]}  - ARRAY
            if (isset($data['products'][0])) {
                $product = $data['products'][0];
                \FacturaScripts\Core\Tools::log()->warning("getProduct({$productId}): JSON con array 'products[0]' ✓");
            }
            // Opción 2: {"prestashop": {"product": {...}}}
            elseif (isset($data['prestashop']['product'])) {
                $product = $data['prestashop']['product'];
                \FacturaScripts\Core\Tools::log()->warning("getProduct({$productId}): JSON con wrapper 'prestashop.product'");
            }
            // Opción 3: {"product": {...}}
            elseif (isset($data['product'])) {
                $product = $data['product'];
                \FacturaScripts\Core\Tools::log()->warning("getProduct({$productId}): JSON con wrapper 'product'");
            }
            // Opción 4: {"id": ...} directo
            elseif (isset($data['id'])) {
                $product = $data;
                \FacturaScripts\Core\Tools::log()->warning("getProduct({$productId}): JSON directo sin wrapper");
            }
            // No se encontró estructura válida
            else {
                \FacturaScripts\Core\Tools::log()->error("getProduct({$productId}): Estructura JSON no reconocida");
                \FacturaScripts\Core\Tools::log()->error("getProduct({$productId}): JSON completo: " . substr($jsonString, 0, 1000));
                return null;
            }

            // Leer ecotax - puede venir como string, float, o vacío
            $ecotax = 0.0;
            if (isset($product['ecotax'])) {
                $ecotaxValue = $product['ecotax'];
                // Convertir a float manejando strings vacíos
                $ecotax = is_numeric($ecotaxValue) ? (float)$ecotaxValue : 0.0;

                \FacturaScripts\Core\Tools::log()->debug("getProduct({$productId}): ecotax raw = '{$ecotaxValue}' → parsed = {$ecotax}");
            } else {
                \FacturaScripts\Core\Tools::log()->debug("getProduct({$productId}): campo 'ecotax' no existe en JSON");
            }

            return [
                'id' => (int)$product['id'],
                'ecotax' => $ecotax,  // ECOTASA del producto (con IVA incluido)
            ];
        } catch (\Exception $e) {
            \FacturaScripts\Core\Tools::log()->error("ERROR getProduct({$productId}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene los detalles de un pedido (order_details) con campos ecotax
     * Este endpoint incluye campos que NO están en order_rows como ecotax
     *
     * @param int $orderId ID del pedido
     * @return array Array de order_details con campos ecotax
     */
    public function getOrderDetails(int $orderId): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            // Obtener order_details filtrados por id_order con display=full
            $params = [
                'filter[id_order]' => $orderId,  // SIN corchetes en el valor
                'display' => 'full'
            ];

            $xmlString = $this->webService->get('order_details?' . http_build_query($params));
            $xml = simplexml_load_string($xmlString);

            $details = [];
            if (isset($xml->order_details->order_detail)) {
                foreach ($xml->order_details->order_detail as $detail) {
                    $details[] = [
                        'id_order_detail' => (int)$detail->id,
                        'product_id' => (int)$detail->product_id,
                        'product_reference' => (string)$detail->product_reference,
                        'product_name' => (string)$detail->product_name,
                        'product_quantity' => (int)$detail->product_quantity,
                        'unit_price_tax_incl' => (float)$detail->unit_price_tax_incl,
                        'unit_price_tax_excl' => (float)$detail->unit_price_tax_excl,
                        'total_price_tax_incl' => (float)$detail->total_price_tax_incl,
                        'total_price_tax_excl' => (float)$detail->total_price_tax_excl,
                        'ecotax' => (float)$detail->ecotax,  // ECOTASA CON IVA
                        'ecotax_tax_rate' => (float)$detail->ecotax_tax_rate,  // IVA de la ecotasa
                    ];
                }
            }

            return $details;
        } catch (\Exception $e) {
            // Log del error para debug
            error_log("ERROR getOrderDetails(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la dirección de un pedido
     */
    public function getAddress(int $addressId): ?\SimpleXMLElement
    {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            // MISMO FIX que getOrder: usar filtro con display=full
            // En versiones 1.7.6.9/1.7.7.8, addresses/$id puede devolver solo atributos
            $params = [
                'filter[id]' => '[' . $addressId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $this->webService->get('addresses', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // La respuesta viene como <addresses><address>...</address></addresses>
            if (isset($xml->addresses->address)) {
                $address = $xml->addresses->address;

                // Si es un array, tomar el primero
                if (is_array($address) || $address instanceof \Traversable) {
                    foreach ($address as $a) {
                        return $a;
                    }
                } else {
                    // Es una sola dirección
                    return $address;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene un cliente
     */
    public function getCustomer(int $customerId): ?\SimpleXMLElement
    {
        if (!$this->isConnected()) {
            return null;
        }

        try {
            // IMPORTANTE: Usar display=full para obtener TODOS los datos del cliente (email, etc.)
            // Sin display=full, PrestaShop puede devolver solo algunos campos
            $params = [
                'filter[id]' => '[' . $customerId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $this->webService->get('customers', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // La respuesta viene como <customers><customer>...</customer></customers>
            if (isset($xml->customers->customer)) {
                $customer = $xml->customers->customer;

                // Si es un array, tomar el primero
                if (is_array($customer) || $customer instanceof \Traversable) {
                    foreach ($customer as $c) {
                        return $c;
                    }
                } else {
                    // Es un solo cliente
                    return $customer;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene el historial de estados de un pedido
     */
    public function getOrderHistory(int $orderId): array
    {
        if (!$this->isConnected()) {
            return [];
        }

        try {
            // Obtener todos los historiales del pedido con información completa
            $params = [
                'filter[id_order]' => $orderId,
                'display' => 'full',
                'sort' => '[id_DESC]' // Ordenar por ID descendente
            ];

            $xmlString = $this->webService->get('order_histories', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            $history = [];
            if (isset($xml->order_histories->order_history)) {
                foreach ($xml->order_histories->order_history as $item) {
                    $history[] = $item;
                }
            }

            return $history;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtiene información de un país desde PrestaShop
     */
    public function getCountry(int $countryId): ?\SimpleXMLElement
    {
        if (!$this->isConnected() || $countryId <= 0) {
            return null;
        }

        try {
            $params = [
                'filter[id]' => '[' . $countryId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $this->webService->get('countries', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            if (isset($xml->countries->country)) {
                $country = $xml->countries->country;

                if (is_array($country) || $country instanceof \Traversable) {
                    foreach ($country as $c) {
                        return $c;
                    }
                } else {
                    return $country;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene el nombre de un estado (provincia/región)
     */
    public function getStateName(int $stateId): ?string
    {
        if (!$this->isConnected() || $stateId <= 0) {
            return null;
        }

        try {
            // MISMO FIX: usar filtro con display=full
            $params = [
                'filter[id]' => '[' . $stateId . ']',
                'display' => 'full',
                'limit' => 1
            ];

            $xmlString = $this->webService->get('states', null, null, $params);
            $xml = simplexml_load_string($xmlString);

            // La respuesta viene como <states><state>...</state></states>
            if (isset($xml->states->state)) {
                $state = $xml->states->state;

                // Si es un array, tomar el primero
                if (is_array($state) || $state instanceof \Traversable) {
                    foreach ($state as $s) {
                        if (isset($s->name)) {
                            return (string)$s->name;
                        }
                    }
                } else {
                    // Es un solo estado
                    if (isset($state->name)) {
                        return (string)$state->name;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
