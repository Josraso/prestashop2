<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Prestashop\Lib\PrestashopConnection;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use FacturaScripts\Dinamic\Model\AlbaranCliente;

/**
 * Controlador para listar pedidos de PrestaShop y ver estado de importación
 */
class ListPedidosPrestashop extends Controller
{
    /** @var array */
    public $pedidos = [];

    /** @var int */
    public $totalPedidos = 0;

    /** @var int */
    public $importados = 0;

    /** @var int */
    public $pendientes = 0;

    /** @var int */
    public $limit = 100;

    /** @var int */
    public $offset = 0;

    /** @var int */
    public $page = 1;

    /** @var int */
    public $totalPages = 1;

    /** @var int */
    public $filterIdFrom = 0;

    /** @var int */
    public $filterIdTo = 0;

    /** @var string */
    public $filterImportado = 'todos'; // todos, importado, pendiente

    /** @var string */
    public $filterReference = '';

    /** @var string */
    public $filterDateFrom = '';

    /** @var string */
    public $filterDateTo = '';

    /** @var bool */
    public $hasFilters = false;

    /** @var array */
    public $estadosPrestaShop = [];

    /** @var int */
    public $filterState = 0;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'Pedidos PrestaShop';
        $data['icon'] = 'fas fa-shopping-cart';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        // Obtener filtros
        $this->filterIdFrom = (int)$this->request->query->get('id_from', 0);
        $this->filterIdTo = (int)$this->request->query->get('id_to', 0);
        $this->filterImportado = $this->request->query->get('importado', 'todos');
        $this->filterReference = trim($this->request->query->get('reference', ''));
        $this->filterDateFrom = $this->request->query->get('date_from', '');
        $this->filterDateTo = $this->request->query->get('date_to', '');
        $this->filterState = (int)$this->request->query->get('state', 0);
        $this->limit = (int)$this->request->query->get('limit', 100);
        $this->page = (int)$this->request->query->get('page', 1);
        $this->offset = ($this->page - 1) * $this->limit;

        // Verificar si hay algún filtro activo
        $this->hasFilters = (
            $this->filterIdFrom > 0 ||
            $this->filterIdTo > 0 ||
            !empty($this->filterReference) ||
            !empty($this->filterDateFrom) ||
            !empty($this->filterDateTo) ||
            $this->filterState > 0 ||
            $this->filterImportado !== 'todos'
        );

        // Cargar nombres de estados de PrestaShop desde API
        $this->loadEstadosPrestaShop();

        // Solo cargar pedidos si hay filtros activos
        if ($this->hasFilters) {
            $this->loadPedidos();
        }

