<?php
/**
 * eliminar_deuda.php
 * Elimina una deuda administrativamente sin generar registros de pago.
 */
require '../conexion.php';

$id_deudor = isset($_POST['id']) ? intval($_POST['id']) : 0;
$autorizado_por = 'Administrador/Eliminación Manual'; 

if ($id_deudor <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de deuda inválido.']);
    exit;
}

// 1. Obtener datos del registro
$sql = "SELECT id_contrato, tipo_registro FROM clientes_deudores WHERE id = ? AND estado = 'PENDIENTE'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_deudor);
$stmt->execute();
$res = $stmt->get_result();
$registro = $res->fetch_assoc();

if (!$registro) {
    echo json_encode(['success' => false, 'message' => 'Registro no encontrado o ya procesado.']);
    exit;
}

$id_contrato = $registro['id_contrato'];
$tipo = $registro['tipo_registro']; // DEUDA o CREDITO
$label = ($tipo === 'CREDITO') ? 'Saldo a Favor' : 'Deuda';

$conn->begin_transaction();

try {
    // 2. Actualizar estado del registro (Saldar/Eliminar administrativamente)
    $nota_adicional = " | ELIMINACIÓN ADMINISTRATIVA: " . ($tipo === 'CREDITO' ? 'Crédito anulado' : 'Deuda cancelada') . " sin registro de pago.";
    $sql_upd = "UPDATE clientes_deudores SET 
                        estado = 'PAGADO', 
                        saldo_pendiente = 0, 
                        monto_pagado = (CASE WHEN tipo_registro = 'DEUDA' THEN monto_total ELSE monto_pagado END), 
                        notas = CONCAT(IFNULL(notas, ''), ?) 
                      WHERE id = ?";
    $stmt_upd = $conn->prepare($sql_upd);
    $stmt_upd->bind_param("si", $nota_adicional, $id_deudor);
    $stmt_upd->execute();

    // 3. REACTIVACIÓN AUTOMÁTICA DEL CONTRATO (Solo si era una DEUDA)
    if ($tipo === 'DEUDA') {
        $sql_upd_contrato = "UPDATE contratos SET estado = 'ACTIVO' WHERE id = ? AND estado = 'SUSPENDIDO'";
        $stmt_upd_contrato = $conn->prepare($sql_upd_contrato);
        $stmt_upd_contrato->bind_param("i", $id_contrato);
        $stmt_upd_contrato->execute();
    }

    $conn->commit();

    echo json_encode(['success' => true, 'message' => "$label eliminado/saldado con éxito."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
