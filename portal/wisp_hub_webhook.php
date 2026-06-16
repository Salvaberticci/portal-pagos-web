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
 *   - No usa base de datos local. Eventos registrados en archivo de log.
 */

// ── 1. Solo aceptar POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ── 2. Leer cuerpo crudo ANTES de cualquier parseo ───────────────────────────
$rawBody = file_get_contents('php://input');

// ── 3. Cargar config y verificar firma HMAC ──────────────────────────────────
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$apiSecret  = $wispConfig['api_secret'] ?? '';

$logDir  = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
$logFile = $logDir . '/wisphub_webhook.log';

$receivedSig  = $_SERVER['HTTP_X_WISPHUB_SIGNATURE'] ?? '';
$expectedSig  = hash_hmac('sha256', $rawBody, $apiSecret);

if ($apiSecret !== '' && !hash_equals($expectedSig, $receivedSig)) {
    file_put_contents(
        $logFile,
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

$eventType   = $event['event']       ?? 'unknown';
$accountId   = $event['account_id']  ?? '';
$contractRef = $event['contract_id'] ?? null;

// ── 5. Procesar el evento (solo log, sin BD) ──────────────────────────────────
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
        echo json_encode(['received' => true]);
        exit;
}

// ── 6. Registrar evento en log de archivo ─────────────────────────────────────
file_put_contents(
    $logFile,
    '[' . date('Y-m-d H:i:s') . '] EVENT: ' . $eventType
        . ' | account=' . htmlspecialchars($accountId)
        . ' | contract=' . htmlspecialchars((string)$contractRef)
        . ' | status=' . ($newStatus ?? 'n/a') . "\n",
    FILE_APPEND | LOCK_EX
);

// ── 7. Responder 200 OK ───────────────────────────────────────────────────────
http_response_code(200);
echo json_encode(['received' => true]);
