<?php

namespace FacturaScripts\Plugins\Prestashop\Extension\Controller;

use Closure;

/**
 * Extensión para añadir funcionalidad al controlador de Albaranes
 */
class EditAlbaranCliente
{
    public function createViews(): Closure
    {
        return function () {
            // Asegurar que el campo numero2 esté visible en la vista
            // Este código se ejecuta después de que se creen las vistas originales
        };
    }
}
