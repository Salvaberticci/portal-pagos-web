<?php
require_once 'security_helper.php';
enforce_https();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

$pago_err = $_SESSION['pago_err'] ?? '';
unset($_SESSION['pago_err']);

$pago_exito = $_SESSION['pago_msg'] ?? $_SESSION['pago_exito'] ?? '';
$pago_data = $_SESSION['pago_data'] ?? null;
unset($_SESSION['pago_msg']);
unset($_SESSION['pago_exito']);
unset($_SESSION['pago_data']);

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
if (DEV_MODE && $cedula === TEST_USER_CEDULA) {
    require_once __DIR__ . '/../src/Services/WispHubDevModeClient.php';
    $wispClient = new \Services\WispHubDevModeClient($wispConfig);
} else {
    $wispClient = new \Services\WispHubClient($wispConfig);
}

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

$invoices = $wispClient->getPendingInvoices($wisp_service_id);
$deuda_total = 0;
$invoices_json = [];
foreach ($invoices as $inv) {
    $id = $inv['id'] ?? $inv['id_factura'] ?? 0;
    $monto = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
    $deuda_total += $monto;
    $monto_bs = $monto * $tasa_bcv;
    $desc = '';
    if (!empty($inv['articulos'][0]['descripcion'])) {
        $desc = $inv['articulos'][0]['descripcion'];
    } elseif (!empty($inv['descripcion'])) {
        $desc = $inv['descripcion'];
    } else {
        $desc = 'Recibo N° ' . $id;
    }
    if (mb_strlen($desc) > 60) $desc = mb_substr($desc, 0, 60) . '...';
    $vencida = !empty($inv['fecha_vencimiento']) && strtotime($inv['fecha_vencimiento']) < time();
    $invoices_json[] = [
        'id' => $id,
        'folio' => $inv['folio'] ?? $id,
        'fecha_emision' => $inv['fecha_emision'] ?? '',
        'fecha_vencimiento' => $inv['fecha_vencimiento'] ?? '',
        'estado' => $inv['estado'] ?? 'Pendiente',
        'total' => floatval($inv['total'] ?? 0),
        'monto_pendiente' => $monto,
        'total_cobrado' => floatval($inv['total_cobrado'] ?? 0),
        'descripcion' => $desc,
        'monto_bs' => round($monto_bs, 2),
        'vencida' => $vencida,
        'preseleccionado' => ($recibo_id_sel > 0 && $id == $recibo_id_sel),
    ];
}

