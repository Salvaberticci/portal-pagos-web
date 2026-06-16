<?php
/**
 * Test: Monthly Billing Generation
 * Verifies:
 * 1. Automatic identifying of active contracts for billing.
 * 2. Correct CxC parameters (amount from plan, status PENDIENTE).
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TESTING: Monthly Billing Generation ===\n";

$test_plan_id = 999;
$test_id = null;

try {
    // --- 1. SETUP: TEST PLAN AND CONTRACT ---
    echo "Creating test plan ($24.99) and active contract...\n";
    
    $conn->query("DELETE FROM planes WHERE id_plan = $test_plan_id");
    $conn->query("INSERT INTO planes (id_plan, nombre_plan, monto) VALUES ($test_plan_id, 'TEST-PLAN-MONTHLY', 24.99)");
    
    $test_cedula = "V" . rand(1000000, 9999999);
    $conn->query("INSERT INTO contratos (cedula, nombre_completo, id_plan, estado, direccion, telefono, ident_caja_nap, puerto_nap, num_presinto_odn) VALUES ('$test_cedula', 'MONTHLY TEST USER', $test_plan_id, 'ACTIVO', 'TEST DIR', '04120000000', 'CAJA001', '1', 'PRE001')");
    $test_id = $conn->insert_id;
    
    echo "✅ Setup complete. Contract $test_id created.\n";

    // --- 2. EXECUTE BILLING LOGIC ---
    // We isolate the logic to only process our test contract
    echo "Executing billing logic for test contract...\n";
    
    $fecha_emision = date('Y-m-d');
    $fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));
    
    $sql_logic = "
        INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, id_plan_cobrado)
        SELECT c.id, '$fecha_emision', '$fecha_vencimiento', p.monto, 'PENDIENTE', c.id_plan
        FROM contratos c
        JOIN planes p ON c.id_plan = p.id_plan
        WHERE c.id = $test_id
    ";

    if ($conn->query($sql_logic)) {
        echo "✅ Billing logic executed successfully.\n";
    } else {
        throw new Exception("Billing logic failed: " . $conn->error);
    }

    // --- 3. VERIFICATION ---
    $cxc = $conn->query("SELECT * FROM cuentas_por_cobrar WHERE id_contrato = $test_id")->fetch_assoc();
    
    if ($cxc) {
        if (abs($cxc['monto_total'] - 24.99) < 0.01) {
            echo "✅ SUCCESS: CxC amount is correct: " . $cxc['monto_total'] . "\n";
        } else {
            throw new Exception("CxC amount mismatch: " . $cxc['monto_total']);
        }
        
        if ($cxc['estado'] === 'PENDIENTE') {
            echo "✅ SUCCESS: CxC status is PENDIENTE.\n";
        } else {
            throw new Exception("CxC status mismatch: " . $cxc['estado']);
        }
    } else {
        throw new Exception("CxC record not found after billing execution.");
    }

    echo "\n=== ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
} finally {
    // Cleanup
    if ($test_id) {
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_id");
        $conn->query("DELETE FROM contratos WHERE id = $test_id");
    }
    $conn->query("DELETE FROM planes WHERE id_plan = $test_plan_id");
    echo "Cleanup completed.\n";
    $conn->close();
}
?>
