<?php

namespace FacturaScripts\Plugins\Prestashop\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Modelo para la configuración de PrestaShop
 */
class PrestashopConfig extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $shop_url;

    /** @var string */
    public $api_key;

    /** @var string */
    public $codalmacen;

    /** @var string */
    public $codserie;

    /** @var string */
    public $estados_importar;

    /** @var string */
    public $import_since_date;

    /** @var int */
    public $import_since_id;

    /** @var bool */
    public $activo;

    /** @var bool */
    public $use_ws_key_param;

    /** @var bool */
    public $webhook_enabled;

    /** @var string */
    public $webhook_token;

    /** @var int */
    public $idioma_productos;

    // Configuración de base de datos para leer ecotax directamente
    /** @var string */
    public $db_host;

    /** @var string */
    public $db_name;

    /** @var string */
    public $db_user;

    /** @var string */
    public $db_password;

    /** @var string */
    public $db_prefix;

    /** @var bool */
    public $use_db_for_ecotax;

    /** @var string Mapeo JSON de estados a series. Ej: {"2":"GENERAL","5":"VENFIS"} */
    public $estados_series;

    /** @var float */
    public $importe_simplificada;

    /** @var string */
    public $serie_simplificada;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'prestashop_config';
    }

    public function clear(): void
    {
        parent::clear();
        $this->activo = true;
        $this->estados_importar = '';
        $this->import_since_id = 0; // Por defecto, importar desde el principio
        $this->import_since_date = ''; // Fecha fija desde la que siempre buscar
        $this->use_ws_key_param = false; // Por defecto, usar Basic Auth
        $this->webhook_enabled = false;
        $this->idioma_productos = 1; // ID 1 = idioma por defecto (normalmente español)
        $this->generateWebhookToken(); // Genera y asigna el token automáticamente

        // Configuración de base de datos para ecotax
        $this->db_host = 'localhost';
        $this->db_name = '';
        $this->db_user = '';
        $this->db_password = '';
        $this->db_prefix = 'ps_';
        $this->use_db_for_ecotax = false; // Por defecto desactivado

        // Mapeo de estados a series (JSON)
        $this->estados_series = ''; // Por defecto vacío, usa codserie
        $this->importe_simplificada = 0;
        $this->serie_simplificada = null;
    }

    /**
     * Genera un token aleatorio para el webhook
     */
    public function generateWebhookToken(): string
    {
        $this->webhook_token = bin2hex(random_bytes(16)); // Token de 32 caracteres
        return $this->webhook_token;
    }

    /**
     * Regenera el token del webhook
     */
    public function regenerateWebhookToken(): string
    {
        return $this->generateWebhookToken();
    }

    /**
     * Obtiene los estados a importar como array
     */
    public function getEstadosArray(): array
    {
        if (empty($this->estados_importar)) {
            return [];
        }

        $estados = json_decode($this->estados_importar, true);
        return is_array($estados) ? $estados : [];
    }

    /**
     * Establece los estados a importar desde un array
     */
    public function setEstadosArray(array $estados): void
    {
        $this->estados_importar = json_encode($estados);
    }

    /**
     * Obtiene la serie a usar para un estado específico
     * Si el estado tiene una serie asignada, la devuelve
     * Si no, devuelve la serie por defecto (codserie)
     */
    public function getSerieForEstado(int $estadoId): string
    {
        // Si hay mapeo específico, buscarlo
        if (!empty($this->estados_series)) {
            $map = json_decode($this->estados_series, true);
            if (is_array($map) && isset($map[(string)$estadoId])) {
                $serie = trim($map[(string)$estadoId]);
                // Si la serie no está vacía, usarla
                if (!empty($serie)) {
                    return $serie;
                }
            }
        }

        // Fallback a serie por defecto
        return $this->codserie ?? '';
    }

    /**
     * Obtiene el mapeo de estados a series como array
     */
    public function getEstadosSeriesArray(): array
    {
        if (empty($this->estados_series)) {
            return [];
        }

        $map = json_decode($this->estados_series, true);
        return is_array($map) ? $map : [];
    }

    /**
     * Establece el mapeo de estados a series desde un array
     */
    public function setEstadosSeriesArray(array $mapeo): void
    {
        // Limpiar entradas vacías
        $cleaned = [];
        foreach ($mapeo as $estado => $serie) {
            if (!empty(trim($serie))) {
                $cleaned[(string)$estado] = trim($serie);
            }
        }

        $this->estados_series = empty($cleaned) ? '' : json_encode($cleaned);
    }

    /**
     * Devuelve true si la facturación simplificada está configurada y activa
     */
    public function isSimplificadaEnabled(): bool
    {
        return $this->importe_simplificada > 0 && !empty($this->serie_simplificada);
    }

    /**
     * Obtiene la configuración activa
     * IMPORTANTE: Siempre devuelve el PRIMER registro (menor ID)
     * El campo 'activo' solo controla el CRON, no qué registro usar
     */
    public static function getActive(): ?self
    {
        $config = new self();

        // Cargar el registro con menor ID (el primero/único)
        $db = new \FacturaScripts\Core\Base\DataBase();
        $tableName = self::tableName();

        // Buscar el registro con menor ID
        $sql = "SELECT * FROM {$tableName} ORDER BY id ASC LIMIT 1";
        $data = $db->select($sql);

        if (!empty($data)) {
            $config->loadFromData($data[0]);
            return $config;
        }

        return null;
    }
}
