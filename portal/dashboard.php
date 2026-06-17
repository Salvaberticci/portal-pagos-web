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

// Tasa BCV (con cache de 1 hora)
$tasa_bcv = 1;
$tasa_fecha = '';
$cache_file = 'tasa_cache.json';
$cache_time = 3600;

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

// Obtener recibos pendientes
$invoices = $wispClient->getPendingInvoices($wisp_service_id);
$deuda_total = 0;
foreach ($invoices as $inv) {
    $deuda_total += floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
}

// Estado del servicio
$estado_ws = strtoupper($c_perfil['estado'] ?? 'ACTIVO');
if ($estado_ws === 'ACTIVE') $estado_ws = 'ACTIVO';
if ($estado_ws === 'SUSPENDED') $estado_ws = 'SUSPENDIDO';

// Mensaje de vencimiento dinámico
$mensaje_vencimiento = [
    'texto' => 'RECUERDA CANCELAR LOS PRIMEROS <span class="text-primary fs-5">5</span> DE CADA MES',
    'icono' => 'fas fa-bell text-primary',
    'bg' => 'linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(14, 165, 233, 0.1))',
    'border' => 'var(--primary)',
];

if (count($invoices) > 0) {
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
                'texto' => "¡TU RECIBO ESTÁ VENCIDO DESDE HACE <span class='text-danger fs-5'>$dias_abs</span> DÍA" . ($dias_abs > 1 ? 'S' : '') . "! (Venció el $fecha_str)",
                'icono' => 'fas fa-exclamation-triangle text-danger',
                'bg' => 'linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1))',
                'border' => 'var(--danger)',
            ];
        } elseif ($diferencia_dias <= 5) {
            $mensaje_vencimiento = [
                'texto' => "FALTAN <span class='text-warning fs-5'>$diferencia_dias</span> DÍAS PARA QUE VENZA TU RECIBO (Vence el $fecha_str)",
                'icono' => 'fas fa-calendar-day text-warning',
                'bg' => 'linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1))',
                'border' => 'var(--warning)',
            ];
        } else {
            $mensaje_vencimiento = [
                'texto' => "PRÓXIMO VENCIMIENTO EN <span class='text-info fs-5'>$diferencia_dias</span> DÍAS (Vence el $fecha_str)",
                'icono' => 'fas fa-calendar-check text-info',
                'bg' => 'linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(2, 132, 199, 0.1))',
                'border' => 'var(--info)',
            ];
        }
    }
} else {
    $mensaje_vencimiento = [
        'texto' => "¡ESTÁS AL DÍA! NO TIENES RECIBOS PENDIENTES.",
        'icono' => 'fas fa-check-circle text-success',
        'bg' => 'linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1))',
        'border' => 'var(--success)',
    ];
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
    <title>Mi Panel - Wireless Supply</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <header class="glass-header py-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="../images/logo-galanet.png" alt="Logo" class="me-3" style="height: 40px; border-radius: 6px;">
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
                <p class="mb-0 fw-bold" style="letter-spacing: 0.5px; color: #bae6fd;">
                    <i class="fas fa-info-circle me-2 text-info"></i>
                    MODO DE PRUEBA: Todos tus pagos serán procesados a exactamente <span class="text-info fs-5">Bs. 1,00</span>.
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
                <span class="badge bg-primary glass-panel p-2">Tasa BCV: Bs <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($tasa_bcv > 1): ?>
            <div class="d-block d-md-none mb-4 text-center">
                <span class="badge bg-primary glass-panel p-2 w-100">Tasa BCV: Bs <?php echo number_format($tasa_bcv, 2, ',', '.'); ?></span>
            </div>
        <?php endif; ?>

        <!-- Mensaje Dinámico -->
        <div class="glass-panel p-3 mb-4 text-center border-0 shadow-sm animate-pulse-slow" style="background: <?php echo $mensaje_vencimiento['bg']; ?>; border-left: 4px solid <?php echo $mensaje_vencimiento['border']; ?> !important;">
            <p class="mb-0 fw-bold" style="letter-spacing: 0.5px;">
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
        <?php if (isset($_SESSION['pago_err'])): ?>
            <div class="alert alert-danger glass-panel mb-4">
                <i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['pago_err'] ?? '', ENT_QUOTES, 'UTF-8'); unset($_SESSION['pago_err']); ?>
            </div>
        <?php endif; ?>

        <!-- Cabecera del Cliente -->
        <div class="glass-panel p-4 mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted d-block">Cliente</small>
                    <span class="fw-bold"><?php echo htmlspecialchars($c_perfil['nombre'] ?? $nombre); ?></span>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Estado</small>
                    <span class="status-badge <?php echo $estado_ws === 'ACTIVO' ? 'status-active' : 'status-suspended'; ?>"><?php echo $estado_ws; ?></span>
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
        </div>

        <!-- Recibos Pendientes -->
        <div class="glass-panel p-4 mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-file-invoice me-2 text-primary"></i> Recibos Pendientes</h5>
            <?php if (count($invoices) > 0): ?>
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th>Recibo</th>
                            <th>Descripción</th>
                            <th class="text-end">Monto USD</th>
                            <th class="text-end">Monto Bs</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv):
                            $inv_id = $inv['id'] ?? $inv['id_factura'] ?? 0;
                            $inv_monto = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
                            $inv_desc = '';
                            if (!empty($inv['articulos'][0]['descripcion'])) {
                                $inv_desc = $inv['articulos'][0]['descripcion'];
                            } elseif (!empty($inv['descripcion'])) {
                                $inv_desc = $inv['descripcion'];
                            } else {
                                $inv_desc = 'Recibo N° ' . $inv_id;
                            }
                            // Truncar a 80 caracteres
                            if (mb_strlen($inv_desc) > 80) {
                                $inv_desc = mb_substr($inv_desc, 0, 80) . '...';
                            }
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo $inv_id; ?></td>
                            <td><?php echo htmlspecialchars($inv_desc); ?></td>
                            <td class="text-end fw-bold">$<?php echo number_format($inv_monto, 2); ?></td>
                            <td class="text-end text-ves">Bs <?php echo number_format($inv_monto * $tasa_bcv, 2, ',', '.'); ?></td>
                            <td class="text-center">
                                <a href="pago.php?id_contrato=<?php echo $wisp_service_id; ?>&recibo_id=<?php echo $inv_id; ?>" class="btn btn-premium btn-sm">
                                    <i class="fas fa-credit-card me-1"></i> Pagar
                                </a>
                            </td>
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

        <footer class="text-center py-4 mt-5 border-top border-white border-opacity-10">
            <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Wireless Supply. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeBtn = document.getElementById('themeToggleBtn');
        const html = document.documentElement;
        const themeIcon = themeBtn.querySelector('i');
        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        updateThemeIcon(html.getAttribute('data-theme'));
        themeBtn.addEventListener('click', function() {
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        });
    });
    </script>
</body>
</html>
