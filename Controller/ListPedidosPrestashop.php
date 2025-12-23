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
            $this->filterImportado !== 'todos'
        );

        // Cargar nombres de estados de PrestaShop
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
            $ordersXml = $connection->getOrders(1000, $sinceId);

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

                // Aplicar filtro "ID hasta"
                if ($this->filterIdTo > 0 && $orderId > $this->filterIdTo) {
                    continue;
                }

                // Aplicar filtro de referencia
                if (!empty($this->filterReference) && stripos($orderRef, $this->filterReference) === false) {
                    continue;
                }

                // Aplicar filtro de fecha desde
                if (!empty($this->filterDateFrom) && $orderDate < $this->filterDateFrom) {
                    continue;
                }

                // Aplicar filtro de fecha hasta
                if (!empty($this->filterDateTo) && $orderDate > $this->filterDateTo . ' 23:59:59') {
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
        $orderIds = $this->request->request->get('order_ids', []);

        if (empty($orderIds) || !is_array($orderIds)) {
            Tools::log()->warning('No se seleccionaron pedidos para importar');
            return;
        }

        $config = PrestashopConfig::getActive();
        if (!$config) {
            Tools::log()->error('Configuración no encontrada');
            return;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($orderIds as $orderId) {
            $orderId = (int)$orderId;
            if ($orderId <= 0) continue;

            try {
                $connection = new PrestashopConnection($config);
                $orderXml = $connection->getOrder($orderId);

                if (!$orderXml) {
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
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                Tools::log()->error("Error importando pedido {$orderId}: " . $e->getMessage());
            }
        }

        Tools::log()->info("Importación masiva: {$imported} importados, {$skipped} ya existían, {$errors} errores");

        // Recargar pedidos
        $this->pedidos = [];
        $this->importados = 0;
        $this->pendientes = 0;
        if ($this->hasFilters) {
            $this->loadPedidos();
        }
    }

    /**
     * Carga los nombres de estados de PrestaShop
     */
    private function loadEstadosPrestaShop(): void
    {
        // Mapeo básico de estados de PrestaShop (los más comunes)
        // Se pueden ampliar según las necesidades
        $this->estadosPrestaShop = [
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
            13 => ['nombre' => 'Problema pagament', 'color' => 'danger']
        ];
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
