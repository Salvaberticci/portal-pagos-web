<?php
header('Content-Type: application/json; charset=utf-8');
require '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_cobro_primario = isset($_POST['id_cobro']) ? intval($_POST['id_cobro']) : 0;
if ($id_cobro_primario <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cobro inválido.']);
    exit;
}

// 1. Obtener datos actuales
$check = $conn->query("SELECT id_grupo_pago, capture_pago, fecha_emision, id_contrato FROM cuentas_por_cobrar WHERE id_cobro = $id_cobro_primario");
if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Cobro no encontrado.']);
    exit;
}
$current_data = $check->fetch_assoc();
$id_grupo_pago = $current_data['id_grupo_pago'];
$capture_path = $current_data['capture_pago'];
$id_contrato_principal = $current_data['id_contrato'];

// 2. Procesar Campos Comunes
$estado = $_POST['estado'] ?? 'PENDIENTE';
$fecha_vencimiento = $_POST['fecha_vencimiento'] ?? date('Y-m-d');
$referencia_pago = $_POST['referencia_pago'] ?? '';
$id_banco_pago = intval($_POST['id_banco_pago'] ?? 0);
$autorizado_por = $_POST['autorizado_por'] ?? '';
$justificacion_raw = trim($_POST['justificacion'] ?? '');

// Lógica de De-duplicación Refinada: Eliminar recursivamente bloques de corchetes y descripciones estándar
$justificacion_global = preg_replace('/^(\[[^\]]+\]\s*)+/i', '', $justificacion_raw);
$justificacion_global = preg_replace('/^-?\s*Mensualidad \(\d+ mes\/es\)\s*/i', '', $justificacion_global);
$justificacion_global = preg_replace('/^-?\s*Pago de Terceros\s*\(ID:\s*\d+\)\s*/i', '', $justificacion_global);
$justificacion_global = trim(preg_replace('/^[-\s]+/', '', $justificacion_global));

$meses_usuario = $_POST['meses_seleccionados_mensualidad'] ?? [];
$fecha_pago = $_POST['fecha_pago'] ?? (($estado === 'PAGADO') ? date('Y-m-d') : null);
$fecha_emision = $fecha_pago ?: $current_data['fecha_emision'];

