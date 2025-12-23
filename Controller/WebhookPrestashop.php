<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopWebhookLog;
use FacturaScripts\Plugins\Prestashop\Lib\PrestashopConnection;
use FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload;

/**
 * Endpoint público para recibir webhooks de PrestaShop
 * URL: https://tudominio.com/WebhookPrestashop?token=XXXXX
 */
class WebhookPrestashop extends Controller
{
    /**
     * Permite acceso público sin autenticación
     */
    public function publicCore(&$response): void
    {
        parent::publicCore($response);

        // Si es GET, mostrar mensaje informativo
        if ($this->request->getMethod() === 'GET') {
            $this->showInfoPage();
            return;
        }

        // Solo aceptar POST
        if ($this->request->getMethod() !== 'POST') {
            $this->sendResponse(405, ['error' => 'Método no permitido. Solo POST.']);
            return;
        }

        // Obtener IP del cliente
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Obtener payload
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);

        if (!$payload) {
            $payload = $_POST; // Fallback a POST data si no es JSON
        }

        // Log inicial del webhook recibido
        Tools::log()->info('========================================');
        Tools::log()->info('WEBHOOK PRESTASHOP RECIBIDO');
        Tools::log()->info("IP: {$ip}");
        Tools::log()->info("Payload: " . substr($rawPayload, 0, 200));
        Tools::log()->info('========================================');

        // Validar token
        $token = $this->request->query->get('token', '');
        $config = PrestashopConfig::getActive();

        if (!$config) {
            $webhookLog = PrestashopWebhookLog::logWebhook($ip, 'POST', $payload, false);
            $webhookLog->markProcessed(false, 'Configuración de PrestaShop no encontrada');
            $this->sendResponse(500, ['error' => 'Configuración no encontrada']);
            return;
        }

        // Verificar que los webhooks estén habilitados
        if (!$config->webhook_enabled) {
            $webhookLog = PrestashopWebhookLog::logWebhook($ip, 'POST', $payload, false);
            $webhookLog->markProcessed(false, 'Webhooks deshabilitados en configuración');
            $this->sendResponse(403, ['error' => 'Webhooks deshabilitados']);
            return;
        }

        // Validar token
        $tokenValido = ($token === $config->webhook_token);
        if (!$tokenValido) {
            Tools::log()->warning("Token inválido. Recibido: {$token}");
            $webhookLog = PrestashopWebhookLog::logWebhook($ip, 'POST', $payload, false);
            $webhookLog->markProcessed(false, 'Token inválido');
            $this->sendResponse(403, ['error' => 'Token inválido']);
            return;
        }

        // Token válido, extraer order_id del payload
        $orderId = $this->extractOrderId($payload);

        if (!$orderId) {
            Tools::log()->error('No se pudo extraer order_id del payload');
            $webhookLog = PrestashopWebhookLog::logWebhook($ip, 'POST', $payload, true);
            $webhookLog->markProcessed(false, 'No se encontró order_id en el payload');
            $this->sendResponse(400, ['error' => 'order_id no encontrado en payload']);
            return;
        }

        // Registrar webhook válido
        $webhookLog = PrestashopWebhookLog::logWebhook($ip, 'POST', $payload, true, $orderId);

