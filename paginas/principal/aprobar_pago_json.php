<?php
// aprobar_pago_json.php - Procesa la aprobación de un reporte de pago y retorna JSON
require_once '../conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id']) || !in_array(strtolower($_SESSION['rol'] ?? ''), ['admin', 'administrador'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Para simplificar, aceptamos tanto JSON como POST normal
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        $data = $_POST;
    }

    $id_reporte = intval($data['id_reporte'] ?? 0);
    $accion = $data['accion'] ?? 'APROBAR';

    if ($id_reporte <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de reporte inválido.']);
        exit;
    }

    if ($accion === 'APROBAR') {
        $id_contrato = intval($data['id_contrato'] ?? 0);
        $monto_total = floatval($data['monto_total'] ?? 0);
        $fecha_pago = $conn->real_escape_string($data['fecha_pago'] ?? date('Y-m-d'));
        $referencia = $conn->real_escape_string($data['referencia'] ?? '');
        $id_banco = intval($data['id_banco'] ?? 0);

        if ($monto_total <= 0) {
            echo json_encode(['success' => false, 'message' => 'Monto inválido para aprobación.']);
            exit;
        }

        // 1. Obtener detalles del reporte original para el historial
        $sql_orig = "SELECT meses_pagados, concepto, capture_path FROM pagos_reportados WHERE id_reporte = ?";
        $stmt_orig = $conn->prepare($sql_orig);
        $stmt_orig->bind_param("i", $id_reporte);
        $stmt_orig->execute();
        $res_orig = $stmt_orig->get_result();
        if (!$res_orig || $res_orig->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No se encontró el reporte original.']);
            exit;
        }
        $reporte = $res_orig->fetch_assoc();
        $justificacion = "[MENSUALIDAD] Aprobado desde Conciliación Bancaria. Meses: " . $reporte['meses_pagados'] . ". Notas: " . $reporte['concepto'];
        $path_archivo = $reporte['capture_path'];

        $conn->begin_transaction();
        try {
            if ($id_contrato > 0) {
                // Registrar pago en CxC solo si hay contrato
                $sql_cxc = "INSERT INTO cuentas_por_cobrar 
                    (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, fecha_pago, referencia_pago, id_banco, origen, capture_pago)
                    VALUES (?, CURRENT_DATE, CURRENT_DATE, ?, 'PAGADO', ?, ?, ?, 'LINK', ?)";

                $stmt_cxc = $conn->prepare($sql_cxc);
                if (!$stmt_cxc)
                    throw new Exception("Error preparando inserción: " . $conn->error);

                $stmt_cxc->bind_param("idssis", $id_contrato, $monto_total, $fecha_pago, $referencia, $id_banco, $path_archivo);
                if (!$stmt_cxc->execute())
                    throw new Exception("Error ejecutando inserción: " . $stmt_cxc->error);

                $id_cobro_nuevo = $conn->insert_id;

                // Insertar en historial de cobros manuales
                $sql_hist = "INSERT INTO cobros_manuales_historial 
                    (id_cobro_cxc, autorizado_por, justificacion, monto_cargado)
                    VALUES (?, 'SISTEMA (CONCILIACIÓN)', ?, ?)";

                $stmt_hist = $conn->prepare($sql_hist);
                if (!$stmt_hist)
                    throw new Exception("Error preparando historial: " . $conn->error);

                $stmt_hist->bind_param("isd", $id_cobro_nuevo, $justificacion, $monto_total);
                if (!$stmt_hist->execute())
                    throw new Exception("Error ejecutando historial: " . $stmt_hist->error);
            }

            // 4. Actualizar reporte original siempre (marcar como aprobado)
            $stmt_upd = $conn->prepare("UPDATE pagos_reportados SET estado = 'APROBADO' WHERE id_reporte = ?");
            if (!$stmt_upd) throw new Exception("Error preparando update: " . $conn->error);
            $stmt_upd->bind_param("i", $id_reporte);
            if (!$stmt_upd->execute()) {
                throw new Exception("Error actualizando estado del reporte: " . $stmt_upd->error);
            }
            $stmt_upd->close();

            $conn->commit();
            $msg = ($id_contrato > 0) ? '¡Pago aprobado y registrado exitosamente!' : '¡Reporte aprobado! (Sin registro en CxC al no tener contrato)';
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no soportada por este endpoint.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}

$conn->close();
?>