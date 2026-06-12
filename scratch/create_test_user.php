<?php
require_once __DIR__ . '/../paginas/conexion.php';

$usuario = 'testadmin';
// Generar hash para 'testadmin123'
$clave = password_hash('testadmin123', PASSWORD_BCRYPT);
$nombre = 'Administrador de Pruebas';
$rol = 'Administrador';

// Verificar si ya existe
$stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo "El usuario temporal ya existe.\n";
} else {
    $stmt_insert = $conn->prepare("INSERT INTO usuarios (usuario, clave, nombre_completo, rol) VALUES (?, ?, ?, ?)");
    $stmt_insert->bind_param("ssss", $usuario, $clave, $nombre, $rol);
    if ($stmt_insert->execute()) {
        echo "Usuario temporal creado con éxito (testadmin / testadmin123).\n";
    } else {
        echo "Error al crear el usuario: " . $stmt_insert->error . "\n";
    }
    $stmt_insert->close();
}
$stmt->close();
$conn->close();
?>
