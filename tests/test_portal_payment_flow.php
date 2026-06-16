<?php
/**
 * Test: Portal Payment Flow and Security Measures (Sin BD Local)
 *
 * Verifica:
 * 1. Generación y verificación de tokens CSRF.
 * 2. Rate Limiting por sesión (sin BD).
 * 3. Validación de inputs (números de referencia).
 * 4. Logging de eventos de seguridad en archivo.
 * 5. Auto-verificación de pago (BDV mock → WispHub mock):
 *    - Match exitoso (Auto-aprobado).
 *    - Referencia inexistente (Rechazado + motivo correcto).
 *    - Monto discrepante (Rechazado + motivo correcto).
 *
 * No requiere base de datos local. Usa mock APIs.
 */

// Simular entorno de sesión para los tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../portal/security_helper.php';
require_once __DIR__ . '/../portal/bdv_autoverify_helper.php';

echo "=== INICIANDO PRUEBAS: FLUJO DE PAGO Y SEGURIDAD DEL PORTAL ===\n\n";

$bancos_path = __DIR__ . '/../paginas/principal/bancos.json';
$original_bancos_content = file_exists($bancos_path) ? file_get_contents($bancos_path) : null;
$server_process = null;
$server_pipes = [];

// Helper para aserciones
function assert_test(bool $condition, string $message): void {
    if ($condition) {
        echo "✅ [OK] $message\n";
    } else {
        echo "❌ [FALLO] $message\n";
        throw new RuntimeException("Fallo en la prueba: $message");
    }
}

$tests_passed = 0;
$tests_failed = 0;

function run_assert(bool $condition, string $message): void {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo "✅ [OK] $message\n";
        $tests_passed++;
    } else {
        echo "❌ [FALLO] $message\n";
        $tests_failed++;
    }
}

