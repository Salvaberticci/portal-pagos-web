<?php
/**
 * Test: Advance Payments Refactor
 * Verifies that multi-month manual payments create separate records
 * and that the monthly process correctly skips pre-paid clients.
 */

require_once __DIR__ . '/../paginas/conexion.php';

function get_bills_count($id_contrato) {
    global $conn;
    $res = $conn->query("SELECT COUNT(*) FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato");
    return (int)$res->fetch_array()[0];
}

function run_manual_payment($id_contrato, $meses, $monto) {
    global $conn;
    $_SERVER["REQUEST_METHOD"] = "POST";
    // monto = per-month price, meses_mensualidad controls splitting in backend
    $_POST = [
        'id_contrato' => $id_contrato,
        'monto' => $monto,
        'referencia_pago' => 'TEST_ADVANCE_' . uniqid(),
        'id_banco_pago' => 1,
        'autorizado_por' => 'TEST_BOT',
        'justificacion' => 'Test de adelanto',
        'desglose_mensualidad_activado' => '1',
        'monto_mensualidad' => $monto,
        'meses_mensualidad' => $meses
    ];

    ob_start();
    $old_cwd = getcwd();
    chdir(__DIR__ . '/../paginas/principal');
    
    // We expect a redirect (header Location)
    include 'generar_cobro_manual.php';
    
    chdir($old_cwd);
    ob_end_clean();
}

function run_monthly_job() {
    ob_start();
    $old_cwd = getcwd();
    chdir(__DIR__ . '/../paginas/principal');
    
    // Define global conn since it's used in generar_mensual.php
    global $conn;
    include 'generar_mensual.php';
    
    chdir($old_cwd);
    ob_end_clean();
}

echo "Starting Advance Payments Test...\n";

// 1. Create a test contract + plan
$plan_id = 9997;
$conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato IN (SELECT id FROM contratos WHERE id_plan = $plan_id))");
$conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato IN (SELECT id FROM contratos WHERE id_plan = $plan_id)");
$conn->query("DELETE FROM contratos WHERE id_plan = $plan_id");
$conn->query("DELETE FROM planes WHERE id_plan = $plan_id");
$conn->query("INSERT INTO planes (id_plan, nombre_plan, monto) VALUES ($plan_id, 'TEST-ADVANCE-PLAN', 25.00)");

$test_cedula = "V" . rand(10000000, 99999999);
$monto_plan = 25.00;
$conn->query("INSERT INTO contratos (cedula, nombre_completo, id_plan, monto_plan, estado, direccion, telefono, ident_caja_nap, puerto_nap, num_presinto_odn, fecha_instalacion) VALUES ('$test_cedula', 'TEST ADVANCE USER', $plan_id, $monto_plan, 'ACTIVO', 'TEST DIR', '04120000000', 'CAJA001', '1', 'PRE001', CURDATE())");
$id_test = $conn->insert_id;

$initial_count = get_bills_count($id_test);
echo "Initial bills for contract $id_test: $initial_count\n";

// 2. Simulate 2 months payment
echo "Registering manual payment for 2 months...\n";
run_manual_payment($id_test, 2, $monto_plan);

$count_after_manual = get_bills_count($id_test);
echo "Bills after manual payment: $count_after_manual\n";

if ($count_after_manual != ($initial_count + 2)) {
    echo "FAIL: Expected " . ($initial_count + 2) . " bills, found $count_after_manual. Refactor failed to create separate records.\n";
    exit(1);
}
echo "PASS: Two separate records created for 2-month payment.\n";

// 3. Verify future date for the 2nd record
$res_dates = $conn->query("SELECT fecha_emision FROM cuentas_por_cobrar WHERE id_contrato = $id_test ORDER BY id_cobro DESC LIMIT 2");
$dates = [];
while($d = $res_dates->fetch_assoc()) $dates[] = $d['fecha_emision'];

echo "Dates created: " . implode(", ", $dates) . "\n";
$next_month_date = date('Y-m-d', strtotime("+1 month"));
if (!in_array($next_month_date, $dates)) {
    echo "FAIL: Next month emission date ($next_month_date) not found in records.\n";
    exit(1);
}
echo "PASS: Future emission date correctly set for advance record.\n";

// 4. Run monthly job and verify NO DUPLICATE for the current month
echo "Running monthly billing job...\n";
run_monthly_job();

$final_count = get_bills_count($id_test);
echo "Bills after monthly job: $final_count\n";

if ($final_count > $count_after_manual) {
    echo "FAIL: Monthly job created a duplicate bill for a pre-paid client.\n";
    exit(1);
}
echo "PASS: Monthly job correctly skipped the pre-paid client.\n";

// 5. Cleanup
echo "Cleaning up test records...\n";
$conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $id_test)");
$conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id_test");
$conn->query("DELETE FROM contratos WHERE id = $id_test");
$conn->query("DELETE FROM planes WHERE id_plan = $plan_id");

echo "\nAll Advance Payment tests PASSED.\n";
