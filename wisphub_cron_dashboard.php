<?php
/**
 * wisphub_cron_dashboard.php
 *
 * Dashboard web para monitorear y ejecutar el cron de corte de servicios WispHub.
 *
 * Acceso:
 *   https://tudominio.com/wisphub_cron_dashboard.php
 *
 * Para configurar el CRON real en cPanel, usa:
 *   wget -O /dev/null "https://tudominio.com/wisphub_cron_dashboard.php?action=run&key=SECRET_KEY"
 *   (reemplaza SECRET_KEY por el valor que definas abajo)
 *
 * Configuración:
 *   - Cambia $secretKey por una clave secreta real
 *   - Ajusta $diasGracia y $batchSize según necesites
 */

// ─── CONFIGURACIÓN ───────────────────────────────────────────────────────────
$secretKey = 'cron_wisphub_2024_secret'; // CAMBIA esto por una clave secreta real
$diasGracia = 5;
$batchSize = 50;
// ─────────────────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? 'dashboard';
$key = $_GET['key'] ?? '';

require_once __DIR__ . '/paginas/conexion.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';

// Crear tablas si no existen
$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `request_payload` TEXT,
    `response_payload` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── RUN: Ejecutar corte (via URL para cron de cPanel) ───────────────────────
if ($action === 'run') {
    if ($secretKey !== 'cron_wisphub_2024_secret' && $key !== $secretKey) {
        header('HTTP/1.0 403 Forbidden');
        die("Clave inválida");
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo "=== Cortar Servicios Vencidos (vía web) ===\n";
    echo "Iniciando: " . date('Y-m-d H:i:s') . "\n\n";

    $fechaLimite = date('Y-m-d', strtotime("-{$diasGracia} days"));
    $procesados = 0;
    $errores = 0;
    $saltados = 0;

    $wispConfig = include __DIR__ . '/config/wisp_hub.php';
    $wispClient = new \Services\WispHubClient($wispConfig);

    $sql = "
        SELECT DISTINCT c.id AS id_contrato, c.cedula, c.nombre_completo,
               wl.wisp_account_id
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
    if (!$result) {
        die("Error en consulta: " . $conn->error . "\n");
    }

    echo "Contratos en este batch: " . $result->num_rows . "\n\n";

    while ($row = $result->fetch_assoc()) {
        $idContrato = (int)$row['id_contrato'];
        $cedula = $row['cedula'];
        $nombre = $row['nombre_completo'];
        $wispAccountId = $row['wisp_account_id'];

        echo "[#{$idContrato}] {$nombre} ({$cedula})... ";

        $checkLog = $conn->query("SELECT id FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%cron_suspend%' AND request_payload LIKE '%$wispAccountId%' AND created_at >= NOW() - INTERVAL 1 DAY");
        if ($checkLog && $checkLog->num_rows > 0) {
            echo "YA PROCESADO HOY\n";
            $saltados++;
            continue;
        }

        try {
            $razon = "Corte por vencimiento de pago - {$diasGracia} días de gracia excedidos";
            $response = $wispClient->suspendService($wispAccountId, $razon);
            $exitoso = ($response['status'] === 200 || $response['status'] === 201);

            if ($exitoso) {
                $conn->query("UPDATE contratos SET estado = 'SUSPENDIDO' WHERE id = $idContrato AND estado = 'ACTIVO'");
                $conn->query("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'cron.suspend', updated_at = NOW() WHERE contract_id = $idContrato AND wisp_account_id = '$wispAccountId'");

                $logPayload = json_encode(['action'=>'cron_suspend','contract_id'=>$idContrato,'service_id'=>$wispAccountId,'reason'=>$razon,'dias_gracia'=>$diasGracia]);
                $logResponse = json_encode($response);
                $stmtLog = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, ?, ?, NOW())");
                if ($stmtLog) {
                    $stmtLog->bind_param("ss", $logPayload, $logResponse);
                    $stmtLog->execute();
                    $stmtLog->close();
                }

                echo "SUSPENDIDO (HTTP {$response['status']})\n";
                $procesados++;
            } else {
                echo "ERROR (HTTP {$response['status']})\n";
                $errores++;
            }
        } catch (Exception $e) {
            echo "EXCEPCIÓN: " . $e->getMessage() . "\n";
            $errores++;
        }
    }

    echo "\n=== Resumen ===\n";
    echo "Procesados: $procesados\n";
    echo "Errores: $errores\n";
    echo "Saltados: $saltados\n";
    echo "Finalizado: " . date('Y-m-d H:i:s') . "\n";
    $conn->close();
    exit;
}

