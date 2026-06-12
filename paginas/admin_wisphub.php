<?php
/**
 * paginas/admin_wisphub.php
 *
 * Panel administrativo de integración WispHub.
 * Solo accesible para usuarios administradores.
 */

$page_title = "WispHub — Panel de Integración";
require_once 'conexion.php';

// ── Crear tablas de integración si no existen ─────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `request_payload` TEXT,
    `response_payload` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `contract_id` INT DEFAULT NULL,
    `wisp_account_id` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'PENDING',
    `last_event` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contract_id` (`contract_id`),
    INDEX `idx_wisp_account_id` (`wisp_account_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE `wisp_hub_links` MODIFY `contract_id` INT DEFAULT NULL");

// ── Autoload para WispHubClient ───────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

// ── Endpoint JSON para heartbeat (AJAX) — ANTES de enviar HTML ─────────────────
if (isset($_GET['action']) && $_GET['action'] === 'cron_status' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $qLastRun = $conn->query("SELECT MAX(created_at) AS ultimo FROM wisp_hub_logs WHERE request_payload LIKE '%cron_suspend%'");
        $lastRun = $qLastRun ? $qLastRun->fetch_assoc() : null;

        $qLastPing = $conn->query("SELECT MAX(created_at) AS ultimo FROM wisp_hub_logs WHERE request_payload LIKE '%cron_job_ping%'");
        $lastPing = $qLastPing ? $qLastPing->fetch_assoc() : null;

        $qToday = $conn->query("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN response_payload LIKE '%\"status\":20%' THEN 1 ELSE 0 END) AS ok,
                   SUM(CASE WHEN response_payload NOT LIKE '%\"status\":20%' THEN 1 ELSE 0 END) AS err
            FROM wisp_hub_logs
            WHERE request_payload LIKE '%cron_suspend%' AND DATE(created_at) = CURDATE()
        ");
        $todayTotals = $qToday ? $qToday->fetch_assoc() : null;

        $qPend = $conn->query("
            SELECT COUNT(DISTINCT c.id) AS n
            FROM contratos c
            INNER JOIN wisp_hub_links wl ON wl.contract_id = c.id AND wl.wisp_account_id != ''
            INNER JOIN cuentas_por_cobrar cxc ON cxc.id_contrato = c.id
            WHERE c.estado = 'ACTIVO' AND cxc.estado = 'PENDIENTE' AND cxc.fecha_vencimiento <= CURDATE()
        ");
        $pendientes = $qPend ? $qPend->fetch_assoc() : null;

        $qPings = $conn->query("SELECT COUNT(*) AS n FROM wisp_hub_logs WHERE request_payload LIKE '%cron_job_ping%' AND DATE(created_at) = CURDATE()");
        $cronJobPings = $qPings ? $qPings->fetch_assoc() : null;

        $ultimo = $lastRun['ultimo'] ?? null;
        $health = 'gray';
        if ($ultimo) {
            $horas = (time() - strtotime($ultimo)) / 3600;
            if ($horas < 26)      $health = 'ok';
            elseif ($horas < 48)  $health = 'warning';
            else                  $health = 'danger';
        }

        $pingTime = $lastPing['ultimo'] ?? null;
        $pingHealth = 'gray';
        if ($pingTime) {
            $horasPing = (time() - strtotime($pingTime)) / 3600;
            if ($horasPing < 26)      $pingHealth = 'ok';
            elseif ($horasPing < 48)  $pingHealth = 'warning';
            else                      $pingHealth = 'danger';
        }

        echo json_encode([
            'lastRun'      => $ultimo,
            'health'       => $health,
            'hoursAgo'     => $ultimo ? round((time() - strtotime($ultimo)) / 3600, 1) : null,
            'todayOk'      => (int)($todayTotals['ok'] ?? 0),
            'todayErr'     => (int)($todayTotals['err'] ?? 0),
            'todayTotal'   => (int)($todayTotals['total'] ?? 0),
            'pending'      => (int)($pendientes['n'] ?? 0),
            'pingTime'     => $pingTime,
            'pingHealth'   => $pingHealth,
            'pingToday'    => (int)($cronJobPings['n'] ?? 0),
            'nextRun'      => $pingTime ? date('Y-m-d 01:00:00', strtotime('+1 day')) : null,
            'now'          => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'lastRun' => null, 'health' => 'gray', 'hoursAgo' => null,
            'todayOk' => 0, 'todayErr' => 0, 'todayTotal' => 0,
            'pending' => 0, 'pingTime' => null, 'pingHealth' => 'gray',
            'pingToday' => 0, 'nextRun' => null, 'now' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage(),
        ]);
    }
    $conn->close();
    exit;
}

// ── Desde aquí se envía HTML (layout) ─────────────────────────────────────────
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';

// ── Verificar que haya sesión activa ───────────────────────────────────────────
$rol_usuario = $_SESSION['rol'] ?? '';

// ── Manejo del formulario de credenciales ────────────────────────────────────
$cred_msg      = '';
$cred_msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_credentials') {
    $new_api_key    = trim($_POST['api_key']    ?? '');
    $new_api_secret = trim($_POST['api_secret'] ?? '');
    $new_base_url   = trim($_POST['base_url']   ?? '');

    if ($new_api_key && $new_base_url) {
        $cred_file = __DIR__ . '/../config/wisphub_credentials.php';
        $content   = "<?php\n";
        $content  .= "// wisphub_credentials.php — generado automáticamente. NO subir al repositorio.\n";
        $content  .= "define('WISP_HUB_API_KEY',    " . var_export($new_api_key,    true) . ");\n";
        $content  .= "define('WISP_HUB_API_SECRET',  " . var_export($new_api_secret, true) . ");\n";
        $content  .= "define('WISP_HUB_BASE_URL',    " . var_export($new_base_url,   true) . ");\n";

        if (file_put_contents($cred_file, $content, LOCK_EX) !== false) {
            // Actualizar también wisp_hub.php para incluir la URL
            $hub_file    = __DIR__ . '/../config/wisp_hub.php';
            $hub_content = "<?php\n";
            $hub_content .= "require_once __DIR__ . '/wisphub_credentials.php';\n";
            $hub_content .= "return [\n";
            $hub_content .= "    'api_key'    => WISP_HUB_API_KEY,\n";
            $hub_content .= "    'api_secret' => defined('WISP_HUB_API_SECRET') ? WISP_HUB_API_SECRET : WISP_HUB_API_KEY,\n";
            $hub_content .= "    'base_url'   => defined('WISP_HUB_BASE_URL')   ? WISP_HUB_BASE_URL   : 'https://sandbox-api.wisphub.net/api',\n";
            $hub_content .= "];\n";
            file_put_contents($hub_file, $hub_content, LOCK_EX);

            // Log de cambio de credenciales
            $log_dir = __DIR__ . '/../logs';
            if (!is_dir($log_dir)) mkdir($log_dir, 0750, true);
            file_put_contents(
                $log_dir . '/wisphub_admin.log',
                '[' . date('Y-m-d H:i:s') . '] Credenciales actualizadas por usuario ID=' . ($_SESSION['usuario_id'] ?? '?') . "\n",
                FILE_APPEND | LOCK_EX
            );

            $cred_msg = '✅ Credenciales guardadas correctamente.';
        } else {
            $cred_msg      = '❌ Error al guardar el archivo de credenciales. Verifica permisos.';
            $cred_msg_type = 'danger';
        }
    } else {
        $cred_msg      = '⚠️ API Key y URL Base son obligatorios.';
        $cred_msg_type = 'warning';
    }
}

