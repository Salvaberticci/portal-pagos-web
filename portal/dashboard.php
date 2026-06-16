<?php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

require '../paginas/conexion.php';
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

// Obtener contratos del cliente y su plan (Optimizado con JOIN)
$contratos = [];
$sql_contratos = "
    SELECT c.id, c.estado as estado_contrato, c.direccion, c.monto_plan, p.nombre_plan,
           SUM(CASE WHEN cxc.estado IN ('PENDIENTE', 'VENCIDO') THEN cxc.monto_total ELSE 0 END) as deuda_mensualidades,
           MIN(CASE WHEN cxc.estado IN ('PENDIENTE', 'VENCIDO') THEN cxc.fecha_vencimiento ELSE NULL END) as vencimiento_pendiente
    FROM contratos c
    LEFT JOIN planes p ON c.id_plan = p.id_plan
    LEFT JOIN cuentas_por_cobrar cxc ON cxc.id_contrato = c.id
    WHERE c.cedula = ? AND c.estado != 'ELIMINADO'
    GROUP BY c.id, c.estado, c.direccion, c.monto_plan, p.nombre_plan
";
$stmt = $conn->prepare($sql_contratos);
if ($stmt) {
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $res = $stmt->get_result();
    $contratoIds = [];
    while ($row = $res->fetch_assoc()) {
        $row['deuda_mensualidades'] = floatval($row['deuda_mensualidades'] ?? 0);
        if ($cedula === TEST_USER_CEDULA) {
            if ($row['deuda_mensualidades'] > 0) {
                $row['deuda_mensualidades'] = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
            }
            $row['monto_plan'] = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
        }
        $row['nombre_plan'] = $row['nombre_plan'] ?: 'Plan Básico';
        $row['historial'] = [];
        $contratoIds[] = $row['id'];
        $contratos[$row['id']] = $row;
    }

    // Batch fetch historial para todos los contratos (N+1 → 1 query)
    if (!empty($contratoIds)) {
        $in = implode(',', array_map('intval', $contratoIds));
        $histBatch = $conn->query("SELECT id_contrato, fecha_pago, monto_total, estado, referencia_pago
            FROM cuentas_por_cobrar
            WHERE id_contrato IN ($in) AND estado IN ('PAGADO', 'PENDIENTE')
            ORDER BY fecha_emision DESC");
        if ($histBatch) {
            while ($h = $histBatch->fetch_assoc()) {
                $cid = $h['id_contrato'];
                if (isset($contratos[$cid]) && count($contratos[$cid]['historial']) < 5) {
                    $contratos[$cid]['historial'][] = $h;
                }
            }
        }
    }
    $contratos = array_values($contratos); // back to indexed array
}

// NUEVO: Obtener estados de pagos reportados
$pagos_recientes = [];
$sql_pagos = "
    SELECT id_reporte, estado, fecha_registro, monto_bs, referencia, meses_pagados, fecha_pago, monto_usd, motivo_rechazo
    FROM pagos_reportados 
    WHERE cedula_titular = ? 
      AND visto_por_cliente = 0
      AND (estado = 'PENDIENTE' OR ((estado = 'APROBADO' OR estado = 'RECHAZADO') AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 2 DAY)))
    ORDER BY fecha_registro DESC
";
$stmt_pagos = $conn->prepare($sql_pagos);
if ($stmt_pagos) {
    $stmt_pagos->bind_param("s", $cedula);
    $stmt_pagos->execute();
    $res_pagos = $stmt_pagos->get_result();
    while ($p = $res_pagos->fetch_assoc()) {
        $pagos_recientes[] = $p;
    }
    $stmt_pagos->close();
}

// Obtener el último pago de mensualidad realizado por el cliente
$ultimo_pago = null;
$sql_ultimo_pago = "
    SELECT cxc.fecha_pago, cxc.fecha_emision, cxc.monto_total, h.justificacion, c.id AS id_contrato, p.nombre_plan
    FROM cuentas_por_cobrar cxc
    LEFT JOIN cobros_manuales_historial h ON cxc.id_cobro = h.id_cobro_cxc
    JOIN contratos c ON cxc.id_contrato = c.id
    LEFT JOIN planes p ON c.id_plan = p.id_plan
    WHERE c.cedula = ? 
      AND cxc.estado = 'PAGADO' 
      AND (
          h.justificacion LIKE '%[MENSUALIDAD]%' 
          OR h.justificacion IS NULL 
          OR h.justificacion = ''
          OR (
              h.justificacion NOT LIKE '%[INSTALACION]%' 
              AND h.justificacion NOT LIKE '%[EQUIPOS]%' 
              AND h.justificacion NOT LIKE '%[PRORRATEO]%'
          )
      )
    ORDER BY cxc.fecha_pago DESC, cxc.id_cobro DESC
    LIMIT 1
