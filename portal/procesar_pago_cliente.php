<?php
// portal/procesar_pago_cliente.php
require_once 'security_helper.php';
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

require_once 'bdv_autoverify_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula           = $_SESSION['cliente_cedula'];
    $nombre           = $_SESSION['cliente_nombre'];
    $fecha_pago       = $_POST['fecha_pago'] ?? date('Y-m-d');
    $metodo_pago      = $_POST['metodo_pago'] ?? '';
    $id_banco_destino = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : null;
    $referencia       = isset($_POST['referencia']) ? trim($_POST['referencia']) : '';
    $concepto         = "Pago de mensualidad por portal";
    $id_contrato_asociado = isset($_POST['id_contrato']) ? trim($_POST['id_contrato']) : null;

    $redirect_url = empty($id_contrato_asociado) ? 'dashboard.php' : 'pago.php?id_contrato=' . urlencode($id_contrato_asociado);

    // 1. Rate Limiting (5 reportes por 10 minutos)
    if (!check_rate_limit('payment_submit', 5, 600)) {
        log_security_event('RATE_LIMIT_EXCEEDED', 'Intento de reporte de pago bloqueado por exceso de peticiones', $cedula);
        $_SESSION['pago_err'] = "Has enviado demasiados reportes de pago en poco tiempo. Por favor, espera unos minutos antes de intentar de nuevo.";
        header('Location: ' . $redirect_url);
        exit;
    }

    // 2. CSRF Verification
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrf_token)) {
        log_security_event('CSRF_VIOLATION', 'Fallo de verificación CSRF al reportar pago', $cedula);
        $_SESSION['pago_err'] = "Petición de seguridad inválida. Por favor, recarga el asistente de pagos.";
        header('Location: ' . $redirect_url);
        exit;
    }

    // 3. Strict Input Validation (Referencia)
    $referencia_clean = preg_replace('/[^a-zA-Z0-9]/', '', $referencia);
    if (empty($referencia_clean) || strlen($referencia_clean) < 6) {
        log_security_event('VALIDATION_FAILED', "La referencia de pago no es válida: '$referencia'", $cedula);
        $_SESSION['pago_err'] = "La referencia de pago debe ser alfanumérica y tener al menos 6 caracteres.";
        header('Location: ' . $redirect_url);
        exit;
    }
    $referencia = $referencia_clean;

    // Meses a pagar
    $meses_adelanto = isset($_POST['meses_adelanto']) ? intval($_POST['meses_adelanto']) : 0;

    // Montos
    $monto_usd  = isset($_POST['monto_usd'])  ? floatval($_POST['monto_usd'])  : 0.00;
    $tasa_dolar = isset($_POST['tasa_dolar']) ? floatval($_POST['tasa_dolar']) : 0.00;
    $monto_bs   = ($monto_usd > 0 && $tasa_dolar > 0) ? round($monto_usd * $tasa_dolar, 2) : 0.00;

    if ($cedula === TEST_USER_CEDULA) {
        $monto_bs_int = round($monto_bs);
        if (abs($monto_bs - $monto_bs_int) < 0.2) {
            $monto_bs = $monto_bs_int;
        }
        if ($monto_bs <= 0) {
            $monto_bs = 1.00;
        }
        $monto_usd = $monto_bs / ($tasa_dolar > 0 ? $tasa_dolar : 1);
    }

    $meses_str = ($meses_adelanto > 0) ? "Pago/Adelanto de $meses_adelanto mes(es)" : "Abono a deuda";

    // Validaciones
    if (empty($metodo_pago) || empty($referencia) || empty($id_banco_destino)) {
        log_security_event('VALIDATION_FAILED', 'Campos requeridos faltantes al reportar pago', $cedula);
        $_SESSION['pago_err'] = "Método de Pago, Banco y Referencia son obligatorios.";
        header('Location: ' . $redirect_url);
        exit;
    }
    if ($monto_usd <= 0) {
        $_SESSION['pago_err'] = "El monto calculado en dólares debe ser mayor a 0.";
        header('Location: ' . $redirect_url);
        exit;
    }

    // Manejo de archivo (Capture - Opcional)
    $capture_path = '';
    $upload_dir = '../uploads/pagos/';
    if (isset($_FILES['capture_pago']) && $_FILES['capture_pago']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_name   = uniqid('portal_') . '_' . basename($_FILES['capture_pago']['name']);
        $file_name   = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
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
            $_SESSION['pago_err'] = "El archivo subido no es una imagen válida.";
            header('Location: ' . $redirect_url);
            exit;
        }
    }

    // ─── Auto-verificación BDV ────────────────────────────────────────────────
    $auto_aprobado = verificar_y_aprobar_pago_bdv(
        $id_banco_destino,
        $referencia,
        $monto_usd,
        $tasa_dolar,
        $fecha_pago,
        $id_contrato_asociado ?? '',
        $capture_path,
        $metodo_pago,
        $meses_str,
        $concepto
    );

    if ($auto_aprobado) {
        log_security_event('PAYMENT_AUTO_APPROVED', "Pago verificado automáticamente por API. Ref: $referencia, Monto: $monto_bs Bs", $cedula);
        $_SESSION['pago_msg'] = "✅ ¡Tu pago fue verificado automáticamente con el Banco de Venezuela! " .
            "Tu servicio ha sido actualizado al instante. Referencia: <strong>$referencia</strong>.";
        unset($_SESSION['pago_err']);
        
        // Redirigir al dashboard si fue exitoso
        header('Location: dashboard.php');
        exit;

    } else {
        // Hubo error con el banco o wisphub
        $razon = $GLOBALS['bdv_falla_motivo'] ?? 'La referencia o el monto no coinciden con los registros del banco.';
        log_security_event('PAYMENT_FAILED_API', "Pago rebotado. Ref: $referencia. Motivo: $razon", $cedula);
        $_SESSION['pago_err'] = "❌ Hubo un error al procesar tu pago: <strong>$razon</strong> Por favor, verifica e intenta nuevamente.";
        
        // Como hubo error, no queremos dejar basura. Borramos el capture si subió alguno
        if (!empty($capture_path) && file_exists(__DIR__ . '/../' . $capture_path)) {
            @unlink(__DIR__ . '/../' . $capture_path);
        }

        // Devolver al formulario
        header('Location: ' . $redirect_url);
        exit;
    }

} else {
    header('Location: dashboard.php');
    exit;
}