// ── Leer credenciales actuales ───────────────────────────────────────────────
$current_api_key    = '';
$current_api_secret = '';
$current_base_url   = '';
$cred_file_path     = __DIR__ . '/../config/wisphub_credentials.php';
if (file_exists($cred_file_path)) {
    // Incluir en scope local para leer constantes
    @include $cred_file_path;
    $current_api_key    = defined('WISP_HUB_API_KEY')    ? WISP_HUB_API_KEY    : '';
    $current_api_secret = defined('WISP_HUB_API_SECRET') ? WISP_HUB_API_SECRET : '';
    $current_base_url   = defined('WISP_HUB_BASE_URL')   ? WISP_HUB_BASE_URL   : 'https://sandbox-api.wisphub.net/api';
}

// ── Paginación y filtros de logs ─────────────────────────────────────────────
$per_page    = 20;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;
$search       = trim($_GET['search'] ?? '');

$where  = '';
$params = [];
$types  = '';
if ($search !== '') {
    $where    = ' WHERE request_payload LIKE ? OR response_payload LIKE ?';
    $like     = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types    = 'ss';
}

$total_stmt = $conn->prepare("SELECT COUNT(*) FROM wisp_hub_logs" . $where);
if ($total_stmt) {
    if ($types) $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_stmt->bind_result($total_logs);
    $total_stmt->fetch();
    $total_stmt->close();
}
$total_pages = max(1, (int)ceil($total_logs / $per_page));

