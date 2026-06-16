<?php
require_once 'security_helper.php';
require '../paginas/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';

    // 1. Rate Limiting (5 intentos por 5 minutos)
    if (!check_rate_limit('login', 5, 300)) {
        $_SESSION['login_error'] = "Demasiados intentos. Por favor, intenta de nuevo en unos minutos.";
        header('Location: index.php');
        exit;
    }

    // 2. CSRF Verification
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrf_token)) {
        log_security_event('CSRF_VIOLATION', 'Fallo de verificación CSRF en inicio de sesión', $cedula);
        $_SESSION['login_error'] = "Petición de seguridad inválida. Por favor, recarga la página.";
        header('Location: index.php');
        exit;
    }

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
            session_regenerate_id(true);
            $_SESSION['cliente_cedula'] = $cedula;
            $_SESSION['cliente_nombre'] = $cliente['nombre_completo'];
            $_SESSION['cliente_telefono'] = $cliente['telefono'];
            
            log_security_event('LOGIN_SUCCESS', 'Inicio de sesión exitoso', $cedula);
            
            header('Location: dashboard.php');
            exit;
        } else {
            // No existe
            log_security_event('LOGIN_FAILED', 'Intento de inicio de sesión fallido: cédula no registrada', $cedula);
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
        if (isset($_SESSION['cliente_cedula'])) {
            log_security_event('LOGOUT', 'Cierre de sesión', $_SESSION['cliente_cedula']);
        }
        session_destroy();
    }
    header('Location: index.php');
    exit;
}
