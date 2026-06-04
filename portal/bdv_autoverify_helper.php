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
 * @param int    $id_contrato        ID del contrato (0 si no aplica)
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
    int    $id_contrato,
    int    $id_reporte,
    string $capture_path,
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

    // Si es el usuario de prueba V99999999, forzar el monto en Bs al entero más cercano
    $es_prueba = false;
    if ($id_reporte > 0) {
        $res_rep = $conn->query("SELECT cedula_titular FROM pagos_reportados WHERE id_reporte = " . intval($id_reporte));
        if ($res_rep && $row_rep = $res_rep->fetch_assoc()) {
            if ($row_rep['cedula_titular'] === 'V99999999') {
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

    // Buscar movimiento por referencia de manera flexible
    $mov_ref = null;
    $ref_user_clean = preg_replace('/\D/', '', $referencia);
    // Si la referencia del cliente tiene más de 8 dígitos, tomar únicamente los últimos 8 dígitos
    if (strlen($ref_user_clean) > 8) {
        $ref_user_clean = substr($ref_user_clean, -8);
    }
    $ref_user_6 = strlen($ref_user_clean) >= 6 ? substr($ref_user_clean, -6) : $ref_user_clean;
    $ref_user_8 = strlen($ref_user_clean) >= 8 ? substr($ref_user_clean, -8) : $ref_user_clean;

    foreach ($resultado['movs'] as $mov) {
        // Asegurar que solo consideramos transacciones tipo CRÉDITO (abonos de pago)
        $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
        if ($tipo !== 'CREDITO') {
            continue;
        }

        if (isset($mov['referencia'])) {
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
        // No match by reference
        $GLOBALS['bdv_falla_motivo'] = "La referencia no coincide con los registros del banco.";
        return false;
    }
    // Verificar monto (puede venir en 'importe' o 'monto' y contener formato con comas)
    $monto_mov_raw = $mov_ref['importe'] ?? $mov_ref['monto'] ?? '0';
    $monto_mov = floatval(str_replace(',', '.', preg_replace('/[^\d,.]/', '', $monto_mov_raw)));
    if (abs($monto_mov - $monto_bs) > 0.01) { // tolerancia 0.01 Bs
        $GLOBALS['bdv_falla_motivo'] = "El monto ingresado no coincide con el registrado en el banco.";
        return false;
    }
    // Si llegamos aquí, usar la referencia encontrada como movimiento
    $movimiento = $mov_ref;

    // ─── Match encontrado → aprobar automáticamente ───────────────────────────
    $justificacion = "[MENSUALIDAD] Auto-aprobado por API BDV. "
        . "Ref banco: " . ($movimiento['referencia'] ?? '') . ". "
        . "Meses: $meses_pagados. Notas: $concepto";

    $conn->begin_transaction();
    try {
        // 1. Marcar reporte como APROBADO
        $stmt_upd = $conn->prepare("UPDATE pagos_reportados SET estado = 'APROBADO' WHERE id_reporte = ?");
        if (!$stmt_upd) throw new Exception('Prepare update reporte: ' . $conn->error);
        $stmt_upd->bind_param('i', $id_reporte);
        if (!$stmt_upd->execute()) throw new Exception('Execute update reporte: ' . $stmt_upd->error);
        $stmt_upd->close();

        // 2. Registrar en cuentas_por_cobrar solo si hay contrato
        if ($id_contrato > 0) {
            $sql_cxc = "INSERT INTO cuentas_por_cobrar
                (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado,
                 fecha_pago, referencia_pago, id_banco, origen, capture_pago)
                VALUES (?, CURRENT_DATE, CURRENT_DATE, ?, 'PAGADO', ?, ?, ?, 'API_BDV', ?)";

            $stmt_cxc = $conn->prepare($sql_cxc);
            if (!$stmt_cxc) throw new Exception('Prepare cxc: ' . $conn->error);

            $ref_banco   = $movimiento['referencia'] ?? $referencia;
            $stmt_cxc->bind_param('idssss', $id_contrato, $monto_usd, $fecha_pago, $ref_banco, $id_banco, $capture_path);
            if (!$stmt_cxc->execute()) throw new Exception('Execute cxc: ' . $stmt_cxc->error);

            $id_cobro_nuevo = $conn->insert_id;
            $stmt_cxc->close();

            // 3. Historial de cobros manuales
            $sql_hist = "INSERT INTO cobros_manuales_historial
                (id_cobro_cxc, autorizado_por, justificacion, monto_cargado)
                VALUES (?, 'SISTEMA (API BDV)', ?, ?)";

            $stmt_hist = $conn->prepare($sql_hist);
            if (!$stmt_hist) throw new Exception('Prepare historial: ' . $conn->error);
            $stmt_hist->bind_param('isd', $id_cobro_nuevo, $justificacion, $monto_usd);
            if (!$stmt_hist->execute()) throw new Exception('Execute historial: ' . $stmt_hist->error);
            $stmt_hist->close();
        }

        $conn->commit();

        if (function_exists('log_security_event')) {
            $cedula_log = $row_rep['cedula_titular'] ?? null;
            log_security_event('PAYMENT_AUTO_APPROVED', "Pago verificado y aprobado automáticamente por API. Reporte #$id_reporte, Ref: $referencia, Monto: $monto_bs Bs", $cedula_log);
        }

        // 4. Eliminar el capture del disco (ya aprobado por la API, no es necesario conservarlo)
        if (!empty($capture_path)) {
            // __DIR__ = portal/  →  dirname(__DIR__) = root del proyecto
            $full_path = dirname(__DIR__) . '/' . $capture_path;
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }

        error_log('[BDV AutoVerify] Pago APROBADO automáticamente. Reporte #' . $id_reporte . ' | Ref=' . $referencia);
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        error_log('[BDV AutoVerify] ERROR en transacción DB: ' . $e->getMessage());
        return false;
    }
}
