<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Controlador para listar y gestionar mapeos de formas de pago
 */
class ListPrestashopPaymentMap extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'accounting';
        $data['title'] = 'Mapeo de Formas de Pago';
        $data['icon'] = 'fas fa-credit-card';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewPaymentMap();
    }

    protected function createViewPaymentMap(string $viewName = 'ListPrestashopPaymentMap')
    {
        $this->addView($viewName, 'PrestashopPaymentMap', 'mapeo-formas-pago', 'fas fa-credit-card');
        $this->addOrderBy($viewName, ['payment_module'], 'payment-module', 1);
        $this->addOrderBy($viewName, ['nombre_prestashop'], 'name');
        $this->addSearchFields($viewName, ['payment_module', 'nombre_prestashop']);
    }
}
