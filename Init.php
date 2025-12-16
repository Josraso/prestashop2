<?php

namespace FacturaScripts\Plugins\Prestashop;

use FacturaScripts\Core\Template\InitClass;

require_once __DIR__.'/vendor/autoload.php';

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditAlbaranCliente());
    }

    public function update(): void
    {
        // Ejecutar el instalador para crear productos necesarios
        // Se ejecuta en cada actualización para asegurar que los productos existen
        $installer = new Extension\Controller\Installer();
        $installer->install();

        // Forzar creación de tabla temporal de productos
        $this->createProductsTempTable();
    }

    /**
     * Crea la tabla temporal de productos si no existe
     */
    private function createProductsTempTable(): void
    {
        $db = new \FacturaScripts\Core\Base\DataBase();

        // Verificar si la tabla ya existe
        $tableName = 'prestashop_products_temp';
        if ($db->tableExists($tableName)) {
            return;
        }

        // Crear la tabla manualmente
        $sql = "CREATE TABLE IF NOT EXISTS " . $tableName . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(128) NOT NULL,
            products_data TEXT,
            fecha_descarga TIMESTAMP,
            INDEX idx_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->exec($sql);
    }

    public function uninstall(): void
    {
        // Ejecutar el desinstalador
        $installer = new Extension\Controller\Installer();
        $installer->uninstall();
    }
}
