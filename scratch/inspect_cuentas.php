<?php
require 'paginas/conexion.php';
$res = $conn->query("DESCRIBE cuentas_por_cobrar");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
