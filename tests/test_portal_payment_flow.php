<?php
/**
 * Test: Portal Payment Flow and Security Measures
 * 
 * Verifies:
 * 1. CSRF Token Generation and Verification.
 * 2. IP-based Rate Limiting (Blocking & Logging).
 * 3. Input Validation (Reference numbers).
 * 4. Audit Trail Event Logging.
 * 5. Payment Auto-Verification (Integrated with local Mock BDV API):
 *    - Successful match (Auto-approved).
 *    - Reference mismatch (Pending + Correct reason).
 *    - Amount mismatch (Pending + Correct reason).
 */

require_once __DIR__ . '/../paginas/conexion.php';
require_once __DIR__ . '/../portal/security_helper.php';
require_once __DIR__ . '/../portal/bdv_autoverify_helper.php';

echo "=== INICIANDO PRUEBAS: FLUJO DE PAGO Y SEGURIDAD DEL PORTAL ===\n\n";

$original_bancos_content = null;
$bancos_path = __DIR__ . '/../paginas/principal/bancos.json';
$server_process = null;
$server_pipes = [];

// Identificadores de prueba
$test_cedula = "V88888888";
$test_contrato_id = null;
$test_reporte_ids = [];

// Helper para aserciones
function assert_test($condition, $message) {
    if ($condition) {
        echo "✅ [OK] $message\n";
    } else {
        echo "❌ [FALLO] $message\n";
        throw new Exception("Fallo en la prueba: $message");
    }
}

