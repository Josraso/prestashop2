<?php
/**
 * Script para corregir codimpuesto="ECOTASA" a "IVA21" en facturas ya generadas
 *
 * PROBLEMA: Las facturas generadas antes del fix tienen líneas de IVA con
 * codimpuesto="ECOTASA" que no existe, causando error VeriFactu
 *
 * SOLUCIÓN: Este script actualiza todas las líneas de IVA incorrectas a "IVA21"
 *
 * USO: php fix_ecotax_codimpuesto.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\ToolBox;

echo "\n";
echo "============================================================\n";
echo "  CORRECCIÓN CODIMPUESTO ECOTASA → IVA21\n";
echo "============================================================\n";
echo "\n";

try {
    // Obtener instancia de base de datos
    $db = new DataBase();

    if (!$db->connected()) {
        echo "❌ ERROR: No se pudo conectar a la base de datos\n";
        exit(1);
    }

    echo "✓ Conectado a la base de datos\n\n";

    // 1. Verificar cuántas líneas tienen el problema
    echo "📊 Buscando líneas con codimpuesto='ECOTASA'...\n";

    $sqlCount = "SELECT COUNT(*) as total FROM lineasivafactcli WHERE codimpuesto = 'ECOTASA'";
    $result = $db->select($sqlCount);
    $totalAfectadas = $result[0]['total'] ?? 0;

    if ($totalAfectadas == 0) {
        echo "✓ No hay líneas con codimpuesto='ECOTASA'. Todo está correcto.\n";
        exit(0);
    }

    echo "⚠ Encontradas {$totalAfectadas} líneas con codimpuesto='ECOTASA'\n\n";

    // 2. Mostrar algunas facturas afectadas
    echo "📋 Facturas afectadas (primeras 10):\n";
    $sqlFacturas = "SELECT DISTINCT idfactura FROM lineasivafactcli WHERE codimpuesto = 'ECOTASA' LIMIT 10";
    $facturas = $db->select($sqlFacturas);

    foreach ($facturas as $factura) {
        echo "   - Factura ID: {$factura['idfactura']}\n";
    }
    echo "\n";

    // 3. Confirmar antes de proceder
    echo "¿Deseas corregir estas líneas? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $respuesta = trim(strtolower($line));
    fclose($handle);

    if ($respuesta !== 'y' && $respuesta !== 's' && $respuesta !== 'si' && $respuesta !== 'sí') {
        echo "\n❌ Operación cancelada por el usuario\n";
        exit(0);
    }

    echo "\n🔧 Corrigiendo líneas...\n";

    // 4. Actualizar todas las líneas con codimpuesto='ECOTASA' a 'IVA21'
    $sqlUpdate = "UPDATE lineasivafactcli SET codimpuesto = 'IVA21' WHERE codimpuesto = 'ECOTASA'";

    if ($db->exec($sqlUpdate)) {
        echo "✓ Se actualizaron {$totalAfectadas} líneas correctamente\n";

        // 5. Verificar que no queden líneas con ECOTASA
        $resultVerify = $db->select($sqlCount);
        $quedanAfectadas = $resultVerify[0]['total'] ?? 0;

        if ($quedanAfectadas == 0) {
            echo "✓ Verificación OK: No quedan líneas con codimpuesto='ECOTASA'\n";
        } else {
            echo "⚠ ADVERTENCIA: Todavía quedan {$quedanAfectadas} líneas sin corregir\n";
        }

        echo "\n";
        echo "============================================================\n";
        echo "  ✓ CORRECCIÓN COMPLETADA\n";
        echo "============================================================\n";
        echo "\n";
        echo "SIGUIENTE PASO:\n";
        echo "Las facturas ya están corregidas en la base de datos.\n";
        echo "Si ya las enviaste a VeriFactu con error, puede que necesites\n";
        echo "reenviarlas o anularlas y volverlas a crear.\n";
        echo "\n";

    } else {
        echo "❌ ERROR: No se pudieron actualizar las líneas\n";
        echo "Error: " . $db->getErrorMessage() . "\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
