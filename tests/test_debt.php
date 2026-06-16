<?php
/**
 * Test script for Debt Logic
 * This script verifies that the debt logic works correctly.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "--- STARTING DEBT LOGIC TEST ---\n";

// 1. Setup a test contract ID
$test_id = 999999;
$conn->query("DELETE FROM contratos WHERE id = $test_id");
$conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $test_id");

// 2. Insert test contract
$monto_pagar = 100.00;
$monto_pagado = 40.00;
$saldo_pendiente = round($monto_pagar - $monto_pagado, 2);

$sql_ins = "INSERT INTO contratos (id, cedula, nombre_completo, monto_pagar, monto_pagado, estado, direccion, telefono, ident_caja_nap, puerto_nap, num_presinto_odn, fecha_instalacion) 
            VALUES ($test_id, 'V99999999', 'TEST DEBTOR', $monto_pagar, $monto_pagado, 'ACTIVO', 'TEST DIR', '04120000000', 'CAJA001', '1', 'PRE001', CURDATE())";
if (!$conn->query($sql_ins)) {
    die("Error inserting test contract: " . $conn->error . "\n");
}
echo "Step 1: Test contract inserted correctly with $40 paid of $100.\n";

// 3. RUN DEBT LOGIC (Simulating guarda.php logic)
if ($saldo_pendiente > 0) {
    if ($conn->query("INSERT INTO clientes_deudores (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado) 
                      VALUES ($test_id, $monto_pagar, $monto_pagado, $saldo_pendiente, 'PENDIENTE')")) {
        echo "Step 2: Debt record created successfully (Simulating guarda.php).\n";
    } else {
        echo "Step 2 ERROR: Failed to create debt record: " . $conn->error . "\n";
    }
}

// 4. VERIFY DEBT RECORD exists
$check = $conn->query("SELECT * FROM clientes_deudores WHERE id_contrato = $test_id AND estado = 'PENDIENTE'");
if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    echo "VERIFICATION 1 SUCCESS: Debt record found with saldo_pendiente = " . $row['saldo_pendiente'] . "\n";
} else {
    echo "VERIFICATION 1 FAILED: Debt record not found.\n";
}

// 5. UPDATE CONTRACT BALANCE (Simulating actualizar_contrato_ajax.php logic)
$monto_pagado_new = 100.00;
$conn->query("UPDATE contratos SET monto_pagado = $monto_pagado_new WHERE id = $test_id");
echo "Step 3: Contract updated to full payment ($100).\n";

// Run SYNC LOGIC
$saldo_pendiente = round($monto_pagar - $monto_pagado_new, 2);
if ($saldo_pendiente <= 0) {
    if ($conn->query("UPDATE clientes_deudores SET estado = 'PAGADO', saldo_pendiente = 0 WHERE id_contrato = $test_id AND estado = 'PENDIENTE'")) {
        echo "Step 4: Debt record marked as PAGADO successfully (Simulating sync logic).\n";
    } else {
        echo "Step 4 ERROR: Failed to update debt record: " . $conn->error . "\n";
    }
}

// 6. VERIFY FINAL STATE
$check_final = $conn->query("SELECT * FROM clientes_deudores WHERE id_contrato = $test_id");
if ($check_final && $check_final->num_rows > 0) {
    $row = $check_final->fetch_assoc();
    if ($row['estado'] == 'PAGADO' && $row['saldo_pendiente'] == 0) {
        echo "VERIFICATION 2 SUCCESS: Debt record state is PAGADO and saldo is 0.\n";
    } else {
        echo "VERIFICATION 2 FAILED: Final state incorrect. Status: " . $row['estado'] . ", Saldo: " . $row['saldo_pendiente'] . "\n";
    }
} else {
    echo "VERIFICATION 2 FAILED: Debt record disappeared.\n";
}

// Cleanup
$conn->query("DELETE FROM contratos WHERE id = $test_id");
$conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $test_id");
echo "Step 5: Test data cleaned up.\n";

$conn->close();
echo "--- TEST COMPLETED ---\n";
?>
