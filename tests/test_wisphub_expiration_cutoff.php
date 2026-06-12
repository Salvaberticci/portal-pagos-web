<?php
/**
 * Test: WispHub Service Expiration → Cutoff
 *
 * Verifica que cuando un contrato tiene cuentas por cobrar vencidas,
 * el script cron corta el servicio llamando a WispHub suspendService().
 *
 * Flujo:
 * 1. Crear contrato ACTIVO con wisp_hub_links
 * 2. Crear cuentas_por_cobrar PENDIENTE con fecha_vencimiento anterior (vencida)
 * 3. Apuntar WispHub al mock API
 * 4. Ejecutar cron/cortar_servicios_vencidos.php con 0 días de gracia
 * 5. Verificar:
 *    - suspendService() fue llamado en mock WispHub
 *    - contrato.estado = 'SUSPENDIDO'
 *    - wisp_hub_links.status = 'SUSPENDED'
 *    - Log en wisp_hub_logs
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TEST: WispHub Expiration → Service Cutoff ===\n\n";

$test_cedula = "V" . rand(1000000, 9999999);
$test_contrato_id = null;

$wisphub_cred_file = __DIR__ . '/../config/wisphub_credentials.php';
$wisphub_cred_backup = file_exists($wisphub_cred_file) ? file_get_contents($wisphub_cred_file) : null;

$server_process = null;
$mock_port = 8743;

function assert_test($condition, $message) {
    if ($condition) {
        echo "  [OK] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        throw new Exception("Fallo: $message");
    }
}

try {
    // ── 0. Levantar mock WispHub ─────────────────────────────────────────
    echo "Fase 0: Iniciando servidor mock WispHub...\n";

    $docRoot = realpath(__DIR__ . '/../');
    $cmd = "php -S 127.0.0.1:$mock_port -t \"$docRoot\"";
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $server_process = proc_open($cmd, $descriptors, $server_pipes);
    if (!is_resource($server_process)) {
        throw new Exception("No se pudo iniciar el servidor PHP interno.");
    }
    sleep(1);
    echo "  Mock WispHub en 127.0.0.1:$mock_port\n";

    // ── 1. Configurar WispHub mock ───────────────────────────────────────
    echo "Fase 1: Configurando WispHub mock...\n";

    $mockContent = "<?php\n"
        . "define('WISP_HUB_API_KEY', 'mock_test_key');\n"
        . "define('WISP_HUB_API_SECRET', 'mock_test_secret');\n"
        . "define('WISP_HUB_BASE_URL', 'http://127.0.0.1:$mock_port/tests/mock_wisphub_api.php/api');\n";
    file_put_contents($wisphub_cred_file, $mockContent);
    echo "  WispHub apunta al mock\n";

    // ── 2. Crear contrato de prueba ACTIVO ───────────────────────────────
    echo "Fase 2: Creando contrato de prueba ACTIVO...\n";

    $sql = "INSERT INTO contratos (
        cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan,
        vendedor_texto, direccion, fecha_instalacion, estado, monto_instalacion,
        monto_pagar, monto_pagado, instalador, tipo_conexion, mac_onu
    ) VALUES (?, 'TEST WISPHUB CUTOFF', 1, 1, 1, 17.50, 'TEST', 'DIR TEST',
              NOW(), 'ACTIVO', 0, 0, 0, 'TEST', 'FTTH', 'MAC-CUT-001')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $test_cedula);
    $stmt->execute();
    $test_contrato_id = $conn->insert_id;
    $stmt->close();
    assert_test($test_contrato_id > 0, "Contrato creado (ID: $test_contrato_id, estado: ACTIVO)");

    // ── 3. Crear wisp_hub_links ──────────────────────────────────────────
    echo "Fase 3: Creando wisp_hub_links...\n";

    $testAccountId = 'test-cutoff-' . rand(1000, 9999);
    $conn->query("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, created_at)
                  VALUES (NULL, $test_contrato_id, '$testAccountId', 'ACTIVE', NOW())");
    echo "  wisp_hub_links creado: account_id=$testAccountId, status=ACTIVE\n";

    // ── 4. Crear cuentas_por_cobrar vencidas ─────────────────────────────
    echo "Fase 4: Creando cuentas por cobrar vencidas...\n";

    $fechaVencida = date('Y-m-d', strtotime('-30 days'));
    $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen)
                  VALUES ($test_contrato_id, '$fechaVencida', '$fechaVencida', 17.50, 'PENDIENTE', 'SISTEMA')");
    echo "  Cuenta por cobrar vencida desde $fechaVencida\n";

    // ── 5. Verificar estado antes del cron ───────────────────────────────
    echo "Fase 5: Verificando estado antes de ejecutar cron...\n";

    $antes = $conn->query("SELECT estado FROM contratos WHERE id = $test_contrato_id")->fetch_assoc();
    assert_test($antes['estado'] === 'ACTIVO', "Contrato está ACTIVO antes del cron");

    // ── 6. Ejecutar script cron ──────────────────────────────────────────
    echo "Fase 6: Ejecutando cron/cortar_servicios_vencidos.php (0 días gracia)...\n";

    $cronScript = realpath(__DIR__ . '/../cron/cortar_servicios_vencidos.php');
    $output = [];
    $exitCode = 0;
    exec("php \"$cronScript\" 0 2>&1", $output, $exitCode);

    echo "  Exit code: $exitCode\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }

    // ── 7. Verificar resultado ───────────────────────────────────────────
    echo "Fase 7: Verificando resultado...\n";

    // Verificar estado del contrato
    $despues = $conn->query("SELECT estado FROM contratos WHERE id = $test_contrato_id")->fetch_assoc();
    assert_test($despues['estado'] === 'SUSPENDIDO', "Contrato cambió a SUSPENDIDO después del cron");

    // Verificar wisp_hub_links
    $linkRes = $conn->query("SELECT status, last_event FROM wisp_hub_links WHERE contract_id = $test_contrato_id ORDER BY id DESC LIMIT 1");
    $linkRow = $linkRes ? $linkRes->fetch_assoc() : null;
    if ($linkRow) {
        assert_test($linkRow['status'] === 'SUSPENDED', "wisp_hub_links.status = SUSPENDED");
        assert_test($linkRow['last_event'] === 'cron.suspend', "wisp_hub_links.last_event = cron.suspend");
    }

    // Verificar log en wisp_hub_logs
    $logRes = $conn->query("SELECT response_payload FROM wisp_hub_logs
                            WHERE payment_id IS NULL
                            AND request_payload LIKE '%cron_suspend%'
                            AND request_payload LIKE '%$testAccountId%'
                            ORDER BY id DESC LIMIT 1");
    $logRow = $logRes ? $logRes->fetch_assoc() : null;
    assert_test($logRow !== null, "Registro de suspensión en wisp_hub_logs");

    if ($logRow) {
        $resp = json_decode($logRow['response_payload'], true);
        $suspendStatus = $resp['status'] ?? 0;
        assert_test($suspendStatus === 200, "suspendService respondió HTTP 200");
        echo "  suspendService status: $suspendStatus\n";
    }

    echo "\n=== TODOS LOS TESTS PASARON EXITOSAMENTE ===\n";

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
} finally {
    // ── Limpieza ────────────────────────────────────────────────────────
    echo "\n--- Limpieza ---\n";

    if ($server_process) {
        proc_terminate($server_process);
        echo "Servidor mock detenido.\n";
    }

    if ($wisphub_cred_backup !== null) {
        file_put_contents($wisphub_cred_file, $wisphub_cred_backup);
        echo "wisphub_credentials.php restaurado.\n";
    }

    if ($test_contrato_id) {
        $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%cron_suspend%'");
        $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $test_contrato_id");
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id)");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM contratos WHERE id = $test_contrato_id");
        echo "Datos de prueba eliminados de BD.\n";
    }

    $conn->close();
}
