<?php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

$cedula = $_SESSION['cliente_cedula'];
$nombre = $_SESSION['cliente_nombre'];

// Depuración de errores en servidor (Opcional, quitar en producción)
// error_reporting(E_ALL); ini_set('display_errors', 1);

// Cargar bancos para el reporte manual
$json_bancos = @file_get_contents('../paginas/principal/bancos.json');
$bancosArr = json_decode($json_bancos, true) ?: [];

// Intentar obtener tasa BCV (con cache de 1 hora para evitar lentitud)
$tasa_bcv = 1;
$tasa_fecha = '';
$cache_file = 'tasa_cache.json';
$cache_time = 3600; // 1 hora

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
$wispClient = new \Services\WispHubClient($wispConfig);

$wisp_service_id = $_SESSION['wisp_service_id'] ?? null;
if (!$wisp_service_id) {
    header('Location: auth.php?logout=1');
    exit;
}

// Obtener perfil del cliente
$profileRes = $wispClient->getServiceProfile($wisp_service_id);
$c_perfil = $profileRes['data'] ?? [];

// Obtener facturas pendientes (deuda)
$invoices = $wispClient->getPendingInvoices($wisp_service_id);
$deuda_mensualidades = 0;
foreach ($invoices as $inv) {
    $deuda_mensualidades += floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
}

$monto_plan = floatval($c_perfil['plan_internet_precio'] ?? 0);
if ($monto_plan <= 0 && count($invoices) > 0) {
    // Fallback: usar el monto de la factura más reciente si no hay info del plan
    $monto_plan = floatval($invoices[0]['monto'] ?? 0);
}

// Configurar el mensaje dinámico de vencimiento
$mensaje_vencimiento = [
    'texto' => 'RECUERDA CANCELAR LOS PRIMEROS <span class="text-primary fs-5">5</span> DE CADA MES',
    'icono' => 'fas fa-bell text-primary',
    'bg' => 'linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(14, 165, 233, 0.1))',
    'border' => 'var(--primary)',
    'text_class' => 'text-primary'
];

if (count($invoices) > 0) {
    // Buscar la fecha de vencimiento más antigua
    $fecha_vencimiento = null;
    foreach ($invoices as $inv) {
        if (!empty($inv['fecha_vencimiento'])) {
            $fv = strtotime($inv['fecha_vencimiento']);
            if ($fecha_vencimiento === null || $fv < $fecha_vencimiento) {
                $fecha_vencimiento = $fv;
            }
        }
    }
    
    if ($fecha_vencimiento) {
        $hoy = strtotime(date('Y-m-d'));
        $fv_date = strtotime(date('Y-m-d', $fecha_vencimiento));
        $diferencia_dias = round(($fv_date - $hoy) / 86400);
        $fecha_str = date('d/m/Y', $fecha_vencimiento);
        
        if ($diferencia_dias < 0) {
            $dias_abs = abs($diferencia_dias);
            $mensaje_vencimiento = [
                'texto' => "¡ATENCIÓN! TU FACTURA ESTÁ VENCIDA DESDE HACE <span class='text-danger fs-5'>$dias_abs</span> DÍA" . ($dias_abs > 1 ? 'S' : '') . " (Venció el $fecha_str)",
                'icono' => 'fas fa-exclamation-triangle text-danger',
                'bg' => 'linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1))',
                'border' => 'var(--danger)',
                'text_class' => 'text-danger'
            ];
        } elseif ($diferencia_dias == 0) {
            $mensaje_vencimiento = [
                'texto' => "¡ATENCIÓN! TU MENSUALIDAD VENCE <span class='text-warning fs-5'>HOY</span> ($fecha_str)",
                'icono' => 'fas fa-clock text-warning',
                'bg' => 'linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1))',
                'border' => 'var(--warning)',
                'text_class' => 'text-warning'
            ];
        } elseif ($diferencia_dias <= 5) {
            $mensaje_vencimiento = [
                'texto' => "FALTAN <span class='text-warning fs-5'>$diferencia_dias</span> DÍAS PARA QUE VENZA TU MENSUALIDAD (Vence el $fecha_str)",
                'icono' => 'fas fa-calendar-day text-warning',
                'bg' => 'linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1))',
                'border' => 'var(--warning)',
                'text_class' => 'text-warning'
            ];
        } else {
            $mensaje_vencimiento = [
                'texto' => "PRÓXIMO VENCIMIENTO EN <span class='text-info fs-5'>$diferencia_dias</span> DÍAS (Vence el $fecha_str)",
                'icono' => 'fas fa-calendar-check text-info',
                'bg' => 'linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(2, 132, 199, 0.1))',
                'border' => 'var(--info)',
                'text_class' => 'text-info'
            ];
        }
    }
} else {
    // Si no tiene deuda
    $mensaje_vencimiento = [
        'texto' => "¡ESTÁS AL DÍA! NO TIENES FACTURAS PENDIENTES.",
        'icono' => 'fas fa-check-circle text-success',
        'bg' => 'linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1))',
        'border' => 'var(--success)',
        'text_class' => 'text-success'
    ];
}

