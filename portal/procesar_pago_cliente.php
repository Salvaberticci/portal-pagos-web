<?php
// portal/procesar_pago_cliente.php
require_once 'security_helper.php';
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

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

// 3. Validar referencia
$referencia_clean = preg_replace('/[^a-zA-Z0-9]/', '', $referencia);
if (empty($referencia_clean) || strlen($referencia_clean) < 6) {
    $_SESSION['pago_err'] = "La referencia debe tener al menos 6 caracteres alfanuméricos.";
    header('Location: ' . $redirect_url);
    exit;
}
$referencia = $referencia_clean;

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
    $wispClient = new \Services\WispHubClient($wispConfig);

    $wispDate = date('Y-m-d H:i', strtotime($fecha_pago));

    // Si es Zelle (sin verificacion), o si viene del nuevo flujo BDV ya verificado,
    // registrar directo en WispHub
    $es_zelle = ($metodo_pago === 'Zelle');
    $verificacion_data = isset($_POST['verificacion_data']) ? json_decode($_POST['verificacion_data'], true) : null;

    if ($es_zelle || $verificacion_data) {
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
    if ($wispResult && in_array($wispResult['status'] ?? 0, [200, 201])) {
        // Construir mensaje detallado segun la distribucion
        $msg_parts = ["Tu pago fue registrado exitosamente."];

        $amount_applied = $wispResult['amount_applied'] ?? $monto_usd;
        $amount_unused  = $wispResult['amount_unused'] ?? 0;

        $pagos_count = count($wispResult['payments_registered'] ?? []);

        if ($pagos_count > 0) {
            $msg_parts[] = "Se aplicaron <strong>$" . number_format($amount_applied, 2) . " USD</strong> a $pagos_count recibo(s).";
        }

        if ($amount_unused > 0) {
            $msg_parts[] = "Te queda un <strong>SALDO A FAVOR de $" . number_format($amount_unused, 2) . " USD</strong> para tu pr&oacute;ximo recibo.";
        }

        if ($amount_applied < $monto_usd && $amount_unused <= 0) {
            $msg_parts[] = "Se aplic&oacute; <strong>$" . number_format($amount_applied, 2) . " USD</strong> como abono a tu deuda.";
        }

        // Si se seleccionaron recibos pero no se aplico a todas
        $selected_count = count($invoice_ids);
        if ($selected_count > 0 && $pagos_count < $selected_count) {
            $msg_parts[] = "Nota: solo se pagaron $pagos_count de $selected_count recibo(s) seleccionado(s) con el monto disponible.";
        }

        $msg_parts[] = "Referencia: <strong>$referencia</strong>.";
        $_SESSION['pago_msg'] = implode(' ', $msg_parts);
        unset($_SESSION['pago_err']);

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
