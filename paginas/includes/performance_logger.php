<?php
/**
 * Performance Logger - Mide y registra el tiempo de ejecución de cada petición.
 * Incluir al inicio de cualquier página: require_once 'includes/performance_logger.php';
 * Crea automáticamente la tabla 'performance_logs' si no existe.
 */

// --- Configuración ---
define('PERF_LOG_ENABLED', true);
define('PERF_LOG_SLOW_THRESHOLD', 2.0); // segundos - peticiones lentas se marcan
define('PERF_LOG_QUERY_SLOW', 1.0);     // segundos - consultas SQL lentas
define('PERF_LOG_MAX_SAMPLE_QUERY', 200); // chars máximos para guardar una consulta

// Solo en páginas del admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    return;
}

$perf_start_time = microtime(true);
$perf_start_memory = memory_get_usage();
$perf_queries = [];
$perf_include_start = 0;

// Interceptar consultas SQL lentas guardando el original mysqli_query
if (!defined('PERF_MYSQLI_WRAPPED') && PERF_LOG_ENABLED) {
    define('PERF_MYSQLI_WRAPPED', true);

    // Solo wrappear si existe $conn (conexión global)
    global $conn;

    // Crear la tabla si no existe (se ejecuta una sola vez)
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

    // Conexión directa para evitar dependencia
    try {
        $perf_db_host = $hostname ?? 'localhost';
        $perf_db_user = $username ?? 'root';
        $perf_db_pass = $password ?? '';
        $perf_db_name = $database ?? 'tecnico-administrativo-wirelessdb';

        $perf_conn = mysqli_connect($perf_db_host, $perf_db_user, $perf_db_pass, $perf_db_name);
        if ($perf_conn) {
            $perf_conn->set_charset("utf8mb4");
            mysqli_query($perf_conn, $create_sql);
            define('PERF_DB_CONN', $perf_conn);
        }
    } catch (Throwable $e) {
        // Silencio - no romper la app si falla la conexión de logging
        define('PERF_DB_CONN', null);
    }
}

/**
 * Registrar una consulta SQL para medir su duración.
 * Llamar antes/después de cada mysqli_query.
 */
function perf_start_query($sql) {
    global $perf_queries;
    if (!defined('PERF_DB_CONN') || !PERF_DB_CONN) return;
    $perf_queries[] = [
        'sql' => mb_substr($sql, 0, PERF_LOG_MAX_SAMPLE_QUERY),
        'start' => microtime(true),
        'time' => 0
    ];
}

function perf_end_query() {
    global $perf_queries;
    if (!defined('PERF_DB_CONN') || !PERF_DB_CONN) return;
    if (empty($perf_queries)) return;
    $idx = count($perf_queries) - 1;
    $perf_queries[$idx]['time'] = (microtime(true) - $perf_queries[$idx]['start']) * 1000; // ms
}

// Registrar al final de la petición
register_shutdown_function(function () use (&$perf_start_time, &$perf_start_memory, &$perf_queries) {
    if (!defined('PERF_DB_CONN') || !PERF_DB_CONN) return;

    $exec_time = microtime(true) - $perf_start_time;
    $memory_used = memory_get_usage() - $perf_start_memory;
    $peak_memory = memory_get_peak_usage();
    $is_slow = $exec_time >= PERF_LOG_SLOW_THRESHOLD;

    $num_queries = count($perf_queries);
    $slow_q = 0;
    foreach ($perf_queries as $q) {
        if ($q['time'] >= PERF_LOG_QUERY_SLOW * 1000) {
            $slow_q++;
        }
    }

    $url = $_SERVER['REQUEST_URI'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uid = $_SESSION['usuario_id'] ?? 0;
    $status = http_response_code();

    $sql = "INSERT INTO performance_logs 
        (usuario_id, url, method, exec_time, memory_used, peak_memory, 
         num_queries, slow_queries, status_code, is_slow, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare(PERF_DB_CONN, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'issdiiiiisss',
            $uid, $url, $method, $exec_time,
            $memory_used, $peak_memory, $num_queries, $slow_q,
            $status, $is_slow, $ip, $ua
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Limpiar registros viejos (> 7 días)
    if (rand(1, 100) === 1) { // 1% de probabilidad para no saturar
        mysqli_query(PERF_DB_CONN, 
            "DELETE FROM performance_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
});
