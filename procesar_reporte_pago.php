<?php
// procesar_reporte_pago.php - Procesa el envío del formulario público
require_once 'paginas/conexion.php';
require_once 'portal/bdv_autoverify_helper.php'; // Auto-verificación BDV

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula         = $conn->real_escape_string($_POST['cedula']);
    $nombre         = $conn->real_escape_string($_POST['nombre']);
    $telefono       = $conn->real_escape_string($_POST['telefono']);
    $fecha_pago     = $conn->real_escape_string($_POST['fecha_pago']);
    $metodo_pago    = $conn->real_escape_string($_POST['metodo_pago']);
    $id_banco_destino = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : null;
    $referencia     = isset($_POST['referencia']) ? $conn->real_escape_string($_POST['referencia']) : '';
    $meses          = isset($_POST['meses']) ? implode(', ', $_POST['meses']) : '';
    $concepto       = isset($_POST['concepto']) ? $conn->real_escape_string($_POST['concepto']) : '';

    // Monto en USD (el valor que realmente se guarda)
    $monto_usd  = isset($_POST['monto_usd'])   ? floatval($_POST['monto_usd'])   : 0.00;
    $tasa_dolar = isset($_POST['tasa_dolar'])   ? floatval($_POST['tasa_dolar'])  : 0.00;
    // monto_bs es solo referencial — calculado a partir de USD x tasa
    $monto_bs   = ($monto_usd > 0 && $tasa_dolar > 0) ? round($monto_usd * $tasa_dolar, 2) : 0.00;

    if ($cedula === 'V99999999') {
        $monto_bs_int = round($monto_bs);
        if (abs($monto_bs - $monto_bs_int) < 0.2) {
            $monto_bs = $monto_bs_int;
        }
        if ($monto_bs <= 0) {
            $monto_bs = 1.00;
        }
        $monto_usd = $monto_bs / ($tasa_dolar > 0 ? $tasa_dolar : 1);
    }

    // VALIDACIÓN BACKEND
    if (empty($metodo_pago) || empty($referencia) || empty($id_banco_destino)) {
        die("Error: El Método de Pago, el Banco y el Número de Referencia son obligatorios.");
    }
    if ($monto_usd <= 0) {
        die("Error: Debe ingresar un monto válido en dólares.");
    }

    // 1. Vincular con contrato existente
    $id_contrato_asociado = isset($_POST['id_contrato_asociado']) && !empty($_POST['id_contrato_asociado'])
                            ? intval($_POST['id_contrato_asociado']) : null;

    if (!$id_contrato_asociado) {
        $sql_check = "SELECT id FROM contratos WHERE cedula = '$cedula' LIMIT 1";
        $res_check = $conn->query($sql_check);
        if ($res_check && $res_check->num_rows > 0) {
            $id_contrato_asociado = $res_check->fetch_assoc()['id'];
        }
    }

    // 2. Manejo de archivo (Capture)
    $capture_path = '';
    if (isset($_FILES['capture_pago']) && $_FILES['capture_pago']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/pagos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_name = uniqid('pago_') . '_' . basename($_FILES['capture_pago']['name']);
        $file_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $file_name);
        $target_file = $upload_dir . $file_name;

        $check = getimagesize($_FILES['capture_pago']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['capture_pago']['tmp_name'], $target_file)) {
                $capture_path = $upload_dir . $file_name; // ruta relativa desde root
            } else {
                die("Error al subir el comprobante.");
            }
        } else {
            die("El archivo subido no es una imagen válida.");
        }
    } else {
        die("Debe subir un comprobante de pago.");
    }

    // 3. Insertar en pagos_reportados
    $sql_insert = "INSERT INTO pagos_reportados
        (cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
         id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
         meses_pagados, concepto, capture_path, id_contrato_asociado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_insert);
    if (!$stmt) die("Error preparando insert: " . $conn->error);
    $stmt->bind_param("sssssisdddsss" . "i",
        $cedula, $nombre, $telefono, $fecha_pago, $metodo_pago,
        $id_banco_destino, $referencia, $monto_bs, $monto_usd, $tasa_dolar,
        $meses, $concepto, $capture_path, $id_contrato_asociado
    );

    $id_reporte_nuevo = 0;
    if ($stmt->execute()) {
        $id_reporte_nuevo = $conn->insert_id;
        $mensaje_exito = "¡Gracias! Tu reporte de pago ha sido enviado correctamente. Será verificado por nuestro equipo administrativo a la brevedad posible.";
    } else {
        $mensaje_err = "Error al procesar el reporte: " . $stmt->error;
    }

    $stmt->close();

    // ─── Auto-verificación BDV ─────────────────────────────────────────────
    if ($id_reporte_nuevo > 0) {
        $id_contrato_para_bdv = $id_contrato_asociado ?? 0;
        $tasa_dolar_val       = isset($_POST['tasa_dolar']) ? floatval($_POST['tasa_dolar']) : 0.00;
        $monto_usd_val        = isset($_POST['monto_usd'])  ? floatval($_POST['monto_usd'])  : 0.00;
        $id_banco_val         = isset($_POST['id_banco_destino']) ? intval($_POST['id_banco_destino']) : 0;

        $auto_aprobado = verificar_y_aprobar_pago_bdv(
            $conn,
            $id_banco_val,
            $referencia,
            $monto_usd_val,
            $tasa_dolar_val,
            $fecha_pago,
            $id_contrato_para_bdv,
            $id_reporte_nuevo,
            $capture_path,
            $meses,
            $concepto
        );

    // After auto verification attempt
    if (isset($auto_aprobado) && $auto_aprobado) {
        // Auto-approval succeeded, $mensaje_exito already set earlier.
        $mensaje_exito = "✅ ¡Tu pago fue verificado automáticamente con el Banco de Venezuela! "
                . "Tu servicio ha sido actualizado al instante. Referencia: <strong>" . htmlspecialchars($referencia) . "</strong>.";
    } else {
        // No auto‑approval: set pending message with specific reason
        $razon = $GLOBALS['bdv_falla_motivo'] ?? "la referencia o el monto no coinciden con los datos del banco";
        $mensaje_pendiente = "🔔 Pago pendiente de revisión: $razon. Nuestro equipo lo verificará manualmente.";
        $mensaje_exito = $mensaje_pendiente;
        // Update DB with pending reason for manual review
        $sql_update = "UPDATE pagos_reportados SET motivo_rechazo = ? WHERE id_reporte = ?";
        $stmt_upd = $conn->prepare($sql_update);
        if ($stmt_upd) {
            $motivo = $razon;
            $stmt_upd->bind_param("si", $motivo, $id_reporte_nuevo);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
    }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Enviado - Wireless Supply</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .success-card {
            max-width: 500px;
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .icon-box {
            background: #d1e7dd;
            color: #0f5132;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }
    </style>
</head>

<body>
    <div class="card success-card p-4 text-center">
<?php if (isset($mensaje_exito)): ?>
    <div class="icon-box <?php echo (isset($auto_aprobado) && $auto_aprobado) ? '' : 'bg-warning-subtle text-warning'; ?>">
        <i class="fas <?php echo (isset($auto_aprobado) && $auto_aprobado) ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
    </div>
    <h3 class="fw-bold mb-3 <?php echo (isset($auto_aprobado) && $auto_aprobado) ? 'text-success' : 'text-warning'; ?>"><?php echo (isset($auto_aprobado) && $auto_aprobado) ? 'Envío Exitoso' : 'Pago Pendiente'; ?></h3>
    <p class="text-muted" id="msg-box">
        <?php echo $mensaje_exito; ?>
    </p>
    <hr>
    <a href="reportar_pago.php" class="btn btn-primary">Reportar otro pago</a>
    <script>
        // Hide the message after 5 seconds
        setTimeout(() => {
            const box = document.getElementById('msg-box');
            if (box) box.style.transition = 'opacity 0.5s';
            if (box) box.style.opacity = '0';
        }, 5000);
    </script>
<?php else: ?>
            <div class="icon-box bg-danger-subtle text-danger"><i class="fas fa-times"></i></div>
            <h3 class="fw-bold mb-3 text-danger">Error</h3>
            <p class="text-muted">
                <?php echo $mensaje_err; ?>
            </p>
            <a href="reportar_pago.php" class="btn btn-secondary">Intentar nuevamente</a>
        <?php endif; ?>
    </div>
</body>

</html>