<?php

namespace FacturaScripts\Plugins\Prestashop\Lib\Actions;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Dinamic\Model\AlbaranCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\LineaAlbaranCliente;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;
use FacturaScripts\Plugins\Prestashop\Lib\PrestashopConnection;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopImportLog;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopPaymentMap;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopTaxMap;

/**
 * Clase para descargar pedidos de PrestaShop e importarlos como albaranes
 */
class OrdersDownload
{
    /** @var PrestashopConnection */
    private $connection;

    /** @var PrestashopConfig */
    private $config;

    /** @var array */
    private $importLog = [];

    public function __construct()
    {
        $this->config = PrestashopConfig::getActive();
        $this->connection = new PrestashopConnection($this->config);
    }

    /**
     * Proceso batch para importar pedidos
     *
     * @param string $origen Origen de la importación (cron, manual, webhook)
     */
    public function batch(string $origen = 'cron'): void
    {
        Tools::log()->info('[OrdersDownload::batch] Método batch() iniciado - VERSIÓN ACTUALIZADA 2025-11-25');

        if (!$this->config) {
            Tools::log()->error('PrestaShop: Configuración no encontrada');
            return;
        }

        Tools::log()->info('[OrdersDownload::batch] Configuración encontrada - URL: ' . $this->config->shop_url);

        // VALIDACIÓN: Verificar conexión antes de procesar
        if (!$this->connection->isConnected()) {
            Tools::log()->error('PrestaShop: No se pudo conectar con la tienda. Verifica la URL y API Key en la configuración.');
            return;
        }

        Tools::log()->info('[OrdersDownload::batch] Conexión verificada correctamente');

        // Verificar que hay estados seleccionados
        $estados = $this->config->getEstadosArray();
        if (empty($estados)) {
            Tools::log()->warning('No hay estados de pedido seleccionados para importar. Configura los estados en la página de configuración.');
            return;
        }

        Tools::log()->info('[OrdersDownload::batch] Estados configurados: ' . implode(', ', $estados));

        // VALIDACIÓN: Probar conexión obteniendo 1 pedido
        Tools::log()->info('[OrdersDownload::batch] Probando conexión con API...');
        try {
            $testOrders = $this->connection->getOrders(1, null);
            if ($testOrders === false || $testOrders === null) {
                Tools::log()->error('PrestaShop: Error al obtener pedidos de la API. Verifica permisos del API Key.');
                return;
            }
            Tools::log()->info('[OrdersDownload::batch] Prueba de API exitosa - Pedidos de prueba: ' . count($testOrders));
        } catch (\Exception $e) {
            Tools::log()->error('PrestaShop: Error de conexión - ' . $e->getMessage());
            return;
        }

        try {
            $imported = 0;
            $errors = 0;
            $skipped = 0;

            // Fecha fija desde la que siempre buscar
            $importSinceDate = null;
            if (!empty($this->config->import_since_date)) {
                $importSinceDate = $this->config->import_since_date;
                Tools::log()->info("Buscando pedidos desde fecha: {$importSinceDate}");
            }

            // Filtro de ID mínimo (SIEMPRE usado)
            $importSinceId = (int)$this->config->import_since_id;

            if ($importSinceId > 0) {
                Tools::log()->info("Filtro de ID mínimo: {$importSinceId}");
            }

            // AVISO: Si hay fecha configurada pero el puntero está avanzado
            if (!empty($importSinceDate) && $importSinceId > 1000) {
                Tools::log()->warning("⚠ ATENCIÓN: Fecha configurada ({$importSinceDate}) con puntero en ID {$importSinceId}");
                Tools::log()->warning("⚠ Si necesitas importar pedidos antiguos, pon import_since_id = 0 en la configuración");
            }

            // Obtener pedidos con límite para evitar timeout
            Tools::log()->info('[OrdersDownload::batch] Obteniendo pedidos desde la API (límite: 50' . ($importSinceId ? ", desde ID: {$importSinceId}" : '') . ')...');

            $orders = $this->connection->getOrders(
                50, // Límite de 50 pedidos por ejecución
                $importSinceId > 0 ? $importSinceId : null
            );

            if (empty($orders)) {
                Tools::log()->info("[OrdersDownload::batch] No hay pedidos nuevos para importar");
                return;
            }

            Tools::log()->info("[OrdersDownload::batch] Se obtuvieron " . count($orders) . " pedidos. Procesando...");

            $lastImportedId = 0; // Guardar el último ID importado

            foreach ($orders as $orderXml) {
                $orderId = (int)$orderXml->id;
                $orderRef = (string)$orderXml->reference;

                // IMPORTANTE: Usar la fecha del ÚLTIMO ESTADO, no la fecha de creación
                // Esto debe coincidir con la fecha que se usa al crear el albarán
                $orderDate = $this->getLastOrderStatusDate($orderXml, $orderId);
                if (!$orderDate) {
                    // Fallback a fecha de creación si no hay historial de estados
                    $orderDate = (string)$orderXml->date_add;
                }

                Tools::log()->info("[OrdersDownload::batch] >>> Pedido ID: {$orderId}, Ref: {$orderRef}, Fecha último estado: {$orderDate}");

                try {
                    // Filtro por fecha: Si está configurado, verificar fecha del ÚLTIMO ESTADO del pedido
                    if ($importSinceDate && $orderDate < $importSinceDate) {
                        Tools::log()->info("[OrdersDownload::batch] ⊘ OMITIDO POR FECHA: {$orderRef} (último estado: {$orderDate} < {$importSinceDate})");
                        PrestashopImportLog::logSkipped($orderId, $orderRef, "Fecha del último estado ({$orderDate}) anterior a la configurada ({$importSinceDate})", $origen);
                        $skipped++; // Contar como omitido
                        continue;
                    }

                    // Verificar si el pedido ya fue importado
                    if ($this->isOrderImported($orderRef)) {
                        Tools::log()->info("[OrdersDownload::batch] ⊘ YA IMPORTADO: {$orderRef}");
                        PrestashopImportLog::logSkipped($orderId, $orderRef, "Pedido ya importado anteriormente", $origen);
                        $skipped++;
                        continue;
                    }

                    // Importar el pedido
                    Tools::log()->info("[OrdersDownload::batch] Importando pedido {$orderRef}...");
                    $albaranData = $this->importOrder($orderXml);
                    if ($albaranData) {
                        $imported++;
                        $lastImportedId = $orderId; // Guardar el último ID importado
                        Tools::log()->info("[OrdersDownload::batch] ✓ Pedido {$orderRef} importado correctamente");

                        // Registrar importación exitosa
                        PrestashopImportLog::logSuccess(
                            $orderId,
                            $orderRef,
                            $albaranData['idalbaran'],
                            $albaranData['codcliente'],
                            $albaranData['nombrecliente'],
                            $albaranData['total'],
                            $origen
                        );
                    } else {
                        Tools::log()->warning("[OrdersDownload::batch] Pedido {$orderRef} no se pudo importar (puede ya existir)");
                        PrestashopImportLog::logSkipped($orderId, $orderRef, "No se pudo importar (posiblemente ya existe)", $origen);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $errorMsg = "Error importando pedido {$orderRef} (ID: {$orderId}): " . $e->getMessage();
                    Tools::log()->error($errorMsg);
                    $this->logError($errorMsg);

                    // Registrar error en log de importaciones
                    PrestashopImportLog::logError($orderId, $orderRef, $e->getMessage(), $origen);
                }
            }

            // Resumen de la importación
            Tools::log()->info('[OrdersDownload::batch] ========== RESUMEN DE IMPORTACIÓN ==========');
            Tools::log()->info('[OrdersDownload::batch] Pedidos importados: ' . $imported);
            Tools::log()->info('[OrdersDownload::batch] Pedidos omitidos (ya importados): ' . $skipped);
            Tools::log()->info('[OrdersDownload::batch] Errores: ' . $errors);
            Tools::log()->info('[OrdersDownload::batch] ===========================================');

            // Actualizar puntero automáticamente
            if (count($orders) > 0) {
                if ($imported > 0 && $lastImportedId > 0) {
                    // Si se importaron pedidos, avanzar al último importado
                    // IMPORTANTE: Solo actualizar import_since_id, NO toda la config
                    $this->updateImportSinceId($lastImportedId);
                    Tools::log()->info("[OrdersDownload::batch] ✓ Puntero actualizado al último importado: ID {$lastImportedId}");
                } else {
                    // Si NO se importó ninguno, avanzar al último procesado para seguir buscando
                    $lastOrderXml = end($orders);
                    $lastOrderId = (int)$lastOrderXml->id;

                    // IMPORTANTE: Solo actualizar import_since_id, NO toda la config
                    $this->updateImportSinceId($lastOrderId);

                    Tools::log()->warning("[OrdersDownload::batch] ⚠ NO se importó ningún pedido. Avanzando a ID {$lastOrderId} para continuar buscando");
                }
            }

            if ($imported > 0) {
                Tools::log()->info("PrestaShop: Importados {$imported} pedidos como albaranes");
            }

            if ($skipped > 0) {
                Tools::log()->info("PrestaShop: {$skipped} pedidos ya estaban importados (omitidos)");
            }

            if ($imported == 0 && $skipped == 0 && $errors == 0) {
                Tools::log()->info("No hay pedidos nuevos para importar");
            }

            if ($errors > 0) {
                Tools::log()->warning("PrestaShop: {$errors} errores durante la importación");
            }

            // Guardar log de errores si hubo alguno
            if (!empty($this->importLog)) {
                $this->saveLog();
            }
        } catch (\Exception $e) {
            $errorMsg = 'Error en importación de pedidos: ' . $e->getMessage();
            Tools::log()->error($errorMsg);
            $this->logError($errorMsg);
            $this->saveLog();
        }
    }

    /**
     * Importa un pedido individual como albarán
     *
     * @return array|null Array con datos del albarán si se importó correctamente, null si falló
     */
    private function importOrder(\SimpleXMLElement $orderXml): ?array
    {
        $orderId = (int)$orderXml->id;
        $orderReference = (string)$orderXml->reference;

        // VALIDACIÓN: Verificar que el pedido tiene datos válidos
        if ($orderId <= 0) {
            Tools::log()->error("Pedido inválido: ID = 0. Posible error de conexión o datos corruptos.");
            throw new \Exception("Pedido con ID inválido (0). Verifica la conexión con PrestaShop.");
        }

        if (empty($orderReference)) {
            Tools::log()->warning("Pedido {$orderId} sin referencia, usando ID como referencia");
            $orderReference = "PS-{$orderId}";
        }

        // LOG: Inicio de importación
        Tools::log()->info("========================================");
        Tools::log()->info("IMPORTANDO PEDIDO: {$orderReference} (ID: {$orderId})");
        Tools::log()->info("========================================");

        // Verificar si ya existe un albarán con este número de pedido en numero2
        if ($this->albaranExists($orderReference)) {
            Tools::log()->debug("⊘ Albarán ya importado: {$orderReference}");
            return null;
        }

        // Obtener o crear cliente usando la dirección de facturación del pedido
        $customerId = (int)$orderXml->id_customer;
        $addressId = (int)$orderXml->id_address_invoice;

        // FALLBACK: Si no hay dirección de facturación, usar dirección de envío
        // Esto pasa en algunas versiones de PrestaShop cuando ambas direcciones son iguales
        if ($addressId <= 0) {
            $addressId = (int)$orderXml->id_address_delivery;
            Tools::log()->warning("Pedido {$orderId} sin dirección de facturación separada. Usando dirección de envío: {$addressId}");
        }

        // VALIDACIÓN: Verificar que el pedido tiene cliente y dirección
        if ($customerId <= 0 || $addressId <= 0) {
            Tools::log()->error("Pedido {$orderId} - Customer ID: {$customerId}, Invoice Address: {$orderXml->id_address_invoice}, Delivery Address: {$orderXml->id_address_delivery}");
            throw new \Exception("Pedido {$orderId} sin cliente ({$customerId}) o dirección ({$addressId}) válidos");
        }

        $cliente = $this->getOrCreateCliente($customerId, $addressId);
        if (!$cliente) {
            throw new \Exception("No se pudo obtener o crear el cliente para el pedido {$orderId}");
        }

        // Guardar datos del cliente para el log
        $codcliente = $cliente->codcliente;
        $nombrecliente = $cliente->nombre;

        // ECOTASA: Verificar que existe el producto ECOTASA-PRESTASHOP en FacturaScripts
        // Solo se verificará si el pedido tiene productos con ecotasa
        $this->verifyEcotaxProductExists();

        // Crear albarán
        $albaran = new AlbaranCliente();
        $albaran->setSubject($cliente);

        // PUNTO 9: Respetar régimen IVA del cliente (incluyendo recargo de equivalencia)
        // setSubject() ya copia regimeniva del cliente al albarán automáticamente

        $albaran->codalmacen = $this->config->codalmacen;
        $albaran->codserie = $this->config->codserie;

        // IMPORTANTE: Usar la fecha del ÚLTIMO estado del pedido, no la fecha de creación
        $lastStatusDate = $this->getLastOrderStatusDate($orderXml, $orderId);
        if ($lastStatusDate) {
            $albaran->fecha = date('d-m-Y', strtotime($lastStatusDate));
            $albaran->hora = date('H:i:s', strtotime($lastStatusDate));
            Tools::log()->info("Usando fecha del último estado: {$lastStatusDate}");
        } else {
            // Fallback: usar fecha de creación del pedido si no hay historial
            $albaran->fecha = date('d-m-Y', strtotime((string)$orderXml->date_add));
            $albaran->hora = date('H:i:s', strtotime((string)$orderXml->date_add));
            Tools::log()->info("Usando fecha de creación (sin historial): " . (string)$orderXml->date_add);
        }

        $albaran->numero2 = $orderReference; // Guardamos el número de pedido de PrestaShop en numero2
        $albaran->observaciones = "Importado de PrestaShop. ID: {$orderId}";

        // Asignar forma de pago según mapeo
        $paymentModule = (string)$orderXml->payment;
        if (!empty($paymentModule)) {
            $codpago = PrestashopPaymentMap::getCodPago($paymentModule);
            if ($codpago) {
                $albaran->codpago = $codpago;
                Tools::log()->info("✓ PAGO → PrestaShop: '{$paymentModule}' → FacturaScripts: '{$codpago}'");
            } else {
                Tools::log()->warning("⚠ PAGO → No hay mapeo para: '{$paymentModule}'");
            }
        } else {
            Tools::log()->warning("⚠ PAGO → Pedido sin método de pago");
        }

        // Obtener dirección de facturación (no de envío) y asignar TODOS los datos
        $addressId = (int)$orderXml->id_address_invoice;
        $address = $this->connection->getAddress($addressId);
        if ($address) {
            // El nombrecliente ya se ha asignado con setSubject($cliente)
            // Si el cliente es una empresa, el nombre ya es correcto
            // Si es particular, también es correcto
            // NO sobrescribir aquí - dejar que use los datos del cliente

            // Dirección completa
            $albaran->direccion = (string)$address->address1;
            if (!empty((string)$address->address2)) {
                $albaran->direccion .= "\n" . (string)$address->address2;
            }

            // Otros datos
            $albaran->codpostal = (string)$address->postcode;
            $albaran->ciudad = (string)$address->city;

            // Provincia: obtener el nombre desde el ID de estado
            $stateId = (int)$address->id_state;
            if ($stateId > 0) {
                $stateName = $this->connection->getStateName($stateId);
                if ($stateName) {
                    $albaran->provincia = $stateName;
                    Tools::log()->info("Provincia: {$stateName} (ID: {$stateId})");
                } else {
                    Tools::log()->warning("No se pudo obtener el nombre de la provincia con ID: {$stateId}");
                }
            }

            $albaran->apartado = (string)$address->other ?? '';

            // Teléfonos
            if (!empty((string)$address->phone)) {
                $albaran->telefono1 = (string)$address->phone;
            }
            if (!empty((string)$address->phone_mobile)) {
                $albaran->telefono2 = (string)$address->phone_mobile;
            }

            // País
            $albaran->codpais = $this->getCountryCode((int)$address->id_country);
        }

        if (!$albaran->save()) {
            throw new \Exception("No se pudo guardar el albarán para el pedido {$orderId}");
        }

        // Importar líneas de pedido
        // ECOTASA: Usar order_details en lugar de order_rows porque incluye campos ecotax
        // order_rows NO tiene los campos ecotax, por eso debemos usar order_details desde el API
        Tools::log()->info("Obteniendo order_details desde API PrestaShop (incluye ecotax)...");
        $orderDetails = $this->connection->getOrderDetails($orderId);

        if (empty($orderDetails)) {
            Tools::log()->warning("No se pudieron obtener order_details. Usando order_rows como fallback (sin ecotax)...");
            // Fallback a order_rows si order_details falla (pero no tendrá ecotax)
            if (isset($orderXml->associations->order_rows->order_row)) {
                foreach ($orderXml->associations->order_rows->order_row as $row) {
                    $orderDetails[] = [
                        'product_id' => (int)$row->product_id,
                        'product_reference' => (string)$row->product_reference,
                        'product_name' => (string)$row->product_name,
                        'product_quantity' => (int)$row->product_quantity,
                        'unit_price_tax_incl' => (float)$row->unit_price_tax_incl,
                        'unit_price_tax_excl' => (float)$row->unit_price_tax_excl,
                        'ecotax' => 0.0,  // order_rows NO tiene ecotax
                        'ecotax_tax_rate' => 21.0,
                    ];
                }
            }
        }

        Tools::log()->info("Procesando " . count($orderDetails) . " líneas de pedido con datos de ecotax...");

        $products = [];
        foreach ($orderDetails as $detail) {
            $unitPriceTaxIncl = $detail['unit_price_tax_incl'];
            $unitPriceTaxExcl = $detail['unit_price_tax_excl'];

            // ECOTASA: Leer ecotax desde order_details (viene con IVA incluido)
            $ecotaxTaxIncl = $detail['ecotax'];
            $ecotaxTaxRate = $detail['ecotax_tax_rate'] > 0 ? $detail['ecotax_tax_rate'] : 21.0;

            // DEBUG: Ver exactamente qué valor tiene ecotax para cada producto
            Tools::log()->debug("DEBUG ECOTAX → Producto: {$detail['product_reference']} | ecotax: {$ecotaxTaxIncl} | ecotax_tax_rate: {$ecotaxTaxRate}");

            // Calcular ecotax sin IVA (PrestaShop lo trae CON IVA)
            $ecotaxTaxExcl = 0.0;
            if ($ecotaxTaxIncl > 0 && $ecotaxTaxRate > 0) {
                $ecotaxTaxExcl = $ecotaxTaxIncl / (1 + ($ecotaxTaxRate / 100));
            }

            // IMPORTANTE: PrestaShop SUMA la ecotasa al precio del producto
            // Debemos RESTAR la ecotasa del precio para tener el precio real del producto
            $realProductPriceTaxIncl = $unitPriceTaxIncl;
            $realProductPriceTaxExcl = $unitPriceTaxExcl;

            if ($ecotaxTaxIncl > 0) {
                // Restar ecotasa del precio (PrestaShop incluye ecotasa en unit_price)
                $realProductPriceTaxIncl = $unitPriceTaxIncl - $ecotaxTaxIncl;
                $realProductPriceTaxExcl = $unitPriceTaxExcl - $ecotaxTaxExcl;

                Tools::log()->info("ECOTASA detectada: {$ecotaxTaxIncl}€ (con IVA) / " . round($ecotaxTaxExcl, 2) . "€ (sin IVA) - IVA: {$ecotaxTaxRate}%");
                Tools::log()->info("Precio original: " . round($unitPriceTaxExcl, 2) . "€ → Precio real producto: " . round($realProductPriceTaxExcl, 2) . "€");
            }

            // Detectar el IVA correcto redondeando al legal más cercano (21%, 10%, 4%, 0%)
            // En lugar de usar el IVA calculado con decimales raros
            $taxRate = 21; // Por defecto 21%

            if ($realProductPriceTaxExcl > 0 && $realProductPriceTaxIncl > $realProductPriceTaxExcl) {
                // Calcular IVA aproximado desde los precios (usando precio real sin ecotasa)
                $calculatedRate = (($realProductPriceTaxIncl / $realProductPriceTaxExcl) - 1) * 100;

                // Redondear al IVA legal español más cercano
                if ($calculatedRate >= 18) {
                    $taxRate = 21; // IVA general
                } elseif ($calculatedRate >= 7) {
                    $taxRate = 10; // IVA reducido
                } elseif ($calculatedRate >= 2) {
                    $taxRate = 4;  // IVA superreducido
                } else {
                    $taxRate = 0;  // Exento
                }
            } elseif ($realProductPriceTaxExcl == 0 || $realProductPriceTaxIncl == $realProductPriceTaxExcl) {
                $taxRate = 0; // Sin IVA o exento
            }

            $products[] = [
                'product_id' => $detail['product_id'],
                'product_reference' => $detail['product_reference'],
                'product_name' => $detail['product_name'],
                'product_quantity' => $detail['product_quantity'],
                'unit_price_tax_incl' => $realProductPriceTaxIncl,  // Precio real SIN ecotasa
                'unit_price_tax_excl' => $realProductPriceTaxExcl,  // Precio real SIN ecotasa
                'tax_rate' => $taxRate,  // IVA legal correcto (21, 10, 4, 0)
                'ecotax_tax_incl' => $ecotaxTaxIncl,  // Ecotasa CON IVA
                'ecotax_tax_excl' => $ecotaxTaxExcl,  // Ecotasa SIN IVA
                'ecotax_tax_rate' => $ecotaxTaxRate,  // IVA de la ecotasa
            ];
        }

        if (empty($products)) {
            Tools::log()->warning("Pedido {$orderId} sin productos. Verifica que associations->order_rows esté en el XML.");
        }

        foreach ($products as $product) {
            // Añadir línea de producto
            $this->addLineaAlbaran($albaran, $product);

            // ECOTASA: Si el producto tiene ecotasa, añadir línea de ecotasa inmediatamente después
            if ($product['ecotax_tax_excl'] > 0) {
                $this->addEcotaxLine(
                    $albaran,
                    $product['ecotax_tax_excl'],      // Ecotasa SIN IVA
                    $product['ecotax_tax_rate'],      // IVA de la ecotasa
                    $product['product_quantity'],      // Misma cantidad que el producto
                    $product['product_name']           // Nombre del producto (para logs)
                );
            }
        }

        // Detectar si todos los productos tienen IVA 0% (pedido exento)
        $allProductsZeroTax = true;
        foreach ($products as $product) {
            if ($product['tax_rate'] > 0) {
                $allProductsZeroTax = false;
                break;
            }
        }

        // IVA a usar para transporte y descuentos
        $ivaTransporteDescuento = $allProductsZeroTax ? 0 : 21;

        if ($allProductsZeroTax) {
            Tools::log()->info("✓ Pedido EXENTO (todos productos IVA 0%) - Transporte y descuentos usarán IVA 0%");
        }

        // Añadir línea de gastos de envío si existe
        $totalShipping = (float)$orderXml->total_shipping;
        if ($totalShipping > 0) {
            $this->addShippingLine($albaran, $totalShipping, $ivaTransporteDescuento);
        }

        // Añadir línea de empaquetado para regalo si existe
        $totalWrapping = (float)$orderXml->total_wrapping_tax_incl;
        if ($totalWrapping > 0) {
            $this->addGiftWrappingLine($albaran, $totalWrapping, $ivaTransporteDescuento);
        }

        // Añadir línea de descuento/cupón si existe
        $totalDiscountWithTax = (float)$orderXml->total_discounts_tax_incl;
        if ($totalDiscountWithTax > 0) {
            // Intentar obtener el nombre del cupón/descuento
            $discountName = $this->getDiscountName($orderXml);
            $this->addDiscountLine($albaran, $totalDiscountWithTax, $discountName, $ivaTransporteDescuento);
        }

        // ECOTASA: Si hay productos con ecotasa, añadir texto legal a observaciones
        $hasEcotax = false;
        foreach ($products as $product) {
            if ($product['ecotax_tax_excl'] > 0) {
                $hasEcotax = true;
                break;
            }
        }

        if ($hasEcotax) {
            // Añadir texto legal SIN borrar el existente
            $textoLegal = "\n\nEcotasa AT. Ecotasa S.I Gestión NFU -RD 731/2020-\nNº de Registro de productos: O00002023e2100205757-\nNº de Registro de productores: NEU/2021/000000063";
            $albaran->observaciones .= $textoLegal;
            Tools::log()->info("✓ Texto legal de ecotasa añadido a observaciones");
        }

        // === CÓDIGO ANTIGUO (comentado) ===
        // $this->calculateTotals($albaran);

        // === CÓDIGO NUEVO ===
        // Calculator::calculate() recalcula TODOS los totales del documento
        // Esto garantiza que neto + totaliva = total y pasa la validación de FacturaScripts
        $lineas = $albaran->getLines();
        Calculator::calculate($albaran, $lineas, true);

        Tools::log()->info("========================================");
        Tools::log()->info("✓ ALBARÁN CREADO: {$albaran->codigo}");
        Tools::log()->info("  Neto: {$albaran->neto}€ | IVA: {$albaran->totaliva}€ | TOTAL: {$albaran->total}€");
        Tools::log()->info("========================================");

        // Devolver datos del albarán para el log
        return [
            'idalbaran' => $albaran->idalbaran,
            'codcliente' => $codcliente,
            'nombrecliente' => $nombrecliente,
            'total' => $albaran->total
        ];
    }

    /**
     * Verifica si ya existe un albarán con este número de pedido
     */
    private function albaranExists(string $orderReference): bool
    {
        $albaran = new AlbaranCliente();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('numero2', $orderReference)];
        return $albaran->loadFromCode('', $where);
    }

