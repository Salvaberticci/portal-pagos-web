<?php
session_start();
require '../paginas/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';

    if (empty($cedula)) {
        $_SESSION['login_error'] = "Por favor, ingresa tu cédula.";
        header('Location: index.php');
        exit;
    }

    // Buscar si existe al menos un contrato con esta cédula
    $sql = "SELECT nombre_completo, telefono FROM contratos WHERE cedula = ? AND estado != 'ELIMINADO' LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $cedula);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $cliente = $res->fetch_assoc();
            
            // Login exitoso
            $_SESSION['cliente_cedula'] = $cedula;
            $_SESSION['cliente_nombre'] = $cliente['nombre_completo'];
            $_SESSION['cliente_telefono'] = $cliente['telefono'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            // No existe
            $_SESSION['login_error'] = "No se encontraron contratos activos con esta cédula.";
            header('Location: index.php');
            exit;
        }
    } else {
        $_SESSION['login_error'] = "Error en el sistema. Intenta más tarde.";
        header('Location: index.php');
        exit;
    }
} else {
    // Si entran por GET
    if (isset($_GET['logout'])) {
        session_destroy();
    }
    header('Location: index.php');
    exit;
}
