<?php
$page_title = "Monitor de Rendimiento";
$path_to_root = '../../';
require_once '../conexion.php';

// Verificar conexión a la BD
if (!$conn) {
    echo '<div class="alert alert-danger m-3">Error: No hay conexión a la base de datos. Verifica conexion.php</div>';
    require_once '../includes/layout_foot.php';
    exit;
}

// Obtener nombre de la BD activa
$dbRow = $conn->query("SELECT DATABASE()")->fetch_row();
$dbName = $dbRow[0] ?? $database ?? 'tecnico-administrativo-wirelessdb';

// Crear tabla de rendimiento si no existe
$conn->query("CREATE TABLE IF NOT EXISTS performance_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';

$perf_start = microtime(true);

// --- Ruta raíz del proyecto ---
$projectRoot = dirname(__DIR__, 2);
// Verificar que estamos en el proyecto correcto, no subiendo de más
while (!file_exists($projectRoot . '/paginas/conexion.php') && $projectRoot !== dirname($projectRoot)) {
    $projectRoot = dirname($projectRoot);
}
define('PROJECT_ROOT', $projectRoot);

// --- Helper functions ---
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

function secondsToTime($seconds) {
    $seconds = max(0, $seconds);
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return "{$hours}h {$mins}m {$secs}s";
}

function microtimeToMs($microtime) {
    return round($microtime * 1000, 2);
}

function getDirectorySize($path) {
    if (!is_dir($path) && !is_file($path)) return -1;
    if (is_file($path)) return @filesize($path);
    $path = realpath($path);

    $cmds = [];
    if (PHP_OS_FAMILY !== 'Windows') {
        $cmds[] = 'exec';
        $cmds[] = 'shell_exec';
        $cmds[] = 'popen';
    } else {
        $cmds[] = 'shell_exec';
    }

    $disabled = array_map('trim', explode(',', ini_get('disable_functions')));

    foreach ($cmds as $cmd) {
        if (in_array($cmd, $disabled)) continue;
        if ($cmd === 'exec') {
            $out = []; $c = -1;
            @exec("du -sb " . escapeshellarg($path) . " 2>/dev/null", $out, $c);
            if ($c === 0 && !empty($out[0]) && preg_match('/^(\d+)/', $out[0], $m)) return (int) $m[1];
        } elseif ($cmd === 'shell_exec') {
            $out = @shell_exec("du -sb " . escapeshellarg($path) . " 2>/dev/null");
            if ($out !== null && preg_match('/^(\d+)/', $out, $m)) return (int) $m[1];
        } elseif ($cmd === 'popen') {
            $h = @popen("du -sb " . escapeshellarg($path) . " 2>/dev/null", "r");
            if (is_resource($h)) {
                $out = @fread($h, 4096);
                @pclose($h);
                if ($out !== false && preg_match('/^(\d+)/', $out, $m)) return (int) $m[1];
            }
        }
    }

    return -1;
}

function getFileCount($path) {
    if (!is_dir($path)) return 0;
    $count = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) $count++;
        }
    } catch (Throwable $e) {}
    return $count;
}

function getServerLoad() {
    // Windows: usar wmic
    if (PHP_OS_FAMILY === 'Windows') {
        $output = '';
        @exec('wmic cpu get loadpercentage 2>&1', $output);
        if (!empty($output[1])) {
            return trim($output[1]);
        }
        return null;
    }
    // Linux: leer /proc/loadavg
    if (file_exists('/proc/loadavg')) {
        $load = file_get_contents('/proc/loadavg');
        $parts = explode(' ', $load);
        return $parts[0] ?? null;
    }
    return null;
}

// --- Active tab from query string ---
$active_tab = $_GET['tab'] ?? 'resumen';
$valid_tabs = ['resumen', 'servidor', 'base-datos', 'trafico', 'benchmark', 'errores'];
if (!in_array($active_tab, $valid_tabs)) $active_tab = 'resumen';
?>
<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">
        <!-- Header -->
        <div class="glass-panel overflow-hidden position-relative mb-4 animate-fade">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3" style="background: linear-gradient(135deg, var(--primary), var(--accent));">
                        <i class="fa-solid fa-gauge-high fa-2x text-white"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1">Monitor de Rendimiento del Sistema</h4>
                        <p class="text-muted mb-0 small">Diagnóstico, métricas en vivo y detección de cuellos de botella</p>
                    </div>
                    <div class="ms-auto text-end">
                        <small class="text-muted d-block"><?php echo date('d/m/Y h:i:s A'); ?></small>
                        <a href="?tab=<?php echo $active_tab; ?>&refresh=<?php echo time(); ?>" class="btn btn-sm btn-glass mt-1">
                            <i class="fa-solid fa-rotate"></i> Actualizar
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs glass-panel mb-4 p-1" style="border-bottom: none;">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'resumen' ? 'active' : ''; ?>" href="?tab=resumen">
                    <i class="fa-solid fa-chart-simple me-1"></i> Resumen
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'servidor' ? 'active' : ''; ?>" href="?tab=servidor">
                    <i class="fa-solid fa-server me-1"></i> Servidor
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'base-datos' ? 'active' : ''; ?>" href="?tab=base-datos">
                    <i class="fa-solid fa-database me-1"></i> Base de Datos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'trafico' ? 'active' : ''; ?>" href="?tab=trafico">
                    <i class="fa-solid fa-arrow-trend-up me-1"></i> Tráfico
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'benchmark' ? 'active' : ''; ?>" href="?tab=benchmark">
                    <i class="fa-solid fa-bolt me-1"></i> Benchmark
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab === 'errores' ? 'active' : ''; ?>" href="?tab=errores">
                    <i class="fa-solid fa-bug me-1"></i> Errores PHP
                </a>
            </li>
        </ul>

