<?php

namespace FacturaScripts\Plugins\Prestashop;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Prestashop\Lib\Actions\InvoiceDownload;
use FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload;

class Cron extends CronClass
{
    /**
     * @throws \Exception
     */
    public function run(): void
    {
        Tools::log()->info('========================================');
        Tools::log()->info('CRON PRESTASHOP INICIADO');
        Tools::log()->info('========================================');

        try {
            Tools::log()->info('Ejecutando InvoiceDownload batch...');
            (new InvoiceDownload())->batch();
            Tools::log()->info('InvoiceDownload batch completado');

            Tools::log()->info('Ejecutando OrdersDownload batch...');
            (new OrdersDownload())->batch();
            Tools::log()->info('OrdersDownload batch completado');

        } catch (\Exception $e) {
            Tools::log()->critical('ERROR CRÍTICO EN CRON PRESTASHOP: ' . $e->getMessage());
            Tools::log()->critical('Traza: ' . $e->getTraceAsString());
            throw $e;
        }

        Tools::log()->info('========================================');
        Tools::log()->info('CRON PRESTASHOP FINALIZADO');
        Tools::log()->info('========================================');
    }
}

