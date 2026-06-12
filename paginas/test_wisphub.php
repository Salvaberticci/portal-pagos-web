<?php
/**
 * paginas/test_wisphub.php
 *
 * Panel de pruebas y simulación de integración con WispHub.
 * Permite simular cortes, reactivaciones, reportes de pago y webhooks.
 */

// ─── AJAX Handler (al inicio, antes de cualquier salida HTML) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');

    if (!@include_once __DIR__ . '/../vendor/autoload.php') {
        echo json_encode(['success' => false, 'message' => 'vendor/autoload.php no encontrado. Ejecuta composer install.']);
        exit;
    }
    require_once __DIR__ . '/../src/Services/WispHubClient.php';

    $wispConfig = @include __DIR__ . '/../config/wisp_hub.php';
    if (!is_array($wispConfig) || empty($wispConfig['api_key'])) {
        echo json_encode(['success' => false, 'message' => 'API Key no configurada. Crea config/wisphub_credentials.php.']);
        exit;
    }

    require_once 'conexion.php';

    $wispClient = new \Services\WispHubClient($wispConfig);
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => 'Acción no válida'];

    try {
        if ($action === 'ping_connection') {
            try {
                $res = $wispClient->listClients(['limit' => 1]);
                    $status = $res['status'] ?? 0;
                    if ($status === 200) {
                        $total = $res['data']['count'] ?? 0;
                        $response['success'] = true;
                        $response['message'] = "✅ Conexión exitosa. Total clientes en WispHub: {$total}.";
                    } else {
                        $response['message'] = "WispHub respondió HTTP {$status}: " . json_encode($res['data'] ?? []);
                    }
                } catch (\Exception $e) {
                    $response['message'] = "Fallo de conexión a WispHub: " . $e->getMessage();
                }
            }
        }
        
        elseif ($action === 'suspend_service') {
            $accountId = trim($_POST['account_id'] ?? '');
            $reason = trim($_POST['reason'] ?? 'Corte administrativo por impago');
            
            if (empty($accountId)) {
                $response['message'] = 'El ID de cuenta de WispHub es obligatorio.';
            } else {
                $res = $wispClient->suspendService($accountId, $reason);
                $status = $res['status'] ?? 0;
                
                // Guardar log
                $req_payload = json_encode(['account_id' => $accountId, 'reason' => $reason]);
                $resp_payload = json_encode($res);
                $stmt_log = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, ?, ?, NOW())");
                if ($stmt_log) {
                    $stmt_log->bind_param("ss", $req_payload, $resp_payload);
                    $stmt_log->execute();
                    $stmt_log->close();
                }
                
                $response['data'] = $res;
                if ($status === 200 || $status === 201 || ($status === 400 && strpos(json_encode($res), 'suspendido') !== false)) {
                    // Actualizar base de datos
                    $stmt_upd = $conn->prepare("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'manual.suspend', updated_at = NOW() WHERE wisp_account_id = ?");
                    if ($stmt_upd) {
                        $stmt_upd->bind_param("s", $accountId);
                        $stmt_upd->execute();
                        if ($stmt_upd->affected_rows === 0) {
                            $stmt_ins = $conn->prepare("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, last_event, created_at) VALUES (NULL, NULL, ?, 'SUSPENDED', 'manual.suspend', NOW())");
                            $stmt_ins->bind_param("s", $accountId);
                            $stmt_ins->execute();
                            $stmt_ins->close();
                        }
                        $stmt_upd->close();
                    }
                    $response['success'] = true;
                    $response['message'] = 'Servicio SUSPENDIDO en WispHub Sandbox. Estado del link local: SUSPENDED.';
                } else {
                    $response['message'] = "Error al suspender servicio en WispHub Sandbox (Código HTTP: $status).";
                }
            }
        }
        
        elseif ($action === 'activate_service') {
            $accountId = trim($_POST['account_id'] ?? '');
            
            if (empty($accountId)) {
                $response['message'] = 'El ID de cuenta de WispHub es obligatorio.';
            } else {
                $res = $wispClient->activateService($accountId);
                $status = $res['status'] ?? 0;
                
                // Guardar log
                $req_payload = json_encode(['account_id' => $accountId]);
                $resp_payload = json_encode($res);
                $stmt_log = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, ?, ?, NOW())");
                if ($stmt_log) {
                    $stmt_log->bind_param("ss", $req_payload, $resp_payload);
                    $stmt_log->execute();
                    $stmt_log->close();
                }
                
                $response['data'] = $res;
                if ($status === 200 || $status === 201 || ($status === 400 && strpos(json_encode($res), 'activo') !== false)) {
                    // Actualizar base de datos
                    $stmt_upd = $conn->prepare("UPDATE wisp_hub_links SET status = 'ACTIVE', last_event = 'manual.activate', updated_at = NOW() WHERE wisp_account_id = ?");
                    if ($stmt_upd) {
                        $stmt_upd->bind_param("s", $accountId);
                        $stmt_upd->execute();
                        if ($stmt_upd->affected_rows === 0) {
                            $stmt_ins = $conn->prepare("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, last_event, created_at) VALUES (NULL, NULL, ?, 'ACTIVE', 'manual.activate', NOW())");
                            $stmt_ins->bind_param("s", $accountId);
                            $stmt_ins->execute();
                            $stmt_ins->close();
                        }
                        $stmt_upd->close();
                    }
                    $response['success'] = true;
                    $response['message'] = 'Servicio ACTIVADO en WispHub Sandbox. Estado del link local: ACTIVE.';
                } else {
                    $response['message'] = "Error al activar servicio en WispHub Sandbox (Código HTTP: $status).";
                }
            }
        }
        
        elseif ($action === 'notify_payment') {
            $accountId = trim($_POST['account_id'] ?? '');
            $contractId = intval($_POST['contract_id'] ?? 0);
            $reference = trim($_POST['reference'] ?? '');
            $amountUsd = floatval($_POST['amount_usd'] ?? 15.00);
            
            if (empty($accountId) || empty($reference)) {
                $response['message'] = 'El ID de cuenta y la Referencia son obligatorios.';
            } else {
                $cedula = 'V99999999';
                if ($contractId > 0) {
                    $res_c = $conn->query("SELECT cedula FROM contratos WHERE id = $contractId");
                    if ($res_c && $row_c = $res_c->fetch_assoc()) {
                        $cedula = $row_c['cedula'];
                    }
                }
                
                // Registrar pago simulado
                $tasa = 40.00;
                $monto_bs = round($amountUsd * $tasa, 2);
                $fecha = date('Y-m-d');
                $concepto = "Pago simulado por Asistente de Pruebas";
                
                $sql_ins_p = "INSERT INTO pagos_reportados 
                    (cedula_titular, nombre_titular, telefono_titular, fecha_pago, metodo_pago,
                     id_banco_destino, referencia, monto_bs, monto_usd, tasa_dolar,
                     meses_pagados, concepto, capture_path, id_contrato_asociado, aprobado_por, fecha_aprobacion)
                    VALUES (?, 'TEST SIMULADO', '04120000000', ?, 'PAGO_MOVIL', 1, ?, ?, ?, ?, '1 mes', ?, '', ?, 'admin_test', NOW())";
                
                $stmt_p = $conn->prepare($sql_ins_p);
                if ($stmt_p) {
                    $stmt_p->bind_param("ssssddssi", $cedula, $fecha, $reference, $monto_bs, $amountUsd, $tasa, $concepto, $contractId);
                    $stmt_p->execute();
                    $paymentId = $conn->insert_id;
                    $stmt_p->close();
                    
                    // Notificar pago
                    $paymentPayload = [
                        'payment_id'   => $paymentId,
                        'contract_id'  => $contractId ?: null,
                        'reference'    => $reference,
                        'amount_usd'   => $amountUsd,
                        'amount_bs'    => $monto_bs,
                        'currency'     => 'USD',
                        'date'         => $fecha,
                        'customer_cedula' => $cedula,
                    ];
                    
                    $res = $wispClient->notifyPayment($paymentPayload);
                    $status = $res['status'] ?? 0;
                    
                    // Guardar log
                    $stmt_log = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (?, ?, ?, NOW())");
                    if ($stmt_log) {
                        $req_json = json_encode($paymentPayload);
                        $resp_json = json_encode($res);
                        $stmt_log->bind_param("iss", $paymentId, $req_json, $resp_json);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                    
                    $response['data'] = $res;
                    if ($status === 200 || $status === 201) {
                        // Actualizar/Crear link local
                        $stmt_lnk = $conn->prepare("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, last_event, created_at) VALUES (?, ?, ?, 'ACTIVE', 'payment.notified', NOW()) ON DUPLICATE KEY UPDATE status = 'ACTIVE', last_event = 'payment.notified', payment_id = VALUES(payment_id)");
                        if ($stmt_lnk) {
                            $stmt_lnk->bind_param("iis", $paymentId, $contractId, $accountId);
                            $stmt_lnk->execute();
                            $stmt_lnk->close();
                        }
                        $response['success'] = true;
                        $response['message'] = "Notificación de pago enviada. WispHub Sandbox retornó código $status. Servicio local actualizado a ACTIVE.";
                    } else {
                        $response['message'] = "Localmente se guardó el pago (#$paymentId), pero la API de WispHub Sandbox retornó HTTP $status.";
                    }
                } else {
                    $response['message'] = 'Error al registrar pago local: ' . $conn->error;
                }
            }
        }
        
        elseif ($action === 'simulate_webhook') {
            $accountId = trim($_POST['account_id'] ?? '');
            $eventType = trim($_POST['event_type'] ?? 'service.activated');
            
            if (empty($accountId)) {
                $response['message'] = 'El ID de cuenta de WispHub es requerido.';
            } else {
                $payload = [
                    'event' => $eventType,
                    'account_id' => $accountId,
                    'contract_id' => rand(1000, 9999),
                    'timestamp' => time()
                ];
                $payload_json = json_encode($payload);
                
                $apiSecret = $wispConfig['api_secret'] ?? '';
                $signature = hash_hmac('sha256', $payload_json, $apiSecret);
                
                $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $webhookUrl = "$proto://$host/portal/wisp_hub_webhook.php";
                
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload_json,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'X-WispHub-Signature: ' . $signature
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 8
                ]);
                
                $webhook_res = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_err = curl_error($ch);
                curl_close($ch);
                
                if ($curl_err) {
                    $response['message'] = "Error de red cURL local: " . $curl_err;
                } else {
                    $response['success'] = ($http_code === 200);
                    $response['message'] = "Webhook recibido localmente con código $http_code. Respuesta: " . $webhook_res;
                    $response['data'] = [
                        'payload' => $payload,
                        'signature' => $signature,
                        'response' => json_decode($webhook_res, true) ?: $webhook_res
                    ];
                }
            }
        }
        
        elseif ($action === 'clear_test_logs') {
            $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IS NULL OR payment_id IN (SELECT id_reporte FROM pagos_reportados WHERE cedula_titular = 'V99999999')");
            $conn->query("DELETE FROM wisp_hub_links WHERE payment_id IS NULL OR payment_id IN (SELECT id_reporte FROM pagos_reportados WHERE cedula_titular = 'V99999999')");
            $conn->query("DELETE FROM pagos_reportados WHERE cedula_titular = 'V99999999'");
            
            $response['success'] = true;
            $response['message'] = 'Logs y enlaces de pruebas limpiados con éxito.';
        }
        
    } catch (\Exception $e) {
        $response['message'] = 'Excepción detectada: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// ─── Inicialización normal de la página (GET requests) ────────────────────────
$page_title = "WispHub — Simulador de Integración";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
require_once 'conexion.php';

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

// Cargar cliente WispHub (tolerante a errores de configuración)
$wispConfigLoaded = false;
$wispConfig = ['api_key' => '', 'base_url' => 'https://api.wisphub.net/api'];
$wispClient = null;
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/Services/WispHubClient.php';
    $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
    if (!empty($wispConfig['api_key'])) {
        $wispClient = new \Services\WispHubClient($wispConfig);
        $wispConfigLoaded = true;
    }
} catch (\Throwable $e) {
}

