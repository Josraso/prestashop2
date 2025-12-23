<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para editar mapeos de formas de pago
 */
class EditPrestashopPaymentMap extends EditController
{
    public function getModelClassName(): string
    {
        return 'PrestashopPaymentMap';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'accounting';
        $data['title'] = 'Mapeo de Forma de Pago';
        $data['icon'] = 'fas fa-credit-card';
        return $data;
    }
}
