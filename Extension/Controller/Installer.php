<?php

namespace FacturaScripts\Plugins\Prestashop\Extension\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Variante;

/**
 * Hook de instalación del plugin
 */
class Installer
{
    public function install()
    {
        // Las tablas se crean automáticamente desde los archivos XML en Table/
        // NO intentar hacer ALTER TABLE aquí porque las tablas aún no existen

        \FacturaScripts\Core\Tools::log()->info("Iniciando instalación del plugin PrestaShop...");

        // Crear productos necesarios para importación
        $this->createShippingProduct();
        $this->createGiftWrappingProduct();
        $this->createEcotaxProduct();

        \FacturaScripts\Core\Tools::log()->info("✓ Instalación del plugin PrestaShop completada");
    }

    public function uninstall()
    {
        // No eliminar productos ni tablas al desinstalar por si hay datos que los usan
        \FacturaScripts\Core\Tools::log()->info("Plugin PrestaShop desinstalado (datos conservados)");
    }

    private function createShippingProduct(): void
    {
        // Buscar si ya existe el producto
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', 'ENVIO-PRESTASHOP')];

        if ($variante->loadFromCode('', $where)) {
            \FacturaScripts\Core\Tools::log()->info("✓ Producto 'Gastos de envío' ya existe (ENVIO-PRESTASHOP)");
            return;
        }

        // Crear el producto
        $producto = new Producto();
        $producto->descripcion = 'Gastos de envío';
        $producto->precio = 0; // El precio se establece dinámicamente
        $producto->nostock = true;
        $producto->ventasinstock = true;
        $producto->bloqueado = false;
        $producto->codimpuesto = 'IVA21'; // IVA del transporte (ajustable)

        if ($producto->save()) {
            // Actualizar la variante con la referencia
            $variante = $producto->getVariants()[0] ?? null;
            if ($variante) {
                $variante->referencia = 'ENVIO-PRESTASHOP';
                if ($variante->save()) {
                    \FacturaScripts\Core\Tools::log()->info("✓ Producto 'Gastos de envío' creado (ENVIO-PRESTASHOP)");
                }
            }
        } else {
            \FacturaScripts\Core\Tools::log()->error("✗ Error al crear producto 'Gastos de envío'");
        }
    }

    private function createGiftWrappingProduct(): void
    {
        // Buscar si ya existe el producto
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', 'REGALO-PRESTASHOP')];

        if ($variante->loadFromCode('', $where)) {
            \FacturaScripts\Core\Tools::log()->info("✓ Producto 'Empaquetado para regalo' ya existe (REGALO-PRESTASHOP)");
            return;
        }

        // Crear el producto
        $producto = new Producto();
        $producto->descripcion = 'Empaquetado para regalo';
        $producto->precio = 0; // El precio se establece dinámicamente
        $producto->nostock = true;
        $producto->ventasinstock = true;
        $producto->bloqueado = false;
        $producto->codimpuesto = 'IVA21'; // IVA del empaquetado (ajustable)

        if ($producto->save()) {
            // Actualizar la variante con la referencia
            $variante = $producto->getVariants()[0] ?? null;
            if ($variante) {
                $variante->referencia = 'REGALO-PRESTASHOP';
                if ($variante->save()) {
                    \FacturaScripts\Core\Tools::log()->info("✓ Producto 'Empaquetado para regalo' creado (REGALO-PRESTASHOP)");
                }
            }
        } else {
            \FacturaScripts\Core\Tools::log()->error("✗ Error al crear producto 'Empaquetado para regalo'");
        }
    }

    private function createEcotaxProduct(): void
    {
        // Buscar si ya existe el producto
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', 'ECOTAX')];

        if ($variante->loadFromCode('', $where)) {
            \FacturaScripts\Core\Tools::log()->info("✓ Producto 'Ecotasa NFU' ya existe (ECOTAX)");
            return;
        }

        // Crear el producto
        $producto = new Producto();
        $producto->descripcion = 'Ecotasa NFU (Neumáticos Fuera de Uso)';
        $producto->precio = 0; // El precio se establece dinámicamente desde PrestaShop
        $producto->nostock = true;
        $producto->ventasinstock = true;
        $producto->bloqueado = false;
        $producto->codimpuesto = 'IVA21'; // IVA de la ecotasa (21% por defecto)

        if ($producto->save()) {
            // Actualizar la variante con la referencia
            $variante = $producto->getVariants()[0] ?? null;
            if ($variante) {
                $variante->referencia = 'ECOTAX';
                if ($variante->save()) {
                    \FacturaScripts\Core\Tools::log()->info("✓ Producto 'Ecotasa NFU' creado (ECOTAX)");
                }
            }
        } else {
            \FacturaScripts\Core\Tools::log()->error("✗ Error al crear producto 'Ecotasa NFU'");
        }
    }
}
