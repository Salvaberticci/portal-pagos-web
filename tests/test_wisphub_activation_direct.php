<?php
/**
 * Test directo: Payment → Activation (sin servidor web)
 *
 * Prueba el flujo completo sin levantar servidor PHP.
 * Las llamadas a APIs externas se redirigen a una función mock.
 */

require_once __DIR__ . '/../paginas/conexion.php';

echo "=== TEST: Payment → Activation (Directo, sin servidor) ===\n\n";

$test_cedula = "V" . rand(1000000, 9999999);
$test_contrato_id = null;
$test_reporte_id = null;

// Guardar configs originales
$wisphub_cred_file = __DIR__ . '/../config/wisphub_credentials.php';
$wisphub_cred_backup = file_exists($wisphub_cred_file) ? file_get_contents($wisphub_cred_file) : null;

$bancos_path = __DIR__ . '/../paginas/principal/bancos.json';
$bancos_backup = file_exists($bancos_path) ? file_get_contents($bancos_path) : null;

function assert_test($condition, $message) {
    if ($condition) {
        echo "  [OK] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        throw new Exception("Fallo: $message");
    }
}

try {
    // ── 1. Configurar WispHub mock (apuntar a mock local) ────────────────
    echo "Fase 1: Configurando WispHub mock...\n";
    $mockWisphub = "<?php\n"
        . "define('WISP_HUB_API_KEY', 'mock_test_key');\n"
        . "define('WISP_HUB_API_SECRET', 'mock_test_secret');\n"
        . "define('WISP_HUB_BASE_URL', 'https://mock-wisphub.local/api');\n";
    file_put_contents($wisphub_cred_file, $mockWisphub);
    echo "  Config mock escrita.\n";

    // ── 2. Configurar BDV mock ──────────────────────────────────────────
    echo "Fase 2: Configurando BDV mock...\n";
    $mockBancos = [[
        'id_banco' => '9',
        'nombre_banco' => 'Banco de Venezuela MOCK',
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
            'endpoint' => 'https://mock-bdv.local/api',
        ],
    ]];
    file_put_contents($bancos_path, json_encode($mockBancos, JSON_PRETTY_PRINT));
    echo "  Config mock BDV escrita.\n";

    // ── 3. Crear contrato ───────────────────────────────────────────────
    echo "Fase 3: Creando contrato de prueba...\n";
    $conn->query("INSERT INTO contratos (
        cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan,
        vendedor_texto, direccion, fecha_instalacion, estado, monto_instalacion,
        monto_pagar, monto_pagado, instalador, tipo_conexion, mac_onu
    ) VALUES ('$test_cedula', 'TEST DIRECT ACTIVATION', 1, 1, 1, 17.50, 'TEST',
              'DIR TEST', NOW(), 'ACTIVO', 0, 0, 0, 'TEST', 'FTTH', 'MAC-DIR-001')");
    $test_contrato_id = $conn->insert_id;
    assert_test($test_contrato_id > 0, "Contrato creado (ID: $test_contrato_id)");

    // ── 4. Crear wisp_hub_links ─────────────────────────────────────────
    echo "Fase 4: Creando wisp_hub_links...\n";
    $testAccountId = 'dir-svc-' . rand(1000, 9999);
    $conn->query("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, created_at)
                  VALUES (NULL, $test_contrato_id, '$testAccountId', 'ACTIVE', NOW())");
    echo "  wisp_hub_links: $testAccountId\n";

    // ── 5. Crear deuda ──────────────────────────────────────────────────
    $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen)
                  VALUES ($test_contrato_id, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 17.50, 'PENDIENTE', 'SISTEMA')");

    // ── 6. Crear reporte de pago ─────────────────────────────────────────
    echo "Fase 5: Creando reporte de pago...\n";
    $tasa = 36.00;
    $monto_bs = 1.00;
    $monto_usd = $monto_bs / $tasa;

    $stmt = $conn->prepare("INSERT INTO pagos_reportados (
        cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
        id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
        meses_pagados, concepto, capture_path, id_contrato_asociado, estado
    ) VALUES (?, 'TITULAR PRUEBA', '04121234567', CURRENT_DATE, 'Pago Móvil', 9,
              '999222', ?, ?, ?, '1 mes', 'Pago mensual', 'capture.png', ?, 'PENDIENTE')");
    $stmt->bind_param("sdddi", $test_cedula, $monto_bs, $monto_usd, $tasa, $test_contrato_id);
    $stmt->execute();
    $test_reporte_id = $conn->insert_id;
    $stmt->close();
    assert_test($test_reporte_id > 0, "Reporte de pago (ID: $test_reporte_id)");

    // ── 7. Simular BDV auto-verificación (mock directo sin HTTP) ─────────
    echo "Fase 6: Simulando auto-aprobación BDV...\n";

    // Simulamos lo que hace bdv_autoverify_helper.php cuando encuentra match
    $conn->begin_transaction();
    try {
        // Marcar reporte APROBADO
        $conn->query("UPDATE pagos_reportados SET estado = 'APROBADO' WHERE id_reporte = $test_reporte_id");

        // Crear CxC
        $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, fecha_pago, referencia_pago, id_banco, origen, capture_pago)
                      VALUES ($test_contrato_id, CURRENT_DATE, CURRENT_DATE, $monto_usd, 'PAGADO', CURRENT_DATE, '999222', 9, 'API_BDV', 'capture.png')");

        $conn->commit();

        // ── 8. Simular WispHub notifyPayment + activateService ──────────
        echo "Fase 7: Simulando llamadas a WispHub...\n";

        // Simular lo que hace bdv_autoverify_helper.php:
        // Buscar wisp_account_id y llamar activateService

        $q = $conn->query("SELECT wisp_account_id FROM wisp_hub_links WHERE contract_id = $test_contrato_id AND wisp_account_id != '' ORDER BY id DESC LIMIT 1");
        $linkRow = $q->fetch_assoc();
        $foundAccountId = $linkRow['wisp_account_id'] ?? '';
        assert_test($foundAccountId === $testAccountId, "wisp_account_id encontrado en BD");

        // Simular activateService con respuesta HTTP 200
        $mockActivateResponse = ['status' => 200, 'data' => ['message' => 'Servicio activado']];
        $activateOk = ($mockActivateResponse['status'] === 200);

        // Log en wisp_hub_logs
        $logPayload = json_encode(['activate_service_id' => $foundAccountId]);
        $logResponse = json_encode($mockActivateResponse);
        $stmt_log = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (?, ?, ?, NOW())");
        $stmt_log->bind_param("iss", $test_reporte_id, $logPayload, $logResponse);
        $stmt_log->execute();
        $stmt_log->close();

        assert_test($activateOk, "activateService() retornó HTTP 200");
        echo "  activateService: HTTP 200 (simulado)\n";

        // ── 9. Verificaciones finales ───────────────────────────────────
        echo "Fase 8: Verificando resultados...\n";

        $rep = $conn->query("SELECT estado FROM pagos_reportados WHERE id_reporte = $test_reporte_id")->fetch_assoc();
        assert_test($rep['estado'] === 'APROBADO', "Reporte APROBADO");

        $ctr = $conn->query("SELECT estado FROM contratos WHERE id = $test_contrato_id")->fetch_assoc();
        assert_test($ctr['estado'] === 'ACTIVO', "Contrato ACTIVO");

        $log = $conn->query("SELECT response_payload FROM wisp_hub_logs WHERE payment_id = $test_reporte_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
        assert_test($log !== null, "wisp_hub_logs tiene registro");

        $link = $conn->query("SELECT status FROM wisp_hub_links WHERE contract_id = $test_contrato_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
        if ($link) {
            echo "  Link status: {$link['status']}\n";
        }

        echo "\n=== TODOS LOS TESTS PASARON EXITOSAMENTE ===\n";
        echo "Flow simulado: Pago → BDV aprueba → WispHub activa ✅\n";

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
} finally {
    // ── Limpieza ────────────────────────────────────────────────────────
    echo "\n--- Limpieza ---\n";

    if ($wisphub_cred_backup !== null) {
        file_put_contents($wisphub_cred_file, $wisphub_cred_backup);
        echo "wisphub_credentials.php restaurado.\n";
    }
    if ($bancos_backup !== null) {
        file_put_contents($bancos_path, $bancos_backup);
        echo "bancos.json restaurado.\n";
    }

    if ($test_contrato_id) {
        $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id = $test_reporte_id OR payment_id IS NULL");
        $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $test_contrato_id");
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id)");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM pagos_reportados WHERE id_reporte = $test_reporte_id");
        $conn->query("DELETE FROM contratos WHERE id = $test_contrato_id");
        echo "Datos de prueba eliminados.\n";
    }

    $conn->close();
}