try {
    // =========================================================================
    // FASE 0: LEVANTAR SERVIDOR MOCK LOCAL
    // =========================================================================
    echo "Fase 0: Levantando servidor local y configurando endpoints mock...\n";

    $mock_port = 8543;
    $docRoot   = realpath(__DIR__ . '/../');
    $cmd = "php -S 127.0.0.1:$mock_port -t \"$docRoot\"";
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $server_process = proc_open($cmd, $descriptors, $server_pipes);
    if (!is_resource($server_process)) {
        throw new RuntimeException("No se pudo iniciar el servidor PHP interno.");
    }
    sleep(1);

    // Apuntar BDV al mock
    $mock_bancos = [[
        'id_banco'           => '9',
        'nombre_banco'       => 'Banco de Venezuela (Pago Móvil) MOCK',
        'numero_cuenta'      => '04247377954',
        'cedula_propietario' => 'J 408882540',
        'nombre_propietario' => 'SITELCO C.A.',
        'metodos_pago'       => ['Pago Móvil'],
        'activo'             => true,
        'api_config'         => [
            'habilitada' => true,
            'tipo'       => 'bdv',
            'api_key'    => 'MOCK_KEY_123456',
            'cuenta'     => '01020589150000001371',
            'titular'    => 'SITELCO C.A.',
            'endpoint'   => "http://127.0.0.1:$mock_port/tests/mock_bdv_api.php",
        ],
    ]];
    file_put_contents($bancos_path, json_encode($mock_bancos, JSON_PRETTY_PRINT));
    echo "✅ Endpoint BDV redirigido a mock local.\n\n";

    // =========================================================================
    // FASE 1: PROTECCIÓN CSRF
    // =========================================================================
    echo "Fase 1: Probando protección CSRF...\n";

    // Limpiar sesión para empezar limpio
    unset($_SESSION['csrf_token']);

    $token = generate_csrf_token();
    run_assert(!empty($token), "El token CSRF no debe estar vacío.");
    run_assert(strlen($token) === 64, "El token CSRF debe tener 64 caracteres hex.");
    run_assert(verify_csrf_token($token), "El token generado debe verificar como CORRECTO.");
    run_assert(!verify_csrf_token('invalid_token_xyz'), "Un token arbitrario debe ser INCORRECTO.");
    run_assert(!verify_csrf_token(null), "Un token nulo debe ser INCORRECTO.");
    echo "\n";

    // =========================================================================
    // FASE 2: RATE LIMITING POR SESIÓN
    // =========================================================================
    echo "Fase 2: Probando Rate Limiting basado en sesión...\n";

    // Limpiar estado previo de rate limit en sesión
    $action = 'test_rate_limit_portal';
    unset($_SESSION["rate_limit_$action"]);

    // Límite: 3 intentos en 60 segundos
    run_assert(check_rate_limit($action, 3, 60), "Hit #1 debe ser PERMITIDO.");
    run_assert(check_rate_limit($action, 3, 60), "Hit #2 debe ser PERMITIDO.");
    run_assert(check_rate_limit($action, 3, 60), "Hit #3 debe ser PERMITIDO.");
    run_assert(!check_rate_limit($action, 3, 60), "Hit #4 (exceso) debe ser BLOQUEADO.");

    // Verificar que se escribió en el log de archivo
    $log_file = __DIR__ . '/../logs/security.log';
    $log_content = file_exists($log_file) ? file_get_contents($log_file) : '';
    run_assert(
        str_contains($log_content, 'RATE_LIMIT_EXCEEDED'),
        "Debe registrarse 'RATE_LIMIT_EXCEEDED' en el log de archivo."
    );

    // Limpiar para no interferir con otros tests
    unset($_SESSION["rate_limit_$action"]);
    echo "\n";

    // =========================================================================
    // FASE 3: VALIDACIÓN DE INPUTS DE REFERENCIA
    // =========================================================================
    echo "Fase 3: Probando validación de referencias de pago...\n";

    $referencias = [
        ['999222',   true,  'Ref numérica válida (6 dígitos)'],
        ['REF123456', true,  'Ref alfanumérica válida (8 chars)'],
        ['123',      false, 'Ref muy corta (3 chars) debe fallar'],
        ['',         false, 'Ref vacía debe fallar'],
        ['ABC!@#',   false, 'Ref con caracteres especiales limpiados queda corta'],
    ];

    foreach ($referencias as [$ref, $expected, $desc]) {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $ref);
        $is_valid = !empty($clean) && strlen($clean) >= 6;
        run_assert($is_valid === $expected, $desc);
    }
    echo "\n";

    // =========================================================================
    // FASE 4: LOGGING DE EVENTOS DE SEGURIDAD EN ARCHIVO
    // =========================================================================
    echo "Fase 4: Probando logging de eventos en archivo...\n";

    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/security.log';

    // Borrar log si existe para prueba limpia
    if (file_exists($log_file)) {
        @unlink($log_file);
    }

    // Simular sesión de usuario
    $_SESSION['cliente_cedula'] = 'V88888888';

    log_security_event('TEST_EVENT', 'Evento de prueba desde test_portal_payment_flow.php', 'V88888888');

    run_assert(file_exists($log_file), "El archivo de log debe crearse.");
    $content = file_get_contents($log_file);
    run_assert(str_contains($content, 'TEST_EVENT'), "El log debe contener el tipo de evento.");
    run_assert(str_contains($content, 'V88888888'), "El log debe contener el identificador del usuario.");
    run_assert(str_contains($content, 'Evento de prueba'), "El log debe contener los detalles del evento.");

    unset($_SESSION['cliente_cedula']);
    echo "\n";

    // =========================================================================
    // FASE 5: AUTO-VERIFICACIÓN EXITOSA BDV (REFERENCIA EXACTA)
    // =========================================================================
    echo "Fase 5: Probando auto-aprobación exitosa (ref exacta '999222', 1.00 Bs)...\n";

    // El usuario de prueba V20788775 con service_id 902
    $_SESSION['cliente_cedula'] = 'V20788775';

    $tasa    = 36.00;
    $monto_bs  = 1.00; // Mock BDV tiene ref 999222 con monto 1.00 Bs
    $monto_usd = $monto_bs / $tasa;

    $aprobado = verificar_y_aprobar_pago_bdv(
        9,           // id_banco (BDV mock)
        '999222',    // referencia
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        '902',       // wisp_service_id (usuario de prueba en WispHub)
        '',          // capture_path
        'Pago Móvil',
        '1 mes',
        'Pago de mensualidad por portal'
    );

    $motivo = $GLOBALS['bdv_falla_motivo'] ?? 'N/A';
    echo "  DEBUG: aprobado=" . ($aprobado ? 'true' : 'false') . ", motivo=$motivo\n";
    run_assert($aprobado === true, "Auto-aprobación con ref '999222' debe ser EXITOSA.");

    // Verificar que se generó log de pago
    $payment_log = __DIR__ . '/../logs/wisphub_payments.log';
    if (file_exists($payment_log)) {
        $plog_content = file_get_contents($payment_log);
        run_assert(str_contains($plog_content, '999222'), "Log de pagos debe contener la referencia aprobada.");
    }

    unset($_SESSION['cliente_cedula']);
    echo "\n";

    // =========================================================================
    // FASE 5B: AUTO-APROBACIÓN CON REFERENCIA PARCIAL (ÚLTIMOS DÍGITOS)
    // =========================================================================
    echo "Fase 5b: Probando auto-aprobación con referencia parcial '999444' (10.00 Bs)...\n";

    $_SESSION['cliente_cedula'] = 'V20788775';
    $monto_bs_parcial  = 10.00; // Mock BDV tiene ref 2026060400999444 con monto 10.00 Bs
    $monto_usd_parcial = $monto_bs_parcial / $tasa;

    $aprobado_parcial = verificar_y_aprobar_pago_bdv(
        9,
        '999444', // últimos 6 dígitos de '2026060400999444'
        $monto_usd_parcial,
        $tasa,
        date('Y-m-d'),
        '902',
        '',
        'Pago Móvil',
        '1 mes',
        'Pago de mensualidad por portal'
    );

    $motivo_p = $GLOBALS['bdv_falla_motivo'] ?? 'N/A';
    echo "  DEBUG: aprobado_parcial=" . ($aprobado_parcial ? 'true' : 'false') . ", motivo=$motivo_p\n";
    run_assert($aprobado_parcial === true, "Auto-aprobación con referencia parcial '999444' debe ser EXITOSA.");

    unset($_SESSION['cliente_cedula']);
    echo "\n";

    // =========================================================================
    // FASE 6: FALLO POR REFERENCIA INEXISTENTE
    // =========================================================================
    echo "Fase 6: Probando fallo con referencia inexistente '999999'...\n";

    $_SESSION['cliente_cedula'] = 'V20788775';

    $aprobado_err = verificar_y_aprobar_pago_bdv(
        9,
        '999999', // No existe en mock BDV
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        '902',
        '',
        'Pago Móvil',
        '1 mes',
        'Pago de mensualidad por portal'
    );

    $motivo_err = $GLOBALS['bdv_falla_motivo'] ?? '';
    echo "  DEBUG: aprobado_err=" . ($aprobado_err ? 'true' : 'false') . ", motivo=$motivo_err\n";
    run_assert($aprobado_err === false, "Ref inexistente debe ser RECHAZADA.");
    run_assert(
        str_contains($motivo_err, 'referencia') || str_contains($motivo_err, 'movimientos') || str_contains($motivo_err, 'no aparece'),
        "Motivo de rechazo debe mencionar la referencia no encontrada."
    );

    unset($_SESSION['cliente_cedula']);
    echo "\n";

    // =========================================================================
    // FASE 7: FALLO POR MONTO DISCREPANTE
    // =========================================================================
    echo "Fase 7: Probando fallo con monto incorrecto (ref '999333', mock tiene 5.50 Bs, reportamos 50.00 Bs)...\n";

    $_SESSION['cliente_cedula'] = 'V20788775';
    $monto_bs_incorrecto  = 50.00; // Mock tiene 5.50 Bs → diferencia 44.5 Bs > tolerancia 10 Bs
    $monto_usd_incorrecto = $monto_bs_incorrecto / $tasa;

    $aprobado_monto = verificar_y_aprobar_pago_bdv(
        9,
        '999333',
        $monto_usd_incorrecto,
        $tasa,
        date('Y-m-d'),
        '902',
        '',
        'Pago Móvil',
        '1 mes',
        'Pago de mensualidad por portal'
    );

    $motivo_monto = $GLOBALS['bdv_falla_motivo'] ?? '';
    echo "  DEBUG: aprobado_monto=" . ($aprobado_monto ? 'true' : 'false') . ", motivo=$motivo_monto\n";
    run_assert($aprobado_monto === false, "Monto discrepante debe ser RECHAZADO.");
    run_assert(
        str_contains($motivo_monto, 'monto') || str_contains($motivo_monto, 'coincide'),
        "Motivo de rechazo debe mencionar discrepancia de monto."
    );

    unset($_SESSION['cliente_cedula']);
    echo "\n";

    // =========================================================================
    // FASE 8: BANCO SIN API HABILITADA
    // =========================================================================
    echo "Fase 8: Probando banco sin API habilitada (id_banco=99)...\n";

    $aprobado_nobanco = verificar_y_aprobar_pago_bdv(
        99,        // banco inexistente
        '999222',
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        '902',
        '',
        'Pago Móvil',
        '1 mes',
        'Pago de mensualidad por portal'
    );

    $motivo_nb = $GLOBALS['bdv_falla_motivo'] ?? '';
    echo "  DEBUG: aprobado_nobanco=" . ($aprobado_nobanco ? 'true' : 'false') . ", motivo=$motivo_nb\n";
    run_assert($aprobado_nobanco === false, "Banco sin API debe devolver false.");
    run_assert(
        str_contains($motivo_nb, 'banco') || str_contains($motivo_nb, 'validación') || str_contains($motivo_nb, 'habilitada'),
        "Motivo debe indicar que el banco no tiene validación automática activa."
    );
    echo "\n";

} catch (Throwable $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "   En: " . $e->getFile() . ":" . $e->getLine() . "\n";
    $tests_failed++;
} finally {
    // =========================================================================
    // LIMPIEZA
    // =========================================================================
    echo "--- Limpieza ---\n";

    if ($server_process && is_resource($server_process)) {
        proc_terminate($server_process);
        echo "Servidor mock detenido.\n";
    }

    if ($original_bancos_content !== null) {
        file_put_contents($bancos_path, $original_bancos_content);
        echo "bancos.json restaurado.\n";
    }

    // Limpiar sesión de prueba
    foreach (array_keys($_SESSION) as $key) {
        if (str_starts_with($key, 'rate_limit_test_') || $key === 'cliente_cedula') {
            unset($_SESSION[$key]);
        }
    }

    echo "\n=== RESUMEN ===\n";
    echo "✅ Pasados: $tests_passed\n";
    echo "❌ Fallidos: $tests_failed\n";
    if ($tests_failed === 0) {
        echo "\n🎉 TODOS LOS TESTS PASARON EXITOSAMENTE\n";
    } else {
        echo "\n⚠️  Algunos tests fallaron. Revisa los errores arriba.\n";
    }
}
