<?php

namespace FacturaScripts\Plugins\Prestashop\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Modelo para mapear tipos de IVA de PrestaShop con FacturaScripts
 */
class PrestashopTaxMap extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var float */
    public $rate_prestashop;

    /** @var string */
    public $nombre_prestashop;

    /** @var string */
    public $codimpuesto;

    public function clear()
    {
        parent::clear();
        $this->rate_prestashop = 0.0;
        $this->nombre_prestashop = '';
        $this->codimpuesto = '';
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'prestashop_tax_map';
    }

    /**
     * Obtiene el código de impuesto de FacturaScripts para una tasa de PrestaShop
     */
    public static function getCodImpuesto(float $rate): ?string
    {
        $model = new self();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('rate_prestashop', $rate)];

        if ($model->loadFromCode('', $where)) {
            return $model->codimpuesto;
        }

        return null;
    }

    /**
     * Obtiene todos los mapeos de IVA
     */
    public static function getAllMappings(): array
    {
        $model = new self();
        return $model->all();
    }
}
