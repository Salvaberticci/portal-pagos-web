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

$wisp_service_id = isset($_GET['id_contrato']) ? $_GET['id_contrato'] : '';
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

$invoices = $wispClient->getPendingInvoices($wisp_service_id);
$deuda_total = 0;
foreach ($invoices as $inv) {
    $deuda_total += floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
}

$monto_plan = floatval($c_perfil['plan_internet_precio'] ?? 0);
if ($monto_plan <= 0 && count($invoices) > 0) {
    $monto_plan = floatval($invoices[0]['monto'] ?? 0);
}
if ($monto_plan <= 0) $monto_plan = 15.0;

$usuario_ws = $c_perfil['usuario'] ?? '';

$ultimo_pago = null;
$paid_receipts = [];
if (!empty($usuario_ws)) {
    $ultimo_pago = $wispClient->getLastPaidInvoice($usuario_ws);
    $paid_receipts = $wispClient->getInvoices([
        'estado'  => 2,
        'cliente' => $usuario_ws,
        'limit'   => 10,
    ]);
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

if ($cedula === TEST_USER_CEDULA) {
    $deuda_total = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
    $monto_plan = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
}

$json_bancos = @file_get_contents('../paginas/principal/bancos.json');
$bancosArr = json_decode($json_bancos, true) ?: [];

$estado_ws = strtoupper($c_perfil['estado'] ?? 'ACTIVO');
if ($estado_ws === 'ACTIVE') $estado_ws = 'ACTIVO';
if ($estado_ws === 'SUSPENDED') $estado_ws = 'SUSPENDIDO';
$badge_class = $estado_ws === 'ACTIVO' ? 'status-active' : 'status-suspended';
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
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

    <div class="container main-container animate-fade py-4">

        <?php if (!empty($pago_err)): ?>
            <div class="alert alert-danger glass-panel mb-4"><?php echo $pago_err; ?></div>
        <?php endif; ?>

        <?php if ($cedula === TEST_USER_CEDULA): ?>
            <div class="alert alert-info glass-panel mb-4 text-center">
                <i class="fas fa-info-circle me-2"></i> Modo de prueba: montos demo.
            </div>
        <?php endif; ?>

        <!-- Cabecera del Cliente -->
        <div class="glass-panel p-4 mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted d-block">Cliente</small>
                    <span class="fw-bold"><?php echo htmlspecialchars($c_perfil['nombre'] ?? $_SESSION['cliente_nombre']); ?></span>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Estado</small>
                    <span class="status-badge <?php echo $badge_class; ?>"><?php echo $estado_ws; ?></span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Email</small>
                    <span><?php echo htmlspecialchars($c_perfil['correo'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Teléfono</small>
                    <span><?php echo htmlspecialchars($c_perfil['telefono'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Zona</small>
                    <span><?php echo htmlspecialchars($c_perfil['zona']['nombre'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Dirección</small>
                    <span><?php echo htmlspecialchars($c_perfil['direccion'] ?? 'N/A'); ?></span>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Plan</small>
                    <span class="fw-bold"><?php echo htmlspecialchars($c_perfil['plan_internet_nombre'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <?php if ($ultimo_pago): ?>
            <div class="row mt-3 pt-3 border-top border-white border-opacity-10">
                <div class="col-12">
                    <div class="ultimo-pago-card glass-panel p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted d-block"><i class="fas fa-check-circle text-success me-1"></i> Último Pago</small>
                            <span class="fw-bold">$<?php echo number_format($ultimo_pago['monto'], 2); ?></span>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Fecha</small>
                            <span class="fw-bold"><?php echo date('d/m/Y', strtotime($ultimo_pago['fecha_pago'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <form id="paymentForm" action="procesar_pago_cliente.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="id_contrato" value="<?php echo $wisp_service_id; ?>">
            <input type="hidden" name="tasa_dolar" value="<?php echo $tasa_bcv; ?>">
            <input type="hidden" name="monto_usd" id="input_monto_usd" value="0">
            <input type="hidden" name="metodo_pago" id="input_metodo" value="">
            <input type="hidden" name="id_banco_destino" id="input_banco" value="">
            <input type="hidden" name="monto_usd_real" id="input_monto_usd_real" value="0">
            <input type="hidden" name="verificacion_data" id="input_verificacion_data" value="">
            <input type="hidden" name="meses_adelanto" value="0">

            <!-- Recibos Pendientes -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-file-invoice me-2 text-primary"></i> Recibos Pendientes</h5>
                <?php if (count($invoices) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-premium mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px"><input type="checkbox" id="check_all" checked onchange="toggleAll(this)"></th>
                                <th>N° Recibo</th>
                                <th>Período</th>
                                <th class="text-end">Monto USD</th>
                                <th class="text-end">Monto Bs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $i => $inv): 
                                $inv_id = $inv['id'] ?? $inv['id_factura'] ?? 0;
                                $inv_monto = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
                                $inv_periodo = ($inv['fecha_emision'] ?? '') . ' al ' . ($inv['fecha_vencimiento'] ?? '');
                            ?>
                            <tr>
                                <td><input type="checkbox" name="invoice_ids[]" value="<?php echo $inv_id; ?>" class="invoice-check" checked onchange="recalcTotal()"></td>
                                <td class="fw-bold"><?php echo $inv_id; ?></td>
                                <td><?php echo htmlspecialchars($inv_periodo); ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($inv_monto, 2); ?></td>
                                <td class="text-end text-ves">Bs <?php echo number_format($inv_monto * $tasa_bcv, 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p class="mb-0">No tienes recibos pendientes.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recibos Pagados -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-check-circle text-success me-2"></i> Recibos Pagados</h5>
                <?php if (count($paid_receipts) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-premium mb-0">
                        <thead>
                            <tr>
                                <th>N° Recibo</th>
                                <th>Período</th>
                                <th>Fecha Pago</th>
                                <th class="text-end">Monto USD</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_pagado = 0;
                            foreach ($paid_receipts as $pr): 
                                $pr_id = $pr['id_factura'] ?? $pr['folio'] ?? '-';
                                $pr_monto = floatval($pr['total_cobrado'] ?? $pr['total'] ?? 0);
                                $total_pagado += $pr_monto;
                                $pr_fecha_pago = date('d/m/Y', strtotime($pr['fecha_pago'] ?? 'now'));
                                $pr_periodo = ($pr['fecha_emision'] ?? '') . ' al ' . ($pr['fecha_vencimiento'] ?? '');
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo $pr_id; ?></td>
                                <td><?php echo htmlspecialchars($pr_periodo); ?></td>
                                <td><?php echo $pr_fecha_pago; ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($pr_monto, 2); ?></td>
                                <td><?php echo htmlspecialchars($pr['referencia'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end text-success">Total Pagado</td>
                                <td class="text-end text-success">$<?php echo number_format($total_pagado, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <p class="text-muted mb-0">No hay recibos pagados aún.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Total a Pagar -->
            <div class="glass-panel p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted d-block">Tasa BCV</small>
                        <span class="fw-bold">Bs <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Total a Pagar</small>
                        <span class="fs-4 fw-bold text-gradient" id="total_usd">$0.00</span>
                        <span class="d-block text-ves" id="total_bs">Bs 0,00</span>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress" style="height:4px;">
                        <div class="progress-bar bg-primary" id="progress_bar" style="width:0%"></div>
                    </div>
                </div>
            </div>

            <!-- Método de Pago -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-credit-card me-2 text-primary"></i> Método de Pago</h5>
                <div class="row g-2">
                    <?php 
                    $metodos_unicos = [];
                    foreach ($bancosArr as $b) {
                        if ($b['activo'] !== false) {
                            foreach ($b['metodos_pago'] as $m) {
                                $metodos_unicos[$m] = true;
                            }
                        }
                    }
                    $orden = ['Pago Móvil', 'Transferencia', 'Zelle'];
                    foreach ($orden as $met): if (!isset($metodos_unicos[$met])) continue; ?>
                    <div class="col-md-4">
                        <div class="selection-card metodo-card py-3 px-3 text-center" data-metodo="<?php echo $met; ?>" onclick="selectMetodo('<?php echo $met; ?>', this)">
                            <div class="d-flex flex-column align-items-center">
                                <div class="selection-icon mb-2" style="width:44px;height:44px;font-size:1.2rem;">
                                    <i class="fas <?php echo $met === 'Pago Móvil' ? 'fa-mobile-alt' : ($met === 'Transferencia' ? 'fa-university' : 'fa-bolt'); ?>"></i>
                                </div>
                                <span class="fw-bold small"><?php echo $met; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Datos del Banco Destino -->
            <div class="glass-panel p-4 mb-4 d-none" id="panel-banco">
                <h5 class="fw-bold mb-3"><i class="fas fa-university me-2 text-primary"></i> <span id="banco_nombre"></span></h5>
                <div id="banco_detalles" class="mb-3"></div>
            </div>

            <!-- Datos del Reporte -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-pen me-2 text-primary"></i> Datos del Reporte</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="label-premium">Fecha de Operación</label>
                        <input type="date" name="fecha_pago" class="form-control glass-input" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="label-premium">Número de Referencia</label>
                        <input type="text" name="referencia" class="form-control glass-input" placeholder="Últimos 6 dígitos o completo" required>
                    </div>
                    <div class="col-md-4 d-none" id="monto_manual_group">
                        <label class="label-premium">Monto en USD</label>
                        <input type="number" step="0.01" min="0.01" name="monto_manual_usd" class="form-control glass-input" placeholder="0.00">
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="d-flex gap-2 mb-4">
                <button type="button" class="btn btn-premium flex-fill py-3" id="btn_verificar" onclick="verificarPago()">
                    <i class="fas fa-search me-2"></i> Verificar Pago
                </button>
                <button type="button" class="btn btn-glass flex-fill py-3 d-none" id="btn_confirmar" onclick="confirmarPago()">
                    <i class="fas fa-check-circle me-2"></i> Confirmar Pago
                </button>
                <button type="submit" class="btn btn-premium flex-fill py-3 d-none" id="btn_submit_zelle">
                    <i class="fas fa-check-circle me-2"></i> Confirmar Pago
                </button>
            </div>

            <!-- Resultado de Verificación -->
            <div class="glass-panel p-4 mb-4 d-none" id="panel-resultado">
                <h5 class="fw-bold mb-3 text-success"><i class="fas fa-check-circle me-2"></i> Datos de Reporte</h5>
                <div class="table-responsive">
                    <table class="table table-premium mb-0">
                        <tbody id="resultado_body"></tbody>
                    </table>
                </div>
                <div class="mt-3 p-3 rounded" style="background:rgba(16,185,129,0.08);border-left:4px solid var(--success);" id="descripcion_pago"></div>
            </div>
        </form>
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
    let selectedMetodo = '';
    let selectedBanco = null;
    let verificacionData = null;

    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    document.getElementById('themeToggleBtn').addEventListener('click', function() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        this.querySelector('i').className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });

    function toggleAll(master) {
        document.querySelectorAll('.invoice-check').forEach(cb => cb.checked = master.checked);
        recalcTotal();
    }

    function recalcTotal() {
        let total = 0;
        const checks = document.querySelectorAll('.invoice-check:checked');
        checks.forEach(cb => {
            const row = cb.closest('tr');
            const txt = row.querySelector('td:nth-child(4)').textContent.replace('$', '').replace(',', '');
            total += parseFloat(txt) || 0;
        });
        document.getElementById('total_usd').textContent = '$' + total.toFixed(2);
        document.getElementById('total_bs').textContent = 'Bs ' + (total * tasaBcv).toLocaleString('es-VE', {minimumFractionDigits: 2});
        document.getElementById('input_monto_usd').value = total.toFixed(2);

        const maxTotal = <?php echo $deuda_total; ?>;
        const pct = maxTotal > 0 ? Math.min(100, (total / maxTotal) * 100) : 0;
        document.getElementById('progress_bar').style.width = pct + '%';

        document.getElementById('btn_verificar').disabled = total <= 0;
        ocultarResultado();
    }

    function selectMetodo(metodo, el) {
        selectedMetodo = metodo;
        document.getElementById('input_metodo').value = metodo;
        document.querySelectorAll('.metodo-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');

        const filtrados = todosLosBancos.filter(b => (b.metodos_pago || []).includes(metodo) && b.activo !== false);
        if (filtrados.length > 0) {
            selectedBanco = filtrados[0];
            mostrarBanco(selectedBanco);
        }

        // Para Zelle, mostrar campo de monto manual
        if (metodo === 'Zelle') {
            document.getElementById('monto_manual_group').classList.remove('d-none');
            document.getElementById('btn_verificar').classList.add('d-none');
            document.getElementById('btn_submit_zelle').classList.remove('d-none');
            document.getElementById('btn_confirmar').classList.add('d-none');
            document.getElementById('panel-resultado').classList.add('d-none');
        } else {
            document.getElementById('monto_manual_group').classList.add('d-none');
            document.getElementById('btn_verificar').classList.remove('d-none');
            document.getElementById('btn_submit_zelle').classList.add('d-none');
            document.getElementById('btn_confirmar').classList.add('d-none');
        }
        ocultarResultado();
    }

    function mostrarBanco(bank) {
        document.getElementById('panel-banco').classList.remove('d-none');
        document.getElementById('banco_nombre').textContent = bank.nombre_banco;
        document.getElementById('input_banco').value = bank.id_banco;

        const div = document.getElementById('banco_detalles');
        div.innerHTML = '';

        let lines = [];
        if (selectedMetodo === 'Pago Móvil') {
            lines = [
                {label:'Teléfono', val:bank.numero_cuenta},
                {label:'Cédula/RIF', val:bank.cedula_propietario},
            ];
        } else if (selectedMetodo === 'Transferencia') {
            lines = [
                {label:'N° Cuenta', val:bank.numero_cuenta},
                {label:'Titular', val:bank.nombre_propietario},
                {label:'RIF', val:bank.cedula_propietario},
            ];
        } else if (selectedMetodo === 'Zelle') {
            lines = [
                {label:'Correo', val:bank.numero_cuenta},
                {label:'Titular', val:bank.nombre_propietario},
            ];
        }

        // Boton copiar todos los datos
        const copyBtn = document.createElement('button');
        copyBtn.className = 'btn btn-sm btn-glass copy-btn mb-3';
        copyBtn.innerHTML = '<i class="far fa-copy me-1"></i> Copiar Todos los Datos';
        copyBtn.onclick = function () {
            const text = lines.map(l => l.label + ': ' + l.val).join('\n');
            navigator.clipboard.writeText(text).then(() => {
                const orig = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check me-1"></i> Copiado';
                this.classList.add('text-success');
                setTimeout(() => { this.innerHTML = orig; this.classList.remove('text-success'); }, 2000);
            });
        };
        const wrap = document.createElement('div');
        wrap.className = 'text-end';
        wrap.appendChild(copyBtn);
        div.appendChild(wrap);

        lines.forEach(l => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center mb-2';
            row.innerHTML = '<div><small class="text-muted">' + l.label + '</small><div class="fw-bold">' + l.val + '</div></div>';
            div.appendChild(row);
        });
    }

    function verificarPago() {
        const fecha = document.querySelector('input[name="fecha_pago"]').value;
        const referencia = document.querySelector('input[name="referencia"]').value.trim();
        const montoUsd = document.getElementById('input_monto_usd').value;

        if (!fecha || !referencia || referencia.length < 6) {
            alert('Completa la fecha y la referencia (min. 6 caracteres).');
            return;
        }
        if (!selectedMetodo || !selectedBanco) {
            alert('Selecciona un método de pago.');
            return;
        }

        const checks = document.querySelectorAll('.invoice-check:checked');
        if (checks.length === 0) {
            alert('Selecciona al menos un recibo.');
            return;
        }

        const invoiceIds = Array.from(checks).map(cb => cb.value);
        document.getElementById('loadingOverlay').style.display = 'flex';

        const params = new URLSearchParams();
        params.append('csrf_token', csrfToken);
        params.append('id_banco', selectedBanco.id_banco);
        params.append('referencia', referencia);
        params.append('fecha_pago', fecha);
        params.append('metodo_pago', selectedMetodo);
        params.append('id_contrato', idContrato);
        invoiceIds.forEach(id => params.append('invoice_ids[]', id));

        fetch('api_verificar_pago.php', {method:'POST', body:params})
            .then(r => r.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                if (data.status === 'verified') {
                    verificacionData = data;
                    mostrarResultado(data);
                } else if (data.status === 'manual') {
                    alert('Este método requiere verificación manual. Usa "Confirmar Pago" para reportar.');
                } else {
                    alert(data.message || 'Error al verificar el pago.');
                }
            })
            .catch(e => {
                document.getElementById('loadingOverlay').style.display = 'none';
                alert('Error de conexión. Intenta de nuevo.');
            });
    }

    function mostrarResultado(data) {
        const mov = data.movimiento || {};
        const tbody = document.getElementById('resultado_body');
        tbody.innerHTML = '';
        const rows = [
            ['Referencia Bancaria', mov.referencia_banco || data.referencia],
            ['Importe en Bs', 'Bs ' + (mov.importe_bs || data.monto_bs).toLocaleString('es-VE', {minimumFractionDigits:2})],
            ['Importe en USD', '$' + (mov.importe_usd || data.monto_usd).toFixed(2)],
            ['Tipo de Movimiento', mov.tipo_movimiento || 'CRÉDITO'],
            ['Observación', mov.observacion || '-'],
            ['Fecha', mov.fecha || document.querySelector('input[name="fecha_pago"]').value],
        ];
        rows.forEach(r => {
            tbody.innerHTML += `<tr><td class="text-muted">${r[0]}</td><td class="fw-bold">${r[1]}</td></tr>`;
        });

        document.getElementById('descripcion_pago').innerHTML = data.descripcion || '';
        document.getElementById('panel-resultado').classList.remove('d-none');
        document.getElementById('btn_verificar').classList.add('d-none');
        document.getElementById('btn_confirmar').classList.remove('d-none');

        document.getElementById('input_monto_usd_real').value = data.monto_usd || 0;
        document.getElementById('input_verificacion_data').value = JSON.stringify(data);
    }

    function ocultarResultado() {
        document.getElementById('panel-resultado').classList.add('d-none');
        document.getElementById('btn_verificar').classList.remove('d-none');
        document.getElementById('btn_confirmar').classList.add('d-none');
        verificacionData = null;
    }

    function confirmarPago() {
        if (!verificacionData) return;
        // Setear montos reales del banco antes de submit
        document.getElementById('input_monto_usd').value = verificacionData.monto_usd || 0;
        document.getElementById('input_monto_usd_real').value = verificacionData.monto_usd || 0;
        document.getElementById('paymentForm').submit();
    }

    // Zelle: submit directo sin verificacion
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        // Si es Zelle, validar monto manual
        if (selectedMetodo === 'Zelle') {
            const manual = document.querySelector('input[name="monto_manual_usd"]');
            if (!manual || !manual.value || parseFloat(manual.value) <= 0) {
                e.preventDefault();
                alert('Ingresa el monto en USD para Zelle.');
                return;
            }
            document.getElementById('input_monto_usd').value = parseFloat(manual.value).toFixed(2);
            document.getElementById('input_monto_usd_real').value = parseFloat(manual.value).toFixed(2);
        }
        document.getElementById('loadingOverlay').style.display = 'flex';
    });

    recalcTotal();
    </script>

    <style>
        @keyframes loadingSpin { to { transform: rotate(360deg); } }
        .progress { background: var(--border-glass); border-radius: 2px; }
        .progress-bar { border-radius: 2px; transition: width 0.3s ease; }
        .table-premium td, .table-premium th { padding: 12px 16px; }
        input[type="checkbox"] { transform: scale(1.2); cursor: pointer; }
        #loadingOverlay { display:none; }
    </style>
</body>
</html>
