<?php

namespace FacturaScripts\Plugins\Prestashop\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Modelo para el historial de webhooks recibidos desde PrestaShop
 */
class PrestashopWebhookLog extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $fecha;

    /** @var string */
    public $ip;

    /** @var string */
    public $metodo;

    /** @var string */
    public $payload;

    /** @var bool */
    public $token_valido;

    /** @var int */
    public $order_id;

    /** @var bool */
    public $procesado;

    /** @var string */
    public $resultado;

    /** @var string */
    public $mensaje;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'prestashop_webhook_log';
    }

    public function clear(): void
    {
        parent::clear();
        $this->fecha = date('Y-m-d H:i:s');
        $this->token_valido = false;
        $this->procesado = false;
    }

    /**
     * Registra un webhook recibido
     */
    public static function logWebhook(string $ip, string $metodo, array $payload, bool $tokenValido, ?int $orderId = null): self
    {
        $log = new self();
        $log->ip = $ip;
        $log->metodo = $metodo;
        $log->payload = json_encode($payload);
        $log->token_valido = $tokenValido;
        $log->order_id = $orderId;
        $log->save();

        return $log;
    }

    /**
     * Marca el webhook como procesado
     */
    public function markProcessed(bool $exitoso, string $mensaje = ''): bool
    {
        $this->procesado = true;
        $this->resultado = $exitoso ? 'success' : 'error';
        $this->mensaje = $mensaje;
        return $this->save();
    }

    /**
     * Obtiene estadísticas de webhooks
     */
    public static function getStats(int $days = 7): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN token_valido THEN 1 ELSE 0 END) as validos,
                    SUM(CASE WHEN procesado THEN 1 ELSE 0 END) as procesados,
                    SUM(CASE WHEN resultado = 'success' THEN 1 ELSE 0 END) as exitosos
                FROM " . static::tableName() . "
                WHERE fecha >= NOW() - INTERVAL '{$days} days'";

        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $results = $dataBase->select($sql);

        if (empty($results)) {
            return [
                'total' => 0,
                'validos' => 0,
                'procesados' => 0,
                'exitosos' => 0
            ];
        }

        return [
            'total' => (int)$results[0]['total'],
            'validos' => (int)$results[0]['validos'],
            'procesados' => (int)$results[0]['procesados'],
            'exitosos' => (int)$results[0]['exitosos']
        ];
    }
}
