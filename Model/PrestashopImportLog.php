<?php

namespace FacturaScripts\Plugins\Prestashop\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Modelo para el historial de importaciones de PrestaShop
 */
class PrestashopImportLog extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $fecha;

    /** @var string */
    public $hora;

    /** @var int */
    public $order_id;

    /** @var string */
    public $order_reference;

    /** @var int */
    public $idalbaran;

    /** @var string */
    public $codcliente;

    /** @var string */
    public $nombrecliente;

    /** @var float */
    public $total;

    /** @var string */
    public $tipo;

    /** @var string */
    public $resultado;

    /** @var string */
    public $mensaje;

    /** @var string */
    public $origen;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'prestashop_import_log';
    }

    public function clear(): void
    {
        parent::clear();
        $this->fecha = date('d-m-Y');
        $this->hora = date('H:i:s');
        $this->tipo = 'order';
        $this->resultado = 'success';
        $this->origen = 'cron';
    }

    /**
     * Registra una importación exitosa
     */
    public static function logSuccess(int $orderId, string $orderRef, int $idalbaran, string $codcliente, string $nombrecliente, float $total, string $origen = 'cron'): bool
    {
        $log = new self();
        $log->order_id = $orderId;
        $log->order_reference = $orderRef;
        $log->idalbaran = $idalbaran;
        $log->codcliente = $codcliente;
        $log->nombrecliente = $nombrecliente;
        $log->total = $total;
        $log->resultado = 'success';
        $log->origen = $origen;
        $log->mensaje = 'Pedido importado correctamente';

        return $log->save();
    }

    /**
     * Registra un error de importación
     */
    public static function logError(int $orderId, string $orderRef, string $mensaje, string $origen = 'cron'): bool
    {
        $log = new self();
        $log->order_id = $orderId;
        $log->order_reference = $orderRef;
        $log->resultado = 'error';
        $log->origen = $origen;
        $log->mensaje = $mensaje;

        return $log->save();
    }

    /**
     * Registra un pedido omitido
     */
    public static function logSkipped(int $orderId, string $orderRef, string $motivo, string $origen = 'cron'): bool
    {
        $log = new self();
        $log->order_id = $orderId;
        $log->order_reference = $orderRef;
        $log->resultado = 'skipped';
        $log->origen = $origen;
        $log->mensaje = $motivo;

        return $log->save();
    }

    /**
     * Obtiene estadísticas de importaciones
     */
    public static function getStats(string $periodo = 'today'): array
    {
        $sql = '';

        switch ($periodo) {
            case 'today':
                $sql = "SELECT resultado, COUNT(*) as total FROM " . static::tableName() . " WHERE fecha = CURRENT_DATE GROUP BY resultado";
                break;
            case 'week':
                $sql = "SELECT resultado, COUNT(*) as total FROM " . static::tableName() . " WHERE fecha >= CURRENT_DATE - 7 GROUP BY resultado";
                break;
            case 'month':
                $sql = "SELECT resultado, COUNT(*) as total FROM " . static::tableName() . " WHERE fecha >= CURRENT_DATE - 30 GROUP BY resultado";
                break;
            case 'year':
                $sql = "SELECT resultado, COUNT(*) as total FROM " . static::tableName() . " WHERE fecha >= CURRENT_DATE - 365 GROUP BY resultado";
                break;
        }

        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $results = $dataBase->select($sql);

        $stats = [
            'success' => 0,
            'error' => 0,
            'skipped' => 0,
            'total' => 0
        ];

        foreach ($results as $row) {
            $stats[$row['resultado']] = (int)$row['total'];
            $stats['total'] += (int)$row['total'];
        }

        return $stats;
    }

    /**
     * Obtiene datos para gráfica de importaciones por día
     */
    public static function getChartData(int $days = 30): array
    {
        $sql = "SELECT fecha, COUNT(*) as total
                FROM " . static::tableName() . "
                WHERE fecha >= CURRENT_DATE - {$days}
                  AND resultado = 'success'
                GROUP BY fecha
                ORDER BY fecha ASC";

        $dataBase = new \FacturaScripts\Core\Base\DataBase();
        $results = $dataBase->select($sql);

        $labels = [];
        $data = [];

        foreach ($results as $row) {
            $labels[] = $row['fecha'];
            $data[] = (int)$row['total'];
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
}
