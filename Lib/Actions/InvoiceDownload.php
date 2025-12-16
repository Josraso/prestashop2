<?php

namespace FacturaScripts\Plugins\Prestashop\Lib\Actions;

use FacturaScripts\Core\Tools;

/**
 * Clase para descargar facturas de PrestaShop (pendiente de implementación)
 */
class InvoiceDownload
{
    /**
     * Proceso batch para importar facturas
     */
    public function batch(): void
    {
        // Por ahora solo registramos que se ejecutó
        // La funcionalidad principal está en OrdersDownload para importar como albaranes
        Tools::log()->debug('InvoiceDownload batch ejecutado (sin implementación activa)');
    }
}
