<?php
require_once '../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int) $data['id'] : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM prorrogas WHERE id_prorroga = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Registro eliminado correctamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "Error al eliminar: " . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "ID no válido."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}

$conn->close();
?>