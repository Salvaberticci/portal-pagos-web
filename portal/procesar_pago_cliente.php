<?php
// portal/procesar_pago_cliente.php
require_once 'security_helper.php';
if (!isset($_SESSION['cliente_cedula'])) {
    $n = ($_SESSION['wisp_account_ref'] ?? 'sitelco') !== 'sitelco' ? '?nodo=' . $_SESSION['wisp_account_ref'] : '';
    header('Location: index.php' . $n);
    exit;
}

@include_once '../config/wisphub_credentials.php';
$_nodoActivo = defined('WISP_HUB_ACTIVE_ACCOUNT') ? WISP_HUB_ACTIVE_ACCOUNT : ($_SESSION['wisp_account_ref'] ?? 'sitelco');
$_nodoParam  = $_nodoActivo !== 'sitelco' ? '&nodo=' . $_nodoActivo : '';

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php' . ($_nodoActivo !== 'sitelco' ? '?nodo=' . $_nodoActivo : ''));
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

$redirect_url = 'dashboard.php' . ($_nodoActivo !== 'sitelco' ? '?nodo=' . $_nodoActivo : '');

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
    $_SESSION['pago_err'] = "Petici�n inv�lida. Recarga la p�gina.";
    header('Location: ' . $redirect_url);
    exit;
}

// 3. Validar referencia (solo d+�gitos, 6-10)
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
    $_SESSION['pago_err'] = "La referencia {$referencia} ya fue utilizada en la Factura{$facturas} del d�a {$refInfo['fecha_pago']}, por el cliente {$refInfo['cliente']}.";
    header('Location: ' . $redirect_url);
    exit;
}

// 4. Validar metodo y banco
if (empty($metodo_pago) || empty($id_banco_destino)) {
    $_SESSION['pago_err'] = "M�todo de pago y banco son obligatorios.";
    header('Location: ' . $redirect_url);
    exit;
}

// 5. Determinar monto
$tasa_dolar = isset($_POST['tasa_dolar']) ? floatval($_POST['tasa_dolar']) : 0;
if ($tasa_dolar <= 0) $tasa_dolar = 1.0;

// Usar monto_usd_real si viene de verificacion BDV, o monto_usd normal (Zelle manual)
$monto_usd = isset($_POST['monto_usd_real']) ? floatval($_POST['monto_usd_real']) : (isset($_POST['monto_usd']) ? floatval($_POST['monto_usd']) : 0);

// Descartar saldo a favor si el exceso es menor a 1 USD (para todos los flujos)
if ($invoice_total > 0 && $monto_usd > $invoice_total) {
    $exceso_calculado = round($monto_usd - $invoice_total, 2);
    if ($exceso_calculado > 0 && $exceso_calculado < 1.0) {
        $monto_usd = $invoice_total;
    }
}

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
        $_SESSION['pago_err'] = "El archivo no es una imagen v�lida.";
        header('Location: ' . $redirect_url);
        exit;
    }
}