    /**
     * Verifica si un pedido ya fue importado (alias de albaranExists)
     */
    private function isOrderImported(string $orderReference): bool
    {
        return $this->albaranExists($orderReference);
    }

    /**
     * Obtiene o crea un cliente desde PrestaShop
     *
     * @param int $customerId ID del cliente en PrestaShop
     * @param int $addressId ID de la dirección de facturación del pedido
     */
    private function getOrCreateCliente(int $customerId, int $addressId): ?Cliente
    {
        $customerXml = $this->connection->getCustomer($customerId);
        if (!$customerXml) {
            return null;
        }

        // Obtener email del cliente
        $email = trim((string)$customerXml->email);

        // Obtener dirección de facturación del pedido para sacar el NIF/CIF real
        $addressXml = null;
        if ($addressId > 0) {
            $addressXml = $this->connection->getAddress($addressId);
        }

        // Extraer datos de la dirección
        $vat_number = '';
        $dni = '';
        $empresa = '';
        $isCompany = false;

        if ($addressXml) {
            // VAT number (CIF empresarial)
            $vat_number = trim((string)$addressXml->vat_number);

            // DNI personal (separado del VAT)
            $dni = trim((string)$addressXml->dni);

            // Nombre de empresa
            $empresa = trim((string)$addressXml->company);

            // CRÍTICO: Solo es empresa si tiene AMBOS campos REALMENTE llenos (no vacíos ni espacios)
            // Verificar con strlen para asegurar que no sean cadenas vacías
            $isCompany = (strlen($empresa) > 0) && (strlen($vat_number) > 0);

            Tools::log()->info("Empresa: '{$empresa}' | VAT: '{$vat_number}' | DNI: '{$dni}' | isCompany: " . ($isCompany ? 'SI' : 'NO'));
        }

        // Buscar cliente existente SOLO por CIF/NIF/DNI (ignorando formato)
        // Priorizar VAT, si no existe usar DNI
        $cifnif_busqueda = !empty($vat_number) ? $vat_number : $dni;
        if (!empty($cifnif_busqueda)) {
            $clienteExistente = $this->findClienteByCifNif($cifnif_busqueda);
            if ($clienteExistente) {
                // IMPORTANTE: Actualizar TODOS los datos del cliente existente
                $actualizado = false;

                // Actualizar email si existe y es diferente
                if (!empty($email) && $clienteExistente->email !== $email) {
                    $clienteExistente->email = $email;
                    $actualizado = true;
                    Tools::log()->info("Actualizando email del cliente existente: {$email}");
                }

                // Actualizar datos de la dirección
                if ($addressXml) {
                    // Teléfonos
                    $telefonoNuevo = '';
                    if (!empty((string)$addressXml->phone_mobile)) {
                        $telefonoNuevo = (string)$addressXml->phone_mobile;
                    } elseif (!empty((string)$addressXml->phone)) {
                        $telefonoNuevo = (string)$addressXml->phone;
                    }

                    if (!empty($telefonoNuevo) && $clienteExistente->telefono1 !== $telefonoNuevo) {
                        $clienteExistente->telefono1 = $telefonoNuevo;
                        $actualizado = true;
                        Tools::log()->info("Actualizando teléfono del cliente existente: {$telefonoNuevo}");
                    }

                    // Dirección completa
                    $direccionNueva = (string)$addressXml->address1;
                    if (!empty((string)$addressXml->address2)) {
                        $direccionNueva .= "\n" . (string)$addressXml->address2;
                    }
                    if (!empty($direccionNueva) && $clienteExistente->direccion !== $direccionNueva) {
                        $clienteExistente->direccion = $direccionNueva;
                        $actualizado = true;
                        Tools::log()->info("Actualizando dirección del cliente existente");
                    }

                    // Código postal
                    $codpostalNuevo = (string)$addressXml->postcode;
                    if (!empty($codpostalNuevo) && $clienteExistente->codpostal !== $codpostalNuevo) {
                        $clienteExistente->codpostal = $codpostalNuevo;
                        $actualizado = true;
                        Tools::log()->info("Actualizando código postal: {$codpostalNuevo}");
                    }

                    // Ciudad
                    $ciudadNueva = (string)$addressXml->city;
                    if (!empty($ciudadNueva) && $clienteExistente->ciudad !== $ciudadNueva) {
                        $clienteExistente->ciudad = $ciudadNueva;
                        $actualizado = true;
                        Tools::log()->info("Actualizando ciudad: {$ciudadNueva}");
                    }

                    // Provincia
                    $stateId = (int)$addressXml->id_state;
                    if ($stateId > 0) {
                        $provinciaNueva = $this->connection->getStateName($stateId);
                        if (!empty($provinciaNueva) && $clienteExistente->provincia !== $provinciaNueva) {
                            $clienteExistente->provincia = $provinciaNueva;
                            $actualizado = true;
                            Tools::log()->info("Actualizando provincia: {$provinciaNueva}");
                        }
                    }

                    // País
                    $countryId = (int)$addressXml->id_country;
                    if ($countryId > 0) {
                        $paisNuevo = $this->getCountryCode($countryId);
                        if (!empty($paisNuevo) && $clienteExistente->codpais !== $paisNuevo) {
                            $clienteExistente->codpais = $paisNuevo;
                            $actualizado = true;
                            Tools::log()->info("Actualizando país: {$paisNuevo}");
                        }
                    }
                }

                // Guardar si se actualizó algo
                if ($actualizado) {
                    $clienteExistente->save();
                    Tools::log()->info("✓ Cliente actualizado con nuevos datos de dirección");
                }

                return $clienteExistente;
            }
        }

        // Crear nuevo cliente - Usar datos de la dirección de facturación
        $cliente = new Cliente();
        $nombrePersona = '';
        if ($addressXml) {
            // Usar nombre de la dirección de facturación (invoice address)
            $nombrePersona = trim((string)$addressXml->firstname . ' ' . (string)$addressXml->lastname);
        }

        // Si no hay dirección, usar datos del customer como fallback
        if (empty($nombrePersona)) {
            $nombrePersona = trim((string)$customerXml->firstname . ' ' . (string)$customerXml->lastname);
        }

        // Si aún no tiene nombre, el pedido tiene datos inválidos - NO crear cliente falso
        if (empty($nombrePersona)) {
            Tools::log()->error("Cliente {$customerId} sin nombre válido. El pedido no tiene dirección de facturación correcta.");
            Tools::log()->error("Dirección ID: {$addressId} - Verifica que el pedido tenga invoice_address en PrestaShop");
            return null; // No crear clientes con datos inventados
        }

        if ($isCompany) {
            // Es una empresa (tiene empresa Y VAT)
            $cliente->nombre = $nombrePersona; // Nombre del contacto
            $cliente->razonsocial = $empresa; // Razón social de la empresa
            $cliente->personafisica = false; // NO es persona física
            $cliente->cifnif = $vat_number; // CIF de la empresa

            // PUNTO 8: Asignar tipoidfiscal según formato
            // Intentar detectar CIF por formato: letra A-W + 7 dígitos + dígito o letra
            if (preg_match('/^[A-W][0-9]{7}[0-9A-J]$/', $vat_number)) {
                $cliente->tipoidfiscal = 'CIF';
                Tools::log()->info("Creando cliente empresa: {$empresa} (CIF: {$vat_number})");
            } else {
                $cliente->tipoidfiscal = 'NIF'; // Fallback genérico para empresas con formato no reconocible
                Tools::log()->info("Creando cliente empresa: {$empresa} (NIF: {$vat_number} - formato no estándar)");
            }
        } else {
            // Es un particular (no tiene empresa O no tiene VAT)
            $cliente->nombre = $nombrePersona;
            $cliente->razonsocial = $nombrePersona;
            $cliente->personafisica = true; // Es persona física

            // Usar DNI/CIF real. Priorizar VAT, si no existe usar DNI, si no existe usar genérico
            if (!empty($vat_number)) {
                $cliente->cifnif = $vat_number;

                // PUNTO 8: Asignar tipoidfiscal según formato
                if (preg_match('/^\d{8}[A-Z]$/', $vat_number)) {
                    $cliente->tipoidfiscal = 'DNI'; // DNI: 8 dígitos + letra
                    Tools::log()->info("Creando cliente particular: {$nombrePersona} (DNI: {$vat_number})");
                } elseif (preg_match('/^[XYZ]\d{7}[A-Z]$/', $vat_number)) {
                    $cliente->tipoidfiscal = 'NIE'; // NIE: X/Y/Z + 7 dígitos + letra
                    Tools::log()->info("Creando cliente particular: {$nombrePersona} (NIE: {$vat_number})");
                } else {
                    $cliente->tipoidfiscal = 'DNI'; // Fallback a DNI para particulares (compatible Verifactu)
                    Tools::log()->info("Creando cliente particular: {$nombrePersona} (DNI: {$vat_number} - formato no estándar)");
                }
            } elseif (!empty($dni)) {
                $cliente->cifnif = $dni;

                // PUNTO 8: Asignar tipoidfiscal según formato
                if (preg_match('/^\d{8}[A-Z]$/', $dni)) {
                    $cliente->tipoidfiscal = 'DNI'; // DNI: 8 dígitos + letra
                    Tools::log()->info("Creando cliente particular: {$nombrePersona} (DNI: {$dni})");
                } elseif (preg_match('/^[XYZ]\d{7}[A-Z]$/', $dni)) {
                    $cliente->tipoidfiscal = 'NIE'; // NIE: X/Y/Z + 7 dígitos + letra
                    Tools::log()->info("Creando cliente particular: {$nombrePersona} (NIE: {$dni})");
                } else {
                    $cliente->tipoidfiscal = 'DNI'; // Fallback a DNI para particulares (compatible Verifactu)
                    Tools::log()->info("Creando cliente particular: {$nombrePersona} (DNI: {$dni} - formato no estándar)");
                }
            } else {
                // DNI único identificable por cliente (PSECOM + ID de PrestaShop)
                $cliente->cifnif = 'PSECOM' . str_pad($customerId, 6, '0', STR_PAD_LEFT);
                $cliente->tipoidfiscal = 'DNI'; // PSECOM para particulares siempre como DNI (compatible Verifactu)
                Tools::log()->warning("Cliente {$customerId} sin DNI/CIF en PrestaShop. Usando DNI temporal: {$cliente->cifnif}" . (!empty($email) ? ". Email: {$email}" : ""));
            }
        }

        // IMPORTANTE: Importar email (esencial para envío de facturas)
        if (!empty($email)) {
            $cliente->email = $email;
            Tools::log()->info("Email del cliente: {$email}");
        } else {
            Tools::log()->warning("Cliente {$customerId} sin email en PrestaShop");
        }

        // IMPORTANTE: Importar teléfono desde la dirección de facturación
        if ($addressXml) {
            if (!empty((string)$addressXml->phone_mobile)) {
                $cliente->telefono1 = (string)$addressXml->phone_mobile;
                Tools::log()->info("Teléfono móvil del cliente: {$cliente->telefono1}");
            } elseif (!empty((string)$addressXml->phone)) {
                $cliente->telefono1 = (string)$addressXml->phone;
                Tools::log()->info("Teléfono del cliente: {$cliente->telefono1}");
            }
        }

        // Dejar que FacturaScripts genere el codcliente automáticamente
        // En FacturaScripts 2025 se genera automáticamente si se deja vacío

        $cliente->observaciones = "Importado de PrestaShop. ID: {$customerId}";

        if ($cliente->save()) {
            Tools::log()->info("Cliente creado: {$cliente->codcliente} - {$cliente->nombre}");
            return $cliente;
        }

        return null;
    }

