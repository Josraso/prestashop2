<?php

namespace FacturaScripts\Plugins\Prestashop\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

/**
 * Modelo para mapear formas de pago de PrestaShop con FacturaScripts
 */
class PrestashopPaymentMap extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $payment_module;

    /** @var string */
    public $nombre_prestashop;

    /** @var string */
    public $codpago;

    public function clear()
    {
        parent::clear();
        $this->payment_module = '';
        $this->nombre_prestashop = '';
        $this->codpago = '';
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'prestashop_payment_map';
    }

    /**
     * Obtiene el código de pago de FacturaScripts para un módulo de pago de PrestaShop
     */
    public static function getCodPago(string $paymentModule): ?string
    {
        $model = new self();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('payment_module', $paymentModule)];

        if ($model->loadFromCode('', $where)) {
            return $model->codpago;
        }

        return null;
    }

    /**
     * Obtiene todos los mapeos de formas de pago
     */
    public static function getAllMappings(): array
    {
        $model = new self();
        return $model->all();
    }
}
