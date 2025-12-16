<?php

namespace FacturaScripts\Plugins\Prestashop\Model;

use FacturaScripts\Core\Model\Base\ModelClass;

/**
 * Modelo temporal para almacenar productos descargados de PrestaShop
 * antes de importarlos a FacturaScripts
 */
class PrestashopProductsTemp extends ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $session_id;

    /** @var string */
    public $products_data;

    /** @var string */
    public $fecha_descarga;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'prestashop_products_temp';
    }

    public function clear()
    {
        parent::clear();
        $this->session_id = '';
        $this->products_data = '';
        $this->fecha_descarga = date('Y-m-d H:i:s');
    }

    /**
     * Guarda productos para la sesión actual
     * ACUMULA productos, NO borra los anteriores
     */
    public static function saveProducts(array $products): bool
    {
        $model = new self();

        // NO limpiar registros anteriores - solo actualizar
        // Buscar registro existente de esta sesión
        $sessionId = session_id();

        if ($model->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('session_id', $sessionId)])) {
            // Ya existe registro - actualizar
            $model->products_data = json_encode($products);
            $model->fecha_descarga = date('Y-m-d H:i:s');
        } else {
            // Nuevo registro
            $model->session_id = $sessionId;
            $model->products_data = json_encode($products);
            $model->fecha_descarga = date('Y-m-d H:i:s');
        }

        return $model->save();
    }

    /**
     * Obtiene productos de la sesión actual
     */
    public static function getProducts(): array
    {
        $model = new self();
        $sessionId = session_id();

        if ($model->loadFromCode('', [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('session_id', $sessionId)])) {
            $data = json_decode($model->products_data, true);
            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Limpia productos de la sesión actual
     */
    public static function clearSession(): void
    {
        $db = new \FacturaScripts\Core\Base\DataBase();
        $sessionId = session_id();
        $sql = "DELETE FROM " . self::tableName() . " WHERE session_id = " . $db->var2str($sessionId);
        $db->exec($sql);
    }

    /**
     * Limpia registros antiguos (más de 24 horas)
     */
    public static function cleanOld(): void
    {
        $db = new \FacturaScripts\Core\Base\DataBase();
        $sql = "DELETE FROM " . self::tableName() . " WHERE fecha_descarga < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $db->exec($sql);
    }
}