";
$stmt_last = $conn->prepare($sql_ultimo_pago);
if ($stmt_last) {
    $stmt_last->bind_param("s", $cedula);
    $stmt_last->execute();
    $res_last = $stmt_last->get_result();
    if ($res_last->num_rows > 0) {
        $ultimo_pago = $res_last->fetch_assoc();
        
        $meses_es = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $ultimo_pago['mes'] = 'N/A';
        $justif = $ultimo_pago['justificacion'] ?? '';
        
        if (preg_match('/\[(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)\]/i', $justif, $matches)) {
            $ultimo_pago['mes'] = $matches[1];
        } else if (preg_match('/(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)/i', $justif, $matches)) {
            $ultimo_pago['mes'] = $matches[1];
        } else {
            $date_to_use = !empty($ultimo_pago['fecha_emision']) ? $ultimo_pago['fecha_emision'] : $ultimo_pago['fecha_pago'];
            if ($date_to_use) {
                $month_num = intval(date('n', strtotime($date_to_use)));
                $ultimo_pago['mes'] = $meses_es[$month_num] ?? 'N/A';
            }
        }
    }
    $stmt_last->close();
}
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

        <!-- Mensaje Recordatorio Premium -->
        <div class="glass-panel p-3 mb-4 text-center border-0 shadow-sm animate-pulse-slow" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(14, 165, 233, 0.1)); border-left: 4px solid var(--primary) !important;">
            <p class="mb-0 fw-bold text-main" style="letter-spacing: 0.5px;">
                <i class="fas fa-bell me-2 text-primary"></i> 
                RECUERDA CANCELAR LOS PRIMEROS <span class="text-primary fs-5">5</span> DE CADA MES
            </p>
        </div>

        <?php if (isset($_SESSION['pago_msg'])): ?>
            <div class="alert alert-success glass-panel mb-4" id="alert-pago-ok">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['pago_msg'] ?? '', ENT_QUOTES, 'UTF-8'); unset($_SESSION['pago_msg']); ?>
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

        <!-- TARJETA: ÚLTIMO PAGO REGISTRADO -->
        <?php if ($ultimo_pago): ?>
        <div class="glass-panel p-3 mb-4 border-0 shadow-sm d-flex align-items-center gap-3 flex-wrap ultimo-pago-card">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 46px; height: 46px; background: rgba(16, 185, 129, 0.12);">
                <i class="fas fa-receipt text-success"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold text-main" style="font-size: 0.92rem;">
                    Último Pago: <span class="text-success"><?php echo htmlspecialchars($ultimo_pago['mes']); ?></span>
                    &nbsp;&mdash;&nbsp;
                    <span class="text-success fw-bold">$<?php echo number_format($ultimo_pago['monto_total'], 2); ?></span>
                </div>
                <div class="small text-muted mt-1">
                    <i class="fas fa-calendar-check me-1"></i>
                    Registrado el <?php echo date('d/m/Y', strtotime($ultimo_pago['fecha_pago'])); ?>
                    &nbsp;|&nbsp;
                    <i class="fas fa-satellite-dish me-1"></i><?php echo htmlspecialchars($ultimo_pago['nombre_plan']); ?>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-success px-3 py-2">
                    <i class="fas fa-check me-1"></i>PAGADO
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECCIÓN DE ESTADOS DE PAGO -->
        <?php if (!empty($pagos_recientes)): ?>
            <div class="mb-4">
                <?php foreach ($pagos_recientes as $pago): ?>
                    <?php if ($pago['estado'] === 'PENDIENTE'): ?>
                        <div class="glass-panel p-3 mb-2 border-start border-warning border-4 shadow-sm animate-fade" id="notif_<?php echo $pago['id_reporte']; ?>" style="background: rgba(245, 158, 11, 0.05);">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 mt-1 flex-shrink-0">
                                        <i class="fas fa-clock text-warning"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-main mb-1">
                                            Pago en revisión &mdash; Ref: <span class="text-warning"><?php echo htmlspecialchars($pago['referencia']); ?></span>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            Monto: <strong>$<?php echo number_format($pago['monto_usd'], 2); ?></strong>
                                            &nbsp;&bull;&nbsp; Fecha: <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?>
                                        </div>
                                        <?php if (!empty($pago['motivo_rechazo'])): ?>
                                            <div class="small mt-1" style="color: #f59e0b;">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                <strong>Motivo:</strong> <?php echo htmlspecialchars($pago['motivo_rechazo']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="small text-muted mt-1">Tu pago está siendo verificado por nuestro equipo.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
                                    <div class="badge bg-warning text-dark">PENDIENTE</div>
                                    <button class="btn btn-sm text-muted p-0" onclick="dismissNotif(<?php echo $pago['id_reporte']; ?>)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($pago['estado'] === 'APROBADO'): ?>
                        <div class="glass-panel p-3 mb-2 border-start border-success border-4 shadow-sm animate-fade" id="notif_<?php echo $pago['id_reporte']; ?>" style="background: rgba(16, 185, 129, 0.05);">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                                        <i class="fas fa-check-circle text-success"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-main">¡Pago Aprobado!</div>
                                        <div class="small text-muted">Tu pago de la fecha <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?> ha sido verificado correctamente.</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="badge bg-success me-2">APROBADO</div>
                                    <button class="btn btn-sm text-muted p-0" onclick="dismissNotif(<?php echo $pago['id_reporte']; ?>)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($pago['estado'] === 'RECHAZADO'): ?>
                        <div class="glass-panel p-3 mb-2 border-start border-danger border-4 shadow-sm animate-fade" id="notif_<?php echo $pago['id_reporte']; ?>" style="background: rgba(239, 68, 68, 0.05);">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-danger bg-opacity-10 p-2 me-3">
                                        <i class="fas fa-times-circle text-danger"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-main">Pago Rechazado (Ref: <?php echo $pago['referencia']; ?>)</div>
                                        <div class="small text-danger fw-bold">Motivo: <?php echo htmlspecialchars($pago['motivo_rechazo'] ?: 'No especificado'); ?></div>
                                        <div class="small text-muted mt-1">Por favor, verifica tus datos e intenta reportar de nuevo.</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="badge bg-danger me-2">RECHAZADO</div>
                                    <button class="btn btn-sm text-muted p-0" onclick="dismissNotif(<?php echo $pago['id_reporte']; ?>)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
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

    function dismissNotif(id) {
        fetch('marcar_leido_pago.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    const el = document.getElementById('notif_' + id);
                    if (el) {
                        el.style.opacity = '0';
                        setTimeout(() => el.remove(), 300);
                    }
                }
            });
    }
    </script>
</body>
</html>