    /**
     * Busca un cliente por CIF/NIF/DNI ignorando mayúsculas y posición de letra
     */
    private function findClienteByCifNif(string $cifnif): ?Cliente
    {
        $cifNormalizado = $this->normalizeCifNif($cifnif);

        // Buscar todos los clientes y comparar normalizados
        $cliente = new Cliente();
        $allClientes = $cliente->all([], [], 0, 0);

        foreach ($allClientes as $cli) {
            if (!empty($cli->cifnif)) {
                $cliNormalizado = $this->normalizeCifNif($cli->cifnif);
                if ($cifNormalizado === $cliNormalizado) {
                    Tools::log()->info("Cliente encontrado por CIF/NIF: {$cli->cifnif} (original: {$cifnif})");
                    return $cli;
                }
            }
        }

        return null;
    }

    /**
     * Normaliza un CIF/NIF/DNI para comparación flexible
     * Ejemplo: "B12345678" == "b12345678" == "12345678B" == "12345678b"
     */
    private function normalizeCifNif(string $cifnif): string
    {
        // Eliminar espacios y guiones
        $cifnif = str_replace([' ', '-'], '', $cifnif);

        // Convertir a mayúsculas
        $cifnif = strtoupper($cifnif);

        // Extraer números y letras
        preg_match_all('/[A-Z0-9]/', $cifnif, $matches);
        $chars = $matches[0] ?? [];

        // Separar números y letras
        $numeros = [];
        $letras = [];

        foreach ($chars as $char) {
            if (is_numeric($char)) {
                $numeros[] = $char;
            } else {
                $letras[] = $char;
            }
        }

        // Formato normalizado: números + letras (todo en mayúsculas)
        return implode('', $numeros) . implode('', $letras);
    }

