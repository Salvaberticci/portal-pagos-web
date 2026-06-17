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
if (!empty($usuario_ws)) {
    $ultimo_pago = $wispClient->getLastPaidInvoice($usuario_ws);
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

        <?php if (!empty($pago_exito)): ?>
            <div class="alert alert-success glass-panel mb-4"><?php echo $pago_exito; ?></div>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i> Recibos Pendientes</h5>
                        <?php if (count($invoices) > 0): ?>
                        <small class="text-muted"><?php echo count($invoices); ?> recibo<?php echo count($invoices) > 1 ? 's' : ''; ?> por pagar. Selecciona uno para pagar.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (count($invoices) > 0): ?>
                <div class="recibos-list">
                    <?php foreach ($invoices as $i => $inv):
                        $inv_id       = $inv['id'] ?? $inv['id_factura'] ?? 0;
                        $inv_monto    = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
                        $inv_monto_bs = $inv_monto * $tasa_bcv;
                        $descripcion  = '';
                        if (!empty($inv['articulos'][0]['descripcion'])) {
                            $desc_full   = $inv['articulos'][0]['descripcion'];
                            $descripcion = explode("\n", $desc_full)[0];
                            if (strlen($descripcion) > 55) $descripcion = substr($descripcion, 0, 55) . '...';
                        }
                        if (!$descripcion) $descripcion = 'Recibo N° ' . $inv_id;
                        $fecha_emi  = $inv['fecha_emision'] ?? '';
                        $fecha_venc = $inv['fecha_vencimiento'] ?? '';
                        $vencida    = $fecha_venc && strtotime($fecha_venc) < time();
                    ?>
                    <!-- Card recibo -->
                    <div class="recibo-card <?php echo $vencida ? 'recibo-vencida' : ''; ?>"
                          onclick="toggleRecibo(this, <?php echo $inv_id; ?>)">

                        <!-- Checkbox oculto funcional -->
                        <input type="checkbox" name="invoice_ids[]" value="<?php echo $inv_id; ?>"
                               class="invoice-check visually-hidden"
                               onchange="event.stopPropagation(); recalcTotal();">

                        <!-- Icono tipo factura -->
                        <div class="recibo-icon-wrap">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>

                        <!-- Cuerpo de la card -->
                        <div class="recibo-body">
                            <div class="recibo-top">
                                <div class="recibo-info">
                                    <span class="recibo-num">Recibo #<?php echo $inv_id; ?></span>
                                    <?php if ($fecha_emi): ?>
                                    <span class="recibo-fecha"><i class="fas fa-calendar-alt me-1"></i><?php echo date('d M Y', strtotime($fecha_emi)); ?></span>
                                    <?php endif; ?>
                                    <?php if ($vencida): ?>
                                    <span class="recibo-badge-vencida"><i class="fas fa-exclamation-triangle me-1"></i>Vencida</span>
                                    <?php endif; ?>
                                </div>
                                <div class="recibo-montos">
                                    <span class="recibo-usd">$<?php echo number_format($inv_monto, 2); ?></span>
                                    <span class="recibo-bs">Bs <?php echo number_format($inv_monto_bs, 2, ',', '.'); ?></span>
                                    <button type="button" class="recibo-select-btn" onclick="event.stopPropagation(); toggleRecibo(this.closest('.recibo-card'), <?php echo $inv_id; ?>);">
                                        <i class="fas fa-check-circle me-1"></i> Seleccionar este
                                    </button>
                                </div>
                            </div>
                            <div class="recibo-desc"><?php echo htmlspecialchars($descripcion); ?></div>
                        </div>

                        <!-- Barra de acento inferior -->
                        <div class="recibo-accent-bar"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3" style="font-size:3rem;">🎉</div>
                    <h6 class="fw-bold text-success">¡Sin deudas pendientes!</h6>
                    <p class="text-muted mb-0 small">No tienes recibos pendientes de pago.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Método de Pago (Dropdown) -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-credit-card me-2 text-primary"></i> Método de Pago</h5>
                <div class="row">
                    <div class="col-md-6">
                        <label class="label-premium">Selecciona método de pago</label>
                        <select id="select_metodo" class="form-select glass-input" onchange="selectMetodo(this.value)">
                            <option value="">-- Seleccionar --</option>
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
                            <option value="<?php echo $met; ?>"><?php echo $met; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="banco_select_group">
                        <label class="label-premium">Banco destino</label>
                        <select id="select_banco" class="form-select glass-input" onchange="selectBanco(this.value)">
                            <option value="">-- Seleccionar banco --</option>
                        </select>
                    </div>
                </div>

                <!-- Datos del Banco Destino (dentro del mismo panel) -->
                <div class="d-none" id="panel-banco">
                    <hr class="my-3 border-white border-opacity-10">
                    <h6 class="fw-bold mb-2"><i class="fas fa-university me-2 text-primary"></i> <span id="banco_nombre"></span></h6>
                    <div id="banco_detalles"></div>
                </div>
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
                <button type="button" class="btn btn-premium flex-fill py-3" id="btn_verificar" onclick="verificarPago()" disabled>
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
    const reciboSel = <?php echo $recibo_id_sel; ?>;
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

    function toggleRecibo(card, invId) {
        const cb = card.querySelector('.invoice-check');
        // Si ya está seleccionado, no hacemos nada (no se puede deseleccionar)
        if (cb.checked) return;

        // Deseleccionar todos los demás
        document.querySelectorAll('.invoice-check:checked').forEach(other => {
            other.checked = false;
            const otherCard = other.closest('.recibo-card');
            if (otherCard) {
                otherCard.classList.remove('selected');
                const otherBtn = otherCard.querySelector('.recibo-select-btn');
                if (otherBtn) otherBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Seleccionar este';
            }
        });

        // Seleccionar este
        cb.checked = true;
        card.classList.add('selected');
        const btn = card.querySelector('.recibo-select-btn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Seleccionado';
        }
        recalcTotal();
    }

    function recalcTotal() {
        let total = 0;
        const checks = document.querySelectorAll('.invoice-check:checked');
        checks.forEach(cb => {
            const card = cb.closest('.recibo-card');
            if (card) {
                card.classList.add('selected');
                const btn = card.querySelector('.recibo-select-btn');
                if (btn) btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Seleccionado';
            }
            const montoEl = card ? card.querySelector('.recibo-usd') : null;
            if (montoEl) {
                total += parseFloat(montoEl.textContent.replace('$', '').replace(',', '')) || 0;
            }
        });
        document.querySelectorAll('.invoice-check:not(:checked)').forEach(cb => {
            const card = cb.closest('.recibo-card');
            if (card) {
                card.classList.remove('selected');
                const btn = card.querySelector('.recibo-select-btn');
                if (btn) btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Seleccionar este';
            }
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

    function selectMetodo(metodo) {
        selectedMetodo = metodo;
        document.getElementById('input_metodo').value = metodo;

        const filtrados = todosLosBancos.filter(b => (b.metodos_pago || []).includes(metodo) && b.activo !== false);
        const bancoGroup = document.getElementById('banco_select_group');
        const selectBanco = document.getElementById('select_banco');

        if (filtrados.length > 0) {
            bancoGroup.classList.remove('d-none');
            selectBanco.innerHTML = '<option value="">-- Seleccionar banco --</option>';
            filtrados.forEach(b => {
                selectBanco.innerHTML += '<option value="' + b.id_banco + '">' + b.nombre_banco + '</option>';
            });
            if (filtrados.length === 1) {
                selectBanco.value = filtrados[0].id_banco;
                selectBanco(filtrados[0].id_banco);
            } else {
                document.getElementById('panel-banco').classList.add('d-none');
                selectedBanco = null;
                document.getElementById('input_banco').value = '';
            }
        } else {
            bancoGroup.classList.add('d-none');
            document.getElementById('panel-banco').classList.add('d-none');
            selectedBanco = null;
            document.getElementById('input_banco').value = '';
        }

        if (metodo === 'Zelle') {
            document.getElementById('monto_manual_group').classList.remove('d-none');
            document.getElementById('btn_verificar').classList.add('d-none');
            document.getElementById('btn_submit_zelle').classList.remove('d-none');
            document.getElementById('btn_confirmar').classList.add('d-none');
        } else {
            document.getElementById('monto_manual_group').classList.add('d-none');
            document.getElementById('btn_verificar').classList.remove('d-none');
            document.getElementById('btn_submit_zelle').classList.add('d-none');
            document.getElementById('btn_confirmar').classList.add('d-none');
        }
        ocultarResultado();
    }

    function selectBanco(bancoId) {
        const bank = todosLosBancos.find(b => b.id_banco === bancoId);
        if (!bank) {
            document.getElementById('panel-banco').classList.add('d-none');
            selectedBanco = null;
            document.getElementById('input_banco').value = '';
            return;
        }
        selectedBanco = bank;
        mostrarBanco(bank);
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
            alert('Selecciona un método de pago y banco destino.');
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
        document.getElementById('input_monto_usd').value = verificacionData.monto_usd || 0;
        document.getElementById('input_monto_usd_real').value = verificacionData.monto_usd || 0;
        document.getElementById('paymentForm').submit();
    }

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
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

        /* ── Recibos: lista de cards ── */
        .recibos-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .recibo-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 16px;
            border: 1.5px solid var(--border-glass);
            background: var(--glass-bg);
            cursor: pointer;
            transition: all 0.25s ease;
            overflow: hidden;
        }
        .recibo-card:hover {
            border-color: rgba(59,130,246,0.5);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.12);
        }
        .recibo-card.selected {
            border-color: #22c55e;
            background: rgba(34,197,94,0.06);
            box-shadow: 0 0 0 1px rgba(34,197,94,0.25), 0 4px 16px rgba(34,197,94,0.1);
        }
        .recibo-card.recibo-vencida {
            border-color: rgba(239,68,68,0.4);
        }
        .recibo-card.recibo-vencida.selected {
            border-color: #22c55e;
            background: rgba(34,197,94,0.06);
            box-shadow: 0 0 0 1px rgba(34,197,94,0.25), 0 4px 16px rgba(34,197,94,0.1);
        }
        /* Barra de acento inferior */
        .recibo-accent-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #22c55e, #16a34a);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
            border-radius: 0 0 16px 16px;
        }
        .recibo-card.selected .recibo-accent-bar {
            transform: scaleX(1);
        }
        .recibo-card.recibo-vencida .recibo-accent-bar {
            background: linear-gradient(90deg, #ef4444, #f97316);
        }
        /* Icono de la card */
        .recibo-icon-wrap {
            flex-shrink: 0;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(34,197,94,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #22c55e;
            transition: background 0.25s;
        }
        .recibo-card.selected .recibo-icon-wrap {
            background: rgba(34,197,94,0.18);
        }
        .recibo-card.recibo-vencida .recibo-icon-wrap {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
        }
        /* Cuerpo */
        .recibo-body {
            flex: 1;
            min-width: 0;
        }
        .recibo-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 4px;
        }
        .recibo-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .recibo-num {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        .recibo-fecha {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .recibo-badge-vencida {
            font-size: 0.7rem;
            font-weight: 600;
            color: #ef4444;
            background: rgba(239,68,68,0.1);
            padding: 1px 7px;
            border-radius: 20px;
            display: inline-block;
        }
        .recibo-montos {
            text-align: right;
            flex-shrink: 0;
        }
        .recibo-usd {
            display: block;
            font-size: 1.15rem;
            font-weight: 800;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .recibo-bs {
            display: block;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 1px;
        }
        .recibo-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Botón "Seleccionar este" en cada card */
        .recibo-select-btn {
            display: block;
            margin-top: 6px;
            padding: 3px 10px;
            font-size: 0.72rem;
            font-weight: 600;
            border: 1px solid #22c55e;
            border-radius: 20px;
            background: transparent;
            color: #22c55e;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .recibo-select-btn:hover {
            background: rgba(34,197,94,0.12);
        }
        .recibo-card.selected .recibo-select-btn {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }
        /* Botón seleccionar todos */
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
