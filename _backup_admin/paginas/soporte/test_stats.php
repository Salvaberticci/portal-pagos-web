<?php
// test_stats.php - Diagnóstico del endpoint de estadísticas
// BORRAR ESTE ARCHIVO DEL SERVIDOR DESPUÉS DE LA PRUEBA

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== STEP 1: PHP funcionando ===\n";

// Verificar si el archivo de conexión existe
$conn_path = '../conexion.php';
echo "Ruta de conexion.php: " . realpath($conn_path) . "\n";
echo "Existe conexion.php: " . (file_exists($conn_path) ? "SI" : "NO") . "\n\n";

echo "=== STEP 2: Conectando a BD ===\n";
require_once $conn_path;
echo "Conexión exitosa: " . (isset($conn) ? "SI" : "NO") . "\n";
if (isset($conn)) {
    echo "Error mysqli: " . $conn->error . "\n";
}
echo "\n";

echo "=== STEP 3: Probando query ===\n";
$sql = "SELECT COUNT(*) as total FROM soportes";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total registros en soportes: " . $row['total'] . "\n";
} else {
    echo "Error en query: " . $conn->error . "\n";
}

echo "\n=== STEP 4: Probando DATE_FORMAT ===\n";
$sql2 = "SELECT DATE_FORMAT(NOW(), '%Y-%m') as mes, DATE_FORMAT(NOW(), '%b %Y') as mes_nombre";
$result2 = $conn->query($sql2);
if ($result2) {
    $row2 = $result2->fetch_assoc();
    echo "DATE_FORMAT funciona: mes=" . $row2['mes'] . " nombre=" . $row2['mes_nombre'] . "\n";
} else {
    echo "Error en DATE_FORMAT: " . $conn->error . "\n";
}

echo "\n=== DIAGNÓSTICO COMPLETO ===\n";
?>