    /**
     * Genera un código de cliente único válido (solo letras y números, sin espacios)
     */
    private function generateCodCliente(string $nombre, int $customerId): string
    {
        // Limpiar el nombre: solo letras y números
        $base = preg_replace('/[^A-Za-z0-9]/', '', $nombre);
        $base = strtoupper(substr($base, 0, 6));

        // Si está vacío, usar PS
        if (empty($base)) {
            $base = 'PS';
        }

        $codigo = $base . $customerId;
        $counter = 1;

        $cliente = new Cliente();
        while ($cliente->loadFromCode($codigo)) {
            $codigo = $base . $customerId . $counter;
            $counter++;
            if ($counter > 100) {
                // Fallback: usar solo el ID
                $codigo = 'PS' . $customerId . rand(1000, 9999);
                break;
            }
        }

        return substr($codigo, 0, 10); // Max 10 caracteres
    }

    /**
     * Añade una línea al albarán
     */
    private function addLineaAlbaran(AlbaranCliente $albaran, array $product): void
    {
        $linea = new LineaAlbaranCliente();
        $linea->idalbaran = $albaran->idalbaran;
        $linea->cantidad = $product['product_quantity'];
        $linea->pvpunitario = $product['unit_price_tax_excl'];
        $linea->descripcion = $product['product_name'];

        $referencia = $product['product_reference'] ?? 'PS-' . $product['product_id'];

        // Buscar producto por referencia
        if (!empty($referencia)) {
            $variante = new Variante();
            $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $referencia)];