$saldo_favor = $wispClient->getClientBalance($wisp_service_id);
$monto_a_pagar = $deuda_total;

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
    <link rel="stylesheet" href="css/fontawesome/css/all.min.css">
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

        <div id="pago_mensajes" data-error="<?php echo htmlspecialchars($pago_err); ?>" data-exito="<?php echo htmlspecialchars($pago_exito); ?>" data-detalles='<?php echo $pago_data ? htmlspecialchars(json_encode($pago_data), ENT_QUOTES, 'UTF-8') : ''; ?>'></div>
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
            <div id="invoice_ids_container"></div>
            <input type="hidden" name="meses_adelanto" value="0">
            <input type="hidden" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>">

            <!-- Seleccionar Recibos -->
            <div class="glass-panel mb-3 recibo-select-panel p-0">
                <div class="recibo-select-header p-4 pb-0">
                    <h6 class="fw-bold mb-2">Seleccionar Recibos a Pagar</h6>
                    <small class="text-muted">Marca los recibos que deseas cancelar</small>
                </div>
                <div class="recibo-select-body" id="reciboBody">
                    <?php if (empty($invoices_json)): ?>
                    <div class="recibo-select-empty p-4 text-center text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                        No tienes recibos pendientes.
                    </div>
                    <?php else: ?>
                    <?php foreach ($invoices_json as $inv): ?>
                    <div class="recibo-select-item<?php echo $inv['vencida'] ? ' vencida' : ''; ?>" data-id="<?php echo $inv['id']; ?>">
                        <label class="recibo-select-label">
                            <input type="checkbox" class="recibo-select-check" data-id="<?php echo $inv['id']; ?>" onchange="toggleRecibo(this)"<?php echo $inv['preseleccionado'] ? ' checked' : ''; ?>>
                            <div class="recibo-select-content">
                                <div class="recibo-select-top">
                                    <span class="recibo-select-folio">Recibo #<?php echo htmlspecialchars($inv['folio']); ?></span>
                                    <span class="recibo-select-monto-bs">Bs <?php echo number_format($inv['monto_bs'], 2, ',', '.'); ?></span>
                                </div>
                                <div class="recibo-select-bottom">
                                    <span class="recibo-select-desc"><?php echo htmlspecialchars($inv['descripcion']); ?></span>
                                    <?php if ($inv['vencida']): ?>
                                    <span class="recibo-select-badge badge bg-danger ms-1">Vencida</span>
                                    <?php endif; ?>
                                    <?php if (!empty($inv['fecha_vencimiento'])): ?>
                                    <span class="recibo-select-fecha ms-1">Vence: <?php echo date('d/m/Y', strtotime($inv['fecha_vencimiento'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="recibo-total-bar p-4">
                    <div class="recibo-total-row">
                        <span class="text-muted">Total a Pagar:</span>
                        <span class="recibo-total-bs fw-bold" id="totalBS">Bs 0,00</span>
                    </div>
                    <div class="recibo-total-row">
                        <span class="text-muted">Equivalente en USD:</span>
                        <span class="recibo-total-usd" id="totalUSD">$0.00</span>
                    </div>
                    <div class="recibo-total-row mt-2 pt-2 border-top border-white border-opacity-10">
                        <span class="text-muted">Saldo a Favor:</span>
                        <span class="fw-bold text-success">$<?php echo number_format($saldo_favor, 2); ?></span>
                    </div>
                    <?php if ($tasa_bcv > 0): ?>
                    <div class="recibo-total-row">
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

            <!-- Boton Pagar -->
            <div class="mb-4">
                <button type="button" class="btn btn-pagar w-100 py-3" id="btn_pagar" onclick="mostrarModalConfirmacion()" disabled>
                    <i class="fas fa-credit-card me-2"></i> Pagar
                </button>
            </div>
        </form>

    </div>

    <!-- Modal Confirmacion -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel p-0">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i> Confirmar Pago</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4">
                    <div id="confirmacion_recibos"></div>
                    <div class="mt-3 pt-3 border-top border-white border-opacity-10">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Total Bs:</span>
                            <span class="fw-bold" id="confirm_total_bs">Bs 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Equivalente USD:</span>
                            <span class="fw-bold text-success" id="confirm_total_usd">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Tasa BCV:</span>
                            <span class="small" id="confirm_tasa">Bs 0,00 / $1</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Saldo a Favor:</span>
                            <span class="fw-bold text-success" id="confirm_saldo">$0.00</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top border-white border-opacity-10">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Método:</span>
                            <span class="fw-bold" id="confirm_metodo">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Banco:</span>
                            <span class="fw-bold" id="confirm_banco">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Referencia:</span>
                            <span class="fw-bold" id="confirm_referencia">-</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-glass flex-fill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-pagar flex-fill" id="btn_confirmar_pago" onclick="ejecutarPago()">
                        <i class="fas fa-check-circle me-2"></i> Confirmar Pago
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Resultado -->
    <div class="modal fade" id="modalResultado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel p-0">
                <div class="modal-body text-center py-5 px-4">
                    <div id="result_icon" class="mb-3" style="font-size:4rem;"></div>
                    <h4 class="fw-bold mb-2" id="result_title"></h4>
                    <p class="text-muted mb-0" id="result_message"></p>
                    <div id="result_details" class="mt-3 text-start d-none"></div>
                </div>
                <div class="modal-footer border-0 justify-content-center px-4 pb-4">
                    <button type="button" class="btn btn-glass" id="btn_cerrar_resultado" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-pagar d-none" id="btn_confirmar_pago_resultado">
                        <i class="fas fa-check-circle me-2"></i> Confirmar y Pagar
                    </button>
                    <a href="dashboard.php" class="btn btn-pagar d-none" id="btn_ir_dashboard">
                        <i class="fas fa-arrow-left me-2"></i> Ir al Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="loadingOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:99999;justify-content:center;align-items:center;flex-direction:column;">
        <div style="width:50px;height:50px;border:4px solid rgba(255,255,255,0.2);border-top-color:#3b82f6;border-radius:50%;animation:loadingSpin 0.8s linear infinite;"></div>
        <p style="color:#fff;font-size:1.2rem;font-weight:600;margin-top:20px;">Procesando...</p>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    const todosLosBancos = <?php echo json_encode($bancosArr); ?>;
    const csrfToken = '<?php echo htmlspecialchars(generate_csrf_token()); ?>';
    const idContrato = '<?php echo $wisp_service_id; ?>';
    const saldoFavor = <?php echo $saldo_favor; ?>;
    const tasaBcv = <?php echo $tasa_bcv; ?>;
    const recibos = <?php echo json_encode($invoices_json); ?>;
    const metodosBancos = <?php echo json_encode($metodosFiltrados); ?>;
    let selectedIds = [];
    for (var i = 0; i < recibos.length; i++) {
        if (recibos[i].preseleccionado) selectedIds.push(recibos[i].id);
    }
    let selectedMetodo = '';
    let selectedBanco = null;

    document.getElementById('themeToggleBtn').addEventListener('click', function() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        this.querySelector('i').className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });

    function toggleRecibo(cb) {
        var id = parseInt(cb.dataset.id);
        if (cb.checked) {
            if (selectedIds.indexOf(id) === -1) selectedIds.push(id);
        } else {
            selectedIds = selectedIds.filter(function(v) { return v !== id; });
        }
        actualizarInvoiceIds();
        actualizarTotal();
        updatePagarBtn();
    }

    function actualizarTotal() {
        var totalUSD = 0;
        for (var i = 0; i < recibos.length; i++) {
            if (selectedIds.indexOf(recibos[i].id) !== -1) {
                totalUSD += recibos[i].monto_pendiente;
            }
        }
        var totalBS = totalUSD * tasaBcv;
        document.getElementById('totalBS').textContent = 'Bs ' + totalBS.toFixed(2).replace('.', ',');
        document.getElementById('totalUSD').textContent = '$' + totalUSD.toFixed(2);
    }

    function actualizarInvoiceIds() {
        var container = document.getElementById('invoice_ids_container');
        container.innerHTML = '';
        for (var i = 0; i < selectedIds.length; i++) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'invoice_ids[]';
            input.value = selectedIds[i];
            container.appendChild(input);
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

        updatePagarBtn();
    }

    function mostrarBanco(bank) {
        document.getElementById('banco_nombre').textContent = bank.nombre_banco;
        document.getElementById('banco_titular').textContent = bank.nombre_banco;

        var div = document.getElementById('banco_detalles');
        div.innerHTML = '';
        var lines = [];
        var cuenta = (bank.numero_cuenta || '').replace(/-/g, '');
        if (selectedMetodo.indexOf('vil') !== -1) {
            lines = [bank.nombre_propietario, cuenta, bank.cedula_propietario];
        } else {
            lines = [cuenta, bank.nombre_propietario, bank.cedula_propietario];
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
        var cuenta = (selectedBanco.numero_cuenta || '').replace(/-/g, '');
        var text;
        if (selectedMetodo.indexOf('vil') !== -1) {
            text = selectedBanco.nombre_propietario + '\n' + cuenta + '\n' + selectedBanco.cedula_propietario;
        } else {
            text = cuenta + '\n' + selectedBanco.nombre_propietario + '\n' + selectedBanco.cedula_propietario;
        }
        navigator.clipboard.writeText(text).then(function() {
            var btn = document.getElementById('btn_copiar');
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Copiado';
            btn.classList.add('text-success');
            setTimeout(function() { btn.innerHTML = '<i class="far fa-copy me-1"></i> Copiar Datos'; btn.classList.remove('text-success'); }, 2000);
        });
    }

    function updatePagarBtn() {
        var ref = document.getElementById('input_referencia').value.trim();
        var btn = document.getElementById('btn_pagar');
        btn.disabled = !selectedMetodo || ref.length < 6 || selectedIds.length === 0;
    }

    document.getElementById('input_referencia').addEventListener('input', updatePagarBtn);

    function mostrarModalConfirmacion() {
        if (selectedIds.length === 0) {
            mostrarModalResultado('error', 'Selecciona al menos un recibo para pagar.');
            return;
        }
        var ref = document.getElementById('input_referencia').value.trim();
        if (!ref || ref.length < 6) {
            mostrarModalResultado('error', 'La referencia debe tener al menos 6 caracteres.');
            return;
        }
        if (!selectedMetodo || !selectedBanco) {
            mostrarModalResultado('error', 'Selecciona un metodo de pago.');
            return;
        }

        var totalUSD = 0;
        var html = '';
        for (var i = 0; i < recibos.length; i++) {
            if (selectedIds.indexOf(recibos[i].id) !== -1) {
                totalUSD += recibos[i].monto_pendiente;
                html += '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<div><small class="text-muted">Recibo #' + recibos[i].folio + '</small><br><small>' + recibos[i].descripcion + '</small></div>' +
                    '<span class="fw-bold">Bs ' + recibos[i].monto_bs.toFixed(2).replace('.', ',') + '</span></div>';
            }
        }
        var totalBS = totalUSD * tasaBcv;
        document.getElementById('confirmacion_recibos').innerHTML = html;
        document.getElementById('confirm_total_bs').textContent = 'Bs ' + totalBS.toFixed(2).replace('.', ',');
        document.getElementById('confirm_total_usd').textContent = '$' + totalUSD.toFixed(2);
        document.getElementById('confirm_tasa').textContent = 'Bs ' + tasaBcv.toFixed(2).replace('.', ',') + ' / $1';
        document.getElementById('confirm_saldo').textContent = '$' + saldoFavor.toFixed(2);
        document.getElementById('confirm_metodo').textContent = selectedMetodo;
        document.getElementById('confirm_banco').textContent = selectedBanco.nombre_banco;
        document.getElementById('confirm_referencia').textContent = ref;

        document.getElementById('input_monto_usd').value = totalUSD.toFixed(2);

        var modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
        modal.show();
    }

    function ejecutarPago() {
        bootstrap.Modal.getInstance(document.getElementById('modalConfirmacion')).hide();
        document.getElementById('loadingOverlay').style.display = 'flex';

        var ref = document.getElementById('input_referencia').value.trim();

        if (selectedMetodo === 'Zelle') {
            var montoZelle = document.getElementById('input_zelle_monto').value;
            if (!montoZelle || parseFloat(montoZelle) <= 0) {
                document.getElementById('loadingOverlay').style.display = 'none';
                mostrarModalResultado('error', 'Ingresa el monto en USD para Zelle.');
                return;
            }
            document.getElementById('input_monto_usd').value = parseFloat(montoZelle).toFixed(2);
            document.getElementById('input_monto_usd_real').value = parseFloat(montoZelle).toFixed(2);
            document.getElementById('paymentForm').submit();
            return;
        }

        if (selectedBanco.api_config && selectedBanco.api_config.habilitada) {
            var params = new URLSearchParams();
            params.append('csrf_token', csrfToken);
            params.append('id_banco', selectedBanco.id_banco);
            params.append('referencia', ref);
            params.append('fecha_pago', '<?php echo date('Y-m-d'); ?>');
            params.append('metodo_pago', selectedMetodo);
            params.append('id_contrato', idContrato);
            for (var i = 0; i < selectedIds.length; i++) {
                params.append('invoice_ids[]', selectedIds[i]);
            }

            fetch('api_verificar_pago.php', {method:'POST', body:params})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    if (data.status === 'verified') {
                        document.getElementById('input_verificacion_data').value = JSON.stringify(data);
                        document.getElementById('input_monto_usd_real').value = data.monto_usd || 0;
                        mostrarModalResultado('verificacion', data.descripcion || 'Referencia verificada correctamente.', data, function() {
                            document.getElementById('paymentForm').submit();
                        });
                    } else if (data.status === 'manual') {
                        document.getElementById('paymentForm').submit();
                    } else {
                        mostrarModalResultado('error', data.message || 'Error al verificar el pago.');
                    }
                })
                .catch(function() {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    mostrarModalResultado('error', 'Error de conexion. Intenta de nuevo.');
                });
        } else {
            document.getElementById('paymentForm').submit();
        }
    }

    function mostrarModalResultado(tipo, mensaje, data, onConfirm) {
        var icon = document.getElementById('result_icon');
        var title = document.getElementById('result_title');
        var msg = document.getElementById('result_message');
        var details = document.getElementById('result_details');
        var btnCerrar = document.getElementById('btn_cerrar_resultado');
        var btnConfirm = document.getElementById('btn_confirmar_pago_resultado');
        var btnDash = document.getElementById('btn_ir_dashboard');

        details.classList.add('d-none');
        btnCerrar.classList.remove('d-none');
        btnConfirm.classList.add('d-none');
        btnDash.classList.add('d-none');
        btnConfirm.onclick = null;

        if (tipo === 'success') {
            icon.innerHTML = '<i class="fas fa-check-circle" style="color:var(--success);"></i>';
            title.textContent = 'Pago Exitoso';
            title.className = 'fw-bold mb-2';
            title.style.color = 'var(--success)';
            msg.textContent = mensaje;
            if (data) {
                details.classList.remove('d-none');
                var dHtml = '<div class="table-responsive mt-2"><table class="table table-premium mb-0">';
                if (data.referencia) dHtml += '<tr><td class="text-muted">Referencia</td><td class="fw-bold">' + data.referencia + '</td></tr>';
                if (data.monto_usd) dHtml += '<tr><td class="text-muted">Monto USD</td><td class="fw-bold">$' + parseFloat(data.monto_usd).toFixed(2) + '</td></tr>';
                if (data.monto_bs) dHtml += '<tr><td class="text-muted">Monto Bs</td><td class="fw-bold">Bs ' + parseFloat(data.monto_bs).toFixed(2).replace('.', ',') + '</td></tr>';
                if (data.service_id) dHtml += '<tr><td class="text-muted">Servicio</td><td class="fw-bold">' + data.service_id + '</td></tr>';
                dHtml += '</table></div>';
                details.innerHTML = dHtml;
            }
            btnDash.classList.remove('d-none');
            btnCerrar.classList.add('d-none');
        } else if (tipo === 'verificacion') {
            icon.innerHTML = '<i class="fas fa-info-circle" style="color:var(--primary);"></i>';
            title.textContent = 'Verificación Exitosa';
            title.className = 'fw-bold mb-2';
            title.style.color = 'var(--primary)';
            msg.innerHTML = mensaje;
            if (data) {
                details.classList.remove('d-none');
                var dHtml = '<div class="table-responsive mt-2"><table class="table table-premium mb-0">';
                if (data.monto_usd) dHtml += '<tr><td class="text-muted">Monto Verificado</td><td class="fw-bold">$' + parseFloat(data.monto_usd).toFixed(2) + '</td></tr>';
                if (data.monto_bs) dHtml += '<tr><td class="text-muted">Monto en Bs</td><td class="fw-bold">Bs ' + parseFloat(data.monto_bs).toFixed(2).replace('.', ',') + '</td></tr>';
                if (data.deuda_seleccionada_usd) dHtml += '<tr><td class="text-muted">Deuda Seleccionada</td><td class="fw-bold">$' + parseFloat(data.deuda_seleccionada_usd).toFixed(2) + '</td></tr>';
                dHtml += '</table></div>';
                details.innerHTML = dHtml;
            }
            btnCerrar.classList.add('d-none');
            btnConfirm.classList.remove('d-none');
            if (typeof onConfirm === 'function') {
                btnConfirm.onclick = function() {
                    bootstrap.Modal.getInstance(document.getElementById('modalResultado')).hide();
                    onConfirm();
                };
            }
        } else {
            icon.innerHTML = '<i class="fas fa-exclamation-circle" style="color:var(--danger);"></i>';
            title.textContent = 'Error';
            title.className = 'fw-bold mb-2';
            title.style.color = 'var(--danger)';
            msg.textContent = mensaje;
        }

        new bootstrap.Modal(document.getElementById('modalResultado')).show();
    }

    document.getElementById('paymentForm').addEventListener('submit', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });

    var msgsDiv = document.getElementById('pago_mensajes');
    if (msgsDiv) {
        var pagoExito = msgsDiv.getAttribute('data-exito');
        var pagoErr = msgsDiv.getAttribute('data-error');
        var pagoDetalles = msgsDiv.getAttribute('data-detalles');
        var data = pagoDetalles ? JSON.parse(pagoDetalles) : null;
        if (pagoExito) {
            mostrarModalResultado('success', pagoExito, data);
        } else if (pagoErr) {
            mostrarModalResultado('error', pagoErr, data);
        }
    }

    actualizarInvoiceIds();
    actualizarTotal();
    updatePagarBtn();
    </script>

    <style>
        @keyframes loadingSpin { to { transform: rotate(360deg); } }
        .table-premium td, .table-premium th { padding: 12px 16px; }
        #loadingOverlay { display:none; }

        .recibo-select-panel { overflow: hidden; }
        .recibo-select-item {
            border-bottom: 1px solid var(--border-glass);
            transition: background 0.2s;
        }
        .recibo-select-item:hover { background: rgba(255,255,255,0.03); }
        .recibo-select-item.vencida { border-left: 3px solid var(--danger); }
        .recibo-select-label {
            display: flex;
            align-items: flex-start;
            padding: 14px 16px;
            cursor: pointer;
            gap: 12px;
            margin: 0;
        }
        .recibo-select-check {
            width: 20px;
            height: 20px;
            margin-top: 4px;
            accent-color: var(--primary);
            flex-shrink: 0;
        }
        .recibo-select-content { flex: 1; min-width: 0; }
        .recibo-select-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }
        .recibo-select-folio {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .recibo-select-monto-bs {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
        }
        .recibo-select-bottom {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            gap: 4px;
        }
        .recibo-select-desc {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 200px;
        }
        .recibo-select-badge { font-size: 0.7rem; }
        .recibo-select-fecha { font-size: 0.75rem; }

        .recibo-total-bar {
            background: rgba(255,255,255,0.03);
            border-top: 1px solid var(--border-glass);
        }
        .recibo-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 3px 0;
            font-size: 0.9rem;
        }
        .recibo-total-bs {
            font-size: 1.15rem;
        }
        .recibo-total-usd {
            color: var(--text-muted);
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

        .btn-pagar {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-pagar:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.3);
        }
        .btn-pagar:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-pagar:active { transform: translateY(0) !important; }

        #result_details .table-premium td:first-child { color: var(--text-main); opacity: 0.75; }
        .modal-content.glass-panel {
            background: var(--glass-bg) !important;
            backdrop-filter: blur(16px) !important;
            border: 1px solid var(--border-glass) !important;
            border-radius: 16px !important;
        }
        .modal-content.glass-panel .modal-header,
        .modal-content.glass-panel .modal-footer { border-color: var(--border-glass); }
        .modal-backdrop { background: rgba(0,0,0,0.7) !important; }

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