// ─── DASHBOARD: Mostrar estado ───────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WispHub Cron Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="mb-4">WispHub — Cron de Corte</h1>

    <?php
    // Estadísticas
    $totalActivos = $conn->query("SELECT COUNT(*) AS n FROM contratos WHERE estado = 'ACTIVO'")->fetch_assoc()['n'];
    $suspendidos = $conn->query("SELECT COUNT(*) AS n FROM contratos WHERE estado = 'SUSPENDIDO'")->fetch_assoc()['n'];
    $conLink = $conn->query("SELECT COUNT(DISTINCT wl.contract_id) AS n FROM wisp_hub_links wl INNER JOIN contratos c ON c.id = wl.contract_id WHERE c.estado = 'ACTIVO' AND wl.wisp_account_id != ''")->fetch_assoc()['n'];
    ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-bg-primary">
                <div class="card-body">
                    <h5 class="card-title"><?= $totalActivos ?></h5>
                    <small>Contratos ACTIVOS</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success">
                <div class="card-body">
                    <h5 class="card-title"><?= $conLink ?></h5>
                    <small>Con WispHub link</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-warning">
                <div class="card-body">
                    <h5 class="card-title"><?= $suspendidos ?></h5>
                    <small>Suspendidos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-info">
                <div class="card-body">
                    <h5 class="card-title"><?= $totalActivos - $conLink ?></h5>
                    <small>Sin link WispHub</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimas ejecuciones del cron -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Últimas ejecuciones del cron</span>
            <span class="badge bg-secondary">Días de gracia: <?= $diasGracia ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cantidad</th>
                            <th>Último contrato</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $logs = $conn->query("
                        SELECT DATE(created_at) AS fecha, COUNT(*) AS total,
                               MAX(JSON_UNQUOTE(JSON_EXTRACT(request_payload, '$.contract_id'))) AS ultimo_contrato
                        FROM wisp_hub_logs
                        WHERE request_payload LIKE '%cron_suspend%'
                        GROUP BY DATE(created_at)
                        ORDER BY fecha DESC LIMIT 15
                    ");
                    if ($logs && $logs->num_rows > 0):
                        while ($r = $logs->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fecha']) ?></td>
                            <td><?= (int)$r['total'] ?></td>
                            <td><?= htmlspecialchars($r['ultimo_contrato'] ?? '-') ?></td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr><td colspan="3" class="text-muted">Sin ejecuciones registradas</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Configuración del cron en cPanel -->
    <div class="card mb-4">
        <div class="card-header">Configuración para cPanel</div>
        <div class="card-body">
            <p>En tu cPanel de Namecheap, ve a <strong>Cron Jobs</strong> y agrega:</p>
            <div class="mb-2">
                <label class="form-label fw-bold">Comando (para ejecución diaria a las 6 AM):</label>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="cronCommand"
                           value='wget -O /dev/null "https://<?= $_SERVER['HTTP_HOST'] ?>/wisphub_cron_dashboard.php?action=run&key=<?= htmlspecialchars($secretKey) ?>"'
                           readonly onclick="this.select(); navigator.clipboard?.writeText(this.value)">
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('cronCommand').select(); navigator.clipboard?.writeText(document.getElementById('cronCommand').value)">
                        Copiar
                    </button>
                </div>
            </div>
            <p class="text-muted small mb-0">
                ⚠️ Cambiá <code>$secretKey</code> en el archivo <code>wisphub_cron_dashboard.php</code> por una clave secreta real.<br>
                Programación: <code>0 6 * * *</code> (todos los días a las 6:00 AM).
            </p>
        </div>
    </div>

    <!-- Trigger manual -->
    <div class="card">
        <div class="card-header">Ejecutar manualmente</div>
        <div class="card-body">
            <p>Ejecutá el corte ahora mismo (máximo <?= $batchSize ?> contratos por vez):</p>
            <a href="?action=run&key=<?= urlencode($secretKey) ?>" class="btn btn-danger"
               onclick="return confirm('¿Ejecutar corte de servicios ahora?')">
                ▶ Ejecutar corte ahora
            </a>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
