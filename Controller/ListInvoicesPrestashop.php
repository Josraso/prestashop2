<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;

/**
 * Controlador público para listar facturas de PrestaShop
 */
class ListInvoicesPrestashop extends Controller
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
            $this->sendJson(403, ['success' => false, 'error' => 'Token inválido']);
            return;
        }

        // Obtener parámetros
        $customerEmail = $this->request->query->get('customer_email', '');
        $orderReferences = $this->request->query->get('order_refs', ''); // IDs separados por comas

        if (empty($orderReferences)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Parámetro order_refs requerido']);
            return;
        }

        // Convertir a array
        $orderRefArray = array_map('trim', explode(',', $orderReferences));

        $facturas = [];

        foreach ($orderRefArray as $orderRef) {
            if (empty($orderRef)) {
                continue;
            }

            // Buscar albarán por numero2 (referencia PrestaShop)
            $albaranModel = new AlbaranCliente();
            $where = [new DataBaseWhere('numero2', $orderRef)];
            $albaranes = $albaranModel->all($where, [], 0, 1);

            if (empty($albaranes)) {
                $facturas[] = [
                    'order_reference' => $orderRef,
                    'has_invoice' => false,
                    'status' => 'pending'
                ];
                continue;
            }

            $albaran = $albaranes[0];

            // Buscar factura asociada
            $invoiceId = $this->getInvoiceIdFromAlbaran($albaran->idalbaran);

            if ($invoiceId) {
                $facturaModel = new \FacturaScripts\Dinamic\Model\FacturaCliente();
                if ($facturaModel->loadFromCode($invoiceId)) {
                    $facturas[] = [
                        'order_reference' => $orderRef,
                        'has_invoice' => true,
                        'status' => 'completed',
                        'invoice_id' => $facturaModel->idfactura,
                        'invoice_code' => $facturaModel->codigo,
                        'invoice_date' => $facturaModel->fecha,
                        'total' => $facturaModel->total
                    ];
                } else {
                    $facturas[] = [
                        'order_reference' => $orderRef,
                        'has_invoice' => false,
                        'status' => 'processing'
                    ];
                }
            } else {
                $facturas[] = [
                    'order_reference' => $orderRef,
                    'has_invoice' => false,
                    'status' => 'processing',
                    'albaran_code' => $albaran->codigo
                ];
            }
        }

        $this->sendJson(200, [
            'success' => true,
            'invoices' => $facturas
        ]);
    }

    /**
     * Obtiene ID de factura asociada a un albarán
     */
    private function getInvoiceIdFromAlbaran(int $idalbaran): ?int
    {
        // Método 1: Desde el albarán directamente
        $albaranModel = new AlbaranCliente();
        if ($albaranModel->loadFromCode($idalbaran) && !empty($albaranModel->idfactura)) {
            return $albaranModel->idfactura;
        }

        // Método 2: Desde las líneas
        $sql = "SELECT DISTINCT idfactura FROM lineasfacturascli
                WHERE idalbaran = " . (int)$idalbaran . "
                AND idfactura IS NOT NULL
                LIMIT 1";

        $db = Tools::dataBase();
        $result = $db->select($sql);

        if (!empty($result) && isset($result[0]['idfactura'])) {
            return (int)$result[0]['idfactura'];
        }

        return null;
    }

    /**
     * Envía respuesta JSON
     */
    private function sendJson(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'List Invoices PrestaShop';
        $data['menu'] = 'admin';
        $data['showonmenu'] = false;
        return $data;
    }
}
