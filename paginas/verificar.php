<?php
// Incluye el archivo de conexión (asumo que está en la misma carpeta 'paginas')
require 'conexion.php'; 
// Inicia la sesión de PHP (ESENCIAL para guardar los datos del usuario)
session_start();

// Verifica que los datos hayan sido enviados por el método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si no es POST, redirige al login.
    header('Location: ../index.html');
    exit;
}

// 1. Recolección de datos (usando POST, que es más seguro que REQUEST)
$usuario = $_POST['usuario'];
$clave = $_POST['clave'];

// 2. USO DE SENTENCIAS PREPARADAS (PREVIENE INYECCIÓN SQL)
$stmt = $conn->prepare("SELECT `id_usuario`, `usuario`, `clave`, `nombre_completo`, `rol` FROM `usuarios` WHERE `usuario` = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $user_db = $resultado->fetch_assoc();
    
    // 3. VERIFICACIÓN DE CONTRASEÑA (USA password_verify, NO '==')
    // Compara la clave ingresada ($clave) con el hash almacenado ($user_db['clave'])
    if (password_verify($clave, $user_db['clave'])) {
        
        // --- ÉXITO: GUARDAR DATOS DE SESIÓN ---
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $user_db['id_usuario'];
        $_SESSION['usuario'] = $user_db['usuario'];
        $_SESSION['nombre_completo'] = $user_db['nombre_completo'];
        $_SESSION['rol'] = $user_db['rol']; // 'Administrador' o 'Vendedor'

        // Redirigir al menú principal
        header('Location: menu.php');
        exit;

    } else {
        // Error: Contraseña incorrecta
        $mensaje = "Usuario o Clave Incorrectos.";
    }
} else {
    // Error: Usuario no encontrado
    $mensaje = "Usuario o Clave Incorrectos.";
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Acceso Denegado</title>
	<link href="../css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<div class="container text-center mt-5">
		<h3 class='text-danger'>Error de Acceso</h3>
        <p><?php echo htmlspecialchars($mensaje); ?></p>
		<form action= '../index.html' method='post'>
			<div class='col-12 text-center mt-3'>
               <input class='btn btn-primary' type ='submit' value='Regresar' name='regresar'>
			</div>
        </form>
	</div>
</body>
</html>