<?php
// Quick DB check
require_once __DIR__ . '\..\paginas\conexion.php';

echo "DB connected: OK\n\n";

$r = $conn->query("SELECT id, estado FROM contratos WHERE cedula = 'V99999999'");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "Test user: ID={$row['id']} Estado={$row['estado']}\n";
    }
} else {
    echo "No test user found\n";
}

echo "\n";
$r2 = $conn->query("SELECT l.wisp_account_id, l.status FROM wisp_hub_links l INNER JOIN contratos c ON l.contract_id = c.id WHERE c.cedula = 'V99999999'");
if ($r2 && $r2->num_rows > 0) {
    while ($row = $r2->fetch_assoc()) {
        echo "WispHub link: ID={$row['wisp_account_id']} Status={$row['status']}\n";
    }
} else {
    echo "No wisp_hub_links found\n";
}

echo "\nUpdating wisp_account_id to 902...\n";
$conn->query("UPDATE wisp_hub_links SET wisp_account_id = '902', status = 'SUSPENDED' WHERE contract_id = 10015");
echo "Updated. Rows affected: " . $conn->affected_rows . "\n";
