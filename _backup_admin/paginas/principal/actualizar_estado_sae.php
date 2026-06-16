<?php
// actualizar_estado_sae.php - Actualiza el estado de carga a SAE Plus vía AJAX
header('Content-Type: application/json; charset=utf-8');
require '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cobro = intval($_POST['id_cobro']);
    $estado = $conn->real_escape_string($_POST['estado_sae_plus']);

    if (!in_array($estado, ['CARGADO', 'NO CARGADO'])) {
        echo json_encode(["success" => false, "message" => "Estado no válido."]);
        exit;
    }

    $sql = "UPDATE cuentas_por_cobrar SET estado_sae_plus = '$estado' WHERE id_cobro = $id_cobro";

    if ($conn->query($sql)) {
        echo json_encode(["success" => true, "message" => "Estado actualizado correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error BD: " . $conn->error]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}

$conn->close();
?>