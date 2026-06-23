<?php
require_once __DIR__ . '/portal/referencia_helper.php';

$ref = '139627';
echo "<h1>Diagnóstico de Referencia Duplicada</h1>";
echo "Buscando referencia: <strong>'$ref'</strong><br><br>";

$pdo = getDb();
if (!$pdo) {
    echo "<b style='color:red;'>ERROR: No se pudo conectar a la base de datos (getDb devolvió null).</b><br>";
    exit;
}
echo "<b style='color:green;'>Conexión a la base de datos: OK</b><br>";

// 1. Búsqueda exacta
$stmt = $pdo->prepare("SELECT * FROM pagos_registrados WHERE referencia = ?");
$stmt->execute([$ref]);
$exactRows = $stmt->fetchAll();

echo "<h3>1. Búsqueda exacta (referencia = '$ref'):</h3>";
if (empty($exactRows)) {
    echo "<span style='color:red;'>No se encontró coincidencia exacta.</span><br>";
} else {
    echo "<span style='color:green;'>Se encontraron " . count($exactRows) . " coincidencias exactas.</span><br>";
    echo "<pre>" . print_r($exactRows, true) . "</pre>";
}

// 2. Búsqueda LIKE
$stmt2 = $pdo->prepare("SELECT id, referencia, LENGTH(referencia) as len FROM pagos_registrados WHERE referencia LIKE ?");
$stmt2->execute(["%$ref%"]);
$likeRows = $stmt2->fetchAll();

echo "<h3>2. Búsqueda con LIKE (%$ref%):</h3>";
if (empty($likeRows)) {
    echo "Tampoco se encontró con LIKE.<br>";
} else {
    echo "Se encontraron " . count($likeRows) . " coincidencias parciales. Esto significa que la referencia ESTÁ en la base de datos pero tiene caracteres invisibles, espacios o ceros iniciales.<br>";
    echo "Datos encontrados (con longitud real):<br>";
    echo "<table border='1'><tr><th>ID</th><th>Referencia (Raw)</th><th>Longitud</th></tr>";
    foreach ($likeRows as $r) {
        echo "<tr><td>{$r['id']}</td><td>'{$r['referencia']}'</td><td>{$r['len']}</td></tr>";
    }
    echo "</table>";
}
