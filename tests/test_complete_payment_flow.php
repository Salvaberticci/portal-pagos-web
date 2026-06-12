<?php
/**
 * Test: Complete Payment → Activation Flow (E2E)
 *
 * Simula el flujo completo usando el usuario de prueba V99999999:
 * 1. Contrato ACTIVO con deuda PENDIENTE y wisp_hub_links
 * 2. Cliente reporta pago por el portal (simulado)
 * 3. BDV auto-verifica y aprueba
 * 4. WispHub: notifyPayment + activateService
 * 5. Verificar todo el registro en BD (pagos_reportados, cuentas_por_cobrar, wisp_hub_logs)
 *
 * Usa mocks: mock_bdv_api.php + mock_wisphub_api.php
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TEST E2E: Complete Payment → Activation Flow (V99999999) ===\n\n";

$test_cedula = 'V99999999';
$test_contrato_id = null;
$test_reporte_id = null;

$wisphub_cred_file = __DIR__ . '/../config/wisphub_credentials.php';
$wisphub_cred_backup = file_exists($wisphub_cred_file) ? file_get_contents($wisphub_cred_file) : null;

$bancos_path = __DIR__ . '/../paginas/principal/bancos.json';
$bancos_backup = file_exists($bancos_path) ? file_get_contents($bancos_path) : null;

$server_process = null;
$mock_port = 8843;

function assert_test($condition, $message) {
    if ($condition) {
        echo "  [OK] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        throw new Exception("Fallo: $message");
    }
}

try {
    // ── 0. Limpiar datos previos del test user ────────────────────────────
    echo "Fase 0: Limpiando datos previos de V99999999...\n";
    $old_contratos = $conn->query("SELECT id FROM contratos WHERE cedula = 'V99999999'");
    while ($old = $old_contratos->fetch_assoc()) {
        $cid = $old['id'];
        $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IN (SELECT id_reporte FROM pagos_reportados WHERE cedula_titular = 'V99999999')");
        $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $cid");
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $cid)");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $cid");
        $conn->query("DELETE FROM pagos_reportados WHERE id_contrato_asociado = $cid");
        $conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $cid");
        $conn->query("DELETE FROM contratos WHERE id = $cid");
    }
    echo "  Datos previos eliminados.\n";

    // ── 1. Levantar servidor mock ────────────────────────────────────────
    echo "Fase 1: Iniciando servidor local con mocks...\n";

    $docRoot = realpath(__DIR__ . '/../');
    $cmd = "php -S 127.0.0.1:$mock_port -t \"$docRoot\"";
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $server_process = proc_open($cmd, $descriptors, $server_pipes);
    if (!is_resource($server_process)) {
        throw new Exception("No se pudo iniciar el servidor PHP interno.");
    }
    sleep(1);
    echo "  Servidor mock en 127.0.0.1:$mock_port\n";

    // ── 2. Configurar mocks ──────────────────────────────────────────────
    echo "Fase 2: Configurando mocks (WispHub + BDV)...\n";

    // WispHub mock
    $mockWisphub = "<?php\n"
        . "define('WISP_HUB_API_KEY', 'mock_test_key');\n"
        . "define('WISP_HUB_API_SECRET', 'mock_test_secret');\n"
        . "define('WISP_HUB_BASE_URL', 'http://127.0.0.1:$mock_port/tests/mock_wisphub_api.php/api');\n";
    file_put_contents($wisphub_cred_file, $mockWisphub);

    // BDV mock
    $mockBancos = [[
        'id_banco' => '9',
        'nombre_banco' => 'Banco de Venezuela (Pago Móvil) MOCK',
        'numero_cuenta' => '04247377954',
        'cedula_propietario' => 'J 408882540',
        'nombre_propietario' => 'SITELCO C.A.',
        'metodos_pago' => ['Pago Móvil'],
        'activo' => true,
        'api_config' => [
            'habilitada' => true,
            'tipo' => 'bdv',
            'api_key' => 'MOCK_KEY_123456',
            'cuenta' => '01020589150000001371',
            'titular' => 'SITELCO C.A.',
            'endpoint' => "http://127.0.0.1:$mock_port/tests/mock_bdv_api.php",
        ],
    ]];
    file_put_contents($bancos_path, json_encode($mockBancos, JSON_PRETTY_PRINT));
    echo "  Mocks configurados.\n";

    // ── 3. Crear contrato de prueba ───────────────────────────────────────
    echo "Fase 3: Creando contrato de prueba V99999999...\n";

    $sql = "INSERT INTO contratos (
        cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan,
        vendedor_texto, direccion, fecha_instalacion, estado, monto_instalacion,
        monto_pagar, monto_pagado, instalador, tipo_conexion, mac_onu
    ) VALUES ('V99999999', 'USUARIO DE PRUEBA E2E', 1, 1, 4, 17.50,
              'TEST', 'DIRECCION DE PRUEBA', NOW(), 'ACTIVO', 0, 0, 0,
              'TEST', 'FTTH', 'MAC-E2E-001')";

    $conn->query($sql);
    $test_contrato_id = $conn->insert_id;
    assert_test($test_contrato_id > 0, "Contrato creado (ID: $test_contrato_id, estado: ACTIVO)");

    // ── 4. Crear wisp_hub_links + deuda ──────────────────────────────────
    echo "Fase 4: Preparando wisp_hub_links y deuda...\n";

    $testAccountId = 'V99999999';
    $conn->query("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, created_at)
                  VALUES (NULL, $test_contrato_id, '$testAccountId', 'ACTIVE', NOW())");
    echo "  wisp_hub_links: account_id=$testAccountId\n";

    $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen)
                  VALUES ($test_contrato_id, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 17.50, 'PENDIENTE', 'SISTEMA')");
    echo "  Deuda PENDIENTE creada: 17.50 USD\n";

    // ── 5. Simular pago que BDV auto-aprueba ──────────────────────────────
    echo "Fase 5: Simulando reporte de pago desde el portal...\n";

    $tasa = 36.00;
    $monto_bs = 1.00; // Coincide con mock BDV ref 999222, monto 1.00
    $monto_usd = $monto_bs / $tasa;

    $sql_rep = "INSERT INTO pagos_reportados (
        cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
        id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
        meses_pagados, concepto, capture_path, id_contrato_asociado, estado
    ) VALUES ('V99999999', 'USUARIO DE PRUEBA E2E', '04120000000', CURRENT_DATE,
              'Pago Móvil', 9, '999222', ?, ?, ?, '1 mes',
              'Pago de mensualidad por portal', 'capture_e2e.png', ?, 'PENDIENTE')";

    $stmt_rep = $conn->prepare($sql_rep);
    $stmt_rep->bind_param("dddi", $monto_bs, $monto_usd, $tasa, $test_contrato_id);
    $stmt_rep->execute();
    $test_reporte_id = $conn->insert_id;
    $stmt_rep->close();
    assert_test($test_reporte_id > 0, "Reporte de pago creado (ID: $test_reporte_id)");

    // ── 6. Ejecutar auto-verificación BDV ────────────────────────────────
    echo "Fase 6: Ejecutando auto-verificación BDV...\n";

    require_once __DIR__ . '/../portal/bdv_autoverify_helper.php';

    $auto_aprobado = verificar_y_aprobar_pago_bdv(
        $conn,
        9,
        '999222',
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $test_reporte_id,
        'capture_e2e.png',
        '1 mes',
        'Pago de mensualidad por portal'
    );

    assert_test($auto_aprobado === true, "Auto-aprobación BDV exitosa");

    // ── 7. Verificar registros en BD ─────────────────────────────────────
    echo "Fase 7: Verificando registros en base de datos...\n";

    // 7a. pagos_reportados debe estar APROBADO
    $repRes = $conn->query("SELECT estado FROM pagos_reportados WHERE id_reporte = $test_reporte_id")->fetch_assoc();
    assert_test($repRes['estado'] === 'APROBADO', "pagos_reportados.estado = APROBADO");

    // 7b. cuentas_por_cobrar debe tener PAGADO
    $cxcRes = $conn->query("SELECT estado, referencia_pago FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id AND origen = 'API_BDV' ORDER BY id_cobro DESC LIMIT 1")->fetch_assoc();
    assert_test($cxcRes !== null && $cxcRes['estado'] === 'PAGADO', "cuentas_por_cobrar creada con estado PAGADO");

    // 7c. wisp_hub_logs debe tener registros de notify + activate
    $logRes = $conn->query("SELECT response_payload FROM wisp_hub_logs WHERE payment_id = $test_reporte_id ORDER BY id DESC LIMIT 1");
    $logRow = $logRes ? $logRes->fetch_assoc() : null;
    assert_test($logRow !== null, "Registro en wisp_hub_logs para el payment_id");

    if ($logRow) {
        $resp = json_decode($logRow['response_payload'], true);
        $activate = $resp['activate'] ?? $resp;
        assert_test(($activate['status'] ?? 0) === 200, "activateService HTTP 200 en logs");
        echo "  activateService status: " . ($activate['status'] ?? 'N/A') . "\n";
    }

    // 7d. contrato debe estar ACTIVO
    $ctrRes = $conn->query("SELECT estado FROM contratos WHERE id = $test_contrato_id")->fetch_assoc();
    assert_test($ctrRes['estado'] === 'ACTIVO', "Contrato permanece ACTIVO");

    echo "\n=== TODOS LOS TESTS E2E PASARON EXITOSAMENTE ===\n";
    echo "Flow: V99999999 → Pago → BDV Auto-Aprueba → WispHub Activa ✅\n";

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
    if ($bancos_backup !== null) {
        file_put_contents($bancos_path, $bancos_backup);
        echo "bancos.json restaurado.\n";
    }

    if ($test_contrato_id) {
        $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id = $test_reporte_id OR payment_id = 0");
        $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $test_contrato_id");
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id)");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM pagos_reportados WHERE id_reporte = $test_reporte_id");
        $conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM contratos WHERE id = $test_contrato_id");
        echo "Datos de prueba eliminados de BD.\n";
    }

    $conn->close();
}
