<?php
require_once '../conexion.php';

// 1. CAPTURA Y SANEO DE PARÁMETROS DE FILTRO
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'TODOS';
$banco_filtro = isset($_GET['id_banco']) ? $_GET['id_banco'] : '';
$origen_filtro = isset($_GET['origen']) ? $_GET['origen'] : '';
$ref_filtro = isset($_GET['referencia']) ? $_GET['referencia'] : '';
$plan_filtro = isset($_GET['id_plan']) ? $_GET['id_plan'] : '';
$sae_plus_filtro = isset($_GET['estado_sae_plus']) ? $_GET['estado_sae_plus'] : 'TODOS';
$mes_cobrado = isset($_GET['mes_cobrado']) ? $_GET['mes_cobrado'] : '';

// 1.1 ESTADÍSTICAS GLOBALES (METAS)
$total_contratos_periodo = 0;
$unique_contracts_map = [];
$total_cobros_periodo = 0;
$total_facturado_periodo = 0;

$cobros = [];
$total_cobrado = 0; 
$deuda_clientes = 0; 

// 2. CONSTRUCCIÓN DINÁMICA DE LA CLÁUSULA WHERE
$where_clause = " WHERE 1=1 ";
$params = [];
$types = '';

if ($estado_filtro !== 'TODOS') {
    $where_clause .= " AND cxc.estado = ? ";
    $params[] = $estado_filtro;
    $types .= 's';
}

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $where_clause .= " AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) >= ? AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) <= ? ";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
    $types .= 'ss';
}

if (!empty($mes_cobrado)) {
    // Mapeo de meses en español a números para el fallback de fecha_emision
    $mesesMapNum = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12
    ];
    $numMes = isset($mesesMapNum[$mes_cobrado]) ? $mesesMapNum[$mes_cobrado] : 0;

    $where_clause .= " AND (
        cxc.id_cobro IN (SELECT id_cobro_cxc FROM cobros_manuales_historial WHERE justificacion LIKE ?)
        OR 
        (
            NOT EXISTS (SELECT 1 FROM cobros_manuales_historial WHERE id_cobro_cxc = cxc.id_cobro) 
            AND MONTH(cxc.fecha_emision) = ?
        )
    )";
    $params[] = "%[$mes_cobrado]%";
    $params[] = $numMes;
    $types .= 'si';
}

if (!empty($banco_filtro)) {
    $where_clause .= " AND cxc.id_banco = ? ";
    $params[] = $banco_filtro;
    $types .= 'i';
}

if (!empty($origen_filtro)) {
    $where_clause .= " AND cxc.origen = ? ";
    $params[] = $origen_filtro;
    $types .= 's';
}

if (!empty($ref_filtro)) {
    $where_clause .= " AND cxc.referencia_pago LIKE ? ";
    $params[] = "%$ref_filtro%";
    $types .= 's';
}

if (!empty($plan_filtro)) {
    $where_clause .= " AND co.id_plan = ? ";
    $params[] = $plan_filtro;
    $types .= 'i';
}

// Filtro SAE Plus: solo se aplica a la lista de resultados (NO a los totales globales)
if ($sae_plus_filtro !== 'TODOS') {
    $where_clause .= " AND cxc.estado_sae_plus = ? ";
    $params[] = $sae_plus_filtro;
    $types .= 's';
}


// 3. CARGAR BANCOS DESDE JSON (fuente maestra del sistema) — debe ir antes de cualquier uso
$bancos_json_path = dirname(__FILE__) . '/../principal/bancos.json';
$lista_bancos = [];
if (file_exists($bancos_json_path)) {
    $bancos_raw = json_decode(file_get_contents($bancos_json_path), true);
    if ($bancos_raw) {
        usort($bancos_raw, fn($a, $b) => strcmp($a['nombre_banco'], $b['nombre_banco']));
        $lista_bancos = $bancos_raw;
    }
}
// Fallback a SQL si el JSON falla
if (empty($lista_bancos)) {
    $bancos_res = $conn->query("SELECT id_banco, nombre_banco FROM bancos ORDER BY nombre_banco ASC");
    if ($bancos_res) {
        while ($b = $bancos_res->fetch_assoc()) $lista_bancos[] = $b;
    }
}

