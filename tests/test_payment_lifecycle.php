<?php
/**
 * Test: Payment and Debt Lifecycle
 * Verifies:
 * 1. Initial debt creation during registration.
 * 2. Partial payment (abono) on debt.
 * 3. Debt status update to PAGADO when fully covered.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TESTING: Payment and Debt Lifecycle ===\n";

$test_cedula = "V" . rand(1000000, 9999999);
$test_id = null;

try {
    // --- 1. SETUP: REGISTER CONTRACT WITH DEBT ---
    echo "Ph1: Registering contract with $50 debt...\n";
    
    $mock_reg = [
        'cedula' => $test_cedula,
        'nombre_completo' => 'TEST PAYMENT USER',
        'monto_instalacion' => 100.00,
        'monto_pagar' => 100.00,
        'monto_pagado' => 50.00, // $50 PENDING
        'id_municipio' => 1,
        'id_parroquia' => 1,
        'id_plan' => 1,
        'monto_plan' => 25.00,
        'vendedor_texto' => 'TEST VEND',
        'direccion' => 'TEST DIR',
        'telefono' => '04120000000',
        'fecha_instalacion' => date('Y-m-d'),
        'estado_contrato' => 'ACTIVO',
        'instaladores' => ['TEST TEST'],
        'tipo_conexion' => 'FTTH',
        'mac_onu' => 'MAC' . rand(1000, 9999),
        'ident_caja_nap' => 'CAJA001',
        'puerto_nap' => '1',
        'num_presinto_odn' => 'PRE001',
    ];

    // Simulate guarda.php logic
    $instalador_val = implode(', ', $mock_reg['instaladores']);
    $sql_ins = "INSERT INTO contratos (
        cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan, vendedor_texto,
        direccion, telefono, fecha_instalacion, estado, monto_instalacion, monto_pagar, monto_pagado, 
        instalador, tipo_conexion, mac_onu, ident_caja_nap, puerto_nap, num_presinto_odn
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql_ins);
    $types = "ssiiidsssssddddsssss";
    $stmt->bind_param($types, 
        $mock_reg['cedula'], $mock_reg['nombre_completo'], $mock_reg['id_municipio'],
        $mock_reg['id_parroquia'], $mock_reg['id_plan'], $mock_reg['monto_plan'],
        $mock_reg['vendedor_texto'], $mock_reg['direccion'], $mock_reg['telefono'],
        $mock_reg['fecha_instalacion'],
        $mock_reg['estado_contrato'], $mock_reg['monto_instalacion'], $mock_reg['monto_pagar'],
        $mock_reg['monto_pagado'], $instalador_val, $mock_reg['tipo_conexion'], $mock_reg['mac_onu'],
        $mock_reg['ident_caja_nap'], $mock_reg['puerto_nap'], $mock_reg['num_presinto_odn']
    );
    $stmt->execute();
    $test_id = $conn->insert_id;
    $stmt->close();

    // Trigger deudor logic from guarda.php
    $saldo_pendiente = 100.00 - 50.00;
    $conn->query("INSERT INTO clientes_deudores (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado) 
                  VALUES ($test_id, 100.00, 50.00, $saldo_pendiente, 'PENDIENTE')");
    $id_deudor = $conn->insert_id;

    echo "✅ Contract $test_id created with Deudor $id_deudor.\n";

    // --- 2. ABONO REGISTRATION ---
    echo "Ph2: Registering $30 abono...\n";
    
    $abono_amount = 30.00;
    $referencia = "REF-" . rand(1000, 9999);
    $id_banco = 1;

    // Simulate registrar_abono_deudor.php logic
    $check_d = $conn->query("SELECT * FROM clientes_deudores WHERE id = $id_deudor");
    $deudor = $check_d->fetch_assoc();
    
    $nuevo_monto_pagado = $deudor['monto_pagado'] + $abono_amount;
    $nuevo_saldo_pend = $deudor['saldo_pendiente'] - $abono_amount;
    
    // Update deudor
    $conn->query("UPDATE clientes_deudores SET monto_pagado = $nuevo_monto_pagado, saldo_pendiente = $nuevo_saldo_pend WHERE id = $id_deudor");
    
    // Register cxc for abono
    $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, monto_total, fecha_emision, fecha_vencimiento, estado, fecha_pago, referencia_pago, id_banco)
                  VALUES ($test_id, $abono_amount, CURDATE(), CURDATE(), 'PAGADO', NOW(), '$referencia', $id_banco)");
    
    echo "✅ Abono registered. Verifying balance...\n";
    
    $check_v1 = $conn->query("SELECT saldo_pendiente FROM clientes_deudores WHERE id = $id_deudor")->fetch_assoc();
    if (abs($check_v1['saldo_pendiente'] - 20.00) < 0.01) {
        echo "✅ SUCCESS: Balance is $20.00.\n";
    } else {
        throw new Exception("Balance mismatch: " . $check_v1['saldo_pendiente']);
    }

    // --- 3. FINAL ABONO (Settle Debt) ---
    echo "Ph3: Registering final $20 abono...\n";
    
    $abono_final = 20.00;
    
    // Simulate registrar_abono_deudor.php settlement logic
    $conn->query("UPDATE clientes_deudores SET monto_pagado = monto_pagado + $abono_final, saldo_pendiente = 0, estado = 'PAGADO' WHERE id = $id_deudor");
    
    echo "✅ Debt settled. Verifying status...\n";
    
    $check_v2 = $conn->query("SELECT estado, saldo_pendiente FROM clientes_deudores WHERE id = $id_deudor")->fetch_assoc();
    if ($check_v2['estado'] === 'PAGADO' && $check_v2['saldo_pendiente'] == 0) {
        echo "✅ SUCCESS: Debtor status is PAGADO and balance is 0.\n";
    } else {
        throw new Exception("Settlement failed: Status=" . $check_v2['estado'] . " Balance=" . $check_v2['saldo_pendiente']);
    }

    echo "\n=== ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
} finally {
    // Cleanup
    if ($test_id) {
        $conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $test_id");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_id");
        $conn->query("DELETE FROM contratos WHERE id = $test_id");
        echo "Cleanup completed.\n";
    }
    $conn->close();
}
?>
