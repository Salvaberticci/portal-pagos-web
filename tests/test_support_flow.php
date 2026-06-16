<?php
/**
 * Test: Technical Support Flow
 * Verifies:
 * 1. Report submission and data integrity.
 * 2. Automatic debt creation/update for service fees.
 * 3. CxC generation for the visit.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TESTING: Technical Support Flow ===\n";

$test_cedula = "V" . rand(1000000, 9999999);
$id_contrato = null;

try {
    // --- 1. SETUP: REGISTER CONTRACT ---
    $conn->query("INSERT INTO contratos (cedula, nombre_completo, estado, direccion, telefono, ident_caja_nap, puerto_nap, num_presinto_odn) VALUES ('$test_cedula', 'SUPPORT TEST USER', 'ACTIVO', 'TEST DIR', '04120000000', 'CAJA001', '1', 'PRE001')");
    $id_contrato = $conn->insert_id;
    echo "✅ Contract $id_contrato registered.\n";

    // --- 2. SUBMIT TECHNICAL REPORT ---
    echo "Submitting technical report with $10 fee...\n";

    $mock_post = [
        'id_contrato' => $id_contrato,
        'tipo_servicio' => 'INTERNET',
        'tipo_falla' => 'LENTITUD',
        'monto_total' => 10.00,
        'monto_pagado' => 0.00,
        'sector' => 'CENTRO',
        'id_olt' => 1,
        'id_pon' => 1,
        'observaciones' => 'TEST REPORT VIA SCRIPT',
        'solucion_completada' => 1
    ];

    // Create mock environment
    $mock_file = __DIR__ . '/tmp_mock_support.php';
    $mock_content = "<?php \n\$_POST = " . var_export($mock_post, true) . ";\n" . 
                   "\$_SERVER['REQUEST_METHOD'] = 'POST';\n" .
                   "chdir(__DIR__ . '/../paginas/soporte');\n" .
                   "if (!function_exists('ob_clean')) { function ob_clean() {} }\n" .
                   "if (!function_exists('ob_get_length')) { function ob_get_length() { return 0; } }\n";
    file_put_contents($mock_file, $mock_content);

    // Execute backend
    $cmd = "php -d auto_prepend_file=" . escapeshellarg($mock_file) . " " . escapeshellarg(__DIR__ . '/../paginas/soporte/guardar_reporte_publico.php');
    $output = shell_exec($cmd);
    
    echo "Response: $output\n";
    $res = json_decode($output, true);
    if (!$res || $res['status'] !== 'success') {
        throw new Exception("Report submission failed: " . ($res['msg'] ?? 'Unknown error'));
    }
    $id_soporte = $res['id_soporte'];
    echo "✅ Report $id_soporte created.\n";

    // --- 3. VERIFICATIONS ---
    
    // Check soportes table
    $soporte = $conn->query("SELECT * FROM soportes WHERE id_soporte = $id_soporte")->fetch_assoc();
    if ($soporte && $soporte['tipo_falla'] === 'LENTITUD' && $soporte['prioridad'] === 'NIVEL 1') {
        echo "✅ SUCCESS: Support record data integrity verified.\n";
    } else {
        throw new Exception("Support record mismatch.");
    }

    // Check deudores table
    $deudor = $conn->query("SELECT * FROM clientes_deudores WHERE id_contrato = $id_contrato")->fetch_assoc();
    if ($deudor && abs($deudor['saldo_pendiente'] - 10.00) < 0.01) {
        echo "✅ SUCCESS: Debtor record created with $10 balance.\n";
    } else {
        throw new Exception("Debtor record mismatch or not found.");
    }

    // Check CxC table
    $cxc = $conn->query("SELECT * FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato")->fetch_assoc();
    if ($cxc && abs($cxc['monto_total'] - 10.00) < 0.01) {
        echo "✅ SUCCESS: CxC record created for the visit.\n";
    } else {
        throw new Exception("CxC record mismatch or not found.");
    }

    echo "\n=== ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
} finally {
    // Cleanup
    if ($id_contrato) {
        $conn->query("DELETE FROM soportes WHERE id_contrato = $id_contrato");
        $conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $id_contrato");
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_contrato = $id_contrato");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato");
        $conn->query("DELETE FROM contratos WHERE id = $id_contrato");
        if (isset($mock_file) && file_exists($mock_file)) unlink($mock_file);
        echo "Cleanup completed.\n";
    }
    $conn->close();
}
?>