        // Procesar el pedido inmediatamente
        try {
            Tools::log()->info("Procesando pedido ID: {$orderId} desde webhook");

            $connection = new PrestashopConnection($config);
            $orderXml = $connection->getOrder($orderId);

            if (!$orderXml) {
                throw new \Exception("No se pudo obtener el pedido {$orderId} de PrestaShop");
            }

            // IMPORTANTE: Verificar si el estado del pedido está en los estados configurados
            $estadosImportar = $config->getEstadosArray();
            $currentState = (int)$orderXml->current_state;

            if (!empty($estadosImportar) && !in_array($currentState, $estadosImportar)) {
                $webhookLog->markProcessed(false, "Pedido en estado {$currentState} no está en los estados configurados para importar");
                Tools::log()->warning("⊘ Webhook omitido: Pedido {$orderId} en estado {$currentState} no configurado para importar");

                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Pedido en estado no importable',
                    'order_id' => $orderId,
                    'current_state' => $currentState,
                    'estados_configurados' => $estadosImportar
                ]);
                die();
            }

            // CRÍTICO: Verificar fecha del ÚLTIMO ESTADO (no fecha de creación del pedido)
            if (!empty($config->import_since_date)) {
                $lastStatusDate = $this->getLastOrderStatusDate($orderXml, $orderId, $connection);

                if ($lastStatusDate && $lastStatusDate < $config->import_since_date) {
                    $webhookLog->markProcessed(false, "Fecha del último estado ({$lastStatusDate}) anterior a la configurada ({$config->import_since_date})");
                    Tools::log()->warning("⊘ Webhook omitido: Pedido {$orderId} con último estado del {$lastStatusDate} anterior a {$config->import_since_date}");

                    http_response_code(200);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Fecha del último estado anterior a la configurada',
                        'order_id' => $orderId,
                        'last_status_date' => $lastStatusDate,
                        'import_since_date' => $config->import_since_date
                    ]);
                    die();
                }
            }

            // Importar el pedido
            $importer = new OrdersDownload();
            $reflection = new \ReflectionClass($importer);
            $method = $reflection->getMethod('importOrder');
            $method->setAccessible(true);

            $result = $method->invoke($importer, $orderXml);

            if ($result && is_array($result)) {
                $webhookLog->markProcessed(true, "Pedido importado correctamente");
                Tools::log()->info("✓ Webhook procesado exitosamente: Pedido {$orderId} importado");

                // Preparar respuesta con datos del albarán/factura
                $response = [
                    'success' => true,
                    'message' => 'Pedido importado correctamente',
                    'order_id' => $orderId,
                    'order_reference' => (string)$orderXml->reference
                ];

                // Añadir ID del albarán si existe
                if (isset($result['idalbaran']) && $result['idalbaran']) {
                    $response['albaran_id'] = (int)$result['idalbaran'];
                }

                // Buscar factura asociada al albarán
                if (isset($result['idalbaran']) && $result['idalbaran']) {
                    $albaranModel = new \FacturaScripts\Dinamic\Model\AlbaranCliente();
                    if ($albaranModel->loadFromCode($result['idalbaran'])) {
                        if (!empty($albaranModel->idfactura)) {
                            $response['factura_id'] = (int)$albaranModel->idfactura;

                            // Obtener código de factura
                            $facturaModel = new \FacturaScripts\Dinamic\Model\FacturaCliente();
                            if ($facturaModel->loadFromCode($albaranModel->idfactura)) {
                                $response['factura_code'] = $facturaModel->codigo;
                            }
                        }
                    }
                }

                $this->sendResponse(200, $response);
            } else {
                $webhookLog->markProcessed(false, "El pedido ya estaba importado o falló la importación");
                Tools::log()->warning("Webhook procesado pero pedido no importado (ya existe): {$orderId}");
                $this->sendResponse(200, [
                    'success' => true,
                    'message' => 'Pedido ya importado anteriormente',
                    'order_id' => $orderId,
                    'order_reference' => (string)$orderXml->reference
                ]);
            }

        } catch (\Exception $e) {
            $webhookLog->markProcessed(false, $e->getMessage());
            Tools::log()->error("Error procesando webhook: " . $e->getMessage());
            $this->sendResponse(500, [
                'error' => 'Error procesando pedido: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Extrae el order_id del payload del webhook
     */
    private function extractOrderId(array $payload): ?int
    {
        // Intentar diferentes formatos comunes de webhooks de PrestaShop
        if (isset($payload['id_order'])) {
            return (int)$payload['id_order'];
        }

        if (isset($payload['order_id'])) {
            return (int)$payload['order_id'];
        }

        if (isset($payload['orderId'])) {
            return (int)$payload['orderId'];
        }

        if (isset($payload['id'])) {
            return (int)$payload['id'];
        }

        // Si el payload es directamente el ID
        if (is_numeric($payload)) {
            return (int)$payload;
        }

        return null;
    }

    /**
     * Muestra página informativa cuando se accede con GET
     */
    private function showInfoPage(): void
    {
        $config = PrestashopConfig::getActive();
        $webhookEnabled = $config && $config->webhook_enabled ? 'Sí' : 'No';
        $hasToken = $config && !empty($config->webhook_token);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook PrestaShop - FacturaScripts</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .status {
            background: #f0f0f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .status-item:last-child {
            border-bottom: none;
        }
        .status-label {
            font-weight: 600;
            color: #555;
        }
        .status-value {
            color: #333;
        }
        .status-value.active {
            color: #10b981;
            font-weight: 600;
        }
        .status-value.inactive {
            color: #ef4444;
            font-weight: 600;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .info-box h3 {
            color: #1e40af;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box p {
            color: #1e3a8a;
            line-height: 1.6;
            font-size: 14px;
        }
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .warning-box strong {
            color: #92400e;
        }
        .warning-box p {
            color: #78350f;
            line-height: 1.6;
            font-size: 14px;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 13px;
        }
        .icon {
            display: inline-block;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">⚡</span>Webhook PrestaShop</h1>
        <p class="subtitle">Endpoint de integración FacturaScripts</p>

        <div class="status">
            <div class="status-item">
                <span class="status-label">Estado del endpoint:</span>
                <span class="status-value active">✓ Operativo</span>
            </div>
            <div class="status-item">
                <span class="status-label">Webhooks habilitados:</span>
                <span class="status-value ' . ($config && $config->webhook_enabled ? 'active">✓ Sí' : 'inactive">✗ No') . '</span>
            </div>
            <div class="status-item">
                <span class="status-label">Token configurado:</span>
                <span class="status-value ' . ($hasToken ? 'active">✓ Sí' : 'inactive">✗ No') . '</span>
            </div>
        </div>

        <div class="info-box">
            <h3>ℹ️ Información</h3>
            <p>
                Este es un endpoint público diseñado para recibir webhooks desde PrestaShop.
                Solo acepta peticiones <strong>POST</strong> con un token válido en la URL.
            </p>
        </div>

        <div class="warning-box">
            <strong>⚠️ Nota importante</strong>
            <p>
                Esta URL no debe ser visitada directamente en el navegador.
                Solo debe ser utilizada por PrestaShop para enviar notificaciones automáticas de pedidos.
            </p>
        </div>

        <div class="footer">
            FacturaScripts PrestaShop Plugin v1.0
        </div>
    </div>
</body>
</html>';
        die();
    }

    /**
     * Envía respuesta JSON y termina la ejecución
     */
    private function sendResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

    /**
     * Obtiene la fecha del ÚLTIMO estado del pedido
     * Usa el historial de estados para obtener la fecha más reciente
     */
    private function getLastOrderStatusDate(\SimpleXMLElement $orderXml, int $orderId, PrestashopConnection $connection): ?string
    {
        try {
            // Intentar 1: order_state_histories (plural) en associations del XML
            if (isset($orderXml->associations->order_state_histories->order_state_history)) {
                $histories = $orderXml->associations->order_state_histories->order_state_history;

                $historyArray = [];
                foreach ($histories as $history) {
                    $historyArray[] = [
                        'date' => (string)$history->date_add,
                        'id_order_state' => (int)$history->id_order_state
                    ];
                }

                if (!empty($historyArray)) {
                    usort($historyArray, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });

                    return $historyArray[0]['date'];
                }
            }

            // Intentar 2: order_history (singular) en associations del XML
            if (isset($orderXml->associations->order_history)) {
                $histories = $orderXml->associations->order_history;

                $historyArray = [];
                foreach ($histories as $history) {
                    $historyArray[] = [
                        'date' => (string)$history->date_add,
                        'id_order_state' => (int)$history->id_order_state
                    ];
                }

                if (!empty($historyArray)) {
                    usort($historyArray, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });

                    return $historyArray[0]['date'];
                }
            }

            // Intentar 3: Desde API order_histories
            $history = $connection->getOrderHistory($orderId);

            if (!empty($history)) {
                $lastStatus = $history[0];
                $dateAdd = (string)$lastStatus->date_add;

                if (!empty($dateAdd)) {
                    return $dateAdd;
                }
            }

            return null;
        } catch (\Exception $e) {
            Tools::log()->error("Error obteniendo fecha del último estado en webhook: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Información de la página (no se usa en webhooks pero requerido por FacturaScripts)
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Webhook PrestaShop';
        $data['icon'] = 'fas fa-webhook';
        $data['showonmenu'] = false;
        return $data;
    }
}
