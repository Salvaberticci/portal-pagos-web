<?php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA'))
    define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE'))
    define('DEV_MODE', false);

$cedula = $_SESSION['cliente_cedula'];
$nombre = $_SESSION['cliente_nombre'];

$pago_msg = $_SESSION['pago_msg'] ?? null;
$pago_err = $_SESSION['pago_err'] ?? null;
$wisp_service_id = $_SESSION['wisp_service_id'] ?? null;
unset($_SESSION['pago_msg'], $_SESSION['pago_err']);
session_write_close();

// Tasa BCV (con cache de 1 hora)
$tasa_bcv = 1;
$tasa_fecha = '';
$cache_file = 'tasa_cache.json';
$cache_time = 3600;

// Send loading overlay to browser before slow API calls
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <script>const savedTheme = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', savedTheme);</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - Wireless Supply</title>
    <link rel="icon" href="../images/favicon.png" type="image/png">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div id="page-loading" class="loading-overlay" style="display:flex;">
        <div class="spinner"></div>
        <div class="loading-text">Cargando...</div>
        <div class="loading-sub">Consultando tus datos</div>
    </div>
    <?php
    if (ob_get_level())
        ob_end_flush();
    flush();

    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        $data_cache = json_decode(file_get_contents($cache_file), true);
        $tasa_bcv = $data_cache['tasa'] ?? 1;
        $tasa_fecha = $data_cache['fecha'] ?? '';
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
                $tasa_fecha = date('d/m/Y h:i A', strtotime($data_bcv['fechaActualizacion'] ?? 'now'));
                @file_put_contents($cache_file, json_encode(['tasa' => $tasa_bcv, 'fecha' => $tasa_fecha]));
            }
        }
        curl_close($ch);
    }

    // Conectar a WispHub
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/Services/WispHubClient.php';
    $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
    if (DEV_MODE && $cedula === TEST_USER_CEDULA) {
        require_once __DIR__ . '/../src/Services/WispHubDevModeClient.php';
        $wispClient = new \Services\WispHubDevModeClient($wispConfig);
    } else {
        $wispClient = new \Services\WispHubClient($wispConfig);
    }

    if (!$wisp_service_id) {
        header('Location: index.php?logout=1');
        exit;
    }

    require_once __DIR__ . '/wisp_helper.php';
    require_once __DIR__ . '/referencia_helper.php';
    $wisp_cached = wisp_get_cached_data($wispClient, $wisp_service_id);
    $c_perfil = $wisp_cached['profile'];
    $invoices = $wisp_cached['invoices'];
    $saldo_favor = $wisp_cached['balance'];
    $ultimo_pago = $wisp_cached['ultimo_pago'];

    // Obtener todos los servicios del cliente
    $clientServices = $wispClient->getServicesByCedula($cedula);

    $deuda_total = 0;
    $notas_credito = []; // facturas con total negativo = saldo a favor
    foreach ($invoices as $inv) {
        $total = floatval($inv['total'] ?? 0);
        $cobrado = floatval($inv['total_cobrado'] ?? 0);
        if ($total < 0) {
            $notas_credito[] = $inv;
            continue;
        }
        if ($total > 0 && $cobrado < $total) {
            $saldo = floatval($inv['saldo_nuevo'] ?? $inv['saldo'] ?? ($total - $cobrado));
            if ($saldo < 0.005)
                $saldo = $total - $cobrado;
            $deuda_total += $saldo;
        }
    }
    $total_credito = 0;
    foreach ($notas_credito as $nc) {
        $total_credito += abs(floatval($nc['total'] ?? 0));
    }

    // Estado del servicio (por defecto del perfil)
    $estado_ws = strtoupper($c_perfil['estado'] ?? 'ACTIVO');

    // Sincronizar el estado con la llamada en vivo de getServicesByCedula
