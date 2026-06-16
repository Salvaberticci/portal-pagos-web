<?php
require_once '../conexion.php';

// 1. CAPTURA DE PARÁMETROS DE FILTRO
$busqueda_filtro = isset($_GET['busqueda']) ? $_GET['busqueda'] : ''; // 🔍 NUEVO: Filtro de búsqueda textual
$id_municipio_filtro = isset($_GET['municipio']) ? $_GET['municipio'] : 'TODOS';
$id_parroquia_filtro = isset($_GET['parroquia']) ? $_GET['parroquia'] : 'TODOS';
$estado_contrato_filtro = isset($_GET['estado_contrato']) ? $_GET['estado_contrato'] : 'TODOS';
$vendedor_texto_filtro = isset($_GET['vendedor']) ? $_GET['vendedor'] : 'TODOS';
$id_plan_filtro = isset($_GET['plan']) ? $_GET['plan'] : 'TODOS';
$cobros_estado_filtro = isset($_GET['estado_cobros']) ? $_GET['estado_cobros'] : 'TODOS';
$id_olt_filtro = isset($_GET['olt']) ? $_GET['olt'] : 'TODOS';
$id_pon_filtro = isset($_GET['pon']) ? $_GET['pon'] : 'TODOS';

// --- PAGINACIÓN ---
$registros_por_pagina = 25;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$clientes = [];
$total_clientes = 0;

// Consultas para cargar los filtros
$municipios = $conn->query("SELECT id_municipio, nombre_municipio FROM municipio ORDER BY nombre_municipio")->fetch_all(MYSQLI_ASSOC);
$vendedores_json_path = '../principal/data/vendedores.json';
$vendedores = file_exists($vendedores_json_path) ? json_decode(file_get_contents($vendedores_json_path), true) ?: [] : [];
$planes = $conn->query("SELECT id_plan, nombre_plan FROM planes ORDER BY nombre_plan")->fetch_all(MYSQLI_ASSOC);
$estados_contrato = ['ACTIVO', 'INACTIVO', 'SUSPENDIDO', 'CANCELADO'];
$estados_cobros = ['PENDIENTE', 'PAGADO', 'RECHAZADO', 'TODOS'];
$olts = $conn->query("SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt")->fetch_all(MYSQLI_ASSOC);
$pons = $conn->query("SELECT id_pon, nombre_pon FROM pon ORDER BY nombre_pon")->fetch_all(MYSQLI_ASSOC);

// Carga condicional de parroquias
$parroquias_filtradas = [];
if ($id_municipio_filtro !== 'TODOS') {
    $stmt_parroquias = $conn->prepare("SELECT id_parroquia, nombre_parroquia FROM parroquia WHERE id_municipio = ? ORDER BY nombre_parroquia");
    if ($stmt_parroquias) {
        $stmt_parroquias->bind_param("i", $id_municipio_filtro);
        $stmt_parroquias->execute();
        $resultado_parroquias = $stmt_parroquias->get_result();
        $parroquias_filtradas = $resultado_parroquias->fetch_all(MYSQLI_ASSOC);
        $stmt_parroquias->close();
    }
}

// 2. CONSTRUCCIÓN DINÁMICA DE LA CLÁUSULA WHERE
$where_clause = " WHERE 1=1 ";
$params = [];
$types = '';

// Para reportes de clientes, siempre necesitamos JOINs
$join_clause = "
    LEFT JOIN municipio m ON c.id_municipio = m.id_municipio
    LEFT JOIN parroquia pa ON c.id_parroquia = pa.id_parroquia
    LEFT JOIN planes pl ON c.id_plan = pl.id_plan
    LEFT JOIN olt ol ON c.id_olt = ol.id_olt
    LEFT JOIN pon p ON c.id_pon = p.id_pon 
";

// 2.1. Filtros

// 🔍 NUEVO: Filtro de Búsqueda General
if (!empty($busqueda_filtro)) {
    $where_clause .= " AND (c.nombre_completo LIKE ? OR c.cedula LIKE ? OR c.ip_onu LIKE ? OR c.telefono LIKE ?) ";
    $busqueda_param = "%" . $busqueda_filtro . "%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $types .= 'ssss';
}