// 7. Procesar pago en WispHub
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/Services/WispHubClient.php';
    $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
    error_log("[procesar_pago] wispConfig base_url=" . ($wispConfig['base_url'] ?? 'NONE') . " account_ref=" . ($wispConfig['account_ref'] ?? 'NONE'));
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

            // Sobrescribir la referencia del cliente con los �ltimos 8 d�gitos de la referencia
            // real del banco. El cliente a veces omite d�gitos al teclear (ej: 8998874 vs 38998874).
            if (!empty($verificacion_data['movimiento']['referencia_banco'])) {
                $ref_banco_raw = preg_replace('/\D/', '', $verificacion_data['movimiento']['referencia_banco']);
                if (strlen($ref_banco_raw) >= 8) {
                    $referencia = substr($ref_banco_raw, -8);
                }
            }

            require_once __DIR__ . '/referencia_helper.php';
            // Guardar pago en BD local INMEDIATAMENTE
            $db_ok = guardarPago(
                $nombre, $ipServicio, $fecha_pago, $zona, $monto_usd, $metodo_pago, $referencia, 
                $invoice_total, $accion_pre, $id_contrato_asociado, $id_banco_destino, 
                implode(',', $invoice_ids), $monto_banco_bs, $fecha_banco, $banco_descripcion
            );
            if (!$db_ok) { error_log('[procesar_pago] pre-burn guardarPago fall� para ref: ' . $referencia); }
        }
        // ---------------------------------------------------------------------------

        // IMPORTANTE: Si es pago parcial, calculamos la promesa y creamos la factura de saldo en WispHub.
        $shouldCreatePromise = false;
        $saldoRestante = 0;
        $nuevaFacturaId = null;
        $fechaPromesaLocal = null;
        $totalFactura = 0;
        $fechaEmiOriginal = '';
        $fechaVencOriginal = '';
        $totalFacturaOriginal = 0;
        if (!empty($invoice_ids)) {
            $firstInvoiceId = (int)$invoice_ids[0];
            $invDetail = $wispClient->getInvoiceDetail((string)$firstInvoiceId);
            $fechaEmi = $invDetail['fecha_emision'] ?? '';
            $fechaVenc = $invDetail['fecha_vencimiento'] ?? '';
            $totalFactura = floatval($invDetail['total'] ?? $invoice_total);
            // Guardar copia de seguridad para $cobertura_hasta (evitar que
            // WispHub modifique los datos de la factura despu�s del pago)
            $fechaEmiOriginal = $fechaEmi;
            $fechaVencOriginal = $fechaVenc;
            $totalFacturaOriginal = $totalFactura;
        }
        $monto_pago_wisp = $monto_usd; // por defecto
        $excesoAmount = 0;
        $creditNoteId = null;
        $creditNoteCreated = false;
        $fechaPagoOriginal = $fecha_pago;
        $precioPlan = 0;

        if ($monto_usd < $totalFactura && $totalFactura > 0 && !empty($invoice_ids)) {
            // Funci�n recursiva para obtener el precio real del plan buscando la factura original
            $getTruePlanPrice = function($wispClient, $invId, $fallbackPrice) use (&$getTruePlanPrice) {
                $detail = $wispClient->getInvoiceDetail((string)$invId);
                if (empty($detail)) return $fallbackPrice;
                
                $parentInvoiceId = 0;
                if (!empty($detail['articulos'])) {
                    foreach ($detail['articulos'] as $art) {
                        $desc = $art['descripcion'] ?? '';
                        if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $desc, $m)) {
                            $parentInvoiceId = $m[1];
                            break;
                        }
                    }
                }
                
                if ($parentInvoiceId) {
                    return $getTruePlanPrice($wispClient, $parentInvoiceId, $fallbackPrice);
                }
                return floatval($detail['total'] ?? $fallbackPrice);
            };

            // Obtener el precio real de la factura ra�z original
            $precioPlan = $getTruePlanPrice($wispClient, $firstInvoiceId, $totalFactura);
            if ($precioPlan <= 0) $precioPlan = $totalFactura; // fallback de seguridad

            $diasExtra = round(30 * ($monto_usd / max($precioPlan, 1)));
            
            // Funci�n recursiva para extraer la fecha de inicio del per�odo desde la factura ra�z.
            // Las facturas de "Saldo pendiente" no tienen "Periodo del..." en la descripci�n,
            // as� que debemos navegar la cadena de facturas hasta encontrar la original.
            $getTruePeriodStart = function($wispClient, $invId, $fallbackDate) use (&$getTruePeriodStart) {
                $detail = $wispClient->getInvoiceDetail((string)$invId);
                if (empty($detail)) return $fallbackDate;
                
                $meses = ['Ene'=>'01','Feb'=>'02','Mar'=>'03','Abr'=>'04','May'=>'05','Jun'=>'06',
                          'Jul'=>'07','Ago'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dic'=>'12'];
                
                $parentInvoiceId = 0;
                if (!empty($detail['articulos'])) {
                    foreach ($detail['articulos'] as $art) {
                        $desc = $art['descripcion'] ?? '';
                        // Buscar "Periodo del X/Mes./A�o" en la descripci�n
                        if (preg_match('/Periodo del\s+(\d{1,2})\/([A-Za-z.]+)\/(\d{4})/i', $desc, $m)) {
                            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                            $monthStr = ucfirst(strtolower(str_replace('.', '', $m[2])));
                            if (isset($meses[$monthStr])) {
                                return $m[3] . '-' . $meses[$monthStr] . '-' . $day;
                            }
                        }
                        // Si es "Saldo pendiente", seguir la cadena hacia la factura padre
                        if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $desc, $m2)) {
                            $parentInvoiceId = $m2[1];
                        } elseif (preg_match('/Saldo Pendiente de Pago de la Factura (\d+)/i', $desc, $m2)) {
                            $parentInvoiceId = $m2[1];
                        }
                    }
                }
                
                if ($parentInvoiceId) {
                    return $getTruePeriodStart($wispClient, $parentInvoiceId, $fallbackDate);
                }
                // Si no tiene ni per�odo ni padre, usar fecha_pago de la factura como fallback
                return $detail['fecha_pago'] ? substr($detail['fecha_pago'], 0, 10) : $fallbackDate;
            };

            // Obtener la fecha base real del per�odo desde la factura ra�z
            $fechaBasePromesa = $getTruePeriodStart($wispClient, $firstInvoiceId, 
                !empty($fechaEmiOriginal) ? $fechaEmiOriginal : $fecha_pago);

            $fechaLimitePromesa = date('Y-m-d', strtotime($fechaBasePromesa . " + $diasExtra days"));
            
            $saldoRestante = round($totalFactura - $monto_usd, 2);
            $shouldCreatePromise = true;
        }

        // -- Si es pago en exceso: nota de cr�dito en WispHub --
        // Cuando el cliente paga m�s del total de la factura, el exceso
        // se convierte en una nota de cr�dito (factura con monto negativo)
        // que reduce autom�ticamente el balance del cliente en WispHub.
        if ($monto_usd > $totalFactura && $totalFactura > 0 && !empty($invoice_ids)) {
            $excesoAmount = round($monto_usd - $totalFactura, 2);
            $monto_pago_wisp = $totalFactura;

            if (!isset($wispData)) {
                require_once __DIR__ . '/wisp_helper.php';
                $wispData = wisp_get_cached_data($wispClient, $id_contrato_asociado);
            }
            $username = $wispData['profile']['usuario'] ?? '';
            if (empty($username) && !empty($firstInvoiceId)) {
                $invDetail = $wispClient->getInvoiceDetail((string)$firstInvoiceId);
                $username = is_array($invDetail['cliente'] ?? null) ? ($invDetail['cliente']['usuario'] ?? '') : ($invDetail['cliente'] ?? $invDetail['usuario'] ?? '');
            }

            $descNC = 'Saldo a favor por pago en exceso - Factura #' . $firstInvoiceId;
            $ncResult = $wispClient->createCreditNote($username, $excesoAmount, $descNC);
            if (in_array($ncResult['status'] ?? 0, [200, 201])) {
                $msg = $ncResult['data']['messages'] ?? $ncResult['data']['message'] ?? '';
                if (is_array($msg)) $msg = implode(' ', $msg);
                preg_match('/factura\s*#?(\d+)/i', $msg, $m);
                $creditNoteId = isset($m[1]) ? (int)$m[1] : 0;
                $creditNoteCreated = true;
                error_log("[procesar_pago] Nota de cr\u00e9dito #{$creditNoteId} creada en WispHub por \${$excesoAmount} (exceso pago #{$firstInvoiceId})");
            } else {
                error_log("[procesar_pago] Fallo crear nota de cr\u00e9dito: " . json_encode($ncResult));
                $monto_pago_wisp = $monto_usd; // fallback: intentar con monto completo
            }
        }

        // -- Si es pago parcial: crear factura de saldo ANTES del pago --
        // (WispHub duplica el monto si se crea despu�s de registrar el pago)
        if ($shouldCreatePromise && $saldoRestante > 0.01) {
            if (!isset($wispData)) {
                require_once __DIR__ . '/wisp_helper.php';
                $wispData = wisp_get_cached_data($wispClient, $id_contrato_asociado);
            }
            $username = $wispData['profile']['usuario'] ?? '';
            if (empty($username) && !empty($firstInvoiceId)) {
                $invDetail = $wispClient->getInvoiceDetail((string)$firstInvoiceId);
                $username = is_array($invDetail['cliente'] ?? null) ? ($invDetail['cliente']['usuario'] ?? '') : ($invDetail['cliente'] ?? $invDetail['usuario'] ?? '');
            }

            $descNueva = 'Saldo pendiente tras abono - Factura #' . $firstInvoiceId;
            $createResult = $wispClient->createInvoice(
                $username, $saldoRestante, $descNueva, $fechaLimitePromesa, $id_contrato_asociado
            );
            if (in_array($createResult['status'] ?? 0, [200, 201])) {
                $msg = $createResult['data']['messages'] ?? $createResult['data']['message'] ?? '';
                if (is_array($msg)) $msg = implode(' ', $msg);
                preg_match('/factura\s*#?(\d+)/i', $msg, $m);
                $nuevaFacturaId = isset($m[1]) ? (int)$m[1] : 0;
                error_log("[procesar_pago] Factura saldo pendiente #{$nuevaFacturaId} creada en WispHub (\${$saldoRestante})");
            } else {
                error_log("[procesar_pago] Fallo crear factura saldo pendiente: " . json_encode($createResult));
                $nuevaFacturaId = 0;
            }
        }

        // Artificio para enviar el monto en Bs a WispHub en la referencia (para no pagar extra ni hacer conciliaci�n compleja)
        // Regla: �ltimos 8 caracteres de referencia + guion + monto en Bs con coma decimal. Ej: 60741024-130,60
        $monto_bs_final = isset($monto_banco_bs) && $monto_banco_bs > 0 ? $monto_banco_bs : $monto_bs;
        
        // Preferir la referencia real del banco (API verificada) sobre la que tecle� el cliente.
        // El cliente a veces omite d�gitos (ej: pone 8998874 en vez de 38998874).
        // La referencia del banco siempre es la correcta y completa.
        $ref_fuente = $referencia; // fallback: lo que tecle� el cliente
        if (!empty($verificacion_data['movimiento']['referencia_banco'])) {
            $ref_banco = preg_replace('/\D/', '', $verificacion_data['movimiento']['referencia_banco']);
            if (strlen($ref_banco) >= 8) {
                $ref_fuente = $ref_banco;
            }
        }
        $ref_8_chars = substr($ref_fuente, -8);
        
        // Formatear monto con decimales usando coma (130.60 -> 130,60)
        $monto_plano = number_format($monto_bs_final, 2, ',', '');
        
        $referencia_wisp = $ref_8_chars . '-' . $monto_plano;

        // Nuevo flujo: registrar directo con las facturas seleccionadas
        $wispResult = $wispClient->registerPaymentAndActivate(
            $id_contrato_asociado,
            $monto_pago_wisp,
            $referencia_wisp,
            $wispDate,
            WISP_HUB_FORMA_PAGO_OPERACION_BANCARIA,
            false,
            '',
            $invoice_ids
        );

        $wispStatus = $wispResult['status'] ?? 200;

        // -- Si es pago parcial: registrar compromiso en WispHub sobre la factura creada --
        // NOTA: WispHub devuelve status 400 cuando el pago es parcial (total_cobrado < total),
        //       aunque el pago s� fue registrado correctamente. Por eso tambi�n aceptamos 400
        //       siempre que el monto haya sido efectivamente aplicado (amount_applied > 0).
        $wispPaymentApplied = floatval($wispResult['amount_applied'] ?? 0) > 0;
        $wispOk = in_array($wispStatus, [200, 201]) || ($wispStatus === 400 && $wispPaymentApplied);
        if ($shouldCreatePromise && $saldoRestante > 0.01 && $nuevaFacturaId && $wispOk) {
            // Sumar +1 d�a a la fecha l�mite en WispHub para que el cliente tenga
            // el d�a completo de servicio (ej: 15/07 ? 16/07 en WispHub)
            $fechaLimiteWisp = date('Y-m-d', strtotime($fechaLimitePromesa . ' +1 day'));
            $promiseResult = $wispClient->addPaymentPromise(
                $nuevaFacturaId, $fechaLimiteWisp, $saldoRestante, 1
            );
            if (in_array($promiseResult['status'] ?? 0, [200, 201])) {
                error_log("[procesar_pago] Promesa creada en WispHub: Factura #{$nuevaFacturaId}, vence {$fechaLimiteWisp}");
            } else {
                error_log("[procesar_pago] Fallo crear promesa en WispHub: " . json_encode($promiseResult));
            }
            $fechaPromesaLocal = $fechaLimitePromesa;
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
            $_SESSION['pago_msg'] = "�Tu pago fue verificado y registrado! Ref: $referencia.";
            // Guardar pago en BD local
            require_once __DIR__ . '/referencia_helper.php';
            $db_ok = guardarPago(
                $nombre, '', $fecha_pago, '', $monto_usd, $metodo_pago, $referencia, 
                $invoice_total, $accion ?? null, $id_contrato_asociado ?? '', $id_banco_destino, 
                implode(',', $invoice_ids), null, null, null // No tenemos los datos crudos en el legacy
            );
            if (!$db_ok) { error_log('[procesar_pago] guardarPago fall� en legacy para ref: ' . $referencia); }
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
    // NOTA: WispHub devuelve 400 en pagos parciales aunque el pago haya sido aplicado.
    //       Tambi�n consideramos exitoso si amount_applied > 0.
    $wispSuccess = $wispResult && (
        in_array($wispResult['status'] ?? 0, [200, 201]) ||
        ($wispResult['status'] === 400 && floatval($wispResult['amount_applied'] ?? 0) > 0)
    );
    $bankVerified = !empty($verificacion_data);

    if ($wispSuccess || $bankVerified) {
        // Construir mensaje detallado segun la distribucion
        $msg_parts = ["Tu pago fue verificado y registrado exitosamente."];

        if ($wispSuccess) {
            $amount_applied = floatval($wispResult['amount_applied'] ?? $monto_pago_wisp);
            $amount_unused  = floatval($wispResult['amount_unused'] ?? 0);
            $pagos_count    = count($wispResult['payments_registered'] ?? []);

            // CORRECCI�N: WispHub a veces devuelve amount_applied=0 cuando el cliente ya hizo
            // un abono previo y la factura no aparece como "pendiente" en WispHub.
            // En ese caso calculamos manualmente: aplicado = min(pagado, deuda_pendiente)
            if ($amount_applied == 0 && $monto_usd > 0 && $invoice_total > 0) {
                $amount_applied = min($monto_pago_wisp, $invoice_total);
                $amount_unused  = max(0, round($monto_usd - $invoice_total, 2)); // monto_usd original para saber exceso real
                // Simular que s� hubo un registro (para no mostrar el aviso de "0 recibos")
                $pagos_count = !empty($invoice_ids) ? 1 : 0;
                error_log("[procesar_pago] WispHub devolvi� amount_applied=0 para Ref: $referencia � corrigiendo con invoice_total=$invoice_total");
            }

            if ($pagos_count > 0 && $amount_applied > 0) {
                $msg_parts[] = "Se aplicaron <strong>$" . number_format($amount_applied, 2) . " USD</strong> a $pagos_count recibo(s).";
            }

            // Si se cre� nota de cr�dito (pago en exceso), mostrar mensaje
            if ($creditNoteCreated && $excesoAmount > 0.005) {
                $msg_parts[] = "Se cre\u00f3 una <strong>NOTA DE CR\u00c9DITO por $" . number_format($excesoAmount, 2) . " USD</strong> como saldo a favor para tu pr\u00f3ximo recibo.";
            } else {
                // Fallback: calcular exceso manualmente
                $amount_unused = max($amount_unused, round(max(0, $monto_usd - ($totalFactura ?: $invoice_total)), 2));
                if ($amount_unused > 0.005) {
                    $saldo_favor_real = round($amount_unused, 2);
                    $msg_parts[] = "Te queda un <strong>SALDO A FAVOR de $" . number_format($saldo_favor_real, 2) . " USD</strong> para tu pr&oacute;ximo recibo.";
                }

                if ($amount_applied > 0 && $amount_applied < $monto_usd && $amount_unused <= 0.005) {
                    $msg_parts[] = "Se aplic&oacute; <strong>$" . number_format($amount_applied, 2) . " USD</strong> como abono a tu deuda.";
                }
            }

            // Aviso solo si realmente no se pudo pagar ning�n recibo
            $selected_count = count($invoice_ids);
            if ($selected_count > 0 && $pagos_count == 0) {
                $msg_parts[] = "Nota: el pago qued� registrado manualmente. Contacta a soporte con tu referencia si no ves el cambio reflejado.";
            }
        } else {
            // WispHub fall� pero el banco aprob�
            $errorMsg = $wispResult['error'] ?? json_encode($wispResult['data'] ?? 'Error desconocido');
            error_log("[procesar_pago_cliente] WispHub rechaz� pero Banco aprob� (Ref: $referencia): " . $errorMsg);
            $msg_parts[] = "<br><strong style='color:#dc2626;'>Aviso:</strong> El banco aprob� el pago, pero WispHub no pudo aplicarlo a tu factura autom�ticamente. Por favor contacta a soporte con tu n�mero de referencia. (Detalle: " . htmlspecialchars($errorMsg) . ")";
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
        if ($accion === null && $totalFactura > 0) {
            $diff = round($monto_usd - $totalFactura, 2);
            $accion = $diff < 0 ? 'abono' : ($diff == 0 ? 'completo' : 'exceso');
        }

        // Calcular cobertura para abonos
        // Se basa en $precioPlan (precio del plan = 30 d�as) y $fecha_pago
        // en vez de fecha de vencimiento de la factura
        $cobertura_hasta = '';
        if ($accion_pre === 'abono' && isset($diasExtra)) {
            if (!empty($fechaPromesaLocal)) {
                $cobertura_hasta = date('d/m/Y', strtotime($fechaPromesaLocal));
            } else {
                $cobertura_hasta = date('d/m/Y', strtotime($fechaPagoOriginal . ' + ' . $diasExtra . ' days'));
            }
        } else if ($accion_pre === 'completo' || $accion_pre === 'exceso') {
             // ... l�gica adicional si es necesario
        }

        // Guardar pago_data en sesi�n para el modal de resultado
        $_SESSION['pago_data'] = [
            'referencia' => $referencia,
            'monto_usd'  => $monto_usd,
            'monto_bs'   => $monto_bs,
            'service_id' => $id_contrato_asociado,
            'accion'     => $accion_pre,
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
        if (!$db_ok) { error_log('[procesar_pago] guardarPago fall� para ref: ' . $referencia); }

        // -- SALDO A FAVOR: guardar exceso en BD local --------------------------
        // Si se cre� nota de cr�dito en WispHub, el exceso ya est� registrado ah�
        // (como factura con monto negativo) y no debe duplicarse en BD local.
        // Solo guardamos en BD local como fallback si la nota de cr�dito fall�.
        if (!$creditNoteCreated) {
            $exceso_real = round(max(0, $monto_usd - ($totalFactura ?: $invoice_total)), 2);
            if ($exceso_real > 0.005 && $db_ok) {
                $pdo_sf = getDb();
                if ($pdo_sf) {
                    $pdo_sf->prepare(
                        "UPDATE pagos_registrados SET saldo_favor = ? WHERE referencia = ? AND service_id = ?"
                    )->execute([$exceso_real, $referencia, $id_contrato_asociado]);
                    error_log("[procesar_pago] Saldo a favor guardado (local): \${$exceso_real} para Ref: $referencia Service: $id_contrato_asociado");
                }
            }
        } else {
            error_log("[procesar_pago] Exceso \${$excesoAmount} manejado v�a nota de cr�dito #{$creditNoteId} � no se guarda en BD local");
        }

        // -- SALDO A FAVOR: consumir cr�dito previo si el cliente lo us� --------
        // Si el pago consumi� saldo a favor, descontarlo de la BD local
        // El cr�dito usado viene en el JSON de verificaci�n de la API
        $credito_usado = 0;
        if (isset($verificacion_data) && is_array($verificacion_data) && isset($verificacion_data['credito_usado'])) {
            $credito_usado = floatval($verificacion_data['credito_usado']);
        }
        
        if ($credito_usado > 0.005) {
            consumeSaldoFavor($id_contrato_asociado, $credito_usado);
            error_log("[procesar_pago] Cr�dito consumido: \${$credito_usado} para Service: $id_contrato_asociado");
        }

        // Mensaje para pago parcial con factura creada en WispHub
        if ($nuevaFacturaId && $saldoRestante > 0.01) {
            $msg_parts[] = "<br>? Abono de <strong>$" . number_format($amount_applied, 2) . " USD</strong> registrado. Se gener� la Factura <strong>#" . $nuevaFacturaId . "</strong> por <strong>$" . number_format($saldoRestante, 2) . " USD</strong> como saldo pendiente. Compromiso de pago hasta el <strong>" . date('d/m/Y', strtotime($fechaLimitePromesa)) . "</strong>.";
            $_SESSION['pago_msg'] = implode(' ', $msg_parts);
        } elseif ($shouldCreatePromise && $saldoRestante > 0.01) {
            $msg_parts[] = "<br>? Abono de <strong>$" . number_format($amount_applied, 2) . " USD</strong> registrado. Saldo pendiente: <strong>$" . number_format($saldoRestante, 2) . " USD</strong>.";
            $_SESSION['pago_msg'] = implode(' ', $msg_parts);
        } elseif ($exceso_real > 0.005) {
            $_SESSION['pago_msg'] = implode(' ', $msg_parts);
        }

        $redirect_url = 'dashboard.php?refreshed=1' . $_nodoParam;


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
        error_log("[procesar_pago_cliente] WispHub rechaz�: " . $errorMsg);
        $_SESSION['pago_err'] = "El pago no pudo registrarse en el sistema de facturaci&oacute;n. Intenta de nuevo.";
        if (!empty($capture_path) && file_exists(__DIR__ . '/../' . $capture_path)) {
            @unlink(__DIR__ . '/../' . $capture_path);
        }
    }

} catch (\Exception $e) {
    error_log('[procesar_pago_cliente] Excepci�n: ' . $e->getMessage());
    $_SESSION['pago_err'] = "Error de comunicaci&oacute;n. Intenta de nuevo.";
    if (!empty($capture_path) && file_exists(__DIR__ . '/../' . $capture_path)) {
        @unlink(__DIR__ . '/../' . $capture_path);
    }
}

header('Location: ' . $redirect_url);
exit;