$contratos = [];
$estado_ws = strtoupper($c_perfil['estado'] ?? 'ACTIVO');
if ($estado_ws === 'ACTIVE') $estado_ws = 'ACTIVO';
if ($estado_ws === 'SUSPENDED') $estado_ws = 'SUSPENDIDO';

$contratos[] = [
    'id' => $wisp_service_id,
    'estado_contrato' => $estado_ws,
    'direccion' => $c_perfil['direccion'] ?? 'No especificada',
    'monto_plan' => $monto_plan,
    'nombre_plan' => $c_perfil['plan_internet_nombre'] ?? 'Servicio de Internet',
    'deuda_mensualidades' => $deuda_mensualidades
];

if ($cedula === TEST_USER_CEDULA) {
    if ($contratos[0]['deuda_mensualidades'] > 0) {
        $contratos[0]['deuda_mensualidades'] = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
    }
    $contratos[0]['monto_plan'] = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
}

// Portal sin base de datos local: los estados de pagos se gestionan
// directamente desde WispHub. No hay pagos_recientes locales.
$pagos_recientes = [];
$ultimo_pago = null;
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <script>
        // Iniciar tema lo más rápido posible para evitar parpadeo
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - Wireless Supply</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- Header -->
    <header class="glass-header py-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="../images/logo-galanet.png" alt="Logo Galanet" class="me-3" style="height: 40px; border-radius: 6px;">
                <h5 class="mb-0 fw-bold d-none d-sm-block text-gradient">Portal de Clientes</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="theme-toggle" id="themeToggleBtn" title="Cambiar Tema">
                    <i class="fas fa-sun"></i>
                </button>
                <div>
                    <span class="me-3 text-muted d-none d-md-inline" style="font-size: 0.9rem;"><i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($nombre); ?></span>
                    <a href="auth.php?logout=1" class="btn btn-sm btn-glass text-danger border-danger"><i class="fas fa-sign-out-alt"></i> Salir</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container main-container animate-fade">
        <?php if ($cedula === TEST_USER_CEDULA): ?>
            <div class="alert alert-info glass-panel mb-4 text-center border-0 shadow-sm" style="background: rgba(14, 165, 233, 0.15); border-left: 4px solid #0ea5e9 !important; border-radius: 12px;">
                <p class="mb-0 fw-bold text-main" style="letter-spacing: 0.5px; color: #bae6fd;">
                    <i class="fas fa-info-circle me-2 text-info"></i> 
                    MODO DE PRUEBA: Iniciaste sesión como usuario de prueba. Todos tus pagos serán facturados a exactamente <span class="text-info fs-5">Bs. 1,00</span> para pruebas de la API.
                </p>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="mb-1 text-gradient">Gestión de Mensualidades</h2>
                <p class="text-muted mb-0">Revisa tus mensualidades, historial de pagos y mantente al día.</p>
            </div>
            <?php if ($tasa_bcv > 1): ?>
            <div class="text-end d-none d-md-block">
                <span class="badge bg-primary glass-panel p-2">Tasa BCV: Bs <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
                <div class="small text-muted mt-1" style="font-size: 0.75rem;">Ref: <?php echo $tasa_fecha; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($tasa_bcv > 1): ?>
            <div class="d-block d-md-none mb-4 text-center">
                <span class="badge bg-primary glass-panel p-2 w-100">Tasa BCV: Bs <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
            </div>
        <?php endif; ?>

        <!-- Mensaje Recordatorio Dinámico -->
        <div class="glass-panel p-3 mb-4 text-center border-0 shadow-sm animate-pulse-slow" style="background: <?php echo $mensaje_vencimiento['bg']; ?>; border-left: 4px solid <?php echo $mensaje_vencimiento['border']; ?> !important;">
            <p class="mb-0 fw-bold text-main" style="letter-spacing: 0.5px;">
                <i class="<?php echo $mensaje_vencimiento['icono']; ?> me-2"></i> 
                <?php echo $mensaje_vencimiento['texto']; ?>
            </p>
        </div>

        <?php if (isset($_SESSION['pago_msg'])): ?>
            <div class="alert alert-success glass-panel mb-4" id="alert-pago-ok">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo strip_tags($_SESSION['pago_msg'] ?? '', '<strong><span><br>'); unset($_SESSION['pago_msg']); ?>
            </div>
            <script>
                setTimeout(() => {
                    const a = document.getElementById('alert-pago-ok');
                    if (a) { a.style.transition = 'opacity 0.6s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 650); }
                }, 6000);
            </script>
        <?php endif; ?>
        <?php if (isset($_SESSION['pago_pendiente'])) { unset($_SESSION['pago_pendiente']); } ?>
        <?php if (isset($_SESSION['pago_err'])): ?>
            <div class="alert alert-danger glass-panel mb-4">
                <i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['pago_err'] ?? '', ENT_QUOTES, 'UTF-8'); unset($_SESSION['pago_err']); ?>
            </div>
        <?php endif; ?>



        <div class="row g-4">
            <?php if (empty($contratos)): ?>
                <div class="col-12 text-center py-5 glass-panel">
                    <i class="fas fa-satellite-dish fa-3x text-muted mb-3"></i>
                    <h4>No se encontraron servicios asociados</h4>
                    <p class="text-muted">Si crees que esto es un error, por favor contacta a soporte técnico.</p>
                </div>
            <?php else: ?>
                <?php foreach ($contratos as $c): ?>
                    <div class="col-12">
                        <div class="glass-panel p-4 contract-card">
                            <div class="row">
                                <!-- Info Col -->
                                <div class="col-md-7 mb-4 mb-md-0 border-end border-secondary border-opacity-25 pe-md-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h4 class="mb-1 fw-bold">Contrato #<?php echo $c['id']; ?></h4>
                                            <?php 
                                                $badge_class = 'status-active';
                                                if ($c['estado_contrato'] === 'SUSPENDIDO') $badge_class = 'status-suspended';
                                                if ($c['estado_contrato'] === 'POR INSTALAR') $badge_class = 'status-pending';
                                            ?>
                                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($c['estado_contrato']); ?></span>
                                            <span class="badge bg-info text-dark ms-2"><i class="fas fa-bolt me-1"></i> <?php echo htmlspecialchars($c['nombre_plan']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <p class="text-muted small mb-3"><i class="fas fa-map-marker-alt me-1 text-primary"></i> <?php echo htmlspecialchars($c['direccion']); ?></p>

                                    <div class="d-flex flex-wrap gap-2 mb-4">
                                        <div class="glass-panel p-2 px-3 d-flex align-items-center border-0" style="background: var(--border-glass);">
                                            <i class="fas fa-circle-check me-2 <?php echo $c['deuda_mensualidades'] > 0 ? 'text-warning' : 'text-success'; ?>"></i>
                                            <div>
                                                <span class="text-muted d-block" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;">ESTADO DE PAGO</span>
                                                <span class="fw-bold small">
                                                    <?php echo $c['deuda_mensualidades'] > 0 ? 'Pago Pendiente' : 'Al día'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Payment Col -->
                                <div class="col-md-5 ps-md-4 d-flex flex-column justify-content-center">
                                    <div class="payment-summary-box mb-4 shadow-sm">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small fw-semibold">Tarifa Mensual</span>
                                            <span class="fw-bold">$<?php echo number_format($c['monto_plan'], 2); ?></span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted small fw-semibold">Deuda Pendiente</span>
                                            <span class="fs-4 fw-bold <?php echo ($c['deuda_mensualidades'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                $<?php echo number_format($c['deuda_mensualidades'], 2); ?>
                                            </span>
                                        </div>
                                        <?php if ($tasa_bcv > 1 && $c['deuda_mensualidades'] > 0): ?>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <span class="text-muted small" style="font-size: 0.75rem;">Equivalente en Bs</span>
                                            <span class="text-muted fw-bold" style="font-size: 0.9rem;">
                                                Bs <?php echo number_format($c['deuda_mensualidades'] * $tasa_bcv, 2, ',', '.'); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($c['deuda_mensualidades'] > 0): ?>
                                        <a href="pago.php?id_contrato=<?php echo $c['id']; ?>" class="btn btn-premium w-100 py-3">
                                            <i class="fas fa-credit-card me-2"></i> PROCEDER AL PAGO
                                        </a>
                                    <?php else: ?>
                                        <a href="pago.php?id_contrato=<?php echo $c['id']; ?>" class="btn btn-premium w-100">
                                            <i class="fas fa-arrow-up me-2"></i> ADELANTAR PAGO
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <footer class="text-center py-4 mt-5 border-top border-white border-opacity-10">
            <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Wireless Supply. Todos los derechos reservados.</p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="../js/bootstrap.bundle.min.js"></script>

    <!-- GESTIÓN DE TEMA -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeBtn = document.getElementById('themeToggleBtn');
        const html = document.documentElement;
        const themeIcon = themeBtn.querySelector('i');

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.className = 'fas fa-sun';
            } else {
                themeIcon.className = 'fas fa-moon';
            }
        }

        updateThemeIcon(html.getAttribute('data-theme'));

        themeBtn.addEventListener('click', function() {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
    });


    </script>
</body>
</html>
