<?php
session_start();

require_once __DIR__ . '/../paginas/principal/banco_api_router.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Acción no válida'];

    try {
        if ($action === 'test_bank_api_retry') {
            $id_banco = intval($_POST['id_banco'] ?? 9);

            $ts  = strtotime(date('Y-m-d'));
            $hoy = (new \DateTime('now', new \DateTimeZone('America/Caracas')))->format('Y-m-d');
            $max_fecha = $hoy;
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
        } elseif ($action === 'list_bank_transactions' || $action === 'list_all_bank_refs') {
            $id_banco = intval($_POST['id_banco'] ?? 9);
            $buscar_ref = trim($_POST['referencia'] ?? '');

            if ($action === 'list_all_bank_refs') {
                $fecha_ini = '2026-06-01';
                $fecha_fin = (new \DateTime('now', new \DateTimeZone('America/Caracas')))->format('Y-m-d');
            } else {
                $fecha_ini = $_POST['fecha_ini'] ?? date('Y-m-d');
                $fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
            }

            $resultado = consultar_movimientos_rango($id_banco, $fecha_ini, $fecha_fin);
            $total_apis = $resultado['total_api_calls'];

            if (empty($resultado['movs'])) {
                $response = ['status' => 'error', 'message' => "No se encontraron movimientos en el rango ({$total_apis} consultas API realizadas)."];
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

                $html = "<div class='small text-muted mb-1'>{$total_apis} consultas API | ";
                $html .= "<span class='text-info'>Total: " . count($movs) . "</span> | ";
                $html .= "<span class='text-success'>Créditos: {$creditos}</span> | ";
                $html .= "<span class='text-danger'>Débitos: {$debitos}</span></div>";
                $html .= '<div style="max-height:400px;overflow-y:auto;">';
                $html .= '<table class="table table-dark table-sm table-striped mb-0" style="font-size:0.75rem;">';
                $html .= '<thead><tr><th>Fecha</th><th>Hora</th><th>Tipo</th><th>Ref</th><th>Monto Bs</th><th>Obs</th></tr></thead><tbody>';
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
        } elseif ($action === 'list_last30_credits') {
            $id_banco = intval($_POST['id_banco'] ?? 9);

            $resultado = obtener_creditos_recientes($id_banco);

            if (empty($resultado['movs'])) {
                if ($resultado['api_respondio']) {
                    $response = ['status' => 'error', 'message' => "No se encontraron créditos en los últimos 30 días."];
                } else {
                    $response = ['status' => 'error', 'message' => "API no respondió. Error de conexión bancaria."];
                }
            } else {
                $html = "<div class='small text-muted mb-1'>Total créditos (30d): " . count($resultado['movs']) . "</div>";
                $html .= '<div style="max-height:400px;overflow-y:auto;">';
                $html .= '<table class="table table-dark table-sm table-striped mb-0" style="font-size:0.75rem;">';
                $html .= '<thead><tr><th>Fecha</th><th>Hora</th><th>Ref</th><th>Monto Bs</th><th>Origen</th></tr></thead><tbody>';
                foreach ($resultado['movs'] as $m) {
                    $fecha = $m['fecha'] ?? '?';
                    $hora = $m['hora'] ?? '';
                    $ref = $m['referencia'] ?? '?';
                    $importe = $m['importe'] ?? $m['monto'] ?? '?';
                    $desc = htmlspecialchars(substr($m['observacion'] ?? $m['descripcion'] ?? '', 0, 50));
                    $html .= "<tr class='text-success'><td>{$fecha}</td><td>{$hora}</td><td style='font-family:monospace'>{$ref}</td><td>Bs {$importe}</td><td style='font-size:0.7rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'>{$desc}</td></tr>";
                }
                $html .= '</tbody></table></div>';
                $response = ['status' => 'ok', 'html' => $html, 'total' => count($resultado['movs'])];
            }
        } elseif ($action === 'list_local_refs') {
            require_once __DIR__ . '/referencia_helper.php';
            $pdo = \getDb();
            if (!$pdo) {
                $response = ['status' => 'error', 'message' => 'No se pudo conectar a la base de datos local.'];
            } else {
                $buscar_ref = trim($_POST['referencia'] ?? '');
                try {
                    if ($buscar_ref) {
                        $stmt = $pdo->prepare("SELECT * FROM pagos_registrados WHERE referencia LIKE ? ORDER BY created_at DESC LIMIT 50");
                        $stmt->execute(['%' . $buscar_ref . '%']);
                    } else {
                        $stmt = $pdo->query("SELECT * FROM pagos_registrados ORDER BY created_at DESC LIMIT 100");
                    }
                    $rows = $stmt->fetchAll();
                    if (empty($rows)) {
                        $response = ['status' => 'error', 'message' => $buscar_ref ? "Referencia '{$buscar_ref}' no encontrada en la DB local." : "No hay referencias registradas en la DB local."];
                    } else {
                        $html = "<div class='small text-info mb-1'>Total registros: " . count($rows) . "</div>";
                        $html .= '<div style="max-height:400px;overflow-y:auto;">';
                        $html .= '<table class="table table-dark table-sm table-striped mb-0" style="font-size:0.7rem;">';
                        $html .= '<thead><tr><th>#</th><th>Referencia</th><th>Cliente</th><th>Fecha</th><th>Monto Bs</th><th>Servicio</th><th>Facturas</th><th>Método</th><th>Registrado</th></tr></thead><tbody>';
                        foreach ($rows as $r) {
                            $html .= '<tr>';
                            $html .= '<td>' . htmlspecialchars($r['id']) . '</td>';
                            $html .= '<td style="font-family:monospace;font-weight:700;color:#facc15;">' . htmlspecialchars($r['referencia']) . '</td>';
                            $html .= '<td>' . htmlspecialchars(substr($r['cliente'], 0, 20)) . '</td>';
                            $html .= '<td>' . htmlspecialchars($r['fecha_pago']) . '</td>';
                            $html .= '<td class="text-success">Bs ' . number_format($r['total_cobrado'], 2) . '</td>';
                            $html .= '<td>' . htmlspecialchars($r['service_id']) . '</td>';
                            $html .= '<td>' . htmlspecialchars($r['facturas']) . '</td>';
                            $html .= '<td>' . htmlspecialchars($r['forma_pago']) . '</td>';
                            $html .= '<td style="font-size:0.65rem;">' . htmlspecialchars($r['created_at']) . '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table></div>';
                        $response = ['status' => 'ok', 'html' => $html, 'total' => count($rows)];
                    }
                } catch (\PDOException $e) {
                    $response = ['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()];
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificador de Referencias — BDV</title>
    <link rel="icon" href="../images/favicon.png" type="image/png">
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
        .text-muted {
            color: #cbd5e1 !important;
        }
        .form-label {
            color: #f8fafc !important;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div id="page-loading" class="loading-overlay" style="display:none;"><div class="spinner"></div><div class="loading-text">Cargando...</div><div class="loading-sub">Procesando solicitud</div></div>

<div class="container">
    <div class="header-title">
        <span class="badge bg-warning px-3 py-2 text-dark">PRUEBAS</span>
        <h2 class="mb-0"><i class="fas fa-university me-2 text-warning"></i> Verificador de Referencias — BDV</h2>
    </div>
    <p class="text-muted mb-4">Página independiente para probar números de referencia contra el Banco de Venezuela (BDV) sin tocar el simulador de WispHub.</p>

    <div class="row">
        <div class="col-lg-12">
            <!-- Verificador BDV -->
            <div class="glass-panel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2 text-warning"></i> Consulta de Movimientos</h5>
                    <button class="btn btn-outline-warning btn-sm btn-custom" onclick="resetDefaults()">
                        <i class="fas fa-calendar"></i> Rango actual
                    </button>
                </div>

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
                    <label class="form-label small"><i class="fas fa-hashtag me-1"></i> Número de Referencia (opcional)</label>
                    <input type="text" id="bank_referencia" class="form-control bg-dark text-white border-secondary" placeholder="Ej: 139627" onkeypress="if(event.key==='Enter'){runBankAction('search_ref');}">
                </div>

                <div class="row g-2">
                    <div class="col-6 col-md-2">
                        <button class="btn btn-warning btn-custom w-100" onclick="runBankAction('test')">
                            <i class="fas fa-plug"></i> Test
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button class="btn btn-secondary btn-custom w-100" onclick="runBankAction('retry')">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button class="btn btn-info btn-custom w-100" onclick="runBankAction('list')">
                            <i class="fas fa-list"></i> Rango
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button class="btn btn-primary btn-custom w-100" onclick="runBankAction('search_ref')">
                            <i class="fas fa-search"></i> Buscar Ref
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button class="btn btn-success btn-custom w-100" onclick="runBankAction('all')">
                            <i class="fas fa-database"></i> Todo
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <button class="btn btn-light btn-custom w-100" onclick="runBankAction('last30')">
                            <i class="fas fa-clock"></i> 30D Créd
                        </button>
                    </div>
                </div>

                <div id="bank-result-box" class="mt-3 p-3 rounded" style="background:rgba(0,0,0,0.3);display:none;font-size:0.85rem;"></div>
            </div>

            <!-- Referencias Locales DB -->
            <div class="glass-panel">
                <h5 class="mb-4"><i class="fas fa-database me-2 text-info"></i> Referencias Registradas (DB Local)</h5>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <button class="btn btn-info btn-custom w-100" onclick="loadLocalRefs()">
                            <i class="fas fa-list"></i> Ver Todas
                        </button>
                    </div>
                    <div class="col-8">
                        <input type="text" id="local_ref_search" class="form-control bg-dark text-white border-secondary" placeholder="Buscar referencia registrada..." onkeypress="if(event.key==='Enter'){loadLocalRefs();}">
                    </div>
                </div>
                <div id="local-ref-result-box" class="p-3 rounded" style="background:rgba(0,0,0,0.3);display:none;font-size:0.85rem;"></div>
            </div>
        </div>

    </div>
</div>

<script>
function logMsg(msg, isError = false) {
    const box = document.getElementById('consola');
    if (!box) return;
    const time = new Date().toLocaleTimeString();
    const color = isError ? '#ff4444' : '#00ff00';
    box.innerHTML += `<span style="color: #666">[${time}]</span> <span style="color: ${color}">${msg}</span><br>`;
    box.scrollTop = box.scrollHeight;
}

function resetDefaults() {
    setDefaultDates();
    logMsg('Rango de fechas restablecido a los últimos 7 días.');
}

function setDefaultDates() {
    var hoy = new Date();
    var dd = String(hoy.getDate()).padStart(2,'0');
    var mm = String(hoy.getMonth()+1).padStart(2,'0');
    var yyyy = hoy.getFullYear();
    var hoyStr = yyyy+'-'+mm+'-'+dd;
    var inicio = new Date(hoy.getTime() - 7*24*60*60*1000);
    dd = String(inicio.getDate()).padStart(2,'0');
    mm = String(inicio.getMonth()+1).padStart(2,'0');
    var inicioStr = inicio.getFullYear()+'-'+mm+'-'+dd;
    document.getElementById('bank_fecha_ini').value = inicioStr;
    document.getElementById('bank_fecha_fin').value = hoyStr;
}

function loadLocalRefs() {
    var ref = document.getElementById('local_ref_search').value.trim();
    logMsg(ref ? 'Buscando referencia local: ' + ref : 'Cargando todas las referencias registradas...');
    document.getElementById('page-loading').style.display = 'flex';
    var box = document.getElementById('local-ref-result-box');
    box.style.display = 'none';
    var params = 'action=list_local_refs';
    if (ref) params += '&referencia=' + encodeURIComponent(ref);
    fetch('verificador_bdv.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('page-loading').style.display = 'none';
        box.style.display = 'block';
        if (data.status === 'ok') {
            logMsg('EXITO: ' + data.total + ' registro(s) encontrado(s)');
            box.innerHTML = data.html;
        } else {
            logMsg('ERROR: ' + data.message, true);
            box.innerHTML = '<div class="text-danger small">' + data.message + '</div>';
        }
    })
    .catch(function(err) {
        document.getElementById('page-loading').style.display = 'none';
        logMsg('ERROR DE RED: ' + err, true);
        box.innerHTML = '<div class="text-danger small">Error de red: ' + err + '</div>';
    });
}

function runBankAction(mode) {
    var id_banco = document.getElementById('bank_selector').value;
    var fecha_ini = document.getElementById('bank_fecha_ini').value;
    var fecha_fin = document.getElementById('bank_fecha_fin').value;
    var referencia = document.getElementById('bank_referencia').value;

    if (mode === 'test') {
        logMsg('Probando conexión con API BDV...');
        var hoy = new Date();
        fecha_ini = hoy.toISOString().split('T')[0];
        fecha_fin = fecha_ini;
        referencia = '';
    } else if (mode === 'retry') {
        logMsg('Ejecutando test con retry multi-rango (como en produccion)...');
        fetch('verificador_bdv.php', {
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
            logMsg('ERROR: Ingresa un número de referencia para buscar', true);
            document.getElementById('bank_referencia').focus();
            return;
        }
        logMsg('Buscando referencia ' + referencia + ' en BDV...');
    } else if (mode === 'all') {
        logMsg('Trayendo TODAS las referencias desde Junio 2026... (varias consultas API)');
        document.getElementById('page-loading').style.display = 'flex';
        var box = document.getElementById('bank-result-box');
        box.style.display = 'none';
        fetch('verificador_bdv.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=list_all_bank_refs&id_banco=' + id_banco
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('page-loading').style.display = 'none';
            box.style.display = 'block';
            if (data.status === 'ok') {
                logMsg('EXITO: ' + data.total + ' movimiento(s) encontrados');
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
    } else if (mode === 'last30') {
        logMsg('Trayendo últimos 30 días de CRÉDITOS BDV (modo producción)...');
        document.getElementById('page-loading').style.display = 'flex';
        var box = document.getElementById('bank-result-box');
        box.style.display = 'none';
        fetch('verificador_bdv.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=list_last30_credits&id_banco=' + id_banco
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('page-loading').style.display = 'none';
            box.style.display = 'block';
            if (data.status === 'ok') {
                logMsg('EXITO: ' + data.total + ' crédito(s) encontrado(s)');
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

    fetch('verificador_bdv.php', {
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
