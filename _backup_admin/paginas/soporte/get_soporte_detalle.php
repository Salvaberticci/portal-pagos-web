<?php
// paginas/soporte/get_soporte_detalle.php
// Retorna todos los campos de un soporte para poblar el modal de edición.

header('Content-Type: application/json; charset=utf-8');
require_once '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$sql = "SELECT s.*, s.telefono as telefono_soporte, c.nombre_completo, c.cedula, o.nombre_olt, p.nombre_pon
        FROM soportes s
        INNER JOIN contratos c ON s.id_contrato = c.id
        LEFT JOIN olt o ON s.id_olt = o.id_olt
        LEFT JOIN pon p ON s.id_pon = p.id_pon
        WHERE s.id_soporte = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Soporte no encontrado']);
    exit;
}

$row = $result->fetch_assoc();

// 2. Obtener todas las OLTs/PONs afectadas registradas en la tabla de relación
$olts_afectadas = [];
$sql_olts = "SELECT soa.*, o.nombre_olt, p.nombre_pon 
             FROM soporte_olts_afectados soa
             LEFT JOIN olt o ON soa.id_olt = o.id_olt
             LEFT JOIN pon p ON soa.id_pon = p.id_pon
             WHERE soa.id_soporte = ?";
$stmt_olts = $conn->prepare($sql_olts);
$stmt_olts->bind_param("i", $id);
$stmt_olts->execute();
$res_olts = $stmt_olts->get_result();
while ($o = $res_olts->fetch_assoc()) {
    $olts_afectadas[] = $o;
}
$row['olts_afectadas'] = $olts_afectadas;

// Formatear fecha para input[type=date]
$row['fecha_soporte_form'] = date('Y-m-d', strtotime($row['fecha_soporte']));

echo json_encode($row, JSON_UNESCAPED_UNICODE);
?>