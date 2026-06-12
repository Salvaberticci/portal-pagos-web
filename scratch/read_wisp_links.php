<?php
require 'paginas/conexion.php';
$res = $conn->query("SELECT * FROM wisp_hub_links");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No rows in wisp_hub_links or query failed: " . $conn->error . "\n";
}
?>
