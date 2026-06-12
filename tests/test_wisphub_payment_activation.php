<?php
/**
 * Test: WispHub Payment → Service Activation Flow
 *
 * Verifica que cuando un pago es auto-aprobado por BDV,
 * se llama a WispHub para activar el servicio.
 *
 * Flujo:
 * 1. Crear contrato de prueba con wisp_hub_links (wisp_account_id conocido)
 * 2. Apuntar config de WispHub al mock API
 * 3. Reportar pago con referencia que coincide con el mock BDV
 * 4. Ejecutar auto-verificación BDV
 * 5. Verificar que se llamó a activateService() en el mock WispHub
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TEST: WispHub Payment → Service Activation ===\n\n";

$test_cedula = "V" . rand(1000000, 9999999);
$test_contrato_id = null;
$test_reporte_id = null;

// Guardar configs originales para restaurar después
$wisphub_cred_file = __DIR__ . '/../config/wisphub_credentials.php';
$wisphub_cred_backup = file_exists($wisphub_cred_file) ? file_get_contents($wisphub_cred_file) : null;

$bancos_path = __DIR__ . '/../paginas/principal/bancos.json';
$bancos_backup = file_exists($bancos_path) ? file_get_contents($bancos_path) : null;

$server_process = null;
$mock_port = 8643;

function assert_test($condition, $message) {
    if ($condition) {
        echo "  [OK] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        throw new Exception("Fallo: $message");
    }
}

try {
    // ── 0. Levantar mock WispHub + mock BDV ──────────────────────────────
    echo "Fase 0: Iniciando servidor local con mocks...\n";

    $docRoot = realpath(__DIR__ . '/../');
    $cmd = "php -S 127.0.0.1:$mock_port -t \"$docRoot\"";
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $server_process = proc_open($cmd, $descriptors, $server_pipes);
    if (!is_resource($server_process)) {
        throw new Exception("No se pudo iniciar el servidor PHP interno.");
    }
    sleep(1);
    echo "  Servidor mock en 127.0.0.1:$mock_port\n";

    // ── 1. Configurar WispHub para apuntar al mock ────────────────────────
    echo "Fase 1: Configurando WispHub mock...\n";

    // Crear archivo temporal de credenciales WispHub apuntando al mock
    $mockWisphubContent = "<?php\n"
        . "define('WISP_HUB_API_KEY', 'mock_test_key');\n"
        . "define('WISP_HUB_API_SECRET', 'mock_test_secret');\n"
        . "define('WISP_HUB_BASE_URL', 'http://127.0.0.1:$mock_port/tests/mock_wisphub_api.php/api');\n";
    file_put_contents($wisphub_cred_file, $mockWisphubContent);
    echo "  WispHub ahora apunta al mock\n";

    // ── 2. Configurar bancos.json para mock BDV ──────────────────────────
    echo "Fase 2: Configurando BDV mock...\n";

    $mockBancos = [
        [
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
        ],
    ];
    file_put_contents($bancos_path, json_encode($mockBancos, JSON_PRETTY_PRINT));
    echo "  BDV ahora apunta al mock\n";

    // ── 3. Crear contrato de prueba ───────────────────────────────────────
    echo "Fase 3: Creando contrato de prueba...\n";

    $sql = "INSERT INTO contratos (
        cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan,
        vendedor_texto, direccion, fecha_instalacion, estado, monto_instalacion,
        monto_pagar, monto_pagado, instalador, tipo_conexion, mac_onu
    ) VALUES (?, 'TEST WISPHUB ACTIVATION', 1, 1, 1, 17.50, 'TEST', 'DIR TEST',
              NOW(), 'ACTIVO', 0, 0, 0, 'TEST', 'FTTH', 'MAC-TEST-001')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $test_cedula);
    $stmt->execute();
    $test_contrato_id = $conn->insert_id;
    $stmt->close();
    assert_test($test_contrato_id > 0, "Contrato creado (ID: $test_contrato_id)");

    // Crear wisp_hub_links con un account_id conocido
    $testAccountId = 'test-svc-' . rand(1000, 9999);
    $conn->query("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, created_at)
                  VALUES (NULL, $test_contrato_id, '$testAccountId', NOW())");
    echo "  wisp_hub_links creado con account_id: $testAccountId\n";

    // Crear deuda pendiente
    $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen)
                  VALUES ($test_contrato_id, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 17.50, 'PENDIENTE', 'SISTEMA')");
    echo "  Cuenta por cobrar PENDIENTE creada\n";

    // ── 4. Simular pago que será auto-aprobado ────────────────────────────
    echo "Fase 4: Simulando pago con auto-aprobación BDV...\n";

    $tasa = 36.00;
    $monto_bs = 1.00; // Coincide con mock BDV referencia 999222 monto 1.00 Bs
    $monto_usd = $monto_bs / $tasa;

    // Insertar reporte de pago simulado
    $sql_rep = "INSERT INTO pagos_reportados (
        cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
        id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
        meses_pagados, concepto, capture_path, id_contrato_asociado, estado
    ) VALUES (?, 'TITULAR PRUEBA', '04121234567', CURRENT_DATE, 'Pago Móvil', 9,
              '999222', ?, ?, ?, '1 mes', 'Pago mensual', 'capture_test.png', ?, 'PENDIENTE')";

    $stmt_rep = $conn->prepare($sql_rep);
    $stmt_rep->bind_param("sdddi", $test_cedula, $monto_bs, $monto_usd, $tasa, $test_contrato_id);
    $stmt_rep->execute();
    $test_reporte_id = $conn->insert_id;
    $stmt_rep->close();
    assert_test($test_reporte_id > 0, "Reporte de pago creado (ID: $test_reporte_id)");

    // ── 5. Ejecutar auto-verificación BDV ────────────────────────────────
    echo "Fase 5: Ejecutando auto-verificación BDV...\n";

    require_once __DIR__ . '/../portal/bdv_autoverify_helper.php';

    $aprobado = verificar_y_aprobar_pago_bdv(
        $conn,
        9,
        '999222',
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $test_reporte_id,
        'capture_test.png',
        '1 mes',
        'Pago mensual'
    );

    assert_test($aprobado === true, "Auto-aprobación BDV exitosa");

    // ── 6. Verificar WispHub activation ──────────────────────────────────
    echo "Fase 6: Verificando activación en WispHub...\n";

    // Verificar que se registró el log de activate en wisp_hub_logs
    $logRes = $conn->query("SELECT request_payload, response_payload FROM wisp_hub_logs
                            WHERE payment_id = $test_reporte_id
                            ORDER BY id DESC LIMIT 1");
    $logRow = $logRes ? $logRes->fetch_assoc() : null;

    assert_test($logRow !== null, "Registro en wisp_hub_logs existe");

    if ($logRow) {
        $resp = json_decode($logRow['response_payload'], true);
        $notifyStatus = $resp['notify']['status'] ?? ($resp['status'] ?? 0);
        $activateStatus = $resp['activate']['status'] ?? 0;

        assert_test($notifyStatus === 200, "notifyPayment respondió HTTP 200");
        assert_test($activateStatus === 200, "activateService respondió HTTP 200");

        echo "  notifyPayment status: $notifyStatus\n";
        echo "  activateService status: $activateStatus\n";
    }

    // Verificar que wisp_hub_links se actualizó
    $linkRes = $conn->query("SELECT status, last_event FROM wisp_hub_links WHERE contract_id = $test_contrato_id ORDER BY id DESC LIMIT 1");
    if ($linkRes && $linkRow = $linkRes->fetch_assoc()) {
        echo "  Link status: " . ($linkRow['status'] ?? 'sin status') . "\n";
        echo "  Link last_event: " . ($linkRow['last_event'] ?? 'sin evento') . "\n";
    }

    // Verificar estado del contrato
    $contratoRes = $conn->query("SELECT estado FROM contratos WHERE id = $test_contrato_id");
    $contratoRow = $contratoRes->fetch_assoc();
    assert_test($contratoRow['estado'] === 'ACTIVO', "Contrato permanece ACTIVO (no fue suspendido)");

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

    // Restaurar configs originales
    if ($wisphub_cred_backup !== null) {
        file_put_contents($wisphub_cred_file, $wisphub_cred_backup);
        echo "wisphub_credentials.php restaurado.\n";
    }
    if ($bancos_backup !== null) {
        file_put_contents($bancos_path, $bancos_backup);
        echo "bancos.json restaurado.\n";
    }

    // Limpiar BD
    if ($test_contrato_id) {
        $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id = 0 OR payment_id = " . intval($test_reporte_id));
        $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $test_contrato_id");
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id)");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM pagos_reportados WHERE id_reporte = " . intval($test_reporte_id));
        $conn->query("DELETE FROM contratos WHERE id = $test_contrato_id");
        echo "Datos de prueba eliminados de BD.\n";
    }

    $conn->close();
}
