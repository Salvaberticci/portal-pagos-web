<?php
/**
 * Gestión de Clientes Deudores
 */
require_once '../conexion.php';

$path_to_root = "../../";
$page_title = "Clientes Deudores";
$breadcrumb = ["Cobranzas"];
$back_url = "../menu.php";

// Obtener Bancos desde JSON (Consistencia con otros módulos)
$bancos_json_path = 'bancos.json';
$bancos_data = [];
if (file_exists($bancos_json_path)) {
    $bancos_data = json_decode(file_get_contents($bancos_json_path), true) ?: [];
    // Ordenar alfabéticamente por nombre de banco
    usort($bancos_data, function($a, $b) {
        return strcmp($a['nombre_banco'], $b['nombre_banco']);
    });
}

require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';
?>

<link href="<?php echo $path_to_root; ?>css/datatables.min.css" rel="stylesheet">

<style>
    /* Estilos personalizados para las pestañas de Deudores */
    #deudorTabs .nav-link {
        transition: all 0.3s ease;
        border: 1px solid transparent;
        color: var(--text-main);
        opacity: 0.7;
    }

    #deudorTabs .nav-link:hover {
        background: rgba(128, 128, 128, 0.1);
        color: var(--text-main);
        opacity: 1;
    }

    /* Pestaña Deudas (Roja al estar activa) */
    #tab-deudas.active {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        color: white !important;
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    /* Pestaña Créditos (Verde al estar activa) */
    #tab-creditos.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        color: white !important;
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }
    
    #tabla_deudores thead th, #tabla_creditos thead th {
        background-color: transparent !important;
        color: var(--text-main, #333) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    /* Mejora de espaciado para DataTables */
    .dataTables_wrapper {
        padding-top: 1rem;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 1.5rem;
        padding: 0 1rem;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 1.5rem;
        padding: 0 1rem;
    }
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">
        <div class="glass-panel animate-fade mb-4">
            <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom border-white border-opacity-10 py-4 px-4">
                <div>
                    <h4 class="fw-bold text-gradient mb-1">Clientes Deudores</h4>
                    <p class="text-muted small mb-0">Listado de clientes con saldos pendientes</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-premium px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalCrearDeuda">
                        <i class="fas fa-plus-circle me-1"></i> Crear Nueva Deuda
                    </button>
                </div>
            </div>

            <div class="card-body px-4 pt-4">
                <!-- TABS DE NAVEGACIÓN -->
                <ul class="nav nav-pills mb-4 bg-transparent p-2 rounded-4 border border-white border-opacity-10" id="deudorTabs" role="tablist">
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link active w-100 fw-bold py-2 rounded-3" id="tab-deudas" data-bs-toggle="pill" data-bs-target="#pane-deudas" type="button" role="tab" aria-selected="true">
                            <i class="fas fa-hand-holding-dollar me-2"></i>Deudas Pendientes
                        </button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link w-100 fw-bold py-2 rounded-3" id="tab-creditos" data-bs-toggle="pill" data-bs-target="#pane-creditos" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-coins me-2"></i>Saldos a Favor (Créditos)
                        </button>
                    </li>
                </ul>
                <!-- Filtros y Ordenamiento -->
                <div class="row g-3 mb-4 bg-transparent p-4 rounded-4 border border-white border-opacity-10 mx-0 shadow-lg">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-main opacity-75 mb-1"><i class="fas fa-calendar-alt me-1 text-danger"></i> Desde (Registro)</label>
                        <input type="date" id="filter_date_start" class="form-control glass-input text-main">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-main opacity-75 mb-1"><i class="fas fa-calendar-alt me-1 text-danger"></i> Hasta (Registro)</label>
                        <input type="date" id="filter_date_end" class="form-control glass-input text-main">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-main opacity-75 mb-1"><i class="fas fa-dollar-sign me-1 text-success"></i> Mín. Saldo</label>
                        <input type="number" id="filter_balance_min" class="form-control glass-input text-main" placeholder="0.00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-main opacity-75 mb-1"><i class="fas fa-dollar-sign me-1 text-success"></i> Máx. Saldo</label>
                        <input type="number" id="filter_balance_max" class="form-control glass-input text-main" placeholder="9999.99">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-main opacity-75 mb-1"><i class="fas fa-sort me-1 text-primary"></i> Orden Rápido</label>
                        <select id="filter_order" class="form-select glass-input fw-bold text-main">
                            <option value="7-desc">Más Recientes</option>
                            <option value="7-asc">Más Antiguos</option>
                            <option value="6-desc">Mayor Saldo</option>
                            <option value="6-asc">Menor Saldo</option>
                        </select>
                    </div>
                    <div class="col-12 text-end mt-3">
                        <button type="button" id="btn_reset_filters" class="btn btn-glass btn-sm px-3">
                            <i class="fas fa-undo me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>

                <div class="tab-content" id="deudorTabsContent">
                    <!-- PANEL 1: DEUDAS -->
                    <div class="tab-pane fade show active" id="pane-deudas" role="tabpanel">
                        <div class="table-responsive">
                            <table class="display table table-hover w-100 text-main" id="tabla_deudores">
                                <thead class="bg-transparent border-bottom border-white border-opacity-10 text-main">
                                    <tr>
                                        <th class="text-center">ID</th>
                                        <th class="text-center">Cliente</th>
                                        <th class="text-center">Cédula</th>
                                        <th class="text-center">IP</th>
                                        <th class="text-center">Monto Total</th>
                                        <th class="text-center">Monto Pagado</th>
                                        <th class="text-center">Saldo Pendiente</th>
                                        <th class="text-center">Fecha Registro</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center" width="15%">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT d.*, c.nombre_completo, c.cedula, c.ip_onu
                                            FROM clientes_deudores d
                                            INNER JOIN contratos c ON d.id_contrato = c.id
                                            WHERE d.estado = 'PENDIENTE' AND d.tipo_registro = 'DEUDA'
                                            ORDER BY d.fecha_registro DESC";
                                    $result = $conn->query($sql);
        
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>
                                            <td class='text-center text-main opacity-75'>{$row['id']}</td>
                                            <td class='fw-bold text-main text-center'>{$row['nombre_completo']}</td>
                                            <td class='text-center'><span class='badge bg-primary-light text-primary'>{$row['cedula']}</span></td>
                                            <td class='text-center'><code>{$row['ip_onu']}</code></td>
                                            <td class='text-center text-main'>\${$row['monto_total']}</td>
                                            <td class='text-center text-main opacity-75'>\${$row['monto_pagado']}</td>
                                            <td class='text-center text-danger fw-bold'>\${$row['saldo_pendiente']}</td>
                                            <td class='text-center text-main opacity-75' data-order='{$row['fecha_registro']}'>" . date('d/m/Y', strtotime($row['fecha_registro'])) . "</td>
                                            <td class='text-center'><span class='badge bg-danger-light text-danger'>PENDIENTE</span></td>
                                            <td>
                                                <div class='d-flex justify-content-center gap-1 flex-nowrap'>
                                                    <button class='btn btn-sm btn-glass text-success' onclick='marcarPagado({$row['id']})' title='Marcar como Pagado'>
                                                        <i class='fa-solid fa-check'></i> Pagado
                                                    </button>
                                                    <button class='btn btn-sm btn-glass text-info' onclick='abrirModalAbonos({$row['id']}, {$row['saldo_pendiente']})' title='Gestionar Abonos'>
                                                        <i class='fa-solid fa-coins'></i> Abonos
                                                    </button>
                                                    <button class='btn btn-sm btn-glass text-muted' onclick='eliminarDeuda({$row['id']})' title='Eliminar/Anular Deuda'>
                                                        <i class='fa-solid fa-trash'></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PANEL 2: CRÉDITOS -->
                    <div class="tab-pane fade" id="pane-creditos" role="tabpanel">
                        <div class="table-responsive">
                            <table class="display table table-hover w-100 text-main" id="tabla_creditos">
                                <thead class="bg-transparent border-bottom border-white border-opacity-10 text-main">
                                    <tr>
                                        <th class="text-center">ID</th>
                                        <th class="text-center">Cliente</th>
                                        <th class="text-center">Cédula</th>
                                        <th class="text-center">IP</th>
                                        <th class="text-center">Monto Inicial</th>
                                        <th class="text-center">Crédito Disponible</th>
                                        <th class="text-center">Fecha Generado</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center" width="15%">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql_c = "SELECT d.*, c.nombre_completo, c.cedula, c.ip_onu
                                            FROM clientes_deudores d
                                            INNER JOIN contratos c ON d.id_contrato = c.id
                                            WHERE d.estado = 'PENDIENTE' AND d.tipo_registro = 'CREDITO'
                                            ORDER BY d.fecha_registro DESC";
                                    $result_c = $conn->query($sql_c);
        
                                    while ($row_c = $result_c->fetch_assoc()) {
                                        echo "<tr>
                                            <td class='text-center text-main opacity-75'>{$row_c['id']}</td>
                                            <td class='fw-bold text-center text-main'>{$row_c['nombre_completo']}</td>
                                            <td class='text-center text-main opacity-75'>{$row_c['cedula']}</td>
                                            <td class='text-center'><code>{$row_c['ip_onu']}</code></td>
                                            <td class='text-center text-main'>\${$row_c['monto_pagado']}</td>
                                            <td class='text-center text-success fw-bold'>\${$row_c['saldo_pendiente']}</td>
                                            <td class='text-center text-main opacity-75' data-order='{$row_c['fecha_registro']}'>" . date('d/m/Y', strtotime($row_c['fecha_registro'])) . "</td>
                                            <td class='text-center'><span class='badge bg-success text-white'>A FAVOR</span></td>
                                            <td>
                                                <div class='d-flex justify-content-center gap-1 flex-nowrap'>
                                                    <button class='btn btn-sm btn-glass text-success' onclick='abrirModalUsarSaldo({$row_c['id']}, {$row_c['id_contrato']}, {$row_c['saldo_pendiente']}, \"{$row_c['nombre_completo']}\")' title='Usar este saldo para pagar deudas'>
                                                        <i class='fa-solid fa-hand-holding-dollar'></i> Usar Saldo
                                                    </button>
                                                    <button class='btn btn-sm btn-glass text-danger' onclick='eliminarSaldoFavor({$row_c['id']})' title='Eliminar/Anular Saldo a Favor'>
                                                        <i class='fa-solid fa-trash'></i> Eliminar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center mt-4">
                    <a href="../../paginas/menu.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-2"></i> Volver al Menú
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Ver Contrato Detalle -->
<div class="modal fade" id="modalVerContrato" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-panel border-0 shadow-lg">
            <div class="modal-header modal-header-gradient border-0 text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-contract me-2"></i>Detalle del Contrato #<span id="vc_id_contrato">---</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Banner Superior -->
                <div class="bg-transparent p-4 border-bottom border-white border-opacity-10">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3 class="fw-bold text-main mb-1" id="vc_nombre_completo">---</h3>
                            <span class="badge bg-primary px-3 py-2" id="vc_estado">ACTIVO</span>
                            <span class="ms-2 text-main opacity-75 small"><i class="fas fa-id-card me-1"></i><span id="vc_cedula">---</span></span>
                        </div>
                        <div class="text-end">
                            <label class="small text-main opacity-75 d-block fw-bold">Plan de Internet</label>
                            <h4 class="text-primary fw-bold mb-0" id="vc_plan">---</h4>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="row g-4">
                        <!-- Columna Contacto -->
                        <div class="col-md-6">
                            <div class="card bg-transparent border border-white border-opacity-10 h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 text-main border-bottom border-white border-opacity-10 pb-2"><i class="fas fa-user-circle me-2"></i>Contacto y Ubicación</h6>
                                    <div class="mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">Teléfono</small><span id="vc_telefono">---</span></div>
                                    <div class="mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">Correo</small><span id="vc_correo">---</span></div>
                                    <div class="mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">Dirección</small>
                                        <p class="small mb-0" id="vc_direccion_completa">---</p>
                                    </div>
                                    <div class="mt-2 small text-main opacity-75">
                                        <i class="fas fa-map-marker-alt me-1 text-danger"></i> <span id="vc_municipio">---</span>, <span id="vc_parroquia">---</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Conexión -->
                        <div class="col-md-6">
                            <div class="card bg-transparent border border-white border-opacity-10 h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 text-main border-bottom border-white border-opacity-10 pb-2"><i class="fas fa-network-wired me-2"></i>Información de Red</h6>
                                    <div class="row">
                                        <div class="col-6 mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">IP ONU</small><code id="vc_ip_onu">---</code></div>
                                        <div class="col-6 mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">IP Antena/Router</small><code id="vc_ip">---</code></div>
                                        <div class="col-12 mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">Fecha Instalación</small><span id="vc_fecha_instalacion">---</span></div>
                                        <div class="col-12 mb-2 text-main"><small class="text-main opacity-75 d-block small fw-bold uppercase">Vendedor</small><span id="vc_vendedor">---</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección Técnica (FTTH / RADIO) -->
                        <div class="col-12">
                            <div class="card bg-transparent border border-white border-opacity-10 shadow-sm" id="vc_tech_section">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3 text-main"><i class="fas fa-tools me-2"></i>Detalles Técnicos de <span id="vc_tipo_conexion">---</span></h6>
                                    
                                    <!-- Campos FTTH -->
                                    <div id="vc_tech_ftth" class="row g-3" style="display:none;">
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">OLT</small><span id="vc_olt">---</span></div>
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">PON</small><span id="vc_pon">---</span></div>
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">Caja NAP</small><span id="vc_caja">---</span></div>
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">Puerto</small><span id="vc_puerto">---</span></div>
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">MAC/Serial</small><code id="vc_mac">---</code></div>
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">RX Power</small><span id="vc_rx" class="badge bg-dark">---</span></div>
                                        <div class="col-md-3 text-main"><small class="text-main opacity-75 d-block fw-bold small">Distancia</small><span id="vc_distancia">---</span></div>
                                    </div>

                                    <!-- Campos RADIO -->
                                    <div id="vc_tech_radio" class="row g-3" style="display:none;">
                                        <div class="col-md-6 text-main"><small class="text-main opacity-75 d-block fw-bold small">Punto de Acceso</small><span id="vc_ap">---</span></div>
                                        <div class="col-md-6 text-main"><small class="text-main opacity-75 d-block fw-bold small">Señal (dBm)</small><span id="vc_signal" class="badge bg-dark">---</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-transparent border-top border-white border-opacity-10">
                <button type="button" class="btn btn-glass shadow-sm px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Gestionar Abonos -->
<div class="modal fade" id="modalGestionarAbonos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fas fa-coins me-2"></i>Gestionar Abonos - Deuda #<span id="abono_id_deuda"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-0">
                <!-- Info Banner -->
                <div class="bg-transparent p-3 border-bottom border-white border-opacity-10 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="small text-main opacity-75 fw-bold d-block text-uppercase">Saldo Pendiente Actual</span>
                        <h4 class="mb-0 text-danger fw-bold">$<span id="abono_saldo_pendiente_display">0.00</span></h4>
                    </div>
                </div>

                <!-- Tabs Navbar -->
                <ul class="nav nav-tabs px-3 pt-3 bg-transparent border-bottom border-white border-opacity-10" id="abonoTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-main" id="tab-registrar" data-bs-toggle="tab" data-bs-target="#pane-registrar" type="button" role="tab" aria-selected="true">
                            <i class="fas fa-plus-circle me-1"></i>Ingresar Abono
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-main opacity-75" id="tab-historial" data-bs-toggle="tab" data-bs-target="#pane-historial" type="button" role="tab" aria-selected="false">
                            <i class="fas fa-history me-1"></i>Historial de Pagos
                        </button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content" id="abonoTabsContent">
                    <!-- Tab 1: Registrar Abono -->
                    <div class="tab-pane fade show active p-4" id="pane-registrar" role="tabpanel">
                        <form id="formRegistrarAbono" enctype="multipart/form-data">
                            <input type="hidden" name="id_deudor" id="abono_input_id_deudor">
                            <input type="hidden" id="abono_input_max_monto">
                            
                            <div class="row g-3">
                                <!-- Columna Izquierda: Datos del Pago -->
                                <div class="col-md-7 border-end border-white border-opacity-10 pe-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-main opacity-75 small mb-1">Monto a Abonar ($)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-transparent border-white border-opacity-10 fw-bold text-success">$</span>
                                            <input type="text" class="form-control form-control-lg fw-bold text-success decimal-input glass-input" id="abono_monto" name="monto_abono" required placeholder="0.00" inputmode="decimal" autocomplete="off">
                                        </div>
                                        <div class="form-text small text-danger d-none" id="abono_error_monto">El monto excede el saldo pendiente.</div>
                                    </div>
                                    
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <label class="form-label fw-bold text-main opacity-75 small mb-1">Referencia</label>
                                            <input type="text" class="form-control form-control-sm glass-input" name="referencia" required autocomplete="off">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-bold text-main opacity-75 small mb-1">Banco Receptor</label>
                                            <select class="form-select form-select-sm glass-input text-main" name="id_banco" id="abono_select_banco" required>
                                                <option value="" class="text-dark">Seleccione...</option>
                                                <!-- Llenado vía JS/PHP -->
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-main opacity-75 small mb-1">Justificación (Opcional)</label>
                                        <input type="text" class="form-control form-control-sm glass-input" name="justificacion" placeholder="Ej: Transferencia parcial acordada...">
                                    </div>
                                </div>
                                
                                <!-- Columna Derecha: Capture -->
                                <div class="col-md-5 ps-3">
                                    <label class="form-label fw-bold text-main opacity-75 small mb-2 d-block">Comprobante (Opcional)</label>
                                    <div class="border border-white border-opacity-10 rounded p-2 text-center bg-transparent mb-2">
                                        <input type="file" class="form-control form-control-sm mb-2 glass-input" id="abono_capture" name="capture_abono" accept="image/*">
                                        <div class="rounded bg-transparent border border-white border-opacity-10" style="height: 150px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                            <img id="abono_preview_img" src="" class="img-fluid d-none" style="object-fit: contain; max-height: 100%;">
                                            <i class="fas fa-image fa-3x text-main opacity-25" id="abono_preview_icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="button" class="btn btn-glass me-2" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary fw-bold px-4" id="btn_submit_abono">
                                    <i class="fas fa-save me-1"></i>Registrar Abono
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 2: Historial -->
                    <div class="tab-pane fade p-3" id="pane-historial" role="tabpanel">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover text-main mb-0" style="font-size: 0.85rem;">
                                <thead class="bg-transparent border-bottom border-white border-opacity-10 text-main sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Ref / Banco</th>
                                        <th>Justificación</th>
                                        <th class="text-end">Monto ABONADO</th>
                                        <th class="text-center">Capture</th>
                                    </tr>
                                </thead>
                                <tbody id="abono_historial_body">
                                    <tr><td colspan="5" class="text-center text-main opacity-75 py-4">Cargando historial...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Usar Saldo a Favor -->
<div class="modal fade" id="modalUsarSaldoCredito" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fas fa-hand-holding-dollar me-2"></i>Aplicar Saldo a Favor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUsarSaldo">
                <input type="hidden" name="id_credito" id="usc_id_credito">
                
                <div class="modal-body p-4">
                    <div class="alert alert-success border-0 shadow-sm mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle fa-2x me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold" id="usc_cliente_nombre">---</h6>
                                <span class="small">Crédito Disponible: <strong class="fs-5">$<span id="usc_saldo_disponible">0.00</span></strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-main opacity-75 small"><i class="fas fa-list-ul me-1 text-primary"></i> 1. Seleccione la Deuda a amortizar</label>
                        <div id="usc_deudas_list" class="list-group shadow-sm" style="max-height: 200px; overflow-y: auto;">
                            <!-- Cargando vía JS -->
                            <div class="p-3 text-center text-main opacity-75">Cargando deudas...</div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold text-main opacity-75 small"><i class="fas fa-money-bill-wave me-1 text-success"></i> 2. Monto a aplicar ($)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-transparent border-white border-opacity-10 fw-bold text-success">$</span>
                            <input type="number" step="0.01" min="0.01" name="monto" id="usc_monto_aplicar" class="form-control fw-bold glass-input text-main" placeholder="0.00" required>
                        </div>
                        <div id="usc_error_monto" class="text-danger small mt-1 d-none">El monto supera el crédito o la deuda.</div>
                    </div>
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="btn_submit_usc">
                        <i class="fas fa-check-circle me-1"></i> Confirmar Aplicación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalCrearDeuda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-hand-holding-dollar me-2"></i>Registrar Nueva Deuda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCrearDeuda">
                <div class="modal-body p-4">
                    <!-- 1. Buscador de Contrato -->
                    <div class="mb-4 position-relative">
                        <label class="form-label fw-bold text-main opacity-75 small"><i class="fas fa-search me-1 text-danger"></i> 1. Buscar Cliente / Contrato</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-white border-opacity-10 border-end-0"><i class="fas fa-user text-main opacity-75"></i></span>
                            <input type="text" id="contrato_search_deuda" class="form-control border-start-0 glass-input text-main" placeholder="Nombre, Cédula o ID..." autocomplete="off">
                        </div>
                        <input type="hidden" name="id_contrato" id="id_contrato_hidden_deuda" required>
                        <div id="contrato_search_results_deuda" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1050; max-height: 200px; overflow-y: auto; background: var(--bg-card);"></div>
                    </div>

                    <!-- 2. Monto de la Deuda -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-main opacity-75 small"><i class="fas fa-money-bill-wave me-1 text-success"></i> 2. Monto de la Deuda ($)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-transparent border-white border-opacity-10 fw-bold text-success">$</span>
                            <input type="number" step="0.01" min="0.01" name="monto" class="form-control fw-bold glass-input text-main" placeholder="0.00" required>
                        </div>
                    </div>

                    <!-- 3. Notas / Justificación -->
                    <div class="mb-2">
                        <label class="form-label fw-bold text-main opacity-75 small"><i class="fas fa-comment-alt me-1 text-primary"></i> 3. Notas / Justificación</label>
                        <textarea name="notas" class="form-control glass-input text-main" rows="3" placeholder="Ej: Saldo pendiente por reposición de equipo ONU..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="btn_submit_deuda">
                        <i class="fas fa-save me-1"></i> Guardar Deuda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
        const table = $('#tabla_deudores').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_",
                "zeroRecords": "No hay deudores registrados",
                "info": "_START_ - _END_ de _TOTAL_",
                "search": "Buscar:",
                "paginate": { "next": ">", "previous": "<" }
            },
            "order": [[7, "desc"]], 
            "columnDefs": [
                { "type": "num-fmt", "targets": 6 }
            ]
        });

        const tableCreditos = $('#tabla_creditos').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_",
                "zeroRecords": "No hay créditos registrados",
                "info": "_START_ - _END_ de _TOTAL_",
                "search": "Buscar:",
                "paginate": { "next": ">", "previous": "<" }
            },
            "order": [[6, "desc"]], 
            "columnDefs": [
                { "type": "num-fmt", "targets": 5 }
            ]
        });

        // Lógica de Filtros Personalizados
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            // 1. Filtrado por Fecha (Columna 7)
            const minDate = $('#filter_date_start').val();
            const maxDate = $('#filter_date_end').val();
            const rowDate = data[7] || ''; // Formato d/m/Y (desde el echo de PHP)
            
            if (minDate || maxDate) {
                const parts = rowDate.split('/');
                if (parts.length === 3) {
                    const rowDateIso = `${parts[2]}-${parts[1]}-${parts[0]}`; // YYYY-MM-DD
                    if (minDate && rowDateIso < minDate) return false;
                    if (maxDate && rowDateIso > maxDate) return false;
                }
            }

            // 2. Filtrado por Saldo (Columna 6)
            const minBal = parseFloat($('#filter_balance_min').val());
            const maxBal = parseFloat($('#filter_balance_max').val());
            const rowBalRaw = data[6].replace(/[^\d.]/g, ''); // Limpiar símbolos y comas
            const rowBal = parseFloat(rowBalRaw);

            if (!isNaN(minBal) && rowBal < minBal) return false;
            if (!isNaN(maxBal) && rowBal > maxBal) return false;

            return true;
        });

        // Listeners para los inputs de filtro
        $('#filter_date_start, #filter_date_end, #filter_balance_min, #filter_balance_max').on('change keyup', function() {
            table.draw();
        });

        // Listener para el Orden Rápido
        $('#filter_order').on('change', function() {
            const val = $(this).val();
            const parts = val.split('-');
            const col = parseInt(parts[0]);
            const dir = parts[1];
            table.order([col, dir]).draw();
        });

        // Botón Reiniciar
        $('#btn_reset_filters').on('click', function() {
            $('#filter_date_start, #filter_date_end, #filter_balance_min, #filter_balance_max').val('');
            $('#filter_order').val('7-desc');
            table.order([7, 'desc']).draw();
            table.draw();
        });

        // Declarar bancosInfo globalmente para que sea accesible desde marcarPagado()
        window.bancosInfo = <?php echo json_encode($bancos_data); ?>;
        
        const selectBanco = document.getElementById('abono_select_banco');
        if (window.bancosInfo && window.bancosInfo.length > 0) {
            window.bancosInfo.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id_banco;
                opt.textContent = b.nombre_banco;
                selectBanco.appendChild(opt);
            });
        }

        // --- LÓGICA USAR SALDO A FAVOR ---
        window.abrirModalUsarSaldo = function(idCredito, idContrato, saldo, nombre) {
            document.getElementById('usc_id_credito').value = idCredito;
            document.getElementById('usc_cliente_nombre').textContent = nombre;
            document.getElementById('usc_saldo_disponible').textContent = saldo.toFixed(2);
            document.getElementById('usc_monto_aplicar').value = "";
            document.getElementById('usc_monto_aplicar').max = saldo;
            
            const list = document.getElementById('usc_deudas_list');
            list.innerHTML = '<div class="p-4 text-center"><div class="spinner-border spinner-border-sm text-primary"></div><br><small>Buscando deudas...</small></div>';
            
            fetch(`get_contract_balance.php?id=${idContrato}&action=deuda`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        const deudasPendientes = res.data.filter(d => d.estado === 'PENDIENTE');
                        if (deudasPendientes.length === 0) {
                            list.innerHTML = '<div class="alert alert-warning m-2 small">No hay deudas PENDIENTES vinculadas a este cliente.</div>';
                            return;
                        }
                        
                        list.innerHTML = '';
                        deudasPendientes.forEach(d => {
                            const item = document.createElement('label');
                            item.className = 'list-group-item list-group-item-action bg-transparent text-main border-white border-opacity-10 d-flex justify-content-between align-items-center cursor-pointer';
                            item.innerHTML = `
                                <div>
                                    <input class="form-check-input me-2" type="radio" name="id_deuda" value="${d.id}" data-saldo="${d.saldo_pendiente}" required>
                                    <span class="small fw-bold text-main">ID #${d.id}</span> 
                                    <span class="text-main opacity-75 ms-1">(${d.fecha_registro.split(' ')[0]})</span>
                                </div>
                                <span class="badge bg-danger">Faltan $${d.saldo_pendiente}</span>
                            `;
                            list.appendChild(item);
                        });
                        
                        // Auto-ajustar monto al seleccionar
                        list.querySelectorAll('input[name="id_deuda"]').forEach(radio => {
                            radio.addEventListener('change', function() {
                                const saldoDeuda = parseFloat(this.dataset.saldo);
                                const saldoCredito = parseFloat(document.getElementById('usc_saldo_disponible').textContent);
                                document.getElementById('usc_monto_aplicar').value = Math.min(saldoDeuda, saldoCredito).toFixed(2);
                            });
                        });
                        
                    } else {
                        list.innerHTML = '<div class="alert alert-warning m-2 small">No se encontraron deudas para este contrato.</div>';
                    }
                });
            
            new bootstrap.Modal(document.getElementById('modalUsarSaldoCredito')).show();
        };

        document.getElementById('formUsarSaldo').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn_submit_usc');
            const data = new FormData(this);
            
            const montoAplicar = parseFloat(data.get('monto'));
            const saldoDisponible = parseFloat(document.getElementById('usc_saldo_disponible').textContent);
            
            const deudaRadio = document.querySelector('input[name="id_deuda"]:checked');
            if (!deudaRadio) {
                Swal.fire('Error', 'Debe seleccionar una deuda de la lista.', 'warning');
                return;
            }
            
            const saldoDeuda = parseFloat(deudaRadio.dataset.saldo);
            
            if (montoAplicar > saldoDisponible || montoAplicar > saldoDeuda) {
                Swal.fire('Atención', 'El monto supera el crédito disponible o el saldo de la deuda.', 'warning');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';

            fetch('procesar_uso_credito.php', {
                method: 'POST',
                body: data
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('¡Éxito!', res.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Confirmar Aplicación';
                }
            });
        });
    });

    async function solicitarClaveAdmin(titulo = 'Confirmar Acción') {
        const focusHandler = (e) => {
            if (e.target.closest(".swal2-container")) {
                e.stopImmediatePropagation();
            }
        };
        document.addEventListener('focusin', focusHandler, true);

        const { value: password } = await Swal.fire({
            title: titulo,
            input: 'password',
            inputLabel: 'Ingrese su clave de usuario para proceder',
            inputPlaceholder: 'Contraseña',
            inputAttributes: {
                autocapitalize: 'off',
                autocorrect: 'off'
            },
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            didClose: () => {
                document.removeEventListener('focusin', focusHandler, true);
            }
        });

        if (password) {
            const resp = await fetch('verificar_clave.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'clave=' + encodeURIComponent(password)
            });
            const data = await resp.json();
            if (data.success) {
                return true;
            }
            Swal.fire('Error', 'Clave incorrecta', 'error');
        }
        return false;
    }

    async function marcarPagado(id) {
        const proceeds = await solicitarClaveAdmin('Confirmar Pago');
        if (!proceeds) return;

        // Generar opciones de bancos para el select
        let bancoOptions = '<option value="">Seleccione Banco...</option>';
        if (typeof bancosInfo !== 'undefined') {
            bancosInfo.forEach(b => {
                bancoOptions += `<option value="${b.id_banco}">${b.nombre_banco}</option>`;
            });
        }

        const { value: formValues } = await Swal.fire({
            title: 'Datos del Pago',
            html:
                '<div class="text-start mb-3">' +
                '  <label class="form-label small fw-bold">Banco Receptor</label>' +
                '  <select id="swal-banco" class="form-select">' + bancoOptions + '</select>' +
                '</div>' +
                '<div class="text-start">' +
                '  <label class="form-label small fw-bold">Referencia de Pago</label>' +
                '  <input id="swal-ref" class="form-control" placeholder="Ej: 123456">' +
                '</div>',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Confirmar Pago',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const idBanco = document.getElementById('swal-banco').value;
                const ref = document.getElementById('swal-ref').value;
                if (!idBanco) {
                    Swal.showValidationMessage('Debe seleccionar un banco');
                    return false;
                }
                return { id_banco: idBanco, referencia: ref };
            }
        });

        if (formValues) {
            // Mostrar estado de carga
            Swal.fire({
                title: 'Procesando...',
                text: 'Sincronizando pago con mensualidades',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.post('marcar_pagado.php', { 
                id: id, 
                id_banco: formValues.id_banco, 
                referencia: formValues.referencia 
            }, function (resp) {
                if (resp.trim() === 'OK') {
                    Swal.fire({
                        title: '¡Saldado!',
                        text: 'La deuda ha sido liquidada y el pago registrado en mensualidades.',
                        icon: 'success',
                        confirmButtonColor: '#0d6efd',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo actualizar el registro: ' + resp,
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            }).fail(function() {
                Swal.fire({
                    title: 'Error de Red',
                    text: 'No se pudo comunicar con el servidor.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            });
        }
    }

    /* =========================================
       LÓGICA DE ABONOS (PAGOS PARCIALES)
       ========================================= */

    function abrirModalAbonos(idDeudor, montoPendiente) {
        // Reset form e UI
        document.getElementById('formRegistrarAbono').reset();
        document.getElementById('abono_preview_img').classList.add('d-none');
        document.getElementById('abono_preview_icon').classList.remove('d-none');
        document.getElementById('abono_error_monto').classList.add('d-none');
        
        // Cargar datos al modal
        document.getElementById('abono_id_deuda').textContent = idDeudor;
        document.getElementById('abono_input_id_deudor').value = idDeudor;
        document.getElementById('abono_saldo_pendiente_display').textContent = parseFloat(montoPendiente).toFixed(2);
        document.getElementById('abono_input_max_monto').value = montoPendiente;
        
        // Activar la primera pestaña y limpiar tabla historial visualmente
        const bsTab = new bootstrap.Tab(document.getElementById('tab-registrar'));
        bsTab.show();
        document.getElementById('abono_historial_body').innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando historial...</td></tr>';

        // Mostrar el modal
        const modal = new bootstrap.Modal(document.getElementById('modalGestionarAbonos'));
        modal.show();

        // Cargar historial en background
        cargarHistorialAbonos(idDeudor);
    }

    function cargarHistorialAbonos(idDeudor) {
        fetch(`get_historial_abonos.php?id_deudor=${idDeudor}`)
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('abono_historial_body');
                tbody.innerHTML = '';
                
                if (!res.success) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${res.message}</td></tr>`;
                    return;
                }

                if (res.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No se han registrado abonos para esta deuda.</td></tr>';
                } else {
                    res.data.forEach(p => {
                        let captureHtml = p.capture_pago 
                            ? `<a href="../../${p.capture_pago}" target="_blank" class="btn btn-sm btn-light border" title="Ver Capture"><i class="fas fa-image text-primary"></i></a>`
                            : '<span class="text-muted small">N/A</span>';

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${p.fecha_pago || p.fecha_creacion}</td>
                            <td>
                                <strong>Ref:</strong> ${p.referencia_pago || 'N/A'}<br>
                                <span class="small text-muted">${p.nombre_banco || ''}</span>
                            </td>
                            <td>${p.justificacion}</td>
                            <td class="text-end fw-bold text-success">+$${parseFloat(p.monto_cargado).toFixed(2)}</td>
                            <td class="text-center">${captureHtml}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            })
            .catch(err => {
                console.error("Error historial abonos:", err);
                document.getElementById('abono_historial_body').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error técnico al cargar el historial.</td></tr>';
            });
    }

    // Validación de entrada para el monto a abonar
    document.getElementById('abono_monto').addEventListener('input', function() {
        // Limpiar caracteres no numéricos excepto punto (o coma si se prefiere)
        this.value = this.value.replace(/[^0-9.]/g, '');
        
        const val = parseFloat(this.value) || 0;
        const max = parseFloat(document.getElementById('abono_input_max_monto').value) || 0;
        const btnSubmit = document.getElementById('btn_submit_abono');
        const errorMsg = document.getElementById('abono_error_monto');

        if (val > max && max > 0) { // Permitir validación solo si hay max válido
            errorMsg.classList.remove('d-none');
            this.classList.add('is-invalid', 'border-danger');
            btnSubmit.disabled = true;
        } else {
            errorMsg.classList.add('d-none');
            this.classList.remove('is-invalid', 'border-danger');
            btnSubmit.disabled = false;
        }
    });

    // Vista previa de la imagen al subir
    document.getElementById('abono_capture').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const previewImg = document.getElementById('abono_preview_img');
        const previewIcon = document.getElementById('abono_preview_icon');
        
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                previewImg.src = evt.target.result;
                previewImg.classList.remove('d-none');
                previewIcon.classList.add('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.src = '';
            previewImg.classList.add('d-none');
            previewIcon.classList.remove('d-none');
        }
    });

    // Enviar el formulario
    document.getElementById('formRegistrarAbono').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const val = parseFloat(document.getElementById('abono_monto').value) || 0;
        const max = parseFloat(document.getElementById('abono_input_max_monto').value) || 0;
        
        if (val <= 0 || val > max) {
             Swal.fire('Error', 'El monto a abonar no es válido. Revise el saldo pendiente.', 'error');
             return;
        }

        const proceeds = await solicitarClaveAdmin('Confirmar Abono');
        if (!proceeds) return;

        const btn = document.getElementById('btn_submit_abono');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Procesando...';

        const fd = new FormData(this);

        fetch('registrar_abono_deudor.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Verificar si saldó la deuda completamente
                if (res.saldado) {
                    Swal.fire({
                        title: '¡Deuda Saldada!',
                        text: 'El abono ha cubierto la totalidad de la deuda. El cliente ya no es considerado deudor.',
                        icon: 'success',
                        confirmButtonColor: '#198754'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        title: '¡Abono Registrado!',
                        text: res.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                }
            } else {
                Swal.fire('Error', res.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error Técnico', 'No se pudo comunicar con el servidor.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });

    function verContrato(id) {
        // Mostrar modal de carga o simplemente reset
        $('#modalVerContrato').modal('show');
        
        // Limpiar campos visualmente
        $('#vc_id_contrato').text(id);
        $('#vc_nombre_completo').text('Cargando...');
        
        $.get('get_contrato_detalle.php', { id: id }, function(data) {
            if(data.error) {
                alert(data.error);
                return;
            }
            
            // Llenar campos
            $('#vc_nombre_completo').text(data.nombre_completo);
            $('#vc_cedula').text(data.cedula);
            $('#vc_estado').text(data.estado).removeClass().addClass('badge px-3 py-2 ' + (data.estado === 'ACTIVO' ? 'bg-success' : 'bg-danger'));
            $('#vc_plan').text(data.nombre_plan || 'N/A');
            $('#vc_telefono').text(data.telefono || '---');
            $('#vc_correo').text(data.correo || '---');
            $('#vc_direccion_completa').text(data.direccion || '---');
            $('#vc_municipio').text(data.municipio_texto || data.nombre_municipio || '---');
            $('#vc_parroquia').text(data.parroquia_texto || data.nombre_parroquia || '---');
            
            $('#vc_ip_onu').text(data.ip_onu || '---');
            $('#vc_ip').text(data.ip || '---');
            $('#vc_fecha_instalacion').text(data.fecha_instalacion || '---');
            $('#vc_vendedor').text(data.vendedor_texto || '---');
            $('#vc_tipo_conexion').text(data.tipo_conexion || 'Técnicos');
            
            // Lógica Técnica
            $('#vc_tech_ftth, #vc_tech_radio').hide();
            if(data.tipo_conexion && data.tipo_conexion.includes('FTTH')) {
                $('#vc_tech_ftth').show();
                $('#vc_olt').text(data.nombre_olt || '---');
                $('#vc_pon').text(data.nombre_pon || '---');
                $('#vc_caja').text(data.ident_caja_nap || '---');
                $('#vc_puerto').text(data.puerto_nap || '---');
                $('#vc_mac').text(data.mac_onu || '---');
                $('#vc_rx').text(data.onu_rx_power || '---');
                $('#vc_distancia').text(data.distancia_drop ? data.distancia_drop + ' m' : '---');
            } else {
                $('#vc_tech_radio').show();
                $('#vc_ap').text(data.punto_acceso || '---');
                $('#vc_signal').text(data.valor_conexion_dbm ? data.valor_conexion_dbm + ' dBm' : '---');
            }
            
        });
    }

    /* =========================================
       NUEVO: REGISTRO MANUAL DE DEUDA
       ========================================= */
    const searchInputDeuda = document.getElementById('contrato_search_deuda');
    const resultsContainerDeuda = document.getElementById('contrato_search_results_deuda');
    const hiddenInputDeuda = document.getElementById('id_contrato_hidden_deuda');

    if (searchInputDeuda) {
        let timer;
        searchInputDeuda.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 3) { resultsContainerDeuda.innerHTML = ''; return; }
            timer = setTimeout(() => {
                fetch(`buscar_contratos.php?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(data => {
                        resultsContainerDeuda.innerHTML = '';
                        if (data.length) {
                            data.forEach(c => {
                                const a = document.createElement('a');
                                a.className = 'list-group-item list-group-item-action';
                                
                                // Reutilizamos lógica de badges de mensualidades para consistencia
                                let nroLabel = (c.total_contratos > 1) ? `<span class="bg-primary text-white px-2 py-0 rounded-pill me-1" style="font-size:0.7rem;">#${c.nro_orden}</span>` : '';
                                let debtStatusHtml = '';
                                if (c.saldo_deuda && parseFloat(c.saldo_deuda) > 0) {
                                    debtStatusHtml = `<div class="mt-1"><span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">Deuda Pendiente: $${parseFloat(c.saldo_deuda).toFixed(2)}</span></div>`;
                                } else {
                                    debtStatusHtml = `<div class="mt-1"><span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">Al día / Sin deudas</span></div>`;
                                }

                                a.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            ${nroLabel} <strong>ID ${c.id}: ${c.nombre_completo}</strong>
                                            <br><small class="text-muted">C.I.: ${c.cedula || 'N/A'}</small>
                                            ${debtStatusHtml}
                                        </div>
                                        <i class="fas fa-plus text-danger"></i>
                                    </div>
                                `;
                                a.onclick = (e) => {
                                    e.preventDefault();
                                    searchInputDeuda.value = `ID ${c.id}: ${c.nombre_completo}`;
                                    hiddenInputDeuda.value = c.id;
                                    resultsContainerDeuda.innerHTML = '';
                                };
                                resultsContainerDeuda.appendChild(a);
                            });
                        } else {
                            resultsContainerDeuda.innerHTML = '<div class="list-group-item disabled">Sin resultados</div>';
                        }
                    });
            }, 300);
        });
        
        // Cerrar resultados al clickear fuera
        document.addEventListener('click', (e) => {
            if (!searchInputDeuda.contains(e.target) && !resultsContainerDeuda.contains(e.target)) {
                resultsContainerDeuda.innerHTML = '';
            }
        });
    }

    // Envío del formulario de deuda
    document.getElementById('formCrearDeuda').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!hiddenInputDeuda.value) {
            Swal.fire('Atención', 'Por favor, seleccione un cliente válido del buscador.', 'warning');
            return;
        }

        const proceeds = await solicitarClaveAdmin('Confirmar Nueva Deuda');
        if (!proceeds) return;

        const btn = document.getElementById('btn_submit_deuda');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';

        const fd = new FormData(this);
        fetch('registrar_deuda_manual.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({
                    title: '¡Registrado!',
                    text: res.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error Técnico', 'No se pudo comunicar con el servidor.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });

    /**
     * Lógica para ajustar residuos menores a $0.50 a saldo cero
     */
    async function confirmarLimpiezaResiduos() {
        const { isConfirmed } = await Swal.fire({
            title: '¿Ajustar Residuos?',
            text: "Los saldos menores a $0.50 pasarán a $0.00 y se marcarán como PAGADOS (desaparecerán de esta lista). ¿Deseas continuar?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#343a40',
            confirmButtonText: 'Sí, ajustar montos',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!isConfirmed) return;

        const proceeds = await solicitarClaveAdmin('Seguridad: Ingrese Clave');
        if (!proceeds) return;

        Swal.fire({
            title: 'Procesando...',
            text: 'Ajustando saldos residuales...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post('limpiar_residuos.php', {}, function(resp) {
            if (resp.success) {
                Swal.fire({
                    title: '¡Ajuste Completado!',
                    text: resp.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', resp.message, 'error');
            }
        }, 'json').fail(() => {
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        });
    }

    async function eliminarDeuda(id) {
        const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar Deuda?',
            text: "Esta acción marcará la deuda como saldada administrativamente SIN registrar un pago en el sistema. ¿Deseas continuar?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#343a40',
            confirmButtonText: 'Sí, eliminar deuda',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!isConfirmed) return;

        const proceeds = await solicitarClaveAdmin('Seguridad: Ingrese Clave');
        if (!proceeds) return;

        Swal.fire({
            title: 'Procesando...',
            text: 'Eliminando cargo de la lista...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post('eliminar_deuda.php', { id: id }, function(resp) {
            if (resp.success) {
                Swal.fire({
                    title: '¡Eliminado!',
                    text: resp.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', resp.message, 'error');
            }
        }, 'json').fail(() => {
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        });
    }

    async function eliminarSaldoFavor(id) {
        const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar Saldo a Favor?',
            text: "Esta acción anulará el crédito disponible para este cliente SIN generar devoluciones ni registros contables. ¿Deseas continuar?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#343a40',
            confirmButtonText: 'Sí, eliminar saldo',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!isConfirmed) return;

        const proceeds = await solicitarClaveAdmin('Seguridad: Ingrese Clave');
        if (!proceeds) return;

        Swal.fire({
            title: 'Procesando...',
            text: 'Anulando saldo a favor...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post('eliminar_deuda.php', { id: id }, function(resp) {
            if (resp.success) {
                Swal.fire({
                    title: '¡Anulado!',
                    text: resp.message,
                    icon: 'success',
                    confirmButtonColor: '#0d6efd'
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', resp.message, 'error');
            }
        }, 'json').fail(() => {
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        });
    }
</script>

<?php require_once '../includes/layout_foot.php'; ?>