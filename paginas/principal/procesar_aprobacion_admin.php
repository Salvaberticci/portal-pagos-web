<?php
// procesar_aprobacion_admin.php - Procesa la decisión del administrador sobre un reporte de pago
require_once '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reporte = intval($_POST['id_reporte']);
    $accion = isset($_POST['accion']) ? $_POST['accion'] : 'APROBAR';

    $message = "Operación no reconocida.";
    $class = "danger";

    if ($accion === 'APROBAR') {
        $id_contrato = intval($_POST['id_contrato']);
        $monto_total = floatval($_POST['monto_total']);
        $fecha_pago = $conn->real_escape_string($_POST['fecha_pago']);
        $referencia = $conn->real_escape_string($_POST['referencia']);
        $id_banco = intval($_POST['id_banco']);

        // 1. Obtener detalles del reporte original para el historial
        $sql_orig = "SELECT meses_pagados, concepto, capture_path FROM pagos_reportados WHERE id_reporte = $id_reporte";
        $res_orig = $conn->query($sql_orig);
        $reporte = $res_orig->fetch_assoc();
        $justificacion = "[MENSUALIDAD] Aprobado desde reporte Web. Meses: " . $reporte['meses_pagados'] . ". Notas: " . $reporte['concepto'];
        $path_archivo = $reporte['capture_path'];

        $conn->begin_transaction();
        try {
            // 2. Insertar en cuentas_por_cobrar
            $sql_cxc = "INSERT INTO cuentas_por_cobrar 
                (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, fecha_pago, referencia_pago, id_banco, origen, capture_pago)
                VALUES (?, CURRENT_DATE, CURRENT_DATE, ?, 'PAGADO', ?, ?, ?, 'LINK', ?)";

            $stmt_cxc = $conn->prepare($sql_cxc);
            if (!$stmt_cxc) {
                throw new Exception("Error preparando inserción CxC: " . $conn->error);
            }

            $stmt_cxc->bind_param("idssis", $id_contrato, $monto_total, $fecha_pago, $referencia, $id_banco, $path_archivo);
            if (!$stmt_cxc->execute()) {
                throw new Exception("Error ejecutando inserción CxC: " . $stmt_cxc->error);
            }
            $id_cobro_nuevo = $conn->insert_id;

            // 3. Insertar en historial de cobros manuales
            $sql_hist = "INSERT INTO cobros_manuales_historial 
                (id_cobro_cxc, autorizado_por, justificacion, monto_cargado)
                VALUES (?, 'SISTEMA (APROBACIÓN WEB)', ?, ?)";

            $stmt_hist = $conn->prepare($sql_hist);
            if (!$stmt_hist) {
                throw new Exception("Error preparando historial: " . $conn->error);
            }

            $stmt_hist->bind_param("isd", $id_cobro_nuevo, $justificacion, $monto_total);
            if (!$stmt_hist->execute()) {
                throw new Exception("Error ejecutando historial: " . $stmt_hist->error);
            }

            // 4. Actualizar reporte original
            $sql_upd = "UPDATE pagos_reportados SET estado = 'APROBADO' WHERE id_reporte = $id_reporte";
            if (!$conn->query($sql_upd)) {
                throw new Exception("Error actualizando estado del reporte: " . $conn->error);
            }

            $conn->commit();
            
            // --- NUEVO: Eliminar el capture del servidor tras aprobación para ahorrar espacio ---
            if (!empty($path_archivo)) {
                $full_file_path = "../../" . $path_archivo;
                if (file_exists($full_file_path)) {
                    unlink($full_file_path);
                }
            }

            $message = "¡Pago aprobado y registrado exitosamente! El comprobante ha sido eliminado para ahorrar espacio.";
            $class = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error en el proceso de aprobación: " . $e->getMessage();
            $class = "danger";
        }
    } elseif ($accion === 'RECHAZAR') {
        $motivo = isset($_POST['motivo']) ? $conn->real_escape_string($_POST['motivo']) : 'Sin motivo especificado.';
        
        // Obtener ruta del archivo antes de actualizar (para borrarlo)
        $sql_f = "SELECT capture_path FROM pagos_reportados WHERE id_reporte = $id_reporte";
        $res_f = $conn->query($sql_f);
        if ($res_f && $res_f->num_rows > 0) {
            $reporte = $res_f->fetch_assoc();
            $file_path = "../../" . $reporte['capture_path'];
            if (!empty($reporte['capture_path']) && file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql_rej = "UPDATE pagos_reportados SET estado = 'RECHAZADO', motivo_rechazo = '$motivo' WHERE id_reporte = $id_reporte";

        if ($conn->query($sql_rej)) {
            $message = "El reporte ha sido rechazado correctamente y la imagen eliminada.";
            $class = "warning";
        } else {
            $message = "Error al rechazar el reporte: " . $conn->error;
            $class = "danger";
        }
    } elseif ($accion === 'ELIMINAR') {
        // Obtener ruta del archivo antes de borrar el registro
        $sql_f = "SELECT capture_path FROM pagos_reportados WHERE id_reporte = $id_reporte";
        $res_f = $conn->query($sql_f);
        if ($res_f && $res_f->num_rows > 0) {
            $reporte = $res_f->fetch_assoc();
            $file_path = "../../" . $reporte['capture_path'];
            if (!empty($reporte['capture_path']) && file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql_del = "DELETE FROM pagos_reportados WHERE id_reporte = $id_reporte";
        if ($conn->query($sql_del)) {
            $message = "Reporte e imagen eliminados permanentemente.";
            $class = "info";
        } else {
            $message = "Error al eliminar el reporte: " . $conn->error;
            $class = "danger";
        }
    }

    $conn->close();
    header("Location: aprobar_pagos.php?maintenance_done=1&message=" . urlencode($message) . "&class=" . $class);
    exit();
}
?>