<?php if ($active_tab === 'resumen'): ?>
        <!-- === TAB: RESUMEN === -->
        <div class="row g-4">
            <!-- Server Health -->
            <div class="col-lg-6">
                <div class="card h-100 border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-heart-pulse text-danger me-2"></i> Salud del Servidor</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $cpuLoad = getServerLoad();
                        $memTotal = 0; $memFree = 0; $memUsed = 0;
                        $projSize = getDirectorySize(PROJECT_ROOT);

                        if (PHP_OS_FAMILY === 'Windows') {
                            $output = @shell_exec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory 2>&1');
                            if ($output) {
                                preg_match_all('/\d+/', $output, $matches);
                                if (count($matches[0]) >= 2) {
                                    $memTotal = intval($matches[0][0]) * 1024;
                                    $memFree = intval($matches[0][1]) * 1024;
                                    $memUsed = $memTotal - $memFree;
                                }
                            }
                        } elseif (file_exists('/proc/meminfo')) {
                            $memInfo = file_get_contents('/proc/meminfo');
                            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m);
                            $memTotal = intval($m[1]) * 1024;
                            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m);
                            $memFree = intval($m[1]) * 1024;
                            $memUsed = $memTotal - $memFree;
                        }

                        $memPct = $memTotal > 0 ? round($memUsed / $memTotal * 100, 1) : 0;
                        $uptime = false;
                        if (PHP_OS_FAMILY === 'Windows') {
                            $up = @shell_exec('wmic os get lastbootuptime 2>&1');
                            if ($up && preg_match('/\d{14}/', $up, $m)) {
                                $uptime = strtotime($m[0]);
                            }
                        } elseif (file_exists('/proc/uptime')) {
                            $uptime = (int) trim(file_get_contents('/proc/uptime'));
                        }
                        ?>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted ps-0">CPU</td>
                                <td class="fw-bold">
                                    <?php if ($cpuLoad !== null): ?>
                                        <span class="badge bg-<?php echo $cpuLoad > 80 ? 'danger' : ($cpuLoad > 50 ? 'warning' : 'success'); ?>">
                                            <?php echo $cpuLoad; ?>% (<?php echo PHP_OS_FAMILY === 'Windows' ? 'uso actual' : 'load 1m'; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No disponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">RAM</td>
                                <td class="fw-bold">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $memPct > 80 ? 'danger' : ($memPct > 50 ? 'warning' : 'success'); ?>" 
                                                 style="width: <?php echo $memPct; ?>%"></div>
                                        </div>
                                        <small><?php echo formatBytes($memUsed); ?> / <?php echo formatBytes($memTotal); ?> (<?php echo $memPct; ?>%)</small>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Tamaño del Sistema</td>
                                <td class="fw-bold">
                                    <?php if ($projSize >= 0): ?>
                                        <?php echo formatBytes($projSize); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Uptime</td>
                                <td class="fw-bold">
                                    <?php if ($uptime !== false): ?>
                                        <?php echo PHP_OS_FAMILY === 'Windows' ? secondsToTime(time() - $uptime) : secondsToTime($uptime); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">PHP</td>
                                <td class="fw-bold"><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Sistema</td>
                                <td class="fw-bold"><?php echo php_uname('s') . ' ' . php_uname('r'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Database Summary -->
            <div class="col-lg-6">
                <div class="card h-100 border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-database text-info me-2"></i> Base de Datos</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $totalRows = 0;
                        $totalSize = 0;
                        $tableCount = 0;
                        $res = mysqli_query($conn, "SELECT TABLE_NAME, TABLE_ROWS, 
                            (DATA_LENGTH + INDEX_LENGTH) AS total_size,
                            ENGINE, CREATE_TIME
                            FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = '$dbName' AND TABLE_TYPE = 'BASE TABLE'
                            ORDER BY total_size DESC");
                        $tables = [];
                        while ($row = mysqli_fetch_assoc($res)) {
                            $tables[] = $row;
                            $totalRows += intval($row['TABLE_ROWS']);
                            $totalSize += intval($row['total_size']);
                            $tableCount++;
                        }
                        ?>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted ps-0">Tablas</td>
                                <td class="fw-bold"><?php echo $tableCount; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Registros totales</td>
                                <td class="fw-bold"><?php echo number_format($totalRows); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Tamaño total</td>
                                <td class="fw-bold"><?php echo formatBytes($totalSize); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Motor</td>
                                <td class="fw-bold"><?php echo $tables[0]['ENGINE'] ?? 'InnoDB'; ?></td>
                            </tr>
                        </table>
                        <hr class="my-2">
                        <h6 class="small fw-bold mb-2">Top 5 tablas más grandes</h6>
                        <?php $idx = 0; foreach ($tables as $t): if ($idx++ >= 5) break; ?>
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span class="text-muted"><?php echo htmlspecialchars($t['TABLE_NAME']); ?></span>
                                <span class="fw-bold"><?php echo formatBytes(intval($t['total_size'])); ?> (<?php echo number_format(intval($t['TABLE_ROWS'])); ?> filas)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Performance Logs Summary (if table exists) -->
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-gauge text-primary me-2"></i> Rendimiento de Peticiones</h6>
                        <span class="badge bg-<?php $perfCheck = mysqli_query($conn, "SHOW TABLES LIKE 'performance_logs'"); echo mysqli_num_rows($perfCheck) > 0 ? 'success' : 'secondary'; ?>">
                            <?php echo mysqli_num_rows($perfCheck) > 0 ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $perfExists = mysqli_num_rows($perfCheck) > 0;
                        if ($perfExists):
                            $perfStats = mysqli_fetch_assoc(mysqli_query($conn, 
                                "SELECT COUNT(*) AS total, 
                                 ROUND(AVG(exec_time),4) AS avg_time,
                                 ROUND(MAX(exec_time),4) AS max_time,
                                 SUM(is_slow) AS slow_count,
                                 ROUND(AVG(memory_used)) AS avg_mem,
                                 COUNT(DISTINCT url) AS unique_urls
                                 FROM performance_logs 
                                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"));
                        ?>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-center p-3 rounded-3" style="background: var(--table-header-bg);">
                                    <div class="fs-3 fw-bold text-primary"><?php echo number_format(intval($perfStats['total'])); ?></div>
                                    <small class="text-muted">Peticiones (24h)</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 rounded-3" style="background: var(--table-header-bg);">
                                    <div class="fs-3 fw-bold <?php echo floatval($perfStats['avg_time']) > 2 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo round(floatval($perfStats['avg_time']) * 1000, 1); ?> ms
                                    </div>
                                    <small class="text-muted">Tiempo promedio</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 rounded-3" style="background: var(--table-header-bg);">
                                    <div class="fs-3 fw-bold text-danger"><?php echo number_format(intval($perfStats['slow_count'])); ?></div>
                                    <small class="text-muted">Peticiones lentas (&gt;2s)</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 rounded-3" style="background: var(--table-header-bg);">
                                    <div class="fs-3 fw-bold text-warning"><?php echo formatBytes(intval($perfStats['avg_mem'])); ?></div>
                                    <small class="text-muted">Memoria promedio</small>
                                </div>
                            </div>
                        </div>

                        <!-- Slow requests list -->
                        <?php
                        $slowReqs = mysqli_query($conn, 
                            "SELECT url, exec_time, memory_used, num_queries, created_at, usuario_id
                             FROM performance_logs 
                             WHERE is_slow = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                             ORDER BY exec_time DESC LIMIT 10");
                        if (mysqli_num_rows($slowReqs) > 0):
                        ?>
                        <hr class="my-3">
                        <h6 class="small fw-bold mb-2 text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Peticiones más lentas (últimas 24h)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm small">
                                <thead>
                                    <tr>
                                        <th>URL</th>
                                        <th class="text-end">Tiempo</th>
                                        <th class="text-end">Memoria</th>
                                        <th class="text-end">Consultas</th>
                                        <th class="text-end">Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($r = mysqli_fetch_assoc($slowReqs)): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($r['url']); ?>"><?php echo htmlspecialchars($r['url']); ?></td>
                                        <td class="text-end fw-bold text-danger"><?php echo round(floatval($r['exec_time']) * 1000, 0); ?> ms</td>
                                        <td class="text-end"><?php echo formatBytes(intval($r['memory_used'])); ?></td>
                                        <td class="text-end"><?php echo intval($r['num_queries']); ?></td>
                                        <td class="text-end text-muted"><?php echo date('H:i:s', strtotime($r['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fa-solid fa-circle-info fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-2">El sistema de monitoreo de peticiones no está activo.</p>
                            <p class="small text-muted mb-0">Para activarlo, incluya <code>includes/performance_logger.php</code> al inicio de las páginas que desee monitorear (ya está disponible en el sistema).</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php elseif ($active_tab === 'servidor'): ?>
        <!-- === TAB: SERVIDOR === -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-microchip me-2"></i> Información del Sistema</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td class="ps-3 text-muted">Sistema Operativo</td><td class="fw-bold"><?php echo php_uname('s'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Hostname</td><td class="fw-bold"><?php echo php_uname('n'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Versión del kernel</td><td class="fw-bold"><?php echo php_uname('r'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Arquitectura</td><td class="fw-bold"><?php echo php_uname('m'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Servidor Web</td><td class="fw-bold"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td></tr>
                                <tr><td class="ps-3 text-muted">PHP Version</td><td class="fw-bold"><?php echo phpversion(); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Zend Engine</td><td class="fw-bold"><?php echo zend_version(); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Interfaz PHP</td><td class="fw-bold"><?php echo php_sapi_name(); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Memoria Límite</td><td class="fw-bold"><?php echo ini_get('memory_limit'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Max Execution Time</td><td class="fw-bold"><?php echo ini_get('max_execution_time'); ?>s</td></tr>
                                <tr><td class="ps-3 text-muted">Max Upload</td><td class="fw-bold"><?php echo ini_get('upload_max_filesize'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Post Max Size</td><td class="fw-bold"><?php echo ini_get('post_max_size'); ?></td></tr>
                                <tr><td class="ps-3 text-muted">Time Zone</td><td class="fw-bold"><?php echo date_default_timezone_get(); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-list-check me-2"></i> Extensiones PHP</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        $required = ['mysqli', 'pdo_mysql', 'gd', 'mbstring', 'curl', 'json', 'xml', 'zip', 'openssl', 'bcmath', 'fileinfo'];
                        $exts = get_loaded_extensions();
                        ?>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php foreach ($required as $ext): ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?php echo $ext; ?></td>
                                    <td class="fw-bold">
                                        <?php if (in_array($ext, $exts)): ?>
                                            <span class="text-success"><i class="fa-solid fa-check-circle"></i> OK</span>
                                        <?php else: ?>
                                            <span class="text-danger"><i class="fa-solid fa-times-circle"></i> Falta</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card border-white border-opacity-5 mt-3">
                    <div class="card-header bg-transparent border-bottom p-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-folder-open me-2"></i> Archivos por Carpeta</h6>
                        <span class="small text-muted">Tamaños vía <code>du</code>: <?php echo getDirectorySize(PROJECT_ROOT) >= 0 ? '<span class="text-success">Disponible</span>' : '<span class="text-warning">No disponible</span>'; ?></span>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $paths = [
                            'Raíz (proyecto)' => PROJECT_ROOT,
                            'paginas/' => __DIR__ . '/../',
                            'portal/' => PROJECT_ROOT . '/portal/',
                            'vendor/' => PROJECT_ROOT . '/vendor/',
                            'css/' => PROJECT_ROOT . '/css/',
                            'js/' => PROJECT_ROOT . '/js/',
                            'dompdf/' => PROJECT_ROOT . '/dompdf/',
                            'uploads/' => PROJECT_ROOT . '/uploads/',
                            'logs/' => PROJECT_ROOT . '/logs/',
                        ];
                        $raizSize = getDirectorySize(PROJECT_ROOT);
                        $raizFiles = getFileCount(PROJECT_ROOT);
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-0">Carpeta</th>
                                        <th class="text-end">Archivos</th>
                                        <th class="text-end">Tamaño</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paths as $label => $p):
                                        if (!file_exists($p)) continue;
                                        $size = getDirectorySize($p);
                                        $files = getFileCount($p);
                                    ?>
                                    <tr>
                                        <td class="ps-0 text-muted"><?php echo $label; ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format($files); ?></td>
                                        <td class="text-end fw-medium">
                                            <?php if ($size >= 0): ?>
                                                <?php echo formatBytes($size); ?>
                                            <?php else: ?>
                                                <span class="text-muted" title="Usa el visor de disco de cPanel">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td class="ps-0 border-0">Total proyecto</td>
                                        <td class="text-end border-0"><?php echo number_format($raizFiles); ?></td>
                                        <td class="text-end border-0"><?php echo $raizSize >= 0 ? formatBytes($raizSize) : 'N/A'; ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if ($raizSize < 0): ?>
                        <div class="alert alert-info py-2 small mt-2 mb-0">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            <code>exec()</code> no está disponible en este hosting. Los tamaños no se pueden calcular desde PHP.
                            Para ver el uso exacto de disco, revisá el <strong>Visor de Disco</strong> en cPanel.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- PHP Info -->
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-gear me-2"></i> Configuración PHP Destacada</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        $iniChecks = [
                            'display_errors' => ['Off', 'Producción'],
                            'error_reporting' => ['E_ALL', 'Desarrollo'],
                            'session.gc_maxlifetime' => ['2880', 'Recomendado > 7200'],
                            'max_input_time' => ['-1', 'Ilimitado'],
                            'max_input_vars' => ['1000', 'Mín. 3000 recomendado'],
                            'mysql.connect_timeout' => ['60', 'Conexión'],
                            'default_socket_timeout' => ['60', 'Timeout socket'],
                            'allow_url_fopen' => ['1', 'Permitir fopen remoto'],
                            'file_uploads' => ['1', 'Subidas habilitadas'],
                        ];
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 small">
                                <thead>
                                    <tr><th class="ps-3">Directiva</th><th>Valor Actual</th><th>Nota</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($iniChecks as $key => $info): 
                                        $val = ini_get($key);
                                        $status = $val == $info[0] ? 'success' : 'warning';
                                    ?>
                                    <tr>
                                        <td class="ps-3 text-muted"><?php echo $key; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($val ?: '0/Off'); ?></td>
                                        <td><span class="badge bg-<?php echo $status; ?> bg-opacity-10 text-<?php echo $status; ?>"><?php echo $info[1]; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php elseif ($active_tab === 'base-datos'): ?>
        <!-- === TAB: BASE DE DATOS === -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-table me-2"></i> Tablas - Tamaño y Filas</h6>
                        <small class="text-muted">Base de datos: <?php echo htmlspecialchars($database ?? 'N/A'); ?></small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0 datatable">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Tabla</th>
                                        <th class="text-end">Filas</th>
                                        <th class="text-end">Tamaño (Data)</th>
                                        <th class="text-end">Tamaño (Índices)</th>
                                        <th class="text-end">Total</th>
                                        <th>Motor</th>
                                        <th>Creación</th>
                                        <th>Promedio fila</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res2 = mysqli_query($conn, "SELECT TABLE_NAME, TABLE_ROWS, 
                                        DATA_LENGTH, INDEX_LENGTH, (DATA_LENGTH + INDEX_LENGTH) AS total_size,
                                        ENGINE, CREATE_TIME, AVG_ROW_LENGTH
                                        FROM information_schema.TABLES 
                                        WHERE TABLE_SCHEMA = '$dbName' AND TABLE_TYPE = 'BASE TABLE'
                                        ORDER BY total_size DESC");
                                    while ($t = mysqli_fetch_assoc($res2)):
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-medium"><?php echo htmlspecialchars($t['TABLE_NAME']); ?></td>
                                        <td class="text-end"><?php echo number_format(intval($t['TABLE_ROWS'])); ?></td>
                                        <td class="text-end"><?php echo formatBytes(intval($t['DATA_LENGTH'])); ?></td>
                                        <td class="text-end"><?php echo formatBytes(intval($t['INDEX_LENGTH'])); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatBytes(intval($t['total_size'])); ?></td>
                                        <td><?php echo $t['ENGINE'] ?? 'InnoDB'; ?></td>
                                        <td class="text-muted"><?php echo $t['CREATE_TIME'] ?? '-'; ?></td>
                                        <td class="text-end small text-muted"><?php echo formatBytes(intval($t['AVG_ROW_LENGTH'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database health checks -->
            <div class="col-lg-6">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-stethoscope me-2"></i> Diagnóstico Rápido</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $diag = [];
                        // Check auto_increment tables near limit
                        $resAI = mysqli_query($conn, "SELECT TABLE_NAME, AUTO_INCREMENT 
                            FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = '$dbName' AND AUTO_INCREMENT IS NOT NULL
                            ORDER BY AUTO_INCREMENT DESC LIMIT 5");
                        $diag['auto_increment'] = [];
                        while ($r = mysqli_fetch_assoc($resAI)) {
                            $diag['auto_increment'][] = $r['TABLE_NAME'] . ' (ID: ' . number_format(intval($r['AUTO_INCREMENT'])) . ')';
                        }

                        // Check for tables using MyISAM
                        $resMyISAM = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = '$dbName' AND ENGINE = 'MyISAM'");
                        $myisamCount = mysqli_num_rows($resMyISAM);

                        // Check fragmentation
                        $resFrag = mysqli_query($conn, "SELECT TABLE_NAME, (DATA_FREE / (DATA_LENGTH + INDEX_LENGTH + DATA_FREE)) * 100 AS frag_pct
                            FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = '$dbName' AND (DATA_LENGTH + INDEX_LENGTH) > 0
                            HAVING frag_pct > 10
                            ORDER BY frag_pct DESC LIMIT 5");
                        ?>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted ps-0">Tablas MyISAM</td>
                                <td class="fw-bold"><?php echo $myisamCount > 0 ? "<span class='text-warning'>{$myisamCount} tablas</span>" : "<span class='text-success'>0 (todas InnoDB)</span>"; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Fragmentación > 10%</td>
                                <td class="fw-bold"><?php echo mysqli_num_rows($resFrag) > 0 ? "<span class='text-warning'>" . mysqli_num_rows($resFrag) . " tablas</span>" : "<span class='text-success'>Ninguna</span>"; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Siguientes IDs altos</td>
                                <td class="small"><?php echo !empty($diag['auto_increment']) ? implode(', ', $diag['auto_increment']) : '<span class="text-muted">-</span>'; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Versión MySQL</td>
                                <td class="fw-bold"><?php echo mysqli_get_server_info($conn); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Tiempo de conexión</td>
                                <td class="fw-bold"><?php echo mysqli_stat($conn) ? 'Conectado' : 'Desconectado'; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Charset conexión</td>
                                <td class="fw-bold"><?php echo $conn ? $conn->character_set_name() : 'N/A'; ?></td>
                            </tr>
                            <?php
                            $uptimeRes = mysqli_query($conn, "SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Uptime'");
                            $uptimeRow = mysqli_fetch_assoc($uptimeRes);
                            if ($uptimeRow):
                            ?>
                            <tr>
                                <td class="text-muted ps-0">Uptime MySQL</td>
                                <td class="fw-bold"><?php echo secondsToTime(intval($uptimeRow['VARIABLE_VALUE'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Query performance info -->
            <div class="col-lg-6">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-chart-bar me-2"></i> Estadísticas de Consultas</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $statusVars = [
                            'Questions' => 'Consultas totales',
                            'Slow_queries' => 'Consultas lentas',
                            'Com_select' => 'SELECT',
                            'Com_insert' => 'INSERT',
                            'Com_update' => 'UPDATE',
                            'Com_delete' => 'DELETE',
                            'Threads_connected' => 'Conexiones activas',
                            'Threads_running' => 'Hilos ejecutándose',
                            'Bytes_received' => 'Bytes recibidos',
                            'Bytes_sent' => 'Bytes enviados',
                        ];
                        ?>
                        <table class="table table-sm table-borderless mb-0 small">
                            <?php foreach ($statusVars as $var => $label): 
                                $r = mysqli_query($conn, "SHOW GLOBAL STATUS LIKE '$var'");
                                $d = mysqli_fetch_assoc($r);
                                $val = $d ? $d['Value'] : 'N/A';
                            ?>
                            <tr>
                                <td class="text-muted ps-0"><?php echo $label; ?></td>
                                <td class="fw-bold text-end"><?php echo is_numeric($val) ? number_format(intval($val)) : $val; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

<?php elseif ($active_tab === 'trafico'): ?>
        <!-- === TAB: TRÁFICO === -->
        <div class="row g-4">
            <?php $perfExists = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'performance_logs'")) > 0; ?>
            <?php if ($perfExists): ?>
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i> Historial de Peticiones</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0 datatable">
                                <thead>
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>URL</th>
                                        <th class="text-end">Tiempo</th>
                                        <th class="text-end">Memoria</th>
                                        <th class="text-end">Consultas</th>
                                        <th>IP</th>
                                        <th>Usuario</th>
                                        <th class="text-end">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $perfPage = max(1, intval($_GET['perf_page'] ?? 1));
                                    $perfLimit = 50;
                                    $perfOffset = ($perfPage - 1) * $perfLimit;
                                    $perfTotal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM performance_logs"));
                                    $perfTotalRows = intval($perfTotal['c']);
                                    $perfPages = max(1, ceil($perfTotalRows / $perfLimit));
                                    $perfRes = mysqli_query($conn, "SELECT * FROM performance_logs ORDER BY id DESC LIMIT $perfLimit OFFSET $perfOffset");
                                    while ($p = mysqli_fetch_assoc($perfRes)):
                                    ?>
                                    <tr class="<?php echo intval($p['is_slow']) ? 'table-danger' : ''; ?>">
                                        <td class="ps-3 text-muted"><?php echo $p['id']; ?></td>
                                        <td class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($p['url']); ?>">
                                            <span class="badge bg-<?php echo $p['method'] === 'POST' ? 'warning' : 'info'; ?> bg-opacity-10 text-<?php echo $p['method'] === 'POST' ? 'warning' : 'info'; ?> me-1"><?php echo $p['method']; ?></span>
                                            <?php echo htmlspecialchars($p['url']); ?>
                                        </td>
                                        <td class="text-end fw-bold <?php echo intval($p['is_slow']) ? 'text-danger' : ''; ?>">
                                            <?php echo round(floatval($p['exec_time']) * 1000, 0); ?> ms
                                        </td>
                                        <td class="text-end"><?php echo formatBytes(intval($p['memory_used'])); ?></td>
                                        <td class="text-end"><?php echo $p['num_queries']; ?></td>
                                        <td class="small text-muted"><?php echo $p['ip_address']; ?></td>
                                        <td class="small text-muted"><?php echo $p['usuario_id'] ? '#' . $p['usuario_id'] : '-'; ?></td>
                                        <td class="text-end text-muted small"><?php echo date('d/m H:i:s', strtotime($p['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($perfPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center p-3 border-top">
                            <small class="text-muted">Página <?php echo $perfPage; ?> de <?php echo $perfPages; ?> (<?php echo number_format($perfTotalRows); ?> registros)</small>
                            <div>
                                <?php if ($perfPage > 1): ?>
                                    <a href="?tab=trafico&perf_page=<?php echo $perfPage - 1; ?>" class="btn btn-sm btn-glass">Anterior</a>
                                <?php endif; ?>
                                <?php if ($perfPage < $perfPages): ?>
                                    <a href="?tab=trafico&perf_page=<?php echo $perfPage + 1; ?>" class="btn btn-sm btn-glass">Siguiente</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Traffic Stats -->
            <div class="col-lg-6">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-calendar-day me-2"></i> Por Hora (últimas 24h)</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $hourly = mysqli_query($conn, 
                            "SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt,
                             ROUND(AVG(exec_time)*1000,1) AS avg_ms
                             FROM performance_logs
                             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                             GROUP BY HOUR(created_at)
                             ORDER BY hr ASC");
                        $maxCnt = 0;
                        $hourlyData = [];
                        while ($h = mysqli_fetch_assoc($hourly)) {
                            $hourlyData[intval($h['hr'])] = $h;
                            $maxCnt = max($maxCnt, intval($h['cnt']));
                        }
                        ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php for ($i = 0; $i < 24; $i++): 
                                $data = $hourlyData[$i] ?? null;
                                $cnt = $data ? intval($data['cnt']) : 0;
                                $avg = $data ? $data['avg_ms'] : 0;
                                $barH = $maxCnt > 0 ? max(4, round($cnt / $maxCnt * 60)) : 4;
                            ?>
                            <div class="d-flex flex-column align-items-center" style="flex: 1 0 3.8%;">
                                <small class="text-muted" style="font-size: 0.55rem;"><?php echo $avg > 0 ? $avg : ''; ?></small>
                                <div style="height: 60px; width: 100%; display: flex; align-items: flex-end; justify-content: center;">
                                    <div title="<?php echo $cnt; ?> peticiones, <?php echo $avg; ?>ms avg" 
                                         style="width: 70%; height: <?php echo $barH; ?>px; 
                                                background: <?php echo $avg > 2000 ? 'var(--danger)' : ($avg > 1000 ? 'var(--warning)' : 'var(--primary)'); ?>; 
                                                border-radius: 2px 2px 0 0; opacity: 0.8;"></div>
                                </div>
                                <small class="text-muted" style="font-size: 0.55rem;"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></small>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <div class="d-flex justify-content-center gap-3 mt-2 small text-muted">
                            <span><span class="badge bg-primary bg-opacity-25">&nbsp;&nbsp;</span> &lt;1s</span>
                            <span><span class="badge bg-warning bg-opacity-25">&nbsp;&nbsp;</span> 1-2s</span>
                            <span><span class="badge bg-danger bg-opacity-25">&nbsp;&nbsp;</span> &gt;2s</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-file me-2"></i> Páginas más visitadas (24h)</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm small mb-0">
                            <thead>
                                <tr><th class="ps-3">URL</th><th class="text-end">Peticiones</th><th class="text-end">Tiempo Prom.</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $topUrls = mysqli_query($conn, 
                                    "SELECT url, COUNT(*) AS cnt, ROUND(AVG(exec_time)*1000,1) AS avg_ms,
                                     ROUND(MAX(exec_time)*1000,0) AS max_ms
                                     FROM performance_logs
                                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                     GROUP BY url ORDER BY cnt DESC LIMIT 15");
                                while ($u = mysqli_fetch_assoc($topUrls)):
                                ?>
                                <tr>
                                    <td class="ps-3 text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($u['url']); ?></td>
                                    <td class="text-end fw-bold"><?php echo $u['cnt']; ?></td>
                                    <td class="text-end <?php echo floatval($u['avg_ms']) > 2000 ? 'text-danger' : ''; ?>"><?php echo $u['avg_ms']; ?> ms</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-body text-center py-5">
                        <i class="fa-solid fa-circle-info fa-4x text-muted mb-3"></i>
                        <h5>Monitoreo de Tráfico Inactivo</h5>
                        <p class="text-muted">Active el sistema de logging incluyendo <code>includes/performance_logger.php</code> al inicio de las páginas.</p>
                        <p class="small text-muted mb-0">Una vez activo, aquí verá el historial completo de peticiones con tiempos de carga, memoria y más.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

<?php elseif ($active_tab === 'benchmark'): ?>
        <!-- === TAB: BENCHMARK === -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-gauge-high me-2"></i> Benchmark del Sistema</h6>
                        <form method="GET" class="d-inline">
                            <input type="hidden" name="tab" value="benchmark">
                            <button name="run_bench" value="1" class="btn btn-sm btn-glass">
                                <i class="fa-solid fa-play"></i> Ejecutar Benchmark
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        if (isset($_GET['run_bench'])):
                            $results = [];

                            // 1. PHP speed test (calc primes)
                            $start = microtime(true);
                            $cnt = 0;
                            for ($i = 2; $i < 50000; $i++) {
                                $prime = true;
                                for ($j = 2; $j * $j <= $i; $j++) {
                                    if ($i % $j == 0) { $prime = false; break; }
                                }
                                if ($prime) $cnt++;
                            }
                            $results['PHP Cálculo (primos 1-50000)'] = microtime(true) - $start;

                            // 2. String operations
                            $start = microtime(true);
                            $s = str_repeat('Hello World ', 1000);
                            for ($i = 0; $i < 10000; $i++) {
                                str_replace('World', 'PHP', $s);
                                md5($s);
                                substr($s, 0, 100);
                            }
                            $results['PHP Strings (10k ops)'] = microtime(true) - $start;

                            // 3. Array operations
                            $start = microtime(true);
                            $arr = range(1, 5000);
                            for ($i = 0; $i < 100; $i++) {
                                shuffle($arr);
                                sort($arr);
                                array_reverse($arr);
                            }
                            $results['PHP Arrays (100 ops)'] = microtime(true) - $start;

                            // 4. MySQL query speed
                            $start = microtime(true);
                            for ($i = 0; $i < 10; $i++) {
                                $q = mysqli_query($conn, "SELECT COUNT(*) as c FROM contratos");
                                mysqli_fetch_assoc($q);
                            }
                            $results['MySQL Consultas (10x COUNT contratos)'] = microtime(true) - $start;

                            // 5. MySQL insert/select
                            $start = microtime(true);
                            $testVal = 'perf_test_' . time();
                            mysqli_query($conn, "INSERT INTO performance_logs (url, method, exec_time, memory_used, peak_memory, num_queries, slow_queries, created_at, ip_address, user_agent) VALUES ('_benchmark_','BENCH',$start,0,0,0,0,NOW(),'','')");
                            $insId = mysqli_insert_id($conn);
                            $q = mysqli_query($conn, "SELECT * FROM performance_logs WHERE id = $insId");
                            mysqli_fetch_assoc($q);
                            mysqli_query($conn, "DELETE FROM performance_logs WHERE id = $insId");
                            $results['MySQL Insert+Select+Delete'] = microtime(true) - $start;

                            // 6. File I/O (temp write)
                            $start = microtime(true);
                            $tmpFile = sys_get_temp_dir() . '/perf_test_' . uniqid() . '.tmp';
                            for ($i = 0; $i < 10; $i++) {
                                file_put_contents($tmpFile, str_repeat('x', 65536));
                                file_get_contents($tmpFile);
                            }
                            unlink($tmpFile);
                            $results['Archivo I/O (10x 64KB)'] = microtime(true) - $start;
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Prueba</th><th class="text-end">Tiempo</th><th class="text-end">Calificación</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $testName => $time): 
                                        $ms = $time * 1000;
                                        $grade = $ms < 100 ? 'Excelente' : ($ms < 300 ? 'Bueno' : ($ms < 800 ? 'Regular' : 'Lento'));
                                        $color = $ms < 100 ? 'success' : ($ms < 300 ? 'primary' : ($ms < 800 ? 'warning' : 'danger'));
                                    ?>
                                    <tr>
                                        <td><?php echo $testName; ?></td>
                                        <td class="text-end fw-bold"><?php echo round($ms, 1); ?> ms</td>
                                        <td class="text-end"><span class="badge bg-<?php echo $color; ?>"><?php echo $grade; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fa-solid fa-gauge-high fa-4x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Presione "Ejecutar Benchmark" para probar el rendimiento de PHP, MySQL y el sistema de archivos.</p>
                            <p class="small text-muted">Las pruebas toman unos segundos e incluyen: cálculo numérico, manipulación de strings, arrays, consultas SQL y E/S de archivos.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Page Load Test -->
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-stopwatch me-2"></i> Tiempo de Carga de Páginas Clave</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $keyPages = [
                            'Panel Principal' => PROJECT_ROOT . '/paginas/menu.php',
                            'Gestión Contratos' => PROJECT_ROOT . '/paginas/principal/gestion_contratos.php',
                            'Gestión Mensualidades' => PROJECT_ROOT . '/paginas/principal/gestion_mensualidades.php',
                            'Gestión Deudores' => PROJECT_ROOT . '/paginas/principal/gestion_deudores.php',
                            'Gestión Usuarios' => PROJECT_ROOT . '/paginas/gestion_usuarios.php',
                            'Gestión Fallas' => PROJECT_ROOT . '/paginas/soporte/gestion_fallas.php',
                            'Conciliación' => PROJECT_ROOT . '/paginas/principal/conciliacion.php',
                        ];
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Página</th><th class="text-end">Tiempo</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keyPages as $label => $file): 
                                        $path = __DIR__ . '/' . $file;
                                        if (!file_exists($path)) {
                                            echo "<tr><td>{$label}</td><td class='text-end text-muted'>Archivo no encontrado</td></tr>";
                                            continue;
                                        }
                                        $t0 = microtime(true);
                                        // Simular include
                                        $phpContent = file_get_contents($path);
                                        $t1 = microtime(true);
                                        $loadMs = round(($t1 - $t0) * 1000, 1);
                                        $sizeKb = round(strlen($phpContent) / 1024, 1);
                                    ?>
                                    <tr>
                                        <td><?php echo $label; ?> <small class="text-muted">(<?php echo $sizeKb; ?> KB)</small></td>
                                        <td class="text-end fw-bold"><?php echo $loadMs; ?> ms</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2 small text-muted">
                            <i class="fa-solid fa-info-circle me-1"></i> Mide el tiempo de lectura del archivo desde disco. El tiempo real de ejecución puede ser mayor.
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php elseif ($active_tab === 'errores'): ?>
        <!-- === TAB: ERRORES PHP === -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-white border-opacity-5">
                    <div class="card-header bg-transparent border-bottom p-3">
                        <h6 class="fw-bold mb-0"><i class="fa-solid fa-bug me-2"></i> Log de Errores PHP</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php
                        $errorLog = ini_get('error_log');
                        $logLines = [];
                        $errorCount = 0;
                        $errorTypes = [];

                        if ($errorLog && file_exists($errorLog)) {
                            $logContent = file($errorLog);
                            $logContent = array_reverse($logContent);
                            $maxLines = min(200, count($logContent));
                            for ($i = 0; $i < $maxLines; $i++) {
                                $line = $logContent[$i];
                                $logLines[] = $line;
                                $errorCount++;
                                // Count by type
                                if (preg_match('/PHP (Fatal error|Warning|Notice|Parse error|Error)/', $line, $m)) {
                                    $type = $m[1];
                                    $errorTypes[$type] = ($errorTypes[$type] ?? 0) + 1;
                                }
                            }
                        }

                        // Also check for error_log files in app directories
                        $appLogs = [];
                        $searchDirs = [
                            PROJECT_ROOT . '/logs/',
                            __DIR__ . '/../',
                            PROJECT_ROOT,
                        ];
                        foreach ($searchDirs as $dir) {
                            if (is_dir($dir)) {
                                foreach (glob($dir . 'error_log*') as $f) {
                                    $appLogs[] = $f;
                                }
                                foreach (glob($dir . '*.log') as $f) {
                                    if (basename($f) !== 'wisphub_admin.log') continue;
                                    $appLogs[] = $f;
                                }
                            }
                        }
                        $appLogs = array_unique($appLogs);
                        ?>
                        <?php if ($errorLog && file_exists($errorLog)): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-danger me-2"><?php echo $errorCount; ?> errores recientes</span>
                                <?php foreach ($errorTypes as $type => $count): ?>
                                    <span class="badge bg-<?php echo $type === 'Fatal error' || $type === 'Parse error' ? 'danger' : ($type === 'Warning' ? 'warning' : 'secondary'); ?> bg-opacity-10 text-<?php echo $type === 'Fatal error' || $type === 'Parse error' ? 'danger' : ($type === 'Warning' ? 'warning' : 'secondary'); ?> me-1">
                                        <?php echo $type; ?>: <?php echo $count; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Archivo: <?php echo htmlspecialchars($errorLog); ?></small>
                        </div>
                        <div style="max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 0.75rem; background: #1a1a2e; color: #e0e0e0; padding: 1rem; border-radius: 8px;">
                            <?php foreach ($logLines as $line): 
                                $cssClass = '';
                                if (preg_match('/PHP (Fatal error|Parse error)/', $line)) $cssClass = 'color: #ff6b6b;';
                                elseif (preg_match('/PHP Warning/', $line)) $cssClass = 'color: #ffd93d;';
                                elseif (preg_match('/PHP Notice/', $line)) $cssClass = 'color: #6bcbff;';
                            ?>
                            <div style="<?php echo $cssClass; ?>"><?php echo htmlspecialchars($line); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fa-solid fa-check-circle fa-4x text-success mb-3"></i>
                            <p class="text-muted">No se encontró el archivo de log de errores PHP, o está vacío.</p>
                            <p class="small text-muted">Ruta configurada: <code><?php echo htmlspecialchars($errorLog ?: 'No configurado'); ?></code></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($appLogs)): ?>
                        <hr class="my-3">
                        <h6 class="fw-bold mb-3">Logs de Aplicación</h6>
                        <?php foreach ($appLogs as $logFile): 
                            $logName = basename($logFile);
                            $logSize = filesize($logFile);
                            $logMtime = date('d/m/Y H:i:s', filemtime($logFile));
                            $logContent = file_get_contents($logFile);
                            $logLines = explode("\n", trim($logContent));
                            $logLines = array_reverse($logLines);
                            $logLines = array_slice($logLines, 0, 50);
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold small"><?php echo htmlspecialchars($logName); ?></span>
                                <span class="text-muted small"><?php echo formatBytes($logSize); ?> - Última modificación: <?php echo $logMtime; ?></span>
                            </div>
                            <div style="max-height: 300px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 0.7rem; background: #1a1a2e; color: #e0e0e0; padding: 0.75rem; border-radius: 6px;">
                                <?php foreach ($logLines as $l): ?>
                                <div><?php echo htmlspecialchars($l); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php endif; ?>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 30s on the summary tab
    <?php if ($active_tab === 'resumen'): ?>
    setTimeout(function() {
        // Only if not being interacted with
        window.location.href = '?tab=resumen&refresh=' + Date.now();
    }, 30000);
    <?php endif; ?>

    // Progress bar animations
    document.querySelectorAll('.progress-bar').forEach(function(bar) {
        var width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(function() {
            bar.style.width = width;
            bar.style.transition = 'width 0.8s ease';
        }, 100);
    });
});
</script>

<?php require_once '../includes/layout_foot.php'; ?>
