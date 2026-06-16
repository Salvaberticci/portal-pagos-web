<?php
/**
 * Specialized Test: registro_contrato_instalador.php -> guardar_contrato_instalador.php
 * Verifies all fields and debtor logic.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TESTING: registro_contrato_instalador.php -> guardar_contrato_instalador.php ===\n";

$test_cedula = "V" . rand(10000000, 99999999);

// Mock data simulating form submission from registro_contrato_instalador.php
$mock_post = [
    'cedula' => $test_cedula,
    'nombre_completo' => 'TEST USER INSTALADOR',
    'telefono' => '04240000000',
    'correo' => 'instalador@test.com',
    'id_municipio' => 'MUN-1',
    'id_parroquia' => 'PAR-1',
    'id_plan' => 1,
    'monto_plan' => 35.00,
    'vendedor_texto' => 'VENDEDOR B',
    'direccion' => 'CALLE TECNICA 123',
    'fecha_instalacion' => date('Y-m-d'),
    'estado_contrato' => 'ACTIVO',
    'id_olt' => 1,
    'id_pon' => 1,
    'tipo_conexion' => 'RADIO',
    'tipo_instalacion' => 'RADIO',
    'monto_instalacion' => 60.00,
    'gastos_adicionales' => 5.00,
    'monto_pagar' => 100.00,
    'monto_pagado' => 70.00,   // Paid partial -> Should trigger DEUDOR
    'medio_pago' => 'Pago Móvil',
    'moneda_pago' => 'USD',
    'instaladores' => ['TECNICO 1'],
    'punto_acceso' => 'AP-TEST-X',
    'valor_conexion_dbm' => '-55',
    'ident_caja_nap_tecnico' => 'NAP-TECH',
    'puerto_nap_tecnico' => 'PT-9',
    'num_presinto_odn' => 'ODN-TECH'
];

// 1. Create mock environment file
$mock_file = __DIR__ . '/tmp_mock_post_inst.php';
$mock_content = "<?php \n\$_POST = " . var_export($mock_post, true) . ";\n" . 
               "\$_SERVER['REQUEST_METHOD'] = 'POST';\n" .
               "chdir(__DIR__ . '/../paginas/soporte');\n" .
               "// Mock ob_ functions to prevent output errors in CLI\n" .
               "if (!function_exists('ob_clean')) { function ob_clean() {} }\n" .
               "if (!function_exists('ob_get_length')) { function ob_get_length() { return 0; } }\n";
file_put_contents($mock_file, $mock_content);

// 2. Execute guardar_contrato_instalador.php using the mock
$cmd = "php -d auto_prepend_file=" . escapeshellarg($mock_file) . " " . escapeshellarg(__DIR__ . '/../paginas/soporte/guardar_contrato_instalador.php');
echo "Executing: $cmd\n";
$output = shell_exec($cmd);

echo "Response from guardar_contrato_instalador.php:\n$output\n";

// 3. Verify in Database
$res = json_decode($output, true);
if (!$res || $res['status'] === 'error' || !isset($res['id'])) {
    die("❌ FAILED: guardar_contrato_instalador.php did not return a success JSON with an ID. Response: $output\n");
}

$id_contrato = $res['id'];

// Check Contrato
$check_c = $conn->query("SELECT * FROM contratos WHERE id = $id_contrato");
$contract = $check_c->fetch_assoc();

if ($contract && $contract['cedula'] === $test_cedula) {
    echo "✅ SUCCESS: Contract recorded with ID $id_contrato\n";
    if ($contract['instalador'] === 'TECNICO 1') echo "✅ Installer saved correctly.\n";
    else echo "❌ Installer error: " . $contract['instalador'] . "\n";
    if ($contract['punto_acceso'] === 'AP-TEST-X') echo "✅ Punto Acceso saved correctly.\n";
} else {
    die("❌ FAILED: Contract record not found or data mismatch.\n");
}

// Check Deudor
$check_d = $conn->query("SELECT * FROM clientes_deudores WHERE id_contrato = $id_contrato");
$deudor = $check_d->fetch_assoc();

if ($deudor) {
    echo "✅ SUCCESS: Debtor record created.\n";
    $expected_saldo = 100.00 - 70.00;
    if (abs($deudor['saldo_pendiente'] - $expected_saldo) < 0.01) {
        echo "✅ SUCCESS: Saldo pendiente correct: " . $deudor['saldo_pendiente'] . "\n";
    } else {
        echo "❌ FAILED: Saldo pendiente mismatch: " . $deudor['saldo_pendiente'] . " (Expected $expected_saldo)\n";
    }
} else {
    echo "❌ FAILED: Debtor record NOT created for pending balance.\n";
}

// 4. Cleanup
$conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $id_contrato");
$conn->query("DELETE FROM contratos WHERE id = $id_contrato");
unlink($mock_file);

echo "=== TEST COMPLETED ===\n";
$conn->close();
?>
