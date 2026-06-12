<?php
/**
 * cron/cortar_servicios_vencidos.php
 *
 * Script para ejecutar vía CRON.
 * Busca contratos con cuentas por cobrar vencidas (más de N días de gracia)
 * y suspende el servicio en WispHub.
 *
 * Uso (CLI):
 *   php cron/cortar_servicios_vencidos.php [dias_gracia] [--batch=50] [--offset=0] [--max=0]
 *
 * Parámetros:
 *   dias_gracia  Días después del vencimiento para cortar (default: 5)
 *   --batch=N    Número máximo de contratos por ejecución (default: 50)
 *   --offset=N   Saltar los primeros N contratos (para retomar desde un punto)
 *   --max=0      Máximo total de suspensiones (0 = sin límite)
 *
 * Configurar en crontab (todos los días a las 6:00 AM):
 *   0 6 * * * /usr/bin/php /ruta/al/proyecto/cron/cortar_servicios_vencidos.php
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

set_time_limit(0);
ini_set('memory_limit', '512M');

$diasGracia = isset($argv[1]) && is_numeric($argv[1]) ? intval($argv[1]) : 5;
if ($diasGracia < 0) $diasGracia = 0;

$batchSize = 50;
$offset = 0;
$maxSuspensions = 0;
for ($i = 1; $i < $argc; $i++) {
    if (preg_match('/^--batch=(\d+)$/', $argv[$i], $m)) {
        $batchSize = max(1, min(500, intval($m[1])));
    } elseif (preg_match('/^--offset=(\d+)$/', $argv[$i], $m)) {
        $offset = intval($m[1]);
    } elseif (preg_match('/^--max=(\d+)$/', $argv[$i], $m)) {
        $maxSuspensions = intval($m[1]);
    }
}

echo "=== Cortar Servicios Vencidos ===\n";
echo "Días de gracia: $diasGracia\n";
echo "Batch size: $batchSize\n";
echo "Offset: $offset\n";
echo "Max suspensiones: " . ($maxSuspensions > 0 ? $maxSuspensions : 'sin límite') . "\n";
echo "Iniciando: " . date('Y-m-d H:i:s') . "\n\n";

require_once __DIR__ . '/../paginas/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

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

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$fechaLimite = date('Y-m-d', strtotime("-{$diasGracia} days"));
$procesados = 0;
$errores = 0;
$saltados = 0;

$sql = "
    SELECT DISTINCT c.id AS id_contrato, c.cedula, c.nombre_completo,
           wl.wisp_account_id
    FROM contratos c
    INNER JOIN wisp_hub_links wl ON wl.contract_id = c.id AND wl.wisp_account_id != ''
    INNER JOIN cuentas_por_cobrar cxc ON cxc.id_contrato = c.id
    WHERE c.estado = 'ACTIVO'
      AND cxc.estado = 'PENDIENTE'
      AND cxc.fecha_vencimiento <= '$fechaLimite'
      AND c.id > $offset
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

    if ($maxSuspensions > 0 && $procesados >= $maxSuspensions) {
        echo "[LÍMITE] Se alcanzó el máximo de $maxSuspensions suspensiones. Deteniendo.\n";
        break;
    }

    echo "[#{$idContrato}] {$nombre} ({$cedula}) - Account: {$wispAccountId}... ";

    $checkLog = $conn->query("SELECT id FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%cron_suspend%' AND request_payload LIKE '%$wispAccountId%' AND created_at >= NOW() - INTERVAL 1 DAY");
    if ($checkLog && $checkLog->num_rows > 0) {
        echo "YA PROCESADO HOY (saltando)\n";
        $saltados++;
        continue;
    }

    try {
        $razon = "Corte por vencimiento de pago - " . $diasGracia . " días de gracia excedidos";
        $response = $wispClient->suspendService($wispAccountId, $razon);

        $exitoso = ($response['status'] === 200 || $response['status'] === 201);

        if ($exitoso) {
            $stmtUpd = $conn->prepare("UPDATE contratos SET estado = 'SUSPENDIDO' WHERE id = ? AND estado = 'ACTIVO'");
            if ($stmtUpd) {
                $stmtUpd->bind_param("i", $idContrato);
                $stmtUpd->execute();
                $stmtUpd->close();
            }

            $stmtLink = $conn->prepare("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'cron.suspend', updated_at = NOW() WHERE contract_id = ? AND wisp_account_id = ?");
            if ($stmtLink) {
                $stmtLink->bind_param("is", $idContrato, $wispAccountId);
                $stmtLink->execute();
                $stmtLink->close();
            }

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
echo "Saltados (ya procesados hoy): $saltados\n";

$ultimoId = 0;
if ($procesados > 0 || $errores > 0) {
    // Re-consultar para obtener el último id_contrato procesado
    $lastRes = $conn->query("SELECT MAX(c.id) AS ultimo FROM contratos c
        INNER JOIN wisp_hub_links wl ON wl.contract_id = c.id AND wl.wisp_account_id != ''
        INNER JOIN cuentas_por_cobrar cxc ON cxc.id_contrato = c.id
        WHERE c.estado = 'ACTIVO'
          AND cxc.estado = 'PENDIENTE'
          AND cxc.fecha_vencimiento <= '$fechaLimite'
          AND c.id > $offset
        GROUP BY c.id
        HAVING COUNT(cxc.id_cobro) > 0");
    if ($lastRes && $lastRow = $lastRes->fetch_assoc()) {
        $ultimoId = $lastRow['ultimo'];
    }
    echo "Próximo offset sugerido: " . ($ultimoId) . "\n";
    echo "Para continuar: php " . $argv[0] . " $diasGracia --batch=$batchSize --offset=$ultimoId";
    if ($maxSuspensions > 0) {
        echo " --max=$maxSuspensions";
    }
    echo "\n";
}

echo "Finalizado: " . date('Y-m-d H:i:s') . "\n";

$conn->close();
