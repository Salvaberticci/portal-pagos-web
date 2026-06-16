<?php
/**
 * Performance Logger - Mide y registra el tiempo de ejecución de cada petición.
 * Se incluye automáticamente desde layout_head.php.
 * Crea la tabla 'performance_logs' si no existe.
 */

define('PERF_LOG_ENABLED', true);
define('PERF_LOG_SLOW_THRESHOLD', 2.0);
define('PERF_LOG_MAX_SAMPLE_QUERY', 200);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    return;
}

$perf_start_time = microtime(true);
$perf_start_memory = memory_get_usage();
$perf_queries = [];

if (!defined('PERF_LOGGER_ACTIVE') && PERF_LOG_ENABLED) {
    define('PERF_LOGGER_ACTIVE', true);

    // Asegurar que $conn exista incluyendo conexion.php si hace falta
    global $conn;
    if (!$conn) {
        $connFile = dirname(__DIR__) . '/conexion.php';
        if (file_exists($connFile)) {
            require_once $connFile;
        }
    }

    // Crear tabla si no existe
    $create_sql = "CREATE TABLE IF NOT EXISTS performance_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NULL,
        url VARCHAR(500) NOT NULL DEFAULT '',
        method VARCHAR(10) NOT NULL DEFAULT 'GET',
        exec_time DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
        memory_used INT UNSIGNED NOT NULL DEFAULT 0,
        peak_memory INT UNSIGNED NOT NULL DEFAULT 0,
        num_queries SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        slow_queries SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        status_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
        is_slow TINYINT(1) NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_slow (is_slow, created_at),
        INDEX idx_url (url(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn) {
        @$conn->query($create_sql);
    }
}

register_shutdown_function(function () use (&$perf_start_time, &$perf_start_memory, &$perf_queries) {
    if (!PERF_LOG_ENABLED) return;
    global $conn;
    if (!$conn) return;

    $exec_time = microtime(true) - $perf_start_time;
    $memory_used = memory_get_usage() - $perf_start_memory;
    $peak_memory = memory_get_peak_usage();
    $is_slow = $exec_time >= PERF_LOG_SLOW_THRESHOLD;
    $num_queries = count($perf_queries);
    $url = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uid = $_SESSION['usuario_id'] ?? 0;
    $status = http_response_code();

    $slow_q = 0;
    foreach ($perf_queries as $q) {
        if ($q['time'] >= 1000) $slow_q++;
    }

    $sql = "INSERT INTO performance_logs 
        (usuario_id, url, method, exec_time, memory_used, peak_memory,
         num_queries, slow_queries, status_code, is_slow, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = @$conn->prepare($sql);
    if ($stmt) {
        $uid = (int)$uid;
        $is_slow = (int)$is_slow;
        $status = (int)$status;
        $mem = (int)$memory_used;
        $peak = (int)$peak_memory;
        $nq = (int)$num_queries;
        $sq = (int)$slow_q;
        $stmt->bind_param('issdiiiiisss', $uid, $url, $method, $exec_time, $mem, $peak, $nq, $sq, $status, $is_slow, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }

    // Limpiar registros viejos (> 7 días) - 1% de probabilidad
    if (rand(1, 100) === 1) {
        @$conn->query("DELETE FROM performance_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
});