// para evitar incongruencias de caché justo después de pagar
    foreach ($clientServices as $svc) {
        $svcId = $svc['id_servicio'] ?? $svc['id'] ?? $svc['service_id'] ?? 0;
        if ($svcId == $wisp_service_id) {
            $estado_ws = strtoupper($svc['estado'] ?? $estado_ws);
            break;
        }
    }

    if ($estado_ws === 'ACTIVE')
        $estado_ws = 'ACTIVO';
    if ($estado_ws === 'SUSPENDED')
        $estado_ws = 'SUSPENDIDO';
    if ($estado_ws === 'CANCELLED')
        $estado_ws = 'CANCELADO';
    if ($estado_ws === 'FREE')
        $estado_ws = 'GRATIS';

    ?>
    <div id="page-content" style="display:none;">

        <header class="glass-header py-3 mb-4">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <img src="../images/logo-galanet.png" alt="Logo" class="me-3"
                        style="height: 40px; border-radius: 6px;">
                    <h5 class="mb-0 fw-bold d-none d-sm-block text-gradient">Portal de Clientes</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button class="theme-toggle" id="themeToggleBtn" title="Cambiar Tema">
                        <i class="fas fa-sun"></i>
                    </button>
                    <div>
                        <span class="me-3 text-muted d-none d-md-inline" style="font-size: 0.9rem;"><i
                                class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($nombre); ?></span>
                        <a href="index.php?logout=1" class="btn btn-sm btn-glass text-danger border-danger"><i
                                class="fas fa-sign-out-alt"></i> Salir</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="container main-container animate-fade">
            <?php if ($cedula === TEST_USER_CEDULA): ?>
                <div class="alert alert-info glass-panel mb-4 text-center border-0 shadow-sm"
                    style="background: rgba(14, 165, 233, 0.15); border-left: 4px solid #0ea5e9 !important; border-radius: 12px;">
                    <p class="mb-0 fw-bold" style="letter-spacing: 0.5px; color: #bae6fd;">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        MODO DE PRUEBA: Todos tus pagos serán procesados a exactamente <span class="text-info fs-5">Bs.
                            1,00</span>.
                    </p>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h2 class="mb-1 text-gradient">Pago Pendiente</h2>
                    <p class="text-muted mb-0">Selecciona un recibo para realizar tu pago.</p>
                </div>
            </div>

            <?php if ($pago_msg): ?>
                <div class="alert alert-success glass-panel mb-4" id="alert-pago-ok">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo strip_tags($pago_msg, '<strong><span><br>'); ?>
                </div>
                <script>
                    setTimeout(() => {
                        const a = document.getElementById('alert-pago-ok');
                        if (a) { a.style.transition = 'opacity 0.6s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 650); }
                    }, 6000);
                </script>
            <?php endif; ?>
            <?php if ($pago_err): ?>
                <div class="alert alert-danger glass-panel mb-4">
                    <i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($pago_err, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Cabecera del Cliente -->
            <div class="glass-panel p-4 mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block"><i class="fas fa-user me-1"></i> Cliente</small>
                        <span
                            class="fw-bold"><?php echo htmlspecialchars(trim(($c_perfil['nombre'] ?? '') . ' ' . ($c_perfil['apellidos'] ?? '')) ?: $nombre); ?></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block"><i class="fas fa-signal me-1"></i> Estado</small>
                        <?php
                        $statusClass = match ($estado_ws) {
                            'ACTIVO' => 'status-active',
                            'SUSPENDIDO' => 'status-suspended',
                            'GRATIS' => 'status-free',
                            'CANCELADO' => 'status-cancelled',
                            default => 'status-suspended',
                        };
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $estado_ws; ?></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i> Zona</small>
                        <span><?php echo htmlspecialchars($c_perfil['zona']['nombre'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <div class="row g-3 mt-3 pt-3 border-top border-white border-opacity-10">
                    <div class="col-md-6">
                        <small class="text-muted d-block"><i class="fas fa-home me-1"></i> Dirección</small>
                        <span><?php echo htmlspecialchars($c_perfil['direccion'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block"><i class="fas fa-wifi me-1"></i> Plan</small>
                        <span
                            class="fw-bold"><?php echo htmlspecialchars($c_perfil['plan_internet']['nombre'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <?php if ($ultimo_pago): ?>
                    <div class="row mt-3 pt-3 border-top border-white border-opacity-10">
                        <div class="col-12">
                            <div class="ultimo-pago-card glass-panel p-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <small class="text-muted d-block"><i class="fas fa-check-circle text-success me-1"></i>
                                        Último Pago</small>
                                    <?php if (!empty($ultimo_pago['id'])): ?>
                                        <span class="badge bg-success mb-1">Recibo #<?php echo $ultimo_pago['id']; ?></span>
                                    <?php endif; ?>
                                    <span class="fw-bold">$<?php echo number_format($ultimo_pago['monto'], 2); ?></span>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Fecha</small>
                                    <span
                                        class="fw-bold"><?php echo date('d/m/Y', strtotime($ultimo_pago['fecha_pago'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>



            <!-- Todos los Recibos -->
            <div class="glass-panel p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-file-invoice me-2 text-primary"></i> Mis Recibos</h5>
                        <?php
                        $totalInvs = 0;
                        $unpaid = 0;
                        foreach ($invoices as $inv) {
                            $t = floatval($inv['total'] ?? $inv['monto'] ?? 0);
                            if ($t < 0) continue; // nota de crédito
                            $c = floatval($inv['total_cobrado'] ?? 0);
                            if ($c < $t) {
                                $totalInvs++;
                                if ($c <= 0)
                                    $unpaid++;
                            }
                        }
                        ?>
                        <?php if ($totalInvs > 0): ?>
                            <small class="text-muted"><?php echo $totalInvs; ?>
                                recibo<?php echo $totalInvs > 1 ? 's' : ''; ?><?php if ($unpaid > 0): ?>
                                    (<?php echo $unpaid; ?>
                                    pendiente<?php echo $unpaid > 1 ? 's' : ''; ?>)<?php endif; ?></small>
                        <?php endif; ?>


                    </div>
                </div>
                <?php
                $pending_invoices = $invoices;
                // Filtrar facturas con total positivo (excluir notas de crédito con total < 0)
                $positive_count = 0;
                foreach ($pending_invoices as $inv) {
                    if (floatval($inv['total'] ?? $inv['monto'] ?? 0) > 0) $positive_count++;
                }
                ?>

                <?php if ($positive_count > 0): ?>
                    
                    <!-- Sección de Facturas Pendientes (Sin Abonos) -->
                    <?php if ($positive_count > 0): ?>
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                <i class="fas fa-exclamation-circle text-primary me-2"></i> Recibos Pendientes
                            </h6>
                            <div class="recibos-list">
                                <?php 
                                $displayedAny = false;
                                foreach ($pending_invoices as $inv):
                                    $inv_id = $inv['id'] ?? $inv['id_factura'] ?? 0;
                                    $inv_monto = floatval($inv['total'] ?? $inv['monto'] ?? $inv['monto_pendiente'] ?? 0);
                                    if ($inv_monto < 0) continue; // nota de crédito
                                    $displayedAny = true;
                                    $inv_monto_bs = $inv_monto * $tasa_bcv;
                                    $inv_desc = wisp_extract_desc($inv, $inv_id);
                                    $fecha_emi = $inv['fecha_emision'] ?? '';
                                    $fecha_venc = $inv['fecha_vencimiento'] ?? '';
                                    $vencida = $fecha_venc && strtotime($fecha_venc) < time();
                                    $es_recurring = $fecha_emi && $fecha_venc && max(1, round((strtotime($fecha_venc) - strtotime($fecha_emi)) / 86400)) > 1;
                                    ?>
                                    <div class="recibo-card <?php echo $vencida ? 'recibo-vencida' : ''; ?>">
                                        <!-- Cuerpo -->
                                        <div class="recibo-body">
                                            <div class="recibo-top">
                                                <div class="recibo-info">
                                                    <span class="recibo-num">Recibo #<?php echo $inv_id; ?></span>
                                                    <?php if ($fecha_emi): ?>
                                                        <span class="recibo-fecha"><i
                                                                class="fas fa-calendar-alt me-1"></i><?php echo date('d M Y', strtotime($fecha_emi)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="recibo-montos">
                                                    <span class="recibo-usd">$<?php echo number_format($inv_monto, 2); ?></span>
                                                    <span class="recibo-bs">Bs
                                                        <?php echo number_format($inv_monto_bs, 2, ',', '.'); ?></span>
                                                </div>
                                            </div>

                                            <!-- Descripción -->
                                            <div class="recibo-desc-row">
                                                <span class="recibo-desc"><?php echo htmlspecialchars($inv_desc); ?></span>
                                            </div>

                                            <hr class="recibo-divider">

                                            <?php if ($es_recurring && $fecha_venc): ?>
                                                <div class="recibo-row recibo-venc-row <?php echo $vencida ? 'text-danger' : 'text-warning'; ?>">
                                                    <span><i class="fas fa-clock me-1"></i>Vence:
                                                        <?php echo date('d/m/Y', strtotime($fecha_venc)); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="recibo-row recibo-status-recibo">
                                                <span class="recibo-status-text pendiente"><i class="fas fa-clock me-1"></i>Pendiente de pago</span>
                                            </div>
                                        </div>

                                        <!-- Barra de acento inferior -->
                                        <div class="recibo-accent-bar"></div>
                                    </div>
                                <?php endforeach; 
                                if (!$displayedAny):
                                ?>
                                    <p class="text-muted text-center py-3 mb-0">No hay recibos pendientes.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>



                    <div class="text-center mt-4">
                        <a href="pago.php?id_contrato=<?php echo $wisp_service_id; ?>" class="btn btn-premium btn-lg px-5">
                            <i class="fas fa-credit-card me-2"></i> Continuar
                        </a>
                    </div>

                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3" style="font-size:3rem;">&#127881;</div>
                        <h6 class="fw-bold text-success">&#161;Sin deudas pendientes!</h6>
                        <p class="text-muted mb-0 small">No tienes recibos pendientes de pago.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($notas_credito)): ?>
                    <div class="mb-4">
                        <h6 class="text-uppercase fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.5px; color: #10b981;">
                            <i class="fas fa-check-circle me-2" style="color: #10b981;"></i> Saldo a Favor / Notas de Crédito
                            <span class="ms-2 badge bg-success-subtle text-success" style="font-size: 0.7rem; vertical-align: middle;">$<?php echo number_format($total_credito, 2); ?></span>
                        </h6>
                        <div class="recibos-list">
                            <?php foreach ($notas_credito as $nc):
                                $nc_id = $nc['id'] ?? 0;
                                $nc_monto = abs(floatval($nc['total'] ?? 0));
                                $nc_desc = wisp_extract_desc($nc, $nc_id);
                                $fecha_nc = $nc['fecha_emision'] ?? '';
                                ?>
                                <div class="recibo-card" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05);">
                                    <div class="recibo-body" style="width: 100%;">
                                        <div class="recibo-top">
                                            <div class="recibo-info">
                                                <span class="recibo-num" style="color: #10b981;">Nota de Crédito #<?php echo $nc_id; ?></span>
                                                <?php if ($fecha_nc): ?>
                                                    <span class="recibo-fecha"><i class="fas fa-calendar-alt me-1"></i><?php echo date('d M Y', strtotime($fecha_nc)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="recibo-montos">
                                                <span class="recibo-usd" style="color: #10b981;">+$<?php echo number_format($nc_monto, 2); ?></span>
                                            </div>
                                        </div>
                                        <div class="recibo-desc-row">
                                            <span class="recibo-desc"><?php echo htmlspecialchars($nc_desc); ?></span>
                                        </div>
                                        <hr class="recibo-divider">
                                        <div class="recibo-row recibo-status-recibo">
                                            <span class="recibo-status-text" style="color: #10b981; font-weight: 600;"><i class="fas fa-check-circle me-1"></i>Disponible como saldo a favor</span>
                                        </div>
                                    </div>
                                    <div class="recibo-accent-bar" style="background: linear-gradient(90deg, #10b981, #34d399);"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i> Este saldo se descuenta autom&aacute;ticamente de tu pr&oacute;ximo recibo.</p>
                    </div>
                <?php endif; ?>
            </div>

            <footer class="text-center py-4 mt-5 border-top border-white border-opacity-10">
                <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Wireless Supply. Todos los derechos
                    reservados.</p>
            </footer>
        </div>

        <script src="../js/bootstrap.bundle.min.js"></script>

        <style>
            /* ── Recibos: cards premium en dashboard ── */
            .recibos-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .recibo-card {
                position: relative;
                display: block;
                width: 100%;
                padding: 16px 18px;
                border-radius: 16px;
                border: 1.5px solid var(--border-glass);
                background: var(--glass-bg);
                overflow: hidden;
                transition: all 0.25s ease;
                box-sizing: border-box;
            }

            .recibo-card:hover {
                border-color: rgba(59, 130, 246, 0.5);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.12);
            }

            .recibo-card.recibo-vencida {
                border-color: rgba(239, 68, 68, 0.4);
            }

            /* Cuerpo */
            .recibo-body {
                width: 100%;
            }

            .recibo-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 10px;
                margin-bottom: 6px;
            }

            .recibo-info {
                display: flex;
                flex-direction: column;
                gap: 2px;
                flex: 1;
                min-width: 0;
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
                background: rgba(239, 68, 68, 0.1);
                padding: 1px 7px;
                border-radius: 20px;
                display: inline-block;
            }

            /* Nuevas filas del card rediseñado */
            .recibo-divider {
                margin: 6px 0;
                border-color: var(--border-glass);
                opacity: 0.4;
            }

            .recibo-row {
                font-size: 0.78rem;
                margin-bottom: 3px;
                line-height: 1.5;
            }

            .recibo-venc-row {
                font-weight: 600;
            }

            .recibo-status-recibo {
                margin-top: 2px;
            }

            .recibo-status-text {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 20px;
                font-size: 0.72rem;
                font-weight: 700;
            }

            .recibo-status-text.pagado {
                color: #10b981;
                background: rgba(16, 185, 129, 0.12);
            }

            .recibo-status-text.pendiente {
                color: var(--warning);
                background: rgba(245, 158, 11, 0.12);
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

            /* Fila inferior: descripción */
            .recibo-desc-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }

            .recibo-desc {
                font-size: 0.8rem;
                color: var(--text-muted);
                line-height: 1.4;
                flex: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* Botón Pagar */
            .btn-pagar {
                flex-shrink: 0;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 6px 16px;
                border-radius: 10px;
                background: linear-gradient(135deg, #3b82f6, #6366f1);
                color: #fff;
                font-size: 0.82rem;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
            }

            .btn-pagar:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 14px rgba(59, 130, 246, 0.45);
                color: #fff;
            }

            /* Barra de acento inferior */
            .recibo-accent-bar {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, #3b82f6, #8b5cf6);
                border-radius: 0 0 16px 16px;
            }

            .recibo-vencida .recibo-accent-bar {
                background: linear-gradient(90deg, #ef4444, #f97316);
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const themeBtn = document.getElementById('themeToggleBtn');
                if (!themeBtn) return;
                const html = document.documentElement;
                const themeIcon = themeBtn.querySelector('i');
                function updateThemeIcon(theme) {
                    themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                }
                const saved = localStorage.getItem('theme') || 'dark';
                html.setAttribute('data-theme', saved);
                updateThemeIcon(saved);
                themeBtn.addEventListener('click', function () {
                    const current = html.getAttribute('data-theme');
                    const next = current === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-theme', next);
                    localStorage.setItem('theme', next);
                    updateThemeIcon(next);
                });
            });
        </script>
    </div>
    <script>
        (function () { var lo = document.getElementById('page-loading'); if (lo) lo.style.display = 'none'; var pc = document.getElementById('page-content'); if (pc) pc.style.display = 'block'; })();
    </script>
</body>

</html>