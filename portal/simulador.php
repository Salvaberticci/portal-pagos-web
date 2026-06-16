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
            $perfilRes = $wispClient->getServiceProfile($service_id);
            $invoices = $wispClient->getPendingInvoices($service_id);
            
            if ($perfilRes['status'] === 200) {
                $data = $perfilRes['data'];
                // Determinar estado basado en facturas/saldo WispHub
                $saldo = floatval($data['saldo'] ?? 0);
                $estado = ($saldo > 0) ? 'Con Deuda (Posible Suspensión)' : 'Al Día';
                // Aunque WispHub no retorna 'estado' directamente, el saldo nos da una idea.
                
                // Construir una lista más completa con los datos útiles del perfil
                $html = "<div class='row text-light small g-3'>
                            <div class='col-md-6'>
                                <div class='p-2 rounded' style='background: rgba(0,0,0,0.2);'>
                                    <h6 class='text-info mb-2 border-bottom border-secondary pb-1'>Datos Personales</h6>
                                    <strong>Nombre:</strong> {$data['nombre']} {$data['apellidos']}<br>
                                    <strong>Cédula:</strong> " . ($data['cedula'] ?: 'N/A') . "<br>
                                    <strong>Email:</strong> " . ($data['email'] ?: 'N/A') . "<br>
                                    <strong>Teléfono:</strong> " . ($data['telefono'] ?: 'N/A') . "<br>
                                    <strong>Dirección:</strong> " . ($data['direccion'] ?: 'N/A') . "<br>
                                    <strong>Ciudad:</strong> " . ($data['ciudad'] ?: 'N/A') . "<br>
                                </div>
                            </div>
                            <div class='col-md-6'>
                                <div class='p-2 rounded' style='background: rgba(0,0,0,0.2);'>
                                    <h6 class='text-info mb-2 border-bottom border-secondary pb-1'>Servicio y Facturación</h6>
                                    <strong>Usuario WispHub:</strong> " . ($data['usuario'] ?: 'N/A') . "<br>
                                    <strong>ID Servicio:</strong> {$data['id_servicio']}<br>
                                    <strong>Saldo Total (Deuda):</strong> <span class='" . ($saldo > 0 ? "text-danger fw-bold" : "text-success fw-bold") . "'>$" . number_format($saldo, 2) . "</span><br>
                                    <strong>Facturas Pendientes:</strong> " . count($invoices) . "<br>
                                    <strong>Aplicar Mora:</strong> " . (empty($data['aplicar_mora']) ? 'No' : 'Sí') . "<br>
                                    <strong>Aviso en Pantalla:</strong> " . (empty($data['aviso_pantalla']) ? 'No' : 'Sí') . "<br>
                                </div>
                            </div>
                            <div class='col-12 mt-3'>
                                <strong>Estado Estimado:</strong> <span class='badge " . ($saldo > 0 ? "bg-danger" : "bg-success") . " fs-6'>{$estado}</span>
                            </div>
                         </div>";
                $response = ['status' => 'ok', 'html' => $html];
            } else {
                $response = ['status' => 'error', 'message' => 'No se pudo obtener datos'];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        if (data.status === 'ok') {
            logMsg('ÉXITO: ' + data.message);
            if (data.html) {
                const cb = document.getElementById('client-data-box');
                cb.style.display = 'block';
                cb.innerHTML = data.html;
            }
        } else {
            logMsg('ERROR: ' + data.message, true);
        }
    })
    .catch(err => {
        logMsg('ERROR DE RED: ' + err, true);
    });
}
</script>
</body>
</html>
