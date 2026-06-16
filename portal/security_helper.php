<?php
// portal/security_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Session idle timeout (30 min)
if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    if (isset($_SERVER['HTTP_HOST'])) {
        header('Location: index.php');
        exit;
    }
}
$_SESSION['_last_activity'] = time();

/**
 * Genera y retorna un token CSRF único para la sesión.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF dado contra el almacenado en la sesión.
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verifica si la sesión ha superado el límite de intentos para una acción específica.
 * Como no usamos BD, se usa la sesión de PHP.
 */
function check_rate_limit(string $action, int $limit = 5, int $timeframe_seconds = 300): bool {
    $now = time();
    $session_key = "rate_limit_" . $action;

    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [
            'hit_count' => 1,
            'first_hit' => $now
        ];
        return true;
    }

    $first_hit = $_SESSION[$session_key]['first_hit'];

    if (($now - $first_hit) > $timeframe_seconds) {
        // Reiniciar
        $_SESSION[$session_key] = [
            'hit_count' => 1,
            'first_hit' => $now
        ];
        return true;
    }

    // Aumentar hit count
    $_SESSION[$session_key]['hit_count']++;

    if ($_SESSION[$session_key]['hit_count'] > $limit) {
        log_security_event('RATE_LIMIT_EXCEEDED', "La sesión superó el límite de la acción '$action'.");
        return false;
    }

    return true;
}

/**
 * Registra un evento de seguridad (ahora se guarda en un archivo de texto en lugar de la BD).
 */
function log_security_event(string $event_type, string $details, ?string $user_identifier = null): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($user_identifier === null && isset($_SESSION['cliente_cedula'])) {
        $user_identifier = $_SESSION['cliente_cedula'];
    }

    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . '/security.log';
    $date = date('Y-m-d H:i:s');
    
    $log_line = "[$date] [$ip] [$user_identifier] [$event_type] $details\n";
    @file_put_contents($log_file, $log_line, FILE_APPEND);
    return true;
}

/**
 * Fuerza el uso de conexiones seguras HTTPS (excepto en entornos locales de desarrollo).
 */
function enforce_https(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $is_localhost = ($ip === '127.0.0.1' || $ip === '::1' || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    
    if (!$is_localhost) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443);
        if (!$is_https) {
            $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }
}
