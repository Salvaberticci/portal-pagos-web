<?php
require dirname(__DIR__) . '/paginas/conexion.php';
$res = $conn->query("SELECT * FROM contratos WHERE cedula = 'V99999999'");
if ($row = $res->fetch_assoc()) {
    echo "CONTRATO:\n" . json_encode($row, JSON_PRETTY_PRINT) . "\n\n";
    $id = $row['id'];
    $res2 = $conn->query("SELECT * FROM cuentas_por_cobrar WHERE id_contrato = $id");
    echo "CUENTAS POR COBRAR:\n";
    while ($row2 = $res2->fetch_assoc()) {
        echo json_encode($row2, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "No encontrado\n";
}