// Cargar últimos registros para mostrar en tablas
$activeLinks = [];
$res = $conn->query("SELECT wl.*, c.nombre_completo, c.cedula FROM wisp_hub_links wl LEFT JOIN contratos c ON wl.contract_id = c.id ORDER BY wl.id DESC LIMIT 15");
if ($res) {
    while ($row = $res->fetch_assoc()) $activeLinks[] = $row;
}

$recentLogs = [];
$resLogs = $conn->query("SELECT * FROM wisp_hub_logs ORDER BY id DESC LIMIT 15");
if ($resLogs) {
    while ($row = $resLogs->fetch_assoc()) $recentLogs[] = $row;
}

// Cargar contratos de la base de datos para el selector
$contracts = [];
$res_c = $conn->query("SELECT id, cedula, nombre_completo, estado FROM contratos ORDER BY id DESC LIMIT 30");
if ($res_c) {
    while ($row = $res_c->fetch_assoc()) {
        $contracts[] = $row;
    }
}
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 animate-fade">
            <div>
                <h1 class="fw-bold mb-1" style="font-size:1.6rem;">
                    <span class="badge rounded-pill me-2" style="background:linear-gradient(135deg,#a855f7,#6366f1);font-size:.7rem;vertical-align:middle;">PRUEBAS</span>
                    WispHub — Simulador e Integración
                </h1>
                <p class="text-muted small mb-0">Herramienta interactiva para verificar suspensión, reactivación, flujo de cobro y webhooks.</p>
            </div>
            <a href="admin_wisphub.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-gears me-1"></i> Configuración
            </a>
        </div>

        <div class="row g-4">
            
            <!-- COLUMNA IZQUIERDA: CONTROLES DE SIMULACIÓN -->
            <div class="col-lg-6">
                
                <!-- ESTADO DE LA API -->
                <div class="glass-panel p-4 mb-4 animate-fade">
                    <h5 class="fw-bold mb-3 text-gradient d-flex align-items-center justify-content-between">
                        <span><i class="fa-solid fa-server me-2" style="color:#6366f1;"></i> Conectividad API</span>
                        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill py-0 px-2" onclick="pingConnection(this)" style="font-size:0.75rem;">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Probar Conexión
                        </button>
                    </h5>
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);">
                        <div class="row small g-2">
                            <div class="col-sm-4 text-muted">API Endpoint:</div>
                            <div class="col-sm-8 font-monospace text-truncate"><?= htmlspecialchars($wispConfig['base_url']) ?></div>
                            <div class="col-sm-4 text-muted">API Key:</div>
                            <div class="col-sm-8 font-monospace">
                                <?= empty($wispConfig['api_key']) ? '<span class="text-danger">No configurada</span>' : '••••••••' . substr($wispConfig['api_key'], -6) ?>
                            </div>
                            <div class="col-sm-4 text-muted">Modo de Operación:</div>
                            <div class="col-sm-8">
                                <?php if (strpos($wispConfig['base_url'], 'sandbox') !== false): ?>
                                    <span class="badge bg-warning bg-opacity-20 text-warning rounded-pill">SANDBOX</span>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-20 text-success rounded-pill">PRODUCCIÓN</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div id="pingResult" class="mt-3 d-none">
                        <div class="alert alert-sm small py-2 rounded-3 mb-0" id="pingAlert"></div>
                    </div>
                </div>

                <!-- SIMULADOR INTERACTIVO -->
                <div class="glass-panel p-4 animate-fade" style="animation-delay:.05s">
                    <h5 class="fw-bold mb-3">
                        <i class="fa-solid fa-circle-play me-2 text-info"></i>
                        Simulador de Acciones
                    </h5>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">1. Seleccionar Cliente / Contrato Local</label>
                        <select class="form-select form-select-sm rounded-3" id="sim_contract_id" onchange="updateAccountField()">
                            <option value="0" data-cedula="V99999999">-- Generar cliente de prueba (Cédula: V99999999) --</option>
                            <?php foreach ($contracts as $c): ?>
                                <option value="<?= $c['id'] ?>" data-cedula="<?= htmlspecialchars($c['cedula']) ?>">
                                    #<?= $c['id'] ?> - <?= htmlspecialchars($c['nombre_completo']) ?> (<?= htmlspecialchars($c['cedula']) ?>) [<?= $c['estado'] ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">2. ID de Cuenta en WispHub (Sandbox)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text rounded-start-3">Wisp Account ID</span>
                            <input type="text" id="sim_account_id" class="form-control font-monospace" placeholder="Ej: 12345 o sandbox_user_123" value="sandbox_user_9999">
                            <button class="btn btn-outline-secondary" type="button" onclick="randomizeAccountId()">
                                <i class="fa-solid fa-dice"></i>
                            </button>
                        </div>
                        <div class="form-text small">Para pruebas en sandbox-api, puedes usar IDs arbitrarios de prueba.</div>
                    </div>

                    <hr class="opacity-10 my-4">

                    <!-- SECCIÓN A: ACCIONES DE CORTE / RESTABLECIMIENTO -->
                    <div class="mb-4">
                        <h6 class="fw-bold small mb-2"><i class="fa-solid fa-bolt me-1 text-warning"></i> Simular Suspensión y Activación Directa</h6>
                        <p class="text-muted small mb-3">Llama directamente a los endpoints `/clientes/suspender/` y `/clientes/activar/` en WispHub Sandbox.</p>
                        
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <button type="button" class="btn btn-sm btn-danger w-100 rounded-3" onclick="triggerAction('suspend_service', this)">
                                    <i class="fa-solid fa-circle-pause me-1"></i> Cortar / Suspender
                                </button>
                            </div>
                            <div class="col-sm-6">
                                <button type="button" class="btn btn-sm btn-success w-100 rounded-3" onclick="triggerAction('activate_service', this)">
                                    <i class="fa-solid fa-circle-check me-1"></i> Activar / Restablecer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN B: SIMULAR REPORTE DE PAGO -->
                    <div class="mb-4">
                        <h6 class="fw-bold small mb-2"><i class="fa-solid fa-cash-register me-1 text-success"></i> Simular Reporte y Aprobación de Pago</h6>
                        <p class="text-muted small mb-2">Simula el registro automático de un pago aprobado en el portal web que llama a `/payments/notify/` en WispHub.</p>
                        
                        <div class="p-3 rounded-3 mb-3" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Referencia Bancaria</label>
                                    <input type="text" id="sim_ref" class="form-control form-control-sm font-monospace" placeholder="Automático" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Monto (USD)</label>
                                    <input type="number" id="sim_amount" class="form-control form-control-sm" value="15.00" step="0.01">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary w-100 rounded-3" onclick="triggerAction('notify_payment', this)">
                            <i class="fa-solid fa-paper-plane me-1"></i> Reportar Pago y Auto-Activar
                        </button>
                    </div>

                    <!-- SECCIÓN C: SIMULAR WEBHOOK DE ENTRADA -->
                    <div class="mb-2">
                        <h6 class="fw-bold small mb-2"><i class="fa-solid fa-webhook me-1 text-info"></i> Simular Webhook Entrante de WispHub</h6>
                        <p class="text-muted small mb-3">Genera una firma segura HMAC y hace una petición local al webhook receptor para validar la sincronización del estado.</p>
                        
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <button type="button" class="btn btn-sm btn-outline-warning w-100 rounded-3" onclick="triggerWebhook('service.suspended', this)">
                                    <i class="fa-solid fa-bell me-1"></i> Webhook: Suspendido
                                </button>
                            </div>
                            <div class="col-sm-6">
                                <button type="button" class="btn btn-sm btn-outline-success w-100 rounded-3" onclick="triggerWebhook('service.activated', this)">
                                    <i class="fa-solid fa-bell me-1"></i> Webhook: Activado
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- COLUMNA DERECHA: REGISTROS DE BD Y LOGS -->
            <div class="col-lg-6">

                <!-- ENLACES ACTIVOS EN BD (wisp_hub_links) -->
                <div class="glass-panel p-4 mb-4 animate-fade" style="animation-delay:.1s">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">
                            <i class="fa-solid fa-link me-2 text-primary"></i>
                            Enlaces Locales (`wisp_hub_links`)
                        </h5>
                        <button class="btn btn-xs btn-outline-danger rounded-pill" onclick="clearTestLogs(this)">
                            <i class="fa-solid fa-trash-can me-1"></i> Limpiar Pruebas
                        </button>
                    </div>

                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.85rem;">
                            <thead>
                                <tr class="text-muted">
                                    <th>Ref. Pago</th>
                                    <th>Cédula / Cliente</th>
                                    <th>Account ID</th>
                                    <th>Estado</th>
                                    <th>Últ. Evento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activeLinks)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No hay enlaces guardados. Ejecuta una simulación.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activeLinks as $link): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary bg-opacity-20 text-secondary">#<?= $link['payment_id'] ?></span></td>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width:120px;"><?= htmlspecialchars($link['nombre_completo'] ?? 'Prueba/Manual') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($link['cedula'] ?? 'V99999999') ?></small>
                                            </td>
                                            <td class="font-monospace text-info"><?= htmlspecialchars($link['wisp_account_id']) ?></td>
                                            <td>
                                                <?php if ($link['status'] === 'ACTIVE'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>
                                                <?php elseif ($link['status'] === 'SUSPENDED'): ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill"><i class="fa-solid fa-circle-pause me-1"></i>Suspendido</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill"><?= htmlspecialchars($link['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($link['last_event'] ?? 'Ninguno') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- LOG DE LLAMADAS (wisp_hub_logs) -->
                <div class="glass-panel p-4 animate-fade" style="animation-delay:.15s">
                    <h5 class="fw-bold mb-3">
                        <i class="fa-solid fa-terminal me-2 style="color:#6366f1;"></i>
                        Bitácora de Eventos (`wisp_hub_logs`)
                    </h5>
                    
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.8rem;">
                            <thead>
                                <tr class="text-muted">
                                    <th>#</th>
                                    <th>Hora</th>
                                    <th>Detalles (Click para expandir)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLogs)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No hay logs registrados.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <tr class="log-header-row" style="cursor:pointer;" onclick="toggleLogDetail(<?= $log['id'] ?>)">
                                            <td><span class="text-muted"><?= $log['id'] ?></span></td>
                                            <td class="text-muted text-nowrap"><?= substr($log['created_at'], 11, 8) ?></td>
                                            <td>
                                                <div class="text-truncate text-main" style="max-width:250px;">
                                                    <?= htmlspecialchars(substr($log['request_payload'], 0, 80)) ?>...
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="d-none" id="log-detail-<?= $log['id'] ?>">
                                            <td colspan="3" class="bg-dark bg-opacity-40 p-3" style="border-top:1px solid rgba(255,255,255,0.05)">
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <div class="small fw-semibold text-muted mb-1">REQUEST (Enviado)</div>
                                                        <pre class="small p-2 bg-black bg-opacity-50 text-light rounded font-monospace" style="max-height:180px; overflow:auto; white-space: pre-wrap; font-size:0.75rem;"><?= htmlspecialchars(json_encode(json_decode($log['request_payload']), JSON_PRETTY_PRINT)) ?></pre>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="small fw-semibold text-muted mb-1">RESPONSE (Recibido)</div>
                                                        <pre class="small p-2 bg-black bg-opacity-50 text-light rounded font-monospace" style="max-height:180px; overflow:auto; white-space: pre-wrap; font-size:0.75rem;"><?= htmlspecialchars(json_encode(json_decode($log['response_payload']), JSON_PRETTY_PRINT)) ?></pre>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>

    </div>
</main>

<!-- MODAL PARA MOSTRAR DETALLES COMPLETOS DE RESPUESTAS -->
<div class="modal fade" id="responseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-panel" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.1);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-gradient" id="modalTitle">Resultado de la Operación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert mb-3" id="modalAlert" role="alert"></div>
                <div class="mb-1 small fw-semibold text-muted">PAYLOAD DE RESPUESTA:</div>
                <pre class="p-3 bg-black bg-opacity-60 text-info font-monospace rounded-3 mb-0" id="modalResponseText" style="max-height:350px; overflow:auto; font-size:0.8rem;"></pre>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializaciones
document.addEventListener('DOMContentLoaded', () => {
    randomizeAccountId();
    generateRef();
});

function randomizeAccountId() {
    const rand = Math.floor(10000 + Math.random() * 90000);
    document.getElementById('sim_account_id').value = 'sandbox_user_' + rand;
}

function generateRef() {
    const rand = Math.floor(10000000 + Math.random() * 90000000);
    document.getElementById('sim_ref').value = 'REF' + rand;
}

function updateAccountField() {
    const sel = document.getElementById('sim_contract_id');
    const opt = sel.options[sel.selectedIndex];
    const cedula = opt.getAttribute('data-cedula');
    if (cedula !== 'V99999999') {
        // Sugerir ID de cuenta basado en cédula
        document.getElementById('sim_account_id').value = 'wisp_' + cedula.replace(/[^0-9]/g, '');
    } else {
        randomizeAccountId();
    }
}

function toggleLogDetail(id) {
    const row = document.getElementById('log-detail-' + id);
    if(row) {
        row.classList.toggle('d-none');
    }
}

// Conexión Ping
function pingConnection(btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Probando...';

    const formData = new FormData();
    formData.append('ajax_action', 'ping_connection');

    fetch('test_wisphub.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const pingResultDiv = document.getElementById('pingResult');
        const pingAlert = document.getElementById('pingAlert');
        pingResultDiv.classList.remove('d-none');
        
        if (data.success) {
            pingAlert.className = 'alert alert-sm alert-success py-2 rounded-3 mb-0';
            pingAlert.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>' + data.message;
        } else {
            pingAlert.className = 'alert alert-sm alert-danger py-2 rounded-3 mb-0';
            pingAlert.innerHTML = '<i class="fa-solid fa-circle-exmark me-2"></i>' + data.message;
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error al probar conexión.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Acciones Directas (Suspender, Activar, Notificar Pago)
function triggerAction(action, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Enviando...';

    const accountId = document.getElementById('sim_account_id').value;
    const contractId = document.getElementById('sim_contract_id').value;
    const ref = document.getElementById('sim_ref').value;
    const amount = document.getElementById('sim_amount').value;

    const formData = new FormData();
    formData.append('ajax_action', action);
    formData.append('account_id', accountId);
    formData.append('contract_id', contractId);
    formData.append('reference', ref);
    formData.append('amount_usd', amount);

    fetch('test_wisphub.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        // Mostrar Modal con Resultados
        const modal = new bootstrap.Modal(document.getElementById('responseModal'));
        const modalAlert = document.getElementById('modalAlert');
        const modalResponseText = document.getElementById('modalResponseText');
        const modalTitle = document.getElementById('modalTitle');

        modalTitle.textContent = action === 'suspend_service' ? 'Suspensión de Servicio' : (action === 'activate_service' ? 'Activación de Servicio' : 'Notificación de Pago');
        
        if (data.success) {
            modalAlert.className = 'alert alert-success py-2 rounded-3';
            modalAlert.innerHTML = '<i class="fa-solid fa-check-double me-2"></i>' + data.message;
        } else {
            modalAlert.className = 'alert alert-danger py-2 rounded-3';
            modalAlert.innerHTML = '<i class="fa-solid fa-circle-exmark me-2"></i>' + data.message;
        }

        modalResponseText.textContent = JSON.stringify(data.data, null, 2);
        modal.show();

        // Regenerar referencia si fue pago
        if (action === 'notify_payment') {
            generateRef();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error procesando acción.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        // Auto recargar tablas después de cerrar modal (o 2s)
        setTimeout(() => {
            location.reload();
        }, 3000);
    });
}

// Simular Webhook
function triggerWebhook(eventType, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Procesando...';

    const accountId = document.getElementById('sim_account_id').value;

    const formData = new FormData();
    formData.append('ajax_action', 'simulate_webhook');
    formData.append('account_id', accountId);
    formData.append('event_type', eventType);

    fetch('test_wisphub.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const modal = new bootstrap.Modal(document.getElementById('responseModal'));
        const modalAlert = document.getElementById('modalAlert');
        const modalResponseText = document.getElementById('modalResponseText');
        const modalTitle = document.getElementById('modalTitle');

        modalTitle.textContent = 'Simulación de Webhook Inbound';
        
        if (data.success) {
            modalAlert.className = 'alert alert-success py-2 rounded-3';
            modalAlert.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>' + data.message;
        } else {
            modalAlert.className = 'alert alert-danger py-2 rounded-3';
            modalAlert.innerHTML = '<i class="fa-solid fa-circle-exmark me-2"></i>' + data.message;
        }

        modalResponseText.textContent = JSON.stringify(data.data, null, 2);
        modal.show();
    })
    .catch(err => {
        console.error(err);
        alert('Error simulando webhook.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        setTimeout(() => {
            location.reload();
        }, 3000);
    });
}

// Limpiar Logs
function clearTestLogs(btn) {
    if (!confirm('¿Estás seguro de que deseas eliminar todos los logs y enlaces generados por simulación y pruebas?')) return;

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Limpiando...';

    const formData = new FormData();
    formData.append('ajax_action', 'clear_test_logs');

    fetch('test_wisphub.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Error al limpiar registros.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>

<?php require_once 'includes/layout_foot.php'; ?>
