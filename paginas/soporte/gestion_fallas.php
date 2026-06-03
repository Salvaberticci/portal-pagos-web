<?php
/**
 * Gestión de Fallas Técnicas - Dashboard Principal
 * Módulo completo con estadísticas, filtros y exportación PDF
 */
$path_to_root = "../../";
$page_title = "Gestión de Fallas";
$breadcrumb = ["Soporte", "Gestión de Fallas"];
$back_url = "../menu.php";
require_once $path_to_root . 'paginas/conexion.php';

// Obtener OLTs para el modal de edición
$olts = [];
$res_olt = $conn->query("SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt ASC");
while ($row = $res_olt->fetch_assoc()) {
    $olts[] = $row;
}
require_once $path_to_root . 'paginas/includes/layout_head.php';
// Cargar lista de instaladores/técnicos
$jsonInstaladores = $path_to_root . 'paginas/principal/data/instaladores.json';
$instaladoresList = [];
if (file_exists($jsonInstaladores)) {
    $instaladoresList = json_decode(file_get_contents($jsonInstaladores), true) ?: [];
}
// Incluir SweetAlert2 para diálogos de confirmación
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
require_once $path_to_root . 'paginas/includes/sidebar.php';

// Leer filtros para PHP Table Rendering
$filtro_desde = isset($_GET['fecha_desde']) ? $conn->real_escape_string($_GET['fecha_desde']) : date('Y-m-d', strtotime('-1 month'));
$filtro_hasta = isset($_GET['fecha_hasta']) ? $conn->real_escape_string($_GET['fecha_hasta']) : date('Y-m-d');
$filtro_tipo = isset($_GET['tipo_falla']) ? $conn->real_escape_string($_GET['tipo_falla']) : '';
$filtro_tecnico = isset($_GET['tecnico']) ? $conn->real_escape_string($_GET['tecnico']) : '';
$filtro_pago = isset($_GET['estado_pago']) ? $conn->real_escape_string($_GET['estado_pago']) : '';

// Conteo por nivel de prioridad (Respetando filtros)
$sWhere_base = "fecha_soporte BETWEEN '$filtro_desde' AND '$filtro_hasta'";
if (!empty($filtro_tecnico)) $sWhere_base .= " AND tecnico_asignado LIKE '%$filtro_tecnico%'";

$cnt_n1 = (int)($conn->query("SELECT COUNT(*) c FROM soportes WHERE prioridad = 'NIVEL 1' AND $sWhere_base")->fetch_assoc()['c'] ?? 0);
$cnt_n2 = (int)($conn->query("SELECT COUNT(*) c FROM soportes WHERE prioridad = 'NIVEL 2' AND $sWhere_base")->fetch_assoc()['c'] ?? 0);
$cnt_n3 = (int)($conn->query("SELECT COUNT(*) c FROM soportes WHERE prioridad = 'NIVEL 3' AND $sWhere_base")->fetch_assoc()['c'] ?? 0);
?>

