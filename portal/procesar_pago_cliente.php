<?php
// portal/procesar_pago_cliente.php
require_once 'security_helper.php';
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$cedula           = $_SESSION['cliente_cedula'];
$nombre           = $_SESSION['cliente_nombre'];
$fecha_pago       = $_POST['fecha_pago'] ?? date('Y-m-d');
$metodo_pago      = $_POST['metodo_pago'] ?? '';
$id_banco_destino = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : null;
$referencia       = isset($_POST['referencia']) ? trim($_POST['referencia']) : '';
$id_contrato_asociado = isset($_POST['id_contrato']) ? trim($_POST['id_contrato']) : null;
$invoice_ids      = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : [];
$invoice_total    = isset($_POST['invoice_total']) ? floatval($_POST['invoice_total']) : 0;
$invoice_fecha_emision = isset($_POST['invoice_fecha_emision']) ? trim($_POST['invoice_fecha_emision']) : '';

$redirect_url = empty($id_contrato_asociado) ? 'dashboard.php' : 'dashboard.php';

// 1. Rate Limiting
if (!check_rate_limit('payment_submit', 5, 600)) {
    log_security_event('RATE_LIMIT_EXCEEDED', 'Intento de reporte de pago bloqueado por exceso de peticiones', $cedula);
    $_SESSION['pago_err'] = "Has enviado demasiados reportes. Espera unos minutos.";
    header('Location: ' . $redirect_url);
    exit;
}

// 2. CSRF
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!verify_csrf_token($csrf_token)) {
    log_security_event('CSRF_VIOLATION', 'Fallo CSRF al reportar pago', $cedula);
    $_SESSION['pago_err'] = "Petición inválida. Recarga la página.";
    header('Location: ' . $redirect_url);
    exit;
}

// 3. Validar referencia (solo d├¡gitos, 6-10)
$referencia_clean = preg_replace('/\D/', '', $referencia);
if (empty($referencia_clean) || strlen($referencia_clean) < 6 || strlen($referencia_clean) > 15) {
    $_SESSION['pago_err'] = "La referencia debe tener entre 6 y 15 d\u00edgitos.";
    header('Location: ' . $redirect_url);
    exit;
}
$referencia = $referencia_clean;

// 3b. Verificar referencia duplicada en BD local
require_once __DIR__ . '/referencia_helper.php';
$refInfo = getReferenciaInfo($referencia);
if ($refInfo) {
    $facturas = $refInfo['facturas'] ? ' #' . $refInfo['facturas'] : '';
    $_SESSION['pago_err'] = "La referencia {$referencia} ya fue utilizada en la Factura{$facturas} del día {$refInfo['fecha_pago']}, por el cliente {$refInfo['cliente']}.";
    header('Location: ' . $redirect_url);
    exit;
}

// 4. Validar metodo y banco
if (empty($metodo_pago) || empty($id_banco_destino)) {
    $_SESSION['pago_err'] = "Método de pago y banco son obligatorios.";
    header('Location: ' . $redirect_url);
    exit;
}

// 5. Determinar monto
$tasa_dolar = isset($_POST['tasa_dolar']) ? floatval($_POST['tasa_dolar']) : 0;
if ($tasa_dolar <= 0) $tasa_dolar = 1.0;

// Usar monto_usd_real si viene de verificacion BDV, o monto_usd normal (Zelle manual)
$monto_usd = isset($_POST['monto_usd_real']) ? floatval($_POST['monto_usd_real']) : (isset($_POST['monto_usd']) ? floatval($_POST['monto_usd']) : 0);

if ($monto_usd <= 0) {
    $_SESSION['pago_err'] = "El monto debe ser mayor a 0.";
    header('Location: ' . $redirect_url);
    exit;
}

$monto_bs = round($monto_usd * $tasa_dolar, 2);

