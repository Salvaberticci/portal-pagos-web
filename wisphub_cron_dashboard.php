<?php
/**
 * wisphub_cron_dashboard.php - Endpoint URL para cron-job.org.
 *
 * Procesa en orden:
 *   1. Genera facturas mensuales (contratos ACTIVO sin factura del mes)
 *   2. Suspende servicios vencidos (más de dias_gracia días sin pagar)
 *
 * El dashboard está en paginas/admin_wisphub.php.
 *
 * Para configurar en cron-job.org o UptimeRobot:
 *   https://tudominio.com/wisphub_cron_dashboard.php?action=run&key=TU_CLAVE_SECRETA
 *
 * La clave secreta se define en config/wisphub_credentials.php como WISP_HUB_CRON_SECRET.
 */

$action = $_GET['action'] ?? '';
$key    = $_GET['key']    ?? '';

$wispConfig = @include __DIR__ . '/config/wisp_hub.php';
$cronSecret = is_array($wispConfig) && !empty($wispConfig['cron_secret']) ? $wispConfig['cron_secret'] : '';

if ($action !== 'run' || $key !== $cronSecret || empty($cronSecret)) {
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

echo "=== Cron WispHub ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// Siempre registrar un heartbeat para monitorizar que cron-job.org está llamando
$conn->query("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, '{\"action\":\"cron_job_ping\",\"source\":\"cron-job.org\"}', '{\"status\":\"ok\"}', NOW())");

// ─────────────────────────────────────────────────────────────────────────────
// 1. GENERAR FACTURAS MENSUALES
// ─────────────────────────────────────────────────────────────────────────────
echo "--- Generar Facturas Mensuales ---\n";

$fecha_emision = date('Y-m-d');
$fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));
$mes_actual = date('Y-m');

echo "Fecha Emision: $fecha_emision | Vencimiento: $fecha_vencimiento\n";

$sql_contratos = "
    SELECT c.id, p.monto, c.id_plan
    FROM contratos c
    JOIN planes p ON c.id_plan = p.id_plan
    WHERE c.estado = 'ACTIVO'
      AND p.monto > 0
      AND NOT EXISTS (
          SELECT 1 FROM cuentas_por_cobrar cxc
          WHERE cxc.id_contrato = c.id
            AND cxc.fecha_emision LIKE '{$mes_actual}%'
      )
";

$resultado_contratos = $conn->query($sql_contratos);
$contador_facturas = 0;

if ($resultado_contratos === FALSE) {
    echo "Error SQL: " . $conn->error . "\n";
} elseif ($resultado_contratos->num_rows > 0) {
    echo "Generando facturas para $mes_actual...\n";

    $stmt_insert = $conn->prepare("
        INSERT INTO cuentas_por_cobrar
        (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, id_plan_cobrado)
        VALUES (?, ?, ?, ?, 'PENDIENTE', ?)
    ");

    if ($stmt_insert === FALSE) {
        echo "Error prepare: " . $conn->error . "\n";
    } else {
        while ($fila = $resultado_contratos->fetch_assoc()) {
            $stmt_insert->bind_param("issdi",
                $fila['id'],
                $fecha_emision,
                $fecha_vencimiento,
                $fila['monto'],
                $fila['id_plan']
            );
            if ($stmt_insert->execute()) {
                $contador_facturas++;
            } else {
                echo "Error contrato #{$fila['id']}: " . $stmt_insert->error . "\n";
            }
        }
        $stmt_insert->close();
    }
    echo "Facturas generadas: $contador_facturas\n";
} else {
    echo "No hay contratos pendientes de facturar.\n";
}

echo "\n";

// ─────────────────────────────────────────────────────────────────────────────
// 2. SUSPENDER SERVICIOS VENCIDOS
// ─────────────────────────────────────────────────────────────────────────────
echo "--- Cortar Servicios Vencidos ---\n";

$diasGracia = isset($_GET['dias']) ? max(0, intval($_GET['dias'])) : 5;
$batchSize  = isset($_GET['batch']) ? max(1, min(200, intval($_GET['batch']))) : 50;
$fechaLimite = date('Y-m-d', strtotime("-{$diasGracia} days"));
$procesados = 0; $errores = 0; $saltados = 0;

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
