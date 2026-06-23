<?php
require_once __DIR__ . '/portal/referencia_helper.php';
$db = getDb();
if ($db) {
    $stmt = $db->query("SELECT * FROM pagos_registrados ORDER BY id DESC LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo "No DB connection.";
}
