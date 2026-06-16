<?php
/**
 * fix_bom_all.php - Elimina BOM (EF BB BF) de todos los archivos PHP
 * del proyecto que lo tengan.
 */

$base = dirname(__DIR__);
$dirs = ['src', 'config', 'paginas', 'portal', 'tests'];

$fixed = 0;
$ok    = 0;
$BOM   = "\xEF\xBB\xBF";

foreach ($dirs as $dir) {
    $path = $base . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($path)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;

        $content = file_get_contents($file->getPathname());
        if (substr($content, 0, 3) === $BOM) {
            file_put_contents($file->getPathname(), substr($content, 3));
            echo "[FIXED] " . $file->getPathname() . "\n";
            $fixed++;
        } else {
            echo "[OK]    " . $file->getPathname() . "\n";
            $ok++;
        }
    }
}

echo "\n--- Resultado ---\n";
echo "Corregidos: $fixed\n";
echo "Ya estaban bien: $ok\n";
