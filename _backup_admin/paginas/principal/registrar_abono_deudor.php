<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexion.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// 1. Recibir y Sanitizar Datos
$id_deudor = isset($_POST['id_deudor']) ? intval($_POST['id_deudor']) : 0;
$monto_abono = isset($_POST['monto_abono']) ? floatval(str_replace(',', '.', $_POST['monto_abono'])) : 0.0;
$referencia = isset($_POST['referencia']) ? $conn->real_escape_string(trim($_POST['referencia'])) : '';
$id_banco = isset($_POST['id_banco']) ? intval($_POST['id_banco']) : 0;
// Default a un usuario genérico si no hay sesión para auditoría
$autorizado_por = 'Sistema/Usuario Web'; 
$justificacion_ingresada = isset($_POST['justificacion']) ? trim($_POST['justificacion']) : '';

if ($id_deudor <= 0 || $monto_abono <= 0 || empty($referencia) || $id_banco <= 0) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios o el monto es inválido.']);
    exit;
}

// 2. Manejo del Capture (Opcional)
$capture_path = '';
if (isset($_FILES['capture_abono']) && $_FILES['capture_abono']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../img/captures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_extension = pathinfo($_FILES['capture_abono']['name'], PATHINFO_EXTENSION);
    $file_name = 'abono_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['capture_abono']['tmp_name'], $target_file)) {
        $capture_path = 'img/captures/' . $file_name;
    }
}

// 3. Obtener Datos Actuales del Deudor
$sql_deudor = "SELECT id_contrato, monto_pagado, saldo_pendiente FROM clientes_deudores WHERE id = $id_deudor AND estado = 'PENDIENTE'";
$res_deudor = $conn->query($sql_deudor);

if ($res_deudor->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'El registro de deuda no existe o ya fue saldado completamente.']);
    exit;
}

$deudor = $res_deudor->fetch_assoc();
$id_contrato = $deudor['id_contrato'];
$saldo_pendiente_actual = floatval($deudor['saldo_pendiente']);
$monto_pagado_actual = floatval($deudor['monto_pagado']);

if ($monto_abono > $saldo_pendiente_actual) {
    echo json_encode(['success' => false, 'message' => 'El monto del abono no puede superar el saldo pendiente actual ($' . number_format($saldo_pendiente_actual, 2) . ').']);
    exit;
}

$nuevo_saldo_pendiente = $saldo_pendiente_actual - $monto_abono;
$nuevo_monto_pagado = $monto_pagado_actual + $monto_abono;
$fecha_actual = date('Y-m-d H:i:s');
$justificacion_final = "[ABONO_DEUDA] Abono #{$referencia}" . (!empty($justificacion_ingresada) ? " - " . $conn->real_escape_string($justificacion_ingresada) : "");

$conn->begin_transaction();

try {
    // 4. Registrar el Abono Físicamente (para Historial y Finanzas)
    // Se inserta en cuentas_por_cobrar como un pago saldado (PAGADO)
    $sql_cxc = "INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen, estado_sae_plus, capture_pago, fecha_pago, referencia_pago, id_banco) 
                VALUES (?, ?, ?, ?, 'PAGADO', 'SISTEMA', 'NO CARGADO', ?, ?, ?, ?)";
    
    $stmt_cxc = $conn->prepare($sql_cxc);
    $fecha_solo_dia = date('Y-m-d');
    $stmt_cxc->bind_param("issdsssi", $id_contrato, $fecha_solo_dia, $fecha_solo_dia, $monto_abono, $capture_path, $fecha_actual, $referencia, $id_banco);
    $stmt_cxc->execute();
    $id_cobro_nuevo = $conn->insert_id;
    $stmt_cxc->close();

    // 5. Registrar en Historial para Detalles
    $sql_hist = "INSERT INTO cobros_manuales_historial (id_cobro_cxc, id_contrato, autorizado_por, justificacion, monto_cargado) 
                 VALUES (?, ?, ?, ?, ?)";
    $stmt_hist = $conn->prepare($sql_hist);
    $stmt_hist->bind_param("iissd", $id_cobro_nuevo, $id_contrato, $autorizado_por, $justificacion_final, $monto_abono);
    $stmt_hist->execute();
    $stmt_hist->close();

    // 6. Actualizar la tabla clientes_deudores (Ajuste por residuo < 0.50)
    if ($nuevo_saldo_pendiente < 0.50) {
        $nuevo_saldo_pendiente = 0;
        $estado_deudor = 'PAGADO';
    } else {
        $estado_deudor = 'PENDIENTE';
    }

    $sql_update_deudor = "UPDATE clientes_deudores SET 
                            monto_pagado = ?, 
                            saldo_pendiente = ?, 
                            estado = ? 
                          WHERE id = ?";
    $stmt_upd = $conn->prepare($sql_update_deudor);
    $stmt_upd->bind_param("ddsi", $nuevo_monto_pagado, $nuevo_saldo_pendiente, $estado_deudor, $id_deudor);
    $stmt_upd->execute();
    $stmt_upd->close();

    $conn->commit();

    // 7. REACTIVACIÓN AUTOMÁTICA DEL CONTRATO (Si aplica)
    if ($estado_deudor === 'PAGADO') {
        $sql_upd_contrato = "UPDATE contratos SET estado = 'ACTIVO' WHERE id = ? AND estado = 'SUSPENDIDO'";
        $stmt_upd_contrato = $conn->prepare($sql_upd_contrato);
        $stmt_upd_contrato->bind_param("i", $id_contrato);
        $stmt_upd_contrato->execute();
        $stmt_upd_contrato->close();
    }

    echo json_encode([
        'success' => true, 
        'message' => 'El abono se registró exitosamente.',
        'saldado' => ($estado_deudor === 'PAGADO') // Bandera para la UI
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error al registrar el abono: ' . $e->getMessage()]);
}
?>