$logs = [];
$logs_stmt = $conn->prepare(
    "SELECT wl.id, wl.payment_id, wl.request_payload, wl.response_payload, wl.created_at
     FROM wisp_hub_logs wl" . $where . "
     ORDER BY wl.id DESC
     LIMIT ? OFFSET ?"
);
if ($logs_stmt) {
    if ($types) {
        $params[] = $per_page;
        $params[] = $offset;
        $logs_stmt->bind_param($types . 'ii', ...$params);
    } else {
        $logs_stmt->bind_param('ii', $per_page, $offset);
    }
    $logs_stmt->execute();
    $result = $logs_stmt->get_result();
    while ($row = $result->fetch_assoc()) $logs[] = $row;
    $logs_stmt->close();
}

// ── Estadísticas rápidas ─────────────────────────────────────────────────────
$stat_total   = $total_logs ?? 0;
$stat_active  = 0;
$stat_suspend = 0;

$stat_res = $conn->query("SELECT status, COUNT(*) AS c FROM wisp_hub_links GROUP BY status");
if ($stat_res) {
    while ($s = $stat_res->fetch_assoc()) {
        if ($s['status'] === 'ACTIVE')    $stat_active  = $s['c'];
        if ($s['status'] === 'SUSPENDED') $stat_suspend = $s['c'];
    }
}
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">

        <!-- ── Page header ──────────────────────────────────────────────── -->
        <div class="d-flex align-items-center justify-content-between mb-4 animate-fade">
            <div>
                <h1 class="fw-bold mb-1" style="font-size:1.6rem;">
                    <span class="badge rounded-pill me-2" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);font-size:.7rem;vertical-align:middle;">API</span>
                    WispHub — Integración
                </h1>
                <p class="text-muted small mb-0">Monitoreo de notificaciones de pago y configuración del servicio</p>
            </div>
            <a href="menu.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver
            </a>
        </div>

        <!-- ── Estadísticas ──────────────────────────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="glass-panel p-4 text-center hover-lift animate-fade">
                    <div class="mb-2">
                        <i class="fa-solid fa-list-check fa-2x" style="color:#6366f1;"></i>
                    </div>
                    <div class="fs-2 fw-bold"><?= number_format($stat_total) ?></div>
                    <div class="text-muted small">Notificaciones enviadas</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="glass-panel p-4 text-center hover-lift animate-fade" style="animation-delay:.08s">
                    <div class="mb-2">
                        <i class="fa-solid fa-circle-check fa-2x text-success"></i>
                    </div>
                    <div class="fs-2 fw-bold text-success"><?= $stat_active ?></div>
                    <div class="text-muted small">Servicios activos</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="glass-panel p-4 text-center hover-lift animate-fade" style="animation-delay:.16s">
                    <div class="mb-2">
                        <i class="fa-solid fa-circle-pause fa-2x text-warning"></i>
                    </div>
                    <div class="fs-2 fw-bold text-warning"><?= $stat_suspend ?></div>
                    <div class="text-muted small">Servicios suspendidos</div>
                </div>
            </div>
        </div>

        <!-- ── Credenciales ──────────────────────────────────────────────── -->
        <div id="credenciales" class="glass-panel p-4 mb-4 animate-fade" style="animation-delay:.1s">
            <h5 class="fw-bold mb-3">
                <i class="fa-solid fa-key me-2" style="color:#8b5cf6;"></i>
                Credenciales de la API
            </h5>

            <?php if ($cred_msg): ?>
                <div class="alert alert-<?= htmlspecialchars($cred_msg_type) ?> alert-dismissible fade show rounded-3" role="alert">
                    <?= htmlspecialchars($cred_msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="formCredenciales">
                <input type="hidden" name="action" value="update_credentials">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">API Key <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password"
                                   id="inp_api_key"
                                   name="api_key"
                                   class="form-control form-control-sm rounded-start-3"
                                   value="<?= htmlspecialchars($current_api_key) ?>"
                                   placeholder="Ej: sk_live_xxxxxxxxxxxxxxxx"
                                   required>
                            <button type="button" class="btn btn-outline-secondary btn-sm toggle-pass" data-target="inp_api_key">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">API Secret (HMAC)</label>
                        <div class="input-group">
                            <input type="password"
                                   id="inp_api_secret"
                                   name="api_secret"
                                   class="form-control form-control-sm rounded-start-3"
                                   value="<?= htmlspecialchars($current_api_secret) ?>"
                                   placeholder="Dejar vacío para usar la misma API Key">
                            <button type="button" class="btn btn-outline-secondary btn-sm toggle-pass" data-target="inp_api_secret">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">URL Base <span class="text-danger">*</span></label>
                        <input type="url"
                               name="base_url"
                               class="form-control form-control-sm rounded-3"
                               value="<?= htmlspecialchars($current_base_url) ?>"
                               placeholder="https://api.wisphub.net/api  o  https://sandbox-api.wisphub.net/api"
                               required>
                        <div class="form-text">Sandbox: <code>https://sandbox-api.wisphub.net/api</code> · Producción: <code>https://api.wisphub.net/api</code></div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm w-100 rounded-3 fw-semibold"
                                style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Guardar credenciales
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- ── Webhook URL ────────────────────────────────────────────────── -->
        <div class="glass-panel p-4 mb-4 animate-fade" style="animation-delay:.15s">
            <h5 class="fw-bold mb-2">
                <i class="fa-solid fa-webhook me-2 text-info"></i>
                URL del Webhook
            </h5>
            <p class="text-muted small mb-2">
                Configura esta URL en el panel de WispHub para recibir notificaciones de eventos (activación, suspensión, etc.):
            </p>
            <div class="input-group">
                <input type="text"
                       id="webhookUrl"
                       class="form-control form-control-sm font-monospace rounded-start-3"
                       value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'tu-dominio.com') . '/portal/wisp_hub_webhook.php') ?>"
                       readonly>
                <button class="btn btn-sm btn-outline-info" onclick="copyWebhookUrl()" title="Copiar URL">
                    <i class="fa-solid fa-copy"></i>
                </button>
            </div>
        </div>

        <!-- ── Corte automático (Cron) ─────────────────────────────────────── -->
        <div class="glass-panel p-4 mb-4 animate-fade" style="animation-delay:.18s">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-clock me-2 text-warning"></i>
                    Corte automático (Cron)
                    <span id="cronHealthDot" class="badge rounded-pill ms-2" style="font-size:.5rem;vertical-align:middle;">&bull;</span>
                </h5>
                <span id="cronLastRun" class="text-muted small">Consultando...</span>
            </div>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_cron'): ?>
                    <?php
                    $diasGracia = max(0, intval($_POST['dias_gracia'] ?? 5));
                    $batchSize  = max(1, min(200, intval($_POST['batch_size'] ?? 10)));
                    $fechaLimite = date('Y-m-d', strtotime("-{$diasGracia} days"));
                    $procesados = 0; $errores = 0; $saltados = 0;

                    $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
                    $wispClient = new \Services\WispHubClient($wispConfig);

                    $sql = "
                        SELECT DISTINCT c.id AS id_contrato, wl.wisp_account_id
                        FROM contratos c
                        INNER JOIN wisp_hub_links wl ON wl.contract_id = c.id AND wl.wisp_account_id != ''
                        INNER JOIN cuentas_por_cobrar cxc ON cxc.id_contrato = c.id
                        WHERE c.estado = 'ACTIVO'
                          AND cxc.estado = 'PENDIENTE'
                          AND cxc.fecha_vencimiento <= '$fechaLimite'
                        GROUP BY c.id
                        HAVING COUNT(cxc.id_cobro) > 0
                        ORDER BY c.id
                        LIMIT $batchSize
                    ";
                    $result = $conn->query($sql);
                    ?>
                    <div class="alert alert-info rounded-3 p-3 mb-0 w-100">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span><i class="fa-solid fa-list me-1"></i> <?= $result ? $result->num_rows : 0 ?> contratos a procesar</span>
                            <span><i class="fa-solid fa-calendar me-1"></i> Días gracia: <?= $diasGracia ?></span>
                            <span><i class="fa-solid fa-layer-group me-1"></i> Batch: <?= $batchSize ?></span>
                        </div>
                        <?php if ($result): while ($row = $result->fetch_assoc()):
                            $idContrato = (int)$row['id_contrato'];
                            $wispAccountId = $row['wisp_account_id'];

                            $checkLog = $conn->query("SELECT id FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%cron_suspend%' AND request_payload LIKE '%$wispAccountId%' AND created_at >= NOW() - INTERVAL 1 DAY");
                            if ($checkLog && $checkLog->num_rows > 0) { $saltados++; continue; }

                            try {
                                $response = $wispClient->suspendService($wispAccountId, "Corte por vencimiento - {$diasGracia} días de gracia");
                                if ($response['status'] === 200 || $response['status'] === 201) {
                                    $conn->query("UPDATE contratos SET estado = 'SUSPENDIDO' WHERE id = $idContrato AND estado = 'ACTIVO'");
                                    $conn->query("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'cron.suspend', updated_at = NOW() WHERE contract_id = $idContrato");
                                    $logP = json_encode(['action'=>'cron_suspend','contract_id'=>$idContrato,'service_id'=>$wispAccountId,'dias_gracia'=>$diasGracia]);
                                    $logR = json_encode($response);
                                    $stmtL = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, ?, ?, NOW())");
                                    if ($stmtL) { $stmtL->bind_param("ss", $logP, $logR); $stmtL->execute(); $stmtL->close(); }
                                    $procesados++;
                                } else { $errores++; }
                            } catch (Exception $e) { $errores++; }
                        endwhile; endif; ?>
                        <hr class="my-2">
                        <strong>Resultado:</strong>
                        <span class="text-success"><?= $procesados ?> suspendidos</span> ·
                        <span class="text-danger"><?= $errores ?> errores</span> ·
                        <span class="text-muted"><?= $saltados ?> ya procesados hoy</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-2">
                    <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,.03);">
                        <div class="text-muted small">Hoy</div>
                        <div class="fw-bold"><span id="cronTodayOk">-</span> <span class="text-success">✓</span> · <span id="cronTodayErr">-</span> <span class="text-danger">✗</span></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,.03);">
                        <div class="text-muted small">Pendientes</div>
                        <div class="fw-bold"><span id="cronPending">-</span></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,.03);">
                        <div class="text-muted small">Días gracia</div>
                        <div class="fw-bold">5</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,.03);">
                        <div class="text-muted small">Último heartbeat</div>
                        <div class="fw-bold"><span id="cronHeartbeatTs">-</span></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,.03);position:relative;">
                        <div class="text-muted small">
                            <i class="fa-solid fa-clock me-1"></i>cron-job.org
                            <span id="pingDot" style="color:#6b7280;font-size:1.2rem;">●</span>
                        </div>
                        <div class="fw-bold small"><span id="cronJobStatus">Sincronizando...</span></div>
                    </div>
                </div>
            </div>

            <form method="POST" onsubmit="return confirm('¿Ejecutar corte ahora? Se procesarán hasta 10 contratos.');">
                <input type="hidden" name="action" value="run_cron">
                <div class="d-flex align-items-end gap-3 flex-wrap mb-3">
                    <div>
                        <label class="form-label small mb-1 fw-semibold">Días de gracia</label>
                        <input type="number" name="dias_gracia" class="form-control form-control-sm" value="5" min="0" max="60" style="width:90px">
                    </div>
                    <div>
                        <label class="form-label small mb-1 fw-semibold">Batch</label>
                        <input type="number" name="batch_size" class="form-control form-control-sm" value="10" min="1" max="200" style="width:90px">
                    </div>
                    <button type="submit" class="btn btn-sm btn-warning fw-semibold px-3">
                        <i class="fa-solid fa-play me-1"></i> Ejecutar corte
                    </button>
                </div>
            </form>

            <div class="border-top pt-3">
                <?php
                $projectBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
                $cronJobUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $projectBase . '/wisphub_cron_dashboard.php?action=run&key=CRON_KEY';
                ?>
                <h6 class="fw-semibold mb-2"><i class="fa-solid fa-clock me-1 text-info"></i> cron-job.org — Programación automática</h6>
                <p class="text-muted small mb-2">
                    Ejecuta todos los días a la <strong>1:00 AM</strong> (America/Caracas).
                    Próxima ejecución estimada: <strong id="cronNextRun">hoy 1:00 AM</strong>.
                    <a href="https://cron-job.org" target="_blank" class="text-info">Abrir panel de cron-job.org</a>
                </p>
                <div class="input-group mb-2">
                    <span class="input-group-text" style="font-size:.8rem;"><i class="fa-solid fa-link"></i></span>
                    <input type="text"
                           class="form-control form-control-sm font-monospace"
                           id="cronJobUrl"
                           value="<?= htmlspecialchars($cronJobUrl) ?>"
                           readonly
                           onclick="this.select(); navigator.clipboard?.writeText(this.value)">
                    <button class="btn btn-sm btn-outline-info" type="button" title="Copiar URL"
                            onclick="document.getElementById('cronJobUrl').select(); navigator.clipboard?.writeText(document.getElementById('cronJobUrl').value)">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <small class="text-muted">
                        <span id="cronJobPingCount">-</span> pings recibidos de cron-job.org hoy.
                        <a href="#" id="testCronJobLink" class="text-warning">Probar conexión ahora</a>
                    </small>
                </div>
            </div>
        </div>

        <!-- ── Log de notificaciones ─────────────────────────────────────── -->
        <div class="glass-panel p-4 animate-fade" style="animation-delay:.2s">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="fw-bold mb-0">
                    <i class="fa-solid fa-terminal me-2" style="color:#6366f1;"></i>
                    Log de Notificaciones de Pago
                </h5>
                <form method="GET" class="d-flex gap-2" id="formSearch">
                    <input type="text"
                           name="search"
                           class="form-control form-control-sm rounded-3"
                           placeholder="Buscar en payloads…"
                           value="<?= htmlspecialchars($search) ?>"
                           style="min-width:200px;">
                    <button class="btn btn-sm btn-outline-secondary rounded-3">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <?php if ($search): ?>
                        <a href="admin_wisphub.php" class="btn btn-sm btn-outline-danger rounded-3">✕</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">No hay registros<?= $search ? " que coincidan con \"" . htmlspecialchars($search) . "\"" : '' ?>.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tblLogs">
                        <thead>
                            <tr class="text-muted small" style="font-size:.8rem;">
                                <th>#</th>
                                <th>ID Pago</th>
                                <th>Fecha</th>
                                <th>Request</th>
                                <th>Response</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log):
                            $req  = json_decode($log['request_payload'],  true) ?? [];
                            $resp = json_decode($log['response_payload'], true) ?? [];
                            $status_code = $resp['status'] ?? ($resp['code'] ?? '—');
                            $is_ok = ($status_code == 200 || $status_code == 201);
                        ?>
                            <tr class="log-row" data-bs-toggle="tooltip" title="Click para expandir">
                                <td class="text-muted small"><?= (int)$log['id'] ?></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-25 text-white rounded-pill">
                                        #<?= (int)$log['payment_id'] ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($log['created_at']) ?></td>
                                <td class="small">
                                    <span class="font-monospace text-truncate d-inline-block" style="max-width:160px;">
                                        <?= htmlspecialchars(substr($log['request_payload'], 0, 60)) ?>…
                                    </span>
                                </td>
                                <td class="small">
                                    <span class="font-monospace text-truncate d-inline-block" style="max-width:160px;">
                                        <?= htmlspecialchars(substr($log['response_payload'], 0, 60)) ?>…
                                    </span>
                                </td>
                                <td>
                                    <?php if ($is_ok): ?>
                                        <span class="badge rounded-pill" style="background:#16a34a22;color:#16a34a;">
                                            <i class="fa-solid fa-circle-check me-1"></i><?= $status_code ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill" style="background:#dc262622;color:#dc2626;">
                                            <i class="fa-solid fa-circle-xmark me-1"></i><?= $status_code ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Fila detalle -->
                            <tr class="d-none log-detail-<?= (int)$log['id'] ?>">
                                <td colspan="6" class="p-0">
                                    <div class="row g-0 p-3" style="background:rgba(99,102,241,.05);border-top:1px solid rgba(255,255,255,.05);">
                                        <div class="col-md-6 pe-2">
                                            <p class="small fw-semibold mb-1 text-muted">REQUEST</p>
                                            <pre class="small p-2 rounded-3 mb-0" style="background:rgba(0,0,0,.2);white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;"><?= htmlspecialchars(json_encode($req, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        </div>
                                        <div class="col-md-6 ps-2">
                                            <p class="small fw-semibold mb-1 text-muted">RESPONSE</p>
                                            <pre class="small p-2 rounded-3 mb-0" style="background:rgba(0,0,0,.2);white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;"><?= htmlspecialchars(json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-3 d-flex justify-content-center" aria-label="Paginación de logs">
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <li class="page-item <?= $p === $current_page ? 'active' : '' ?>">
                                    <a class="page-link rounded-2 mx-1"
                                       href="?page=<?= $p ?>&search=<?= urlencode($search) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div><!-- /page-content -->
</main>

<script>
// ── Toggle visibilidad contraseña ─────────────────────────────────────────────
document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
        const inp = document.getElementById(btn.dataset.target);
        const ico = btn.querySelector('i');
        if (inp.type === 'password') {
            inp.type = 'text';
            ico.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            inp.type = 'password';
            ico.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});

// ── Expandir / colapsar filas de detalle ─────────────────────────────────────
document.querySelectorAll('.log-row').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => {
        const id    = row.querySelector('td:first-child').textContent.trim();
        const detail = document.querySelector('.log-detail-' + id);
        if (detail) detail.classList.toggle('d-none');
    });
});

