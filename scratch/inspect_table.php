<?php
require 'paginas/conexion.php';
$res = $conn->query("DESCRIBE contratos");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
