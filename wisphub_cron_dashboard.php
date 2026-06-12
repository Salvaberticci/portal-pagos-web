<?php
/**
 * wisphub_cron_dashboard.php - Endpoint URL para cron de cPanel.
 *
 * Este archivo NO muestra interfaz — solo procesa la acción "run".
 * El dashboard está en paginas/admin_wisphub.php.
 *
 * Para configurar en cPanel:
 *   wget -O /dev/null "https://tudominio.com/wisphub_cron_dashboard.php?action=run&key=TU_CLAVE_SECRETA"
 *
 * Cambiá CRON_SECRET_KEY abajo por una clave real.
 */

define('CRON_SECRET_KEY', 'cron_wisphub_2024_secret');

$action = $_GET['action'] ?? '';
$key    = $_GET['key']    ?? '';

if ($action !== 'run' || $key !== CRON_SECRET_KEY) {
    header('HTTP/1.0 403 Forbidden');
    die("Acceso denegado");
}

require_once __DIR__ . '/paginas/conexion.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';

header('Content-Type: text/plain; charset=utf-8');

// Si es un test de conexión, responder rápido sin ejecutar nada
if (isset($_GET['test'])) {
    echo "OK:test\n";
    echo "time=" . date('Y-m-d H:i:s') . "\n";
    $conn->close();
    exit;
}

echo "=== Cortar Servicios Vencidos ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// Siempre registrar un heartbeat para monitorizar que cron-job.org está llamando
$conn->query("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, '{\"action\":\"cron_job_ping\",\"source\":\"cron-job.org\"}', '{\"status\":\"ok\"}', NOW())");

$diasGracia = isset($_GET['dias']) ? max(0, intval($_GET['dias'])) : 5;
$batchSize  = isset($_GET['batch']) ? max(1, min(200, intval($_GET['batch']))) : 50;
$fechaLimite = date('Y-m-d', strtotime("-{$diasGracia} days"));
$procesados = 0; $errores = 0; $saltados = 0;

$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$result = $conn->query("
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
");

if (!$result) { die("Error DB: " . $conn->error . "\n"); }

echo "Batch: {$result->num_rows} contratos\n\n";

while ($row = $result->fetch_assoc()) {
    $idContrato = (int)$row['id_contrato'];
    $wispAccountId = $row['wisp_account_id'];
    echo "[#$idContrato] account=$wispAccountId ... ";

    $checkLog = $conn->query("SELECT id FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%cron_suspend%' AND request_payload LIKE '%$wispAccountId%' AND created_at >= NOW() - INTERVAL 1 DAY");
    if ($checkLog && $checkLog->num_rows > 0) { echo "YA HOY\n"; $saltados++; continue; }

    try {
        $response = $wispClient->suspendService($wispAccountId, "Corte por vencimiento - {$diasGracia} días de gracia");
        if ($response['status'] === 200 || $response['status'] === 201) {
            $conn->query("UPDATE contratos SET estado = 'SUSPENDIDO' WHERE id = $idContrato AND estado = 'ACTIVO'");
            $conn->query("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'cron.suspend', updated_at = NOW() WHERE contract_id = $idContrato");
            $logP = json_encode(['action'=>'cron_suspend','contract_id'=>$idContrato,'service_id'=>$wispAccountId,'dias_gracia'=>$diasGracia]);
            $logR = json_encode($response);
            $stmt = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, ?, ?, NOW())");
            if ($stmt) { $stmt->bind_param("ss", $logP, $logR); $stmt->execute(); $stmt->close(); }
            echo "SUSPENDIDO\n"; $procesados++;
        } else { echo "ERROR HTTP {$response['status']}\n"; $errores++; }
    } catch (Exception $e) { echo "EXCEPCIÓN: {$e->getMessage()}\n"; $errores++; }
}

echo "\nOK=$procesados ERR=$errores SKIP=$saltados\n";
echo "Fin: " . date('Y-m-d H:i:s') . "\n";
$conn->close();
