<?php
/**
 * Script de Diagnóstico del Sistema
 * Ayuda a identificar problemas con rutas de vendor, autoload y bases de datos.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Reporte de Diagnóstico del Servidor</h1>";
echo "<hr>";

// 1. Información del Entorno
echo "<h2>1. Información del Sistema</h2>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li><strong>Directorio Actual (__DIR__):</strong> " . __DIR__ . "</li>";
echo "<li><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</li>";
echo "</ul>";

// 2. Verificación de Rutas de Autoload
echo "<h2>2. Verificación de Autoload (Vendor)</h2>";
$possible_paths = [
    '../../vendor/autoload.php',
    '../vendor/autoload.php',
    './vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php'
];

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background: #eee;'><th>Ruta Relativa / Absoluta</th><th>Estado</th><th>Permisos</th></tr>";

foreach ($possible_paths as $path) {
    echo "<tr>";
    echo "<td><code>$path</code></td>";
    if (file_exists($path)) {
        echo "<td style='color: green;'>✅ Existe</td>";
        echo "<td>" . substr(sprintf('%o', fileperms($path)), -4) . "</td>";
    } else {
        echo "<td style='color: red;'>❌ No existe</td>";
        echo "<td>-</td>";
    }
    echo "</tr>";
}
echo "</table>";

// 3. Verificación de Base de Datos
echo "<h2>3. Conexión a Base de Datos</h2>";
$conexion_file = '../conexion.php';
if (file_exists($conexion_file)) {
    include $conexion_file;
    if (isset($conn) && !$conn->connect_error) {
        echo "<p style='color: green;'>✅ Conexión exitosa a la base de datos.</p>";
    } else {
        echo "<p style='color: red;'>❌ Error de conexión: " . ($conn->connect_error ?? 'Variable $conn no definida') . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Archivo conexion.php no encontrado en la ruta esperada.</p>";
}

// 4. Instrucciones
echo "<h2>4. Recomendaciones</h2>";
echo "<p>Si todas las rutas de <strong>Autoload</strong> aparecen como 'No existe':</p>";
echo "<ul>";
echo "<li>Asegúrate de haber subido la carpeta <code>vendor/</code> completa al servidor.</li>";
echo "<li>Si usas Git, recuerda que <code>vendor/</code> suele estar en el .gitignore y debe subirse manualmente o vía <code>composer install</code>.</li>";
echo "<li>Verifica que la estructura de carpetas en el servidor coincida con la local.</li>";
echo "</ul>";
?>
