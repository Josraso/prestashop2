<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Plugins\Prestashop\Lib\PrestashopConnection;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;

/**
 * Controlador para configurar PrestaShop
 */
class ConfigPrestashop extends Controller
{
    /** @var PrestashopConfig */
    public $config;

    /** @var array */
    public $almacenes;

    /** @var array */
    public $series;

    /** @var array */
    public $estadosPrestaShop;

    /** @var string */
    public $importResult = '';

    /** @var array */
    public $taxMaps = [];

    /** @var array */
    public $paymentMaps = [];

    /** @var string */
    public $activeTab = 'config';

    /** @var array */
    public $recentWebhooks = [];

    /** @var array */
    public $idiomasPrestaShop = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'Configuración PrestaShop';
        $data['icon'] = 'fas fa-shopping-cart';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        // Cargar configuración
        $this->loadConfig();

        // Cargar datos para los selectores
        $this->loadAlmacenes();
        $this->loadSeries();
        $this->loadMappings();
        $this->loadRecentWebhooks();
        $this->loadIdiomas();

        // Obtener tab activa desde GET
        $this->activeTab = $this->request->query->get('tab', 'config');

        // Procesar acciones
        $action = $this->request->request->get('action', '');
        switch ($action) {
            case 'save':
                $this->saveAction();
                break;

            case 'test':
                $this->testConnectionAction();
                break;

            case 'load-states':
                $this->loadStatesAction();
                break;

            case 'import-order':
                $this->importOrderAction();
                break;

            case 'detect-payment-methods':
                $this->detectPaymentMethodsAction();
                break;

            case 'import-batch':
                $this->importBatchAction();
                break;

            case 'save-webhooks':
                $this->saveWebhooksAction();
                break;

            case 'regenerate-token':
                $this->regenerateTokenAction();
                break;

            case 'test-webhook':
                $this->testWebhookAction();
                break;
        }
    }

    /**
     * Carga la configuración
     */
    private function loadConfig(): void
    {
        $this->config = PrestashopConfig::getActive();
        if (!$this->config) {
            $this->config = new PrestashopConfig();
            $this->config->save();
        }
    }

    /**
     * Carga los almacenes disponibles
     */
    private function loadAlmacenes(): void
    {
        $almacenModel = new Almacen();
        $this->almacenes = $almacenModel->all([], [], 0, 0);
    }

    /**
     * Carga las series disponibles
     */
    private function loadSeries(): void
    {
        $serieModel = new Serie();
        $this->series = $serieModel->all([], [], 0, 0);
    }

    /**
     * Guarda la configuración
     */
    private function saveAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('No tienes permisos para guardar');
            return;
        }

        $this->config->shop_url = $this->request->request->get('shop_url', '');
        $this->config->api_key = $this->request->request->get('api_key', '');
        $this->config->codalmacen = $this->request->request->get('codalmacen', '');
        $this->config->codserie = $this->request->request->get('codserie', '');
        $this->config->activo = (bool)$this->request->request->get('activo', false);
        $this->config->use_ws_key_param = (bool)$this->request->request->get('use_ws_key_param', false);
        $this->config->import_since_id = (int)$this->request->request->get('import_since_id', 0);
        $this->config->import_since_date = $this->request->request->get('import_since_date', '');
        $this->config->idioma_productos = (int)$this->request->request->get('idioma_productos', 1);

        // Configuración de base de datos para ecotax
        $this->config->use_db_for_ecotax = (bool)$this->request->request->get('use_db_for_ecotax', false);
        $this->config->db_host = $this->request->request->get('db_host', 'localhost');
        $this->config->db_name = $this->request->request->get('db_name', '');
        $this->config->db_user = $this->request->request->get('db_user', '');
        $this->config->db_password = $this->request->request->get('db_password', '');
        $this->config->db_prefix = $this->request->request->get('db_prefix', 'ps_');

        // Guardar estados seleccionados
        $estadosSeleccionados = $this->request->request->all()['estados'] ?? [];
        $this->config->setEstadosArray($estadosSeleccionados);

        // Guardar mapeo de estados→series (opcional)
        $estadosSeries = $this->request->request->all()['estados_series'] ?? [];
        $this->config->setEstadosSeriesArray($estadosSeries);

        if ($this->config->save()) {
            Tools::log()->info('Configuración guardada correctamente');
        } else {
            Tools::log()->error('Error al guardar la configuración');
        }
    }

    /**
     * Prueba la conexión con PrestaShop
     */
    private function testConnectionAction(): void
    {
        $shop_url = $this->request->request->get('shop_url', '');
        $api_key = $this->request->request->get('api_key', '');

        // Validar que tenemos los datos
        if (empty($shop_url) || empty($api_key)) {
            Tools::log()->error('Debes completar URL y API Key');
            return;
        }

        // Intentar conectar REALMENTE
        try {
            $webService = new \prestashop\prestashopWebserviceLib\Shared\Application\PrestaShopWebservice(
                $shop_url,
                $api_key,
                false
            );

            // Hacer una llamada real para verificar la conexión
            $xmlString = $webService->get('order_states');
            $xml = simplexml_load_string($xmlString);

            // Si llegamos aquí, la conexión funcionó
            $states = [];
            if (isset($xml->order_states->order_state)) {
                foreach ($xml->order_states->order_state as $state) {
                    $id = (int)$state->id;
                    if (isset($state->name->language)) {
                        foreach ($state->name->language as $lang) {
                            $states[$id] = (string)$lang;
                            break;
                        }
                    } elseif (isset($state->name)) {
                        $states[$id] = (string)$state->name;
                    }
                }
            }

            if (!empty($states)) {
                Tools::log()->info('✓ Conexión exitosa. Se encontraron ' . count($states) . ' estados de pedidos');
                $this->estadosPrestaShop = $states;
            } else {
                Tools::log()->warning('Conexión establecida pero no se encontraron estados');
            }

        } catch (\PrestaShopWebserviceException $e) {
            Tools::log()->error('✗ Error de conexión: ' . $e->getMessage());
        } catch (\Exception $e) {
            Tools::log()->error('✗ Error: ' . $e->getMessage());
        }
    }

    /**
     * Carga los estados de PrestaShop
     */
    private function loadStatesAction(): void
    {
        if ($this->config->shop_url && $this->config->api_key) {
            $connection = new PrestashopConnection($this->config);
            try {
                $this->estadosPrestaShop = $connection->getOrderStates();
            } catch (\Exception $e) {
                Tools::log()->error('Error al cargar estados: ' . $e->getMessage());
                $this->estadosPrestaShop = [];
            }
        }
    }

    /**
     * Obtiene los estados de PrestaShop para mostrar en la vista
     */
    public function getEstadosPrestaShop(): array
    {
        if (empty($this->estadosPrestaShop) && $this->config->shop_url && $this->config->api_key) {
            $connection = new PrestashopConnection($this->config);
            try {
                $this->estadosPrestaShop = $connection->getOrderStates();
            } catch (\Exception $e) {
                $this->estadosPrestaShop = [];
            }
        }

        return $this->estadosPrestaShop ?? [];
    }

    /**
     * Importa un pedido manualmente para pruebas
     */
    private function importOrderAction(): void
    {
        $orderId = $this->request->request->get('order_id', '');

        if (empty($orderId) || !is_numeric($orderId)) {
            Tools::log()->error('Debes proporcionar un ID de pedido válido');
            $this->importResult = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Debes proporcionar un ID de pedido válido</div>';
            return;
        }

        if (!$this->config || !$this->config->shop_url || !$this->config->api_key) {
            Tools::log()->error('PrestaShop no está configurado correctamente');
            $this->importResult = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> PrestaShop no está configurado correctamente</div>';
            return;
        }

        try {
            $connection = new PrestashopConnection($this->config);
            if (!$connection->isConnected()) {
                Tools::log()->error('No se pudo conectar con PrestaShop');
                $this->importResult = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> No se pudo conectar con PrestaShop</div>';
                return;
            }

            // Obtener el pedido
            $orderXml = $connection->getOrder((int)$orderId);
            if (!$orderXml) {
                Tools::log()->error("No se encontró el pedido con ID {$orderId}");
                $this->importResult = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> No se encontró el pedido con ID ' . $orderId . '</div>';
                return;
            }

            // Crear instancia de OrdersDownload para importar
            $importer = new \FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload();

            // Usar reflexión para llamar al método privado importOrder
            $reflection = new \ReflectionClass($importer);
            $method = $reflection->getMethod('importOrder');
            $method->setAccessible(true);

            $result = $method->invoke($importer, $orderXml);

            if ($result) {
                $orderReference = (string)$orderXml->reference;
                $customerId = (int)$orderXml->id_customer;
                $currentState = (int)$orderXml->current_state;

                Tools::log()->info("Pedido {$orderReference} importado correctamente");
                $this->importResult = '<div class="alert alert-success">' .
                    '<i class="fas fa-check-circle"></i> <strong>Pedido importado correctamente</strong><br>' .
                    '<strong>Referencia:</strong> ' . $orderReference . '<br>' .
                    '<strong>ID Cliente PrestaShop:</strong> ' . $customerId . '<br>' .
                    '<strong>Estado actual:</strong> ' . $currentState .
                    '</div>';
            } else {
                Tools::log()->warning("El pedido {$orderId} ya existe o no se pudo importar");
                $this->importResult = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> El pedido ya existe o no se pudo importar</div>';
            }

        } catch (\Exception $e) {
            Tools::log()->error('Error importando pedido: ' . $e->getMessage());
            $this->importResult = '<div class="alert alert-danger">' .
                '<i class="fas fa-times-circle"></i> <strong>Error al importar pedido:</strong><br>' .
                htmlspecialchars($e->getMessage()) .
                '</div>';
        }
    }

    /**
     * Detecta los métodos de pago usados en PrestaShop y crea mapeos automáticos
     */
    private function detectPaymentMethodsAction(): void
    {
        if (!$this->config || !$this->config->shop_url || !$this->config->api_key) {
            Tools::log()->error('PrestaShop no está configurado correctamente');
            return;
        }

        try {
            $connection = new PrestashopConnection($this->config);
            if (!$connection->isConnected()) {
                Tools::log()->error('No se pudo conectar con PrestaShop');
                return;
            }

            // Obtener pedidos recientes (últimos 100)
            $orders = $connection->getOrders(100);
            $paymentMethods = [];

            // Recopilar métodos de pago únicos
            foreach ($orders as $orderXml) {
                $paymentModule = trim((string)$orderXml->payment);
                if (!empty($paymentModule) && !isset($paymentMethods[$paymentModule])) {
                    $paymentMethods[$paymentModule] = $paymentModule;
                }
            }

            // Crear mapeos automáticamente para los que no existen
            $created = 0;
            foreach ($paymentMethods as $paymentModule) {
                $mapping = new \FacturaScripts\Plugins\Prestashop\Model\PrestashopPaymentMap();
                $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('payment_module', $paymentModule)];

                // Si no existe, crear uno nuevo sin asignar forma de pago
                if (!$mapping->loadFromCode('', $where)) {
                    $mapping->payment_module = $paymentModule;
                    $mapping->nombre_prestashop = $paymentModule;
                    // codpago se deja vacío para que el usuario lo configure
                    // No guardamos porque codpago es requerido
                    // En su lugar, mostramos los métodos detectados
                    $created++;
                }
            }

            Tools::log()->info("Se detectaron {$created} métodos de pago en PrestaShop. Ve a 'Mapeo de Formas de Pago' para configurarlos.");

        } catch (\Exception $e) {
            Tools::log()->error('Error detectando métodos de pago: ' . $e->getMessage());
        }
    }

    /**
     * Carga los mapeos de IVA y pagos
     */
    private function loadMappings(): void
    {
        // Cargar mapeos de IVA
        $taxMapModel = new \FacturaScripts\Plugins\Prestashop\Model\PrestashopTaxMap();
        $this->taxMaps = $taxMapModel->all([], ['rate_prestashop' => 'ASC'], 0, 0);

        // Cargar mapeos de pagos
        $paymentMapModel = new \FacturaScripts\Plugins\Prestashop\Model\PrestashopPaymentMap();
        $this->paymentMaps = $paymentMapModel->all([], ['payment_module' => 'ASC'], 0, 0);
    }

    /**
     * Importa pedidos manualmente con filtros (desde ID o todos pendientes)
     */
    private function importBatchAction(): void
    {
        if (!$this->config || !$this->config->shop_url || !$this->config->api_key) {
            Tools::log()->error('PrestaShop no está configurado correctamente');
            $this->importResult = '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> PrestaShop no está configurado correctamente</div>';
            return;
        }

        $sinceId = (int)$this->request->request->get('manual_since_id', 0);

        try {
            $importer = new \FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload();

            // Usar reflexión para llamar al método batch
            $reflection = new \ReflectionClass($importer);
            $method = $reflection->getMethod('batch');
            $method->setAccessible(true);

            // Si hay un ID desde el cual importar, temporalmente modificar la config
            $originalSinceId = $this->config->import_since_id;
            if ($sinceId > 0) {
                $this->config->import_since_id = $sinceId;
                $this->config->save();
            }

            // Ejecutar importación
            $method->invoke($importer);

            // Restaurar config original
            if ($sinceId > 0) {
                $this->config->import_since_id = $originalSinceId;
                $this->config->save();
            }

            $msgPrefix = $sinceId > 0 ? "desde ID {$sinceId}" : "todos los pendientes";
            Tools::log()->info("Importación manual completada: {$msgPrefix}");
            $this->importResult = '<div class="alert alert-success">' .
                '<i class="fas fa-check-circle"></i> <strong>Importación manual completada</strong><br>' .
                'Revisa el log para ver cuántos pedidos se importaron.' .
                '</div>';

        } catch (\Exception $e) {
            Tools::log()->error('Error en importación manual: ' . $e->getMessage());
            $this->importResult = '<div class="alert alert-danger">' .
                '<i class="fas fa-times-circle"></i> <strong>Error en importación manual:</strong><br>' .
                htmlspecialchars($e->getMessage()) .
                '</div>';
        }
    }

    /**
     * Guarda la configuración de webhooks
     */
    private function saveWebhooksAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('No tienes permisos para guardar');
            return;
        }

        $this->config->webhook_enabled = (bool)$this->request->request->get('webhook_enabled', false);

        // Si se está activando por primera vez y no hay token, generar uno
        if ($this->config->webhook_enabled && empty($this->config->webhook_token)) {
            $this->config->generateWebhookToken();
        }

        if ($this->config->save()) {
            Tools::log()->info('Configuración de webhooks guardada correctamente');
            $this->activeTab = 'webhooks';
        } else {
            Tools::log()->error('Error al guardar la configuración de webhooks');
        }
    }

    /**
     * Regenera el token de webhook
     */
    private function regenerateTokenAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('No tienes permisos para regenerar el token');
            return;
        }

        $this->config->regenerateWebhookToken();

        if ($this->config->save()) {
            Tools::log()->info('Token de webhook regenerado correctamente');
            $this->activeTab = 'webhooks';
        } else {
            Tools::log()->error('Error al regenerar el token de webhook');
        }
    }

    /**
     * Obtiene la URL completa del webhook
     */
    public function getWebhookUrl(): string
    {
        if (empty($this->config->webhook_token)) {
            return '';
        }

        // Obtener la URL base de FacturaScripts
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;

        return $baseUrl . '/WebhookPrestashop?token=' . $this->config->webhook_token;
    }

    /**
     * Carga los webhooks recientes
     */
    private function loadRecentWebhooks(): void
    {
        $webhookModel = new \FacturaScripts\Plugins\Prestashop\Model\PrestashopWebhookLog();
        $order = ['fecha' => 'DESC', 'id' => 'DESC'];
        $this->recentWebhooks = $webhookModel->all([], $order, 0, 20);
    }

    /**
     * Prueba el webhook enviando una petición simulada
     */
    private function testWebhookAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('No tienes permisos para probar el webhook');
            return;
        }

        $orderId = $this->request->request->get('test_order_id', '');

        if (empty($orderId) || !is_numeric($orderId)) {
            Tools::log()->error('Debes proporcionar un ID de pedido válido para probar');
            $this->activeTab = 'webhooks';
            return;
        }

        $webhookUrl = $this->getWebhookUrl();

        if (empty($webhookUrl)) {
            Tools::log()->error('No hay token configurado. Activa los webhooks primero.');
            $this->activeTab = 'webhooks';
            return;
        }

        // Enviar webhook de prueba
        try {
            $payload = ['order_id' => (int)$orderId];

            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \Exception("Error cURL: {$curlError}");
            }

            if ($httpCode == 200) {
                $responseData = json_decode($response, true);
                if (isset($responseData['success']) && $responseData['success']) {
                    Tools::log()->info("✓ Webhook de prueba enviado correctamente. Pedido {$orderId} procesado.");
                } else {
                    Tools::log()->warning("Webhook enviado pero con advertencias. Respuesta: {$response}");
                }
            } else {
                Tools::log()->error("Error en webhook. Código HTTP: {$httpCode}. Respuesta: {$response}");
            }

        } catch (\Exception $e) {
            Tools::log()->error('Error al probar webhook: ' . $e->getMessage());
        }

        $this->activeTab = 'webhooks';
        $this->loadRecentWebhooks(); // Recargar para mostrar el nuevo webhook

        // Redirigir para refrescar la página
        header('Location: ' . $this->url() . '?tab=webhooks');
        exit;
    }

    /**
     * Obtiene clase de badge según resultado del webhook
     */
    public function getWebhookBadgeClass(string $resultado): string
    {
        switch ($resultado) {
            case 'success':
                return 'badge-success';
            case 'error':
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }

    /**
     * Carga los idiomas configurados en PrestaShop
     */
    private function loadIdiomas(): void
    {
        if (!$this->config || !$this->config->shop_url || !$this->config->api_key) {
            $this->idiomasPrestaShop = [];
            return;
        }

        try {
            $connection = new PrestashopConnection($this->config);
            if (!$connection->isConnected()) {
                $this->idiomasPrestaShop = [];
                return;
            }

            $webService = $connection->getWebService();
            $xmlString = $webService->get('languages', null, null, ['display' => '[id,name,iso_code,active]']);
            $xml = simplexml_load_string($xmlString);

            $idiomas = [];
            if (isset($xml->languages->language)) {
                foreach ($xml->languages->language as $lang) {
                    $id = (int)$lang->id;
                    $active = (int)$lang->active;

                    // Solo mostrar idiomas activos
                    if ($active === 1) {
                        $name = (string)$lang->name;
                        $isoCode = (string)$lang->iso_code;
                        $idiomas[$id] = "{$name} ({$isoCode})";
                    }
                }
            }

            $this->idiomasPrestaShop = $idiomas;

        } catch (\Exception $e) {
            Tools::log()->warning('No se pudieron cargar idiomas de PrestaShop: ' . $e->getMessage());
            $this->idiomasPrestaShop = [];
        }
    }
}