// 6. Manejo de archivo (capture)
$capture_path = '';
$upload_dir = '../uploads/pagos/';
if (isset($_FILES['capture_pago']) && $_FILES['capture_pago']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $file_name = uniqid('portal_') . '_' . basename($_FILES['capture_pago']['name']);
    $file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
    $target_file = $upload_dir . $file_name;
    $check = getimagesize($_FILES['capture_pago']['tmp_name']);
    if ($check !== false) {
        if (move_uploaded_file($_FILES['capture_pago']['tmp_name'], $target_file)) {
            $capture_path = 'uploads/pagos/' . $file_name;
        } else {
            $_SESSION['pago_err'] = "Error al guardar el comprobante.";
            header('Location: ' . $redirect_url);
            exit;
        }
    } else {
        $_SESSION['pago_err'] = "El archivo no es una imagen válida.";
        header('Location: ' . $redirect_url);
        exit;
    }
}

// 7. Procesar pago en WispHub
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/Services/WispHubClient.php';
    $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
    if (DEV_MODE && $cedula === TEST_USER_CEDULA) {
        require_once __DIR__ . '/../src/Services/WispHubDevModeClient.php';
        $wispClient = new \Services\WispHubDevModeClient($wispConfig);
    } else {
        $wispClient = new \Services\WispHubClient($wispConfig);
    }

    $wispDate = date('Y-m-d H:i', strtotime($fecha_pago));

    // Si es Zelle (sin verificacion), o si viene del nuevo flujo BDV ya verificado,
    // registrar directo en WispHub
    $es_zelle = ($metodo_pago === 'Zelle');
    $verificacion_data = isset($_POST['verificacion_data']) ? json_decode($_POST['verificacion_data'], true) : null;

    if ($es_zelle || $verificacion_data) {
        // --- QUEMAR REFERENCIA INMEDIATAMENTE SI FUE VERIFICADA POR API BANCARIA ---
        if ($verificacion_data) {
            require_once __DIR__ . '/wisp_helper.php';
            $wispData = wisp_get_cached_data($wispClient, $id_contrato_asociado);
            $c_perfil = $wispData['profile'] ?? [];
            $ipServicio = $c_perfil['ip'] ?? '';
            $zona = $c_perfil['zona']['nombre'] ?? '';

            $accion_pre = $verificacion_data['tipo_pago'] ?? null;
            if ($accion_pre === null && $invoice_total > 0) {
                $diff = round($monto_usd - $invoice_total, 2);
                $accion_pre = $diff < 0 ? 'abono' : ($diff == 0 ? 'completo' : 'exceso');
            }

            $monto_banco_bs = isset($verificacion_data['movimiento']['importe_bs']) ? floatval($verificacion_data['movimiento']['importe_bs']) : null;
            $fecha_banco = $verificacion_data['movimiento']['fecha'] ?? null;
            $banco_descripcion = isset($verificacion_data['movimiento']['observacion']) ? trim($verificacion_data['movimiento']['observacion']) : null;

            require_once __DIR__ . '/referencia_helper.php';
            // Guardar pago en BD local INMEDIATAMENTE
            $db_ok = guardarPago(
                $nombre, $ipServicio, $fecha_pago, $zona, $monto_usd, $metodo_pago, $referencia, 
                $invoice_total, $accion_pre, $id_contrato_asociado, $id_banco_destino, 
                implode(',', $invoice_ids), $monto_banco_bs, $fecha_banco, $banco_descripcion
            );
            if (!$db_ok) { error_log('[procesar_pago] pre-burn guardarPago falló para ref: ' . $referencia); }
        }
        // ---------------------------------------------------------------------------

        // IMPORTANTE: Si es pago parcial, calculamos la promesa pero la aplicamos DESPUÉS de registrar el abono.
        // WispHub marca la factura original como "Pagada" al recibir el abono y genera una nueva de "Saldo Pendiente".
        $shouldCreatePromise = false;
        $promiseDetails = [];
        if ($monto_usd < $invoice_total && $invoice_total > 0 && !empty($invoice_ids)) {
            $firstInvoiceId = (int)$invoice_ids[0];
            $invDetail = $wispClient->getInvoiceDetail((string)$firstInvoiceId);
            $fechaEmi = $invDetail['fecha_emision'] ?? '';
            $fechaVenc = $invDetail['fecha_vencimiento'] ?? '';
            $totalFactura = floatval($invDetail['total'] ?? $invoice_total);
            if ($totalFactura > 0 && $fechaEmi && $fechaVenc) {
                $proporcion = $monto_usd / $totalFactura;
                $diasExtra = round(30 * $proporcion) + 1; // +1 día de gracia
                $fechaLimite = date('Y-m-d', strtotime($fechaVenc . " + $diasExtra days"));
            } else {
                $fechaLimite = !empty($fechaVenc) ? date('Y-m-d', strtotime($fechaVenc)) : date('Y-m-d', strtotime('+30 days'));
            }
            $saldoRestante = round($invoice_total - $monto_usd, 2);
            $shouldCreatePromise = true;
            $promiseDetails = [
                'fecha_limite' => $fechaLimite,
                'saldo' => $saldoRestante
            ];
        }

        // Nuevo flujo: registrar directo con las facturas seleccionadas
        $wispResult = $wispClient->registerPaymentAndActivate(
            $id_contrato_asociado,
            $monto_usd,
            $referencia,
            $wispDate,
            \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
            false,
            '',
            $invoice_ids  // Solo pagar las facturas seleccionadas
        );

        $wispStatus = $wispResult['status'] ?? 200;

        // Calcular fecha de promesa para abonos parciales y guardarla en la BD local.
        // WispHub rechaza promesas en facturas que ya marcó como "Pagada" (HTTP 400),
        // por lo que usamos nuestra BD local como fuente de verdad para la promesa.
        $fechaPromesaLocal = null;
        if ($shouldCreatePromise) {
            $fechaPromesaLocal = $promiseDetails['fecha_limite'];
            $GLOBALS['pending_promise_msg'] = " Promesa de pago registrada: tienes hasta el "
                . date('d/m/Y', strtotime($fechaPromesaLocal))
                . " para pagar los $" . number_format($promiseDetails['saldo'], 2) . " USD restantes.";
        }
    } else {
        // Flujo legacy (desde bdv_autoverify_helper.php del wizard viejo)
        require_once __DIR__ . '/bdv_autoverify_helper.php';
        $auto_aprobado = verificar_y_aprobar_pago_bdv(
            $id_banco_destino,
            $referencia,
            $monto_usd,
            $tasa_dolar,
            $fecha_pago,
            $id_contrato_asociado ?? '',
            $capture_path,
            $metodo_pago,
            '',
            'Pago de mensualidad por portal'
        );

        if ($auto_aprobado) {
            $_SESSION['pago_msg'] = "¡Tu pago fue verificado y registrado! Ref: $referencia.";
            // Guardar pago en BD local
            require_once __DIR__ . '/referencia_helper.php';
            $db_ok = guardarPago(
                $nombre, '', $fecha_pago, '', $monto_usd, $metodo_pago, $referencia, 
                $invoice_total, $accion ?? null, $id_contrato_asociado ?? '', $id_banco_destino, 
                implode(',', $invoice_ids), null, null, null // No tenemos los datos crudos en el legacy
            );
            if (!$db_ok) { error_log('[procesar_pago] guardarPago falló en legacy para ref: ' . $referencia); }
        } else {
            $razon = $GLOBALS['bdv_falla_motivo'] ?? 'Error desconocido.';
            $_SESSION['pago_err'] = "Error: $razon";
            if (!empty($capture_path) && file_exists(__DIR__ . '/../' . $capture_path)) {
                @unlink(__DIR__ . '/../' . $capture_path);
            }
        }
        header('Location: ' . $redirect_url);
        exit;
    }

    // Verificar resultado del registro en WispHub
    $wispSuccess = $wispResult && in_array($wispResult['status'] ?? 0, [200, 201]);
    $bankVerified = !empty($verificacion_data);

    if ($wispSuccess || $bankVerified) {
        // Construir mensaje detallado segun la distribucion
        $msg_parts = ["Tu pago fue verificado y registrado exitosamente."];

        if ($wispSuccess) {
            $amount_applied = floatval($wispResult['amount_applied'] ?? $monto_usd);
            $amount_unused  = floatval($wispResult['amount_unused'] ?? 0);
            $pagos_count    = count($wispResult['payments_registered'] ?? []);

            // CORRECCIÓN: WispHub a veces devuelve amount_applied=0 cuando el cliente ya hizo
            // un abono previo y la factura no aparece como "pendiente" en WispHub.
            // En ese caso calculamos manualmente: aplicado = min(pagado, deuda_pendiente)
            if ($amount_applied == 0 && $monto_usd > 0 && $invoice_total > 0) {
                $amount_applied = min($monto_usd, $invoice_total);
                $amount_unused  = max(0, round($monto_usd - $invoice_total, 2));
                // Simular que sí hubo un registro (para no mostrar el aviso de "0 recibos")
                $pagos_count = !empty($invoice_ids) ? 1 : 0;
                error_log("[procesar_pago] WispHub devolvió amount_applied=0 para Ref: $referencia — corrigiendo con invoice_total=$invoice_total");
            }

            if ($pagos_count > 0 && $amount_applied > 0) {
                $msg_parts[] = "Se aplicaron <strong>$" . number_format($amount_applied, 2) . " USD</strong> a $pagos_count recibo(s).";
            }

            if ($amount_unused > 0.005) {
                $saldo_favor_real = round($amount_unused, 2);
                $msg_parts[] = "Te queda un <strong>SALDO A FAVOR de $" . number_format($saldo_favor_real, 2) . " USD</strong> para tu pr&oacute;ximo recibo.";
            }

            if ($amount_applied > 0 && $amount_applied < $monto_usd && $amount_unused <= 0.005) {
                $msg_parts[] = "Se aplic&oacute; <strong>$" . number_format($amount_applied, 2) . " USD</strong> como abono a tu deuda.";
            }

            // Aviso solo si realmente no se pudo pagar ningún recibo
            $selected_count = count($invoice_ids);
            if ($selected_count > 0 && $pagos_count == 0) {
                $msg_parts[] = "Nota: el pago quedó registrado manualmente. Contacta a soporte con tu referencia si no ves el cambio reflejado.";
            }
        } else {
            // WispHub falló pero el banco aprobó
            $errorMsg = $wispResult['error'] ?? json_encode($wispResult['data'] ?? 'Error desconocido');
            error_log("[procesar_pago_cliente] WispHub rechazó pero Banco aprobó (Ref: $referencia): " . $errorMsg);
            $msg_parts[] = "<br><strong style='color:#dc2626;'>Aviso:</strong> El banco aprobó el pago, pero WispHub no pudo aplicarlo a tu factura automáticamente. Por favor contacta a soporte con tu número de referencia. (Detalle: " . htmlspecialchars($errorMsg) . ")";
        }

        // Agregar mensaje de promesa si existe
        if (!empty($GLOBALS['pending_promise_msg'])) {
            $msg_parts[] = $GLOBALS['pending_promise_msg'];
        }

        $msg_parts[] = "Referencia: <strong>$referencia</strong>.";
        $_SESSION['pago_msg'] = implode(' ', $msg_parts);

        // Limpiar cache de WispHub para que dashboard refresque datos
        require_once __DIR__ . '/wisp_helper.php';
        wisp_clear_cache($id_contrato_asociado);

        // Obtener datos del perfil (IP, Zona) del cache renovado
        $wispData = wisp_get_cached_data($wispClient, $id_contrato_asociado);
        $c_perfil = $wispData['profile'] ?? [];
        $ipServicio = $c_perfil['ip'] ?? '';
        $zona = $c_perfil['zona']['nombre'] ?? '';

        // Determinar accion (completo/abono/exceso)
        $accion = $verificacion_data['tipo_pago'] ?? null;
        if ($accion === null && $invoice_total > 0) {
            $diff = round($monto_usd - $invoice_total, 2);
            $accion = $diff < 0 ? 'abono' : ($diff == 0 ? 'completo' : 'exceso');
        }

        // Calcular cobertura para abonos
        $cobertura_hasta = '';
        if ($accion === 'abono' && !empty($invoice_ids)) {
            $firstInvoiceId = (int)$invoice_ids[0];
            $invDetail = $wispClient->getInvoiceDetail((string)$firstInvoiceId);
            $fechaEmi = $invDetail['fecha_emision'] ?? '';
            $fechaVenc = $invDetail['fecha_vencimiento'] ?? '';
            $totalFactura = floatval($invDetail['total'] ?? $invoice_total);
            if ($totalFactura > 0 && $fechaEmi && $fechaVenc && $invoice_total > 0) {
                $proporcion = $monto_usd / $invoice_total;
                $diasExtra = round(30 * $proporcion);
                $cobertura_hasta = date('d/m/Y', strtotime($fechaVenc . ' + ' . ($diasExtra + 1) . ' days'));
            }
        }

        // Guardar pago_data en sesión para el modal de resultado
        $_SESSION['pago_data'] = [
            'referencia' => $referencia,
            'monto_usd'  => $monto_usd,
            'monto_bs'   => $monto_bs,
            'service_id' => $id_contrato_asociado,
            'accion'     => $accion,
            'cobertura_hasta' => $cobertura_hasta,
            'invoice_total'   => $invoice_total,
        ];
        unset($_SESSION['pago_err']);

        unset($_SESSION['pago_err']);

        $monto_banco_bs = isset($verificacion_data['movimiento']['importe_bs']) ? floatval($verificacion_data['movimiento']['importe_bs']) : null;
        $fecha_banco = $verificacion_data['movimiento']['fecha'] ?? null;
        $banco_descripcion = isset($verificacion_data['movimiento']['observacion']) ? trim($verificacion_data['movimiento']['observacion']) : null;

        // Guardar pago en BD local (upsert: si ya fue insertado por pre-burn, se actualiza)
        require_once __DIR__ . '/referencia_helper.php';
        $db_ok = guardarPago(
            $nombre, $ipServicio, $fecha_pago, $zona, $monto_usd, $metodo_pago, $referencia, 
            $invoice_total, $accion, $id_contrato_asociado, $id_banco_destino, 
            implode(',', $invoice_ids), $monto_banco_bs, $fecha_banco, $banco_descripcion,
            $fechaPromesaLocal ?? null
        );
        if (!$db_ok) { error_log('[procesar_pago] guardarPago falló para ref: ' . $referencia); }

        // ── SALDO A FAVOR: guardar exceso en BD local ──────────────────────────
        // Si el cliente pagó MÁS de lo que debía, guardar el exceso como saldo_favor
        // para que sea visible en el dashboard y reutilizable en el próximo pago.
        if ($amount_unused > 0.005 && $db_ok) {
            $pdo_sf = getDb();
            if ($pdo_sf) {
                $pdo_sf->prepare(
                    "UPDATE pagos_registrados SET saldo_favor = ? WHERE referencia = ? AND service_id = ?"
                )->execute([round($amount_unused, 2), $referencia, $id_contrato_asociado]);
                error_log("[procesar_pago] Saldo a favor guardado: \${$amount_unused} para Ref: $referencia Service: $id_contrato_asociado");
            }
        }

        // ── SALDO A FAVOR: consumir crédito previo si el cliente lo usó ────────
        // Si el sistema dedujo un saldo a favor previo del monto a pagar, marcarlo consumido.
        // La deducción se hace en api_verificar_pago.php antes de llegar aquí,
        // registrada en $_SESSION['credito_usado'].
        $credito_usado = floatval($_SESSION['credito_usado'] ?? 0);
        if ($credito_usado > 0.005) {
            consumeSaldoFavor($id_contrato_asociado, $credito_usado);
            unset($_SESSION['credito_usado']);
            error_log("[procesar_pago] Crédito consumido: \${$credito_usado} para Service: $id_contrato_asociado");
        }

        // Calcular y guardar promesa de pago localmente si es pago parcial.
        // La fecha límite se calcula desde HOY sumando los días proporcionales ganados.
        // Fórmula: días_ganados = round(30 * (monto_abonado / total_factura))
        // Usamos $amount_applied (ya corregido si WispHub devolvió 0) como fuente de verdad.
        if ($amount_applied < $invoice_total && $invoice_total > 0 && !empty($invoice_ids)) {
            $totalFactura = floatval($invoice_total);
            // $amount_applied ya fue corregido arriba (min(monto_usd, invoice_total) cuando WispHub devolvió 0)
            $appliedToFirst = $amount_applied;
            // Cálculo proporcional desde HOY
            $proporcion = min(1.0, $appliedToFirst / $totalFactura);
            $diasGanados = max(1, round(30 * $proporcion));
            $fechaLimiteLocal = date('Y-m-d', strtotime("+{$diasGanados} days"));
            $saldoRestante2 = round($totalFactura - $appliedToFirst, 2);

            // Actualizar la fecha_promesa en la BD local
            $pdo_upd = getDb();
            if ($pdo_upd) {
                $pdo_upd->prepare("UPDATE pagos_registrados SET fecha_promesa = ?, accion = 'abono' WHERE referencia = ? AND service_id = ?")
                        ->execute([$fechaLimiteLocal, $referencia, $id_contrato_asociado]);
            }
            $msg_parts[] = "<br>✅ Abono de <strong>$" . number_format($appliedToFirst, 2) . " USD</strong> registrado. Tu servicio estará activo hasta el <strong>" . date('d/m/Y', strtotime($fechaLimiteLocal)) . "</strong> ($diasGanados días). Saldo pendiente: <strong>$" . number_format($saldoRestante2, 2) . " USD</strong>.";
            $_SESSION['pago_msg'] = implode(' ', $msg_parts);
        } elseif ($amount_unused > 0.005 && $invoice_total > 0) {
            // Pago con exceso (pagó más de lo que debía): la factura queda cubierta, el resto es saldo a favor
            // El mensaje de saldo a favor ya se agregó arriba — solo actualizamos la sesión
            $_SESSION['pago_msg'] = implode(' ', $msg_parts);
        }

        $redirect_url = 'dashboard.php?refreshed=1';


        // Log
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
        @file_put_contents(
            $log_dir . '/wisphub_payments.log',
            "[" . date('Y-m-d H:i:s') . "] SUCCESS Ref: $referencia Monto: {$monto_usd} USD Service: {$id_contrato_asociado} InvoiceIds: " . implode(',', $invoice_ids) . "\n",
            FILE_APPEND
        );
    } else {
        $errorMsg = $wispResult['error'] ?? json_encode($wispResult['data'] ?? 'Error desconocido');
        error_log("[procesar_pago_cliente] WispHub rechazó: " . $errorMsg);
        $_SESSION['pago_err'] = "El pago no pudo registrarse en el sistema de facturaci&oacute;n. Intenta de nuevo.";
        if (!empty($capture_path) && file_exists(__DIR__ . '/../' . $capture_path)) {
            @unlink(__DIR__ . '/../' . $capture_path);
        }
    }

} catch (\Exception $e) {
    error_log('[procesar_pago_cliente] Excepción: ' . $e->getMessage());
    $_SESSION['pago_err'] = "Error de comunicaci&oacute;n. Intenta de nuevo.";
    if (!empty($capture_path) && file_exists(__DIR__ . '/../' . $capture_path)) {
        @unlink(__DIR__ . '/../' . $capture_path);
    }
}

header('Location: ' . $redirect_url);
exit;