try {
    // Inicializar tablas de seguridad
    _init_security_tables($conn);

    // -------------------------------------------------------------------------
    // FASE 0: CONFIGURACIÓN DE ENTORNO MOCK (PHP Built-in Server + bancos.json)
    // -------------------------------------------------------------------------
    echo "Fase 0: Levantando servidor local y configurando endpoints mock...\n";
    
    // Iniciar servidor local en el puerto 8543
    $cmd = "php -S 127.0.0.1:8543 -t \"" . realpath(__DIR__ . '/../') . "\"";
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    $server_process = proc_open($cmd, $descriptors, $server_pipes);
    if (!is_resource($server_process)) {
        throw new Exception("No se pudo iniciar el servidor PHP interno.");
    }
    
    // Esperar a que el servidor esté activo
    sleep(1);
    
    // Copiar copia de seguridad de bancos.json
    if (file_exists($bancos_path)) {
        $original_bancos_content = file_get_contents($bancos_path);
    }
    
    // Crear configuración temporal para apuntar al mock de la API del banco
    $mock_bancos = [
        [
            "id_banco" => "9",
            "nombre_banco" => "Banco de Venezuela (Pago Móvil) MOCK",
            "numero_cuenta" => "04247377954",
            "cedula_propietario" => "J 408882540",
            "nombre_propietario" => "SITELCO C.A.",
            "metodos_pago" => ["Pago Móvil"],
            "activo" => true,
            "api_config" => [
                "habilitada" => true,
                "tipo" => "bdv",
                "api_key" => "MOCK_KEY_123456",
                "cuenta" => "01020589150000001371",
                "titular" => "SITELCO C.A.",
                "endpoint" => "http://127.0.0.1:8543/tests/mock_bdv_api.php"
            ]
        ]
    ];
    file_put_contents($bancos_path, json_encode($mock_bancos, JSON_PRETTY_PRINT));
    echo "✅ Endpoint de BDV redireccionado a local mock API.\n\n";

    // -------------------------------------------------------------------------
    // FASE 1: PRUEBA DE CONTROL CSRF
    // -------------------------------------------------------------------------
    echo "Fase 1: Probando protección CSRF...\n";
    
    // Generar token
    $token = generate_csrf_token();
    assert_test(!empty($token), "El token CSRF no debe estar vacío.");
    
    // Validar token correcto
    assert_test(verify_csrf_token($token), "El token generado debe ser validado como CORRECTO.");
    
    // Validar token incorrecto
    assert_test(!verify_csrf_token("invalid_token_xyz"), "Un token arbitrario debe ser validado como INCORRECTO.");
    assert_test(!verify_csrf_token(null), "Un token nulo debe ser validado como INCORRECTO.");
    echo "\n";

    // -------------------------------------------------------------------------
    // FASE 2: PRUEBA DE RATE LIMITING (LIMITACIÓN DE VELOCIDAD)
    // -------------------------------------------------------------------------
    echo "Fase 2: Probando control de Rate Limiting...\n";
    
    $ip = $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $action = "test_rate_limit_action";
    
    // Limpiar base de datos para asegurar un estado limpio
    $conn->query("DELETE FROM security_rate_limits WHERE ip_address = '$ip' AND action_name = '$action'");
    
    // Probar hits permitidos (Límite: 3 hits en 60 segundos)
    assert_test(check_rate_limit($action, 3, 60), "Hit #1 debe ser PERMITIDO.");
    assert_test(check_rate_limit($action, 3, 60), "Hit #2 debe ser PERMITIDO.");
    assert_test(check_rate_limit($action, 3, 60), "Hit #3 debe ser PERMITIDO.");
    
    // Hit excedente: debe bloquear
    assert_test(!check_rate_limit($action, 3, 60), "Hit #4 (exceso) debe ser BLOQUEADO.");
    
    // Verificar si se registró en la bitácora de seguridad el bloqueo
    $res_log = $conn->query("SELECT details FROM portal_security_logs WHERE event_type = 'RATE_LIMIT_EXCEEDED' ORDER BY id_log DESC LIMIT 1");
    assert_test($res_log && $res_log->num_rows > 0, "Debe registrarse el bloqueo en la bitácora de seguridad.");
    
    // Limpiar tabla rate limits después de probar
    $conn->query("DELETE FROM security_rate_limits WHERE ip_address = '$ip' AND action_name = '$action'");
    echo "\n";

    // -------------------------------------------------------------------------
    // FASE 3: CONFIGURACIÓN DE CONTRATO DE PRUEBA Y AUTODEUDOR
    // -------------------------------------------------------------------------
    echo "Fase 3: Registrando contrato y deuda de prueba...\n";
    
    $sql_ins = "INSERT INTO contratos (
        cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan, vendedor_texto,
        direccion, fecha_instalacion, estado, monto_instalacion, monto_pagar, monto_pagado, 
        instalador, tipo_conexion, mac_onu
    ) VALUES (?, 'CONTRATO PRUEBA PORTAL', 1, 1, 1, 25.00, 'MOCK VEND', 'DIR MOCK', NOW(), 'ACTIVO', 100.00, 100.00, 0.00, 'INST1', 'FTTH', 'MAC9876')";
    
    $stmt = $conn->prepare($sql_ins);
    $stmt->bind_param("s", $test_cedula);
    $stmt->execute();
    $test_contrato_id = $conn->insert_id;
    $stmt->close();
    
    // Crear cliente deudor
    $conn->query("INSERT INTO clientes_deudores (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado) 
                  VALUES ($test_contrato_id, 100.00, 0.00, 100.00, 'PENDIENTE')");
    
    assert_test($test_contrato_id > 0, "Contrato de prueba registrado con ID $test_contrato_id.");
    echo "\n";

    // -------------------------------------------------------------------------
    // FASE 4: INTEGRACIÓN AUTO-APROBACIÓN EXITOSA (BDV MATCH)
    // -------------------------------------------------------------------------
    echo "Fase 4: Probando auto-aprobación exitosa con la API del banco...\n";
    
    // Insertar reporte de pago coincidente con mock: Ref 999222, monto 1.00 Bs
    // Para 1.00 Bs a tasa 36.00 (por ejemplo), el monto USD = 1.00 / 36.00 = 0.0277...
    $tasa = 36.00;
    $monto_bs = 1.00;
    $monto_usd = $monto_bs / $tasa;
    
    $sql_rep = "INSERT INTO pagos_reportados (
        cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
        id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
        meses_pagados, concepto, capture_path, id_contrato_asociado, estado
    ) VALUES (?, 'TITULAR PRUEBA', '04121234567', CURRENT_DATE, 'Pago Móvil', 9, '999222', ?, ?, ?, '1 mes', 'Pago mensual', 'capture.png', ?, 'PENDIENTE')";
    
    $stmt_rep = $conn->prepare($sql_rep);
    $stmt_rep->bind_param("sdddi", $test_cedula, $monto_bs, $monto_usd, $tasa, $test_contrato_id);
    $stmt_rep->execute();
    $reporte_id_ok = $conn->insert_id;
    $test_reporte_ids[] = $reporte_id_ok;
    $stmt_rep->close();
    
    // Ejecutar autoverificación
    $aprobado = verificar_y_aprobar_pago_bdv(
        $conn,
        9, // Banco destino BDV
        '999222',
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $reporte_id_ok,
        'capture.png',
        '1 mes',
        'Pago mensual'
    );
    
    echo "DEBUG: aprobado=" . ($aprobado ? "true" : "false") . ", motivo=" . ($GLOBALS['bdv_falla_motivo'] ?? 'null') . "\n";
    assert_test($aprobado === true, "La auto-aprobación del pago debe ser EXITOSA.");
    
    // Verificar que el reporte se cambió a APROBADO en la base de datos
    $rep_res = $conn->query("SELECT estado FROM pagos_reportados WHERE id_reporte = $reporte_id_ok")->fetch_assoc();
    assert_test($rep_res['estado'] === 'APROBADO', "El reporte de pago debe marcarse como APROBADO en la BD.");
    
    // Verificar cuentas_por_cobrar
    $cxc_res = $conn->query("SELECT estado, referencia_pago FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id ORDER BY id_cobro DESC LIMIT 1")->fetch_assoc();
    assert_test($cxc_res['estado'] === 'PAGADO' && $cxc_res['referencia_pago'] === '999222', "Debe registrarse el cobro como PAGADO en cuentas_por_cobrar.");
    
    // Verificar logs de seguridad
    $logs_res = $conn->query("SELECT event_type FROM portal_security_logs WHERE user_identifier = '$test_cedula' AND event_type = 'PAYMENT_AUTO_APPROVED'")->fetch_assoc();
    assert_test(!empty($logs_res), "Debe registrarse el evento 'PAYMENT_AUTO_APPROVED' en la bitácora.");
    echo "\n";

    // -------------------------------------------------------------------------
    // FASE 4B: INTEGRACIÓN AUTO-APROBACIÓN EXITOSA CON REFERENCIA PARCIAL (BDV MATCH ÚLTIMOS DÍGITOS)
    // -------------------------------------------------------------------------
    echo "Fase 4b: Probando auto-aprobación exitosa con referencia parcial (últimos dígitos del banco)...\n";
    
    // Banco tiene referencia: 2026060400999444. Cliente reporta la referencia parcial '999444' y el monto 10.00 Bs.
    $monto_bs_parcial = 10.00;
    $monto_usd_parcial = $monto_bs_parcial / $tasa;
    
    $sql_rep_parcial = "INSERT INTO pagos_reportados (
        cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
        id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
        meses_pagados, concepto, capture_path, id_contrato_asociado, estado
    ) VALUES (?, 'TITULAR PRUEBA', '04121234567', CURRENT_DATE, 'Pago Móvil', 9, '999444', ?, ?, ?, '1 mes', 'Pago mensual', 'capture_parcial.png', ?, 'PENDIENTE')";
    
    $stmt_rep_parcial = $conn->prepare($sql_rep_parcial);
    $stmt_rep_parcial->bind_param("sdddi", $test_cedula, $monto_bs_parcial, $monto_usd_parcial, $tasa, $test_contrato_id);
    $stmt_rep_parcial->execute();
    $reporte_id_parcial = $conn->insert_id;
    $test_reporte_ids[] = $reporte_id_parcial;
    $stmt_rep_parcial->close();
    
    // Ejecutar autoverificación con la referencia parcial '999444'
    $aprobado_parcial = verificar_y_aprobar_pago_bdv(
        $conn,
        9, // Banco destino BDV
        '999444',
        $monto_usd_parcial,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $reporte_id_parcial,
        'capture_parcial.png',
        '1 mes',
        'Pago mensual'
    );
    
    echo "DEBUG: aprobado_parcial=" . ($aprobado_parcial ? "true" : "false") . ", motivo=" . ($GLOBALS['bdv_falla_motivo'] ?? 'null') . "\n";
    assert_test($aprobado_parcial === true, "La auto-aprobación con referencia parcial de BDV debe ser EXITOSA.");
    
    // Verificar que el reporte se cambió a APROBADO en la base de datos
    $rep_res_parcial = $conn->query("SELECT estado FROM pagos_reportados WHERE id_reporte = $reporte_id_parcial")->fetch_assoc();
    assert_test($rep_res_parcial['estado'] === 'APROBADO', "El reporte con referencia parcial debe marcarse como APROBADO en la BD.");
    echo "\n";

    // Banco tiene referencia: 2026060400999444. Cliente reporta la referencia larga '2026060400999444' (se truncará a los últimos 8) y el monto 10.00 Bs.
    $sql_rep_larga = "INSERT INTO pagos_reportados (
        cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
        id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
        meses_pagados, concepto, capture_path, id_contrato_asociado, estado
    ) VALUES (?, 'TITULAR PRUEBA', '04121234567', CURRENT_DATE, 'Pago Móvil', 9, '2026060400999444', ?, ?, ?, '1 mes', 'Pago mensual', 'capture_larga.png', ?, 'PENDIENTE')";
    
    $stmt_rep_larga = $conn->prepare($sql_rep_larga);
    $stmt_rep_larga->bind_param("sdddi", $test_cedula, $monto_bs_parcial, $monto_usd_parcial, $tasa, $test_contrato_id);
    $stmt_rep_larga->execute();
    $reporte_id_larga = $conn->insert_id;
    $test_reporte_ids[] = $reporte_id_larga;
    $stmt_rep_larga->close();
    
    // Ejecutar autoverificación con la referencia larga '2026060400999444'
    $aprobado_larga = verificar_y_aprobar_pago_bdv(
        $conn,
        9, // Banco destino BDV
        '2026060400999444',
        $monto_usd_parcial,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $reporte_id_larga,
        'capture_larga.png',
        '1 mes',
        'Pago mensual'
    );
    
    echo "DEBUG: aprobado_larga=" . ($aprobado_larga ? "true" : "false") . ", motivo=" . ($GLOBALS['bdv_falla_motivo'] ?? 'null') . "\n";
    assert_test($aprobado_larga === true, "La auto-aprobación con referencia larga (>8 dígitos) de BDV debe ser EXITOSA.");
    
    // Verificar que el reporte se cambió a APROBADO en la base de datos
    $rep_res_larga = $conn->query("SELECT estado FROM pagos_reportados WHERE id_reporte = $reporte_id_larga")->fetch_assoc();
    assert_test($rep_res_larga['estado'] === 'APROBADO', "El reporte con referencia larga debe marcarse como APROBADO en la BD.");
    echo "\n";

    // -------------------------------------------------------------------------
    // FASE 5: PRUEBA DE FALLO: REFERENCIA INEXISTENTE EN BANCO
    // -------------------------------------------------------------------------
    echo "Fase 5: Probando reporte con referencia inexistente en el banco...\n";
    
    // Insertar reporte con referencia errónea '999999'
    $stmt_rep = $conn->prepare($sql_rep);
    $referencia_erronea = '999999';
    $stmt_rep->bind_param("sdddi", $test_cedula, $monto_bs, $monto_usd, $tasa, $test_contrato_id);
    $stmt_rep->execute();
    $reporte_id_err = $conn->insert_id;
    $test_reporte_ids[] = $reporte_id_err;
    $stmt_rep->close();
    
    // Ejecutar autoverificación
    $aprobado_err = verificar_y_aprobar_pago_bdv(
        $conn,
        9,
        $referencia_erronea,
        $monto_usd,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $reporte_id_err,
        'capture2.png',
        '1 mes',
        'Pago mensual'
    );
    
    assert_test($aprobado_err === false, "La autoverificación debe ser RECHAZADA.");
    assert_test($GLOBALS['bdv_falla_motivo'] === "La referencia no coincide con los registros del banco.", "El motivo del rechazo debe ser 'La referencia no coincide con los registros del banco.'");
    
    // Guardar el motivo en base de datos como lo haría procesar_pago_cliente.php
    $conn->query("UPDATE pagos_reportados SET motivo_rechazo = '" . $GLOBALS['bdv_falla_motivo'] . "' WHERE id_reporte = $reporte_id_err");
    
    // Verificar que quede PENDIENTE
    $rep_res_err = $conn->query("SELECT estado, motivo_rechazo FROM pagos_reportados WHERE id_reporte = $reporte_id_err")->fetch_assoc();
    assert_test($rep_res_err['estado'] === 'PENDIENTE', "El reporte debe quedar en estado PENDIENTE.");
    assert_test($rep_res_err['motivo_rechazo'] === "La referencia no coincide con los registros del banco.", "El motivo debe guardarse en la columna motivo_rechazo.");
    echo "\n";

    // -------------------------------------------------------------------------
    // FASE 6: PRUEBA DE FALLO: MONTO DISCREPANTE
    // -------------------------------------------------------------------------
    echo "Fase 6: Probando reporte con monto que no coincide con el banco...\n";
    
    // Insertar reporte con referencia real '999333' (monto banco: 5.50 Bs), pero reportamos un monto incorrecto de 10.00 Bs
    $monto_bs_incorrecto = 10.00;
    $monto_usd_incorrecto = $monto_bs_incorrecto / $tasa;
    
    $stmt_rep = $conn->prepare($sql_rep);
    $stmt_rep->bind_param("sdddi", $test_cedula, $monto_bs_incorrecto, $monto_usd_incorrecto, $tasa, $test_contrato_id);
    $stmt_rep->execute();
    $reporte_id_monto = $conn->insert_id;
    $test_reporte_ids[] = $reporte_id_monto;
    $stmt_rep->close();
    
    // Ejecutar autoverificación
    $aprobado_monto = verificar_y_aprobar_pago_bdv(
        $conn,
        9,
        '999333',
        $monto_usd_incorrecto,
        $tasa,
        date('Y-m-d'),
        $test_contrato_id,
        $reporte_id_monto,
        'capture3.png',
        '1 mes',
        'Pago mensual'
    );
    
    assert_test($aprobado_monto === false, "La autoverificación debe ser RECHAZADA.");
    assert_test($GLOBALS['bdv_falla_motivo'] === "El monto ingresado no coincide con el registrado en el banco.", "El motivo del rechazo debe ser 'El monto ingresado no coincide con el registrado en el banco.'");
    
    // Guardar el motivo en base de datos
    $conn->query("UPDATE pagos_reportados SET motivo_rechazo = '" . $GLOBALS['bdv_falla_motivo'] . "' WHERE id_reporte = $reporte_id_monto");
    
    // Verificar que quede PENDIENTE
    $rep_res_monto = $conn->query("SELECT estado, motivo_rechazo FROM pagos_reportados WHERE id_reporte = $reporte_id_monto")->fetch_assoc();
    assert_test($rep_res_monto['estado'] === 'PENDIENTE', "El reporte debe quedar en estado PENDIENTE.");
    assert_test($rep_res_monto['motivo_rechazo'] === "El monto ingresado no coincide con el registrado en el banco.", "El motivo de discordancia de monto debe guardarse.");
    echo "\n";

    echo "=== TODOS LOS TESTS PASARON EXITOSAMENTE ===\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
} finally {
    // -------------------------------------------------------------------------
    // FASE 7: LIMPIEZA Y RESTAURACIÓN
    // -------------------------------------------------------------------------
    echo "\nFase 7: Limpiando base de datos y restaurando configuraciones...\n";
    
    // Terminar el servidor local
    if ($server_process) {
        proc_terminate($server_process);
        echo "Servidor PHP interno cerrado.\n";
    }
    
    // Restaurar bancos.json original
    if ($original_bancos_content !== null) {
        file_put_contents($bancos_path, $original_bancos_content);
        echo "bancos.json original restaurado.\n";
    }
    
    // Limpieza de datos en BD
    if ($test_contrato_id) {
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id)");
        $conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $test_contrato_id");
        $conn->query("DELETE FROM contratos WHERE id = $test_contrato_id");
    }
    
    if (!empty($test_reporte_ids)) {
        $ids_str = implode(',', $test_reporte_ids);
        $conn->query("DELETE FROM pagos_reportados WHERE id_reporte IN ($ids_str)");
    }
    
    $conn->query("DELETE FROM portal_security_logs WHERE user_identifier = '$test_cedula'");
    
    $conn->close();
    echo "Limpieza de base de datos finalizada.\n";
}
?>
