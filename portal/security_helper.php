<?php
// portal/security_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Obtiene o establece la conexión a la base de datos si la variable global $conn no está definida.
 */
function _get_security_db_connection() {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        return $conn;
    }
    
    $possible_paths = [
        __DIR__ . '/../paginas/conexion.php',
        dirname(__DIR__) . '/paginas/conexion.php',
        '../paginas/conexion.php',
        'paginas/conexion.php'
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            @include_once $path;
            break;
        }
    }
    
    global $conn;
    return $conn;
}

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
 * Verifica si la IP del cliente ha superado el límite de intentos para una acción específica.
 */
function check_rate_limit(string $action, int $limit = 5, int $timeframe_seconds = 300): bool {
    $db = _get_security_db_connection();
    if (!$db) {
        return true; // Fallback tolerante si no hay base de datos conectada
    }
    
    _init_security_tables($db);
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Limpieza de registros antiguos de rate limit (Garbage Collection)
    $db->query("DELETE FROM security_rate_limits WHERE last_hit < SUBDATE(NOW(), INTERVAL $timeframe_seconds SECOND)");
    
    // Buscar si existe un rate limit activo para esta IP y acción
    $stmt = $db->prepare("SELECT hit_count, first_hit, last_hit FROM security_rate_limits WHERE ip_address = ? AND action_name = ?");
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param("ss", $ip, $action);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $now = time();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        $first_hit_time = strtotime($row['first_hit']);
        
        if (($now - $first_hit_time) > $timeframe_seconds) {
            // El periodo de tiempo ya pasó, reiniciar contador
            $stmt_upd = $db->prepare("UPDATE security_rate_limits SET hit_count = 1, first_hit = CURRENT_TIMESTAMP, last_hit = CURRENT_TIMESTAMP WHERE ip_address = ? AND action_name = ?");
            if ($stmt_upd) {
                $stmt_upd->bind_param("ss", $ip, $action);
                $stmt_upd->execute();
                $stmt_upd->close();
            }
            return true;
        } else {
            // Aún dentro del periodo, incrementar contador
            $new_count = $row['hit_count'] + 1;
            $stmt_upd = $db->prepare("UPDATE security_rate_limits SET hit_count = ?, last_hit = CURRENT_TIMESTAMP WHERE ip_address = ? AND action_name = ?");
            if ($stmt_upd) {
                $stmt_upd->bind_param("iss", $new_count, $ip, $action);
                $stmt_upd->execute();
                $stmt_upd->close();
            }
            
            if ($new_count > $limit) {
                log_security_event('RATE_LIMIT_EXCEEDED', "La IP superó el límite de la acción '$action'. Intentos: $new_count, límite: $limit en {$timeframe_seconds}s.");
                return false;
            }
            return true;
        }
    } else {
        $stmt->close();
        // Primer intento registrado
        $stmt_ins = $db->prepare("INSERT INTO security_rate_limits (ip_address, action_name, hit_count, first_hit, last_hit) VALUES (?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        if ($stmt_ins) {
            $stmt_ins->bind_param("ss", $ip, $action);
            $stmt_ins->execute();
            $stmt_ins->close();
        }
        return true;
    }
}

/**
 * Registra un evento de seguridad en la bitácora de auditoría.
 */
function log_security_event(string $event_type, string $details, ?string $user_identifier = null): bool {
    $db = _get_security_db_connection();
    if (!$db) {
        return false;
    }
    
    _init_security_tables($db);
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($user_identifier === null && isset($_SESSION['cliente_cedula'])) {
        $user_identifier = $_SESSION['cliente_cedula'];
    }
    
    $stmt = $db->prepare("INSERT INTO portal_security_logs (event_type, ip_address, user_identifier, details) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $event_type, $ip, $user_identifier, $details);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    return false;
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

/**
 * Inicializa automáticamente las tablas de seguridad si no existen.
 */
function _init_security_tables(mysqli $db): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    $check1 = $db->query("SHOW TABLES LIKE 'security_rate_limits'");
    $check2 = $db->query("SHOW TABLES LIKE 'portal_security_logs'");
    
    if ($check1->num_rows === 0) {
        $db->query("CREATE TABLE IF NOT EXISTS security_rate_limits (
            ip_address VARCHAR(45) NOT NULL,
            action_name VARCHAR(50) NOT NULL,
            hit_count INT NOT NULL DEFAULT 1,
            first_hit TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_hit TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ip_address, action_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    if ($check2->num_rows === 0) {
        $db->query("CREATE TABLE IF NOT EXISTS portal_security_logs (
            id_log INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_identifier VARCHAR(50) DEFAULT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    $initialized = true;
}
