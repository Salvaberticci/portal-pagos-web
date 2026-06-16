<?php
/**
 * bdv_autoverify_helper.php
 *
 * Orquestador de auto-verificación y aprobación de pagos BDV.
 * Se incluye desde procesar_pago_cliente.php y procesar_reporte_pago.php.
 *
 * Requisitos previos al include:
 *   - $conn  (mysqli) ya conectado
 *   - bdv_api_helper.php cargado (define consultar_movimientos_bdv y buscar_movimiento_bdv)
 */

require_once __DIR__ . '/../paginas/principal/banco_api_router.php'; // incluye bdv_api_helper
@include_once __DIR__ . '/../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

/**
 * Intenta verificar y aprobar automáticamente un pago cuyo banco sea BDV.
 *
 * Si la API confirma el movimiento:
 *   - Actualiza pagos_reportados -> APROBADO
 *   - Inserta en cuentas_por_cobrar -> PAGADO
 *   - Registra en cobros_manuales_historial
 *   - Elimina el capture del disco (ya no es necesario)
 *   - Devuelve true
 *
 * Si no hay match (demora bancaria, API caída, etc.):
 *   - No modifica nada en la BD (el registro ya está PENDIENTE)
 *   - Devuelve false  → flujo manual de administración
 *
 * @param mysqli $conn
 * @param int    $id_banco           ID del banco (9 o 12 para BDV)
 * @param string $referencia         Número de referencia reportado por el cliente
 * @param float  $monto_usd          Monto en dólares
 * @param float  $tasa_dolar         Tasa BCV usada en el momento del reporte
 * @param string $fecha_pago         Fecha del pago Y-m-d
 * @param string $id_contrato        ID del contrato (WispHub service ID)
 * @param int    $id_reporte         ID en pagos_reportados recién insertado
 * @param string $capture_path       Ruta relativa del comprobante (puede ser '')
 * @param string $meses_pagados      String descriptivo de meses (para historial)
 * @param string $concepto           Concepto del pago
 *
 * @return bool  true = aprobado automáticamente | false = queda pendiente
 */
