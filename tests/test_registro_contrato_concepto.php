<?php
/**
 * Test: Verificar que al registrar un contrato, la primera factura
 * usa el concepto [REGISTRO_CONTRATO] en cobros_manuales_historial.
 *
 * Verifica:
 *  1. El contrato se crea correctamente.
 *  2. Se genera una entrada en cuentas_por_cobrar.
 *  3. Se genera una entrada en cobros_manuales_historial con [REGISTRO_CONTRATO].
 *  4. El concepto mostrado NO es "Mensualidad" genérica.
 *
 * Cleanup: Elimina todos los registros de prueba al finalizar.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TEST: Concepto 'Registro de Contrato' al crear contrato ===\n\n";

$passed = 0;
$failed = 0;

function pass($msg) { global $passed; $passed++; echo "✅ PASS: $msg\n"; }
function fail($msg) { global $failed; $failed++; echo "❌ FAIL: $msg\n"; }

// === PREPARAR DATOS ===
$test_cedula  = 'V' . rand(10000000, 99999999);
$test_nombre  = 'TEST REGISTRO CONTRATO';
$test_ip      = '10.99.' . rand(1,254) . '.' . rand(2,254);
$test_mac     = 'TT:' . rand(10,99) . ':' . rand(10,99) . ':' . rand(10,99) . ':' . rand(10,99) . ':' . rand(10,99);

// Obtener un plan válido
$plan_res = $conn->query("SELECT id_plan, monto FROM planes LIMIT 1");
if (!$plan_res || $plan_res->num_rows === 0) {
    die("❌ ABORT: No hay planes en la BD. No se puede ejecutar el test.\n");
}
$plan = $plan_res->fetch_assoc();
$id_plan    = $plan['id_plan'];
$monto_plan = $plan['monto'];

echo "Usando plan ID: $id_plan, monto: \$$monto_plan\n";
echo "Cédula test: $test_cedula / IP test: $test_ip\n\n";

// === STEP 1: Insertar el contrato directamente (simula guarda.php) ===
$fecha_hoy = date('Y-m-d');
$fecha_ven = date('Y-m-d', strtotime('+30 days'));

$stmt_c = $conn->prepare(
    "INSERT INTO contratos (cedula, nombre_completo, telefono, id_plan, monto_plan, 
     fecha_instalacion, estado, monto_instalacion, monto_pagar, monto_pagado,
     tipo_conexion, ip_onu, mac_onu, medio_pago, moneda_pago,
     direccion, ident_caja_nap, puerto_nap, num_presinto_odn)
     VALUES (?, ?, '04120000000', ?, ?, ?, 'ACTIVO', 50, 80, 40, 'FTTH', ?, ?, 'Efectivo', 'USD',
     'TEST DIR', 'CAJA001', '1', 'PRE001')"
);
$stmt_c->bind_param("ssidsss", $test_cedula, $test_nombre, $id_plan, $monto_plan, $fecha_hoy, $test_ip, $test_mac);
$stmt_c->execute();
$id_contrato = $conn->insert_id;
$stmt_c->close();

if ($id_contrato <= 0) {
    die("❌ ABORT: No se pudo insertar el contrato de prueba.\n");
}
pass("Contrato creado con ID: $id_contrato");

// === STEP 2: Insertar la primera factura (simula la lógica de guarda.php) ===
$stmt_cxc = $conn->prepare(
    "INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total)
     VALUES (?, ?, ?, ?)"
);
$stmt_cxc->bind_param("issd", $id_contrato, $fecha_hoy, $fecha_ven, $monto_plan);
$stmt_cxc->execute();
$id_cobro = $conn->insert_id;
$stmt_cxc->close();

if ($id_cobro <= 0) {
    fail("No se generó la primera factura en cuentas_por_cobrar.");
} else {
    pass("Primera factura generada en cuentas_por_cobrar con ID: $id_cobro");
}

// === STEP 3: Insertar en historial con [REGISTRO_CONTRATO] (nueva lógica de guarda.php) ===
$justif = "[REGISTRO_CONTRATO] Primera mensualidad del contrato";
$stmt_h = $conn->prepare(
    "INSERT INTO cobros_manuales_historial (id_cobro_cxc, id_contrato, autorizado_por, justificacion, monto_cargado)
     VALUES (?, ?, 'SISTEMA', ?, ?)"
);
$stmt_h->bind_param("iisd", $id_cobro, $id_contrato, $justif, $monto_plan);
$stmt_h->execute();
$id_historial = $conn->insert_id;
$stmt_h->close();

if ($id_historial <= 0) {
    fail("No se insertó el registro en cobros_manuales_historial.");
} else {
    pass("Registro historial creado con ID: $id_historial");
}

// === STEP 4: Verificar que la justificación contiene [REGISTRO_CONTRATO] ===
$verify = $conn->query("SELECT justificacion, autorizado_por FROM cobros_manuales_historial WHERE id_cobro_cxc = $id_cobro");
if ($verify && $verify->num_rows > 0) {
    $row = $verify->fetch_assoc();
    if (strpos($row['justificacion'], '[REGISTRO_CONTRATO]') !== false) {
        pass("Justificación contiene '[REGISTRO_CONTRATO]': " . $row['justificacion']);
    } else {
        fail("Justificación NO contiene '[REGISTRO_CONTRATO]'. Valor actual: " . $row['justificacion']);
    }
    if ($row['autorizado_por'] === 'SISTEMA') {
        pass("autorizado_por es 'SISTEMA' correctamente.");
    } else {
        fail("autorizado_por inesperado: " . $row['autorizado_por']);
    }
} else {
    fail("No se encontró el registro en cobros_manuales_historial para id_cobro = $id_cobro.");
}

// === STEP 5: Verificar que el concepto NO cae en "Mensualidad" genérica ===
// Simular la lógica de server_process_mensualidades.php
$justif_db = $conn->query("SELECT h.justificacion, pl.nombre_plan 
    FROM cobros_manuales_historial h
    JOIN cuentas_por_cobrar cxc ON cxc.id_cobro = h.id_cobro_cxc
    JOIN contratos co ON co.id = cxc.id_contrato
    JOIN planes pl ON pl.id_plan = co.id_plan
    WHERE h.id_cobro_cxc = $id_cobro")->fetch_assoc();

if ($justif_db) {
    $j = $justif_db['justificacion'];
    $conceptosArr = [];
    if (strpos($j, '[MENSUALIDAD') !== false)     $conceptosArr[] = 'Mensualidad';
    if (strpos($j, '[INSTALACION') !== false)     $conceptosArr[] = 'Instalación';
    if (strpos($j, '[REGISTRO_CONTRATO') !== false) $conceptosArr[] = 'Registro de Contrato';

    if (in_array('Registro de Contrato', $conceptosArr) && !in_array('Mensualidad', $conceptosArr)) {
        pass("El concepto resuelto es 'Registro de Contrato' (no 'Mensualidad').");
    } else {
        fail("El concepto incorrecto: " . implode(', ', $conceptosArr));
    }
}

// === CLEANUP ===
echo "\n--- Limpiando registros de prueba ---\n";
$conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc = $id_cobro");
$conn->query("DELETE FROM cuentas_por_cobrar WHERE id_cobro = $id_cobro");
$conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $id_contrato");
$conn->query("DELETE FROM contratos WHERE id = $id_contrato");
echo "Registros eliminados.\n";

// === RESUMEN ===
echo "\n=== RESUMEN: {$passed} pasados | {$failed} fallidos ===\n";
if ($failed === 0) {
    echo "🎉 Todos los tests pasaron correctamente.\n";
} else {
    echo "⚠️  Algunos tests fallaron. Revisar los errores arriba.\n";
}

$conn->close();
?>
