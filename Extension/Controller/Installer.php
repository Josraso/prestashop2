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

    public function update()
    {
        // Este método se ejecuta cada vez que se activa el plugin
        // Es necesario para añadir columnas nuevas en actualizaciones

        \FacturaScripts\Core\Tools::log()->info("Verificando actualizaciones del plugin PrestaShop...");

        // CRÍTICO: Añadir columnas de BD para ecotax si no existen (para actualizaciones desde versiones antiguas)
        $this->addDatabaseColumnsIfNotExist();

        // Verificar que existen los productos necesarios
        $this->createShippingProduct();
        $this->createGiftWrappingProduct();
        $this->createEcotaxProduct();

        \FacturaScripts\Core\Tools::log()->info("✓ Verificación de actualizaciones completada");
    }

    public function uninstall()
    {
        // No eliminar productos ni tablas al desinstalar por si hay datos que los usan
        \FacturaScripts\Core\Tools::log()->info("Plugin PrestaShop desinstalado (datos conservados)");
    }

    /**
     * Añade las columnas de BD para ecotax si no existen
     * Este método es necesario para actualizaciones desde versiones antiguas
     */
    private function addDatabaseColumnsIfNotExist(): void
    {
        $db = new \FacturaScripts\Core\Base\DataBase();

        // Verificar si la tabla existe primero
        $tableExists = $db->select("SELECT table_name FROM information_schema.tables
                                    WHERE table_schema = DATABASE()
                                    AND table_name = 'prestashop_config'");

        if (empty($tableExists)) {
            \FacturaScripts\Core\Tools::log()->info("Tabla prestashop_config no existe aún, se creará desde XML");
            return;
        }

        // La tabla existe, verificar si las columnas existen
        $columns = $db->select("SELECT column_name FROM information_schema.columns
                                WHERE table_schema = DATABASE()
                                AND table_name = 'prestashop_config'
                                AND column_name IN ('db_host', 'db_name', 'db_user', 'db_password', 'db_prefix', 'use_db_for_ecotax')");

        if (count($columns) >= 6) {
            \FacturaScripts\Core\Tools::log()->debug("✓ Columnas de BD para ecotax ya existen");
            return;
        }

        \FacturaScripts\Core\Tools::log()->info("Añadiendo columnas de BD para ecotax...");

        // Añadir columnas SIN default (evita problemas de sintaxis SQL entre motores)
        $alterQueries = [
            "ALTER TABLE prestashop_config ADD COLUMN IF NOT EXISTS db_host VARCHAR(255)",
            "ALTER TABLE prestashop_config ADD COLUMN IF NOT EXISTS db_name VARCHAR(100)",
            "ALTER TABLE prestashop_config ADD COLUMN IF NOT EXISTS db_user VARCHAR(100)",
            "ALTER TABLE prestashop_config ADD COLUMN IF NOT EXISTS db_password VARCHAR(255)",
            "ALTER TABLE prestashop_config ADD COLUMN IF NOT EXISTS db_prefix VARCHAR(20)",
            "ALTER TABLE prestashop_config ADD COLUMN IF NOT EXISTS use_db_for_ecotax BOOLEAN"
        ];

        $errores = 0;
        foreach ($alterQueries as $query) {
            try {
                $db->exec($query);
            } catch (\Exception $e) {
                \FacturaScripts\Core\Tools::log()->error("Error ejecutando: " . $query . " - " . $e->getMessage());
                $errores++;
            }
        }

        // Establecer valores por defecto para registros existentes
        if ($errores === 0) {
            try {
                $db->exec("UPDATE prestashop_config SET db_host = 'localhost' WHERE db_host IS NULL OR db_host = ''");
                $db->exec("UPDATE prestashop_config SET db_prefix = 'ps_' WHERE db_prefix IS NULL OR db_prefix = ''");
                $db->exec("UPDATE prestashop_config SET use_db_for_ecotax = false WHERE use_db_for_ecotax IS NULL");

                \FacturaScripts\Core\Tools::log()->info("✓ Columnas de BD para ecotax añadidas correctamente");
            } catch (\Exception $e) {
                \FacturaScripts\Core\Tools::log()->warning("⚠ Columnas creadas pero error al establecer valores por defecto: " . $e->getMessage());
            }
        } else {
            \FacturaScripts\Core\Tools::log()->warning("⚠ Hubo {$errores} errores al añadir columnas de BD");
        }
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
