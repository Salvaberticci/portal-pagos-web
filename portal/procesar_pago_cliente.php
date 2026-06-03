<?php
// portal/procesar_pago_cliente.php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

require '../paginas/conexion.php';
require_once 'bdv_autoverify_helper.php'; // incluye bdv_api_helper internamente

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula           = $_SESSION['cliente_cedula'];
    $nombre           = $_SESSION['cliente_nombre'];
    $telefono         = "";
    $fecha_pago       = $conn->real_escape_string($_POST['fecha_pago']);
    $metodo_pago      = $conn->real_escape_string($_POST['metodo_pago']);
    $id_banco_destino = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : null;
    $referencia       = isset($_POST['referencia']) ? $conn->real_escape_string($_POST['referencia']) : '';
    $concepto         = "Pago de mensualidad por portal";
    $id_contrato_asociado = isset($_POST['id_contrato']) ? intval($_POST['id_contrato']) : null;

    // Meses a pagar
    $meses_adelanto = isset($_POST['meses_adelanto']) ? intval($_POST['meses_adelanto']) : 0;

    // Montos
    $monto_usd  = isset($_POST['monto_usd'])  ? floatval($_POST['monto_usd'])  : 0.00;
    $tasa_dolar = isset($_POST['tasa_dolar']) ? floatval($_POST['tasa_dolar']) : 0.00;
    $monto_bs   = ($monto_usd > 0 && $tasa_dolar > 0) ? round($monto_usd * $tasa_dolar, 2) : 0.00;

    $meses_str = ($meses_adelanto > 0) ? "Pago/Adelanto de $meses_adelanto mes(es)" : "Abono a deuda";

    // Validaciones
    if (empty($metodo_pago) || empty($referencia) || empty($id_banco_destino)) {
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
        $stmt->bind_param("sssssisdddsssi",
            $cedula, $nombre, $telefono, $fecha_pago, $metodo_pago,
            $id_banco_destino, $referencia, $monto_bs, $monto_usd, $tasa_dolar,
            $meses_str, $concepto, $capture_path, $id_contrato_asociado
        );

        if ($stmt->execute()) {
            $id_reporte_nuevo = $conn->insert_id;
        } else {
            $_SESSION['pago_err'] = "Error en base de datos: " . $stmt->error;
            $stmt->close();
            $conn->close();
            header('Location: dashboard.php');
            exit;
        }
        $stmt->close();
    } else {
        $_SESSION['pago_err'] = "Error preparando la consulta.";
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
            $meses_str,
            $concepto
        );
    }

    // Mensaje al cliente según resultado
    if ($auto_aprobado) {
        $_SESSION['pago_msg'] = "✅ ¡Tu pago fue verificado automáticamente con el Banco de Venezuela! "
            . "Tu servicio ha sido actualizado al instante. Referencia: <strong>$referencia</strong>.";
    } else {
        $_SESSION['pago_msg'] = "Tu pago ha sido reportado exitosamente. "
            . "En breve será verificado por administración.";
    }

    $conn->close();
    header('Location: dashboard.php');
    exit;

} else {
    header('Location: dashboard.php');
    exit;
}