if ($id_municipio_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_municipio = ? ";
    $params[] = $id_municipio_filtro;
    $types .= 'i';
}
if ($id_parroquia_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_parroquia = ? ";
    $params[] = $id_parroquia_filtro;
    $types .= 'i';
}
if ($estado_contrato_filtro !== 'TODOS') {
    $where_clause .= " AND c.estado = ? ";
    $params[] = $estado_contrato_filtro;
    $types .= 's';
}
if ($vendedor_texto_filtro !== 'TODOS') {
    $where_clause .= " AND c.vendedor_texto = ? ";
    $params[] = $vendedor_texto_filtro;
    $types .= 's';
}
if ($id_plan_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_plan = ? ";
    $params[] = $id_plan_filtro;
    $types .= 'i';
}
if ($id_olt_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_olt = ? ";
    $params[] = $id_olt_filtro;
    $types .= 'i';
}
if ($id_pon_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_pon = ? ";
    $params[] = $id_pon_filtro;
    $types .= 'i';
}
if ($cobros_estado_filtro !== 'TODOS') {
    $join_clause .= " INNER JOIN cuentas_por_cobrar cxc ON c.id = cxc.id_contrato ";
    $where_clause .= " AND cxc.estado = ? ";
    $params[] = $cobros_estado_filtro;
    $types .= 's';
}
// 3. CONSULTA SQL BASE
$sql = "
    SELECT 
        c.id, c.nombre_completo, c.cedula, c.telefono, c.estado AS estado_contrato, c.ip_onu,
        m.nombre_municipio AS municipio, pa.nombre_parroquia AS parroquia, 
        pl.nombre_plan AS plan, c.vendedor_texto AS vendedor,
        ol.nombre_olt AS olt_nombre, p.nombre_pon AS pon_nombre 
    FROM contratos c
    {$join_clause}
    {$where_clause}
    GROUP BY c.id 
    ORDER BY c.nombre_completo ASC
";

// 3.1. CONSULTA PARA EL TOTAL (PARA PAGINACIÓN)
$sql_count = "SELECT COUNT(DISTINCT c.id) as total FROM contratos c {$join_clause} {$where_clause}";
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$res_count = $stmt_count->get_result()->fetch_assoc();
$total_clientes_filtrados = $res_count['total'];
$total_paginas = ceil($total_clientes_filtrados / $registros_por_pagina);

// 3.2. CONSULTA SQL FINAL CON LIMIT
$sql .= " LIMIT ? OFFSET ? ";
$params[] = $registros_por_pagina;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $clientes = $resultado->fetch_all(MYSQLI_ASSOC);
    $total_clientes_pagina = count($clientes);
}
$total_clientes = $total_clientes_filtrados; // Para mostrar el total real en el header

// --- TEMPLATE START ---
$path_to_root = "../../";
$page_title = "Reporte de Clientes";
$breadcrumb = ["Reportes"];
$back_url = "../menu.php";
include $path_to_root . 'paginas/includes/layout_head.php';

// Consultas para KPIs rápidos
$total_activos = $conn->query("SELECT COUNT(*) FROM contratos WHERE estado = 'ACTIVO'")->fetch_row()[0];
$total_suspendidos = $conn->query("SELECT COUNT(*) FROM contratos WHERE estado = 'SUSPENDIDO'")->fetch_row()[0];
$total_inactivos = $conn->query("SELECT COUNT(*) FROM contratos WHERE estado = 'INACTIVO'")->fetch_row()[0];
?>