        // Procesar acciones
        $action = $this->request->request->get('action', '');
        if ($action === 'import-order' && $permissions->allowUpdate) {
            $this->importOrderAction();
        } elseif ($action === 'import-selected' && $permissions->allowUpdate) {
            $this->importSelectedAction();
        } elseif ($action === 'export-csv') {
            $this->exportCSV();
        } elseif ($action === 'get-order-details') {
            $this->getOrderDetailsAjax();
        }
    }

    /**
     * Obtiene detalles completos de un pedido para mostrar en modal (AJAX)
     */
    private function getOrderDetailsAjax(): void
    {
        header('Content-Type: application/json');

        $orderId = (int)$this->request->request->get('order_id', 0);

        if ($orderId <= 0) {
            echo json_encode(['error' => 'ID de pedido inválido']);
            die();
        }

        $config = PrestashopConfig::getActive();
        if (!$config) {
            echo json_encode(['error' => 'Configuración no encontrada']);
            die();
        }

        try {
            $connection = new PrestashopConnection($config);
            $orderXml = $connection->getOrder($orderId);

            if (!$orderXml) {
                echo json_encode(['error' => 'Pedido no encontrado']);
                die();
            }

            // Obtener datos básicos del pedido
            $orderData = [
                'id' => (int)$orderXml->id,
                'reference' => (string)$orderXml->reference,
                'total_paid' => (float)$orderXml->total_paid,
                'total_paid_tax_excl' => (float)$orderXml->total_paid_tax_excl,
                'total_paid_tax_incl' => (float)$orderXml->total_paid_tax_incl,
                'total_products' => (float)$orderXml->total_products,
                'total_products_wt' => (float)$orderXml->total_products_wt,
                'total_shipping' => (float)$orderXml->total_shipping,
                'total_shipping_tax_excl' => (float)$orderXml->total_shipping_tax_excl,
                'total_shipping_tax_incl' => (float)$orderXml->total_shipping_tax_incl,
                'total_discounts' => (float)$orderXml->total_discounts,
                'date_add' => (string)$orderXml->date_add,
                'current_state' => (int)$orderXml->current_state,
                'payment' => (string)$orderXml->payment,
                'id_customer' => (int)$orderXml->id_customer,
                'id_address_delivery' => (int)$orderXml->id_address_delivery,
                'id_address_invoice' => (int)$orderXml->id_address_invoice
            ];

            // Obtener nombre del cliente
            $customerXml = $connection->getCustomer($orderData['id_customer']);
            if ($customerXml) {
                $orderData['customer_name'] = trim((string)$customerXml->firstname . ' ' . (string)$customerXml->lastname);
                $orderData['customer_email'] = (string)$customerXml->email;
            }

            // Obtener dirección de facturación
            $addressXml = $connection->getAddress($orderData['id_address_invoice']);
            if ($addressXml) {
                $stateId = (int)$addressXml->id_state;
                $stateName = '';
                if ($stateId > 0) {
                    $stateName = $connection->getStateName($stateId);
                }

                $orderData['invoice_address'] = [
                    'company' => (string)$addressXml->company,
                    'firstname' => (string)$addressXml->firstname,
                    'lastname' => (string)$addressXml->lastname,
                    'address1' => (string)$addressXml->address1,
                    'address2' => (string)$addressXml->address2,
                    'postcode' => (string)$addressXml->postcode,
                    'city' => (string)$addressXml->city,
                    'state' => $stateName,
                    'phone' => (string)$addressXml->phone,
                    'phone_mobile' => (string)$addressXml->phone_mobile,
                    'vat_number' => (string)$addressXml->vat_number
                ];
            }

            // Obtener dirección de envío
            $deliveryXml = $connection->getAddress($orderData['id_address_delivery']);
            if ($deliveryXml) {
                $stateId = (int)$deliveryXml->id_state;
                $stateName = '';
                if ($stateId > 0) {
                    $stateName = $connection->getStateName($stateId);
                }

                $orderData['delivery_address'] = [
                    'company' => (string)$deliveryXml->company,
                    'firstname' => (string)$deliveryXml->firstname,
                    'lastname' => (string)$deliveryXml->lastname,
                    'address1' => (string)$deliveryXml->address1,
                    'address2' => (string)$deliveryXml->address2,
                    'postcode' => (string)$deliveryXml->postcode,
                    'city' => (string)$deliveryXml->city,
                    'state' => $stateName,
                    'phone' => (string)$deliveryXml->phone,
                    'phone_mobile' => (string)$deliveryXml->phone_mobile
                ];
            }

            // Obtener líneas de productos
            $products = $connection->getOrderProducts($orderId);
            $orderData['lines'] = [];

            foreach ($products as $product) {
                $quantity = (int)$product['product_quantity'];
                $unitPriceExcl = (float)$product['unit_price_tax_excl'];
                $unitPriceIncl = (float)$product['unit_price_tax_incl'];

                $orderData['lines'][] = [
                    'product_id' => (int)$product['product_id'],
                    'product_name' => (string)$product['product_name'],
                    'product_reference' => (string)$product['product_reference'],
                    'product_quantity' => $quantity,
                    'unit_price_tax_excl' => $unitPriceExcl,
                    'unit_price_tax_incl' => $unitPriceIncl,
                    'total_price_tax_excl' => $unitPriceExcl * $quantity,
                    'total_price_tax_incl' => $unitPriceIncl * $quantity,
                    'tax_rate' => (float)($product['tax_rate'] ?? 0)
                ];
            }

            // Verificar si está importado
            $albaranModel = new AlbaranCliente();
            $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('numero2', $orderData['reference'])];
            $albaran = $albaranModel->all($where, [], 0, 1);
            $orderData['importado'] = !empty($albaran);
            if ($orderData['importado'] && !empty($albaran)) {
                $orderData['idalbaran'] = $albaran[0]->idalbaran;
                $orderData['codigo_albaran'] = $albaran[0]->codigo;
            }

            echo json_encode(['success' => true, 'data' => $orderData]);
            die();

        } catch (\Exception $e) {
            echo json_encode(['error' => 'Error al obtener detalles: ' . $e->getMessage()]);
            die();
        }
    }

    /**
     * Carga los pedidos de PrestaShop y verifica cuáles están importados
     */
    private function loadPedidos(): void
    {
        $config = PrestashopConfig::getActive();

        if (!$config || !$config->shop_url || !$config->api_key) {
            Tools::log()->warning('PrestaShop no está configurado');
            return;
        }

        try {
            $connection = new PrestashopConnection($config);

            // Si hay filtro "ID desde", usarlo en la API para obtener desde ese punto
            $sinceId = $this->filterIdFrom > 0 ? $this->filterIdFrom : null;

            // Obtener hasta 1000 pedidos (los filtraremos en PHP)
            // NO aplicar filtro de estados de configuración - el usuario tiene control total de filtros
            $ordersXml = $connection->getOrders(1000, $sinceId, [], false);

            if (!$ordersXml) {
                Tools::log()->error('No se pudieron obtener pedidos de PrestaShop');
                return;
            }

            // Convertir a array y aplicar filtros en PHP
            $allOrders = [];
            foreach ($ordersXml as $orderXml) {
                $orderId = (int)$orderXml->id;
                $orderRef = (string)$orderXml->reference;
                $orderDate = (string)$orderXml->date_add;
                $orderState = (int)$orderXml->current_state;

                // Aplicar filtro "ID hasta"
                if ($this->filterIdTo > 0 && $orderId > $this->filterIdTo) {
                    continue;
                }

                // Aplicar filtro de referencia
                if (!empty($this->filterReference) && stripos($orderRef, $this->filterReference) === false) {
                    continue;
                }

                // Aplicar filtro de fecha desde (comparar solo la fecha, sin hora)
                if (!empty($this->filterDateFrom)) {
                    $orderDateOnly = substr($orderDate, 0, 10); // YYYY-MM-DD
                    if ($orderDateOnly < $this->filterDateFrom) {
                        continue;
                    }
                }

                // Aplicar filtro de fecha hasta (comparar solo la fecha, sin hora)
                if (!empty($this->filterDateTo)) {
                    $orderDateOnly = substr($orderDate, 0, 10); // YYYY-MM-DD
                    if ($orderDateOnly > $this->filterDateTo) {
                        continue;
                    }
                }

                // Aplicar filtro de estado
                if ($this->filterState > 0 && $orderState != $this->filterState) {
                    continue;
                }

                $allOrders[] = $orderXml;
            }

            // Ordenar por ID DESC (más recientes primero)
            usort($allOrders, function($a, $b) {
                return (int)$b->id - (int)$a->id;
            });

            $albaranModel = new AlbaranCliente();

            // Procesar todos y aplicar filtro de importado
            $filteredOrders = [];
            foreach ($allOrders as $orderXml) {
                $orderRef = (string)$orderXml->reference;

                // Verificar si está importado
                $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('numero2', $orderRef)];
                $albaran = $albaranModel->all($where, [], 0, 1);
                $importado = !empty($albaran);

                // Aplicar filtro de importado
                if ($this->filterImportado === 'importado' && !$importado) {
                    continue;
                }
                if ($this->filterImportado === 'pendiente' && $importado) {
                    continue;
                }

                $filteredOrders[] = [
                    'xml' => $orderXml,
                    'importado' => $importado,
                    'albaran' => $importado && !empty($albaran) ? $albaran[0] : null
                ];
            }

            // Calcular paginación DESPUÉS de filtrar
            $this->totalPedidos = count($filteredOrders);
            $this->totalPages = ceil($this->totalPedidos / $this->limit);

            // Obtener solo la página actual
            $ordersPage = array_slice($filteredOrders, $this->offset, $this->limit);

            // Resetear contadores
            $this->importados = 0;
            $this->pendientes = 0;

            foreach ($ordersPage as $orderData) {
                $orderXml = $orderData['xml'];
                $importado = $orderData['importado'];
                $orderId = (int)$orderXml->id;
                $orderRef = (string)$orderXml->reference;
                $customerId = (int)$orderXml->id_customer;
                $currentState = (int)$orderXml->current_state;
                $totalPaid = (float)$orderXml->total_paid;
                $dateAdd = (string)$orderXml->date_add;

                if ($importado) {
                    $this->importados++;
                } else {
                    $this->pendientes++;
                }

                // Obtener nombre del cliente
                $customerName = $this->getCustomerName($connection, $customerId);

                $this->pedidos[] = [
                    'id' => $orderId,
                    'reference' => $orderRef,
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'state' => $currentState,
                    'total' => $totalPaid,
                    'date' => $dateAdd,
                    'importado' => $importado,
                    'idalbaran' => $orderData['albaran'] ? $orderData['albaran']->idalbaran : null
                ];
            }

        } catch (\Exception $e) {
            Tools::log()->error('Error cargando pedidos: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene el nombre del cliente desde PrestaShop
     */
    private function getCustomerName(PrestashopConnection $connection, int $customerId): string
    {
        try {
            $customerXml = $connection->getCustomer($customerId);
            if ($customerXml) {
                $firstname = (string)$customerXml->firstname;
                $lastname = (string)$customerXml->lastname;
                return trim($firstname . ' ' . $lastname);
            }
        } catch (\Exception $e) {
            // Ignorar errores
        }

        return 'Cliente #' . $customerId;
    }

    /**
     * Importa un pedido específico
     */
    private function importOrderAction(): void
    {
        $orderId = (int)$this->request->request->get('order_id', 0);

        if ($orderId <= 0) {
            Tools::log()->error('ID de pedido inválido');
            return;
        }

        $config = PrestashopConfig::getActive();

        if (!$config) {
            Tools::log()->error('Configuración no encontrada');
            return;
        }

        try {
            $connection = new PrestashopConnection($config);
            $orderXml = $connection->getOrder($orderId);

            if (!$orderXml) {
                Tools::log()->error("Pedido {$orderId} no encontrado en PrestaShop");
                return;
            }

            // Importar usando OrdersDownload
            $importer = new \FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload();
            $reflection = new \ReflectionClass($importer);
            $method = $reflection->getMethod('importOrder');
            $method->setAccessible(true);

            $result = $method->invoke($importer, $orderXml);

            if ($result && is_array($result)) {
                Tools::log()->info("✓ Pedido {$orderId} importado correctamente");
            } else {
                Tools::log()->warning("Pedido {$orderId} ya estaba importado");
            }

            // Recargar pedidos
            $this->pedidos = [];
            $this->importados = 0;
            $this->pendientes = 0;
            $this->loadPedidos();

        } catch (\Exception $e) {
            Tools::log()->error("Error importando pedido {$orderId}: " . $e->getMessage());
        }
    }

    /**
     * Importa múltiples pedidos seleccionados
     */
    private function importSelectedAction(): void
    {
        // DEBUG: Ver todos los datos POST recibidos
        $allPostData = $this->request->request->all();
        Tools::log()->debug("POST data recibido: " . print_r($allPostData, true));

        $orderIds = $this->request->request->get('order_ids', []);

        // DEBUG: Ver específicamente order_ids
        Tools::log()->debug("order_ids raw: " . print_r($orderIds, true));

        if (empty($orderIds) || !is_array($orderIds)) {
            Tools::log()->warning('No se seleccionaron pedidos para importar');
            return;
        }

        $totalSelected = count($orderIds);
        Tools::log()->info("🔵 Iniciando importación masiva de {$totalSelected} pedidos: [" . implode(', ', $orderIds) . "]");

        $config = PrestashopConfig::getActive();
        if (!$config) {
            Tools::log()->error('Configuración no encontrada');
            return;
        }

        // Crear conexión UNA SOLA VEZ fuera del bucle (más eficiente)
        $connection = new PrestashopConnection($config);

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($orderIds as $orderId) {
            $orderId = (int)$orderId;
            if ($orderId <= 0) {
                Tools::log()->warning("ID inválido ignorado: {$orderId}");
                continue;
            }

            try {
                Tools::log()->debug("Procesando pedido {$orderId}...");

                $orderXml = $connection->getOrder($orderId);

                if (!$orderXml) {
                    Tools::log()->error("Pedido {$orderId} no encontrado en PrestaShop");
                    $errors++;
                    continue;
                }

                $importer = new \FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload();
                $reflection = new \ReflectionClass($importer);
                $method = $reflection->getMethod('importOrder');
                $method->setAccessible(true);

                $result = $method->invoke($importer, $orderXml);

                if ($result && is_array($result)) {
                    $imported++;
                    Tools::log()->info("✓ Pedido {$orderId} importado correctamente");
                } else {
                    $skipped++;
                    Tools::log()->info("⊘ Pedido {$orderId} ya estaba importado");
                }
            } catch (\Exception $e) {
                $errors++;
                Tools::log()->error("✗ Error importando pedido {$orderId}: " . $e->getMessage());
            }
        }

        Tools::log()->info("✓ Importación masiva completada: {$imported} importados, {$skipped} ya existían, {$errors} errores de {$totalSelected} totales");

        // Recargar pedidos
        $this->pedidos = [];
        $this->importados = 0;
        $this->pendientes = 0;
        if ($this->hasFilters) {
            $this->loadPedidos();
        }
    }

    /**
     * Carga los nombres de estados de PrestaShop desde la API
     */
    private function loadEstadosPrestaShop(): void
    {
        $config = PrestashopConfig::getActive();

        if (!$config || !$config->shop_url || !$config->api_key) {
            // Si no hay configuración, usar mapeo básico
            $this->estadosPrestaShop = $this->getDefaultStates();
            return;
        }

        try {
            $connection = new PrestashopConnection($config);
            $webService = $connection->getWebService();

            // Obtener estados desde PrestaShop
            $xmlString = $webService->get('order_states', null, null, ['display' => 'full']);
            $xml = simplexml_load_string($xmlString);

            if (isset($xml->order_states->order_state)) {
                foreach ($xml->order_states->order_state as $state) {
                    $id = (int)$state->id;

                    // Obtener nombre (primer idioma disponible)
                    $nombre = 'Estado ' . $id;
                    if (isset($state->name->language)) {
                        foreach ($state->name->language as $lang) {
                            $nombre = trim((string)$lang);
                            if (!empty($nombre)) {
                                break;
                            }
                        }
                    }

                    // Determinar color según el nombre o características
                    $color = $this->getColorForState($nombre, $state);

                    $this->estadosPrestaShop[$id] = [
                        'nombre' => $nombre,
                        'color' => $color
                    ];
                }

                Tools::log()->debug("✓ Cargados " . count($this->estadosPrestaShop) . " estados desde PrestaShop");
            } else {
                // Si falla, usar mapeo por defecto
                $this->estadosPrestaShop = $this->getDefaultStates();
            }
        } catch (\Exception $e) {
            Tools::log()->warning("No se pudieron cargar estados desde PrestaShop, usando valores por defecto: " . $e->getMessage());
            $this->estadosPrestaShop = $this->getDefaultStates();
        }
    }

    /**
     * Devuelve mapeo de estados por defecto
     */
    private function getDefaultStates(): array
    {
        return [
            1 => ['nombre' => 'Esperando pago', 'color' => 'secondary'],
            2 => ['nombre' => 'Pago aceptado', 'color' => 'success'],
            3 => ['nombre' => 'Preparación en curso', 'color' => 'info'],
            4 => ['nombre' => 'Enviado', 'color' => 'primary'],
            5 => ['nombre' => 'Entregado', 'color' => 'success'],
            6 => ['nombre' => 'Cancelado', 'color' => 'danger'],
            7 => ['nombre' => 'Reembolsado', 'color' => 'warning'],
            8 => ['nombre' => 'Error de pago', 'color' => 'danger'],
            9 => ['nombre' => 'Esperando reposición', 'color' => 'warning'],
            10 => ['nombre' => 'Esperando pago bancario', 'color' => 'secondary'],
            11 => ['nombre' => 'Pago remoto aceptado', 'color' => 'success'],
            12 => ['nombre' => 'En proceso', 'color' => 'info'],
            13 => ['nombre' => 'Problema pago', 'color' => 'danger']
        ];
    }

    /**
     * Determina el color del badge según el nombre o características del estado
     */
    private function getColorForState(string $nombre, $state): string
    {
        $nombreLower = strtolower($nombre);

        // Verde: estados positivos/completados
        if (stripos($nombreLower, 'pago aceptado') !== false ||
            stripos($nombreLower, 'entregado') !== false ||
            stripos($nombreLower, 'completado') !== false ||
            stripos($nombreLower, 'pagado') !== false) {
            return 'success';
        }

        // Azul: en proceso/enviado
        if (stripos($nombreLower, 'enviado') !== false ||
            stripos($nombreLower, 'en proceso') !== false ||
            stripos($nombreLower, 'preparación') !== false ||
            stripos($nombreLower, 'transito') !== false) {
            return 'primary';
        }

        // Rojo: cancelados/errores
        if (stripos($nombreLower, 'cancelado') !== false ||
            stripos($nombreLower, 'error') !== false ||
            stripos($nombreLower, 'rechazado') !== false ||
            stripos($nombreLower, 'anulado') !== false) {
            return 'danger';
        }

        // Amarillo: reembolsos/devoluciones
        if (stripos($nombreLower, 'reembolso') !== false ||
            stripos($nombreLower, 'devol') !== false ||
            stripos($nombreLower, 'reposición') !== false) {
            return 'warning';
        }

        // Cyan: informativo
        if (stripos($nombreLower, 'espera') !== false ||
            stripos($nombreLower, 'pendiente') !== false) {
            return 'info';
        }

        // Por defecto: gris
        return 'secondary';
    }

    /**
     * Exporta los pedidos a CSV
     */
    private function exportCSV(): void
    {
        if (empty($this->pedidos)) {
            Tools::log()->warning('No hay pedidos para exportar');
            return;
        }

        $filename = 'pedidos_prestashop_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // BOM para Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Cabeceras
        fputcsv($output, [
            'ID',
            'Referencia',
            'Cliente ID',
            'Cliente',
            'Estado',
            'Total',
            'Fecha',
            'Importado',
            'ID Albarán'
        ], ';');

        // Datos
        foreach ($this->pedidos as $pedido) {
            $estadoNombre = isset($this->estadosPrestaShop[$pedido['state']])
                ? $this->estadosPrestaShop[$pedido['state']]['nombre']
                : 'Estado ' . $pedido['state'];

            fputcsv($output, [
                $pedido['id'],
                $pedido['reference'],
                $pedido['customer_id'],
                $pedido['customer_name'],
                $estadoNombre,
                number_format($pedido['total'], 2, ',', '.'),
                $pedido['date'],
                $pedido['importado'] ? 'Sí' : 'No',
                $pedido['idalbaran'] ?? ''
            ], ';');
        }

        fclose($output);
        die();
    }
}
