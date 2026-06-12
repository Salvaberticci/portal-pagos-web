<?php
/**
 * portal/wisp_hub_webhook.php
 *
 * Recibe notificaciones (webhooks) enviadas por WispHub cuando cambia
 * el estado de un servicio (activación, suspensión, etc.).
 *
 * Seguridad:
 *   - Verifica la firma HMAC‑SHA256 enviada en X‑WispHub‑Signature.
 *   - Solo acepta POST.
 *   - Responde 200 OK siempre para no revelar información interna.
 */

// ── 0. Asegurar que existan las tablas ────────────────────────────────────────
require_once __DIR__ . '/../paginas/conexion.php';

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
    `contract_id` INT NOT NULL,
    `wisp_account_id` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'PENDING',
    `last_event` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contract_id` (`contract_id`),
    INDEX `idx_wisp_account_id` (`wisp_account_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── 1. Solo aceptar POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ── 2. Leer cuerpo crudo ANTES de cualquier parseo ───────────────────────────
$rawBody = file_get_contents('php://input');

// ── 3. Cargar config y verificar firma HMAC ──────────────────────────────────
require_once __DIR__ . '/../config/wisp_hub.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$apiSecret  = $wispConfig['api_secret'] ?? '';

$receivedSig  = $_SERVER['HTTP_X_WISPHUB_SIGNATURE'] ?? '';
$expectedSig  = hash_hmac('sha256', $rawBody, $apiSecret);

if (!hash_equals($expectedSig, $receivedSig)) {
    // Log del intento fallido
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents(
        $logDir . '/wisphub_webhook.log',
        '[' . date('Y-m-d H:i:s') . '] HMAC MISMATCH - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n",
        FILE_APPEND | LOCK_EX
    );
    http_response_code(200); // Respondemos 200 para no revelar info
    exit;
}

// ── 4. Parsear el JSON del evento ─────────────────────────────────────────────
$event = json_decode($rawBody, true);
if (!is_array($event)) {
    http_response_code(200);
    exit;
}

$eventType   = $event['event']       ?? '';
$accountId   = $event['account_id']  ?? '';
$contractRef = $event['contract_id'] ?? null;

// ── 5. Conectar a la BD y procesar el evento ─────────────────────────────────
require_once __DIR__ . '/../paginas/conexion.php';

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
$logFile = $logDir . '/wisphub_webhook.log';

$newStatus = null;
switch ($eventType) {
    case 'service.activated':
        $newStatus = 'ACTIVE';
        break;
    case 'service.suspended':
    case 'service.deactivated':
        $newStatus = 'SUSPENDED';
        break;
    default:
        // Evento desconocido — solo registrar
        file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] UNKNOWN_EVENT: ' . htmlspecialchars($eventType) . ' account=' . htmlspecialchars($accountId) . "\n",
            FILE_APPEND | LOCK_EX
        );
        http_response_code(200);
        exit;
}

// Actualizar wisp_hub_links si tenemos un account_id
if ($accountId && $newStatus) {
    $stmt = $conn->prepare(
        "UPDATE wisp_hub_links SET status = ?, last_event = ?, updated_at = NOW() WHERE wisp_account_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('sss', $newStatus, $eventType, $accountId);
        $stmt->execute();
        $stmt->close();
    }
}

// ── 6. Insertar en wisp_hub_logs ─────────────────────────────────────────────
$stmt_log = $conn->prepare(
    "INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at)
     VALUES (NULL, ?, 'webhook_inbound', NOW())"
);
if ($stmt_log) {
    $stmt_log->bind_param('s', $rawBody);
    $stmt_log->execute();
    $stmt_log->close();
}

// ── 7. Log en archivo ─────────────────────────────────────────────────────────
file_put_contents(
    $logFile,
    '[' . date('Y-m-d H:i:s') . '] EVENT: ' . $eventType . ' | account=' . $accountId . ' | status_set=' . ($newStatus ?? 'n/a') . "\n",
    FILE_APPEND | LOCK_EX
);

$conn->close();

// ── 8. Responder 200 OK ───────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['received' => true]);