<style>
    :root {
        --primary: #0d6efd;
        --primary-rgb: 13, 110, 253;
        --bg-card: #ffffff;
        --text-main: #212529;
        --border-glass: rgba(0, 0, 0, 0.08);
    }
    [data-theme="dark"] {
        --bg-card: #1a2234;
        --text-main: #e2e8f0;
        --border-glass: rgba(255, 255, 255, 0.1);
    }
    .text-gradient {
        background: linear-gradient(45deg, var(--primary), #00c6ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .glass-panel {
        background: var(--bg-card) !important;
        backdrop-filter: blur(10px);
        border: 1px solid var(--border-glass) !important;
        border-radius: 20px !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05) !important;
        transition: all 0.3s ease;
    }
    .kpi-card {
        border-radius: 18px !important;
        border: none !important;
        background: var(--bg-card);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    }
    .table-premium th,
    .table-premium td {
        text-align: center !important;
        vertical-align: middle !important;
        padding: 12px 8px !important;
        background: transparent !important;
    }
    .table-premium tfoot tr {
        background: transparent !important;
    }
    .table-premium thead th {
        background: rgba(var(--primary-rgb), 0.03) !important;
        color: var(--text-main) !important;
        border: none !important;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    [data-theme="dark"] .table-premium thead th {
        background: rgba(255, 255, 255, 0.05) !important;
    }
    .info-box-premium {
        background: rgba(0, 0, 0, 0.03) !important;
        border: 1px solid rgba(0,0,0,0.05) !important;
        border-radius: 12px !important;
        padding: 1rem !important;
    }
    [data-theme="dark"] .info-box-premium {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255,255,255,0.1) !important;
    }
    .btn-glass {
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        border: 1px solid rgba(var(--primary-rgb), 0.2);
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
    }
    .btn-glass:hover {
        background: var(--primary);
        color: white;
    }
    
    /* Pagination Style to match DataTables */
    .pagination-sm .page-item .page-link {
        border: 1px solid var(--border-glass) !important;
        background: var(--bg-card) !important;
        color: var(--primary) !important;
        padding: 5px 12px !important;
        font-size: 0.8rem !important;
        border-radius: 2px !important;
        margin: 0 !important;
        transition: all 0.2s ease;
        font-weight: 500;
    }
    .pagination-sm .page-item.active .page-link {
        background-color: var(--primary) !important;
        border-color: var(--primary) !important;
        color: white !important;
    }
    .pagination-sm .page-item.disabled .page-link {
        background-color: #f8f9fa !important;
        color: #6c757d !important;
        border-color: #dee2e6 !important;
    }
    [data-theme="dark"] .pagination-sm .page-item.disabled .page-link {
        background-color: rgba(255,255,255,0.05) !important;
        border-color: var(--border-glass) !important;
    }
    .pagination-sm .page-item .page-link:hover:not(.active) {
        background-color: #eee !important;
        color: var(--primary) !important;
    }
    [data-theme="dark"] .pagination-sm .page-item .page-link:hover:not(.active) {
        background-color: rgba(255,255,255,0.1) !important;
    }
</style>

