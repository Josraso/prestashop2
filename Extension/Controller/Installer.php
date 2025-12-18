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
        // Crear producto "Gastos de envío" si no existe
        $this->createShippingProduct();

        // Crear producto "Empaquetado para regalo" si no existe
        $this->createGiftWrappingProduct();

        // Crear producto "Ecotasa Neumáticos" si no existe
        $this->createEcotaxProduct();
    }

    public function uninstall()
    {
        // No eliminar el producto al desinstalar por si hay albaranes que lo usan
    }

    private function createShippingProduct(): void
    {
        // Buscar si ya existe el producto
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', 'ENVIO-PRESTASHOP')];

        if ($variante->loadFromCode('', $where)) {
            // Ya existe, no hacer nada
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
                $variante->save();
            }
        }
    }

    private function createGiftWrappingProduct(): void
    {
        // Buscar si ya existe el producto
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', 'REGALO-PRESTASHOP')];

        if ($variante->loadFromCode('', $where)) {
            // Ya existe, no hacer nada
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
                $variante->save();
            }
        }
    }

    private function createEcotaxProduct(): void
    {
        // Buscar si ya existe el producto
        $variante = new Variante();
        $where = [new DataBaseWhere('referencia', 'ECOTAX')];

        if ($variante->loadFromCode('', $where)) {
            // Ya existe, no hacer nada
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
                $variante->save();
            }
        }
    }
}
