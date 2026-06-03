<?php
// portal/procesar_pago_cliente.php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

require '../paginas/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula         = $_SESSION['cliente_cedula'];
    $nombre         = $_SESSION['cliente_nombre'];
    $telefono       = ""; // Opcional, o sacar de DB si lo tenemos
    $fecha_pago     = $conn->real_escape_string($_POST['fecha_pago']);
    $metodo_pago    = $conn->real_escape_string($_POST['metodo_pago']);
    $id_banco_destino = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : null;
    $referencia     = isset($_POST['referencia']) ? $conn->real_escape_string($_POST['referencia']) : '';
    $concepto       = "Pago de mensualidad por portal";
    $id_contrato_asociado = isset($_POST['id_contrato']) ? intval($_POST['id_contrato']) : null;

    // Meses a pagar (1, 2, o 3)
    $meses_adelanto = isset($_POST['meses_adelanto']) ? intval($_POST['meses_adelanto']) : 0;
    
    // Monto calculado desde el formulario
    $monto_usd  = isset($_POST['monto_usd']) ? floatval($_POST['monto_usd']) : 0.00;
    $tasa_dolar = isset($_POST['tasa_dolar']) ? floatval($_POST['tasa_dolar']) : 0.00;
    $monto_bs   = ($monto_usd > 0 && $tasa_dolar > 0) ? round($monto_usd * $tasa_dolar, 2) : 0.00;

    // Para la columna 'meses_pagados', generamos un string representativo (ej: "Pago 1 mes(es)")
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

    // Manejo de archivo (Capture)
    $capture_path = '';
    if (isset($_FILES['capture_pago']) && $_FILES['capture_pago']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/pagos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_name = uniqid('portal_') . '_' . basename($_FILES['capture_pago']['name']);
        $file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
        $target_file = $upload_dir . $file_name;

        $check = getimagesize($_FILES['capture_pago']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['capture_pago']['tmp_name'], $target_file)) {
                $capture_path = 'uploads/pagos/' . $file_name; // Ruta relativa para el panel admin
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
    } else {
        $_SESSION['pago_err'] = "Debe subir la imagen (capture) del pago.";
        header('Location: dashboard.php');
        exit;
    }

    // Insertar en pagos_reportados
    $sql_insert = "INSERT INTO pagos_reportados
        (cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
         id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
         meses_pagados, concepto, capture_path, id_contrato_asociado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_insert);
    if ($stmt) {
        $stmt->bind_param("sssssisdddsssi",
            $cedula, $nombre, $telefono, $fecha_pago, $metodo_pago,
            $id_banco_destino, $referencia, $monto_bs, $monto_usd, $tasa_dolar,
            $meses_str, $concepto, $capture_path, $id_contrato_asociado
        );

        if ($stmt->execute()) {
            $_SESSION['pago_msg'] = "Tu pago ha sido reportado exitosamente. En breve será verificado por administración.";
        } else {
            $_SESSION['pago_err'] = "Error en base de datos: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['pago_err'] = "Error preparando la consulta.";
    }

    $conn->close();
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: dashboard.php');
    exit;
}
