<?php
/**
 * Specialized Test: nuevo.php -> guarda.php
 * Verifies all fields and debtor logic.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TESTING: nuevo.php -> guarda.php ===\n";

$test_id_base = "TREG" . rand(100, 999);
$test_cedula = "V" . rand(1000000, 9999999);

// Mock data simulating form submission from nuevo.php
$mock_post = [
    'cedula' => $test_cedula,
    'nombre_completo' => 'TEST USER NUEVO',
    'telefono' => '04121234567',
    'correo' => 'nuevo@test.com',
    'id_municipio' => 1,
    'id_parroquia' => 1,
    'id_plan' => 1,
    'monto_plan' => 30.00,
    'vendedor_texto' => 'VENDEDOR TEST',
    'direccion' => 'DIRECCION TEST FOR NUEVO',
    'fecha_instalacion' => date('Y-m-d'),
    'estado_contrato' => 'ACTIVO',
    'ident_caja_nap' => 'NAP-NUEVO',
    'puerto_nap' => 'P-NUEVO',
    'num_presinto_odn' => 'ODN-NUEVO',
    'id_olt' => 1,
    'id_pon' => 1,
    'tipo_instalacion' => 'FTTH',
    'monto_instalacion' => 50.00,
    'gastos_adicionales' => 10.00,
    'monto_pagar' => 90.00,    // Total to pay
    'monto_pagado' => 45.00,   // Paid half -> Should trigger DEUDOR
    'instaladores' => ['INSTALADOR A', 'INSTALADOR B'],
    'medio_pago' => 'Efectivo',
    'moneda_pago' => 'USD',
    'tipo_conexion' => 'FTTH',
    'mac_onu' => 'AA:BB:CC:' . rand(10, 99),
    'ip_onu' => '10.0.0.' . rand(2, 254),
    'numero_onu' => 'ONU-' . rand(100, 999),
    'nap_tx_power' => '-22',
    'onu_rx_power' => '-24',
    'distancia_drop' => '150',
    'evidencia_fibra' => 'SI'
];

// 1. Create mock environment file
$mock_file = __DIR__ . '/tmp_mock_post.php';
$mock_content = "<?php \n\$_POST = " . var_export($mock_post, true) . ";\n" . 
               "\$_SERVER['REQUEST_METHOD'] = 'POST';\n" .
               "chdir(__DIR__ . '/../paginas/principal');\n" .
               "// Mock ob_ functions to prevent output errors in CLI\n" .
               "if (!function_exists('ob_clean')) { function ob_clean() {} }\n" .
               "if (!function_exists('ob_get_length')) { function ob_get_length() { return 0; } }\n";
file_put_contents($mock_file, $mock_content);

// 2. Execute guarda.php using the mock
// We use -d auto_prepend_file to inject the mock POST data
$cmd = "php -d auto_prepend_file=" . escapeshellarg($mock_file) . " " . escapeshellarg(__DIR__ . '/../paginas/principal/guarda.php');
echo "Executing: $cmd\n";
$output = shell_exec($cmd);

echo "Response from guarda.php:\n$output\n";

// 3. Verify in Database
$res = json_decode($output, true);
if (!$res || $res['status'] === 'error' || !isset($res['id'])) {
    die("❌ FAILED: guarda.php did not return a success JSON with an ID. Response: $output\n");
}

$id_contrato = $res['id'];

// Check Contrato
$check_c = $conn->query("SELECT * FROM contratos WHERE id = $id_contrato");
$contract = $check_c->fetch_assoc();

if ($contract && $contract['cedula'] === $test_cedula) {
    echo "✅ SUCCESS: Contract recorded with ID $id_contrato\n";
    if ($contract['instalador'] === 'INSTALADOR A, INSTALADOR B') echo "✅ Installer saved correctly.\n";
    else echo "❌ Installer error: " . $contract['instalador'] . "\n";
} else {
    die("❌ FAILED: Contract record not found or data mismatch.\n");
}

// Check Deudor
$check_d = $conn->query("SELECT * FROM clientes_deudores WHERE id_contrato = $id_contrato");
$deudor = $check_d->fetch_assoc();

if ($deudor) {
    echo "✅ SUCCESS: Debtor record created.\n";
    $expected_saldo = 90.00 - 45.00;
    if (abs($deudor['saldo_pendiente'] - $expected_saldo) < 0.01) {
        echo "✅ SUCCESS: Saldo pendiente correct: " . $deudor['saldo_pendiente'] . "\n";
    } else {
        echo "❌ FAILED: Saldo pendiente mismatch: " . $deudor['saldo_pendiente'] . " (Expected $expected_saldo)\n";
    }
} else {
    echo "❌ FAILED: Debtor record NOT created for pending balance.\n";
}

// 4. Cleanup
$conn->query("DELETE FROM cobros_manuales_historial WHERE id_contrato = $id_contrato");
$conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato");
$conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $id_contrato");
$conn->query("DELETE FROM contratos WHERE id = $id_contrato");
@unlink($mock_file);

echo "=== TEST COMPLETED ===\n";
$conn->close();
?>