// Construir mapa id_banco => nombre_banco para resolución rápida en PHP
$bancosJsonMap = [];
foreach ($lista_bancos as $bj) {
    $bancosJsonMap[(string)$bj['id_banco']] = $bj['nombre_banco'];
}

// 4. CONSULTA SQL PARA LA TABLA (DETALLADA)

$sql = "
    SELECT 
        cxc.id_cobro, 
        cxc.id_contrato,
        cxc.estado_sae_plus,
        cxc.fecha_emision, 
        cxc.fecha_vencimiento, 
        cxc.monto_total, 
        cxc.estado,
        cxc.referencia_pago,
        cxc.origen,
        cxc.id_banco,
        co.nombre_completo AS cliente,
        co.ip_onu,
        p.nombre_plan,
        DATEDIFF(CURRENT_DATE(), cxc.fecha_vencimiento) AS dias_vencido
    FROM cuentas_por_cobrar cxc
    JOIN contratos co ON cxc.id_contrato = co.id
    LEFT JOIN planes p ON co.id_plan = p.id_plan
    " . $where_clause . "
    ORDER BY cxc.fecha_emision DESC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($fila = $resultado->fetch_assoc()) {
        $cobros[] = $fila;
    }
    $stmt->close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. CÁLCULO DE KPIs E INDICES (SEPARADO PARA PRECISIÓN)
// ─────────────────────────────────────────────────────────────────────────────

// A. Totales del Período (Afectados solo por Fecha y Plan, si aplica)
$where_kpi = " WHERE 1=1 ";
$params_kpi = [];
$types_kpi = '';

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    // Usamos COALESCE para capturar tanto pagos como emisiones en el rango
    $where_kpi .= " AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) >= ? AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) <= ? ";
    $params_kpi[] = $fecha_inicio; $params_kpi[] = $fecha_fin;
    $types_kpi .= 'ss';
}
if (!empty($plan_filtro)) {
    $where_kpi .= " AND co.id_plan = ? ";
    $params_kpi[] = $plan_filtro; $types_kpi .= 'i';
}

// Query Unificada de Totales
$sql_kpi = "
    SELECT 
        COALESCE(SUM(cxc.monto_total), 0) AS facturacion_total,
        COUNT(cxc.id_cobro) AS cant_cobros,
        SUM(CASE WHEN (TRIM(cxc.estado_sae_plus) = 'CARGADO' OR (co.sae_plus IS NOT NULL AND co.sae_plus != '')) AND cxc.estado = 'PAGADO' THEN cxc.monto_total ELSE 0 END) AS total_cobrado_sae,
        SUM(CASE WHEN cxc.estado = 'PENDIENTE' THEN cxc.monto_total ELSE 0 END) AS deuda_pendiente
    FROM cuentas_por_cobrar cxc
    JOIN contratos co ON cxc.id_contrato = co.id
    $where_kpi
";
$stmt_kpi = $conn->prepare($sql_kpi);
if ($stmt_kpi) {
    if (!empty($params_kpi)) $stmt_kpi->bind_param($types_kpi, ...$params_kpi);
    $stmt_kpi->execute();
    $res_kpi = $stmt_kpi->get_result()->fetch_assoc();
    $total_facturado_periodo = (float)$res_kpi['facturacion_total'];
    $total_cobros_periodo = (int)$res_kpi['cant_cobros'];
    $total_cobrado = (float)$res_kpi['total_cobrado_sae'];
    $deuda_clientes = (float)$res_kpi['deuda_pendiente'];
    $stmt_kpi->close();
}

// B. Referencias Globales (Sistema Completo)
$res_global_c = $conn->query("SELECT COUNT(*) as total FROM contratos WHERE estado = 'ACTIVO'");
$total_contratos_activos = $res_global_c ? $res_global_c->fetch_assoc()['total'] : 0;

