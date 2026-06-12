<?php
require 'paginas/conexion.php';
$table = 'wisp_hub_links';
echo "--- $table ---\n";
$res = $conn->query("DESCRIBE $table");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Error describing table: " . $conn->error . "\n";
}

$table2 = 'wisp_hub_logs';
echo "\n--- $table2 ---\n";
$res2 = $conn->query("DESCRIBE $table2");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Error describing table: " . $conn->error . "\n";
}
?>
