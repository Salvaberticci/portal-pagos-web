<?php
/**
 * bdv_autoverify_helper.php
 *
 * Orquestador de auto-verificación y aprobación de pagos BDV.
 * Se incluye desde procesar_pago_cliente.php
 */

require_once __DIR__ . '/../paginas/principal/banco_api_router.php'; // incluye bdv_api_helper
@include_once __DIR__ . '/../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

/**
 * Intenta verificar y aprobar automáticamente un pago cuyo banco sea BDV y registrar en WispHub.
 *
 * Si falla:
 *   - Devuelve false y setea el motivo en $GLOBALS['bdv_falla_motivo']
 *
 * @param int    $id_banco           ID del banco (9 o 12 para BDV)
 * @param string $referencia         Número de referencia reportado por el cliente
 * @param float  $monto_usd          Monto en dólares
 * @param float  $tasa_dolar         Tasa BCV usada en el momento del reporte
 * @param string $fecha_pago         Fecha del pago Y-m-d
 * @param string $wisp_service_id    ID del servicio en WispHub
 * @param string $capture_path       Ruta relativa del comprobante (puede ser '')
 * @param string $metodo_pago        Metodo de pago
 * @param string $meses_pagados      String descriptivo de meses (para historial)
 * @param string $concepto           Concepto del pago
 *
 * @return bool  true = aprobado | false = error/cancelado
 */
function verificar_y_aprobar_pago_bdv(
    int    $id_banco,
    string $referencia,
    float  $monto_usd,
    float  $tasa_dolar,
    string $fecha_pago,
    string $wisp_service_id,
    string $capture_path,
    string $metodo_pago   = '',
    string $meses_pagados = '',
    string $concepto      = 'Pago de mensualidad'
): bool {

    $GLOBALS['bdv_falla_motivo'] = "Pendiente de validación.";

    // Solo proceder si el banco tiene una API habilitada configurada
    $api_cfg = obtener_config_api_banco($id_banco);
    if ($api_cfg === null) {
        $GLOBALS['bdv_falla_motivo'] = "El banco seleccionado no tiene validación automática activa.";
        return false;
    }

    // Calcular monto en bolívares
    $monto_bs = ($monto_usd > 0 && $tasa_dolar > 0)
        ? round($monto_usd * $tasa_dolar, 2)
        : 0.00;

    // Si es el usuario de prueba, forzar el monto en Bs al entero más cercano
    $es_prueba = (isset($_SESSION['cliente_cedula']) && $_SESSION['cliente_cedula'] === TEST_USER_CEDULA);
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
        $GLOBALS['bdv_falla_motivo'] = "El monto en Bolívares es inválido.";
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
        // API caída o sin movimientos
        error_log('[BDV AutoVerify] No se pudo consultar o sin movimientos: ' . $resultado['message']);
        $GLOBALS['bdv_falla_motivo'] = "No pudimos conectar con el banco para verificar tu pago. Inténtalo más tarde.";
        return false;
    }

    // Buscar movimiento por referencia
    $mov_ref = null;
    $ref_user_clean = preg_replace('/\D/', '', $referencia);

    if ($metodo_pago === 'Transferencia') {
        // Transferencia: match exacto con la referencia completa
        foreach ($resultado['movs'] as $mov) {
            $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
            $desc = strtoupper($mov['descripcion'] ?? '');
            if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
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
            $desc = strtoupper($mov['descripcion'] ?? '');
            if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
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
        $GLOBALS['bdv_falla_motivo'] = "La referencia ingresada no aparece en los movimientos recientes del banco.";
        return false;
    }
    // Obtener monto real del banco
    $monto_mov_raw = $mov_ref['importe'] ?? $mov_ref['monto'] ?? '0';
    $monto_mov = floatval(str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,.]/', '', $monto_mov_raw))));
    
    // Verificar que coincida con el monto reportado por el formulario para seguridad
    if (abs($monto_mov - $monto_bs) > 10) { // tolerancia 10 Bs
        $GLOBALS['bdv_falla_motivo'] = "El monto de la referencia no coincide con el monto reportado ($monto_bs Bs).";
        return false;
    }
    
    // Sobrescribimos el monto en USD usando el monto real conciliado del banco para evitar discrepancias de centavos
    $monto_usd = round($monto_mov / $tasa_dolar, 2);
    
    // ─── Match encontrado → aprobar automáticamente en WispHub ───────────────────────────
    if (!empty($wisp_service_id)) {
        try {
            require_once dirname(__DIR__) . '/vendor/autoload.php';
            require_once dirname(__DIR__) . '/src/Services/WispHubClient.php';
            $wispConfig = include dirname(__DIR__) . '/config/wisp_hub.php';
            if (DEV_MODE && ($_SESSION['cliente_cedula'] ?? '') === TEST_USER_CEDULA) {
                require_once dirname(__DIR__) . '/src/Services/WispHubDevModeClient.php';
                $wispClient = new \Services\WispHubDevModeClient($wispConfig);
            } else {
                $wispClient = new \Services\WispHubClient($wispConfig);
            }

            $wispDate = date('Y-m-d H:i', strtotime($fecha_pago));

            $wispResult = $wispClient->registerPaymentAndActivate(
                $wisp_service_id, 
                $monto_usd, 
                $referencia, 
                $wispDate,
                \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA, 
                false, 
                ''
            );

            if ($wispResult && in_array($wispResult['status'] ?? 0, [200, 201])) {
                // Éxito WispHub
                
                // Si se configuró guardar local logs, lo haríamos en un archivo
                $log_dir = __DIR__ . '/../logs';
                if (!is_dir($log_dir)) @mkdir($log_dir, 0777, true);
                @file_put_contents($log_dir . '/wisphub_payments.log', "[" . date('Y-m-d H:i:s') . "] SUCCESS Ref: $referencia Monto: $monto_usd \n", FILE_APPEND);

                return true;
            } else {
                $errorMsg = $wispResult['error'] ?? json_encode($wispResult['data'] ?? 'Error desconocido');
                error_log("[BDV AutoVerify] WispHub rechazó el pago: " . $errorMsg);
                $GLOBALS['bdv_falla_motivo'] = "El pago fue verificado por el banco, pero hubo un problema al aplicarlo a tu factura en WispHub.";
                return false;
            }
        } catch (\Exception $e) {
            error_log('[BDV AutoVerify] Excepción en WispHub: ' . $e->getMessage());
            $GLOBALS['bdv_falla_motivo'] = "Error de comunicación con el sistema de facturación. Intenta de nuevo.";
            return false;
        }
    } else {
        $GLOBALS['bdv_falla_motivo'] = "No se proporcionó un ID de servicio válido para aplicar el pago.";
        return false;
    }
}