// ── Copiar URL webhook ────────────────────────────────────────────────────────
function copyWebhookUrl() {
    const inp = document.getElementById('webhookUrl');
    inp.select();
    navigator.clipboard.writeText(inp.value).then(() => {
        const btn = inp.nextElementSibling;
        btn.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
        setTimeout(() => btn.innerHTML = '<i class="fa-solid fa-copy"></i>', 2000);
    });
}

// ── Heartbeat: estado del cron en vivo (cada 30s) ────────────────────────────
function updateCronStatus() {
    const hb = document.getElementById('cronHeartbeatTs');
    if (hb) hb.textContent = new Date().toLocaleTimeString();

    fetch('admin_wisphub.php?action=cron_status&ajax=1&_=' + Date.now())
        .then(r => r.json())
        .then(d => {
            // Dot de salud (cortes)
            const dot = document.getElementById('cronHealthDot');
            if (dot) {
                const colors = { ok: '#22c55e', warning: '#eab308', danger: '#ef4444', gray: '#6b7280' };
                dot.style.color = colors[d.health] || colors.gray;
                dot.title = d.health === 'ok' ? 'Funcionando normal'
                          : d.health === 'warning' ? 'Sin ejecución reciente'
                          : d.health === 'danger' ? '¡Sin ejecución por más de 48h!'
                          : 'Sin datos';
            }
            // Última ejecución de corte
            const lr = document.getElementById('cronLastRun');
            if (lr) lr.textContent = d.lastRun
                ? 'Último corte: ' + d.lastRun + ' (hace ' + d.hoursAgo + 'h)'
                : 'Sin ejecuciones registradas';

            // Contadores
            document.getElementById('cronTodayOk').textContent = d.todayOk;
            document.getElementById('cronTodayErr').textContent = d.todayErr;
            document.getElementById('cronPending').textContent = d.pending;

            // cron-job.org status
            const pingDot = document.getElementById('pingDot');
            if (pingDot) {
                const pColors = { ok: '#22c55e', warning: '#eab308', danger: '#ef4444', gray: '#6b7280' };
                pingDot.style.color = pColors[d.pingHealth] || pColors.gray;
                pingDot.title = d.pingHealth === 'ok' ? 'cron-job.org activo'
                              : d.pingHealth === 'warning' ? 'Sin ping reciente'
                              : d.pingHealth === 'danger' ? '¡Sin ping por más de 48h!'
                              : 'Esperando primer ping';
            }
            const statusEl = document.getElementById('cronJobStatus');
            if (statusEl) {
                if (d.pingTime) {
                    const pingH = Math.round((Date.now() - new Date(d.pingTime).getTime()) / 3600000);
                    statusEl.textContent = 'Último ping: ' + d.pingTime + ' (hace ' + pingH + 'h) · ' + d.pingToday + ' hoy';
                } else {
                    statusEl.textContent = 'Esperando primer ping de cron-job.org...';
                }
            }
            // Ping count y próxima ejecución
            document.getElementById('cronJobPingCount').textContent = d.pingToday;
            const nextEl = document.getElementById('cronNextRun');
            if (nextEl) {
                const now = new Date();
                const next = new Date(now);
                next.setHours(1, 0, 0, 0);
                if (now.getHours() >= 1) next.setDate(next.getDate() + 1);
                nextEl.textContent = next.toLocaleDateString() + ' 1:00 AM';
            }
        })
        .catch(() => {
            document.getElementById('cronLastRun').textContent = 'Error al consultar estado';
        });
}
// Actualizar inmediatamente y cada 30s
updateCronStatus();
setInterval(updateCronStatus, 30000);

