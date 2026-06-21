<?php
session_start();
// Opcional: Asegurar que solo admins o test users puedan entrar. 
// Por ahora, al ser un simulador, lo dejaremos accesible si conocen la ruta (o puedes agregarle auth).

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$service_id = '902'; // Fijo para pruebas
$cedula_test = 'V20788775';

// Respuestas JSON para AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Acción no válida'];

    try {
        if ($action === 'test_connection') {
            $res = $wispClient->getServiceProfile($service_id);
            if ($res['status'] === 200) {
                $response = ['status' => 'ok', 'message' => 'Conexión exitosa. Cliente: ' . ($res['data']['nombre'] ?? 'Desconocido')];
            } else {
                $response = ['status' => 'error', 'message' => 'Error de conexión HTTP ' . $res['status']];
            }
        } elseif ($action === 'get_data') {
            $clientData = $wispClient->findClientByDocument($cedula_test);
            $invoices = $wispClient->getPendingInvoices($service_id);
            
            if ($clientData['status'] === 200 && isset($clientData['data']['data'])) {
                $data = $clientData['data']['data'];
                $saldo = floatval($data['saldo'] ?? 0);
                
                // Usar el estado real de WispHub
                $estado_real = $data['estado'] ?? 'Desconocido';
                $badge_class = (strtolower($estado_real) === 'activo') ? 'bg-success' : 'bg-danger';
                
                $plan_nombre = $data['plan_internet']['nombre'] ?? 'N/A';
                $ip = $data['ip'] ?? 'N/A';
                $router = $data['router']['nombre'] ?? 'N/A';
                
                // Construir una lista más completa con los datos útiles del perfil
                $html = "<div class='row text-light small g-3'>
                            <div class='col-md-6'>
                                <div class='p-2 rounded h-100' style='background: rgba(0,0,0,0.2);'>
                                    <h6 class='text-info mb-2 border-bottom border-secondary pb-1'>Datos Personales</h6>
                                    <strong>Nombre:</strong> {$data['nombre']}<br>
                                    <strong>Cédula:</strong> " . ($data['cedula'] ?: 'N/A') . "<br>
                                    <strong>Email:</strong> " . ($data['email'] ?: 'N/A') . "<br>
                                    <strong>Teléfono:</strong> " . ($data['telefono'] ?: 'N/A') . "<br>
                                    <strong>Dirección:</strong> " . ($data['direccion'] ?: 'N/A') . "<br>
                                    <strong>Ciudad:</strong> " . ($data['ciudad'] ?: 'N/A') . "<br>
                                </div>
                            </div>
                            <div class='col-md-6'>
                                <div class='p-2 rounded h-100' style='background: rgba(0,0,0,0.2);'>
                                    <h6 class='text-info mb-2 border-bottom border-secondary pb-1'>Servicio y WispHub</h6>
                                    <strong>ID / Usuario:</strong> {$data['id_servicio']} / " . ($data['usuario'] ?: 'N/A') . "<br>
                                    <strong>Plan:</strong> {$plan_nombre}<br>
                                    <strong>IP / Router:</strong> {$ip} / {$router}<br>
                                    <strong>Saldo / Facturas:</strong> <span class='" . ($saldo > 0 ? "text-danger fw-bold" : "text-success fw-bold") . "'>$" . number_format($saldo, 2) . "</span> / " . count($invoices) . " ptes.<br>
                                    <strong>Auto-Activar:</strong> " . (empty($data['auto_activar_servicio']) ? 'No' : 'Sí') . "<br>
                                    <strong>Corte el:</strong> " . ($data['fecha_corte'] ?: 'N/A') . "<br>
                                </div>
                            </div>
                            <div class='col-12 mt-2'>
                                <strong>Estado Real en WispHub:</strong> <span class='badge {$badge_class} fs-6 px-3 py-2'>{$estado_real}</span>
                            </div>
                         </div>";
                $response = ['status' => 'ok', 'html' => $html];
            } else {
                $response = ['status' => 'error', 'message' => 'No se pudo obtener datos o cliente no encontrado'];
            }
        } elseif ($action === 'suspend') {
            $res = $wispClient->suspendService($service_id, 'Corte simulado desde el Panel de Pruebas');
            if (in_array($res['status'], [200, 201])) {
                $response = ['status' => 'ok', 'message' => 'Servicio suspendido en WispHub.'];
            } else {
                $response = ['status' => 'error', 'message' => 'Error al suspender: HTTP ' . $res['status']];
            }
        } elseif ($action === 'activate') {
            $res = $wispClient->activateService($service_id);
            if (in_array($res['status'], [200, 201])) {
                $response = ['status' => 'ok', 'message' => 'Servicio activado en WispHub.'];
            } else {
                $response = ['status' => 'error', 'message' => 'Error al activar: HTTP ' . $res['status']];
            }
            } elseif ($action === 'pay') {
            $ref = $_POST['referencia'] ?? '';
            $monto = floatval($_POST['monto'] ?? 0);
            if (!$ref || $monto <= 0) {
                $response = ['status' => 'error', 'message' => 'Datos de pago inválidos'];
            } else {
                $res = $wispClient->registerPaymentAndActivate(
                    $service_id,
                    $monto,
                    $ref,
                    date('Y-m-d H:i'),
                    \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
                    true // force activate
                );
                if (in_array($res['status'], [200, 201])) {
                    $response = ['status' => 'ok', 'message' => 'Pago registrado y servicio activado. Se aplicaron $' . $res['amount_applied']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Error al registrar pago: ' . ($res['error'] ?? 'HTTP '.$res['status'])];
                }
            }
        } elseif ($action === 'test_bank_api_retry') {
            require_once __DIR__ . '/../paginas/principal/banco_api_router.php';
            $id_banco = intval($_POST['id_banco'] ?? 9);

            $ts  = strtotime(date('Y-m-d'));
            $hoy = (new \DateTime('now', new \DateTimeZone('America/Caracas')))->format('Y-m-d');
            $max_fecha = $hoy;
            if ((int)date('N', strtotime($max_fecha)) === 7) {
                $max_fecha = date('Y-m-d', strtotime($max_fecha . ' -1 day'));
            }
            $rangos = [
                ['-2 days', '+1 day'],
                ['-1 day',  '+0 day'],
                ['-3 days', '+1 day'],
                ['-10 days', '+0 day'],
            ];

            $html = '';
            $resultado_final = ['success' => false, 'movs' => []];
            $api_respondio = false;
            foreach ($rangos as $i => $offset) {
                $fi = date('Y-m-d', strtotime($offset[0], $ts));
                $ff = date('Y-m-d', strtotime($offset[1], $ts));
                if ($ff > $max_fecha) $ff = $max_fecha;

                $r = consultar_movimientos_banco($id_banco, $fi, $ff);
                $total = count($r['movs'] ?? []);
                $icon = !empty($r['success']) ? ($total > 0 ? '✅' : '⚠️') : '❌';
                $html .= "<div>{$icon} Rango " . ($i+1) . ": {$fi} a {$ff} → success: " . ($r['success']?'true':'false') . ", movs: {$total}</div>";

                if (!empty($r['success'])) {
                    $api_respondio = true;
                }
                if (!empty($r['success']) && !empty($r['movs'])) {
                    $resultado_final = $r;
                    break;
                }
                $resultado_final = $r;
            }

            if (empty($resultado_final['success']) || empty($resultado_final['movs'])) {
                if ($api_respondio) {
                    $html .= "<div class='text-warning mt-2'>⚠️ API respondió pero sin movimientos en todos los rangos.</div>";
                } else {
                    $html .= "<div class='text-danger mt-2'>❌ API no respondió en ningún rango. Posible error de conexión.</div>";
                }
                $response = ['status' => 'ok', 'html' => $html];
            } else {
                $html .= "<div class='text-success mt-2 fw-bold'>✅ Se encontraron " . count($resultado_final['movs']) . " movimientos.</div>";
                $response = ['status' => 'ok', 'html' => $html, 'total' => count($resultado_final['movs'])];
            }
        } elseif ($action === 'list_bank_transactions') {
            require_once __DIR__ . '/../paginas/principal/banco_api_router.php';
            $id_banco = intval($_POST['id_banco'] ?? 9);
            $fecha_ini = $_POST['fecha_ini'] ?? date('Y-m-d');
            $fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
            $buscar_ref = trim($_POST['referencia'] ?? '');

            $resultado = consultar_movimientos_banco($id_banco, $fecha_ini, $fecha_fin);

            if (empty($resultado['success'])) {
                $msg = $resultado['message'] ?? 'Error de conexión con la API del banco';
                $response = ['status' => 'error', 'message' => $msg];
            } elseif (empty($resultado['movs'])) {
                $response = ['status' => 'error', 'message' => 'No se encontraron movimientos en el rango seleccionado.'];
            } else {
                $movs = $resultado['movs'];

                if ($buscar_ref) {
                    $ref_clean = preg_replace('/\D/', '', $buscar_ref);
                    $ref_6 = strlen($ref_clean) >= 6 ? substr($ref_clean, -6) : $ref_clean;
                    $filtered = [];
                    foreach ($movs as $m) {
                        $m_ref = preg_replace('/\D/', '', $m['referencia'] ?? '');
                        $m_ref_6 = strlen($m_ref) >= 6 ? substr($m_ref, -6) : $m_ref;
                        if ($m_ref === $ref_clean || $m_ref_6 === $ref_6) {
                            $filtered[] = $m;
                        }
                    }
                    $movs = $filtered;
                    if (empty($movs)) {
                        echo json_encode(['status' => 'error', 'message' => "Referencia '{$buscar_ref}' no encontrada en el rango."]);
                        exit;
                    }
                }

                $creditos = 0;
                $debitos = 0;
                foreach ($movs as $m) {
                    $t = strtoupper($m['mov'] ?? $m['Tipo'] ?? '');
                    if ($t === 'CREDITO') $creditos++;
                    elseif (strpos($t, 'DEBITO') !== false) $debitos++;
                }

                $html = "<div class='small'><span class='text-info'>Total: " . count($movs) . "</span> | ";
                $html .= "<span class='text-success'>Créditos: {$creditos}</span> | ";
                $html .= "<span class='text-danger'>Débitos: {$debitos}</span></div>";
                $html .= '<div style="max-height:350px;overflow-y:auto;margin-top:8px;">';
                $html .= '<table class="table table-dark table-sm table-striped mb-0" style="font-size:0.75rem;">';
                $html .= '<thead><tr><th>Fecha</th><th>Hora</th><th>Tipo</th><th>Referencia</th><th>Monto Bs</th><th>Obs</th></tr></thead><tbody>';
                foreach ($movs as $m) {
                    $tipo = $m['mov'] ?? $m['Tipo'] ?? '?';
                    $fecha = $m['fecha'] ?? '?';
                    $hora = $m['hora'] ?? '';
                    $ref = $m['referencia'] ?? '?';
                    $importe = $m['importe'] ?? $m['monto'] ?? '?';
                    $obs = htmlspecialchars(substr($m['observacion'] ?? '', 0, 45));
                    $color = strtoupper($tipo) === 'CREDITO' ? 'text-success' : 'text-danger';
                    $html .= "<tr class='{$color}'><td>{$fecha}</td><td>{$hora}</td><td>{$tipo}</td><td style='font-family:monospace'>{$ref}</td><td>Bs {$importe}</td><td style='font-size:0.7rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'>{$obs}</td></tr>";
                }
                $html .= '</tbody></table></div>';
                $response = ['status' => 'ok', 'html' => $html, 'total' => count($movs)];
            }
        }
    } catch (\Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>WispHub — Simulador de Integración</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/fontawesome/css/all.min.css">
    <style>
        :root {
            --bg-color: #0f172a;
            --panel-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --primary: #3b82f6;
            --danger: #ef4444;
            --success: #10b981;
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', system-ui, sans-serif;
            padding-top: 2rem;
        }
        .glass-panel {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-custom {
            border-radius: 8px;
            font-weight: 500;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        .log-box {
            background: #000;
            color: #0f0;
            font-family: monospace;
            padding: 10px;
            border-radius: 8px;
            height: 200px;
            overflow-y: auto;
            font-size: 0.85rem;
        }
        .text-muted {
            color: #cbd5e1 !important; /* Gris más claro para que resalte en fondo oscuro */
        }
        .form-label {
            color: #f8fafc !important; /* Blanco para los labels */
            font-weight: 600;
        }
    </style>
</head>
<body>

<div id="page-loading" class="loading-overlay" style="display:none;"><div class="spinner"></div><div class="loading-text">Cargando...</div><div class="loading-sub">Procesando solicitud</div></div>

<div class="container">
    <div class="header-title">
        <span class="badge bg-primary px-3 py-2">PRUEBAS</span>
        <h2 class="mb-0">WispHub — Simulador de Integración</h2>
    </div>
    <p class="text-muted mb-4">Herramienta interactiva para verificar suspensión, reactivación y flujo de cobro con la API de WispHub.</p>

    <div class="row">
        <!-- Columna Izquierda -->
        <div class="col-md-7">
            <!-- Conectividad -->
            <div class="glass-panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-server me-2 text-primary"></i> Conectividad API</h5>
                    <button class="btn btn-outline-primary btn-sm btn-custom" onclick="runAction('test_connection')">
                        <i class="fas fa-sync-alt"></i> Probar Conexión
                    </button>
                </div>
                <div class="row text-light small">
                    <div class="col-4">API Endpoint:</div>
                    <div class="col-8 text-main fw-bold">https://api.wisphub.net/api/</div>
                    <div class="col-4 mt-2">API Key:</div>
                    <div class="col-8 text-main mt-2 fw-bold">******** (Oculto)</div>
                </div>
            </div>

            <!-- Simulador -->
            <div class="glass-panel">
                <h5 class="mb-4"><i class="fas fa-play-circle me-2 text-info"></i> Simulador de Acciones</h5>
                
                <div class="mb-4">
                    <label class="form-label text-muted small">1. Cliente de Prueba (Fijo)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-info border-secondary">V20788775</span>
                        <input type="text" class="form-control bg-dark text-white border-secondary" value="Cliente OFICINA Prueba (ID: 902)" readonly>
                    </div>
                    <button class="btn btn-outline-info btn-custom w-100 mt-2" onclick="runAction('get_data')">
                        <i class="fas fa-search"></i> Obtener datos del cliente en WispHub
                    </button>
                    <div id="client-data-box" class="mt-3 p-3 rounded" style="background: rgba(255,255,255,0.05); display:none;"></div>
                </div>

                <div class="mb-4">
                    <label class="form-label small"><i class="fas fa-bolt text-warning"></i> Simular Suspensión y Activación Directa</label>
                    <p class="small text-light mb-2">Llama directamente a los endpoints de suspender y activar en WispHub.</p>
                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-danger btn-custom w-100" onclick="runAction('suspend')">
                                <i class="fas fa-pause-circle"></i> Cortar / Suspender
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-success btn-custom w-100" onclick="runAction('activate')">
                                <i class="fas fa-check-circle"></i> Activar / Restablecer
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small"><i class="fas fa-money-bill-wave text-success"></i> Simular Reporte y Aprobación de Pago</label>
                    <p class="small text-light mb-2">Simula el registro automático de un pago aprobado, que llama a registrar-pago y auto-activa.</p>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <input type="text" id="sim_ref" class="form-control bg-dark text-white border-secondary" placeholder="Ref. Bancaria (ej. 123456)">
                        </div>
                        <div class="col-6">
                            <input type="number" id="sim_monto" class="form-control bg-dark text-white border-secondary" placeholder="Monto (USD)" value="1.00" step="0.01">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-custom w-100" onclick="runAction('pay')">
                        <i class="fas fa-paper-plane"></i> Reportar Pago y Auto-Activar
                    </button>
                </div>
                
                <hr class="border-secondary mt-4 mb-4">
                
                <div class="alert alert-warning mb-0 text-dark">
                    <i class="fas fa-info-circle"></i> <strong>Nota sobre Facturas:</strong>
                    Para que el botón de pago tenga efecto sobre el saldo, o para simular un "Impago", 
                    debes generar una factura manual directamente en el panel real de <strong>WispHub</strong> para el cliente 902.
                </div>

            </div>

            <!-- Banco API -->
            <div class="glass-panel">
                <h5 class="mb-4"><i class="fas fa-university me-2 text-warning"></i> Banco API — Verificador BDV</h5>

                <div class="mb-3">
                    <label class="form-label small">Banco</label>
                    <select id="bank_selector" class="form-select bg-dark text-white border-secondary">
                        <option value="9">BDV Pago Móvil</option>
                        <option value="12">BDV Transferencia</option>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small">Fecha Desde</label>
                        <input type="date" id="bank_fecha_ini" class="form-control bg-dark text-white border-secondary">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Fecha Hasta</label>
                        <input type="date" id="bank_fecha_fin" class="form-control bg-dark text-white border-secondary">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Buscar Referencia (opcional)</label>
                    <input type="text" id="bank_referencia" class="form-control bg-dark text-white border-secondary" placeholder="Ej: 139627">
                </div>

                <div class="row g-2">
                    <div class="col-3">
                        <button class="btn btn-warning btn-custom w-100" onclick="runBankAction('test')">
                            <i class="fas fa-plug"></i> Test
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-secondary btn-custom w-100" onclick="runBankAction('retry')">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-info btn-custom w-100" onclick="runBankAction('list')">
                            <i class="fas fa-list"></i> Listar
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-primary btn-custom w-100" onclick="runBankAction('search_ref')">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>

                <div id="bank-result-box" class="mt-3 p-3 rounded" style="background:rgba(0,0,0,0.3);display:none;font-size:0.85rem;"></div>
            </div>
        </div>

        <!-- Columna Derecha: Consola -->
        <div class="col-md-5">
            <div class="glass-panel h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-terminal me-2"></i> Consola de Salida</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('consola').innerHTML=''">Limpiar</button>
                </div>
                <div id="consola" class="log-box">
                    > Listo para ejecutar pruebas...<br>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function logMsg(msg, isError = false) {
    const box = document.getElementById('consola');
    const time = new Date().toLocaleTimeString();
    const color = isError ? '#ff4444' : '#00ff00';
    box.innerHTML += `<span style="color: #666">[${time}]</span> <span style="color: ${color}">${msg}</span><br>`;
    box.scrollTop = box.scrollHeight;
}

function runAction(action) {
    logMsg(`Ejecutando acción: ${action}...`);
    document.getElementById('page-loading').style.display = 'flex';
    
    const formData = new FormData();
    formData.append('action', action);
    
    if (action === 'pay') {
        formData.append('referencia', document.getElementById('sim_ref').value);
        formData.append('monto', document.getElementById('sim_monto').value);
    }
    
    fetch('simulador.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('page-loading').style.display = 'none';
        if (data.status === 'ok') {
            logMsg('ÉXITO: ' + data.message);
            if (data.html) {
                const cb = document.getElementById('client-data-box');
                cb.style.display = 'block';
                cb.innerHTML = data.html;
            }
            
            // Si la acción modificó algo, esperamos 3 segundos y recargamos los datos
            if (['suspend', 'activate', 'pay'].includes(action)) {
                logMsg('Esperando a que WispHub procese la tarea...');
                setTimeout(() => {
                    logMsg('Recargando estado actual del cliente...');
                    runAction('get_data');
                }, 3000);
            }
        } else {
            logMsg('ERROR: ' + data.message, true);
        }
    })
    .catch(err => {
        document.getElementById('page-loading').style.display = 'none';
        logMsg('ERROR DE RED: ' + err, true);
    });
}

function setDefaultDates() {
    var hoy = new Date();
    var dd = String(hoy.getDate()).padStart(2,'0');
    var mm = String(hoy.getMonth()+1).padStart(2,'0');
    var yyyy = hoy.getFullYear();
    var hoyStr = yyyy+'-'+mm+'-'+dd;
    var inicio = new Date(Date.now() - 7*24*60*60*1000);
    dd = String(inicio.getDate()).padStart(2,'0');
    mm = String(inicio.getMonth()+1).padStart(2,'0');
    var inicioStr = inicio.getFullYear()+'-'+mm+'-'+dd;
    document.getElementById('bank_fecha_ini').value = inicioStr;
    document.getElementById('bank_fecha_fin').value = hoyStr;
}

function runBankAction(mode) {
    var id_banco = document.getElementById('bank_selector').value;
    var fecha_ini = document.getElementById('bank_fecha_ini').value;
    var fecha_fin = document.getElementById('bank_fecha_fin').value;
    var referencia = document.getElementById('bank_referencia').value;

    if (mode === 'test') {
        logMsg('Probando conexión con API BDV (rango: hoy)...');
        fecha_ini = new Date().toISOString().split('T')[0];
        fecha_fin = fecha_ini;
        referencia = '';
    } else if (mode === 'retry') {
        logMsg('Ejecutando test con retry multi-rango (como en produccion)...');
        fetch('simulador.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=test_bank_api_retry&id_banco=' + id_banco
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('page-loading').style.display = 'none';
            var box = document.getElementById('bank-result-box');
            box.style.display = 'block';
            if (data.status === 'ok') {
                var total = data.total || 0;
                logMsg(total > 0 ? 'EXITO: ' + total + ' movimiento(s) encontrado(s)' : 'INFO: API respondio, sin movimientos');
                box.innerHTML = data.html;
            } else {
                logMsg('ERROR: ' + data.message, true);
                box.innerHTML = '<div class="text-danger small">' + data.message + '</div>';
            }
        })
        .catch(function(err) {
            document.getElementById('page-loading').style.display = 'none';
            logMsg('ERROR DE RED: ' + err, true);
        });
        return;
    } else if (mode === 'search_ref') {
        if (!referencia) {
            logMsg('ERROR: Ingresa una referencia para buscar', true);
            return;
        }
        logMsg('Buscando referencia ' + referencia + ' en BDV...');
    } else {
        logMsg('Listando transacciones BDV de ' + fecha_ini + ' a ' + fecha_fin + '...');
    }

    document.getElementById('page-loading').style.display = 'flex';
    var box = document.getElementById('bank-result-box');
    box.style.display = 'none';

    var formData = new FormData();
    formData.append('action', 'list_bank_transactions');
    formData.append('id_banco', id_banco);
    formData.append('fecha_ini', fecha_ini);
    formData.append('fecha_fin', fecha_fin);
    formData.append('referencia', referencia);

    fetch('simulador.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('page-loading').style.display = 'none';
        box.style.display = 'block';
        if (data.status === 'ok') {
            logMsg('EXITO: ' + data.total + ' movimiento(s) encontrado(s)');
            box.innerHTML = data.html;
        } else {
            logMsg('ERROR: ' + data.message, true);
            box.innerHTML = '<div class="text-danger small">' + data.message + '</div>';
        }
    })
    .catch(function(err) {
        document.getElementById('page-loading').style.display = 'none';
        logMsg('ERROR DE RED: ' + err, true);
    });
}

setDefaultDates();
</script>
</body>
</html>
