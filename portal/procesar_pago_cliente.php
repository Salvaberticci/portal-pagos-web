<?php
// portal/procesar_pago_cliente.php
require_once 'security_helper.php';
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

require '../paginas/conexion.php';
@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

// ── Crear tablas de integración WispHub si no existen ───────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `request_payload` TEXT,
    `response_payload` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `contract_id` INT DEFAULT NULL,
    `wisp_account_id` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'PENDING',
    `last_event` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contract_id` (`contract_id`),
    INDEX `idx_wisp_account_id` (`wisp_account_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

require_once 'bdv_autoverify_helper.php'; // incluye bdv_api_helper internamente

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula           = $_SESSION['cliente_cedula'];
    $nombre           = $_SESSION['cliente_nombre'];
    $telefono         = "";
    $fecha_pago       = $conn->real_escape_string($_POST['fecha_pago']);
    $metodo_pago      = $conn->real_escape_string($_POST['metodo_pago']);
    $id_banco_destino = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : null;
    $referencia       = isset($_POST['referencia']) ? trim($_POST['referencia']) : '';
    $concepto         = "Pago de mensualidad por portal";
    $id_contrato_asociado = isset($_POST['id_contrato']) ? trim($_POST['id_contrato']) : null;

    // 1. Rate Limiting (5 reportes por 10 minutos)
    if (!check_rate_limit('payment_submit', 5, 600)) {
        log_security_event('RATE_LIMIT_EXCEEDED', 'Intento de reporte de pago bloqueado por exceso de peticiones', $cedula);
        $_SESSION['pago_err'] = "Has enviado demasiados reportes de pago en poco tiempo. Por favor, espera unos minutos antes de intentar de nuevo.";
        header('Location: dashboard.php');
        exit;
    }

    // 2. CSRF Verification
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrf_token)) {
        log_security_event('CSRF_VIOLATION', 'Fallo de verificación CSRF al reportar pago', $cedula);
        $_SESSION['pago_err'] = "Petición de seguridad inválida. Por favor, recarga el asistente de pagos.";
        header('Location: dashboard.php');
        exit;
    }

    // 3. Strict Input Validation (Referencia)
    $referencia_clean = preg_replace('/[^a-zA-Z0-9]/', '', $referencia);
    if (empty($referencia_clean) || strlen($referencia_clean) < 6) {
        log_security_event('VALIDATION_FAILED', "La referencia de pago no es válida: '$referencia'", $cedula);
        $_SESSION['pago_err'] = "La referencia de pago debe ser alfanumérica y tener al menos 6 caracteres.";
        header('Location: dashboard.php');
        exit;
    }
    $referencia = $conn->real_escape_string($referencia_clean);

    // 3b. Propiedad del contrato ahora se delega a la sesión
    // Eliminamos la validación local de `contratos` porque usamos el service_id validado en dashboard.

    // 3c. Duplicate reference check (evitar doble pago con misma referencia)
    $dup_check = $conn->prepare("SELECT id_reporte FROM pagos_reportados WHERE referencia = ? AND id_banco_destino = ? AND estado = 'APROBADO' LIMIT 1");
    $dup_check->bind_param("si", $referencia, $id_banco_destino);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) {
        log_security_event('DUPLICATE_PAYMENT', "Intento de pago duplicado ref=$referencia banco=$id_banco_destino", $cedula);
        $_SESSION['pago_err'] = "Esta referencia de pago ya fue registrada anteriormente.";
        header('Location: dashboard.php');
        exit;
    }
    $dup_check->close();

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
        header('Location: dashboard.php');
        exit;
    }
    if ($monto_usd <= 0) {
        $_SESSION['pago_err'] = "El monto calculado en dólares debe ser mayor a 0.";
        header('Location: dashboard.php');
        exit;
    }

    // Manejo de archivo (Capture - Opcional)
    $capture_path = '';
    if (isset($_FILES['capture_pago']) && $_FILES['capture_pago']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/pagos/';
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
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $_SESSION['pago_err'] = "El archivo subido no es una imagen válida.";
            header('Location: dashboard.php');
            exit;
        }
    }

    // Insertar en pagos_reportados (siempre, estado inicial PENDIENTE)
    $sql_insert = "INSERT INTO pagos_reportados
        (cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
         id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
         meses_pagados, concepto, capture_path, id_contrato_asociado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_insert);
    $id_reporte_nuevo = 0;

    if ($stmt) {
        $stmt->bind_param("sssssisdddssss",
            $cedula, $nombre, $telefono, $fecha_pago, $metodo_pago,
            $id_banco_destino, $referencia, $monto_bs, $monto_usd, $tasa_dolar,
            $meses_str, $concepto, $capture_path, $id_contrato_asociado
        );

        if ($stmt->execute()) {
            $id_reporte_nuevo = $conn->insert_id;
            log_security_event('PAYMENT_SUBMITTED', "Pago reportado. ID Reporte: $id_reporte_nuevo, Ref: $referencia, Banco: $id_banco_destino, Monto: $monto_bs Bs / $monto_usd USD", $cedula);
        } else {
            log_security_event('DATABASE_ERROR', "Error insertando reporte de pago: " . $stmt->error, $cedula);
            $_SESSION['pago_err'] = "Error en base de datos al reportar el pago.";
            $stmt->close();
            $conn->close();
            header('Location: dashboard.php');
            exit;
        }
        $stmt->close();
    } else {
        log_security_event('DATABASE_ERROR', "Error preparando consulta de reporte de pago", $cedula);
        $_SESSION['pago_err'] = "Error de sistema al procesar el reporte.";
        $conn->close();
        header('Location: dashboard.php');
        exit;
    }

    // ─── Auto-verificación BDV ────────────────────────────────────────────────
    $auto_aprobado = false;
    if ($id_reporte_nuevo > 0) {
        $auto_aprobado = verificar_y_aprobar_pago_bdv(
            $conn,
            $id_banco_destino,
            $referencia,
            $monto_usd,
            $tasa_dolar,
            $fecha_pago,
            $id_contrato_asociado ?? 0,
            $id_reporte_nuevo,
            $capture_path,
            $metodo_pago,
            $meses_str,
            $concepto
        );
    }

    // Mensaje al cliente según resultado
    if ($auto_aprobado) {
        log_security_event('PAYMENT_AUTO_APPROVED', "Pago verificado automáticamente por API. Ref: $referencia, Monto: $monto_bs Bs", $cedula);
        // Pago aprobado automáticamente: mensaje verde
        $_SESSION['pago_msg'] = "✅ ¡Tu pago fue verificado automáticamente con el Banco de Venezuela! " .
            "Tu servicio ha sido actualizado al instante. Referencia: <strong>$referencia</strong>.";
        unset($_SESSION['pago_pendiente']);

} else {
        // Pago quedó pendiente: mensaje amarillo con motivo específico
        $razon = $GLOBALS['bdv_falla_motivo'] ?? 'La referencia o el monto no coinciden con los registros del banco.';
        log_security_event('PAYMENT_PENDING', "Pago enviado a revisión manual. Ref: $referencia, Monto: $monto_bs Bs. Motivo: $razon", $cedula);
        $_SESSION['pago_pendiente'] = "⏳ Tu pago está <strong>en revisión manual</strong>. Motivo: $razon Nuestro equipo lo verificará a la brevedad.";
        unset($_SESSION['pago_msg']);

        // Guardar motivo en la base de datos
        if ($id_reporte_nuevo > 0) {
            $sql_motivo = "UPDATE pagos_reportados SET motivo_rechazo = ? WHERE id_reporte = ?";
            $stmt_m = $conn->prepare($sql_motivo);
            if ($stmt_m) {
                $stmt_m->bind_param('si', $razon, $id_reporte_nuevo);
                $stmt_m->execute();
                $stmt_m->close();
            }
        }
    }

    $conn->close();
    header('Location: dashboard.php');
    exit;

} else {
    header('Location: dashboard.php');
    exit;
}