// ── Auto-trigger: si pasaron >26h sin ejecución, ejecutar corte ──────────────
function autoTriggerCron() {
    fetch('admin_wisphub.php?action=cron_status&ajax=1&_=' + Date.now())
        .then(r => r.json())
        .then(d => {
            if (d.health === 'danger' || d.health === 'gray') {
                // Ejecutar corte vía AJAX
                const form = new URLSearchParams();
                form.append('action', 'run_cron');
                form.append('dias_gracia', '5');
                form.append('batch_size', '10');
                fetch('admin_wisphub.php', { method: 'POST', body: form })
                    .then(() => { setTimeout(updateCronStatus, 5000); })
                    .catch(() => {});
            }
        })
        .catch(() => {});
}
// Ejecutar auto-trigger 2 segundos después de cargar la página
setTimeout(autoTriggerCron, 2000);

// ── Probar conexión con cron-job.org ─────────────────────────────────────────
const testLink = document.getElementById('testCronJobLink');
if (testLink) {
    testLink.addEventListener('click', function(e) {
        e.preventDefault();
        const link = this;
        const orig = link.textContent;
        link.textContent = 'Probando...';
        link.style.pointerEvents = 'none';
        const url = document.getElementById('cronJobUrl').value.replace('CRON_KEY', 'cron_wisphub_2024_secret');
        fetch(url + '&test=1&_=' + Date.now())
            .then(r => {
                link.textContent = r.ok ? '✅ Conexión exitosa' : '❌ Error HTTP ' + r.status;
                setTimeout(updateCronStatus, 2000);
            })
            .catch(() => {
                link.textContent = '❌ Error de conexión';
            })
            .finally(() => {
                setTimeout(() => {
                    link.textContent = orig;
                    link.style.pointerEvents = 'auto';
                }, 4000);
            });
    });
}

