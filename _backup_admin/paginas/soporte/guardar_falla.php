<?php
/**
 * Guardar Falla - Backend
 * Procesa el registro rápido de fallas con soporte para múltiples OLT/PON
 */

header('Content-Type: application/json');
require_once '../conexion.php';

// Validar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Recibir datos
$id_contrato       = isset($_POST['id_contrato']) ? intval($_POST['id_contrato']) : 0;
$prioridad         = 'NIVEL 3';
$tipo_falla        = isset($_POST['tipo_falla'])        ? trim($_POST['tipo_falla'])        : '';
$tipo_servicio     = isset($_POST['tipo_servicio'])     ? trim($_POST['tipo_servicio'])     : 'Fibra Óptica';
$es_caida_critica  = 1;
$clientes_afectados = isset($_POST['clientes_afectados']) ? intval($_POST['clientes_afectados']) : 50;
$sector            = isset($_POST['sector'])            ? trim($_POST['sector'])            : '';
$zona_afectada     = isset($_POST['zona_afectada'])     ? trim($_POST['zona_afectada'])     : '';
$observaciones     = isset($_POST['observaciones'])     ? trim($_POST['observaciones'])     : '';
$equipos_afectados = isset($_POST['equipos_afectados']) ? trim($_POST['equipos_afectados']) : '';
$tecnico_asignado  = isset($_POST['tecnico_asignado'])  ? trim($_POST['tecnico_asignado'])  : '';
$telefono          = isset($_POST['telefono'])          ? trim($_POST['telefono'])          : '';
$notas_internas    = isset($_POST['notas_internas'])    ? trim($_POST['notas_internas'])    : '';
$fecha_reporte     = isset($_POST['fecha_reporte'])     ? $_POST['fecha_reporte']           : date('Y-m-d H:i:s');

// Múltiples OLT/PON — vienen como arrays
$olts_raw = isset($_POST['olts'])  ? (array)$_POST['olts']  : [];
$pons_raw = isset($_POST['pons'])  ? (array)$_POST['pons']  : [];

// Filtrar filas vacías y convertir a int
$pairs = [];
foreach ($olts_raw as $i => $olt_id) {
    $olt_id = intval($olt_id);
    if ($olt_id <= 0) continue;
    $pon_id = isset($pons_raw[$i]) ? intval($pons_raw[$i]) : 0;
    $pairs[] = ['id_olt' => $olt_id, 'id_pon' => $pon_id > 0 ? $pon_id : null];
}

// Primera OLT/PON para la columna legacy en soportes (retrocompatibilidad)
$id_olt_legacy = !empty($pairs) ? $pairs[0]['id_olt'] : null;
$id_pon_legacy = !empty($pairs) ? $pairs[0]['id_pon'] : null;

if (!empty($equipos_afectados)) {
    $observaciones .= "\n\nEquipos Potencialmente Afectados: " . $equipos_afectados;
}

// Validaciones
$errores = [];
if ($id_contrato <= 0)         $errores[] = 'Debe seleccionar un cliente de referencia';
if (empty($pairs))             $errores[] = 'Debe seleccionar al menos una OLT afectada';
if (empty($tipo_falla))        $errores[] = 'Debe seleccionar un tipo de falla';
if (empty($tecnico_asignado))  $errores[] = 'Debe asignar un técnico responsable';
if (strlen($observaciones) < 10) $errores[] = 'La descripción debe tener al menos 10 caracteres';

if (!empty($errores)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errores)]);
    exit;
}

// Obtener datos del cliente
$sql_cliente = "SELECT nombre_completo, cedula, ip_onu as ip, direccion, telefono FROM contratos WHERE id = $id_contrato";
$result_cliente = $conn->query($sql_cliente);
if ($result_cliente->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Cliente de referencia no encontrado']);
    exit;
}
$cliente = $result_cliente->fetch_assoc();
if (empty($telefono)) {
    $telefono = $cliente['telefono'] ?? '';
}

// Descripción corta automática
$descripcion_corta = "[MASIVA] " . $tipo_falla . ' - ' . $cliente['nombre_completo'];
$fecha_soporte     = date('Y-m-d', strtotime($fecha_reporte));

// Escapar datos
$descripcion_corta  = $conn->real_escape_string($descripcion_corta);
$tipo_falla         = $conn->real_escape_string($tipo_falla);
$tipo_servicio      = $conn->real_escape_string($tipo_servicio);
$sector             = $conn->real_escape_string($sector);
$zona_afectada      = $conn->real_escape_string($zona_afectada);
$observaciones      = $conn->real_escape_string($observaciones);
$tecnico_asignado   = $conn->real_escape_string($tecnico_asignado);
$telefono           = $conn->real_escape_string($telefono);
$notas_internas     = $conn->real_escape_string($notas_internas);
$fecha_reporte_sql  = $conn->real_escape_string($fecha_reporte);

// Insertar en soportes (tabla principal)
$id_olt_sql = $id_olt_legacy ?? 'NULL';
$id_pon_sql = $id_pon_legacy  ?? 'NULL';
$hora_solucion = date('H:i:s');

$sql = "INSERT INTO soportes (
    id_contrato, fecha_soporte, fecha_reporte, hora_solucion, descripcion, tipo_falla,
    prioridad, es_caida_critica, clientes_afectados, tipo_servicio,
    sector, zona_afectada, observaciones, notas_internas, tecnico_asignado,
    id_olt, id_pon, solucion_completada, monto_total, monto_pagado, estado_firma, telefono
) VALUES (
    $id_contrato, '$fecha_soporte', '$fecha_reporte_sql', '$hora_solucion', '$descripcion_corta', '$tipo_falla',
    'NIVEL 3', 1, $clientes_afectados, '$tipo_servicio',
    '$sector', '$zona_afectada', '$observaciones', '$notas_internas', '$tecnico_asignado',
    $id_olt_sql, $id_pon_sql, 0, 0, 0, 'PENDIENTE', '$telefono'
)";

if (!$conn->query($sql)) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $conn->error]);
    $conn->close();
    exit;
}

$id_soporte = $conn->insert_id;

// Insertar todos los pares OLT/PON en la tabla de relación
$stmt_olt = $conn->prepare("INSERT INTO soporte_olts_afectados (id_soporte, id_olt, id_pon) VALUES (?, ?, ?)");
foreach ($pairs as $pair) {
    $pon_val = $pair['id_pon']; // null si no hay PON
    $stmt_olt->bind_param("iii", $id_soporte, $pair['id_olt'], $pon_val);
    $stmt_olt->execute();
}
$stmt_olt->close();

error_log("Falla registrada: Ticket #{$id_soporte} - {$tipo_falla} - Cliente: {$cliente['nombre_completo']} [CRÍTICA - {$clientes_afectados} clientes] - " . count($pairs) . " OLTs afectadas");

echo json_encode([
    'success'    => true,
    'message'    => 'Falla registrada exitosamente',
    'id_soporte' => $id_soporte,
    'ticket'     => str_pad($id_soporte, 6, '0', STR_PAD_LEFT),
    'prioridad'  => $prioridad,
    'es_critica' => $es_caida_critica,
    'olts_count' => count($pairs)
]);

$conn->close();
?>