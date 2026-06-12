<?php
// procesar_aprobacion_admin.php - Procesa la decisión del administrador sobre un reporte de pago
require_once '../conexion.php';

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

        // ─── PRIMERO: Registrar en WispHub (antes del commit local) ──────────
        $wispResult = null;
        $wispAccountId = '';
        if ($id_contrato > 0) {
            require_once __DIR__ . '/../../vendor/autoload.php';
            require_once __DIR__ . '/../../src/Services/WispHubClient.php';
            $wispConfig = include __DIR__ . '/../../config/wisp_hub.php';
            $wispClient = new \Services\WispHubClient($wispConfig);

            $q_link = $conn->query("SELECT wisp_account_id FROM wisp_hub_links WHERE contract_id = $id_contrato AND wisp_account_id != '' ORDER BY id DESC LIMIT 1");
            if ($q_link && $link_row = $q_link->fetch_assoc()) {
                $wispAccountId = $link_row['wisp_account_id'];
            }

            if (!empty($wispAccountId)) {
                $wispDate = date('Y-m-d H:i', strtotime($fecha_pago));
                $wispResult = $wispClient->registerPaymentAndActivate(
                    $wispAccountId,
                    $monto_total,
                    $referencia,
                    $wispDate
                );
                if (!in_array($wispResult['status'] ?? 0, [200, 201])) {
                    throw new Exception("WispHub rechazó el pago: "
                        . ($wispResult['error'] ?? json_encode($wispResult['data'] ?? '')));
                }
            }
        }

        // ─── SEGUNDO: transacción local ──────────────────────────────────────
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

            // 5. Log WispHub (dentro de la transacción)
            if ($wispResult !== null) {
                $sql_log = "INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (?, ?, ?, NOW())";
                $stmt_log = $conn->prepare($sql_log);
                if ($stmt_log) {
                    $logPayload = json_encode([
                        'service_id' => $wispAccountId,
                        'amount' => $monto_total,
                        'reference' => $referencia,
                        'date' => $wispDate ?? date('Y-m-d H:i', strtotime($fecha_pago)),
                    ]);
                    $logResponse = json_encode($wispResult);
                    $stmt_log->bind_param("iss", $id_reporte, $logPayload, $logResponse);
                    $stmt_log->execute();
                    $stmt_log->close();
                }
            }

            $conn->commit();
            
            // --- Eliminar el capture del servidor tras aprobación ---
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
            if ($wispAccountId) {
                $message .= " ATENCIÓN: WispHub ya registró el pago para servicio $wispAccountId. Revisión manual requerida.";
                error_log('[AdminAprobacion] ERROR transacción DESPUÉS de WispHub OK para service ' . $wispAccountId . ': ' . $e->getMessage());
            }
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
