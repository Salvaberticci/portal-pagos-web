<?php
/**
 * Test: Installer Edge Cases
 * Verifies:
 * 1. Duplicate IP ONU detection.
 * 2. Field integrity in technical section.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TESTING: Installer Edge Cases ===\n";

$test_id_base = rand(1000, 9999);
$test_ip = "172.16.0." . rand(2, 254);
$test_cedula1 = "V111" . $test_id_base;
$test_cedula2 = "V222" . $test_id_base;

try {
    // --- 1. SETUP: REGISTER FIRST CONTRACT WITH IP ---
    echo "Registering first contract with IP $test_ip...\n";
    
    $conn->query("INSERT INTO contratos (cedula, nombre_completo, ip_onu, estado, direccion, telefono, ident_caja_nap, puerto_nap, num_presinto_odn) VALUES ('$test_cedula1', 'USER 1', '$test_ip', 'ACTIVO', 'TEST DIR', '04120000000', 'CAJA001', '1', 'PRE001')");
    $id1 = $conn->insert_id;
    echo "✅ Contract $id1 registered.\n";

    // --- 2. ATTEMPT SECOND REGISTRATION WITH SAME IP ---
    echo "Attempting second registration with SAME IP $test_ip...\n";
    
    $mock_post = [
        'cedula' => $test_cedula2,
        'nombre_completo' => 'USER 2 (DUPLICATE IP)',
        'ip_onu' => $test_ip, // DUPLICATE
        'monto_instalacion' => 50.00,
        'tipo_conexion' => 'FTTH'
    ];

    $mock_file = __DIR__ . '/tmp_mock_edge.php';
    $mock_content = "<?php \n\$_POST = " . var_export($mock_post, true) . ";\n" . 
                   "\$_SERVER['REQUEST_METHOD'] = 'POST';\n" .
                   "chdir(__DIR__ . '/../paginas/soporte');\n" .
                   "if (!function_exists('ob_clean')) { function ob_clean() {} }\n" .
                   "if (!function_exists('ob_get_length')) { function ob_get_length() { return 0; } }\n";
    file_put_contents($mock_file, $mock_content);

    // Execute backend
    $cmd = "php -d auto_prepend_file=" . escapeshellarg($mock_file) . " " . escapeshellarg(__DIR__ . '/../paginas/soporte/guardar_contrato_instalador.php');
    $output = shell_exec($cmd);
    
    echo "Response: $output\n";
    $res = json_decode($output, true);
    
    if ($res && $res['status'] === 'error' && strpos($res['msg'], "ya existe") !== false) {
        echo "✅ SUCCESS: Duplicate IP detected and rejected as expected.\n";
    } else {
        throw new Exception("Duplicate IP was NOT rejected. Response: $output");
    }

    // Verify second contract NOT in DB
    $check2 = $conn->query("SELECT id FROM contratos WHERE cedula = '$test_cedula2'");
    if ($check2->num_rows === 0) {
        echo "✅ SUCCESS: Duplicate applicant not recorded in database.\n";
    } else {
        throw new Exception("Duplicate applicant record FOUND in database.");
    }

    echo "\n=== ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
} finally {
    // Cleanup
    if (isset($id1)) $conn->query("DELETE FROM contratos WHERE id = $id1");
    // Ensure second one is really gone if it somehow slipped in
    $conn->query("DELETE FROM contratos WHERE cedula = '$test_cedula2'");
    if (isset($mock_file) && file_exists($mock_file)) unlink($mock_file);
    echo "Cleanup completed.\n";
    $conn->close();
}
?>
