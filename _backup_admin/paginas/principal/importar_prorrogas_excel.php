<?php
require_once '../conexion.php';

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['data'])) {
    echo json_encode(["success" => false, "message" => "Datos inválidos"]);
    exit;
}

$imported = 0;
$errors = 0;

$stmt = $conn->prepare("INSERT INTO prorrogas (
    tipo_solicitud, cedula_titular, nombre_titular, existe_saeplus, 
    fecha_corte, dia_prorroga, prorroga_regular, estado, codigo_sae_plus
) VALUES ('PRORROGA', ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($data['data'] as $row) {
    $cedula = $row['cedula'] ?? '';
    $nombre = $row['nombre'] ?? '';
    $saeplus = strtoupper($row['saeplus'] ?? 'NO');
    $corte_dia = (int) ($row['corte'] ?? 0);
    $prorroga_dia = (int) ($row['prorroga'] ?? 0);
    $regular = strtoupper($row['regular'] ?? 'SI');
    $codigo_sae_plus = $row['codigo_sae_plus'] ?? null;

    // Normalizar SAEPLUS y REGULAR (SI/NO)
    if (strpos($saeplus, 'S') !== false)
        $saeplus = 'SI';
    else
        $saeplus = 'NO';
    if (strpos($regular, 'S') !== false)
        $regular = 'SI';
    else
        $regular = 'NO';

    // Construir fecha de corte para el mes actual
    $fecha_corte = null;
    if ($corte_dia > 0) {
        $fecha_corte = date('Y-m-') . str_pad($corte_dia, 2, '0', STR_PAD_LEFT);
    }

    $estado = 'PENDIENTE';

    if (!empty($cedula) && !empty($nombre)) {
        $stmt->bind_param("ssssisss", $cedula, $nombre, $saeplus, $fecha_corte, $prorroga_dia, $regular, $estado, $codigo_sae_plus);
        if ($stmt->execute()) {
            $imported++;
        } else {
            $errors++;
        }
    }
}

echo json_encode([
    "success" => true,
    "imported" => $imported,
    "errors" => $errors
]);

$stmt->close();
$conn->close();
?>