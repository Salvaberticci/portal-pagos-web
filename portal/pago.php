<?php
require_once 'security_helper.php';
enforce_https();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

$pago_err = $_SESSION['pago_err'] ?? '';
unset($_SESSION['pago_err']);

$pago_exito = $_SESSION['pago_exito'] ?? '';
unset($_SESSION['pago_exito']);

$wisp_service_id = isset($_GET['id_contrato']) ? $_GET['id_contrato'] : '';
$recibo_id_sel = isset($_GET['recibo_id']) ? intval($_GET['recibo_id']) : 0;
$cedula = $_SESSION['cliente_cedula'];

if (empty($wisp_service_id)) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$profileRes = $wispClient->getServiceProfile($wisp_service_id);
if ($profileRes['status'] !== 200 || empty($profileRes['data'])) {
    header('Location: dashboard.php');
    exit;
}
$c_perfil = $profileRes['data'];

$detailRes = $wispClient->getServiceDetail($wisp_service_id);
if ($detailRes['status'] === 200 && !empty($detailRes['data'])) {
    $c_perfil = array_merge($c_perfil, $detailRes['data']);
}

$invoices = $wispClient->getPendingInvoices($wisp_service_id);
$deuda_total = 0;
$reciboSeleccionado = null;
foreach ($invoices as $inv) {
    $inv_monto = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
    $deuda_total += $inv_monto;
    if ($recibo_id_sel > 0 && ($inv['id'] ?? $inv['id_factura'] ?? 0) == $recibo_id_sel) {
        $reciboSeleccionado = $inv;
    }
}

$saldo_favor = $wispClient->getClientBalance($wisp_service_id);

$monto_a_pagar = $deuda_total;
if ($reciboSeleccionado) {
    $monto_a_pagar = floatval($reciboSeleccionado['monto'] ?? $reciboSeleccionado['monto_pendiente'] ?? $reciboSeleccionado['total'] ?? 0);
}
if ($cedula === TEST_USER_CEDULA) {
    $monto_a_pagar = 1.00;
    $deuda_total = 1.00;
}

$tasa_bcv = 1;
$cache_file = 'tasa_cache.json';
$cache_time = 3600;
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    $data_cache = json_decode(file_get_contents($cache_file), true);
    $tasa_bcv = $data_cache['tasa'] ?? 1;
} else {
    $url_bcv = "https://ve.dolarapi.com/v1/dolares/oficial";
    $ch = curl_init($url_bcv);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp_bcv = curl_exec($ch);
    if (!curl_errno($ch)) {
        $data_bcv = json_decode($resp_bcv, true);
        if (isset($data_bcv['promedio'])) {
            $tasa_bcv = floatval($data_bcv['promedio']);
            @file_put_contents($cache_file, json_encode(['tasa' => $tasa_bcv, 'fecha' => date('Y-m-d H:i:s')]));
        }
    }
    curl_close($ch);
}

$json_bancos = @file_get_contents('../paginas/principal/bancos.json');
$bancosArr = json_decode($json_bancos, true) ?: [];

$metodosDisponibles = [];
foreach ($bancosArr as $b) {
    if ($b['activo'] !== false) {
        foreach ($b['metodos_pago'] as $m) {
            if (!isset($metodosDisponibles[$m])) {
                $metodosDisponibles[$m] = [];
            }
            $metodosDisponibles[$m][] = $b;
        }
    }
}
$ordenMetodos = array('Pago Móvil', 'Transferencia', 'Zelle');
$metodosFiltrados = array();
foreach ($ordenMetodos as $m) {
    if (isset($metodosDisponibles[$m])) {
        $metodosFiltrados[$m] = $metodosDisponibles[$m];
    }
}