// B. Desglose por Planes (Período)
$sql_planes = "
    SELECT p.nombre_plan, COUNT(cxc.id_cobro) as cantidad, SUM(cxc.monto_total) as subtotal
    FROM cuentas_por_cobrar cxc
    JOIN contratos co ON cxc.id_contrato = co.id
    JOIN planes p ON co.id_plan = p.id_plan
    $where_kpi
    GROUP BY p.id_plan
    ORDER BY cantidad DESC
";
$stmt_pl = $conn->prepare($sql_planes);
if ($stmt_pl) {
    if (!empty($params_kpi)) $stmt_pl->bind_param($types_kpi, ...$params_kpi);
    $stmt_pl->execute();
    $desglose_planes = $stmt_pl->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pl->close();
}

// C. Referencias Globales (Sistema Completo)
$res_global_c = $conn->query("SELECT COUNT(*) as total FROM contratos");
$global_contratos = $res_global_c ? $res_global_c->fetch_assoc()['total'] : 0;

$res_global_u = $conn->query("SELECT COUNT(DISTINCT cedula) as total FROM contratos");
$global_clientes = $res_global_u ? $res_global_u->fetch_assoc()['total'] : 0;


// $lista_bancos ya fue cargado antes de la query principal (ver arriba)

// Obtener planes para el filtro
$planes_res = $conn->query("SELECT id_plan, nombre_plan FROM planes ORDER BY nombre_plan ASC");
$lista_planes = [];
if ($planes_res) {
    while($p = $planes_res->fetch_assoc()) $lista_planes[] = $p;
}


// --- TEMPLATE START ---
$path_to_root = "../../";
$page_title = "Reporte de Cobranzas";
$breadcrumb = ["Reportes"];
$back_url = "../menu.php";
include $path_to_root . 'paginas/includes/layout_head.php';
?>

<!-- Estilos DataTables y Refinamiento Premium -->
<link href="<?php echo $path_to_root; ?>css/datatables.min.css" rel="stylesheet">
<style>
    .section-title {
        background: rgba(0, 0, 0, 0.05) !important;
        border-left: 4px solid var(--primary);
        color: var(--text-main) !important;
        padding: 8px 12px !important;
        margin-bottom: 15px !important;
        font-weight: 700 !important;
        border-radius: 4px;
    }
    .info-box-premium {
        background: rgba(0, 0, 0, 0.03) !important;
        border: 1px solid rgba(0,0,0,0.05) !important;
        border-radius: 12px !important;
        padding: 1.2rem !important;
        height: 100%;
    }
    [data-theme="dark"] .section-title {
        background: rgba(255, 255, 255, 0.05) !important;
    }
    [data-theme="dark"] .info-box-premium {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255,255,255,0.1) !important;
    }
    .table-premium th,
    .table-premium td {
        text-align: center !important;
        vertical-align: middle !important;
        padding: 12px 8px !important;
    }
    .table-premium thead th {
        background: var(--table-header-bg) !important;
        color: var(--text-main) !important;
        border: none !important;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background: var(--bg-card) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--border-glass) !important;
        border-radius: 10px !important;
        padding: 5px 12px !important;
        font-size: 0.85rem !important;
        outline: none !important;
        transition: all 0.3s ease;
    }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1) !important;
    }
    [data-theme="dark"] .dataTables_wrapper .dataTables_filter input,
    [data-theme="dark"] .dataTables_wrapper .dataTables_length select {
        background: rgba(255, 255, 255, 0.05) !important;
    }
    .dataTables_wrapper .dataTables_info {
        color: var(--text-muted) !important;
        font-size: 0.85rem !important;
        font-weight: 500;
    }
</style>

