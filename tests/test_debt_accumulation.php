<?php
/**
 * Test: Debt Accumulation
 * Verifies that the monthly billing process accumulates debt instead of overwriting records.
 */

require_once __DIR__ . '/../paginas/conexion.php';

function get_pending_count($id_contrato) {
    global $conn;
    $res = $conn->query("SELECT COUNT(*) FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato AND estado = 'PENDIENTE'");
    return (int)$res->fetch_array()[0];
}

function run_billing_process() {
    global $conn;
    $old_cwd = getcwd();
    chdir(__DIR__ . '/../paginas/principal');
    
    ob_start();
    include 'generar_mensual.php';
    $output = ob_get_clean();
    
    chdir($old_cwd);
    return $output;
}

echo "Starting Debt Accumulation Test...\n";

// 1. Create a test contract + plan
$plan_id = 9998;
$conn->query("DELETE FROM planes WHERE id_plan = $plan_id");
$conn->query("INSERT INTO planes (id_plan, nombre_plan, monto) VALUES ($plan_id, 'TEST-ACCUM-PLAN', 25.00)");

$test_cedula = "V" . rand(10000000, 99999999);
$conn->query("INSERT INTO contratos (cedula, nombre_completo, id_plan, monto_plan, estado, direccion, telefono, ident_caja_nap, puerto_nap, num_presinto_odn, fecha_instalacion) VALUES ('$test_cedula', 'TEST ACCUM USER', $plan_id, 25.00, 'ACTIVO', 'TEST DIR', '04120000000', 'CAJA001', '1', 'PRE001', CURDATE())");
$id_test = $conn->insert_id;

$initial_count = get_pending_count($id_test);
echo "Initial pending bills for contract $id_test: $initial_count\n";

// 2. Run first billing cycle
echo "Running first billing cycle...\n";
run_billing_process();
$count_1 = get_pending_count($id_test);
echo "Pending bills after 1st run: $count_1\n";

if ($count_1 <= $initial_count) {
    echo "FAIL: No new bill was created. Check if contract is correctly filtered in generar_mensual.php\n";
    // Cleanup before exit
    $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id_test");
    $conn->query("DELETE FROM contratos WHERE id = $id_test");
    $conn->query("DELETE FROM planes WHERE id_plan = $plan_id");
    exit(1);
}

// 3. Run second billing cycle
echo "Running second billing cycle...\n";
run_billing_process();
$count_2 = get_pending_count($id_test);
echo "Pending bills after 2nd run: $count_2\n";

if ($count_2 < $count_1) {
    echo "FAIL: Debt decreased after 2nd run (should have stayed same if already billed).\n";
    $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id_test");
    $conn->query("DELETE FROM contratos WHERE id = $id_test");
    $conn->query("DELETE FROM planes WHERE id_plan = $plan_id");
    exit(1);
}

echo "PASS: Debt accumulated correctly ($initial_count -> $count_1 -> $count_2).\n";

// 4. Cleanup
echo "Cleaning up test records...\n";
$conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id_test");
$conn->query("DELETE FROM contratos WHERE id = $id_test");
$conn->query("DELETE FROM planes WHERE id_plan = $plan_id");

echo "Test finished successfully.\n";
