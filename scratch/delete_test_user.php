<?php
require_once __DIR__ . '/../paginas/conexion.php';

$usuario = 'testadmin';

$stmt = $conn->prepare("DELETE FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
if ($stmt->execute()) {
    echo "Usuario temporal 'testadmin' eliminado con éxito.\n";
} else {
    echo "Error al eliminar el usuario: " . $stmt->error . "\n";
}
$stmt->close();
$conn->close();
?>