<main class="main-content">
    <?php include $path_to_root . 'paginas/includes/header.php'; ?>

    <div class="page-content">
        <!-- Header Section -->
        <div class="mb-4">
            <h2 class="h3 fw-bold mb-1 text-gradient">Reporte de Cobranzas</h2>
            <p class="text-muted mb-0"><i class="fa-solid fa-chart-line me-2"></i>Análisis financiero y control de recaudación</p>
        </div>

        <!-- 1. BARRA DE FILTROS (COMPACTA Y MODERNA) -->
        <div class="card glass-panel border-0 shadow-lg mb-4">
            <div class="card-body p-4">
                <form action="reporte_cobranza.php" method="GET" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Desde</label>
                        <input type="date" class="form-control form-control-sm border-0 bg-light" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Hasta</label>
                        <input type="date" class="form-control form-control-sm border-0 bg-light" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Banco / Caja</label>
                        <select class="form-select form-select-sm border-0 bg-light" name="id_banco">
                            <option value="">TODOS LOS BANCOS</option>
                            <?php foreach ($lista_bancos as $b): 
                                $ultimosCuatro = !empty($b['numero_cuenta']) ? ' (' . substr($b['numero_cuenta'], -4) . ')' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($b['id_banco']); ?>" <?php echo ($banco_filtro == $b['id_banco']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['nombre_banco'] . $ultimosCuatro); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Plan</label>
                        <select class="form-select form-select-sm border-0 bg-light" name="id_plan">
                            <option value="">TODOS LOS PLANES</option>
                            <?php foreach ($lista_planes as $p): ?>
                                <option value="<?php echo $p['id_plan']; ?>" <?php echo ($plan_filtro == $p['id_plan']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre_plan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Mes que Pagó</label>
                        <select class="form-select form-select-sm border-0 bg-light" name="mes_cobrado">
                            <option value="">CUALQUIER MES</option>
                            <?php 
                            $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                            foreach ($meses as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($mes_cobrado == $m) ? 'selected' : ''; ?>>
                                    <?php echo $m; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Carga SAE</label>
                        <select class="form-select form-select-sm border-0 bg-light" name="estado_sae_plus">
                            <option value="TODOS" <?php echo ($sae_plus_filtro == 'TODOS') ? 'selected' : ''; ?>>VER TODO</option>
                            <option value="CARGADO" <?php echo ($sae_plus_filtro == 'CARGADO') ? 'selected' : ''; ?>>SÓLO CARGADO</option>
                            <option value="NO CARGADO" <?php echo ($sae_plus_filtro == 'NO CARGADO') ? 'selected' : ''; ?>>NO CARGADO</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                        </button>
                        <?php $export_params = http_build_query($_GET); ?>
                        <a href="exportar_cobranza_excel.php?<?php echo $export_params; ?>" class="btn btn-success btn-sm" title="Exportar Excel">
                            <i class="fa-solid fa-file-excel"></i>
                        </a>
                        <a href="exportar_cuentas_por_cobrar.php?<?php echo $export_params; ?>" class="btn btn-danger btn-sm" target="_blank" title="Exportar PDF">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>


        <?php if (isset($error)): ?>
            <div class="alert alert-danger shadow-sm border-0">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
            </div>
        <?php elseif (empty($cobros)): ?>
            <div class="glass-panel text-center py-5 border-0 shadow-lg rounded-4 animate-fade">
                <i class="fa-solid fa-circle-info fa-3x mb-3 text-primary opacity-50 d-block"></i>
                <h5 class="fw-bold">No se encontraron resultados</h5>
                <p class="text-muted mb-0">Intenta ajustar los filtros de búsqueda.</p>
            </div>
        <?php else: ?>

        <!-- 2. KPIs (ESTILO PREMIUM) -->
        <div class="row g-3 mb-4">
            <!-- Facturación Total (Período) -->
            <div class="col-md-3">
                <div class="card glass-panel border-0 shadow-lg h-100 border-start border-primary border-4 animate-fade" style="animation-delay: 0.1s;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-file-invoice-dollar text-primary"></i>
                            </div>
                            <h6 class="mb-0 text-muted small fw-bold text-uppercase">Facturación Total</h6>
                        </div>
                        <h3 class="fw-bold mb-0">$<?php echo number_format($total_facturado_periodo, 2); ?></h3>
                        <small class="text-muted">Total facturado en el rango</small>
                    </div>
                </div>
            </div>

            <!-- Total Cobrado (SAE Plus) -->
            <div class="col-md-3">
                <div class="card glass-panel border-0 shadow-lg h-100 border-start border-success border-4 animate-fade" style="animation-delay: 0.2s;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-check-double text-success"></i>
                            </div>
                            <h6 class="mb-0 text-muted small fw-bold text-uppercase">Cobrado SAE Plus</h6>
                        </div>
                        <h3 class="fw-bold mb-0">$<?php echo number_format($total_cobrado, 2); ?></h3>
                        <?php $eficiencia = ($total_facturado_periodo > 0) ? ($total_cobrado / $total_facturado_periodo) * 100 : 0; ?>
                        <div class="progress mt-2" style="height: 6px; background: rgba(0,0,0,0.1);">
                            <div class="progress-bar bg-success shadow-sm" style="width: <?php echo min(100, $eficiencia); ?>%"></div>
                        </div>
                        <small class="text-muted">Eficiencia: <?php echo number_format($eficiencia, 1); ?>%</small>
                    </div>
                </div>
            </div>

            <!-- Deuda Pendiente (Período) -->
            <div class="col-md-3">
                <div class="card glass-panel border-0 shadow-lg h-100 border-start border-danger border-4 animate-fade" style="animation-delay: 0.3s;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-clock text-danger"></i>
                            </div>
                            <h6 class="mb-0 text-muted small fw-bold text-uppercase">Deuda Pendiente</h6>
                        </div>
                        <h3 class="fw-bold mb-0">$<?php echo number_format($deuda_clientes, 2); ?></h3>
                        <small class="text-muted">Monto total por cobrar</small>
                    </div>
                </div>
            </div>

            <!-- Impacto Contractual -->
            <div class="col-md-3">
                <div class="card glass-panel border-0 shadow-lg h-100 border-start border-info border-4 animate-fade" style="animation-delay: 0.4s;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-info bg-opacity-10 p-2 me-3">
                                <i class="fa-solid fa-file-contract text-info"></i>
                            </div>
                            <h6 class="mb-0 text-muted small fw-bold text-uppercase">Contratos Activos</h6>
                        </div>
                        <h3 class="fw-bold mb-0"><?php echo number_format($total_contratos_activos); ?></h3>
                        <small class="text-muted">Total clientes vigentes</small>
                    </div>
                </div>
            </div>

            <!-- REFERENCIA GLOBAL -->
            <div class="col-md-6 mt-2">
                <div class="glass-panel border-0 shadow-lg py-2 px-3 mb-0 d-flex align-items-center animate-fade" style="animation-delay: 0.5s;">
                    <i class="fa-solid fa-database text-muted me-3"></i>
                    <div>
                        <span class="small text-muted text-uppercase fw-bold">Balance General del Sistema:</span>
                        <span class="ms-3 fw-bold text-primary"><?php echo number_format($global_clientes); ?> Clientes</span>
                        <span class="mx-2 text-muted">/</span>
                        <span class="fw-bold text-success"><?php echo number_format($global_contratos); ?> Contratos</span>
                    </div>
                </div>
            </div>
        </div>



            <!-- Desglose por Planes -->
            <div class="card glass-panel border-0 shadow-lg mb-4 overflow-hidden animate-fade" style="animation-delay: 0.6s;">
                <div class="card-header bg-transparent py-3 border-bottom border-white border-opacity-10">
                    <h6 class="mb-0 fw-bold text-gradient"><i class="fa-solid fa-layer-group me-2"></i>Desglose Comercial por Planes</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 table-premium">
                            <thead>
                                <tr>
                                    <th class="text-center">Nombre del Plan</th>
                                    <th class="text-center">Cantidad Clientes</th>
                                    <th class="text-center">Subtotal Mensual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($desglose_planes as $dp): ?>
                                <tr>
                                    <td class="text-center py-3 fw-bold"><?php echo htmlspecialchars($dp['nombre_plan']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light bg-opacity-10 text-main border border-white border-opacity-10 px-3"><?php echo $dp['cantidad']; ?></span>
                                    </td>
                                    <td class="text-center fw-bold text-primary">$<?php echo number_format($dp['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabla de Resultados -->
            <div class="card glass-panel border-0 shadow-lg animate-fade" style="animation-delay: 0.7s;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-premium" id="tabla_reporte_cobranza">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Plan</th>
                                    <th>Emisión</th>
                                    <th>Vencimiento</th>
                                    <th>Días Vencido</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cobros as $fila):
                                    $is_vencido = ($fila['estado'] !== 'PAGADO' && $fila['dias_vencido'] > 0);
                                    $row_bg = $is_vencido ? 'bg-danger bg-opacity-10' : '';
                                    ?>
                                    <tr class="<?php echo $row_bg; ?>">
                                        <td class="fw-medium text-muted">
                                            #<?php echo htmlspecialchars($fila['id_cobro']); ?></td>
                                        <td class="fw-bold">
                                            <div class="d-flex flex-column align-items-center">
                                                <span><?php echo htmlspecialchars($fila['cliente']); ?></span>
                                                <small class="text-muted fw-normal" style="font-size: 0.75rem;">IP: <?php echo htmlspecialchars($fila['ip_onu'] ?? 'S/I'); ?></small>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal"><?php echo htmlspecialchars($fila['nombre_plan'] ?? 'N/A'); ?></span></td>
                                        <td class="small"><?php echo htmlspecialchars($fila['fecha_emision']); ?></td>
                                        <td class="<?php echo $is_vencido ? 'text-danger fw-bold small' : 'small'; ?>">
                                            <?php echo htmlspecialchars($fila['fecha_vencimiento']); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            if ($fila['estado'] !== 'PAGADO' && $fila['dias_vencido'] > 0) {
                                                echo "<span class='badge bg-danger'>{$fila['dias_vencido']} días</span>";
                                            } elseif ($fila['estado'] == 'PAGADO') {
                                                echo "<span class='badge bg-light text-muted border px-2'>0</span>";
                                            } else {
                                                echo "<span class='badge bg-success opacity-75'>Al día</span>";
                                            }
                                            ?>
                                        </td>
                                        <td class="fw-bold">$<?php echo number_format($fila['monto_total'], 2); ?></td>
                                        <td class="text-center">
                                            <?php
                                            $badge_class = 'secondary';
                                            $estado_text = $fila['estado'];
                                            switch ($fila['estado']) {
                                                case 'PAGADO': $badge_class = 'success'; break;
                                                case 'PENDIENTE': $badge_class = 'warning text-dark'; break;
                                                case 'RECHAZADO': $badge_class = 'danger'; break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?> rounded-pill px-3" style="font-size: 0.7rem;"><?php echo htmlspecialchars($estado_text); ?></span>
                                        </td>
                                        <td>
                                            <button type="button" onclick="verDetallesCobro(<?php echo $fila['id_cobro']; ?>)"
                                                    class="btn btn-sm btn-glass rounded-pill py-1 px-3 shadow-none" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-eye me-1 text-primary"></i> Detalles
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="border-top border-white border-opacity-10">
                                <tr>
                                    <th colspan="6" class="ps-4 text-end text-muted small fw-bold">TOTAL VISIBLE EN PÁGINA:</th>
                                    <th class="text-end fw-bold text-primary" id="total_visible">$0.00</th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 mb-2 animate-fade" style="animation-delay: 0.8s;">
                <a href="../menu.php" class="btn btn-glass px-5 py-2">
                    <i class="fa-solid fa-arrow-left me-2"></i>Volver al Menú
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Detalles Cobro (Justificación) -->
<div class="modal fade" id="modalJustificacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-gradient text-white border-0 p-3">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-file-invoice-dollar text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">Detalles del Cobro</h5>
                        <p class="text-white-50 small mb-0">Información técnica y desglose del pago</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Loader -->
                <div id="justif_loader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Cargando detalles...</p>
                </div>
                
                <!-- Content -->
                <div id="justif_content" class="d-none">
                    <div class="p-4 border-bottom border-white border-opacity-10" style="background: var(--bg-card);">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="text-uppercase small fw-bold text-muted d-block mb-1">Cliente / Titular</label>
                                <h5 id="justif_cliente_nombre" class="fw-bold mb-1">-</h5>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2">ID Contrato: #<span id="justif_id_contrato">-</span></span>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <label class="text-uppercase small fw-bold text-muted d-block mb-1">Monto Total Reportado</label>
                                <h3 id="justif_monto" class="fw-bold text-primary mb-0">$0.00</h3>
                                <small class="text-muted">ID Cobro: #<span id="justif_id_cobro">-</span></small>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="info-box-premium d-flex align-items-center">
                                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                        <i class="fas fa-calendar-alt text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-uppercase text-muted fw-bold d-block" style="font-size: 0.65rem;">Fecha Operación</small>
                                        <span id="justif_fecha_creacion" class="fw-bold">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box-premium d-flex align-items-center">
                                    <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                                        <i class="fas fa-hashtag text-success"></i>
                                    </div>
                                    <div>
                                        <small class="text-uppercase text-muted fw-bold d-block" style="font-size: 0.65rem;">Referencia</small>
                                        <span id="justif_referencia" class="fw-bold">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box-premium d-flex align-items-center">
                                    <div class="rounded-circle bg-info bg-opacity-10 p-2 me-3">
                                        <i class="fas fa-university text-info"></i>
                                    </div>
                                    <div>
                                        <small class="text-uppercase text-muted fw-bold d-block" style="font-size: 0.65rem;">Banco Destino</small>
                                        <span id="justif_banco" class="fw-bold">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card glass-panel border-0 shadow-sm mb-4 mx-0 overflow-hidden rounded-4">
                            <div class="card-header bg-transparent py-2 small fw-bold text-muted text-uppercase border-bottom border-white border-opacity-10">Desglose de Conceptos</div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-borderless align-middle mb-0">
                                    <thead class="bg-transparent border-bottom border-white border-opacity-10">
                                        <tr>
                                            <th class="ps-3 small text-muted">ID Cargo</th>
                                            <th class="small text-muted">Concepto/Descripción</th>
                                            <th class="text-end pe-3 small text-muted">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody id="justif_conceptos_body" class="small">
                                        <!-- Dinámico -->
                                    </tbody>
                                    <tfoot class="border-top border-white border-opacity-10">
                                        <tr>
                                            <th colspan="2" class="ps-3 py-2">TOTAL PAGADO</th>
                                            <th id="justif_total_pagado" class="text-end pe-3 py-2 text-primary fw-bold">$0.00</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-box-premium">
                                    <label class="text-uppercase small fw-bold text-primary d-block mb-2"><i class="fas fa-user-shield me-1"></i> Auditoría</label>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Registrado por:</small>
                                        <span id="justif_autorizado" class="fw-bold">-</span>
                                    </div>
                                    <div class="p-3 rounded-3 small text-main fw-medium" id="justif_texto" style="background: var(--bg-card); border: 1px solid var(--border-glass);">
                                        -
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box-premium">
                                    <label class="text-uppercase small fw-bold text-primary d-block mb-2"><i class="fas fa-image me-1"></i> Comprobante</label>
                                    <div id="justif_capture_container" class="text-center d-none">
                                        <a href="#" id="justif_capture_link" target="_blank">
                                            <img src="" id="justif_capture_img" class="img-fluid rounded shadow-sm border border-white border-opacity-20" style="max-height: 180px;" title="Clic para ampliar">
                                        </a>
                                        <p class="small text-muted mt-2 mb-0">Clic para ampliar imagen</p>
                                    </div>
                                    <div id="justif_no_capture" class="text-center py-4 text-muted small">
                                        <i class="fas fa-camera fa-2x d-block mb-2 opacity-25"></i>
                                        Sin imagen adjunta
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-transparent border-0 p-4">
                <button type="button" class="btn btn-glass px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php 
include $path_to_root . 'paginas/includes/layout_foot.php'; 
?>

<!-- Librerías JS Necesarias -->
<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/datatables.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializar DataTable
    $('#tabla_reporte_cobranza').DataTable({
        "order": [[3, "desc"]], 
        "pageLength": 10,
        "dom": '<"d-flex justify-content-between align-items-center flex-wrap p-3 px-4"lf>rt<"d-flex justify-content-between align-items-center flex-wrap p-3 px-4"ip>',
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "_MENU_ por página",
            "sZeroRecords": "No se encontraron resultados",
            "sSearch": "<i class='fa-solid fa-search me-1'></i>",
            "sSearchPlaceholder": "Buscar en tabla...",
            "oPaginate": { "sNext": "Sig", "sPrevious": "Ant" },
            "sInfo": "Mostrando _START_ a _END_ de _TOTAL_",
        },
        "drawCallback": function() {
            var api = this.api();
            var total = api.column(6, {page:'current'}).data().reduce(function (a, b) {
                var x = parseFloat(a) || 0;
                var y = parseFloat(b.replace(/[^\d.-]/g, '')) || 0;
                return x + y;
            }, 0);
            $('#total_visible').html('$' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));
        }
    });
});

function verDetallesCobro(idCobro) {
    const loader = document.getElementById('justif_loader');
    const content = document.getElementById('justif_content');
    
    loader.classList.remove('d-none');
    content.classList.add('d-none');

    var modal = new bootstrap.Modal(document.getElementById('modalJustificacion'));
    modal.show();

    fetch(`../principal/get_justificacion_data.php?id_cobro=${idCobro}`)
        .then(r => r.json())
        .then(res => {
            loader.classList.add('d-none');
            if (res.success) {
                const d = res.data;
                document.getElementById('justif_cliente_nombre').textContent = d.nombre_cliente;
                document.getElementById('justif_id_contrato').textContent = d.id_contrato;
                document.getElementById('justif_id_cobro').textContent = idCobro;
                document.getElementById('justif_monto').textContent = '$' + parseFloat(d.monto_cargado).toFixed(2);
                document.getElementById('justif_fecha_creacion').textContent = new Date(d.fecha_creacion).toLocaleString();
                document.getElementById('justif_referencia').textContent = d.referencia_pago || 'N/A';
                document.getElementById('justif_banco').textContent = d.nombre_banco || 'No especificado';
                document.getElementById('justif_autorizado').textContent = d.autorizado_por;
                document.getElementById('justif_texto').innerHTML = (d.justificacion || '').replace(/\n/g, '<br>');
                
                // Renderizar tabla de conceptos
                const tbody = document.getElementById('justif_conceptos_body');
                tbody.innerHTML = '';
                let totalAcumulado = 0;
                
                if (res.all_concepts && res.all_concepts.length > 0) {
                    res.all_concepts.forEach(c => {
                        const montoVal = parseFloat(c.monto_cargado);
                        totalAcumulado += montoVal;
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${c.id_cobro}</td>
                            <td>${(c.justificacion || 'Cobro').split(' - ')[0]}</td>
                            <td class="text-end fw-bold">$${montoVal.toFixed(2)}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
                document.getElementById('justif_total_pagado').textContent = '$' + totalAcumulado.toFixed(2);

                // Manejo del Capture
                const img = document.getElementById('justif_capture_img');
                const link = document.getElementById('justif_capture_link');
                const imgContainer = document.getElementById('justif_capture_container');
                const noCapContainer = document.getElementById('justif_no_capture');

                if (d.capture_pago) {
                    let path = d.capture_pago;
                    if (path.startsWith('../../')) path = path.replace('../../', '');
                    
                    img.src = '../../' + path;
                    link.href = '../../' + path;
                    imgContainer.classList.remove('d-none');
                    noCapContainer.classList.add('d-none');
                } else {
                    imgContainer.classList.add('d-none');
                    noCapContainer.classList.remove('d-none');
                }

                content.classList.remove('d-none');
            } else {
                Swal.fire('Error', res.message, 'error');
                modal.hide();
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            modal.hide();
        });
}
</script>