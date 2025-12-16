<?php
/**
 * Script de diagnóstico para verificar estado del plugin PrestaShop
 */

echo "=== DIAGNÓSTICO PLUGIN PRESTASHOP ===\n\n";

// 1. Verificar versión
$iniFile = __DIR__ . '/facturascripts.ini';
if (file_exists($iniFile)) {
    $ini = parse_ini_file($iniFile);
    echo "✓ Versión del plugin: " . ($ini['version'] ?? 'desconocida') . "\n";
} else {
    echo "✗ No se encuentra facturascripts.ini\n";
}

// 2. Verificar timestamp de archivos críticos
$files = [
    'Lib/Actions/ProductsDownload.php',
    'Controller/ProductsPrestashop.php',
    'View/ProductsPrestashop.html.twig'
];

echo "\n--- Última modificación de archivos ---\n";
foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✓ {$file}: " . date('Y-m-d H:i:s', filemtime($fullPath)) . "\n";
    } else {
        echo "✗ {$file}: NO EXISTE\n";
    }
}

// 3. Verificar caché OPcache
echo "\n--- Estado de OPcache ---\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "✓ OPcache activo\n";
        echo "  - Scripts en caché: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "  - Hits: " . $status['opcache_statistics']['hits'] . "\n";
        echo "  - Misses: " . $status['opcache_statistics']['misses'] . "\n";
    } else {
        echo "✗ OPcache no disponible\n";
    }
} else {
    echo "- OPcache no instalado\n";
}

// 4. Limpiar caché si es posible
echo "\n--- Limpieza de caché ---\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache limpiada\n";
} else {
    echo "- No se puede limpiar OPcache desde PHP\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
echo "\nSi la versión no es 5.2, desactiva y reactiva el plugin en FacturaScripts.\n";
echo "Si los archivos tienen fecha antigua, puede ser problema de caché.\n";
?>
