<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlador público para descargar PDFs de facturas desde PrestaShop
 */
class DownloadInvoicePrestashop extends Controller
{
    public function publicCore(&$response): void
    {
        parent::publicCore($response);

        // Desactivar renderizado de plantilla Twig
        $this->setTemplate(false);

        // Validar token
        $token = $this->request->query->get('token', '');
        $config = PrestashopConfig::getActive();

        if (empty($token) || empty($config) || $token !== $config->webhook_token) {
            $this->sendError(403, 'Token inválido o no autorizado');
            return;
        }

        // Obtener parámetros
        $orderRef = $this->request->query->get('order_ref', '');
        $invoiceId = $this->request->query->get('invoice_id', '');

        // Opción 1: Buscar por referencia de pedido PrestaShop
        if (!empty($orderRef)) {
            $this->downloadByOrderReference($orderRef);
            return;
        }

        // Opción 2: Buscar por ID de factura directo
        if (!empty($invoiceId)) {
            $this->downloadByInvoiceId((int)$invoiceId);
            return;
        }

        $this->sendError(400, 'Debes proporcionar order_ref o invoice_id');
    }

    /**
     * Busca y descarga factura por referencia de pedido PrestaShop
     */
    private function downloadByOrderReference(string $orderRef): void
    {
        // Buscar albarán que tenga esta referencia en numero2
        $albaranModel = new AlbaranCliente();
        $where = [new DataBaseWhere('numero2', $orderRef)];
        $albaranes = $albaranModel->all($where, [], 0, 1);

        if (empty($albaranes)) {
            $this->sendError(404, "No se encontró albarán con referencia de pedido: {$orderRef}");
            return;
        }

        $albaran = $albaranes[0];

        // Método 1: Buscar por idfactura del albarán
        if (!empty($albaran->idfactura)) {
            $this->downloadByInvoiceId($albaran->idfactura);
            return;
        }

        // Método 2: Buscar factura por SQL directamente (más eficiente)
        $sql = "SELECT DISTINCT idfactura FROM lineasfacturascli
                WHERE idalbaran = " . (int)$albaran->idalbaran . "
                LIMIT 1";

        $db = Tools::dataBase();
        $result = $db->select($sql);
        if (!empty($result) && isset($result[0]['idfactura'])) {
            $this->downloadByInvoiceId((int)$result[0]['idfactura']);
            return;
        }

        $this->sendError(404, "El albarán {$albaran->codigo} no tiene factura asociada todavía");
    }

    /**
     * Descarga factura por ID
     */
    private function downloadByInvoiceId(int $invoiceId): void
    {
        $facturaModel = new FacturaCliente();
        if (!$facturaModel->loadFromCode($invoiceId)) {
            $this->sendError(404, "No se encontró factura con ID: {$invoiceId}");
            return;
        }

        // Generar PDF usando el sistema de FacturaScripts
        try {
            $pdfExport = new \FacturaScripts\Core\ExportManager\PDFExport();
            $pdfExport->setOption('idempresa', $facturaModel->idempresa);

            // Generar el PDF
            $pdfExport->newDoc('FacturaCliente', $facturaModel->codigo, []);

            // Obtener el contenido del PDF
            $pdfContent = $pdfExport->getDoc();

            // Enviar headers
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="factura_' . $facturaModel->codigo . '.pdf"');
            header('Content-Length: ' . strlen($pdfContent));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Enviar PDF
            echo $pdfContent;
            exit;

        } catch (\Exception $e) {
            Tools::log()->error('Error generando PDF: ' . $e->getMessage());
            $this->sendError(500, 'Error al generar el PDF: ' . $e->getMessage());
        }
    }

    /**
     * Envía respuesta de error en JSON
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Download Invoice PrestaShop';
        $data['menu'] = 'admin';
        $data['showonmenu'] = false;
        return $data;
    }
}
