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
        header('Location: auth.php?logout=1');
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
    foreach ($invoices as $inv) {
        $total = floatval($inv['total'] ?? 0);
        $cobrado = floatval($inv['total_cobrado'] ?? 0);
        if ($total > 0 && $cobrado < $total) {
            $saldo = floatval($inv['saldo_nuevo'] ?? $inv['saldo'] ?? ($total - $cobrado));
            if ($saldo < 0.005)
                $saldo = $total - $cobrado;
            $deuda_total += $saldo;
        }
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
                        <a href="auth.php?logout=1" class="btn btn-sm btn-glass text-danger border-danger"><i
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
                <?php if ($tasa_bcv > 1): ?>
                    <div class="text-end d-none d-md-block">
                        <span class="badge bg-primary glass-panel p-2">Tasa BCV: Bs
                            <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($tasa_bcv > 1): ?>
                <div class="d-block d-md-none mb-4 text-center">
                    <span class="badge bg-primary glass-panel p-2 w-100">Tasa BCV: Bs
                        <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
                </div>
            <?php endif; ?>

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
                    <div class="col-md-3">
                        <small class="text-muted d-block">Cliente</small>
                        <span
                            class="fw-bold"><?php echo htmlspecialchars(trim(($c_perfil['nombre'] ?? '') . ' ' . ($c_perfil['apellidos'] ?? '')) ?: $nombre); ?></span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Estado</small>
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
                        <small class="text-muted d-block">Email</small>
                        <span><?php echo htmlspecialchars($c_perfil['email'] ?? 'N/A'); ?></span>
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

            <!-- Mis Servicios -->
            <div class="glass-panel p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-server me-2 text-primary"></i> Mis Servicios</h5>
                <div class="services-list">
                    <?php foreach ($clientServices as $svc):
                        $svcId = $svc['id_servicio'] ?? $svc['id'] ?? $svc['service_id'] ?? 0;
                        $svcEst = strtoupper($svc['estado'] ?? 'ACTIVO');
                        if ($svcEst === 'ACTIVE')
                            $svcEst = 'ACTIVO';
                        if ($svcEst === 'SUSPENDED')
                            $svcEst = 'SUSPENDIDO';
                        if ($svcEst === 'CANCELLED')
                            $svcEst = 'CANCELADO';
                        if ($svcEst === 'FREE')
                            $svcEst = 'GRATIS';
                        $isCurrent = ($svcId == $wisp_service_id);
                        // Solo obtener detalle si no es el servicio actual (ya lo tenemos en $c_perfil)
                        $svcPlan = '';
                        $svcRouter = '';
                        $svcIp = '';
                        $svcZona = '';
                        if ($isCurrent) {
                            $svcPlan = $c_perfil['plan_internet']['nombre'] ?? '';
                            $svcRouter = $c_perfil['router']['nombre'] ?? '';
                            $svcIp = $c_perfil['ip'] ?? '';
                            $svcZona = $c_perfil['zona']['nombre'] ?? '';
                        } elseif ($svcId) {
                            $det = $wispClient->getServiceDetail((string) $svcId);
                            if (!empty($det['data'])) {
                                $d = $det['data'];
                                $svcPlan = $d['plan_internet']['nombre'] ?? '';
                                $svcRouter = $d['router']['nombre'] ?? '';
                                $svcIp = $d['ip'] ?? '';
                                $svcZona = $d['zona']['nombre'] ?? '';
                            }
                        }
                        ?>
                        <div class="service-card <?php echo $isCurrent ? 'service-current' : ''; ?>">
                            <div class="service-top">
                                <span class="service-id">Servicio #<?php echo $svcId; ?></span>
                                <span class="service-badge status-badge status-<?php
                                echo match ($svcEst) {
                                    'ACTIVO' => 'active',
                                    'SUSPENDIDO' => 'suspended',
                                    'GRATIS' => 'free',
                                    'CANCELADO' => 'cancelled',
                                    default => 'pending',
                                };
                                ?>"><?php echo $svcEst; ?></span>
                            </div>
                            <div class="service-details">
                                <?php if ($svcPlan): ?><span><i
                                            class="fas fa-wifi me-1 text-muted"></i><?php echo htmlspecialchars($svcPlan); ?></span><?php endif; ?>
                                <?php if ($svcRouter): ?><span><i
                                            class="fas fa-network-wired me-1 text-muted"></i><?php echo htmlspecialchars($svcRouter); ?></span><?php endif; ?>
                                <?php if ($svcIp): ?><span><i
                                            class="fas fa-globe me-1 text-muted"></i><?php echo htmlspecialchars($svcIp); ?></span><?php endif; ?>
                                <?php if ($svcZona): ?><span><i
                                            class="fas fa-map-marker-alt me-1 text-muted"></i><?php echo htmlspecialchars($svcZona); ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                            $c = floatval($inv['total_cobrado'] ?? 0);
                            $t = floatval($inv['total'] ?? $inv['monto'] ?? 0);
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
                        <?php if ($saldo_favor > 0): ?>
                            <div class="row mt-3 pt-3 border-top border-white border-opacity-10">
                                <div class="col-12">
                                    <div class="glass-panel p-3 d-flex align-items-center justify-content-between">
                                        <div>
                                            <small class="text-muted d-block"><i
                                                    class="fas fa-wallet text-success me-1"></i> Saldo a Favor</small>
                                            <span
                                                class="fw-bold text-success">$<?php echo number_format($saldo_favor, 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                // Pre-cargar abonos parciales de la BD local UNA SOLA VEZ
                // IMPORTANTE: Agrupamos por factura y SUMAMOS todos los total_cobrado
                // para soportar múltiples abonos al mismo recibo (bug: el 2do abono pisaba al 1ro).
                $abonos_parciales = []; // mapa: id_factura => registro consolidado
                $pdo_dash = getDb();
                if ($pdo_dash) {
                    // Paso 1: Sumar todos los abonos por factura en los últimos 60 días
                    $stmt_ab = $pdo_dash->prepare(
                        "SELECT 
                            facturas,
                            SUM(total_cobrado) AS total_cobrado_acumulado,
                            MAX(total)         AS total,
                            MAX(fecha_promesa) AS fecha_promesa,
                            MAX(created_at)    AS created_at,
                            MAX(service_id)    AS service_id
                         FROM pagos_registrados 
                         WHERE service_id = ? 
                         AND facturas != ''
                         AND created_at > DATE_SUB(NOW(), INTERVAL 60 DAY) 
                         GROUP BY facturas
                         HAVING total_cobrado_acumulado < MAX(total)"
                    );
                    $stmt_ab->execute([$wisp_service_id]);
                    foreach ($stmt_ab->fetchAll() as $row) {
                        $inv_id_db = trim($row['facturas'] ?? '');
                        if (empty($inv_id_db)) continue;
                        // Normalizar el campo para que el código posterior lo use igual
                        $row['total_cobrado'] = floatval($row['total_cobrado_acumulado']);
                        // Mapear por id de factura con totales consolidados
                        $abonos_parciales[(int)$inv_id_db] = $row;
                        
                        // Rescate: Si la factura no viene en $invoices (porque WispHub la ocultó
                        // por haberla marcado "Pagada" tras el abono), la inyectamos manualmente.
                        $found = false;
                        foreach ($invoices as $inv) {
                            if ((int)($inv['id'] ?? $inv['id_factura'] ?? 0) === (int)$inv_id_db) {
                                $found = true; break;
                            }
                        }
                        if (!$found) {
                            // Obtener fechas reales de WispHub para calcular cobertura correctamente
                            $realDetail = [];
                            try {
                                $realDetail = $wispClient->getInvoiceDetail((string)$inv_id_db);
                            } catch (\Exception $e) {}
                            $fecha_emi_real  = $realDetail['fecha_emision']    ?? date('Y-m-d', strtotime($row['created_at'] . ' - 30 days'));
                            $fecha_venc_real = $realDetail['fecha_vencimiento'] ?? date('Y-m-d', strtotime($row['created_at']));
                            $total_real      = floatval($realDetail['total'] ?? $row['total']);
                            $desc_real       = !empty($realDetail['articulos'][0]['descripcion']) 
                                                ? $realDetail['articulos'][0]['descripcion'] 
                                                : 'Abono pendiente (Recibo N° ' . $inv_id_db . ')';
                            $invoices[] = [
                                'id'                => (int)$inv_id_db,
                                'id_factura'        => (int)$inv_id_db,
                                'fecha_emision'     => $fecha_emi_real,
                                'fecha_vencimiento' => $fecha_venc_real,
                                'total'             => $total_real,
                                'saldo_nuevo'       => $total_real,
                                'saldo'             => $total_real,
                                'total_cobrado'     => $row['total_cobrado'], // ya es el acumulado
                                'estado'            => 1,
                                'articulos'         => [['descripcion' => $desc_real]],
                                '_rescued'          => true,
                            ];
                        }
                    }
                }

                // Clasificar facturas
                $pending_invoices = [];
                $abonadas_invoices = [];

                foreach ($invoices as $inv) {
                    $inv_id = $inv['id'] ?? $inv['id_factura'] ?? 0;
                    $inv_monto = floatval($inv['total'] ?? $inv['monto'] ?? $inv['monto_pendiente'] ?? 0);
                    $abonado = floatval($inv['total_cobrado'] ?? 0);
                    
                    // --- DETECCIÓN DE FACTURA CON ABONO EN BD LOCAL ---
                    $is_saldo_pendiente = false;
                    if (isset($abonos_parciales[(int)$inv_id])) {
                        $recent_partial = $abonos_parciales[(int)$inv_id];
                        $is_saldo_pendiente = true;
                        // Usar monto real de la BD si WispHub dice total distinto
                        $inv_monto = floatval($recent_partial['total']);
                        // El cobrado real es lo que dice la BD local (más fiable que WispHub)
                        $abonado = floatval($recent_partial['total_cobrado']);
                    }

                    // Saltar facturas completamente pagadas
                    if ($abonado >= $inv_monto && $inv_monto > 0 && !$is_saldo_pendiente) {
                        continue;
                    }
                    
                    // Calcular saldo pendiente
                    // Si hay abono en BD local: siempre calculamos total - cobrado (WispHub no refleja el abono aún)
                    // Si no hay abono local: usamos el saldo que devuelve WispHub
                    if ($is_saldo_pendiente) {
                        $saldo_pend = max(0, $inv_monto - $abonado);
                    } else {
                        $saldo_pend = floatval($inv['saldo_nuevo'] ?? $inv['saldo'] ?? ($inv_monto - $abonado));
                    }

                    // Determinar si es factura recurrente (servicio mensual)
                    $fecha_emi = $inv['fecha_emision'] ?? '';
                    $fecha_venc = $inv['fecha_vencimiento'] ?? '';
                    $es_recurring = false;
                    if ($fecha_emi && $fecha_venc) {
                        $periodo_dias = max(1, round((strtotime($fecha_venc) - strtotime($fecha_emi)) / 86400));
                        $es_recurring = $periodo_dias > 1;
                    }

                    // Cobertura / promesa: se calcula siempre que haya abono parcial
                    $cobertura_dias = 0;
                    $cobertura_hasta = '';
                    $cobertura_restantes = 0;
                    $cobertura_vencida = false;
                    if ($abonado > 0 && $inv_monto > 0 && $abonado < $inv_monto) {
                        // Prioridad 1: usar fecha_promesa guardada en BD local (más exacta)
                        $fecha_promesa_bd = null;
                        if (isset($abonos_parciales[(int)$inv_id]['fecha_promesa'])) {
                            $fecha_promesa_bd = $abonos_parciales[(int)$inv_id]['fecha_promesa'];
                        }

                        if ($fecha_promesa_bd) {
                            $ts_cob = strtotime($fecha_promesa_bd);
                        } else {
                            // Fallback: calcular proporcionalmente desde HOY (igual que en procesar_pago_cliente)
                            $ratio = min(1.0, $abonado / $inv_monto);
                            $cobertura_dias = max(1, (int) round(30 * $ratio));
                            $ts_cob = time() + ($cobertura_dias * 86400);
                        }

                        if ($ts_cob) {
                            $cobertura_hasta = date('d/m/Y', $ts_cob);
                            $cobertura_restantes = (int) floor(($ts_cob - time()) / 86400);
                            $cobertura_vencida = $cobertura_restantes < 0;
                        }
                    }

                    // Enriquecer el arreglo de factura
                    $inv['reconstructed_total'] = $inv_monto;
                    $inv['reconstructed_abonado'] = $abonado;
                    $inv['reconstructed_saldo'] = $saldo_pend;
                    $inv['is_saldo_pendiente'] = $is_saldo_pendiente;
                    $inv['es_recurring'] = $es_recurring;
                    $inv['cobertura_hasta'] = $cobertura_hasta;
                    $inv['cobertura_vencida'] = $cobertura_vencida;
                    
                    if ($abonado > 0) {
                        $abonadas_invoices[] = $inv;
                    } else {
                        $pending_invoices[] = $inv;
                    }
                }

                $totalInvs = count($pending_invoices) + count($abonadas_invoices);
                ?>

                <?php if ($totalInvs > 0): ?>
                    
                    <!-- Sección de Facturas Pendientes (Sin Abonos) -->
                    <?php if (count($pending_invoices) > 0): ?>
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                <i class="fas fa-exclamation-circle text-primary me-2"></i> Recibos Pendientes
                            </h6>
                            <div class="recibos-list">
                                <?php foreach ($pending_invoices as $inv):
                                    $inv_id = $inv['id'] ?? $inv['id_factura'] ?? 0;
                                    $inv_monto = $inv['reconstructed_total'];
                                    $inv_monto_bs = $inv_monto * $tasa_bcv;
                                    $inv_desc = wisp_extract_desc($inv, $inv_id);
                                    if (mb_strlen($inv_desc) > 55)
                                        $inv_desc = mb_substr($inv_desc, 0, 55) . '...';
                                    $fecha_emi = $inv['fecha_emision'] ?? '';
                                    $fecha_venc = $inv['fecha_vencimiento'] ?? '';
                                    $vencida = $fecha_venc && strtotime($fecha_venc) < time();
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
                                                <a href="pago.php?id_contrato=<?php echo $wisp_service_id; ?>&recibo_id=<?php echo $inv_id; ?>" class="btn-pagar">
                                                    <i class="fas fa-credit-card"></i> Pagar
                                                </a>
                                            </div>

                                            <hr class="recibo-divider">

                                            <?php if ($inv['es_recurring'] && $fecha_venc): ?>
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
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sección de Facturas Abonadas (Con Promesas de Pago) -->
                    <?php if (count($abonadas_invoices) > 0): ?>
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fw-bold mb-3 mt-4" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                <i class="fas fa-hand-holding-usd text-success me-2"></i> Abonos y Promesas de Pago
                            </h6>
                            <div class="recibos-list">
                                <?php foreach ($abonadas_invoices as $inv):
                                    $inv_id = $inv['id'] ?? $inv['id_factura'] ?? 0;
                                    $inv_monto = $inv['reconstructed_total'];
                                    $abonado = $inv['reconstructed_abonado'];
                                    $saldo_pend = $inv['reconstructed_saldo'];
                                    $inv_monto_bs = $inv_monto * $tasa_bcv;
                                    $saldo_bs = $saldo_pend * $tasa_bcv;
                                    $inv_desc = wisp_extract_desc($inv, $inv_id);
                                    if (mb_strlen($inv_desc) > 55)
                                        $inv_desc = mb_substr($inv_desc, 0, 55) . '...';
                                    $fecha_emi = $inv['fecha_emision'] ?? '';
                                    $fecha_venc = $inv['fecha_vencimiento'] ?? '';
                                    $cobertura_hasta = $inv['cobertura_hasta'];
                                    $cobertura_vencida = $inv['cobertura_vencida'];
                                    $porcentaje = min(100, round(($abonado / $inv_monto) * 100));
                                    ?>
                                    <div class="recibo-card border-success" style="background: rgba(16, 185, 129, 0.02);">
                                        <!-- Cuerpo -->
                                        <div class="recibo-body">
                                            <div class="recibo-top">
                                                <div class="recibo-info">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="recibo-num">Recibo #<?php echo $inv_id; ?></span>
                                                        <span class="badge bg-success" style="font-size: 0.65rem;">Abonado</span>
                                                    </div>
                                                    <?php if ($fecha_emi): ?>
                                                        <span class="recibo-fecha"><i
                                                                class="fas fa-calendar-alt me-1"></i><?php echo date('d M Y', strtotime($fecha_emi)); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="recibo-montos">
                                                    <span class="recibo-usd text-success">$<?php echo number_format($inv_monto, 2); ?></span>
                                                    <span class="recibo-bs">Bs
                                                        <?php echo number_format($inv_monto_bs, 2, ',', '.'); ?></span>
                                                </div>
                                            </div>

                                            <!-- Descripción -->
                                            <div class="recibo-desc-row mb-3">
                                                <span class="recibo-desc"><?php echo htmlspecialchars($inv_desc); ?></span>
                                            </div>

                                            <!-- Barra de Progreso del Abono -->
                                            <div class="my-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.75rem;">
                                                    <span class="text-success"><i class="fas fa-wallet me-1"></i> Abonado: <strong>$<?php echo number_format($abonado, 2); ?></strong></span>
                                                    <span class="text-warning">Pendiente: <strong>$<?php echo number_format($saldo_pend, 2); ?></strong></span>
                                                </div>
                                                <div class="progress" style="height: 10px; background: rgba(255,255,255,0.08); border-radius: 5px; overflow: hidden;">
                                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                                                         style="width: <?php echo $porcentaje; ?>%; background: linear-gradient(90deg, #10b981, #3b82f6);" 
                                                         aria-valuenow="<?php echo $porcentaje; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1 text-muted" style="font-size: 0.7rem;">
                                                    <span>Servicio hasta: <strong><?php echo $cobertura_hasta ?: '-'; ?></strong></span>
                                                    <span><?php echo $porcentaje; ?>% Cubierto</span>
                                                </div>
                                            </div>

                                            <hr class="recibo-divider">

                                            <!-- Información de la Promesa de Pago -->
                                            <?php if ($cobertura_hasta): ?>
                                                <div class="recibo-row recibo-aviso my-2" style="background: rgba(14, 165, 233, 0.08); border-left: 3px solid #0ea5e9; border-radius: 4px; padding: 8px 12px;">
                                                    <?php if ($cobertura_vencida): ?>
                                                        <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>La promesa de pago venci&oacute; el <strong><?php echo $cobertura_hasta; ?></strong>. Tu servicio podría suspenderse pronto.</span>
                                                    <?php else: ?>
                                                        <span style="color: #bae6fd;"><i class="fas fa-calendar-check me-1 text-info"></i>Tienes hasta el <strong><?php echo $cobertura_hasta; ?></strong> para pagar los <strong>$<?php echo number_format($saldo_pend, 2); ?></strong> (Bs <?php echo number_format($saldo_bs, 2, ',', '.'); ?>) restantes.</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Botón para completar el pago del saldo pendiente -->
                                            <div class="mt-3">
                                                <a href="pago.php?id_contrato=<?php echo $wisp_service_id; ?>&recibo_id=<?php echo $inv_id; ?>" 
                                                   class="btn btn-warning btn-sm w-100 fw-bold" 
                                                   style="background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: #1a1a2e; border-radius: 10px; padding: 10px; letter-spacing: 0.3px;">
                                                    <i class="fas fa-credit-card me-2"></i>
                                                    Completar Pago — $<?php echo number_format($saldo_pend, 2); ?> pendientes
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Barra de acento inferior (verde/azul) -->
                                        <div class="recibo-accent-bar" style="background: linear-gradient(90deg, #10b981, #3b82f6);"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="pago.php?id_contrato=<?php echo $wisp_service_id; ?>" class="btn btn-premium btn-lg px-5">
                            <i class="fas fa-credit-card me-2"></i> Ir a Pagar
                        </a>
                    </div>

                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3" style="font-size:3rem;">&#127881;</div>
                        <h6 class="fw-bold text-success">&#161;Sin deudas pendientes!</h6>
                        <p class="text-muted mb-0 small">No tienes recibos pendientes de pago.</p>
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
            /* ── Servicios: cards ── */
            .services-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .service-card {
                display: flex;
                flex-direction: column;
                gap: 6px;
                padding: 12px 16px;
                border-radius: 12px;
                border: 1.5px solid var(--border-glass);
                background: var(--glass-bg);
                transition: all 0.2s ease;
            }

            .service-card:hover {
                border-color: rgba(59, 130, 246, 0.4);
            }

            .service-card.service-current {
                border-color: rgba(16, 185, 129, 0.4);
                background: rgba(16, 185, 129, 0.04);
            }

            .service-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }

            .service-id {
                font-weight: 700;
                font-size: 0.85rem;
                color: var(--text-primary);
            }

            .service-details {
                display: flex;
                flex-wrap: wrap;
                gap: 8px 16px;
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .service-badge {
                font-size: 0.65rem;
                padding: 2px 8px;
            }

            /* ── Recibos: cards premium en dashboard ── */
            .recibos-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .recibo-card {
                position: relative;
                display: flex;
                align-items: flex-start;
                gap: 14px;
                padding: 16px 18px;
                border-radius: 16px;
                border: 1.5px solid var(--border-glass);
                background: var(--glass-bg);
                overflow: hidden;
                transition: all 0.25s ease;
            }

            .recibo-card:hover {
                border-color: rgba(59, 130, 246, 0.5);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.12);
            }

            .recibo-card.recibo-vencida {
                border-color: rgba(239, 68, 68, 0.4);
            }

            /* Icono */
            .recibo-icon-wrap {
                flex-shrink: 0;
                width: 46px;
                height: 46px;
                border-radius: 12px;
                background: rgba(59, 130, 246, 0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                color: var(--primary);
            }

            .recibo-vencida .recibo-icon-wrap {
                background: rgba(239, 68, 68, 0.1);
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
                margin-bottom: 6px;
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

            .recibo-abono-label {
                color: var(--success);
            }

            .recibo-saldo-label {
                color: var(--warning);
            }

            .recibo-cobertura-label {
                color: var(--text-muted);
            }

            .recibo-aviso {
                font-size: 0.72rem;
                color: var(--text-muted);
                background: rgba(245, 158, 11, 0.06);
                padding: 4px 8px;
                border-radius: 8px;
                margin-top: 4px;
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

            /* Fila inferior: descripción + botón */
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