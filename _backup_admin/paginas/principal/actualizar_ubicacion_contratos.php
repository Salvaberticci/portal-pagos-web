<?php
/**
 * Actualización en Cascada de Ubicaciones
 * Cuando se renombra un municipio o parroquia, actualiza todos los contratos que la tienen asignada.
 */

header('Content-Type: application/json');
require '../conexion.php';

$tipo      = $_POST['tipo']       ?? ''; // 'municipio' or 'parroquia'
$old_value = $_POST['old_value']  ?? '';
$new_value = trim($_POST['new_value'] ?? '');

if (empty($tipo) || empty($old_value) || empty($new_value)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

if ($old_value === $new_value) {
    echo json_encode(['success' => true, 'updated' => 0]);
    exit;
}

try {
    if ($tipo === 'municipio') {
        $stmt = $conn->prepare("UPDATE contratos SET municipio_texto = ? WHERE municipio_texto = ?");
        $stmt->bind_param("ss", $new_value, $old_value);
    } elseif ($tipo === 'parroquia') {
        $stmt = $conn->prepare("UPDATE contratos SET parroquia_texto = ? WHERE parroquia_texto = ?");
        $stmt->bind_param("ss", $new_value, $old_value);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo no válido']);
        exit;
    }

    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'updated' => $updated]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
