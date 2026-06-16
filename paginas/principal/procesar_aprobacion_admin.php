<?php
// procesar_aprobacion_admin.php - Procesa la decisión del administrador sobre un reporte de pago
require_once '../conexion.php';

// ── Verificar sesión y rol ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id']) || !in_array(strtolower($_SESSION['rol'] ?? ''), ['admin', 'administrador'])) {
    header('HTTP/1.0 403 Forbidden');
    die("Acceso denegado");
}

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
        $stmt_orig = $conn->prepare("SELECT meses_pagados, concepto, capture_path FROM pagos_reportados WHERE id_reporte = ?");
        $stmt_orig->bind_param("i", $id_reporte);
        $stmt_orig->execute();
        $res_orig = $stmt_orig->get_result();
        $reporte = $res_orig->fetch_assoc();
        $stmt_orig->close();
        $justificacion = "[MENSUALIDAD] Aprobado desde reporte Web. Meses: " . $reporte['meses_pagados'] . ". Notas: " . $reporte['concepto'];
        $path_archivo = $reporte['capture_path'];

        $wispResult = null;
        $wispAccountId = '';
        $wispServiceId = '';
        $wispDate = date('Y-m-d H:i', strtotime($fecha_pago));

        // ─── PRIMERO: transacción local ───────────────────────────────────────
        $conn->begin_transaction();
        try {
            // 1. Insertar en cuentas_por_cobrar
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

            // Saldar deudas PENDIENTE/VENCIDO previas del mismo contrato
            $stmt_saldar = $conn->prepare("UPDATE cuentas_por_cobrar SET estado = 'PAGADO', fecha_pago = ?, referencia_pago = ?, id_banco = ? WHERE id_contrato = ? AND estado IN ('PENDIENTE', 'VENCIDO') AND id_cobro != ?");
            if ($stmt_saldar) {
                $stmt_saldar->bind_param('ssiii', $fecha_pago, $referencia, $id_banco, $id_contrato, $id_cobro_nuevo);
                $stmt_saldar->execute();
                $stmt_saldar->close();
            }

            // 2. Insertar en historial de cobros manuales
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

            // 3. Actualizar reporte original
            $sql_upd = "UPDATE pagos_reportados SET estado = 'APROBADO' WHERE id_reporte = ?";
            $stmt_upd = $conn->prepare($sql_upd);
            if (!$stmt_upd) throw new Exception("Error preparando update reporte: " . $conn->error);
            $stmt_upd->bind_param("i", $id_reporte);
            if (!$stmt_upd->execute()) {
                throw new Exception("Error actualizando estado del reporte: " . $stmt_upd->error);
            }
            $stmt_upd->close();

            $conn->commit();

            // ─── SEGUNDO: Registrar en WispHub (después del commit local) ─────
            if ($id_contrato > 0) {
                require_once __DIR__ . '/../../vendor/autoload.php';
                require_once __DIR__ . '/../../src/Services/WispHubClient.php';
                $wispConfig = include __DIR__ . '/../../config/wisp_hub.php';
                $wispClient = new \Services\WispHubClient($wispConfig);

                $q_link = $conn->prepare("SELECT wisp_account_id FROM wisp_hub_links WHERE contract_id = ? LIMIT 1");
                if ($q_link) {
                    $q_link->bind_param("i", $id_contrato);
                    $q_link->execute();
                    $r_link = $q_link->get_result();
                    if ($r_link && $row_link = $r_link->fetch_assoc()) {
                        $wispServiceId = $row_link['wisp_account_id'];
                    }
                    $q_link->close();
                }

                if (!empty($wispServiceId)) {
                    $wispResult = $wispClient->registerPaymentAndActivate(
                        $wispServiceId, $monto_total, $referencia, $wispDate,
                        \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA, false, ''
                    );
                } else {
                    $q_cedula = $conn->prepare("SELECT cedula FROM contratos WHERE id = ?");
                    $cedula = '';
                    if ($q_cedula) {
                        $q_cedula->bind_param("i", $id_contrato);
                        $q_cedula->execute();
                        $r_cedula = $q_cedula->get_result();
                        if ($r_cedula && $row = $r_cedula->fetch_assoc()) {
                            $cedula = $row['cedula'];
                        }
                        $q_cedula->close();
                    }
                    if (!empty($cedula)) {
                        $wispResult = $wispClient->registerPaymentAndActivate(
                            '', $monto_total, $referencia, $wispDate,
                            \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA, false, $cedula
                        );
                    }
                }

                if ($wispResult && in_array($wispResult['status'] ?? 0, [200, 201])) {
                    $wispAccountId = $wispResult['service_id'] ?? '';
                    if (!empty($wispAccountId) && empty($wispServiceId)) {
                        $stmt_cache = $conn->prepare(
                            "INSERT INTO wisp_hub_links (contract_id, wisp_account_id, status, created_at)
                             VALUES (?, ?, 'ACTIVE', NOW())
                             ON DUPLICATE KEY UPDATE wisp_account_id = VALUES(wisp_account_id), status = 'ACTIVE', updated_at = NOW()"
                        );
                        if ($stmt_cache) {
                            $stmt_cache->bind_param("is", $id_contrato, $wispAccountId);
                            $stmt_cache->execute();
                            $stmt_cache->close();
                        }
                    }
                    // Log WispHub exitoso
                    $sql_log = "INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (?, ?, ?, NOW())";
                    $stmt_log = $conn->prepare($sql_log);
                    if ($stmt_log) {
                        $logPayload = json_encode([
                            'service_id' => $wispAccountId, 'amount' => $monto_total,
                            'reference' => $referencia, 'date' => $wispDate,
                        ]);
                        $logResponse = json_encode($wispResult);
                        $stmt_log->bind_param("iss", $id_reporte, $logPayload, $logResponse);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                } elseif ($wispResult && $wispResult['status'] !== 404) {
                    error_log("[AdminAprobacion] WispHub rechazó el pago local #$id_reporte: " . ($wispResult['error'] ?? json_encode($wispResult['data'] ?? '')));
                }
            }

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
            $class = "danger";
        }
    } elseif ($accion === 'RECHAZAR') {
        $motivo = $_POST['motivo'] ?? 'Sin motivo especificado.';
        
        $sql_f = "SELECT capture_path FROM pagos_reportados WHERE id_reporte = ?";
        $stmt_f = $conn->prepare($sql_f);
        if ($stmt_f) {
            $stmt_f->bind_param("i", $id_reporte);
            $stmt_f->execute();
            $res_f = $stmt_f->get_result();
            if ($res_f && $res_f->num_rows > 0) {
                $reporte = $res_f->fetch_assoc();
                $file_path = "../../" . $reporte['capture_path'];
                if (!empty($reporte['capture_path']) && file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $stmt_f->close();
        }

        $stmt_rej = $conn->prepare("UPDATE pagos_reportados SET estado = 'RECHAZADO', motivo_rechazo = ? WHERE id_reporte = ?");
        if ($stmt_rej) {
            $stmt_rej->bind_param("si", $motivo, $id_reporte);
            if ($stmt_rej->execute()) {
                $message = "El reporte ha sido rechazado correctamente y la imagen eliminada.";
                $class = "warning";
            } else {
                $message = "Error al rechazar el reporte: " . $stmt_rej->error;
                $class = "danger";
            }
            $stmt_rej->close();
        } else {
            $message = "Error al rechazar el reporte: " . $conn->error;
            $class = "danger";
        }
    } elseif ($accion === 'ELIMINAR') {
        $sql_f = "SELECT capture_path FROM pagos_reportados WHERE id_reporte = ?";
        $stmt_f = $conn->prepare($sql_f);
        if ($stmt_f) {
            $stmt_f->bind_param("i", $id_reporte);
            $stmt_f->execute();
            $res_f = $stmt_f->get_result();
            if ($res_f && $res_f->num_rows > 0) {
                $reporte = $res_f->fetch_assoc();
                $file_path = "../../" . $reporte['capture_path'];
                if (!empty($reporte['capture_path']) && file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $stmt_f->close();
        }

        $stmt_del = $conn->prepare("DELETE FROM pagos_reportados WHERE id_reporte = ?");
        if ($stmt_del) {
            $stmt_del->bind_param("i", $id_reporte);
            if ($stmt_del->execute()) {
                $message = "Reporte e imagen eliminados permanentemente.";
                $class = "info";
            } else {
                $message = "Error al eliminar el reporte: " . $stmt_del->error;
                $class = "danger";
            }
            $stmt_del->close();
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
