<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../conexion.php';

if (!$conn) {
    die("Error de conexión a la base de datos.");
}

$sql_planes = "SELECT * FROM planes";
$result_planes = $conn->query($sql_planes);

echo "<h3>Resultados para la tabla 'planes':</h3>";
if ($result_planes === false) {
    echo "Error en la consulta de planes: " . $conn->error;
} else {
    echo "Número de registros en la tabla 'planes': " . $result_planes->num_rows;
}

$sql_vendedores = "SELECT * FROM vendedores";
$result_vendedores = $conn->query($sql_vendedores);

echo "<h3>Resultados para la tabla 'vendedores':</h3>";
if ($result_vendedores === false) {
    echo "Error en la consulta de vendedores: " . $conn->error;
} else {
    echo "Número de registros en la tabla 'vendedores': " . $result_vendedores->num_rows;
}

$conn->close();
?>