function verificar_y_aprobar_pago_bdv(
    mysqli $conn,
    int    $id_banco,
    string $referencia,
    float  $monto_usd,
    float  $tasa_dolar,
    string $fecha_pago,
    string $id_contrato,
    int    $id_reporte,
    string $capture_path,
    string $metodo_pago   = '',
    string $meses_pagados = '',
    string $concepto      = 'Pago de mensualidad'
): bool {

    $GLOBALS['bdv_falla_motivo'] = "Pendiente de validación.";

    // Solo proceder si el banco tiene una API habilitada configurada
    $api_cfg = obtener_config_api_banco($id_banco);
    if ($api_cfg === null) {
        return false;
    }

    // Calcular monto en bolívares
    $monto_bs = ($monto_usd > 0 && $tasa_dolar > 0)
        ? round($monto_usd * $tasa_dolar, 2)
        : 0.00;

    // Si es el usuario de prueba V20788775, forzar el monto en Bs al entero más cercano
    $es_prueba = false;
    if ($id_reporte > 0) {
        $res_rep = $conn->query("SELECT cedula_titular FROM pagos_reportados WHERE id_reporte = " . intval($id_reporte));
        if ($res_rep && $row_rep = $res_rep->fetch_assoc()) {
            if ($row_rep['cedula_titular'] === TEST_USER_CEDULA) {
                $es_prueba = true;
            }
        }
    }
    if ($es_prueba) {
        $monto_bs_int = round($monto_bs);
        if (abs($monto_bs - $monto_bs_int) < 0.2) {
            $monto_bs = $monto_bs_int;
        }
        if ($monto_bs <= 0) {
            $monto_bs = 1.00;
        }
    }

    if ($monto_bs <= 0) {
        return false;
    }

    // Consultar la API para la fecha del pago ±1 día (para cubrir desfases)
    $ts         = strtotime($fecha_pago);
    $fecha_ini  = date('Y-m-d', strtotime('-1 day', $ts));
    $fecha_fin  = date('Y-m-d', strtotime('+1 day', $ts));

    // Evitar que la fecha fin sea en el futuro, ya que la API de BDV lo rechaza
    $hoy = date('Y-m-d');
    if ($fecha_fin > $hoy) {
        $fecha_fin = $hoy;
    }

    $resultado = consultar_movimientos_banco($id_banco, $fecha_ini, $fecha_fin);

    if (!$resultado['success'] || empty($resultado['movs'])) {
        // API caída o sin movimientos → dejar como PENDIENTE
        error_log('[BDV AutoVerify] No se pudo consultar o sin movimientos: ' . $resultado['message']);
        return false;
    }

    // Buscar movimiento por referencia
    $mov_ref = null;
    $ref_user_clean = preg_replace('/\D/', '', $referencia);

    if ($metodo_pago === 'Transferencia') {
        // Transferencia: match exacto con la referencia completa
        foreach ($resultado['movs'] as $mov) {
            $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
            if ($tipo !== 'CREDITO') continue;
            if (!isset($mov['referencia'])) continue;
            if (preg_replace('/\D/', '', $mov['referencia']) === $ref_user_clean) {
                $mov_ref = $mov;
                break;
            }
        }
    } else {
        // Pago Móvil: match flexible por últimos dígitos
        if (strlen($ref_user_clean) > 8) {
            $ref_user_clean = substr($ref_user_clean, -8);
        }
        $ref_user_6 = strlen($ref_user_clean) >= 6 ? substr($ref_user_clean, -6) : $ref_user_clean;
        $ref_user_8 = strlen($ref_user_clean) >= 8 ? substr($ref_user_clean, -8) : $ref_user_clean;

        foreach ($resultado['movs'] as $mov) {
            $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
            if ($tipo !== 'CREDITO') continue;
            if (!isset($mov['referencia'])) continue;

            $ref_banco_clean = preg_replace('/\D/', '', $mov['referencia']);
            $ref_banco_6 = strlen($ref_banco_clean) >= 6 ? substr($ref_banco_clean, -6) : $ref_banco_clean;
            $ref_banco_8 = strlen($ref_banco_clean) >= 8 ? substr($ref_banco_clean, -8) : $ref_banco_clean;

            if (
                $ref_banco_clean === $ref_user_clean ||
                ($ref_banco_8 !== '' && $ref_banco_8 === $ref_user_8) ||
                ($ref_banco_6 !== '' && $ref_banco_6 === $ref_user_6)
            ) {
                $mov_ref = $mov;
                break;
            }
        }
    }

    if (!$mov_ref) {
        $GLOBALS['bdv_falla_motivo'] = "La referencia no coincide con los registros del banco.";
        return false;
    }
    // Verificar monto (puede venir en 'importe' o 'monto' y contener formato con comas)
    $monto_mov_raw = $mov_ref['importe'] ?? $mov_ref['monto'] ?? '0';
    $monto_mov = floatval(str_replace(',', '.', preg_replace('/[^\d,.]/', '', $monto_mov_raw)));
    if (abs($monto_mov - $monto_bs) > 10) { // tolerancia 10 Bs
        $GLOBALS['bdv_falla_motivo'] = "El monto ingresado no coincide con el registrado en el banco.";
        return false;
    }
    // Si llegamos aquí, usar la referencia encontrada como movimiento
    $movimiento = $mov_ref;

    // ─── Match encontrado → aprobar automáticamente ───────────────────────────
    $wispResult = null;
    $wispAccountId = '';
    $wispServiceId = '';
    $wispDate = date('Y-m-d H:i', strtotime($fecha_pago));

    $justificacion = "[MENSUALIDAD] Auto-aprobado por API BDV. "
        . "Ref banco: " . ($movimiento['referencia'] ?? '') . ". "
        . "Meses: $meses_pagados. Notas: $concepto";

    // PRIMERO: transacción local (commiteamos antes de tocar WispHub)
    $conn->begin_transaction();
    try {
        // 1. Marcar reporte como APROBADO
        $stmt_upd = $conn->prepare("UPDATE pagos_reportados SET estado = 'APROBADO' WHERE id_reporte = ?");
        if (!$stmt_upd) throw new Exception('Prepare update reporte: ' . $conn->error);
        $stmt_upd->bind_param('i', $id_reporte);
        if (!$stmt_upd->execute()) throw new Exception('Execute update reporte: ' . $stmt_upd->error);
        $stmt_upd->close();

        // 2. Ya no insertamos en cuentas_por_cobrar ni cobros_manuales_historial

        $conn->commit();

        // SEGUNDO: registrar en WispHub (después del commit local)
        if (!empty($id_contrato)) {
            try {
                require_once dirname(__DIR__) . '/vendor/autoload.php';
                require_once dirname(__DIR__) . '/src/Services/WispHubClient.php';
                $wispConfig = include dirname(__DIR__) . '/config/wisp_hub.php';
                $wispClient = new \Services\WispHubClient($wispConfig);

                $wispServiceId = $id_contrato;

                $wispResult = $wispClient->registerPaymentAndActivate(
                    $wispServiceId, $monto_usd, $referencia, $wispDate,
                    \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA, false, ''
                );

                if ($wispResult && in_array($wispResult['status'] ?? 0, [200, 201])) {
                    $wispAccountId = $wispResult['service_id'] ?? '';
                    // Log WispHub exitoso
                    $sql_log = "INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (?, ?, ?, NOW())";
                    $stmt_log = $conn->prepare($sql_log);
                    if ($stmt_log) {
                        $logPayload = json_encode([
                            'service_id' => $wispAccountId, 'amount' => $monto_usd,
                            'reference' => $referencia, 'date' => $wispDate,
                        ]);
                        $logResponse = json_encode($wispResult);
                        $stmt_log->bind_param("iss", $id_reporte, $logPayload, $logResponse);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                } elseif ($wispResult && $wispResult['status'] !== 404) {
                    error_log("[BDV AutoVerify] WispHub rechazó el pago local #$id_reporte: " . ($wispResult['error'] ?? json_encode($wispResult['data'] ?? '')));
                }
            } catch (\Exception $e) {
                error_log('[BDV AutoVerify] Excepción en WispHub tras pago local #' . $id_reporte . ': ' . $e->getMessage());
            }
        }

        if (function_exists('log_security_event')) {
            $cedula_log = $row_rep['cedula_titular'] ?? null;
            log_security_event('PAYMENT_AUTO_APPROVED', "Pago verificado y aprobado automáticamente por API. Reporte #$id_reporte, Ref: $referencia, Monto: $monto_bs Bs", $cedula_log);
        }

        // 5. Eliminar el capture del disco
        if (!empty($capture_path)) {
            $full_path = dirname(__DIR__) . '/' . $capture_path;
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }

        error_log('[BDV AutoVerify] Pago APROBADO automáticamente. Reporte #' . $id_reporte . ' | Ref=' . $referencia
            . ($wispAccountId ? " | WispHub OK service=$wispAccountId" : ' | sin WispHub'));
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        error_log('[BDV AutoVerify] ERROR en transacción DB: ' . $e->getMessage());
        return false;
    }
}