            if ($variante->loadFromCode('', $where)) {
                // Producto existe
                $linea->idproducto = $variante->idproducto;
                $linea->referencia = $variante->referencia;
                // Mantener descripción del producto de FacturaScripts si existe
                $producto = new Producto();
                if ($producto->loadFromCode($variante->idproducto)) {
                    $linea->descripcion = $producto->descripcion;
                }
            } else {
                // Producto NO existe - Crear automáticamente
                $nuevoProducto = $this->crearProducto($product, $referencia);
                if ($nuevoProducto) {
                    $linea->idproducto = $nuevoProducto->idproducto;
                    $linea->referencia = $referencia;
                    $linea->descripcion = $nuevoProducto->descripcion;
                }else {
                    // Si falla la creación, crear línea sin producto
                    $linea->referencia = $referencia;
                }
            }
        }

        // CRÍTICO: Asignar codimpuesto DESPUÉS de asignar el producto
        // Esto sobrescribe el codimpuesto del producto con el correcto según PrestaShop
        $taxRate = $product['tax_rate'] ?? 21;
        $codimpuesto = PrestashopTaxMap::getCodImpuesto($taxRate);

        // ============================================================================
        // SOLUCIÓN CORRECTA (2025): Calculator + asignación manual de IVA
        // Calculator::calculateLine() NO calcula IVA, solo pvptotal
        // Para REVERTIR: descomentar "CÓDIGO ANTIGUO" y eliminar "CÓDIGO NUEVO"
        // ============================================================================

        // === CÓDIGO ANTIGUO (comentado - cálculo 100% manual) ===
        /*
        if ($codimpuesto) {
            // Mapeo encontrado: usar el codimpuesto mapeado
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $taxRate;
        } else {
            // Sin mapeo: solo asignar IVA y advertir
            $linea->iva = $taxRate;
            Tools::log()->warning("⚠ IVA {$taxRate}% sin mapear para producto {$referencia}. Configura el mapeo de IVA en Prestashop → Mapeo de Tipos de IVA");
        }

        // IMPORTANTE: Calcular pvptotal para que se muestre correctamente en la vista
        $linea->pvptotal = round(
            $linea->pvpunitario * $linea->cantidad * (1 - $linea->dtopor / 100) * (1 - $linea->dtopor2 / 100),
            2
        );

        // NO hay recargo de equivalencia
        $linea->recargo = 0;

        $linea->save();
        */

        // === CÓDIGO NUEVO (usa Calculator de FacturaScripts correctamente) ===
        // PASO 1: Asignar IVA manualmente (Calculator NO lo calcula)
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $taxRate; // CRÍTICO: debe asignarse manualmente
        } else {
            $linea->iva = $taxRate;
            Tools::log()->warning("⚠ IVA {$taxRate}% sin mapear para producto {$referencia}. Configura el mapeo de IVA en Prestashop → Mapeo de Tipos de IVA");
        }

        // PUNTO 9: NO forzar recargo = 0, dejar que Calculator lo calcule automáticamente
        // si el cliente tiene recargo de equivalencia configurado

        // PASO 2: Calculator calcula pvpsindto, pvptotal y recargo (si aplica)
        Calculator::calculateLine($albaran, $linea);

        // PASO 3: Guardar línea
        $linea->save();
        // ============================================================================

        // LOG DETALLADO para debug
        $precioConIva = round($linea->pvpunitario * (1 + $linea->iva / 100), 2);
        Tools::log()->info("✓ PRODUCTO → {$referencia} | Cant: {$linea->cantidad} | Sin IVA: {$linea->pvpunitario}€ | IVA: {$linea->iva}% | Con IVA: {$precioConIva}€");
    }

    /**
     * Crea un producto en FacturaScripts desde datos de PrestaShop
     */
    private function crearProducto(array $product, string $referencia): ?Producto
    {
        try {
            $producto = new Producto();
            $producto->referencia = $referencia;  // Añadir referencia al producto
            $producto->descripcion = $product['product_name'];
            $producto->precio = (float)$product['unit_price_tax_excl'];
            $producto->nostock = false; // Igual que ProductsDownload
            $producto->ventasinstock = true; // Permitir ventas sin stock
            $producto->sevende = true;  // Se puede vender
            $producto->secompra = true;  // Se puede comprar
            $producto->bloqueado = false;

            // Obtener IVA desde mapeo
            if (isset($product['tax_rate'])) {
                $taxRate = (float)$product['tax_rate'];
                $codimpuesto = PrestashopTaxMap::getCodImpuesto($taxRate);
                if ($codimpuesto) {
                    $producto->codimpuesto = $codimpuesto;
                }
            }

            // Si no hay mapeo, usar IVA por defecto (21%)
            if (empty($producto->codimpuesto)) {
                $producto->codimpuesto = 'IVA21'; // Ajustar según tu configuración
            }

            if ($producto->save()) {
                // Actualizar la variante principal con la referencia
                $variante = $producto->getVariants()[0] ?? null;
                if ($variante) {
                    $variante->referencia = $referencia;
                    $variante->save();

                    // Descargar stock REAL de PrestaShop y actualizarlo
                    $productId = $product['product_id'] ?? 0;
                    if ($productId > 0) {
                        $stockReal = $this->getStockFromPrestashop($productId);
                        $this->actualizarStockVariante($variante->idvariante, $stockReal, $referencia);
                        Tools::log()->info("Stock actualizado desde PrestaShop: {$stockReal} unidades");
                    }
                }

                Tools::log()->info("Producto creado automáticamente: {$referencia} - {$producto->descripcion}");
                return $producto;
            }

            return null;
        } catch (\Exception $e) {
            Tools::log()->error("Error creando producto {$referencia}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el código de país de FacturaScripts desde el ID de PrestaShop
     */
    private function getCountryCode(int $countryId): string
    {
        // Obtener el país directamente desde PrestaShop para usar su iso_code
        $countryXml = $this->connection->getCountry($countryId);

        if ($countryXml && !empty((string)$countryXml->iso_code)) {
            $isoCode2 = strtoupper(trim((string)$countryXml->iso_code)); // ISO de 2 letras

            // Mapeo de ISO 3166-1 alpha-2 (PrestaShop) a alpha-3 (FacturaScripts)
            $isoMap = [
                'ES' => 'ESP', // España
                'FR' => 'FRA', // Francia
                'DE' => 'DEU', // Alemania
                'IT' => 'ITA', // Italia
                'GB' => 'GBR', // Reino Unido
                'US' => 'USA', // Estados Unidos
                'PT' => 'PRT', // Portugal
                'BE' => 'BEL', // Bélgica
                'NL' => 'NLD', // Países Bajos
                'CH' => 'CHE', // Suiza
                'AT' => 'AUT', // Austria
                'PL' => 'POL', // Polonia
                'CZ' => 'CZE', // República Checa
                'RO' => 'ROU', // Rumania
                'SE' => 'SWE', // Suecia
                'DK' => 'DNK', // Dinamarca
                'NO' => 'NOR', // Noruega
                'FI' => 'FIN', // Finlandia
                'IE' => 'IRL', // Irlanda
                'GR' => 'GRC', // Grecia
                'HU' => 'HUN', // Hungría
                'SK' => 'SVK', // Eslovaquia
                'SI' => 'SVN', // Eslovenia
                'HR' => 'HRV', // Croacia
                'BG' => 'BGR', // Bulgaria
                'LT' => 'LTU', // Lituania
                'LV' => 'LVA', // Letonia
                'EE' => 'EST', // Estonia
                'MT' => 'MLT', // Malta
                'CY' => 'CYP', // Chipre
                'LU' => 'LUX', // Luxemburgo
                'MX' => 'MEX', // México
                'AR' => 'ARG', // Argentina
                'BR' => 'BRA', // Brasil
                'CL' => 'CHL', // Chile
                'CO' => 'COL', // Colombia
                'PE' => 'PER', // Perú
                'VE' => 'VEN', // Venezuela
                'CA' => 'CAN', // Canadá
                'AU' => 'AUS', // Australia
                'NZ' => 'NZL', // Nueva Zelanda
                'CN' => 'CHN', // China
                'JP' => 'JPN', // Japón
                'IN' => 'IND', // India
                'RU' => 'RUS', // Rusia
                'TR' => 'TUR', // Turquía
                'ZA' => 'ZAF', // Sudáfrica
                'MA' => 'MAR', // Marruecos
                'DZ' => 'DZA', // Argelia
                'TN' => 'TUN', // Túnez
                'EG' => 'EGY', // Egipto
            ];

            if (isset($isoMap[$isoCode2])) {
                $isoCode3 = $isoMap[$isoCode2];
                Tools::log()->info("País: {$isoCode2} → {$isoCode3} (ID PrestaShop: {$countryId})");
                return $isoCode3;
            } else {
                Tools::log()->warning("Código ISO-2 '{$isoCode2}' no mapeado a ISO-3. Usando ESP por defecto.");
                return 'ESP';
            }
        }

        // Fallback: España por defecto si no se puede obtener
        Tools::log()->warning("No se pudo obtener el código ISO del país ID {$countryId}. Usando ESP por defecto.");
        return 'ESP';
    }

    /**
     * Añade línea de gastos de envío al albarán
     *
     * @param AlbaranCliente $albaran Albarán
     * @param float $shippingCostWithTax Coste con IVA
     * @param float $ivaTransporte IVA a aplicar (0, 4, 10, 21)
     */
    private function addShippingLine(AlbaranCliente $albaran, float $shippingCostWithTax, float $ivaTransporte = 21): void
    {
        // Buscar el producto de envío
        $variante = new Variante();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', 'ENVIO-PRESTASHOP')];

        if (!$variante->loadFromCode('', $where)) {
            $error = "⚠ CRÍTICO: Producto 'Gastos de envío' con referencia ENVIO-PRESTASHOP no encontrado. Reinstala el plugin o créalo manualmente con IVA 21%.";
            Tools::log()->critical($error);
            throw new \Exception($error);
        }

        // IMPORTANTE: total_shipping viene CON IVA, hay que quitárselo
        $shippingCostWithoutTax = $ivaTransporte > 0
            ? $shippingCostWithTax / (1 + $ivaTransporte / 100)
            : $shippingCostWithTax; // Si IVA es 0%, el precio ya es sin IVA

        $linea = new LineaAlbaranCliente();
        $linea->idalbaran = $albaran->idalbaran;
        $linea->idproducto = $variante->idproducto;
        $linea->referencia = $variante->referencia;
        $linea->descripcion = 'Gastos de envío';
        $linea->cantidad = 1;
        $linea->pvpunitario = round($shippingCostWithoutTax, 2); // Precio SIN IVA

        // Asignar codimpuesto correcto para el transporte
        $codimpuesto = PrestashopTaxMap::getCodImpuesto($ivaTransporte);

        // === CÓDIGO ANTIGUO (comentado) ===
        /*
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaTransporte;
        } else {
            $linea->iva = $ivaTransporte;
            Tools::log()->warning("⚠ IVA {$ivaTransporte}% sin mapear para transporte. Configura el mapeo de IVA.");
        }
        $linea->pvptotal = round($linea->pvpunitario * $linea->cantidad, 2);
        $linea->recargo = 0;
        $linea->save();
        */

        // === CÓDIGO NUEVO ===
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaTransporte; // Asignar IVA manualmente
        } else {
            $linea->iva = $ivaTransporte;
            Tools::log()->warning("⚠ IVA {$ivaTransporte}% sin mapear para transporte. Configura el mapeo de IVA.");
        }
        // PUNTO 9: NO forzar recargo = 0, Calculator lo calcula automáticamente
        Calculator::calculateLine($albaran, $linea); // Calculator calcula pvptotal y recargo
        $linea->save();

        Tools::log()->info("✓ ENVÍO → Con IVA: {$shippingCostWithTax}€ | Sin IVA: {$linea->pvpunitario}€ | IVA: {$ivaTransporte}%");
    }

    /**
     * Añade línea de empaquetado para regalo al albarán
     *
     * @param AlbaranCliente $albaran Albarán
     * @param float $wrappingCostWithTax Coste con IVA
     * @param float $ivaRegalo IVA a aplicar (0, 4, 10, 21)
     */
    private function addGiftWrappingLine(AlbaranCliente $albaran, float $wrappingCostWithTax, float $ivaRegalo = 21): void
    {
        // Buscar el producto de empaquetado para regalo
        $variante = new Variante();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', 'REGALO-PRESTASHOP')];

        if (!$variante->loadFromCode('', $where)) {
            $error = "⚠ CRÍTICO: Producto 'Empaquetado para regalo' con referencia REGALO-PRESTASHOP no encontrado. Reinstala el plugin o créalo manualmente con IVA 21%.";
            Tools::log()->critical($error);
            throw new \Exception($error);
        }

        // IMPORTANTE: total_wrapping viene CON IVA, hay que quitárselo
        $wrappingCostWithoutTax = $ivaRegalo > 0
            ? $wrappingCostWithTax / (1 + $ivaRegalo / 100)
            : $wrappingCostWithTax; // Si IVA es 0%, el precio ya es sin IVA

        $linea = new LineaAlbaranCliente();
        $linea->idalbaran = $albaran->idalbaran;
        $linea->idproducto = $variante->idproducto;
        $linea->referencia = $variante->referencia;
        $linea->descripcion = 'Empaquetado para regalo';
        $linea->cantidad = 1;
        $linea->pvpunitario = round($wrappingCostWithoutTax, 2); // Precio SIN IVA

        // Asignar codimpuesto correcto para el empaquetado
        $codimpuesto = PrestashopTaxMap::getCodImpuesto($ivaRegalo);

        // === CÓDIGO ANTIGUO (comentado) ===
        /*
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaRegalo;
        } else {
            $linea->iva = $ivaRegalo;
            Tools::log()->warning("⚠ IVA {$ivaRegalo}% sin mapear para empaquetado. Configura el mapeo de IVA.");
        }
        $linea->pvptotal = round($linea->pvpunitario * $linea->cantidad, 2);
        $linea->recargo = 0;
        $linea->save();
        */

        // === CÓDIGO NUEVO ===
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaRegalo; // Asignar IVA manualmente
        } else {
            $linea->iva = $ivaRegalo;
            Tools::log()->warning("⚠ IVA {$ivaRegalo}% sin mapear para empaquetado. Configura el mapeo de IVA.");
        }
        // PUNTO 9: NO forzar recargo = 0, Calculator lo calcula automáticamente
        Calculator::calculateLine($albaran, $linea); // Calculator calcula pvptotal y recargo
        $linea->save();

        Tools::log()->info("✓ REGALO → Con IVA: {$wrappingCostWithTax}€ | Sin IVA: {$linea->pvpunitario}€ | IVA: {$ivaRegalo}%");
    }

    /**
     * Añade línea de descuento/cupón al albarán
     *
     * @param AlbaranCliente $albaran Albarán al que añadir el descuento
     * @param float $discountWithTax Importe del descuento CON IVA incluido desde PrestaShop
     * @param string $discountName Nombre del cupón/descuento desde PrestaShop
     * @param float $ivaDescuento IVA a aplicar (0, 4, 10, 21)
     */
    private function addDiscountLine(AlbaranCliente $albaran, float $discountWithTax, string $discountName = '', float $ivaDescuento = 21): void
    {
        // PrestaShop trae el descuento con IVA incluido (total_discounts_tax_incl)
        // Calcular el importe sin IVA para la línea
        $discountWithoutTax = $ivaDescuento > 0
            ? $discountWithTax / (1 + $ivaDescuento / 100)
            : $discountWithTax; // Si IVA es 0%, el precio ya es sin IVA

        // Crear línea negativa (descuento) con IVA 21%
        $linea = new LineaAlbaranCliente();
        $linea->idalbaran = $albaran->idalbaran;
        $linea->referencia = 'DCTO-PS';
        $linea->descripcion = !empty($discountName) ? $discountName : 'Descuento / Cupón';
        $linea->cantidad = 1;
        $linea->pvpunitario = -round($discountWithoutTax, 2); // Precio NEGATIVO sin IVA

        // Asignar IVA según el pedido
        $codimpuesto = PrestashopTaxMap::getCodImpuesto($ivaDescuento);

        // === CÓDIGO ANTIGUO (comentado) ===
        /*
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaDescuento;
        } else {
            $linea->iva = $ivaDescuento;
            Tools::log()->warning("⚠ IVA {$ivaDescuento}% sin mapear para descuento. Configura el mapeo de IVA.");
        }
        $linea->pvptotal = round($linea->pvpunitario * $linea->cantidad, 2);
        $linea->recargo = 0;
        $linea->save();
        */

        // === CÓDIGO NUEVO ===
        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaDescuento; // Asignar IVA manualmente
        } else {
            $linea->iva = $ivaDescuento;
            Tools::log()->warning("⚠ IVA {$ivaDescuento}% sin mapear para descuento. Configura el mapeo de IVA.");
        }
        // PUNTO 9: NO forzar recargo = 0, Calculator lo calcula automáticamente
        Calculator::calculateLine($albaran, $linea); // Calculator calcula pvptotal y recargo
        $linea->save();

        Tools::log()->info("✓ DESCUENTO → '{$linea->descripcion}': Con IVA: -{$discountWithTax}€ | Sin IVA: {$linea->pvpunitario}€ | IVA: {$ivaDescuento}%");
    }

    /**
     * Obtiene el nombre del descuento/cupón desde PrestaShop
     */
    private function getDiscountName(\SimpleXMLElement $orderXml): string
    {
        // PrestaShop puede tener información de cupones en associations
        if (isset($orderXml->associations->order_cart_rules->order_cart_rule)) {
            $cartRules = $orderXml->associations->order_cart_rules->order_cart_rule;

            // Si hay un solo cupón
            if (isset($cartRules->name)) {
                $nombreCupon = (string)$cartRules->name;
                Tools::log()->info("Cupón encontrado: {$nombreCupon}");
                return 'Dcto: ' . $nombreCupon;
            }

            // Si hay múltiples cupones
            $names = [];
            foreach ($cartRules as $rule) {
                if (isset($rule->name)) {
                    $names[] = (string)$rule->name;
                }
            }

            if (!empty($names)) {
                $nombresCupones = implode(', ', $names);
                Tools::log()->info("Cupones encontrados: {$nombresCupones}");
                return 'Dcto: ' . $nombresCupones;
            }
        }

        Tools::log()->info("No se encontró nombre de cupón en PrestaShop - usando genérico");
        return 'Descuento / Cupón';
    }

    /**
     * Calcula y asigna los totales del albarán
     */
    private function calculateTotals(AlbaranCliente $albaran): void
    {
        // Obtener todas las líneas del albarán
        $lineas = $albaran->getLines();

        $neto = 0;
        $totalIva = 0;

        foreach ($lineas as $linea) {
            $lineaNeto = $linea->pvpunitario * $linea->cantidad * (1 - $linea->dtopor / 100) * (1 - $linea->dtopor2 / 100);
            $lineaIva = $lineaNeto * ($linea->iva / 100);

            $neto += $lineaNeto;
            $totalIva += $lineaIva;
        }

        // Asignar totales
        $albaran->neto = round($neto, 2);
        $albaran->totaliva = round($totalIva, 2);
        $albaran->total = round($neto + $totalIva, 2);

        // PUNTO 9: NO forzar totalrecargo = 0
        // Esta función ya no se usa, ahora usamos Calculator::calculate() que calcula el recargo automáticamente

        // Guardar con totales calculados
        $albaran->save();

        Tools::log()->debug("Totales calculados - Neto: {$albaran->neto}, IVA: {$albaran->totaliva}, Total: {$albaran->total}");
    }

    /**
     * ECOTASA: Verifica que existe el producto ECOTASA-PRESTASHOP en FacturaScripts
     */
    private function verifyEcotaxProductExists(): void
    {
        // Verificar solo una vez (variable estática)
        static $verified = false;
        if ($verified) {
            return;
        }

        $variante = new Variante();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', 'ECOTASA-PRESTASHOP')];

        if (!$variante->loadFromCode('', $where)) {
            $error = "⚠ CRÍTICO: Producto 'Ecotasa Neumáticos' con referencia ECOTASA-PRESTASHOP no encontrado. Crea el producto manualmente:\n" .
                     "  - Referencia: ECOTASA-PRESTASHOP\n" .
                     "  - Descripción: Ecotasa NFU (Neumáticos Fuera de Uso)\n" .
                     "  - Tipo: Servicio\n" .
                     "  - IVA: 21% (o según corresponda)";
            Tools::log()->critical($error);
            throw new \Exception($error);
        }

        $verified = true;
        Tools::log()->info("✓ Producto ECOTASA-PRESTASHOP verificado");
    }

    /**
     * ECOTASA: Añade línea de ecotasa al albarán
     *
     * @param AlbaranCliente $albaran Albarán al que añadir la ecotasa
     * @param float $ecotaxWithoutTax Importe de ecotasa SIN IVA (por unidad)
     * @param float $ivaEcotasa IVA de la ecotasa (normalmente 21%)
     * @param int $quantity Cantidad de unidades (mismo que el producto)
     * @param string $productName Nombre del producto (para logs)
     */
    private function addEcotaxLine(AlbaranCliente $albaran, float $ecotaxWithoutTax, float $ivaEcotasa, int $quantity, string $productName): void
    {
        // Buscar el producto de ecotasa
        $variante = new Variante();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', 'ECOTASA-PRESTASHOP')];

        if (!$variante->loadFromCode('', $where)) {
            $error = "⚠ CRÍTICO: Producto 'Ecotasa Neumáticos' con referencia ECOTASA-PRESTASHOP no encontrado.";
            Tools::log()->critical($error);
            throw new \Exception($error);
        }

        // Crear línea de ecotasa
        $linea = new LineaAlbaranCliente();
        $linea->idalbaran = $albaran->idalbaran;
        $linea->referencia = $variante->referencia;
        $linea->idproducto = $variante->idproducto;
        $linea->descripcion = 'Ecotasa NFU - ' . $productName;  // Descripción personalizada con nombre del producto
        $linea->cantidad = $quantity;
        $linea->pvpunitario = round($ecotaxWithoutTax, 2);  // Precio SIN IVA por unidad

        // Asignar IVA de la ecotasa
        $codimpuesto = PrestashopTaxMap::getCodImpuesto($ivaEcotasa);

        if ($codimpuesto) {
            $linea->codimpuesto = $codimpuesto;
            $linea->iva = $ivaEcotasa;
        } else {
            $linea->iva = $ivaEcotasa;
            Tools::log()->warning("⚠ IVA {$ivaEcotasa}% sin mapear para ecotasa. Configura el mapeo de IVA.");
        }

        // Calculator calcula pvptotal y recargo (si aplica)
        Calculator::calculateLine($albaran, $linea);
        $linea->save();

        $ecotaxConIva = round($ecotaxWithoutTax * (1 + $ivaEcotasa / 100), 2);
        Tools::log()->info("✓ ECOTASA → Cant: {$quantity} | Sin IVA: {$linea->pvpunitario}€ | IVA: {$ivaEcotasa}% | Con IVA: {$ecotaxConIva}€");
    }

    /**
     * Registra un error en el log de importación
     */
    private function logError(string $message): void
    {
        $this->importLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }

    /**
     * Guarda el log de errores en un archivo
     */
    private function saveLog(): void
    {
        $logDir = \FS_FOLDER . '/MyFiles/Logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/prestashop_import_errors.log';
        $content = '';

        foreach ($this->importLog as $entry) {
            $content .= "[{$entry['timestamp']}] {$entry['message']}\n";
        }

        file_put_contents($logFile, $content, FILE_APPEND);
    }

    /**
     * Obtiene las últimas líneas del log de errores
     */
    public static function getRecentLogs(int $lines = 100): array
    {
        $logFile = \FS_FOLDER . '/MyFiles/Logs/prestashop_import_errors.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $content = file_get_contents($logFile);
        $logLines = explode("\n", trim($content));

        // Obtener las últimas N líneas
        $recentLines = array_slice($logLines, -$lines);

        return array_reverse($recentLines);
    }

    /**
     * Obtiene la fecha del último estado del pedido
     */
    private function getLastOrderStatusDate(\SimpleXMLElement $orderXml, int $orderId): ?string
    {
        try {
            // Intentar 1: order_state_histories (plural) en associations del XML
            if (isset($orderXml->associations->order_state_histories->order_state_history)) {
                $histories = $orderXml->associations->order_state_histories->order_state_history;

                $historyArray = [];
                foreach ($histories as $history) {
                    $historyArray[] = [
                        'date' => (string)$history->date_add,
                        'id_order_state' => (int)$history->id_order_state
                    ];
                }

                if (!empty($historyArray)) {
                    usort($historyArray, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });

                    return $historyArray[0]['date'];
                }
            }

            // Intentar 2: order_history (singular) en associations del XML
            if (isset($orderXml->associations->order_history)) {
                $histories = $orderXml->associations->order_history;

                $historyArray = [];
                foreach ($histories as $history) {
                    $historyArray[] = [
                        'date' => (string)$history->date_add,
                        'id_order_state' => (int)$history->id_order_state
                    ];
                }

                if (!empty($historyArray)) {
                    usort($historyArray, function($a, $b) {
                        return strtotime($b['date']) - strtotime($a['date']);
                    });

                    return $historyArray[0]['date'];
                }
            }

            // Intentar 3: Desde API order_histories
            $history = $this->connection->getOrderHistory($orderId);

            if (!empty($history)) {
                $lastStatus = $history[0];
                $dateAdd = (string)$lastStatus->date_add;

                if (!empty($dateAdd)) {
                    return $dateAdd;
                }
            }

            return null;
        } catch (\Exception $e) {
            Tools::log()->error("Error obteniendo fecha del último estado: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza SOLO el campo import_since_id sin tocar el resto de la configuración
     * Esto previene que se pierdan datos (shop_url, api_key, etc.) por errores de carga
     */
    private function updateImportSinceId(int $newId): void
    {
        try {
            // Usar el método correcto para obtener la base de datos
            $dataBase = new \FacturaScripts\Core\Base\DataBase();
            $sql = "UPDATE prestashop_config SET import_since_id = " . $newId . " WHERE id = " . (int)$this->config->id;

            if ($dataBase->exec($sql)) {
                // Actualizar también en memoria
                $this->config->import_since_id = $newId;
            } else {
                Tools::log()->error("No se pudo actualizar import_since_id en la base de datos");
            }
        } catch (\Exception $e) {
            Tools::log()->error("Error actualizando import_since_id: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el stock REAL de un producto desde PrestaShop
     *
     * @param int $productId
     * @return int
     */
    private function getStockFromPrestashop(int $productId): int
    {
        try {
            $webService = $this->connection->getWebService();

            $params = [
                'filter[id_product]' => $productId,
                'filter[id_product_attribute]' => 0, // 0 = producto sin combinaciones
                'display' => '[quantity]'
            ];

            $xmlString = @$webService->get('stock_availables', null, null, $params);
            if ($xmlString === false) {
                return 0;
            }

            $xml = @simplexml_load_string($xmlString);
            if ($xml === false || !isset($xml->stock_availables->stock_available)) {
                return 0;
            }

            $stock = $xml->stock_availables->stock_available;
            if (is_array($stock) || $stock instanceof \Traversable) {
                foreach ($stock as $s) {
                    return (int)$s->quantity;
                }
            }

            return (int)$stock->quantity;

        } catch (\Exception $e) {
            Tools::log()->warning("Error obteniendo stock para producto {$productId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Actualiza el stock de una variante en el almacén
     * Registra el movimiento en stocks
     *
     * @param int $idvariante ID de la variante
     * @param int $cantidad Cantidad de stock
     * @param string $referencia Referencia (para logs)
     * @return bool
     */
    private function actualizarStockVariante(int $idvariante, int $cantidad, string $referencia): bool
    {
        try {
            // Obtener almacén por defecto
            $codalmacen = $this->config->codalmacen ?? null;

            if (empty($codalmacen)) {
                $db = new \FacturaScripts\Core\Base\DataBase();
                $sqlAlmacen = "SELECT codalmacen FROM almacenes ORDER BY codalmacen LIMIT 1";
                $almacenes = $db->select($sqlAlmacen);

                if (!empty($almacenes)) {
                    $codalmacen = $almacenes[0]['codalmacen'];
                    Tools::log()->warning("No hay almacén configurado, usando: {$codalmacen}");
                } else {
                    Tools::log()->error("No hay almacenes disponibles");
                    return false;
                }
            }

            Tools::log()->info("Registrando stock de {$referencia} en almacén {$codalmacen}: {$cantidad} unidades");

            // Intentar usar el modelo Stock de FacturaScripts (método correcto)
            try {
                $stockModel = new \FacturaScripts\Dinamic\Model\Stock();

                // Buscar stock existente
                $where = [
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('referencia', $referencia),
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('codalmacen', $codalmacen)
                ];

                if ($stockModel->loadFromCode('', $where)) {
                    // Stock existe - actualizar
                    $stockModel->cantidad = $cantidad;
                    $stockModel->disponible = $cantidad;

                    if ($stockModel->save()) {
                        Tools::log()->info("✓ Stock actualizado mediante modelo FacturaScripts: {$referencia} → {$cantidad}");
                        return true;
                    }
                } else {
                    // Stock nuevo - crear
                    $stockModel->referencia = $referencia;
                    $stockModel->codalmacen = $codalmacen;
                    $stockModel->cantidad = $cantidad;
                    $stockModel->disponible = $cantidad;
                    $stockModel->reservada = 0;
                    $stockModel->pterecibir = 0;

                    if ($stockModel->save()) {
                        Tools::log()->info("✓ Stock creado mediante modelo FacturaScripts: {$referencia} → {$cantidad}");
                        return true;
                    }
                }

                Tools::log()->warning("No se pudo guardar stock mediante modelo, intentando SQL directo");

            } catch (\Exception $modelEx) {
                Tools::log()->warning("Modelo Stock no disponible, usando SQL directo: " . $modelEx->getMessage());
            }

            // Fallback: SQL directo si el modelo no funciona
            $db = new \FacturaScripts\Core\Base\DataBase();
            $sqlCheck = "SELECT cantidad FROM stocks WHERE referencia = " . $db->var2str($referencia) .
                       " AND codalmacen = " . $db->var2str($codalmacen);
            $existing = $db->select($sqlCheck);

            if (!empty($existing)) {
                $sqlUpdate = "UPDATE stocks SET cantidad = " . $db->var2str($cantidad) .
                           ", disponible = " . $db->var2str($cantidad) .
                           ", reservada = 0, pterecibir = 0 WHERE referencia = " . $db->var2str($referencia) .
                           " AND codalmacen = " . $db->var2str($codalmacen);

                if ($db->exec($sqlUpdate)) {
                    Tools::log()->info("✓ Stock actualizado via SQL: {$referencia} → {$cantidad}");
                    return true;
                }
            } else {
                $sqlInsert = "INSERT INTO stocks (referencia, codalmacen, cantidad, disponible, reservada, pterecibir) VALUES (" .
                           $db->var2str($referencia) . ", " . $db->var2str($codalmacen) . ", " .
                           $db->var2str($cantidad) . ", " . $db->var2str($cantidad) . ", 0, 0)";

                if ($db->exec($sqlInsert)) {
                    Tools::log()->info("✓ Stock creado via SQL: {$referencia} → {$cantidad}");
                    return true;
                }
            }

            Tools::log()->error("No se pudo registrar stock");
            return false;

        } catch (\Exception $e) {
            Tools::log()->error("Error actualizando stock: " . $e->getMessage());
            return false;
        }
    }
}
