<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Controlador para listar y gestionar mapeos de tipos de IVA
 */
class ListPrestashopTaxMap extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'Mapeo de Tipos de IVA';
        $data['icon'] = 'fas fa-percentage';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewTaxMap();
    }

    protected function createViewTaxMap(string $viewName = 'ListPrestashopTaxMap')
    {
        $this->addView($viewName, 'PrestashopTaxMap', 'mapeo-tipos-iva', 'fas fa-percentage');
        $this->addOrderBy($viewName, ['rate_prestashop'], 'tasa-iva', 1);
        $this->addOrderBy($viewName, ['nombre_prestashop'], 'name');
        $this->addSearchFields($viewName, ['rate_prestashop', 'nombre_prestashop']);
    }
}
