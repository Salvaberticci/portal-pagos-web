<?php
/**
 * cron/cortar_servicios_vencidos.php
 *
 * Script para ejecutar vía CRON.
 * Busca contratos con cuentas por cobrar vencidas (más de N días de gracia)
 * y suspende el servicio en WispHub.
 *
 * Uso (CLI):
 *   php cron/cortar_servicios_vencidos.php [dias_gracia]
 *
 * Por defecto: 5 días de gracia después del vencimiento.
 *
 * Configurar en crontab (todos los días a las 6:00 AM):
 *   0 6 * * * /usr/bin/php /ruta/al/proyecto/cron/cortar_servicios_vencidos.php
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

$diasGracia = isset($argv[1]) ? intval($argv[1]) : 5;
if ($diasGracia < 0) $diasGracia = 0;

echo "=== Cortar Servicios Vencidos ===\n";
echo "Días de gracia: $diasGracia\n";
echo "Iniciando: " . date('Y-m-d H:i:s') . "\n\n";

require_once __DIR__ . '/../paginas/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$fechaLimite = date('Y-m-d', strtotime("-{$diasGracia} days"));
$procesados = 0;
$errores = 0;
$saltados = 0;

// Buscar contratos ACTIVOS con cuentas por cobrar PENDIENTE vencidas
// Solo considerar contratos que tengan wisp_hub_links (integración con WispHub)
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
";

$result = $conn->query($sql);
if (!$result) {
    die("Error en consulta: " . $conn->error . "\n");
}

echo "Contratos a procesar: " . $result->num_rows . "\n\n";

while ($row = $result->fetch_assoc()) {
    $idContrato = (int)$row['id_contrato'];
    $cedula = $row['cedula'];
    $nombre = $row['nombre_completo'];
    $wispAccountId = $row['wisp_account_id'];

    echo "[#{$idContrato}] {$nombre} ({$cedula}) - Account: {$wispAccountId}... ";

    // Saltar si ya se suspendió hoy (evita reintentos diarios)
    $checkLog = $conn->query("SELECT id FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%cron_suspend%' AND request_payload LIKE '%$wispAccountId%' AND created_at >= NOW() - INTERVAL 1 DAY");
    if ($checkLog && $checkLog->num_rows > 0) {
        echo "YA PROCESADO HOY (saltando)\n";
        $saltados++;
        continue;
    }

    try {
        // Llamar a WispHub para suspender el servicio
        $razon = "Corte por vencimiento de pago - " . $diasGracia . " días de gracia excedidos";
        $response = $wispClient->suspendService($wispAccountId, $razon);

        $exitoso = ($response['status'] === 200 || $response['status'] === 201);

        if ($exitoso) {
            // Actualizar estado del contrato
            $stmtUpd = $conn->prepare("UPDATE contratos SET estado = 'SUSPENDIDO' WHERE id = ? AND estado = 'ACTIVO'");
            if ($stmtUpd) {
                $stmtUpd->bind_param("i", $idContrato);
                $stmtUpd->execute();
                $stmtUpd->close();
            }

            // Actualizar wisp_hub_links
            $stmtLink = $conn->prepare("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'cron.suspend', updated_at = NOW() WHERE contract_id = ? AND wisp_account_id = ?");
            if ($stmtLink) {
                $stmtLink->bind_param("is", $idContrato, $wispAccountId);
                $stmtLink->execute();
                $stmtLink->close();
            }

            // Log en wisp_hub_logs
            $logPayload = json_encode([
                'action' => 'cron_suspend',
                'contract_id' => $idContrato,
                'service_id' => $wispAccountId,
                'reason' => $razon,
                'dias_gracia' => $diasGracia,
            ]);
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
            echo "ERROR (HTTP {$response['status']}): " . json_encode($response['data'] ?? $response['error'] ?? '') . "\n";
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
echo "Finalizado: " . date('Y-m-d H:i:s') . "\n";

$conn->close();
