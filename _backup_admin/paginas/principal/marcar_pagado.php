<?php
/**
 * Marcar deuda como pagada y sincronizar con mensualidades
 */
require '../conexion.php';

$id_deudor = isset($_POST['id']) ? intval($_POST['id']) : 0;
$id_banco = isset($_POST['id_banco']) ? intval($_POST['id_banco']) : 0;
$referencia = isset($_POST['referencia']) ? trim($_POST['referencia']) : '';
$autorizado_por = 'Sistema/Usuario Web'; 

if ($id_deudor <= 0) {
    die("ID de deuda inválido.");
}

// 1. Obtener datos de la deuda
$sql = "SELECT id_contrato, saldo_pendiente FROM clientes_deudores WHERE id = ? AND estado = 'PENDIENTE'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_deudor);
$stmt->execute();
$res = $stmt->get_result();
$deuda = $res->fetch_assoc();

if (!$deuda) {
    die("Deuda no encontrada o ya saldada.");
}

$id_contrato = $deuda['id_contrato'];
$monto_total = floatval($deuda['saldo_pendiente']);
$fecha_actual = date('Y-m-d H:i:s');
$fecha_solo_dia = date('Y-m-d');
$justificacion_final = "[PAGO_DEUDA][DEUDA_TOTAL] Liquidación de deuda pendiente" . (!empty($referencia) ? " - Ref: $referencia" : "");

$conn->begin_transaction();

try {
    // 2. Registrar en cuentas_por_cobrar
    $sql_cxc = "INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen, estado_sae_plus, fecha_pago, referencia_pago, id_banco) 
                VALUES (?, ?, ?, ?, 'PAGADO', 'SISTEMA', 'NO CARGADO', ?, ?, ?)";
    $stmt_cxc = $conn->prepare($sql_cxc);
    $stmt_cxc->bind_param("issdssi", $id_contrato, $fecha_solo_dia, $fecha_solo_dia, $monto_total, $fecha_actual, $referencia, $id_banco);
    $stmt_cxc->execute();
    $id_cobro_nuevo = $conn->insert_id;

    // 3. Registrar en Historial
    $sql_hist = "INSERT INTO cobros_manuales_historial (id_cobro_cxc, id_contrato, autorizado_por, justificacion, monto_cargado) 
                 VALUES (?, ?, ?, ?, ?)";
    $stmt_hist = $conn->prepare($sql_hist);
    $stmt_hist->bind_param("iissd", $id_cobro_nuevo, $id_contrato, $autorizado_por, $justificacion_final, $monto_total);
    $stmt_hist->execute();

    // 4. Actualizar estado de la deuda
    $sql_upd_deuda = "UPDATE clientes_deudores SET estado = 'PAGADO', saldo_pendiente = 0, monto_pagado = monto_total WHERE id = ?";
    $stmt_upd_deuda = $conn->prepare($sql_upd_deuda);
    $stmt_upd_deuda->bind_param("i", $id_deudor);
    $stmt_upd_deuda->execute();

    // 5. REACTIVACIÓN AUTOMÁTICA DEL CONTRATO
    $sql_upd_contrato = "UPDATE contratos SET estado = 'ACTIVO' WHERE id = ? AND estado = 'SUSPENDIDO'";
    $stmt_upd_contrato = $conn->prepare($sql_upd_contrato);
    $stmt_upd_contrato->bind_param("i", $id_contrato);
    $stmt_upd_contrato->execute();

    $conn->commit();

    // Cierre de statements
    $stmt_cxc->close();
    $stmt_hist->close();
    $stmt_upd_deuda->close();
    $stmt_upd_contrato->close();

    echo "OK";

} catch (Exception $e) {
    $conn->rollback();
    echo "ERROR: " . $e->getMessage();
}

$conn->close();
?>
