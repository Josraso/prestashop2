<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controlador para editar mapeos de tipos de IVA
 */
class EditPrestashopTaxMap extends EditController
{
    public function getModelClassName(): string
    {
        return 'PrestashopTaxMap';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'Mapeo de Tipo de IVA';
        $data['icon'] = 'fas fa-percentage';
        return $data;
    }
}