<!-- Include DataTables CSS inside head if not already included by layout_head.php (removing DT css) -->
<style>
    .stat-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .stat-card.primary {
        border-left-color: #0d6efd;
    }

    .stat-card.warning {
        border-left-color: #ffc107;
    }

    .stat-card.success {
        border-left-color: #198754;
    }

    .stat-card.danger {
        border-left-color: #dc3545;
    }

    /* Estilos para niveles de prioridad */
    .badge-nivel1 {
        background-color: #ffff00;
        color: #000;
    }

    .badge-nivel2 {
        background-color: #fd7e14;
        color: white;
    }

    .badge-nivel3 {
        background-color: #dc3545;
        color: white;
    }

    .filter-section {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        backdrop-filter: blur(8px);
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin-bottom: 20px;
    }

    /* Ajustes para cabeceras de tablas en modo oscuro */
    [data-theme="dark"] .table thead th {
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: var(--text-main) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    .table-orange-header th {
        background-color: #fff4ea !important;
        color: #ca6510 !important;
    }

    [data-theme="dark"] .table-orange-header th {
        background-color: rgba(253, 126, 20, 0.2) !important;
        color: #fd7e14 !important;
    }

    [data-theme="dark"] .table-warning th {
        background-color: rgba(255, 193, 7, 0.2) !important;
        color: #ffc107 !important;
    }

    [data-theme="dark"] .table-danger th {
        background-color: rgba(220, 53, 69, 0.2) !important;
        color: #dc3545 !important;
    }

    /* Forzar cabeceras de gráficas a ser transparentes en modo oscuro */
    [data-theme="dark"] .glass-panel .card-header,
    [data-theme="dark"] .card-header.bg-transparent {
        background-color: transparent !important;
        color: var(--text-main) !important;
    }

    [data-theme="dark"] .glass-panel .card-header .fw-bold {
        color: var(--text-main) !important;
    }

    .section-title {
        background: rgba(0, 0, 0, 0.05) !important;
        border-left: 4px solid var(--primary);
        color: var(--text-main) !important;
        padding: 8px 12px !important;
        margin-bottom: 12px !important;
        font-weight: 700 !important;
        border-radius: 4px;
    }

    [data-theme="dark"] .section-title {
        background: rgba(255, 255, 255, 0.05) !important;
    }

    .section-title.border-danger {
        border-left-color: var(--danger) !important;
    }

    .info-box-premium {
        background: rgba(128, 128, 128, 0.05) !important;
        border: 1px solid var(--border-glass) !important;
        padding: 15px !important;
        border-radius: 12px !important;
        margin-bottom: 15px !important;
    }

    [data-theme="dark"] .info-box-premium {
        background: rgba(255, 255, 255, 0.03) !important;
    }
</style>

<main class="main-content">
    <?php include $path_to_root . 'paginas/includes/header.php'; ?>

    <div class="page-content">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-0 text-gradient"><i class="fa-solid fa-list-check me-2 text-primary"></i>Gestión de Fallas</h2>
                        <p class="text-muted mb-0">Monitorea y gestiona las solicitudes de soporte técnico</p>
                    </div>
                     <div class="d-flex gap-2">
                         <a href="reporte_tecnico.php" target="_blank" class="btn btn-primary fw-bold px-4 shadow-sm">
                             <i class="fa-solid fa-plus-circle me-2"></i>Nuevo Reporte
                         </a>
                         <button class="btn btn-dark fw-bold px-4 shadow-sm" onclick="exportarPDF(false, '')">
                             <i class="fa-solid fa-file-export me-2"></i>Exportar Todo
                         </button>
                         <a href="registro_falla.php" class="btn btn-danger shadow px-4 fw-bold">
                             <i class="fa-solid fa-triangle-exclamation me-1"></i>REGISTRAR FALLA MASIVA
                         </a>
                         <button type="button" class="btn btn-outline-secondary shadow-sm px-4" data-bs-toggle="modal"
                             data-bs-target="#configModal">
                             <i class="fa-solid fa-cog me-1"></i>Configurar Tipos de Falla
                         </button>
                     </div>
                </div>
            </div>
        </div>



        <!-- KPIs por Nivel de Prioridad -->
        <div class="row g-3 mb-4" id="kpiCardsAvanzados">

            <div class="col-md-6 col-lg-3">
                <div class="card stat-card glass-panel border-0 h-100" style="border-left-color: #ffc107 !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small">Fallas Nivel 1 (Baja Prioridad)</p>
                                <h3 class="fw-bold mb-0" style="color:#cc9a00;"><?php echo $cnt_n1; ?></h3>
                                <small class="text-muted">guía remota / atención inmediata</small>
                            </div>
                            <div style="color:#ffc107;">
                                <i class="fa-solid fa-circle-exclamation fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-0 shadow-sm h-100" style="border-left-color: #fd7e14 !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small">Fallas Nivel 2 (Prioridad Media)</p>
                                <h3 class="fw-bold mb-0" style="color:#fd7e14;"><?php echo $cnt_n2; ?></h3>
                                <small class="text-muted">soporte técnico en campo / visita domicilio</small>
                            </div>
                            <div style="color:#fd7e14;">
                                <i class="fa-solid fa-triangle-exclamation fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-0 shadow-sm h-100" style="border-left-color: #dc3545 !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small">Fallas Nivel 3 (Alta Prioridad / Masiva)</p>
                                <h3 class="fw-bold mb-0 text-danger"><?php echo $cnt_n3; ?></h3>
                                <small class="text-muted">caídas críticas de red / zona afectada</small>
                            </div>
                            <div class="text-danger">
                                <i class="fa-solid fa-fire fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-0 shadow-sm h-100" style="border-left-color: #6f42c1 !important;">
                    <div class="card-body">
                        <div>
                            <p class="text-muted mb-1 small">Zona Más Afectada</p>
                            <h6 class="fw-bold mb-0" style="color: #6f42c1;" id="zona_top">
                                <span class="spinner-border spinner-border-sm"></span>
                            </h6>
                            <small class="text-muted" id="zona_top_count">-- fallas</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Alerta de Caídas Críticas Activas -->
        <div class="alert alert-danger d-none" id="alertCaidasCriticas" role="alert">
            <h6 class="alert-heading fw-bold">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>Caídas Críticas Activas
            </h6>
            <ul id="listaCaidasCriticas" class="mb-0"></ul>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-filter me-2"></i>Filtros</h5>
            <form id="formFiltros" method="GET" action="gestion_fallas.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde"
                            value="<?php echo htmlspecialchars($filtro_desde); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Fecha Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta"
                            value="<?php echo htmlspecialchars($filtro_hasta); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Tipo de Falla</label>
                        <select class="form-select" id="tipo_falla" name="tipo_falla">
                            <option value="">Todos</option>
                            <option value="NIVEL 1" <?php echo $filtro_tipo == 'NIVEL 1' ? 'selected' : ''; ?>>Fallas Nivel 1 (WhatsApp)</option>
                            <option value="NIVEL 2" <?php echo $filtro_tipo == 'NIVEL 2' ? 'selected' : ''; ?>>Fallas Nivel 2 (Visita)</option>
                            <option value="NIVEL 3" <?php echo $filtro_tipo == 'NIVEL 3' ? 'selected' : ''; ?>>Fallas Nivel 3 (Crítica)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Técnico</label>
                        <input type="text" class="form-control" id="filtro_tecnico" name="tecnico"
                            placeholder="Nombre del técnico..."
                            value="<?php echo htmlspecialchars($filtro_tecnico); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Estado de Pago</label>
                        <select class="form-select" id="estado_pago" name="estado_pago">
                            <option value="" <?php echo $filtro_pago == '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="PAGADO" <?php echo $filtro_pago == 'PAGADO' ? 'selected' : ''; ?>>Pagado
                            </option>
                            <option value="PENDIENTE" <?php echo $filtro_pago == 'PENDIENTE' ? 'selected' : ''; ?>>
                                Pendiente</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fa-solid fa-search me-1"></i>Filtrar
                        </button>
                        <a href="gestion_fallas.php" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="glass-panel">
                    <div class="card-header bg-transparent border-bottom border-white border-opacity-10">
                        <h6 class="mb-0 fw-bold text-main">Top 10 Tipos de Falla</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartFallasTipo"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="glass-panel">
                    <div class="card-header bg-transparent border-bottom border-white border-opacity-10">
                        <h6 class="mb-0 fw-bold text-main">Reportes por Mes</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartReportesMes"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="glass-panel">
                    <div class="card-header bg-transparent border-bottom border-white border-opacity-10">
                        <h6 class="mb-0 fw-bold text-main">Top 10 Técnicos</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartTecnicos"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="glass-panel">
                    <div class="card-header bg-transparent border-bottom border-white border-opacity-10">
                        <h6 class="mb-0 fw-bold text-main">Fallas por Nivel de Prioridad</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartNiveles"></canvas>
                        </div>
                    </div>
                </div>
            </div>


        </div>

        
        <!-- PESTAÑAS DE TABLAS POR NIVEL -->
        <?php
        $sWhere_n3 = "s.fecha_soporte BETWEEN '$filtro_desde' AND '$filtro_hasta' AND s.prioridad = 'NIVEL 3'";
        if (!empty($filtro_tecnico)) $sWhere_n3 .= " AND s.tecnico_asignado LIKE '%$filtro_tecnico%'";

        $sql_criticas = "SELECT s.id_soporte, DATE_FORMAT(s.fecha_soporte, '%d/%m/%Y') as fecha, c.nombre_completo, s.tipo_falla, s.tecnico_asignado, s.clientes_afectados, s.zona_afectada, s.solucion_completada, s.prioridad
            FROM soportes s
            INNER JOIN contratos c ON s.id_contrato = c.id
            WHERE $sWhere_n3
            ORDER BY s.id_soporte DESC";
        $res_criticas = $conn->query($sql_criticas);

        $sWhere = "s.fecha_soporte BETWEEN '$filtro_desde' AND '$filtro_hasta'";
        if (!empty($filtro_tipo)) {
            if (strpos($filtro_tipo, 'NIVEL') === 0) {
                $sWhere .= " AND s.prioridad = '$filtro_tipo'";
            } else {
                $sWhere .= " AND s.tipo_falla = '$filtro_tipo'";
            }
        }
        if (!empty($filtro_tecnico)) $sWhere .= " AND s.tecnico_asignado LIKE '%$filtro_tecnico%'";
        if (!empty($filtro_pago)) {
            if ($filtro_pago == 'PAGADO')
                $sWhere .= " AND (s.monto_total - s.monto_pagado) <= 0.01";
            else if ($filtro_pago == 'PENDIENTE')
                $sWhere .= " AND (s.monto_total - s.monto_pagado) > 0.01";
        }

        $sql_reportes = "SELECT s.id_soporte, DATE_FORMAT(s.fecha_soporte, '%d/%m/%Y') as fecha_formateada,
                                           s.hora_solucion, s.tiempo_transcurrido,
                                           c.nombre_completo, COALESCE(s.tipo_falla, 'No especificado') as tipo_falla,
                                           COALESCE(s.tecnico_asignado, 'Sin asignar') as tecnico, s.prioridad,
                                           s.monto_total, s.monto_pagado, (s.monto_total - s.monto_pagado) as saldo_pendiente,
                                           s.solucion_completada, s.es_caida_critica
                                    FROM soportes s
                                    INNER JOIN contratos c ON s.id_contrato = c.id
                                    WHERE $sWhere
                                    ORDER BY s.id_soporte DESC";
        $res_reportes = $conn->query($sql_reportes);

        $rowsNivel1 = [];
        $rowsNivel2 = [];

        if (!function_exists('fix_utf8')) {
            function fix_utf8($str) {
                if (empty($str)) return '';
                if (!mb_check_encoding($str, 'UTF-8')) $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
                return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        if ($res_reportes && $res_reportes->num_rows > 0) {
            while ($row = $res_reportes->fetch_assoc()) {
                if ($row['prioridad'] == 'NIVEL 1') $rowsNivel1[] = $row;
                elseif ($row['prioridad'] == 'NIVEL 2') $rowsNivel2[] = $row;
            }
        }
        
        function renderRow($row, $isNivel3 = false) {
            $id = $isNivel3 ? $row['id_soporte'] : $row['id_soporte'];
            $fecha = $isNivel3 ? htmlspecialchars($row['fecha']) : $row['fecha_formateada'];
            $cliente = fix_utf8($row['nombre_completo']);
            $falla = fix_utf8($row['tipo_falla'] ?? 'N/A');
            $tecnico = fix_utf8($isNivel3 ? $row['tecnico_asignado'] : $row['tecnico']);
            $prioridad = $row['prioridad'] ?? 'NIVEL 3';
            $solucion_completada = $row['solucion_completada'];

            if ($isNivel3) {
                echo "<tr style='cursor:pointer;' ondblclick='verDetallesCritica({$id})'>";
                echo "<td><span class='badge bg-danger'>#{$id}</span></td>";
                echo "<td>{$fecha}</td>";
                echo "<td>{$cliente}</td>";
                echo "<td>{$falla}</td>";
                echo "<td>{$tecnico}</td>";
                echo "<td class='text-center fw-bold text-danger'>" . intval($row['clientes_afectados'] ?? 0) . "</td>";
                echo "<td>" . fix_utf8($row['zona_afectada'] ?? '—') . "</td>";
                echo "<td id='badge-estado-{$id}'>" . ($solucion_completada ? '<span class="badge bg-success">Solucionada</span>' : '<span class="badge bg-warning text-dark">Activa</span>') . "</td>";
                echo "<td class='text-nowrap'>";
                echo "<button class='btn btn-sm btn-outline-primary shadow-sm' onclick='verDetallesCritica({$id})' title='Ver Detalles'><i class='fa-solid fa-eye'></i> Ver</button> ";
                echo "<button class='btn btn-sm btn-warning shadow-sm mx-1' onclick='editarCritica({$id})' title='Editar'><i class='fa-solid fa-pen'></i></button> ";
                
                $btnClass = $solucion_completada ? 'btn-outline-secondary' : 'btn-success';
                $btnIcon = $solucion_completada ? 'rotate-left' : 'check-double';
                $btnText = $solucion_completada ? 'Marcar Activa' : 'Solucionada';
                $nuevoStatus = $solucion_completada ? 0 : 1;
                echo "<button id='btn-toggle-{$id}' class='btn btn-sm {$btnClass} shadow-sm' onclick='toggleEstado({$id}, {$nuevoStatus}, \"NIVEL 3\")' title='Cambiar Estado'><i class='fa-solid fa-{$btnIcon} me-1'></i>{$btnText}</button> ";
                echo "<button class='btn btn-sm btn-outline-danger shadow-sm' onclick='eliminarSoporte({$id})' title='Eliminar'><i class='fa-solid fa-trash'></i></button>";
                echo "</td></tr>";
            } else {
                $saldo = floatval($row['saldo_pendiente'] ?? 0);
                $badgePago = $saldo <= 0.01 ? '<span class="badge bg-success">Pagado</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>';
                
                // Tema por nivel
                $idBadge = '';
                $badgePrioridad = '';
                if ($prioridad == 'NIVEL 1') {
                    $idBadge = "<span class='badge' style='background-color: #ffc107; color: #000;'>#{$id}</span>";
                    $badgePrioridad = '<span class="badge" style="background-color: #ffff00; color: #000;">Nivel 1</span>';
                } else {
                    $idBadge = "<span class='badge' style='background-color: #fd7e14; color: #fff;'>#{$id}</span>";
                    $badgePrioridad = '<span class="badge bg-warning text-dark">Nivel 2</span>';
                }

                $rowClass = !empty($row['es_caida_critica']) ? 'table-warning' : '';
                echo "<tr class='{$rowClass}' style='cursor:pointer;' ondblclick='verDetalles({$id})'>";
                echo "<td>{$idBadge}</td>";
                echo "<td>{$fecha}</td>";
                echo "<td>" . (substr($row['hora_solucion'] ?? '', 0, 5) ?: '—') . "</td>";
                echo "<td>{$cliente}</td>";
                echo "<td>{$falla}</td>";
                echo "<td>{$tecnico}</td>";
                echo "<td>{$badgePrioridad}</td>";
                echo "<td>{$badgePago}</td>";
                $estadoBadge = $solucion_completada ? '<span class="badge bg-success">Solucionada</span>' : '<span class="badge bg-warning text-dark">Activa</span>';
                echo "<td id='badge-estado-{$id}'>{$estadoBadge}</td>";
                echo "<td class='text-nowrap'>";
                echo "<button class='btn btn-sm btn-outline-info shadow-sm' onclick='verDetalles({$id})' title='Ver Detalles'><i class='fa-solid fa-eye'></i></button> ";
                echo "<button class='btn btn-sm btn-warning shadow-sm mx-1' onclick='abrirEditar({$id})' title='Editar'><i class='fa-solid fa-pen'></i></button> ";
                
                $btnClass = $solucion_completada ? 'btn-outline-secondary' : 'btn-success';
                $btnIcon = $solucion_completada ? 'rotate-left' : 'check-double';
                $btnText = $solucion_completada ? 'Reactivar' : 'Solucionar';
                $nuevoStatus = $solucion_completada ? 0 : 1;
                echo "<button id='btn-toggle-{$id}' class='btn btn-sm {$btnClass} shadow-sm' onclick='toggleEstado({$id}, {$nuevoStatus}, \"{$prioridad}\")' title='Cambiar Estado'><i class='fa-solid fa-{$btnIcon} me-1'></i>{$btnText}</button> ";
                echo "<button class='btn btn-sm btn-outline-danger shadow-sm' onclick='eliminarSoporte({$id})' title='Eliminar'><i class='fa-solid fa-trash'></i></button>";
                echo "</td></tr>";
            }
        }
        ?>

            <div class="glass-panel border-0 mt-4">
            <div class="card-header bg-transparent border-bottom border-white border-opacity-10 p-0">
                <ul class="nav nav-tabs border-0" id="tablesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($filtro_tipo == 'NIVEL 1' || empty($filtro_tipo)) ? 'active' : ''; ?> fw-bold text-main px-4 py-3 border-0 rounded-0" id="nav-nivel1-tab" data-bs-toggle="tab" data-bs-target="#nav-nivel1" type="button" role="tab" style="background-color: transparent;">
                            <i class="fa-solid fa-screwdriver-wrench text-warning me-1"></i>Fallas Nivel 1 (<?php echo count($rowsNivel1); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($filtro_tipo == 'NIVEL 2') ? 'active' : ''; ?> fw-bold text-main px-4 py-3 border-0 rounded-0" id="nav-nivel2-tab" data-bs-toggle="tab" data-bs-target="#nav-nivel2" type="button" role="tab" style="background-color: transparent;">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Fallas Nivel 2 (<?php echo count($rowsNivel2); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($filtro_tipo == 'NIVEL 3') ? 'active' : ''; ?> fw-bold text-danger px-4 py-3 border-0 rounded-0" id="nav-criticas-tab" data-bs-toggle="tab" data-bs-target="#nav-criticas" type="button" role="tab" style="background-color: transparent;">
                            <i class="fa-solid fa-fire fa-shake me-1"></i>Caídas Críticas Nivel 3 (<?php echo $res_criticas ? $res_criticas->num_rows : 0; ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="nav-tabContent">
                    
                    <!-- TAB NIVEL 1 -->
                    <div class="tab-pane fade <?php echo ($filtro_tipo == 'NIVEL 1' || empty($filtro_tipo)) ? 'show active' : ''; ?>" id="nav-nivel1" role="tabpanel">
                        <div class="bg-warning text-dark p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                                <span class="fw-bold ms-1">Reportes Nivel 1 WhatsApp</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-dark border-dark text-white"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="buscadorNivel1" class="form-control" placeholder="Buscar Nivel 1...">
                                </div>
                                <button class="btn btn-sm btn-light fw-bold text-warning border" onclick="exportarPDF(true, 'NIVEL 1')">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Listado Nivel 1
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="display table table-striped table-bordered w-100 mb-0" id="tablaNivel1">
                                <thead class="table-warning">
                                    <tr>
                                        <th>ID</th><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Tipo Falla</th><th>Técnico</th><th>Nivel</th><th>Pagado</th><th>Estado</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (count($rowsNivel1) > 0) {
                                        foreach ($rowsNivel1 as $r) renderRow($r);
                                    } else {
                                        echo "<tr class='empty-row'><td colspan='10' class='text-center text-muted py-4'>No se encontraron reportes de Nivel 1.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-transparent border-top border-white border-opacity-10 d-flex justify-content-between align-items-center py-3 px-4">
                            <nav><ul class="pagination pagination-sm mb-0" id="paginacionNivel1"></ul></nav>
                            <span id="infoPaginacionNivel1" class="text-muted small">Mostrando registros</span>
                        </div>
                    </div>

                    <!-- TAB NIVEL 2 -->
                    <div class="tab-pane fade <?php echo ($filtro_tipo == 'NIVEL 2') ? 'show active' : ''; ?>" id="nav-nivel2" role="tabpanel">
                        <div style="background-color: #fd7e14;" class="text-white p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span class="fw-bold ms-1">Reportes Nivel 2 Visita Técnica</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-dark border-dark text-white"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="buscadorNivel2" class="form-control" placeholder="Buscar Nivel 2...">
                                </div>
                                <button class="btn btn-sm btn-light fw-bold text-orange border" style="color: #fd7e14 !important;" onclick="exportarPDF(true, 'NIVEL 2')">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Listado Nivel 2
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="display table table-striped table-bordered w-100 mb-0" id="tablaNivel2">
                                <thead class="table-orange-header">
                                    <tr>
                                        <th>ID</th><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Tipo Falla</th><th>Técnico</th><th>Nivel</th><th>Pagado</th><th>Estado</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (count($rowsNivel2) > 0) {
                                        foreach ($rowsNivel2 as $r) renderRow($r);
                                    } else {
                                        echo "<tr class='empty-row'><td colspan='10' class='text-center text-muted py-4'>No se encontraron reportes de Nivel 2.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-transparent border-top border-white border-opacity-10 d-flex justify-content-between align-items-center py-3 px-4">
                            <nav><ul class="pagination pagination-sm mb-0" id="paginacionNivel2"></ul></nav>
                            <span id="infoPaginacionNivel2" class="text-muted small">Mostrando registros</span>
                        </div>
                    </div>

                    <!-- TAB NIVEL 3 (Caídas Críticas) -->
                    <div class="tab-pane fade <?php echo ($filtro_tipo == 'NIVEL 3') ? 'show active' : ''; ?>" id="nav-criticas" role="tabpanel">
                        <div class="bg-danger text-white p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fa-solid fa-fire fa-shake"></i>
                                <span class="fw-bold ms-1">Fallas Masivas de Infraestructura</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-dark border-dark text-white"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="buscadorNivel3" class="form-control" placeholder="Buscar Nivel 3...">
                                </div>
                                <button class="btn btn-sm btn-light fw-bold text-danger border" onclick="exportarPDF(true, 'NIVEL 3')">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Listado Nivel 3
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="table table-bordered mb-0" id="tablaNivel3">
                                <thead class="table-danger">
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Cliente / Referencia</th>
                                        <th>Tipo Falla</th>
                                        <th>Técnico</th>
                                        <th>Client. Afectados</th>
                                        <th>Zona</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($res_criticas && $res_criticas->num_rows > 0) {
                                        mysqli_data_seek($res_criticas, 0);
                                        while ($r = $res_criticas->fetch_assoc()) {
                                            renderRow($r, true);
                                        }
                                    } else {
                                        echo "<tr class='empty-row'><td colspan='9' class='text-center text-muted py-4'>No se encontraron caídas críticas activas.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-transparent border-top border-white border-opacity-10 d-flex justify-content-between align-items-center py-3 px-4">
                            <nav><ul class="pagination pagination-sm mb-0" id="paginacionNivel3"></ul></nav>
                            <span id="infoPaginacionNivel3" class="text-muted small">Mostrando registros</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <style>
        #tablesTabs .nav-link { 
            color: var(--text-main); 
            opacity: 0.7;
            border-bottom: 2px solid transparent; 
            transition: all 0.2s ease-in-out;
        }
        #tablesTabs .nav-link:hover {
            opacity: 1;
        }
        
        /* Tab Nivel 1 (Amarillo / WhatsApp) */
        #tablesTabs #nav-nivel1-tab.active { 
            border-bottom: 3px solid #ffc107; 
            color: #b18400 !important;
            opacity: 1;
            background-color: rgba(255, 193, 7, 0.1) !important; 
        }
        
        /* Tab Nivel 2 (Naranja / Visita Técnico) */
        #tablesTabs #nav-nivel2-tab.active { 
            border-bottom: 3px solid #fd7e14; 
            color: #ca6510 !important; 
            opacity: 1;
            background-color: rgba(253, 126, 20, 0.1) !important; 
        }
        
        /* Tab Nivel 3 (Rojo / Críticas) */
        #tablesTabs #nav-criticas-tab.active { 
            border-bottom: 3px solid #dc3545; 
            color: #dc3545 !important; 
            opacity: 1;
            background-color: rgba(220, 53, 69, 0.1) !important;
        }
        </style>

<!-- =============== MODAL: VER DETALLES NIVEL 3 =============== -->
<div class="modal fade" id="modalCriticaDetalles" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa-solid fa-fire me-2"></i>Caída Crítica #<span id="criticaDetalle_id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="criticaDetalleBody">
                <div class="text-center py-5"><span class="spinner-border text-danger"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button id="btnCriticaEditar" class="btn btn-warning"><i class="fa-solid fa-pen me-1"></i>Editar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============== MODAL: EDITAR CAÍDA CRÍTICA =============== -->
<div class="modal fade" id="modalCriticaEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen me-2"></i>Editar Caída Crítica #<span id="criticaEdit_id"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="critica_edit_id_soporte">
                <div class="alert alert-danger py-2 mb-3"><i class="fa-solid fa-fire me-1"></i><strong>Falla Masiva NIVEL 3</strong> — Afecta múltiples clientes o infraestructura de red.</div>

                <!-- Infraestructura -->
                <div class="p-2 mb-2 bg-light border-start border-danger border-4 d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Infraestructura Afectada</span>
                    <button type="button" id="btn-add-olt-critica-edit" class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-plus me-1"></i> Agregar OLT / PON
                    </button>
                </div>
                <div id="olts-critica-edit-container" class="mb-3">
                    <!-- Filas dinámicas se insertan aquí -->
                </div>

                <!-- Datos principales -->
                <div class="p-2 mb-2 bg-light border-start border-danger border-4 fw-bold">Datos del Incidente</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipo de Falla (Descriptivo)</label>
                        <input type="text" class="form-control" id="critica_tipo_falla" placeholder="Ej: Corte fibra óptica, Sin energía OLT...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Clientes Afectados</label>
                        <input type="number" class="form-control" id="critica_clientes" min="1" value="50">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Fecha</label>
                        <input type="date" class="form-control" id="critica_fecha">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Zona Afectada</label>
                        <input type="text" class="form-control" id="critica_zona" placeholder="Ej: Sector Norte, Barrio X...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Técnico Responsable</label>
                        <input type="text" class="form-control" id="critica_tecnico" placeholder="Nombre del técnico...">
                    </div>
                </div>

                <!-- Descripción -->
                <div class="p-2 mb-2 bg-light border-start border-primary border-4 fw-bold">Descripción y Seguimiento</div>
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Observaciones / Descripción del Problema</label>
                        <textarea class="form-control" id="critica_observaciones" rows="3" placeholder="Describa el alcance de la falla..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Notas Internas</label>
                        <textarea class="form-control" id="critica_notas" rows="2"></textarea>
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="critica_solucionada">
                    <label class="form-check-label fw-bold text-success" for="critica_solucionada">¿Falla Solucionada?</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold" id="btnGuardarCriticaEdit" onclick="guardarEdicionCritica()">
                    <i class="fa-solid fa-save me-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =============== MODAL: VER DETALLES =============== -->
<div class="modal fade" id="modalVerDetalles" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa-solid fa-eye me-2"></i>Detalles del Reporte <span
                        id="ver_modal_id"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="verDetallesBody">
                <div class="text-center py-5"><span class="spinner-border text-info"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a id="btnVerPDF" href="#" target="_blank" class="btn btn-danger"><i
                        class="fa-solid fa-file-pdf me-1"></i>Ver PDF</a>
                <button id="btnVerEditar" onclick="" class="btn btn-warning"><i
                        class="fa-solid fa-pen me-1"></i>Editar</button>
            </div>
        </div>
    </div>
</div>

<!-- =============== MODAL: EDITAR SOPORTE =============== -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form action="actualizar_soporte.php" method="POST" class="modal-content" id="formEditarSoporte">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen me-2"></i>Editar Soporte #<span
                        id="edit_modal_id_display"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_soporte_edit" id="id_soporte_edit">
                <input type="hidden" name="origen" value="gestion_fallas">

                <!-- Encabezado -->
                <div class="row mb-3">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Fecha</label>
                        <input type="date" class="form-control" name="fecha_edit" id="fecha_edit" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Técnico Asignado</label>
                        <select class="form-select" name="tecnico_edit" id="tecnico_edit">
                            <option value="">-- Seleccionar técnico --</option>
                            <?php foreach ($instaladoresList as $instalador): ?>
                            <option value="<?php echo htmlspecialchars($instalador); ?>">
                                <?php echo htmlspecialchars($instalador); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Sector</label>
                        <input type="text" class="form-control" name="sector" id="sector_edit">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Teléfono Contacto</label>
                        <input type="text" class="form-control" name="telefono_edit" id="telefono_edit">
                    </div>
                    <div class="col-md-3 mb-3">
                                        <label class="form-label fw-bold">Prioridad</label>
                                        <select class="form-select" name="prioridad_edit" id="prioridad_edit">
                                            <option value="NIVEL 1">NIVEL 1 (WhatsApp)</option>
                                            <option value="NIVEL 2">NIVEL 2 (Visita)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label fw-bold">Hora Solución</label>
                                        <input type="time" class="form-control" name="hora_edit" id="hora_edit" step="1">
                                    </div>
                </div>

                <!-- Falla -->
                <div class="section-title border-danger">Información de Falla</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipo de Falla</label>
                        <select class="form-select" name="tipo_falla_edit" id="tipo_falla_edit">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tipo Servicio</label>
                        <select class="form-select" name="tipo_servicio" id="tipo_servicio_edit">
                            <option value="FTTH">FTTH (Fibra)</option>
                            <option value="RADIO">Radio/Antena</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="es_caida_critica_edit"
                                id="es_caida_critica_edit" value="1">
                            <label class="form-check-label text-danger fw-bold" for="es_caida_critica_edit">¿Caída
                                Crítica?</label>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6" id="clientesAfectadosEditContainer">
                        <label class="form-label fw-bold">Clientes Afectados</label>
                        <input type="number" class="form-control" name="clientes_afectados_edit" id="clientes_afectados_edit" min="1">
                    </div>
                </div>

                <div class="section-title">OLTs / PONs Afectados</div>
                <div id="olts-edit-container" class="mb-2">
                    <!-- Filas dinámicas se insertan aquí -->
                </div>
                <div class="mb-3">
                    <button type="button" id="btn-add-olt-edit" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-plus me-1"></i> Agregar otra OLT / PON
                    </button>
                </div>

                <!-- Detalles Técnicos -->
                <div class="section-title">Detalles Técnicos</div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label text-muted small">IP</label>
                        <input type="text" class="form-control" name="ip" id="ip_edit" placeholder="0.0.0.0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Estado ONU</label>
                        <select class="form-select" name="estado_onu" id="estado_onu_edit">
                            <option value="">--</option>
                            <option value="ON">ON</option>
                            <option value="OFF">OFF</option>
                            <option value="LOS">LOS</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Estado Router</label>
                        <select class="form-select" name="estado_router" id="estado_router_edit">
                            <option value="">--</option>
                            <option value="ON">ON</option>
                            <option value="OFF">OFF</option>
                            <option value="RESET">Reset</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Modelo Router</label>
                        <input type="text" class="form-control" name="modelo_router" id="modelo_router_edit">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label class="form-label small">Dispositivos</label>
                        <input type="number" class="form-control" name="num_dispositivos" id="num_dispositivos_edit">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Bajada</label>
                        <input type="text" class="form-control" name="bw_bajada" id="bw_bajada_edit" placeholder="MB">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Subida</label>
                        <input type="text" class="form-control" name="bw_subida" id="bw_subida_edit" placeholder="MB">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Ping</label>
                        <input type="text" class="form-control" name="bw_ping" id="bw_ping_edit" placeholder="ms">
                    </div>
                </div>

                <div class="info-box-premium">
                    <div class="col-12 mb-2"><small class="text-muted fw-bold text-uppercase" style="letter-spacing: 1px;">Solo Radio / Antena:</small></div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Antena Estado</label>
                            <input type="text" class="form-control" name="estado_antena" id="estado_antena_edit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Valores dBm</label>
                            <input type="text" class="form-control" name="valores_antena" id="valores_antena_edit">
                        </div>
                    </div>
                </div>

                <!-- Diagnóstico y Solución -->
                <div class="section-title">Diagnóstico y Solución</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Observaciones / Problema</label>
                        <textarea class="form-control" name="descripcion_edit" id="descripcion_edit" rows="3"
                            required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sugerencias al Cliente</label>
                        <textarea class="form-control" name="sugerencias" id="sugerencias_edit" rows="3"></textarea>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notas Internas</label>
                    <textarea class="form-control" name="notas_internas_edit" id="notas_internas_edit"
                        rows="2"></textarea>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="solucion_completada_edit"
                        name="solucion_completada">
                    <label class="form-check-label fw-bold" for="solucion_completada_edit">¿Falla Solucionada?</label>
                </div>

                <!-- Costos -->
                <div class="section-title">Costos y Facturación</div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Monto Total ($)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="monto_total_edit"
                        id="monto_total_edit" required>
                </div>

                <!-- Firmas -->
                <div class="section-title">Actualizar Firmas (Opcional)</div>
                <p class="text-muted small">Deje los lienzos en blanco para conservar las firmas originales.</p>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Firma Técnico</label>
                        <div class="mb-1 text-center d-none" id="container_firma_tech_edit">
                            <span class="badge bg-info mb-1">Firma Actual</span><br>
                            <img id="imgFirmaTech_edit" src="" style="max-height: 80px; border: 1px dashed #ccc;">
                        </div>
                        <canvas id="sigTechEdit"
                            style="border:1px solid #ccc;width:100%;height:120px;border-radius:4px;background:#fcfcfc;"></canvas>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                            onclick="clearPadEdit('tech')">Limpiar</button>
                        <input type="hidden" name="firma_tecnico_data" id="firma_tecnico_data_edit">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Firma Cliente</label>
                        <div class="mb-1 text-center d-none" id="container_firma_cli_edit">
                            <span class="badge bg-info mb-1">Firma Actual</span><br>
                            <img id="imgFirmaCli_edit" src="" style="max-height: 80px; border: 1px dashed #ccc;">
                        </div>
                        <canvas id="sigCliEdit"
                            style="border:1px solid #ccc;width:100%;height:120px;border-radius:4px;background:#fcfcfc;"></canvas>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                            onclick="clearPadEdit('cli')">Limpiar</button>
                        <input type="hidden" name="firma_cliente_data" id="firma_cliente_data_edit">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold" id="btnGuardarEdicion"><i
                        class="fa-solid fa-save me-1"></i>Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Configuración de Opciones -->
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-cog me-2"></i>Gestión de Tipos de Falla</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="nav nav-tabs nav-fill bg-light border-bottom" id="configTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active py-2 small fw-bold text-uppercase" data-bs-toggle="tab" data-bs-target="#config-n1">Nivel 1 (Baja)</button></li>
                    <li class="nav-item"><button class="nav-link py-2 small fw-bold text-uppercase" data-bs-toggle="tab" data-bs-target="#config-n2">Nivel 2 (Media)</button></li>
                    <li class="nav-item"><button class="nav-link py-2 small fw-bold text-uppercase" data-bs-toggle="tab" data-bs-target="#config-n3">Nivel 3 (Alta)</button></li>
                </ul>
                <div class="tab-content p-3">
                    <!-- NIVEL 1 -->
                    <div class="tab-pane fade show active" id="config-n1">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="nuevoTipoFallaN1" placeholder="Nuevo tipo Nivel 1...">
                            <button class="btn btn-warning" type="button" onclick="agregarOpcion('NIVEL 1')"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <ul class="list-group list-group-flush border rounded" id="listaFallasN1" style="max-height: 300px; overflow-y: auto;"></ul>
                    </div>
                    <!-- NIVEL 2 -->
                    <div class="tab-pane fade" id="config-n2">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="nuevoTipoFallaN2" placeholder="Nuevo tipo Nivel 2...">
                            <button class="btn btn-warning" type="button" style="background-color: #fd7e14; border-color: #fd7e14; color: white;" onclick="agregarOpcion('NIVEL 2')"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <ul class="list-group list-group-flush border rounded" id="listaFallasN2" style="max-height: 300px; overflow-y: auto;"></ul>
                    </div>
                    <!-- NIVEL 3 -->
                    <div class="tab-pane fade" id="config-n3">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="nuevoTipoFallaN3" placeholder="Nuevo tipo Nivel 3...">
                            <button class="btn btn-danger" type="button" onclick="agregarOpcion('NIVEL 3')"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <ul class="list-group list-group-flush border rounded" id="listaFallasN3" style="max-height: 300px; overflow-y: auto;"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>

<script>
    let charts = {};
    let lastChartData = null; // Guardar últimos datos para re-renderizar en cambio de tema
    let padTechEdit = null, padCliEdit = null;
    let _currentEditId = null;

    // --- Notificaciones de Estado ---
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'success') {
            Swal.fire({
                icon: 'success',
                title: '¡Operación Exitosa!',
                text: urlParams.get('msg') || 'Datos actualizados correctamente.',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
            // Limpiar la URL para evitar re-lanzar la alerta al recargar
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('status') === 'error') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: urlParams.get('msg') || 'No se pudo completar la operación.'
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    // ---- Ver Detalles (Modal AJAX) ----
    function verDetalles(id) {
        _currentEditId = id;
        $('#ver_modal_id').text('#' + id);
        $('#verDetallesBody').html('<div class="text-center py-5"><span class="spinner-border text-info"></span></div>');
        $('#btnVerPDF').attr('href', 'generar_pdf_reporte.php?id=' + id);
        $('#btnVerEditar').attr('onclick', 'verAEditar(' + id + ')');
        new bootstrap.Modal(document.getElementById('modalVerDetalles')).show();

        fetch('get_soporte_detalle.php?id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.error) { $('#verDetallesBody').html('<div class="alert alert-danger">' + d.error + '</div>'); return; }
                const f = (v) => v ? v : '—';
                const badge = (p) => {
                    if (p === 'NIVEL 1') return '<span class="badge" style="background:#ffff00;color:#000">NIVEL 1</span>';
                    if (p === 'NIVEL 2') return '<span class="badge bg-warning text-dark">NIVEL 2</span>';
                    if (p === 'NIVEL 3') return '<span class="badge bg-danger">NIVEL 3</span>';
                    return '<span class="badge bg-secondary">' + f(p) + '</span>';
                };
                // Generar lista de OLTs/PONs
                let infraHtml = '';
                if (d.olts_afectadas && d.olts_afectadas.length > 0) {
                    d.olts_afectadas.forEach(p => {
                        infraHtml += `<p class="mb-1"><strong>OLT:</strong> <span class="badge bg-dark">${f(p.nombre_olt)}</span> 
                                      <strong>PON:</strong> <span class="badge bg-secondary">${f(p.nombre_pon)}</span></p>`;
                    });
                } else {
                    infraHtml = `<p class="mb-1"><strong>OLT:</strong> <span class="badge bg-dark">${f(d.nombre_olt)}</span></p>
                                 <p class="mb-1"><strong>PON:</strong> <span class="badge bg-secondary">${f(d.nombre_pon)}</span></p>`;
                }

                const path_root = '../../';
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-header bg-primary text-white"><i class="fa-solid fa-user me-2"></i>Cliente</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Nombre:</strong> ${f(d.nombre_completo)}</p>
                                <p class="mb-1"><strong>Cédula:</strong> ${f(d.cedula)}</p>
                                <p class="mb-1"><strong>IP:</strong> <code>${f(d.ip_address)}</code></p>
                                <p class="mb-0"><strong>Teléfono:</strong> ${f(d.telefono)}</p>
                            </div>
                        </div>
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-header bg-info text-white"><i class="fa-solid fa-calendar-check me-2"></i>Visita</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Fecha:</strong> ${f(d.fecha_soporte_form)}</p>
                                <p class="mb-1"><strong>Hora:</strong> ${f(d.hora_solucion ? d.hora_solucion.substring(0,5) : '')}</p>
                                <p class="mb-1"><strong>Técnico:</strong> ${f(d.tecnico_asignado)}</p>
                                <p class="mb-1"><strong>Sector:</strong> ${f(d.sector)}</p>
                                ${infraHtml}
                                <p class="mb-1"><strong>Tipo Falla:</strong> ${f(d.tipo_falla)}</p>
                                <p class="mb-1"><strong>Prioridad:</strong> ${badge(d.prioridad)}</p>
                                <p class="mb-0"><strong>Caída Crítica:</strong> ${d.es_caida_critica == 1 ? '<span class=\"badge bg-danger\">Sí (' + f(d.clientes_afectados) + ' clientes)</span>' : 'No'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-header bg-warning"><i class="fa-solid fa-tools me-2"></i>Diagnóstico</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>ONU:</strong> ${f(d.estado_onu)} | <strong>Router:</strong> ${f(d.estado_router)}</p>
                                <p class="mb-1"><strong>Modelo:</strong> ${f(d.modelo_router)}</p>
                                <p class="mb-1"><strong>BW:</strong> ↓${f(d.bw_bajada)} / ↑${f(d.bw_subida)} / Ping:${f(d.bw_ping)}</p>
                                <p class="mb-1"><strong>Observaciones:</strong> ${f(d.observaciones)}</p>
                                <p class="mb-0"><strong>Solucionada:</strong> ${d.solucion_completada == 1 ? '<span class=\"badge bg-success\">Sí</span>' : '<span class=\"badge bg-warning text-dark\">No</span>'}</p>
                            </div>
                        </div>
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-header bg-success text-white"><i class="fa-solid fa-dollar-sign me-2"></i>Financiero</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Total:</strong> $${parseFloat(d.monto_total || 0).toFixed(2)}</p>
                                <p class="mb-1"><strong>Pagado:</strong> $${parseFloat(d.monto_pagado || 0).toFixed(2)}</p>
                                <p class="mb-0"><strong>Saldo:</strong> <span class="${(d.monto_total - d.monto_pagado) > 0.01 ? 'text-danger fw-bold' : 'text-success fw-bold'}">$${(parseFloat(d.monto_total || 0) - parseFloat(d.monto_pagado || 0)).toFixed(2)}</span></p>
                            </div>
                        </div>
                    </div>
                </div>`;
                // Firmas
                if (d.firma_tecnico || d.firma_cliente) {
                    html += '<div class="row mt-2">';
                    if (d.firma_tecnico) html += `<div class="col-6 text-center"><small class="text-muted">Firma Técnico</small><br><img src="${path_root}uploads/firmas/${d.firma_tecnico}" class="img-fluid border" style="max-height:100px;"></div>`;
                    if (d.firma_cliente) html += `<div class="col-6 text-center"><small class="text-muted">Firma Cliente</small><br><img src="${path_root}uploads/firmas/${d.firma_cliente}" class="img-fluid border" style="max-height:100px;"></div>`;
                    html += '</div>';
                }
                $('#verDetallesBody').html(html);
            })
            .catch(() => $('#verDetallesBody').html('<div class="alert alert-danger">Error al cargar datos.</div>'));
    }

    function verAEditar(id) {
        bootstrap.Modal.getInstance(document.getElementById('modalVerDetalles'))?.hide();
        setTimeout(() => abrirEditar(id), 300);
    }

    // ---- Abrir Editar Modal ----
    function abrirEditar(id) {
        _currentEditId = id;
        $('#edit_modal_id_display').text(id);
        $('#id_soporte_edit').val(id);
        // Reset firmas
        $('#container_firma_tech_edit, #container_firma_cli_edit').addClass('d-none');
        // Hide clientes afectados by default
        $('#clientesAfectadosEditContainer').hide();


        fetch('get_soporte_detalle.php?id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.error) { alert('Error: ' + d.error); return; }
                
                const prioridad = d.prioridad || 'NIVEL 1';
                $('#prioridad_edit').val(prioridad);
                
                cargarOpcionesFallaEdit(prioridad, () => {
                    $('#fecha_edit').val(d.fecha_soporte_form);
                    $('#hora_edit').val(d.hora_solucion || '');
                    $('#tecnico_edit').val(d.tecnico_asignado || '');
                    $('#sector_edit').val(d.sector || '');
                    $('#telefono_edit').val(d.telefono || '');
                    $('#tipo_falla_edit').val(d.tipo_falla || '');
                    $('#tipo_servicio_edit').val(d.tipo_servicio || 'FTTH');
                    $('#es_caida_critica_edit').prop('checked', d.es_caida_critica == 1);
                    if (d.es_caida_critica == 1) {
                        $('#clientesAfectadosEditContainer').show();
                        $('#clientes_afectados_edit').val(d.clientes_afectados);
                        $('#zona_afectada_edit').val(d.zona_afectada);
                    } else {
                        $('#clientesAfectadosEditContainer').hide();
                    }

                    // --- Cargar OLTs/PONs afectadas ---
                    $('#olts-edit-container').empty();
                    if (d.olts_afectadas && d.olts_afectadas.length > 0) {
                        d.olts_afectadas.forEach(item => {
                            addOltRowEdit(item.id_olt, item.id_pon);
                        });
                    } else {
                        // Fallback: si no hay en la tabla de relación, usar los del soporte principal
                        addOltRowEdit(d.id_olt, d.id_pon);
                    }

                    $('#ip_edit').val(d.ip_address || '');
                    $('#estado_onu_edit').val(d.estado_onu || '');
                    $('#estado_router_edit').val(d.estado_router || '');
                    $('#modelo_router_edit').val(d.modelo_router || '');
                    $('#num_dispositivos_edit').val(d.num_dispositivos || '');
                    $('#bw_bajada_edit').val(d.bw_bajada || '');
                    $('#bw_subida_edit').val(d.bw_subida || '');
                    $('#bw_ping_edit').val(d.bw_ping || '');
                    $('#estado_antena_edit').val(d.estado_antena || '');
                    $('#valores_antena_edit').val(d.valores_antena || '');
                    $('#descripcion_edit').val(d.observaciones || '');
                    $('#sugerencias_edit').val(d.sugerencias || '');
                    $('#notas_internas_edit').val(d.notas_internas || '');
                    $('#solucion_completada_edit').prop('checked', d.solucion_completada == 1);
                    $('#monto_total_edit').val(parseFloat(d.monto_total || 0).toFixed(2));

                    // Firmas actuales
                    const pathRoot = '../../';
                    if (d.firma_tecnico) {
                        $('#imgFirmaTech_edit').attr('src', pathRoot + 'uploads/firmas/' + d.firma_tecnico);
                        $('#container_firma_tech_edit').removeClass('d-none');
                    }
                    if (d.firma_cliente) {
                        $('#imgFirmaCli_edit').attr('src', pathRoot + 'uploads/firmas/' + d.firma_cliente);
                        $('#container_firma_cli_edit').removeClass('d-none');
                    }

                    new bootstrap.Modal(document.getElementById('modalEditar')).show();
                }); // End cargarOpcionesFallaEdit callback
            })
            .catch(() => alert('Error al cargar datos del soporte.'));
    }

    // ---- Cargar opciones de falla ----
    function cargarOpcionesFallaEdit(nivel, callback) {
        if (!nivel) nivel = $('#prioridad_edit').val() || 'NIVEL 1';
        fetch('admin_opciones.php?action=read')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('tipo_falla_edit');
                const current = sel.value;
                sel.innerHTML = '<option value="">-- Seleccionar --</option>';
                if (data.success && data.data[nivel]) {
                    data.data[nivel].forEach(op => {
                        const o = document.createElement('option');
                        o.value = op; o.textContent = op;
                        sel.appendChild(o);
                    });
                }
                if (current) sel.value = current;
                if (callback) callback();
            })
            .catch(() => { if (callback) callback(); });
    }

    // Actualizar opciones al cambiar prioridad en modal
    $('#prioridad_edit').change(function() {
        $('#tipo_falla_edit').val(''); 
        cargarOpcionesFallaEdit($(this).val());
    });

    // ---- SignaturePad para modal de edición ----
    $('#modalEditar').on('shown.bs.modal', function () {
        const canvasTech = document.getElementById('sigTechEdit');
        const canvasCli = document.getElementById('sigCliEdit');
        function resizeCanvas(canvas) {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
        }
        resizeCanvas(canvasTech);
        resizeCanvas(canvasCli);
        if (!padTechEdit) padTechEdit = new SignaturePad(canvasTech);
        else padTechEdit.clear();
        if (!padCliEdit) padCliEdit = new SignaturePad(canvasCli);
        else padCliEdit.clear();
    });

    function clearPadEdit(type) {
        if (type === 'tech' && padTechEdit) padTechEdit.clear();
        if (type === 'cli' && padCliEdit) padCliEdit.clear();
    }

    // ---- On Edit Form Submit ----
    document.getElementById('formEditarSoporte').addEventListener('submit', function (e) {
        if (padTechEdit && !padTechEdit.isEmpty()) {
            document.getElementById('firma_tecnico_data_edit').value = padTechEdit.toDataURL();
        }
        if (padCliEdit && !padCliEdit.isEmpty()) {
            document.getElementById('firma_cliente_data_edit').value = padCliEdit.toDataURL();
        }
        const btn = document.getElementById('btnGuardarEdicion');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Guardando...';
    });

    $(document).ready(function () {
        // Observador para detectar cambios de tema y actualizar gráficas en tiempo real
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'data-theme' && lastChartData) {
                    crearGraficos(lastChartData);
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });

        cargarEstadisticas();
        // DataTables initialization removed as per instruction
    });

    function cargarEstadisticas() {
        const fechaDesde = $('#fecha_desde').val();
        const fechaHasta = $('#fecha_hasta').val();
        const tipoFalla = $('#tipo_falla').val();
        const tecnico = $('#filtro_tecnico').val();
        const estadoPago = $('#estado_pago').val();

        // Cargar estadísticas estándar
        $.ajax({
            url: 'obtener_estadisticas.php',
            data: {
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
                tipo_falla: tipoFalla,
                tecnico: tecnico,
                estado_pago: estadoPago
            },
            dataType: 'json',
            success: function (data) {
                if (data && data.success) {
                    // Update main cards 
                    $('#tiempo_promedio').text((data.tiempo_respuesta?.promedio_respuesta || 0) + 'h');

                    // The rest are updated by actualizarKPIsAvanzados, but wait, those were in the other ajax call.
                    // Let's call the chart function
                    crearGraficos(data);
                } else {
                    console.error("Estadisticas response error: ", data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error, xhr.responseText);
            }
        });

        // Cargar métricas avanzadas (con try/catch)
        $.ajax({
            url: 'obtener_metricas_avanzadas.php',
            data: {
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
                tipo_falla: tipoFalla,
                tecnico: tecnico,
                estado_pago: estadoPago
            },
            dataType: 'json',
            success: function (data) {
                if (data && data.success) {
                    actualizarKPIsAvanzados(data);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Avanzadas Error: ", error, xhr.responseText);
            }
        });
    }

    function actualizarKPIsAvanzados(data) {
        // Tiempo promedio de respuesta
        const tiempoPromedio = data.tiempo_respuesta?.promedio_respuesta || 0;
        $('#tiempo_promedio').text(tiempoPromedio);

        // Fallas críticas activas
        const fallasActivas = data.fallas_criticas?.activas || 0;
        $('#fallas_criticas_activas').text(fallasActivas);

        // Clientes recurrentes
        const clientesRecurrentes = data.clientes_recurrentes?.length || 0;
        $('#clientes_recurrentes_count').text(clientesRecurrentes);

        // Zona más afectada
        const zonas = data.zonas_afectadas || {};
        const zonasArray = Object.entries(zonas).map(([zona, info]) => ({
            zona: zona,
            total: info.total
        })).sort((a, b) => b.total - a.total);

        if (zonasArray.length > 0) {
            $('#zona_top').text(zonasArray[0].zona);
            $('#zona_top_count').text(zonasArray[0].total + ' fallas');
        } else {
            $('#zona_top').text('N/A');
            $('#zona_top_count').text('');
        }

        // Mostrar alerta de caídas críticas si hay activas
        const caidasActivas = data.caidas_recientes?.filter(c => c.estado === 'Activa') || [];
        if (caidasActivas.length > 0) {
            $('#alertCaidasCriticas').removeClass('d-none');
            const lista = caidasActivas.map(caida =>
                `<li><strong>Ticket #${caida.id}</strong> - ${caida.tipo} en ${caida.zona} 
                 (${caida.clientes_afectados} clientes, ${caida.horas_caida}h)</li>`
            ).join('');
            $('#listaCaidasCriticas').html(lista);
        } else {
            $('#alertCaidasCriticas').addClass('d-none');
        }
    }


    function crearGraficos(data) {
        if (!data) return;
        lastChartData = data; // Persistir datos para cambios de tema

        // Destruir gráficos anteriores
        Object.values(charts).forEach(chart => chart.destroy());

        // Obtener colores del tema desde variables CSS para máxima precisión
        const style = getComputedStyle(document.documentElement);
        const textColor = style.getPropertyValue('--text-main').trim() || '#334155';
        const gridColor = style.getPropertyValue('--table-border').trim() || 'rgba(0, 0, 0, 0.1)';

        // Configuración global de escalas reutilizable
        const scaleDefaults = {
            ticks: { color: textColor, font: { size: 12 } },
            grid: { color: gridColor }
        };

        // 1. Gráfico de fallas por tipo (barras horizontales)
        const ctxTipo = document.getElementById('chartFallasTipo').getContext('2d');
        const tipoLabels = Object.keys(data.fallas_por_tipo).slice(0, 10);
        const tipoData = Object.values(data.fallas_por_tipo).slice(0, 10);

        charts.tipo = new Chart(ctxTipo, {
            type: 'bar',
            data: {
                labels: tipoLabels,
                datasets: [{
                    label: 'Cantidad de Reportes',
                    data: tipoData,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: 'rgba(20,20,30,0.9)', titleColor: '#fff', bodyColor: '#ccc' }
                },
                scales: {
                    x: scaleDefaults,
                    y: scaleDefaults
                }
            }
        });

        // 2. Gráfico de reportes por mes (línea)
        const ctxMes = document.getElementById('chartReportesMes').getContext('2d');
        const mesLabels = data.meses_labels || [];
        const mesData = Object.values(data.reportes_por_mes);

        charts.mes = new Chart(ctxMes, {
            type: 'line',
            data: {
                labels: mesLabels,
                datasets: [{
                    label: 'Reportes',
                    data: mesData,
                    borderColor: 'rgba(25, 135, 84, 1)',
                    backgroundColor: 'rgba(25, 135, 84, 0.15)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgba(25, 135, 84, 1)',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: textColor }
                    },
                    tooltip: { backgroundColor: 'rgba(20,20,30,0.9)', titleColor: '#fff', bodyColor: '#ccc' }
                },
                scales: {
                    x: scaleDefaults,
                    y: scaleDefaults
                }
            }
        });

        // 3. Gráfico de técnicos (dona)
        const ctxTecnico = document.getElementById('chartTecnicos').getContext('2d');
        const tecnicoLabels = Object.keys(data.reportes_por_tecnico);
        const tecnicoData = tecnicoLabels.map(t => data.reportes_por_tecnico[t].cantidad);

        charts.tecnico = new Chart(ctxTecnico, {
            type: 'doughnut',
            data: {
                labels: tecnicoLabels,
                datasets: [{
                    data: tecnicoData,
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.8)',
                        'rgba(25, 135, 84, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(13, 202, 240, 0.8)',
                        'rgba(108, 117, 125, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(253, 126, 20, 0.8)',
                        'rgba(32, 201, 151, 0.8)',
                        'rgba(214, 51, 132, 0.8)'
                    ],
                    borderWidth: 0,
                    borderColor: 'transparent'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: textColor,
                            generateLabels: function(chart) {
                                const ds = chart.data.datasets[0];
                                const total = ds.data.reduce((a, b) => a + b, 0);
                                return chart.data.labels.map((label, i) => {
                                    const val = ds.data[i];
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                                    return {
                                        text: label + '  —  ' + val + ' (' + pct + '%)',
                                        fillStyle: ds.backgroundColor[i],
                                        strokeStyle: ds.backgroundColor[i],
                                        fontColor: textColor,
                                        color: textColor,
                                        hidden: false,
                                        index: i
                                    };
                                });
                            },
                            font: { size: 12 },
                            padding: 10
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20,20,30,0.9)',
                        titleColor: '#fff',
                        bodyColor: '#ccc',
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed;
                                const total = tecnicoData.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + val + ' reportes (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // 4. Gráfico de Niveles de Prioridad (dona)
        const ctxNiveles = document.getElementById('chartNiveles').getContext('2d');
        const nivelesLabels = Object.keys(data.fallas_por_nivel || {});
        const nivelesData = Object.values(data.fallas_por_nivel || {});
        const totalFallas = nivelesData.reduce((a, b) => a + b, 0);

        charts.niveles = new Chart(ctxNiveles, {
            type: 'doughnut',
            data: {
                labels: nivelesLabels,
                datasets: [{
                    data: nivelesData,
                    backgroundColor: nivelesLabels.map(l => {
                        if (l === 'NIVEL 3') return 'rgba(220, 53, 69, 0.85)';
                        if (l === 'NIVEL 2') return 'rgba(253, 126, 20, 0.85)';
                        if (l === 'NIVEL 1') return 'rgba(255, 193, 7, 0.9)';
                        return 'rgba(108, 117, 125, 0.8)';
                    }),
                    borderWidth: 0,
                    borderColor: 'transparent'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: textColor,
                            generateLabels: function(chart) {
                                const ds = chart.data.datasets[0];
                                return chart.data.labels.map((label, i) => {
                                    const val = ds.data[i];
                                    const pct = totalFallas > 0 ? ((val / totalFallas) * 100).toFixed(1) : '0.0';
                                    return {
                                        text: label + '  —  ' + val + ' (' + pct + '%)',
                                        fillStyle: ds.backgroundColor[i],
                                        strokeStyle: ds.backgroundColor[i],
                                        fontColor: textColor,
                                        color: textColor,
                                        hidden: false,
                                        index: i
                                    };
                                });
                            },
                            font: { size: 12 },
                            padding: 14
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(20,20,30,0.9)',
                        titleColor: '#fff',
                        bodyColor: '#ccc',
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed || 0;
                                const percentage = totalFallas > 0 ? ((value / totalFallas) * 100).toFixed(1) : 0;
                                return context.label + ': ' + value + ' reportes (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Interceptar el formulario de filtros para actualizar estadísticas vía AJAX
    document.getElementById('formFiltros').addEventListener('submit', function (e) {
        // Permitimos el submit normal para recargar la tabla (Datatables Server Side)
        // Pero también llamamos a cargarEstadisticas() si es necesario.
        // Dado el estado actual, el document.ready ya maneja la carga inicial con los params de la URL.
        // Si el usuario quiere que sea puramente AJAX sin recarga, usaríamos e.preventDefault().
    });

    function exportarPDF(soloPrioridad = false, prioridadEspecifica = '') {
        const params = new URLSearchParams({
            fecha_desde: $('#fecha_desde').val(),
            fecha_hasta: $('#fecha_hasta').val(),
            tipo_falla: $('#tipo_falla').val(),
            tecnico: $('#filtro_tecnico').val(),
            estado_pago: $('#estado_pago').val()
        });
        
        if (soloPrioridad && prioridadEspecifica) {
            params.append('filtro_prioridad', prioridadEspecifica);
        }
        
        window.open('generar_pdf_consolidado.php?' + params.toString(), '_blank');
    }

    // --- Paginación Tabulada (Nivel 1, Nivel 2, Nivel 3) ---
    function initPaginacion(tablaId, buscadorId, infoId, pagId) {
        const filasPorPagina = 10;
        let paginaActual = 1;

        function actualizarTabla() {
            const buscador = $(`#${buscadorId}`).val().toLowerCase();
            const todasLasFilas = $(`#${tablaId} tbody tr`);
            let filasFiltradas = [];

            if (todasLasFilas.length === 1 && todasLasFilas.hasClass('empty-row')) return;

            todasLasFilas.each(function () {
                let fila = $(this);
                let textoFila = fila.text().toLowerCase();
                if (textoFila.includes(buscador)) {
                    filasFiltradas.push(fila);
                    fila.removeClass('d-none');
                } else {
                    fila.addClass('d-none');
                }
            });

            const totalFiltradas = filasFiltradas.length;
            const totalPaginas = Math.ceil(totalFiltradas / filasPorPagina);

            if (paginaActual > totalPaginas && totalPaginas > 0) paginaActual = totalPaginas;
            else if (paginaActual < 1) paginaActual = 1;

            const inicio = (paginaActual - 1) * filasPorPagina;
            const fin = inicio + filasPorPagina;

            for (let i = 0; i < totalFiltradas; i++) {
                if (i >= inicio && i < fin) filasFiltradas[i].removeClass('d-none');
                else filasFiltradas[i].addClass('d-none');
            }

            const numInicioDisp = totalFiltradas === 0 ? 0 : inicio + 1;
            const numFinDisp = (fin > totalFiltradas) ? totalFiltradas : fin;
            $(`#${infoId}`).text(`Mostrando ${numInicioDisp} a ${numFinDisp} de ${totalFiltradas} registros`);
            
            renderizarControles(totalPaginas);
        }

        function renderizarControles(totalPaginas) {
            const contenedor = $(`#${pagId}`);
            contenedor.empty();
            
            const total = Math.max(1, totalPaginas);

            const disAnt = paginaActual <= 1 ? 'disabled' : '';
            contenedor.append(`<li class="page-item ${disAnt}"><a class="page-link" href="javascript:void(0)" data-page="${paginaActual - 1}"><i class="fa-solid fa-chevron-left"></i></a></li>`);

            let startPage = Math.max(1, paginaActual - 2);
            let endPage = startPage + 4;
            if (endPage > total) {
                endPage = total;
                startPage = Math.max(1, endPage - 4);
            }

            for (let i = startPage; i <= endPage; i++) {
                const activo = i === paginaActual ? 'active' : '';
                contenedor.append(`<li class="page-item ${activo}"><a class="page-link" href="javascript:void(0)" data-page="${i}">${i}</a></li>`);
            }

            const disSig = paginaActual >= total ? 'disabled' : '';
            contenedor.append(`<li class="page-item ${disSig}"><a class="page-link" href="javascript:void(0)" data-page="${paginaActual + 1}"><i class="fa-solid fa-chevron-right"></i></a></li>`);

            contenedor.find('.page-link').on('click', function() {
                const page = $(this).data('page');
                if(page) {
                    paginaActual = parseInt(page);
                    actualizarTabla();
                }
            });
        }

        $(`#${buscadorId}`).on('keyup', function () {
            paginaActual = 1;
            actualizarTabla();
        });

        // Init
        if ($(`#${tablaId} tbody tr`).length > 0 && !$(`#${tablaId} tbody tr`).hasClass('empty-row')) {
            actualizarTabla();
        }
    }

    $(document).ready(function () {
        // Validaciones de fechas
        const inputDesde = document.getElementById('fecha_desde');
        const inputHasta = document.getElementById('fecha_hasta');
        if (inputDesde && inputHasta) {
            inputDesde.addEventListener('change', function () {
                if (this.value && inputHasta.value && this.value > inputHasta.value) inputHasta.value = this.value;
            });
            inputHasta.addEventListener('change', function () {
                if (this.value && inputDesde.value && this.value < inputDesde.value) inputDesde.value = this.value;
            });
        }

        // Init tres tablas
        initPaginacion('tablaNivel1', 'buscadorNivel1', 'infoPaginacionNivel1', 'paginacionNivel1');
        initPaginacion('tablaNivel2', 'buscadorNivel2', 'infoPaginacionNivel2', 'paginacionNivel2');
        initPaginacion('tablaNivel3', 'buscadorNivel3', 'infoPaginacionNivel3', 'paginacionNivel3');
    });

    // --- Funciones para Gestión de Opciones JSON ---
    function cargarOpcionesJSON() {
        $.ajax({
            url: 'admin_opciones.php',
            data: { action: 'read' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Limpiar listas
                    $('#listaFallasN1, #listaFallasN2, #listaFallasN3').empty();
                    
                    // Nivel 1
                    (response.data['NIVEL 1'] || []).forEach(op => {
                        $('#listaFallasN1').append(renderOptionRow('NIVEL 1', op));
                    });
                    
                    // Nivel 2
                    (response.data['NIVEL 2'] || []).forEach(op => {
                        $('#listaFallasN2').append(renderOptionRow('NIVEL 2', op));
                    });
                    
                    // Nivel 3
                    (response.data['NIVEL 3'] || []).forEach(op => {
                        $('#listaFallasN3').append(renderOptionRow('NIVEL 3', op));
                    });
                }
            }
        });
    }

    function renderOptionRow(level, op) {
        return `
            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                <span class="fw-medium small me-3">${op}</span>
                <div class="d-flex align-items-center text-nowrap">
                    <button class="btn btn-sm btn-outline-primary border-0 p-1 me-1" onclick="editarOpcion('${level}', '${op}')">
                        <i class="fa-solid fa-pen fa-sm"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger border-0 p-1" onclick="eliminarOpcion('${level}', '${op}')">
                        <i class="fa-solid fa-trash-can fa-sm"></i>
                    </button>
                </div>
            </li>
        `;
    }

    function agregarOpcion(level) {
        let inputId = '';
        if (level === 'NIVEL 1') inputId = '#nuevoTipoFallaN1';
        else if (level === 'NIVEL 2') inputId = '#nuevoTipoFallaN2';
        else if (level === 'NIVEL 3') inputId = '#nuevoTipoFallaN3';

        const valor = $(inputId).val().trim();
        if (!valor) return;

        $.post('admin_opciones.php', { action: 'add', level: level, value: valor }, function (response) {
            if (response.success) {
                $(inputId).val('');
                cargarOpcionesJSON();
                Swal.fire({
                    icon: 'success', title: 'Agregado', text: 'Opción añadida a ' + level,
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 2000
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json');
    }

    function eliminarOpcion(level, valor) {
        Swal.fire({
            title: '¿Eliminar opción?',
            text: `Se eliminará "${valor}" de ${level}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('admin_opciones.php', { action: 'delete', level: level, value: valor }, function (response) {
                    if (response.success) {
                        cargarOpcionesJSON();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    function editarOpcion(level, oldValor) {
        Swal.fire({
            title: 'Editar opción',
            input: 'text',
            inputValue: oldValor,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            target: document.getElementById('configModal'), // Fix modal focus conflict
            customClass: {
                container: 'swal2-modal-container'
            },
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'El valor no puede estar vacío'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('admin_opciones.php', { 
                    action: 'edit', 
                    level: level, 
                    old_value: oldValor,
                    new_value: result.value.trim()
                }, function (response) {
                    if (response.success) {
                        cargarOpcionesJSON();
                        Swal.fire({
                            icon: 'success', title: 'Actualizado', text: 'Opción editada correctamente',
                            toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
                            target: document.getElementById('configModal')
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            target: document.getElementById('configModal')
                        });
                    }
                }, 'json');
            }
        });
    }

    // Cargar al abrir el modal
    $('#configModal').on('show.bs.modal', function () {
        cargarOpcionesJSON();
    });

    // Poner el foco al abrir
    $('#modalNuevoSoporte').on('shown.bs.modal', function () {
        $('#id_contrato').focus();
    });

    // =========================================================
    // --- Lógica de filas dinámicas OLT / PON en Edición ---
    // =========================================================

    window.addOltRowEdit = function(oltId = '', ponId = '') {
        const container = $('#olts-edit-container');
        const index = container.find('.olt-row-edit').length;
        
        const newRow = $(`
            <div class="olt-row-edit row g-2 mb-2 align-items-start" data-index="${index}">
                <div class="col-md-5">
                    <select class="form-select form-select-sm olt-select-edit" name="olts[]" required>
                        <option value="">Seleccione OLT...</option>
                        <?php foreach ($olts as $olt): ?>
                            <option value="<?php echo $olt['id_olt']; ?>"><?php echo htmlspecialchars($olt['nombre_olt']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <select class="form-select form-select-sm pon-select-edit" name="pons[]">
                        <option value="">Cargando...</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100 btn-remove-olt-edit" title="Eliminar fila">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        `);

        container.append(newRow);

        const oltSelect = newRow.find('.olt-select-edit');
        const ponSelect = newRow.find('.pon-select-edit');

        if (oltId) {
            oltSelect.val(oltId);
            cargarPonsEdit(oltSelect, ponId);
        } else {
            ponSelect.html('<option value="">Primero seleccione OLT...</option>');
        }
    };

    function cargarPonsEdit(oltSelect, selectedPonId = '') {
        const id_olt = $(oltSelect).val();
        const ponSelect = $(oltSelect).closest('.olt-row-edit').find('.pon-select-edit');

        if (!id_olt) {
            ponSelect.html('<option value="">Primero seleccione OLT...</option>');
            return;
        }

        ponSelect.html('<option value="">Cargando...</option>');

        $.ajax({
            url: 'get_pons_ajax.php',
            data: { id_olt: id_olt },
            dataType: 'json',
            success: function(data) {
                ponSelect.empty().append('<option value="">Toda la OLT (sin PON específico)</option>');
                data.forEach(function(pon) {
                    const selected = (selectedPonId && selectedPonId == pon.id_pon) ? 'selected' : '';
                    ponSelect.append(`<option value="${pon.id_pon}" ${selected}>${pon.nombre_pon}</option>`);
                });
            },
            error: function() {
                ponSelect.html('<option value="">Error</option>');
            }
        });
    }

    // Eventos para el contenedor de edición
    $('#olts-edit-container').on('change', '.olt-select-edit', function() {
        cargarPonsEdit(this);
    });

    $('#olts-edit-container').on('click', '.btn-remove-olt-edit', function() {
        if ($('#olts-edit-container .olt-row-edit').length > 1) {
            $(this).closest('.olt-row-edit').remove();
        } else {
            // Si es la última, solo limpiar los valores
            $(this).closest('.olt-row-edit').find('select').val('');
        }
    });

    $('#btn-add-olt-edit').click(function() {
        addOltRowEdit();
    });

    // --- Lógica similar para el modal de Críticas ---
    window.addOltRowEditCritica = function(oltId = '', ponId = '') {
        const container = $('#olts-critica-edit-container');
        const index = container.find('.olt-row-critica-edit').length;
        
        const newRow = $(`
            <div class="olt-row-critica-edit row g-2 mb-2 align-items-start" data-index="${index}">
                <div class="col-md-5">
                    <select class="form-select form-select-sm olt-select-critica-edit" name="olts[]" required>
                        <option value="">Seleccione OLT...</option>
                        <?php foreach ($olts as $olt): ?>
                            <option value="<?php echo $olt['id_olt']; ?>"><?php echo htmlspecialchars($olt['nombre_olt']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <select class="form-select form-select-sm pon-select-critica-edit" name="pons[]">
                        <option value="">Cargando...</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100 btn-remove-olt-critica-edit" title="Eliminar fila">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>
        `);

        container.append(newRow);

        const oltSelect = newRow.find('.olt-select-critica-edit');
        const ponSelect = newRow.find('.pon-select-critica-edit');

        if (oltId) {
            oltSelect.val(oltId);
            cargarPonsEditCritica(oltSelect, ponId);
        } else {
            ponSelect.html('<option value="">Primero seleccione OLT...</option>');
        }
    };

    function cargarPonsEditCritica(oltSelect, selectedPonId = '') {
        const id_olt = $(oltSelect).val();
        const ponSelect = $(oltSelect).closest('.olt-row-critica-edit').find('.pon-select-critica-edit');

        if (!id_olt) {
            ponSelect.html('<option value="">Primero seleccione OLT...</option>');
            return;
        }

        ponSelect.html('<option value="">Cargando...</option>');

        $.ajax({
            url: 'get_pons_ajax.php',
            data: { id_olt: id_olt },
            dataType: 'json',
            success: function(data) {
                ponSelect.empty().append('<option value="">Toda la OLT (sin PON específico)</option>');
                data.forEach(function(pon) {
                    const selected = (selectedPonId && selectedPonId == pon.id_pon) ? 'selected' : '';
                    ponSelect.append(`<option value="${pon.id_pon}" ${selected}>${pon.nombre_pon}</option>`);
                });
            },
            error: function() {
                ponSelect.html('<option value="">Error</option>');
            }
        });
    }

    $('#olts-critica-edit-container').on('change', '.olt-select-critica-edit', function() {
        cargarPonsEditCritica(this);
    });

    $('#olts-critica-edit-container').on('click', '.btn-remove-olt-critica-edit', function() {
        if ($('#olts-critica-edit-container .olt-row-critica-edit').length > 1) {
            $(this).closest('.olt-row-critica-edit').remove();
        } else {
            $(this).closest('.olt-row-critica-edit').find('select').val('');
        }
    });

    $('#btn-add-olt-critica-edit').click(function() {
        addOltRowEditCritica();
    });

    // =========================================================
    // --- Ver Detalles Crítica ---
    function verDetallesCritica(id) {
        $('#criticaDetalle_id').text(id);
        $('#criticaDetalleBody').html('<div class="text-center py-5"><span class="spinner-border text-danger"></span></div>');
        $('#btnCriticaEditar').attr('onclick', `editarCriticaFromDetalle(${id})`);
        new bootstrap.Modal(document.getElementById('modalCriticaDetalles')).show();

        fetch('get_soporte_detalle.php?id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.error) { $('#criticaDetalleBody').html(`<div class="alert alert-danger">${d.error}</div>`); return; }
                const f = v => v || '—';
                // Generar lista de OLTs/PONs
                let infraHtml = '';
                if (d.olts_afectadas && d.olts_afectadas.length > 0) {
                    d.olts_afectadas.forEach(p => {
                        infraHtml += `<p class="mb-1"><strong>OLT:</strong> <span class="badge bg-dark">${f(p.nombre_olt)}</span> 
                                      <strong>PON:</strong> <span class="badge bg-secondary">${f(p.nombre_pon)}</span></p>`;
                    });
                } else {
                    infraHtml = `<p class="mb-1"><strong>OLT:</strong> <span class="badge bg-dark">${f(d.nombre_olt)}</span></p>
                                 <p class="mb-1"><strong>PON:</strong> <span class="badge bg-secondary">${f(d.nombre_pon)}</span></p>`;
                }

                const html = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-primary text-white"><i class="fa-solid fa-user me-2"></i>Cliente de Referencia</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Nombre:</strong> ${f(d.nombre_completo)}</p>
                                <p class="mb-0"><strong>Cédula:</strong> ${f(d.cedula)}</p>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-dark text-white"><i class="fa-solid fa-network-wired me-2"></i>Infraestructura</div>
                            <div class="card-body">
                                ${infraHtml}
                                <p class="mb-0"><strong>Clientes Afectados:</strong> <span class="badge bg-danger fs-6">${f(d.clientes_afectados)}</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-danger text-white"><i class="fa-solid fa-fire me-2"></i>Datos del Incidente</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Fecha:</strong> ${f(d.fecha_soporte_form)}</p>
                                <p class="mb-1"><strong>Tipo de Falla:</strong> ${f(d.tipo_falla)}</p>
                                <p class="mb-1"><strong>Zona Afectada:</strong> ${f(d.zona_afectada || d.sector)}</p>
                                <p class="mb-1"><strong>Técnico:</strong> ${f(d.tecnico_asignado)}</p>
                                <p class="mb-0"><strong>Estado:</strong> ${d.solucion_completada == 1 ? '<span class="badge bg-success">Solucionada</span>' : '<span class="badge bg-warning text-dark">Activa</span>'}</p>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-warning"><i class="fa-solid fa-clipboard me-2"></i>Descripción</div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Observaciones:</strong> ${f(d.observaciones)}</p>
                                <p class="mb-0"><strong>Notas Internas:</strong> ${f(d.notas_internas)}</p>
                            </div>
                        </div>
                    </div>
                </div>`;
                $('#criticaDetalleBody').html(html);
            })
            .catch(() => $('#criticaDetalleBody').html('<div class="alert alert-danger">Error al cargar datos.</div>'));
    }

    function editarCriticaFromDetalle(id) {
        bootstrap.Modal.getInstance(document.getElementById('modalCriticaDetalles'))?.hide();
        setTimeout(() => editarCritica(id), 300);
    }

    // --- Editar Crítica ---
    function editarCritica(id) {
        $('#criticaEdit_id').text(id);
        $('#critica_edit_id_soporte').val(id);
        
        // Limpiar contenedor de OLTs
        $('#olts-critica-edit-container').empty();

        new bootstrap.Modal(document.getElementById('modalCriticaEditar')).show();

        fetch('get_soporte_detalle.php?id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.error) { alert('Error: ' + d.error); return; }
                $('#critica_tipo_falla').val(d.tipo_falla || '');
                $('#critica_clientes').val(d.clientes_afectados || 50);
                $('#critica_fecha').val(d.fecha_soporte_form || '');
                $('#critica_zona').val(d.zona_afectada || d.sector || '');
                $('#critica_tecnico').val(d.tecnico_asignado || '');
                $('#critica_observaciones').val(d.observaciones || '');
                $('#critica_notas').val(d.notas_internas || d.descrip_falla || '');
                $('#critica_solucionada').prop('checked', d.solucion_completada == 1);

                // Cargar múltiples OLTs
                if (d.olts_afectadas && d.olts_afectadas.length > 0) {
                    d.olts_afectadas.forEach(p => {
                        addOltRowEditCritica(p.id_olt, p.id_pon);
                    });
                } else if (d.id_olt) {
                    // Fallback para fallas viejas que solo tienen una OLT en la tabla principal
                    addOltRowEditCritica(d.id_olt, d.id_pon);
                } else {
                    addOltRowEditCritica();
                }
            })
            .catch(() => alert('Error al cargar datos.'));
    }

    function guardarEdicionCritica() {
        const id = $('#critica_edit_id_soporte').val();
        const btn = document.getElementById('btnGuardarCriticaEdit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Guardando...';

        // Recolectar OLTs y PONs
        const olts = [];
        const pons = [];
        $('#olts-critica-edit-container .olt-row-critica-edit').each(function() {
            const o = $(this).find('.olt-select-critica-edit').val();
            const p = $(this).find('.pon-select-critica-edit').val();
            if (o) {
                olts.push(o);
                pons.push(p);
            }
        });

        $.ajax({
            url: 'actualizar_soporte.php',
            method: 'POST',
            data: {
                id_soporte_edit: id,
                origen: 'gestion_fallas',
                prioridad_edit: 'NIVEL 3',
                tipo_falla_edit: $('#critica_tipo_falla').val(),
                clientes_afectados_edit: $('#critica_clientes').val(),
                fecha_edit: $('#critica_fecha').val(),
                zona_afectada_edit: $('#critica_zona').val(),
                tecnico_edit: $('#critica_tecnico').val(),
                descripcion_edit: $('#critica_observaciones').val(),
                notas_internas_edit: $('#critica_notas').val(),
                solucion_completada: $('#critica_solucionada').is(':checked') ? 1 : 0,
                olts: olts,
                pons: pons,
                es_caida_critica_edit: 1,
                monto_total_edit: 0
            },
            success: function () {
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: 'Caída crítica actualizada.', timer: 1800, showConfirmButton: false })
                    .then(() => location.reload());
            },
            error: function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save me-1"></i>Guardar Cambios';
                Swal.fire('Error', 'No se pudo guardar.', 'error');
            }
        });
    }

    // --- Toggle Estado ---
    function toggleEstado(id, nuevoEstado, prioridad = 'NIVEL 3') {
        const texto = nuevoEstado === 1 ? 'marcar como Solucionada' : 'marcar como Activa';
        const msg = prioridad === 'NIVEL 3' ? 'esta caída crítica' : 'este reporte';
        
        Swal.fire({
            title: '¿Cambiar Estado?',
            text: `¿Deseas ${texto} ${msg}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'actualizar_soporte.php',
                    method: 'POST',
                    data: {
                        id_soporte_edit: id,
                        origen: 'toggle_estado',
                        solucion_completada: nuevoEstado,
                        prioridad_edit: prioridad,
                        monto_total_edit: 0,
                        fecha_edit: new Date().toISOString().split('T')[0],
                        tecnico_edit: '',
                        descripcion_edit: '',
                        es_caida_critica_edit: (prioridad === 'NIVEL 3' ? 1 : 0)
                    },
                    success: function () {
                        // Actualizar UI sin recargar la página
                        const btn = document.getElementById('btn-toggle-' + id);
                        const badge = document.getElementById('badge-estado-' + id);
                        const isNivel3 = (prioridad === 'NIVEL 3');

                        if (nuevoEstado === 1) {
                            // Cambió a Solucionada
                            if (badge) badge.innerHTML = '<span class="badge bg-success">Solucionada</span>';
                            if (btn) {
                                btn.className = 'btn btn-sm btn-outline-secondary shadow-sm';
                                btn.innerHTML = '<i class="fa-solid fa-rotate-left me-1"></i>' + (isNivel3 ? 'Marcar Activa' : 'Reactivar');
                                btn.setAttribute('onclick', `toggleEstado(${id}, 0, "${prioridad}")`);
                            }
                        } else {
                            // Cambió a Activa
                            if (badge) badge.innerHTML = '<span class="badge bg-warning text-dark">Activa</span>';
                            if (btn) {
                                btn.className = 'btn btn-sm btn-success shadow-sm';
                                btn.innerHTML = '<i class="fa-solid fa-check-double me-1"></i>' + (isNivel3 ? 'Solucionada' : 'Solucionar');
                                btn.setAttribute('onclick', `toggleEstado(${id}, 1, "${prioridad}")`);
                            }
                        }
                        
                        Swal.fire({ icon: 'success', title: 'Estado actualizado', timer: 1500, showConfirmButton: false });
                    },
                    error: function () {
                        Swal.fire('Error', 'No se pudo actualizar el estado.', 'error');
                    }
                });
            }
        });
    }

    // --- Exportar PDF Críticas ---
    function exportarPDFCriticas() {
        window.open('generar_pdf_consolidado.php?solo_nivel_3=1', '_blank');
    }

    // --- Eliminar Reporte ---
    function eliminarSoporte(id) {
        Swal.fire({
            title: '¿Eliminar Reporte?',
            text: "Esta acción eliminará el soporte y cualquier deuda asociada de forma permanente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'eliminar_soporte.php',
                    method: 'POST',
                    data: { id_soporte_eliminar: id, json: 1 },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                // Recargar la página para actualizar tablas y estadísticas
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'No se pudo procesar la solicitud de eliminación.', 'error');
                    }
                });
            }
        });
    }

</script>

<?php require_once $path_to_root . 'paginas/includes/layout_foot.php'; ?>