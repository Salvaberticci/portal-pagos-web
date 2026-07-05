<?php
require_once 'security_helper.php';

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Services/WispHubClient.php';
        $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
        if (DEV_MODE) {
            require_once __DIR__ . '/../src/Services/WispHubDevModeClient.php';
            $wispClient = new \Services\WispHubDevModeClient($wispConfig);
        } else {
            $wispClient = new \Services\WispHubClient($wispConfig);
        }
    } catch (\Exception $e) {
        error_log("[LOGIN_INIT] Error: " . $e->getMessage());
        $_SESSION['login_error'] = "Servicio temporalmente no disponible. Intenta de nuevo en unos minutos.";
        header('Location: index.php');
        exit;
    }

    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';

    if (!check_rate_limit('login', 5, 300)) {
        $_SESSION['login_error'] = "Demasiados intentos. Por favor, intenta de nuevo en unos minutos.";
        header('Location: index.php');
        exit;
    }

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

    $numeroSolo = preg_replace('/^[A-Z]/i', '', $cedula);
    if (strlen($numeroSolo) < 6 || strlen($numeroSolo) > 8) {
        $_SESSION['login_error'] = "Usuario no encontrado";
        header('Location: index.php');
        exit;
    }

    $clientInfo = $wispClient->getClientByDocument($cedula);
    if ($clientInfo['status'] === 0) {
        $_SESSION['login_error'] = "Servicio temporalmente no disponible. Intenta de nuevo en unos minutos.";
        header('Location: index.php');
        exit;
    }
    if ($clientInfo['status'] !== 200 || empty($clientInfo['data']['data']['service_id'] ?? $clientInfo['data']['data']['id_servicio'] ?? '')) {
        $clientInfo = $wispClient->findClientByDocument($cedula);
        if ($clientInfo['status'] === 0) {
            $_SESSION['login_error'] = "Servicio temporalmente no disponible. Intenta de nuevo en unos minutos.";
            header('Location: index.php');
            exit;
        }
    }

    if ($clientInfo['status'] === 200 && !empty($clientInfo['data']['data'])) {
        $cliente = $clientInfo['data']['data'];
        session_regenerate_id(true);
        $_SESSION['cliente_cedula'] = $cedula;
        $_SESSION['cliente_nombre'] = trim(($cliente['nombre'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')) ?: 'Cliente';
        $_SESSION['cliente_telefono'] = $cliente['telefono'] ?? '';
        $_SESSION['wisp_service_id'] = $cliente['service_id'] ?? $cliente['id_servicio'] ?? '';
        log_security_event('LOGIN_SUCCESS', 'Inicio de sesión exitoso (WispHub)', $cedula);
        header('Location: dashboard.php');
        exit;
    } else {
        log_security_event('LOGIN_FAILED', "Cédula no encontrada en WispHub: $cedula", $cedula);
        $_SESSION['login_error'] = "Usuario no encontrado";
        header('Location: index.php');
        exit;
    }
} else {
    if (isset($_GET['logout'])) {
        if (isset($_SESSION['cliente_cedula'])) {
            log_security_event('LOGOUT', 'Cierre de sesión', $_SESSION['cliente_cedula']);
        }
        session_destroy();
    }
    header('Location: index.php');
    exit;
}
