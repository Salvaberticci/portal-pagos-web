<?php
require_once '../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id_prorroga']);
    $nombre = $conn->real_escape_string($_POST['nombre_titular']);
    $cedula = $conn->real_escape_string($_POST['cedula_titular']);
    $fecha_corte = $conn->real_escape_string($_POST['fecha_corte']);
    $prorroga_regular = $conn->real_escape_string($_POST['prorroga_regular']);
    $codigo_sae_plus = $conn->real_escape_string($_POST['codigo_sae_plus'] ?? '');

    $sql = "UPDATE prorrogas SET 
            nombre_titular = '$nombre', 
            cedula_titular = '$cedula', 
            fecha_corte = '$fecha_corte', 
            prorroga_regular = '$prorroga_regular',
            codigo_sae_plus = '$codigo_sae_plus'
            WHERE id_prorroga = $id";

    if ($conn->query($sql)) {
        echo json_encode(["success" => true, "message" => "Prórroga actualizada correctamente"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al actualizar: " . $conn->error]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
}
?>