<main class="main-content">
    <?php include $path_to_root . 'paginas/includes/header.php'; ?>

    <div class="page-content">
        <!-- Header -->
        <div class="mb-4">
            <h2 class="h3 fw-bold mb-1 text-gradient">Análisis de Cartera de Clientes</h2>
            <p class="text-muted mb-0"><i class="fa-solid fa-users me-2"></i>Gestión estratégica y control de abonados</p>
        </div>

        <!-- KPIs Quick View -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="glass-panel p-3 text-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 mx-auto mb-2" style="width: 40px; height: 40px;">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                    <h4 class="fw-bold mb-0"><?php echo number_format($total_clientes, 0); ?></h4>
                    <small class="text-muted">Total Clientes</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-panel p-3 text-center border-start border-4 border-success">
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 mx-auto mb-2" style="width: 40px; height: 40px;">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>
                    <h4 class="fw-bold mb-0"><?php echo number_format($total_activos, 0); ?></h4>
                    <small class="text-muted">Activos</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-panel p-3 text-center border-start border-4 border-warning">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 mx-auto mb-2" style="width: 40px; height: 40px;">
                        <i class="fas fa-pause-circle text-warning"></i>
                    </div>
                    <h4 class="fw-bold mb-0"><?php echo number_format($total_suspendidos, 0); ?></h4>
                    <small class="text-muted">Suspendidos</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="glass-panel p-3 text-center border-start border-4 border-danger">
                    <div class="rounded-circle bg-danger bg-opacity-10 p-2 mx-auto mb-2" style="width: 40px; height: 40px;">
                        <i class="fas fa-times-circle text-danger"></i>
                    </div>
                    <h4 class="fw-bold mb-0"><?php echo number_format($total_inactivos, 0); ?></h4>
                    <small class="text-muted">Inactivos</small>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="glass-panel mb-4 overflow-hidden">
            <div class="p-3 border-bottom border-white border-opacity-10 bg-primary bg-opacity-5">
                <h6 class="mb-0 fw-bold text-primary"><i class="fa-solid fa-filter me-2"></i>Filtros de Búsqueda Avanzada</h6>
            </div>
            <div class="p-4">
                <form method="GET" class="row g-3">

                    <!-- 🔍 NUEVO: Barra de Búsqueda Principal -->
                    <div class="col-12 mb-2">
                        <label for="busqueda"
                            class="form-label fw-semibold text-secondary small text-uppercase">Búsqueda General</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i
                                    class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="busqueda" name="busqueda"
                                value="<?php echo htmlspecialchars($busqueda_filtro); ?>"
                                placeholder="Nombre, Cédula, IP o Teléfono...">
                        </div>
                    </div>

                    <!-- Estado Contrato -->
                    <div class="col-md-3">
                        <label for="estado_contrato"
                            class="form-label fw-semibold text-secondary small text-uppercase">Estado Contrato</label>
                        <select name="estado_contrato" id="estado_contrato" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($estados_contrato as $estado): ?>
                                <option value="<?php echo $estado; ?>" <?php echo ($estado_contrato_filtro === $estado) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Municipio -->
                    <div class="col-md-3">
                        <label for="municipio"
                            class="form-label fw-semibold text-secondary small text-uppercase">Municipio</label>
                        <select name="municipio" id="municipio" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($municipios as $m): ?>
                                <option value="<?php echo $m['id_municipio']; ?>" <?php echo ($id_municipio_filtro == $m['id_municipio']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['nombre_municipio']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Parroquia -->
                    <div class="col-md-3">
                        <label for="parroquia"
                            class="form-label fw-semibold text-secondary small text-uppercase">Parroquia</label>
                        <select name="parroquia" id="parroquia" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php
                            if (!empty($parroquias_filtradas)):
                                foreach ($parroquias_filtradas as $p): ?>
                                    <option value="<?php echo $p['id_parroquia']; ?>" <?php echo ($id_parroquia_filtro == $p['id_parroquia']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['nombre_parroquia']); ?>
                                    </option>
                                <?php endforeach;
                            endif;
                            ?>
                        </select>
                    </div>

                    <!-- Plan -->
                    <div class="col-md-3">
                        <label for="plan"
                            class="form-label fw-semibold text-secondary small text-uppercase">Plan</label>
                        <select name="plan" id="plan" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($planes as $pl): ?>
                                <option value="<?php echo $pl['id_plan']; ?>" <?php echo ($id_plan_filtro == $pl['id_plan']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pl['nombre_plan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Vendedor -->
                    <div class="col-md-3">
                        <label for="vendedor"
                            class="form-label fw-semibold text-secondary small text-uppercase">Vendedor</label>
                        <select name="vendedor" id="vendedor" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($vendedores as $v): ?>
                                <option value="<?php echo htmlspecialchars($v); ?>" <?php echo ($vendedor_texto_filtro == $v) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- OLT -->
                    <div class="col-md-3">
                        <label for="olt" class="form-label fw-semibold text-secondary small text-uppercase">OLT</label>
                        <select name="olt" id="olt" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($olts as $o): ?>
                                <option value="<?php echo $o['id_olt']; ?>" <?php echo ($id_olt_filtro == $o['id_olt']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['nombre_olt']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- PON -->
                    <div class="col-md-3">
                        <label for="pon" class="form-label fw-semibold text-secondary small text-uppercase">PON</label>
                        <select name="pon" id="pon" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($pons as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['id_pon']); ?>" <?php echo ($id_pon_filtro == $p['id_pon']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre_pon']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Estado Cobranza -->
                    <div class="col-md-3">
                        <label for="estado_cobros"
                            class="form-label fw-semibold text-secondary small text-uppercase">Estado Deuda</label>
                        <select name="estado_cobros" id="estado_cobros" class="form-select bg-white">
                            <option value="TODOS">TODOS</option>
                            <?php foreach ($estados_cobros as $est): ?>
                                <option value="<?php echo $est; ?>" <?php echo ($cobros_estado_filtro === $est) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($est); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Botones -->
                    <div class="col-12 d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-white border-opacity-10">
                        <a href="reporte_clientes.php" class="btn btn-glass px-4">
                            <i class="fa-solid fa-redo me-2"></i>Limpiar Filtros
                        </a>
                        <button type="submit" class="btn btn-primary px-5 py-2 shadow-sm rounded-3">
                            <i class="fa-solid fa-magnifying-glass me-2"></i>Filtrar Cartera
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Botones de Exportación -->
        <div class="d-flex justify-content-end mb-4 gap-2">
            <a href="exportar_clientes_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger btn-sm px-3 shadow-sm rounded-pill" target="_blank">
                <i class="fa-solid fa-file-pdf me-2"></i>Exportar PDF
            </a>
            <a href="exportar_clientes_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm px-3 shadow-sm rounded-pill">
                <i class="fa-solid fa-file-excel me-2"></i>Exportar Excel
            </a>
        </div>

        <?php if ($total_clientes > 0): ?>
            <!-- Tabla Resultados -->
            <div class="glass-panel overflow-hidden mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-premium">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Ubicación</th>
                                <th>Plan</th>
                                <th>Red</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                            <tbody>
                                <?php foreach ($clientes as $fila): ?>
                                    <tr>
                                        <td class="fw-medium text-muted small">#<?php echo htmlspecialchars($fila['id']); ?></td>
                                        <td>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="fw-bold text-main"><?php echo htmlspecialchars($fila['nombre_completo']); ?></span>
                                                <small class="text-muted fw-normal" style="font-size: 0.7rem;">IP: <?php echo htmlspecialchars($fila['ip_onu']); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-nowrap small"><?php echo htmlspecialchars($fila['cedula']); ?></td>
                                        <td class="text-nowrap small"><?php echo htmlspecialchars($fila['telefono']); ?></td>
                                        <td>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="small fw-medium"><?php echo htmlspecialchars($fila['municipio'] ?? '-'); ?></span>
                                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($fila['parroquia'] ?? '-'); ?></small>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal px-2 py-1"><?php echo htmlspecialchars($fila['plan'] ?? '-'); ?></span></td>
                                        <td>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="small fw-medium text-primary"><?php echo htmlspecialchars($fila['olt_nombre'] ?? '-'); ?></span>
                                                <small class="text-muted" style="font-size: 0.7rem;">PON: <?php echo htmlspecialchars($fila['pon_nombre'] ?? '-'); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill px-3 py-1 bg-<?php
                                            switch ($fila['estado_contrato']) {
                                                case 'ACTIVO': echo 'success'; break;
                                                case 'SUSPENDIDO': echo 'warning text-dark'; break;
                                                case 'INACTIVO': echo 'secondary'; break;
                                                case 'CANCELADO': echo 'danger'; break;
                                                default: echo 'info';
                                            }
                                            ?>" style="font-size: 0.65rem;">
                                                <?php echo htmlspecialchars($fila['estado_contrato']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="border-top">
                                    <th colspan="7" class="text-end text-muted small fw-bold text-uppercase" style="letter-spacing: 1px; padding: 15px 20px !important;">TOTAL CLIENTES EN ESTA PÁGINA:</th>
                                    <th class="text-center fw-bold text-primary" style="font-size: 0.95rem; padding: 15px 10px !important;"><?php echo count($clientes); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <!-- Pagination Footer -->
                    <div class="p-3 px-4 border-top border-light-subtle d-flex justify-content-between align-items-center flex-wrap" style="background: rgba(0,0,0,0.01);">
                    <div class="small text-muted fw-medium" style="font-size: 0.85rem;">
                        Mostrando <?php echo ($total_clientes > 0) ? $offset + 1 : 0; ?> a <?php echo min($offset + $registros_por_pagina, $total_clientes); ?> de <?php echo $total_clientes; ?>
                    </div>
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $params_link = $_GET;
                                // Anterior
                                $params_link['p'] = $pagina_actual - 1;
                                $disabled_prev = ($pagina_actual <= 1) ? 'disabled' : '';
                                echo "<li class='page-item {$disabled_prev}'><a class='page-link' href='?" . http_build_query($params_link) . "'>Ant</a></li>";

                                // Páginas
                                $rango = 2;
                                for ($i = 1; $i <= $total_paginas; $i++) {
                                    if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango)) {
                                        $params_link['p'] = $i;
                                        $active = ($i == $pagina_actual) ? 'active' : '';
                                        echo "<li class='page-item {$active}'><a class='page-link' href='?" . http_build_query($params_link) . "'>{$i}</a></li>";
                                    } elseif ($i == $pagina_actual - $rango - 1 || $i == $pagina_actual + $rango + 1) {
                                        echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                                    }
                                }

                                // Siguiente
                                $params_link['p'] = $pagina_actual + 1;
                                $disabled_next = ($pagina_actual >= $total_paginas) ? 'disabled' : '';
                                echo "<li class='page-item {$disabled_next}'><a class='page-link' href='?" . http_build_query($params_link) . "'>Sig</a></li>";
                                ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center mt-4 mb-4">
                <a href="../menu.php" class="btn btn-glass px-5 py-2">
                    <i class="fa-solid fa-arrow-left me-2"></i>Volver al Menú
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning shadow-sm border-0 text-center py-4">
                <i class="fa-solid fa-triangle-exclamation fa-2x mb-3 text-warning d-block"></i>
                <h5 class="fw-bold text-dark">No se encontraron clientes</h5>
                <p class="text-muted mb-0">No hay coincidencias con los filtros actuales.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectMunicipio = document.getElementById('municipio');
        const selectParroquia = document.getElementById('parroquia');

        // Guardar el valor de la parroquia seleccionada en el filtro inicial
        const parroquiaFiltroInicial = selectParroquia.value;

        selectMunicipio.addEventListener('change', function () {
            const idMunicipio = this.value;

            // Limpiar el select de Parroquias, manteniendo la opción 'TODOS'
            selectParroquia.innerHTML = '<option value="TODOS">TODOS</option>';

            if (idMunicipio !== 'TODOS') {
                // Realizar la petición AJAX
                fetch('obtener_parroquias.php?id_municipio=' + idMunicipio)
                    .then(response => response.json())
                    .then(parroquias => {
                        parroquias.forEach(parroquia => {
                            const option = document.createElement('option');
                            option.value = parroquia.id_parroquia;
                            option.textContent = parroquia.nombre_parroquia;

                            // Si estamos en la carga inicial y el municipio fue cambiado por JS, 
                            // necesitamos que la parroquia filtrada se mantenga seleccionada.
                            if (parroquia.id_parroquia == parroquiaFiltroInicial) {
                                option.selected = true;
                            }

                            selectParroquia.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error al obtener parroquias:', error));
            }
        });

        // Ejecutar el evento 'change' en la carga inicial si hay un municipio preseleccionado
        // Esto asegura que si el usuario regresa con filtros aplicados, la lista de parroquias sea correcta.
        if (selectMunicipio.value !== 'TODOS' && selectParroquia.options.length <= 1) {
            selectMunicipio.dispatchEvent(new Event('change'));
        }

    });
</script>

<?php include $path_to_root . 'paginas/includes/layout_foot.php'; ?>