// 3. Manejo de Archivo
if (isset($_FILES['capture_archivo']) && $_FILES['capture_archivo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../img/captures/';
    $file_extension = pathinfo($_FILES['capture_archivo']['name'], PATHINFO_EXTENSION);
    $new_file_name = 'cap_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
    if (move_uploaded_file($_FILES['capture_archivo']['tmp_name'], $upload_dir . $new_file_name)) {
        $capture_path = 'img/captures/' . $new_file_name;
    }
}

// 4. Preparar Desglose
$cargos_a_procesar = [];
if (isset($_POST['desglose_mensualidad_activado']) && $_POST['desglose_mensualidad_activado'] == '1') {
    $monto = floatval(str_replace(',', '.', $_POST['monto_mensualidad'] ?? 0));
    $meses = intval($_POST['meses_mensualidad'] ?? 1);
    if ($monto > 0) {
        $monto_unitario = $monto / $meses;
        for ($m = 0; $m < $meses; $m++) {
            $mes_label = isset($meses_usuario[$m]) ? " [".$meses_usuario[$m]."]" : "";
            $loop_ven = date('Y-m-d', strtotime("+$m month", strtotime($fecha_vencimiento)));
            $loop_emi = date('Y-m-d', strtotime("+$m month", strtotime($fecha_emision)));
            $loop_pag = $fecha_pago ? date('Y-m-d', strtotime("+$m month", strtotime($fecha_pago))) : null;

            $cargos_a_procesar[] = [
                'monto' => $monto_unitario, 
                'just' => "[MENSUALIDAD]$mes_label Mensualidad (1 mes/es)" . (!empty($justificacion_global) ? " - $justificacion_global" : ""),
                'vencimiento' => $loop_ven, 'emision' => $loop_emi, 'pago' => $loop_pag
            ];
        }
    }
}
if (isset($_POST['desglose_instalacion_activado']) && $_POST['desglose_instalacion_activado'] == '1') {
    $monto = floatval(str_replace(',', '.', $_POST['monto_instalacion'] ?? 0));
    if ($monto > 0) $cargos_a_procesar[] = ['monto' => $monto, 'just' => "[INSTALACION] Instalación" . (!empty($justificacion_global) ? " - $justificacion_global" : ""), 'vencimiento' => $fecha_vencimiento, 'emision' => $fecha_emision, 'pago' => $fecha_pago];
}
if (isset($_POST['desglose_prorrateo_activado']) && $_POST['desglose_prorrateo_activado'] == '1') {
    $monto = floatval(str_replace(',', '.', $_POST['monto_prorrateo'] ?? 0));
    if ($monto > 0) $cargos_a_procesar[] = ['monto' => $monto, 'just' => "[PRORRATEO] Prorrateo" . (!empty($justificacion_global) ? " - $justificacion_global" : ""), 'vencimiento' => $fecha_vencimiento, 'emision' => $fecha_emision, 'pago' => $fecha_pago];
}
if (isset($_POST['desglose_equipo_activado']) && $_POST['desglose_equipo_activado'] == '1') {
    $monto = floatval(str_replace(',', '.', $_POST['monto_equipo'] ?? 0));
    if ($monto > 0) $cargos_a_procesar[] = ['monto' => $monto, 'just' => "[EQUIPOS] Pago de Equipo" . (!empty($justificacion_global) ? " - $justificacion_global" : ""), 'vencimiento' => $fecha_vencimiento, 'emision' => $fecha_emision, 'pago' => $fecha_pago];
}
if (isset($_POST['desglose_extra_activado']) && $_POST['desglose_extra_activado'] == '1') {
    $contratos = $_POST['extra_contrato'] ?? [];
    $montos = $_POST['extra_monto'] ?? [];
    $meses_c = $_POST['extra_meses'] ?? [];
    foreach ($contratos as $idx => $id_c) {
        $id_c = intval($id_c);
        if ($id_c <= 0) continue;
        $monto_item = floatval(str_replace(',', '.', $montos[$idx] ?? 0));
        $cant_meses = intval($meses_c[$idx] ?? 1);
        if ($monto_item > 0) {
            $monto_u = $monto_item / $cant_meses;
            for ($m = 0; $m < $cant_meses; $m++) {
                $cargos_a_procesar[] = [
                    'id_contrato' => $id_c, 'monto' => $monto_u,
                    'just' => "[EXTRA] [ID: $id_c] Mensualidad (1 mes/es)" . (!empty($justificacion_global) ? " - $justificacion_global" : ""),

                    'vencimiento' => date('Y-m-d', strtotime("+$m month", strtotime($fecha_vencimiento))),
                    'emision' => date('Y-m-d', strtotime("+$m month", strtotime($fecha_emision))),
                    'pago' => $fecha_pago ? date('Y-m-d', strtotime("+$m month", strtotime($fecha_pago))) : null
                ];
            }
        }
    }
}

if (empty($cargos_a_procesar)) {
    echo json_encode(['success' => false, 'message' => 'Debe activar al menos un concepto.']);
    exit;
}

$conn->begin_transaction();
try {
    if (!empty($id_grupo_pago)) {
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_grupo_pago = '$id_grupo_pago' AND id_cobro != $id_cobro_primario)");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_grupo_pago = '$id_grupo_pago' AND id_cobro != $id_cobro_primario");
    } else if (count($cargos_a_procesar) > 1) {
        $id_grupo_pago = bin2hex(random_bytes(16));
    }

    $primer = array_shift($cargos_a_procesar);
    $stmt = $conn->prepare("UPDATE cuentas_por_cobrar SET monto_total = ?, fecha_vencimiento = ?, fecha_emision = ?, estado = ?, referencia_pago = ?, id_banco = ?, capture_pago = ?, id_grupo_pago = ?, fecha_pago = ? WHERE id_cobro = ?");
    $stmt->bind_param("dssssssssi", $primer['monto'], $primer['vencimiento'], $primer['emision'], $estado, $referencia_pago, $id_banco_pago, $capture_path, $id_grupo_pago, $primer['pago'], $id_cobro_primario);
    $stmt->execute();

    $conn->query("UPDATE cobros_manuales_historial SET autorizado_por = '".$conn->real_escape_string($autorizado_por)."', justificacion = '".$conn->real_escape_string($primer['just'])."', monto_cargado = ".$primer['monto']." WHERE id_cobro_cxc = $id_cobro_primario");

    foreach ($cargos_a_procesar as $extra) {
        $id_c_t = $extra['id_contrato'] ?? $id_contrato_principal;
        $stmt_i = $conn->prepare("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, referencia_pago, id_banco, id_grupo_pago, capture_pago, fecha_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_i->bind_param("issdssssss", $id_c_t, $extra['emision'], $extra['vencimiento'], $extra['monto'], $estado, $referencia_pago, $id_banco_pago, $id_grupo_pago, $capture_path, $extra['pago']);
        $stmt_i->execute();
        $nid = $conn->insert_id;
        $conn->query("INSERT INTO cobros_manuales_historial (id_cobro_cxc, id_contrato, autorizado_por, justificacion, monto_cargado) VALUES ($nid, $id_contrato_principal, '".$conn->real_escape_string($autorizado_por)."', '".$conn->real_escape_string($extra['just'])."', ".$extra['monto'].")");
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
