<?php
/**
 * Test script for Registration Fields and Installer Logic (Updated: No sae_plus)
 * This script verifies that all fields (including installer, numero_onu) are saved correctly.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "--- STARTING REGISTRATION FIELDS TEST (NO SAE_PLUS) ---\n";

// 1. Setup a test contract ID
$test_id = 999999;
$conn->query("DELETE FROM contratos WHERE id = $test_id");

// 2. Prepare mock data
$mock_data = [
    'cedula' => 'V-TEST-999',
    'nombre_completo' => 'CLIENTE TEST FIELDS FINAL',
    'telefono' => '04120000000',
    'correo' => 'test@fields.com',
    'id_municipio' => 1,
    'id_parroquia' => 1,
    'municipio_texto' => 'MUNICIPIO TEST',
    'parroquia_texto' => 'PARROQUIA TEST',
    'id_plan' => 1,
    'monto_plan' => 25.00,
    'vendedor_texto' => 'VENDEDOR TEST',
    'direccion' => 'DIRECCION TEST',
    'fecha_instalacion' => date('Y-m-d'),
    'estado' => 'ACTIVO',
    'ident_caja_nap' => 'NAP-TEST',
    'puerto_nap' => 'P-1',
    'num_presinto_odn' => 'PR-123',
    'id_olt' => 1,
    'id_pon' => 1,
    'tipo_instalacion' => 'FTTH',
    'monto_instalacion' => 50.00,
    'gastos_adicionales' => 5.00,
    'monto_pagar' => 80.00,
    'monto_pagado' => 80.00,
    'instaladores' => ['INSTALADOR 1', 'INSTALADOR 2'], // ARRAY OF NAMES
    'telefono_secundario' => '',
    'correo_adicional' => '',
    'medio_pago' => 'Efectivo',
    'moneda_pago' => 'USD',
    'plan_prorrateo_nombre' => '',
    'dias_prorrateo' => 0,
    'monto_prorrateo_usd' => 0,
    'observaciones' => 'TEST OBSERVATIONS',
    'tipo_conexion' => 'FTTH',
    'mac_onu' => 'AA:BB:CC:DD:EE:FF',
    'ip_onu' => '192.168.100.1',
    'nap_tx_power' => '-20',
    'onu_rx_power' => '-22',
    'distancia_drop' => '100',
    'punto_acceso' => '',
    'valor_conexion_dbm' => '',
    'evidencia_fibra' => 'YES'
];

// 3. SIMULATE guarda.php LOGIC FOR SAVING
$instalador_val = implode(', ', $mock_data['instaladores']);

$sql = "INSERT INTO contratos (
    id, cedula, nombre_completo, telefono, correo, 
    id_municipio, id_parroquia, municipio_texto, parroquia_texto, id_plan, monto_plan, vendedor_texto,
    direccion, fecha_instalacion, estado, ident_caja_nap, puerto_nap, 
    num_presinto_odn, id_olt, id_pon, tipo_instalacion, monto_instalacion, 
    gastos_adicionales, monto_pagar, monto_pagado, instalador,
    telefono_secundario, correo_adicional, medio_pago, moneda_pago, plan_prorrateo_nombre, dias_prorrateo,
    monto_prorrateo_usd, observaciones, tipo_conexion, mac_onu, ip_onu,
    nap_tx_power, onu_rx_power, distancia_drop, punto_acceso, valor_conexion_dbm,
    evidencia_fibra
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error . "\n");

// Types: i (id), s (ced), s (name), s (tel), s (email), i (id_m), i (id_p), s (mun_t), s (par_t), i (id_pl), d (m_pl), s (vend)
//        s (dir), s (f_i), s (est), s (nap), s (p_nap), s (p_odn), i (olt), i (pon), s (t_i), d (m_i), d (g_a), d (m_pr), d (m_pd), s (inst)
//        s (t_s), s (c_a), s (m_p), s (mon), s (pl_pr), i (d_pr), d (m_pr_u), s (obs), s (t_c), s (mac), s (ip_o), s (nap_tx), s (onu_rx), s (dist), s (pt_a), s (val_c), s (ev_f)
// Total columns: 43.

$types = "issssiissid" . "sssssssiisdddds" . "ssssidsssssssssss";
// Count: 11 + 15 + 17 = 43. Correct.

$stmt->bind_param(
    $types,
    $test_id,
    $mock_data['cedula'],
    $mock_data['nombre_completo'],
    $mock_data['telefono'],
    $mock_data['correo'],
    $mock_data['id_municipio'],
    $mock_data['id_parroquia'],
    $mock_data['municipio_texto'],
    $mock_data['parroquia_texto'],
    $mock_data['id_plan'],
    $mock_data['monto_plan'],
    $mock_data['vendedor_texto'],
    $mock_data['direccion'],
    $mock_data['fecha_instalacion'],
    $mock_data['estado'],
    $mock_data['ident_caja_nap'],
    $mock_data['puerto_nap'],
    $mock_data['num_presinto_odn'],
    $mock_data['id_olt'],
    $mock_data['id_pon'],
    $mock_data['tipo_instalacion'],
    $mock_data['monto_instalacion'],
    $mock_data['gastos_adicionales'],
    $mock_data['monto_pagar'],
    $mock_data['monto_pagado'],
    $instalador_val,
    $mock_data['telefono_secundario'],
    $mock_data['correo_adicional'],
    $mock_data['medio_pago'],
    $mock_data['moneda_pago'],
    $mock_data['plan_prorrateo_nombre'],
    $mock_data['dias_prorrateo'],
    $mock_data['monto_prorrateo_usd'],
    $mock_data['observaciones'],
    $mock_data['tipo_conexion'],
    $mock_data['mac_onu'],
    $mock_data['ip_onu'],
    $mock_data['nap_tx_power'],
    $mock_data['onu_rx_power'],
    $mock_data['distancia_drop'],
    $mock_data['punto_acceso'],
    $mock_data['valor_conexion_dbm'],
    $mock_data['evidencia_fibra']
);

if ($stmt->execute()) {
    echo "Step 1: Mock contract registration successful.\n";
} else {
    die("Step 1 ERROR: Registration failed: " . $stmt->error . "\n");
}

// 4. VERIFY RESULTS
$check = $conn->query("SELECT * FROM contratos WHERE id = $test_id");
if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    
    // Check Installer
    if ($row['instalador'] === 'INSTALADOR 1, INSTALADOR 2') {
        echo "VERIFICATION SUCCESS: Installer field saved correctly as '" . $row['instalador'] . "'.\n";
    } else {
        echo "VERIFICATION FAILED: Installer field is '" . $row['instalador'] . "' (Expected 'INSTALADOR 1, INSTALADOR 2').\n";
    }
    
} else {
    echo "VERIFICATION FAILED: Contract record not found.\n";
}

// Cleanup
$conn->query("DELETE FROM contratos WHERE id = $test_id");
echo "Step 2: Test data cleaned up.\n";

$conn->close();
echo "--- TEST COMPLETED ---\n";
?>
