<?php
require_once '../conexion.php';

header('Content-Type: application/json');

$id_olt = isset($_GET['id_olt']) ? intval($_GET['id_olt']) : 0;

if ($id_olt <= 0) {
    echo json_encode([]);
    exit;
}

$pons = [];
$stmt = $conn->prepare("SELECT id_pon, nombre_pon FROM pon WHERE id_olt = ? ORDER BY nombre_pon ASC");
$stmt->bind_param("i", $id_olt);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $pons[] = $row;
}

echo json_encode($pons);
$stmt->close();
$conn->close();
?>