// ── Año actual en footer si existe ────────────────────────────────────────────
const yr = document.getElementById('current-year');
if (yr) yr.textContent = new Date().getFullYear();

// ── Manejo suave del scroll al hash #credenciales con compensación de cabecera ──
function scrollToCredenciales() {
    if (window.location.hash === '#credenciales') {
        const target = document.getElementById('credenciales');
        if (target) {
            // Compensar la altura del navbar fijo (aprox 80px)
            const headerOffset = 90;
            const elementPosition = target.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
            
            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });

            // Resaltar visualmente el panel de credenciales (WOW factor)
            target.style.transition = 'all 0.5s ease-in-out';
            target.style.boxShadow = '0 0 25px rgba(139, 92, 246, 0.4)';
            target.style.borderColor = 'rgba(139, 92, 246, 0.6)';
            
            setTimeout(() => {
                target.style.boxShadow = '';
                target.style.borderColor = '';
            }, 2500);
        }
    }
}

// Ejecutar al cargar la página si viene con hash
window.addEventListener('load', () => {
    setTimeout(scrollToCredenciales, 300); // Delay corto para asegurar render del DOM
});

// Ejecutar cuando cambie el hash sin recargar la página
window.addEventListener('hashchange', scrollToCredenciales);
</script>

<?php require_once 'includes/layout_foot.php'; ?>