$deuda_bs = $deuda_total * $tasa_bcv;
$monto_bs = $monto_a_pagar * $tasa_bcv;
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar - Wireless Supply</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <header class="glass-header py-3">
        <div class="container d-flex align-items-center">
            <a href="dashboard.php" class="text-decoration-none me-3">
                <i class="fas fa-chevron-left fa-lg"></i>
            </a>
            <h5 class="mb-0 fw-bold text-gradient">Pagar Mensualidad</h5>
            <div class="ms-auto">
                <button class="theme-toggle" id="themeToggleBtn" title="Cambiar Tema">
                    <i class="fas fa-sun"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="container main-container animate-fade py-4" style="max-width:520px;">

        <?php if (!empty($pago_err)): ?>
            <div class="alert alert-danger glass-panel mb-4"><?php echo $pago_err; ?></div>
        <?php endif; ?>
        <?php if (!empty($pago_exito)): ?>
            <div class="alert alert-success glass-panel mb-4"><?php echo $pago_exito; ?></div>
        <?php endif; ?>
        <?php if ($cedula === TEST_USER_CEDULA): ?>
            <div class="alert alert-info glass-panel mb-4 text-center">
                <i class="fas fa-info-circle me-2"></i> Modo de prueba: montos demo.
            </div>
        <?php endif; ?>

        <form id="paymentForm" action="procesar_pago_cliente.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="id_contrato" value="<?php echo $wisp_service_id; ?>">
            <input type="hidden" name="tasa_dolar" value="<?php echo $tasa_bcv; ?>">
            <input type="hidden" name="monto_usd" id="input_monto_usd" value="<?php echo number_format($monto_a_pagar, 2, '.', ''); ?>">
            <input type="hidden" name="metodo_pago" id="input_metodo" value="">
            <input type="hidden" name="id_banco_destino" id="input_banco" value="">
            <input type="hidden" name="monto_usd_real" id="input_monto_usd_real" value="0">
            <input type="hidden" name="verificacion_data" id="input_verificacion_data" value="">
            <input type="hidden" name="invoice_ids[]" value="<?php echo $recibo_id_sel > 0 ? $recibo_id_sel : ''; ?>">
            <input type="hidden" name="meses_adelanto" value="0">
            <input type="hidden" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>">

            <!-- N Contrato -->
            <div class="pago-field-top glass-panel px-4 py-3 mb-3">
                <input type="text" class="pago-input-top" value="<?php echo htmlspecialchars($wisp_service_id); ?>" readonly>
            </div>

            <!-- Monto a Pagar (Colapsable) -->
            <div class="glass-panel mb-3 pago-monto-panel">
                <div class="pago-monto-header" onclick="toggleMonto()">
                    <div>
                        <small class="text-muted">Monto a Pagar</small>
                        <div class="pago-monto-bs" id="monto_bs_display">Bs. <?php echo number_format($monto_bs, 2, ',', '.'); ?></div>
                    </div>
                    <i class="fas fa-chevron-up pago-monto-chevron" id="monto_chevron"></i>
                </div>
                <div class="pago-monto-body" id="monto_body">
                    <div class="pago-monto-row">
                        <span class="text-muted">Deuda Acumulada:</span>
                        <span class="fw-bold">$<?php echo number_format($deuda_total, 2); ?></span>
                    </div>
                    <div class="pago-monto-row">
                        <span class="text-muted">Monto a Favor:</span>
                        <span class="fw-bold text-success">$<?php echo number_format($saldo_favor, 2); ?></span>
                    </div>
                    <div class="pago-monto-row">
                        <span class="text-muted">Deuda en Bolivares:</span>
                        <span class="fw-bold">Bs <?php echo number_format($deuda_bs, 2, ',', '.'); ?></span>
                    </div>
                    <?php if ($tasa_bcv > 0): ?>
                    <div class="pago-monto-row mt-2 pt-2 border-top border-white border-opacity-10">
                        <span class="text-muted small">Tasa BCV:</span>
                        <span class="small">Bs <?php echo number_format($tasa_bcv, 2, ',', '.'); ?> / $1</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Metodo de Pago (Botones) -->
            <div class="glass-panel p-4 mb-3">
                <h6 class="fw-bold mb-3 text-center">Metodo de Pago</h6>
                <div class="metodo-btns-grid">
                    <?php foreach ($metodosFiltrados as $metodo => $bancosMetodo): ?>
                    <button type="button" class="metodo-btn" data-metodo="<?php echo htmlspecialchars($metodo); ?>" onclick="selectMetodo('<?php echo htmlspecialchars($metodo); ?>')">
                        <?php if ($metodo === 'Zelle'): ?>
                            <i class="fas fa-dollar-sign"></i>
                        <?php elseif (strpos($metodo, 'vil') !== false): ?>
                            <i class="fas fa-mobile-alt"></i>
                        <?php else: ?>
                            <i class="fas fa-university"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($metodo); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Datos Banco (inline) -->
            <div class="glass-panel p-4 mb-3 d-none" id="panel-banco">
                <div class="d-flex align-items-center mb-3">
                    <div class="banco-icon-wrap me-3">
                        <i class="fas fa-university"></i>
                    </div>
                    <div>
                        <div class="fw-bold" id="banco_nombre"></div>
                        <small class="text-muted" id="banco_titular"></small>
                    </div>
                </div>
                <div id="banco_detalles"></div>
                <div class="mt-2 text-end">
                    <button type="button" class="btn btn-sm btn-glass copy-btn" id="btn_copiar" onclick="copiarDatos()">
                        <i class="far fa-copy me-1"></i> Copiar Datos
                    </button>
                </div>
            </div>

            <!-- Monto Zelle (solo para Zelle) -->
            <div class="glass-panel p-4 mb-3 d-none" id="panel-zelle-monto">
                <label class="label-premium">Monto en USD</label>
                <input type="number" step="0.01" min="0.01" name="monto_manual_usd" class="form-control glass-input" placeholder="0.00" id="input_zelle_monto">
            </div>

            <!-- Referencia -->
            <div class="glass-panel p-4 mb-3">
                <label class="label-premium">Numero de Referencia</label>
                <input type="text" name="referencia" class="form-control glass-input" placeholder="Ingresa tu referencia" id="input_referencia" required>
            </div>

            <!-- Boton Activar -->
            <div class="mb-4">
                <button type="button" class="btn btn-activar w-100 py-3" id="btn_activar" onclick="activarPago()" disabled>
                    <i class="fas fa-bolt me-2"></i> Activar
                </button>
            </div>
        </form>

        <!-- Resultado Verificacion -->
        <div class="glass-panel p-4 mb-4 d-none" id="panel-resultado">
            <h5 class="fw-bold mb-3 text-success"><i class="fas fa-check-circle me-2"></i> Datos de Reporte</h5>
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <tbody id="resultado_body"></tbody>
                </table>
            </div>
            <div class="mt-3 p-3 rounded" style="background:rgba(16,185,129,0.08);border-left:4px solid var(--success);" id="descripcion_pago"></div>
            <div class="mt-3">
                <button type="button" class="btn btn-premium w-100 py-3" id="btn_confirmar" onclick="confirmarPago()">
                    <i class="fas fa-check-circle me-2"></i> Confirmar Pago
                </button>
            </div>
        </div>

    </div>

    <div id="loadingOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:99999;justify-content:center;align-items:center;flex-direction:column;">
        <div style="width:50px;height:50px;border:4px solid rgba(255,255,255,0.2);border-top-color:#3b82f6;border-radius:50%;animation:loadingSpin 0.8s linear infinite;"></div>
        <p style="color:#fff;font-size:1.2rem;font-weight:600;margin-top:20px;">Procesando...</p>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    const tasaBcv = <?php echo $tasa_bcv; ?>;
    const todosLosBancos = <?php echo json_encode($bancosArr); ?>;
    const csrfToken = '<?php echo htmlspecialchars(generate_csrf_token()); ?>';
    const idContrato = '<?php echo $wisp_service_id; ?>';
    const saldoFavor = <?php echo $saldo_favor; ?>;
    const deudaUSD = <?php echo $deuda_total; ?>;
    const montoUSD = <?php echo $monto_a_pagar; ?>;
    const metodosBancos = <?php echo json_encode($metodosFiltrados); ?>;
    let selectedMetodo = '';
    let selectedBanco = null;
    let verificacionData = null;
    let montoExpandido = true;

    document.getElementById('themeToggleBtn').addEventListener('click', function() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        this.querySelector('i').className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });

    function toggleMonto() {
        montoExpandido = !montoExpandido;
        const body = document.getElementById('monto_body');
        const chevron = document.getElementById('monto_chevron');
        if (montoExpandido) {
            body.classList.remove('d-none');
            chevron.className = 'fas fa-chevron-up pago-monto-chevron';
        } else {
            body.classList.add('d-none');
            chevron.className = 'fas fa-chevron-down pago-monto-chevron';
        }
    }

    function selectMetodo(metodo) {
        document.querySelectorAll('.metodo-btn').forEach(function(b) { b.classList.remove('active'); });
        var btn = document.querySelector('.metodo-btn[data-metodo="' + metodo + '"]');
        if (btn) btn.classList.add('active');

        selectedMetodo = metodo;
        document.getElementById('input_metodo').value = metodo;

        var bancosMetodo = metodosBancos[metodo] || [];
        var panelBanco = document.getElementById('panel-banco');

        if (bancosMetodo.length > 0) {
            var banco = bancosMetodo[0];
            selectedBanco = banco;
            document.getElementById('input_banco').value = banco.id_banco;
            mostrarBanco(banco);
            panelBanco.classList.remove('d-none');
        } else {
            panelBanco.classList.add('d-none');
            selectedBanco = null;
            document.getElementById('input_banco').value = '';
        }

        var panelZelle = document.getElementById('panel-zelle-monto');
        if (metodo === 'Zelle') {
            panelZelle.classList.remove('d-none');
        } else {
            panelZelle.classList.add('d-none');
        }

        ocultarResultado();
        updateActivarBtn();
    }

    function mostrarBanco(bank) {
        document.getElementById('banco_nombre').textContent = bank.nombre_banco;
        document.getElementById('banco_titular').textContent = bank.nombre_propietario + ' - ' + bank.cedula_propietario;

        var div = document.getElementById('banco_detalles');
        div.innerHTML = '';
        var lines = [];
        if (selectedMetodo.indexOf('vil') !== -1) {
            lines = [bank.numero_cuenta, bank.cedula_propietario];
        } else if (selectedMetodo === 'Transferencia') {
            lines = [bank.numero_cuenta, bank.nombre_propietario];
        } else if (selectedMetodo === 'Zelle') {
            lines = [bank.numero_cuenta, bank.nombre_propietario];
        }
        lines.forEach(function(val) {
            var row = document.createElement('div');
            row.className = 'banco-detail-row';
            row.textContent = val;
            div.appendChild(row);
        });
    }

    function copiarDatos() {
        if (!selectedBanco) return;
        var text = selectedBanco.numero_cuenta + '\n' + selectedBanco.nombre_propietario + '\n' + selectedBanco.cedula_propietario;
        navigator.clipboard.writeText(text).then(function() {
            var btn = document.getElementById('btn_copiar');
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Copiado';
            btn.classList.add('text-success');
            setTimeout(function() { btn.innerHTML = '<i class="far fa-copy me-1"></i> Copiar Datos'; btn.classList.remove('text-success'); }, 2000);
        });
    }

    function updateActivarBtn() {
        var ref = document.getElementById('input_referencia').value.trim();
        var btn = document.getElementById('btn_activar');
        btn.disabled = !selectedMetodo || ref.length < 6;
    }

    document.getElementById('input_referencia').addEventListener('input', updateActivarBtn);

    function activarPago() {
        var referencia = document.getElementById('input_referencia').value.trim();
        if (!referencia || referencia.length < 6) {
            alert('Ingresa una referencia valida (min. 6 caracteres).');
            return;
        }
        if (!selectedMetodo || !selectedBanco) {
            alert('Selecciona un metodo de pago.');
            return;
        }

        if (selectedMetodo === 'Zelle') {
            var montoZelle = document.getElementById('input_zelle_monto').value;
            if (!montoZelle || parseFloat(montoZelle) <= 0) {
                alert('Ingresa el monto en USD para Zelle.');
                return;
            }
            document.getElementById('input_monto_usd').value = parseFloat(montoZelle).toFixed(2);
            document.getElementById('input_monto_usd_real').value = parseFloat(montoZelle).toFixed(2);
            document.getElementById('paymentForm').submit();
            document.getElementById('loadingOverlay').style.display = 'flex';
            return;
        }

        if (selectedBanco.api_config && selectedBanco.api_config.habilitada) {
            verificarBDV(referencia);
        } else {
            document.getElementById('paymentForm').submit();
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
    }

    function verificarBDV(referencia) {
        document.getElementById('loadingOverlay').style.display = 'flex';

        var params = new URLSearchParams();
        params.append('csrf_token', csrfToken);
        params.append('id_banco', selectedBanco.id_banco);
        params.append('referencia', referencia);
        params.append('fecha_pago', '<?php echo date('Y-m-d'); ?>');
        params.append('metodo_pago', selectedMetodo);
        params.append('id_contrato', idContrato);

        fetch('api_verificar_pago.php', {method:'POST', body:params})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('loadingOverlay').style.display = 'none';
                if (data.status === 'verified') {
                    verificacionData = data;
                    mostrarResultado(data);
                } else if (data.status === 'manual') {
                    document.getElementById('paymentForm').submit();
                    document.getElementById('loadingOverlay').style.display = 'flex';
                } else {
                    alert(data.message || 'Error al verificar el pago.');
                }
            })
            .catch(function(e) {
                document.getElementById('loadingOverlay').style.display = 'none';
                alert('Error de conexion. Intenta de nuevo.');
            });
    }

    function mostrarResultado(data) {
        var mov = data.movimiento || {};
        var tbody = document.getElementById('resultado_body');
        tbody.innerHTML = '';
        var rows = [
            ['Referencia Bancaria', mov.referencia_banco || data.referencia],
            ['Importe en Bs', 'Bs ' + (mov.importe_bs || data.monto_bs).toLocaleString('es-VE', {minimumFractionDigits:2})],
            ['Importe en USD', '$' + (mov.importe_usd || data.monto_usd).toFixed(2)],
            ['Tipo de Movimiento', mov.tipo_movimiento || 'CREDITO'],
            ['Observacion', mov.observacion || '-'],
            ['Fecha', mov.fecha || '<?php echo date('Y-m-d'); ?>']
        ];
        rows.forEach(function(r) {
            tbody.innerHTML += '<tr><td class="text-muted">' + r[0] + '</td><td class="fw-bold">' + r[1] + '</td></tr>';
        });
        document.getElementById('descripcion_pago').innerHTML = data.descripcion || '';
        document.getElementById('panel-resultado').classList.remove('d-none');
        document.getElementById('btn_activar').classList.add('d-none');
        document.getElementById('input_monto_usd_real').value = data.monto_usd || 0;
        document.getElementById('input_verificacion_data').value = JSON.stringify(data);
    }

    function ocultarResultado() {
        document.getElementById('panel-resultado').classList.add('d-none');
        document.getElementById('btn_activar').classList.remove('d-none');
        document.getElementById('btn_activar').disabled = false;
        verificacionData = null;
    }

    function confirmarPago() {
        if (!verificacionData) return;
        document.getElementById('input_monto_usd').value = verificacionData.monto_usd || 0;
        document.getElementById('input_monto_usd_real').value = verificacionData.monto_usd || 0;
        document.getElementById('paymentForm').submit();
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    document.getElementById('paymentForm').addEventListener('submit', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });

    updateActivarBtn();
    </script>

    <style>
        @keyframes loadingSpin { to { transform: rotate(360deg); } }
        .table-premium td, .table-premium th { padding: 12px 16px; }
        #loadingOverlay { display:none; }

        .pago-input-top {
            width: 100%;
            background: var(--input-bg);
            border: 1.5px solid var(--border-glass);
            border-radius: 12px;
            padding: 14px 16px;
            color: var(--text-main);
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 1px;
            outline: none;
        }

        .pago-monto-panel { overflow: hidden; }
        .pago-monto-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 4px 0;
        }
        .pago-monto-header small { display: block; margin-bottom: 2px; }
        .pago-monto-bs {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, #f59e0b, #f97316);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .pago-monto-chevron { color: var(--text-muted); transition: transform 0.3s ease; font-size: 1.1rem; }
        .pago-monto-body {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-glass);
        }
        .pago-monto-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 0.9rem;
        }

        .metodo-btns-grid { display: flex; gap: 10px; }
        .metodo-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 16px 8px;
            border-radius: 12px;
            border: 2px solid var(--border-glass);
            background: rgba(255,255,255,0.04);
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .metodo-btn i { font-size: 1.4rem; }
        .metodo-btn:hover {
            border-color: rgba(59,130,246,0.4);
            color: var(--primary);
            transform: translateY(-1px);
        }
        .metodo-btn.active {
            border-color: var(--primary);
            background: rgba(59,130,246,0.12);
            color: var(--primary);
            box-shadow: 0 0 0 1px rgba(59,130,246,0.25);
        }

        .banco-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(59,130,246,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        .banco-detail-row {
            padding: 8px 0;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 1px solid var(--border-glass);
            color: var(--text-main);
        }
        .banco-detail-row:last-child { border-bottom: none; }

        .btn-activar {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-activar:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.3);
        }
        .btn-activar:disabled { opacity: 0.5; cursor: not-allowed; }

        .label-premium {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .glass-input {
            background: var(--input-bg);
            border: 1.5px solid var(--border-glass);
            border-radius: 10px;
            padding: 12px 14px;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .glass-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
        }
        .btn-glass {
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-glass:hover {
            background: rgba(59,130,246,0.12);
            border-color: var(--primary);
            color: var(--primary);
        }
    </style>
</body>
</html>
