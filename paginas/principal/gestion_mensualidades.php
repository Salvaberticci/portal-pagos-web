<?php
/**
 * Gestión de Mensualidades y Pagos
 * Consolida la Gestión de Cobros (Pendientes) y el Historial de Pagos
 */
$path_to_root = "../../";
require_once '../includes/auth.php';
require_once '../conexion.php';


// Lógica de mensajes
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$message_class = isset($_GET['class']) ? htmlspecialchars($_GET['class']) : '';
//$hoy = date('Y-m-d'); // Ya no es necesario para valores iniciales

// Obtener Bancos para Modal
$bancos = $conn->query("SELECT id_banco, nombre_banco FROM bancos ORDER BY nombre_banco ASC");
$bancos_data = [];
if ($bancos) {
    while ($row = $bancos->fetch_assoc()) {
        $bancos_data[] = $row;
    }
}

$page_title = "Gestión de Mensualidades y Pagos";
$breadcrumb = ["Cobranzas"];
$back_url = "../menu.php";
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';

// Obtener Planes para inferir mensualidades en el modal
$planes_query = $conn->query("SELECT nombre_plan, monto FROM planes ORDER BY monto DESC");
$planes_data = [];
if ($planes_query) {
    while ($row = $planes_query->fetch_assoc()) {
        $planes_data[] = $row;
    }
}
?>
<script>
    const planesDisponibles = <?php echo json_encode($planes_data); ?>;
</script>
<!-- Estilos DataTables -->
<link href="<?php echo $path_to_root; ?>css/datatables.min.css" rel="stylesheet">
<style>
    /* Estilos específicos */
    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #000;
    }

    .badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .badge.bg-success {
        background-color: #198754 !important;
    }

    #contrato_search_results_modal {
        max-height: 200px;
        overflow-y: auto;
    }

    @keyframes pulse-warning {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
        }

        70% {
            transform: scale(1.05);
            box-shadow: 0 0 0 10px rgba(255, 193, 7, 0);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
        }
    }

    .pulse-warning {
        animation: pulse-warning 2s infinite;
    }

    /* Estilo Ferro/Neón para la Fecha */
    .col-fecha-vibrante {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 100px;
    }

    .periodo-badge {
        background: #00f2fe; /* Cian vibrante */
        background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 800;
        font-size: 0.85rem;
        text-transform: uppercase;
        box-shadow: 0 2px 8px rgba(0, 242, 254, 0.4);
        margin-bottom: 6px;
        letter-spacing: 0.5px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .fecha-detalle {
        color: #2c3e50;
        font-weight: 700;
        font-size: 1.05rem;
        text-shadow: 0 0 1px rgba(0,0,0,0.1);
    }

    /* Hover effect for rows */
    #tabla_mensualidades_unica tbody tr {
        cursor: pointer;
    }

    #tabla_mensualidades_unica tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.05) !important;
    }

    /* Hover effect for grouped rows specific */
    .grupo-pago-row:hover {
        background-color: rgba(13, 110, 253, 0.1) !important;
    }

    /* Pestañas (Tabs) Estilo Gestión de Fallas */
    .nav-tabs-custom {
        border-bottom: 2px solid #dee2e6;
        padding: 0 1rem;
    }
    .nav-tabs-custom .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #6c757d;
        font-weight: 700;
        padding: 1rem 1.5rem;
        transition: all 0.2s;
    }
    .nav-tabs-custom .nav-link:hover {
        color: #0d6efd;
        background: rgba(13, 110, 253, 0.05);
    }
    .nav-tabs-custom .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background: transparent;
    }
    .nav-tabs-custom .nav-link.active.tab-sae-pendiente {
        color: #dc3545;
        border-bottom-color: #dc3545;
    }
    .nav-tabs-custom .nav-link.active.tab-sae-cargado {
        color: #198754;
        border-bottom-color: #198754;
    }
    .nav-tabs-custom .badge {
        font-size: 0.7rem;
        padding: 0.35em 0.65em;
        margin-left: 0.5rem;
        vertical-align: middle;
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

    /* Ajuste de la tabla para que no se sienta apretada */
    #tabla_mensualidades_unica {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e9ecef !important;
        margin-bottom: 0px !important;
    }

    #tabla_mensualidades_unica thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        padding: 12px 10px;
    }

    /* Reposicionamiento del mensaje 'Procesando...' */
    .dataTables_wrapper {
        position: relative;
    }
    .dataTables_wrapper .dataTables_processing {
        position: absolute;
        top: 25px; /* Ajustado para estar entre las barras superiores */
        left: 50%;
        width: 200px;
        margin-left: -100px;
        margin-top: 0;
        z-index: 1000;
        background: #fdfdfd;
        border: 1px solid #dee2e6;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        color: #0d6efd;
        font-weight: 600;
        border-radius: 20px;
        padding: 8px 15px;
        text-align: center;
        opacity: 0.95;
    }
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">
        <!-- Header Página -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-1">Lista de Mensualidades y Pagos</h4>
                <p class="text-muted small mb-0">Gestión unificada de mensualidades, cargos y pagos manuales.</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <div class="bg-white p-2 rounded shadow-sm border me-2">
                    <span id="tasa_display" class="text-secondary small">Cargando tasa...</span>
                </div>
                <?php
                // Contar clientes deudores pendientes (Solo deudas, no créditos)
                $res_pend = $conn->query("SELECT COUNT(*) FROM clientes_deudores WHERE estado = 'PENDIENTE' AND tipo_registro = 'DEUDA'");
                $cant_pend = $res_pend ? $res_pend->fetch_array()[0] : 0;
                if ($cant_pend > 0):
                    ?>
                    <a href="gestion_deudores.php" class="btn btn-warning shadow-sm me-2 fw-bold pulse-warning">
                        <i class="fas fa-bell me-2"></i> <?php echo $cant_pend; ?> Deudores Pendientes
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal"
                    data-bs-target="#modalGenerarCobro">
                    <i class="fas fa-plus-circle me-1"></i> Generar Cobro
                </button>
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_class; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_inicio"
                            value="" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_fin"
                            value="" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Cuenta/Banco</label>
                        <select class="form-select form-select-sm" id="filtro_cuenta">
                            <option value="">Todas las Cuentas</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Estado SAE Plus</label>
                        <select class="form-select form-select-sm" id="filtro_sae">
                            <option value="">Cualquier Estado</option>
                            <option value="NO CARGADO">No Cargado</option>
                            <option value="CARGADO">Cargado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Estado de Pago</label>
                        <select class="form-select form-select-sm" id="filtro_estado">
                            <option value="">Cualquier Estado</option>
                            <option value="PAGADO">Pagado</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="RECHAZADO">Rechazado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Tipo de Pago</label>
                        <select class="form-select form-select-sm" id="filtro_tipo">
                            <option value="">Todos los Tipos</option>
                            <option value="Mensualidad">Mensualidad</option>
                            <option value="Instalacion">Instalación</option>
                            <option value="Equipos">Equipos / Materiales</option>
                            <option value="Prorrateo">Prorrateo</option>
                            <option value="Abono">Abono / Saldo a Favor</option>
                            <option value="Extra">Pago de Terceros</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Mes que Pagó</label>
                        <select class="form-select form-select-sm" id="filtro_mes_cobrado">
                            <option value="">Cualquier Mes</option>
                            <option value="Enero">Enero</option>
                            <option value="Febrero">Febrero</option>
                            <option value="Marzo">Marzo</option>
                            <option value="Abril">Abril</option>
                            <option value="Mayo">Mayo</option>
                            <option value="Junio">Junio</option>
                            <option value="Julio">Julio</option>
                            <option value="Agosto">Agosto</option>
                            <option value="Septiembre">Septiembre</option>
                            <option value="Octubre">Octubre</option>
                            <option value="Noviembre">Noviembre</option>
                            <option value="Diciembre">Diciembre</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Meses sin Pagar</label>
                        <select class="form-select form-select-sm" id="filtro_meses_mora">
                            <option value="">Todos los Clientes</option>
                            <option value="1">≥ 1 mes sin pagar</option>
                            <option value="2">≥ 2 meses sin pagar</option>
                            <option value="3">≥ 3 meses sin pagar</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted mb-1">Buscar por Referencia</label>
                        <input type="text" class="form-control form-control-sm" id="filtro_referencia" placeholder="Ej: 123456" autocomplete="off">
                    </div>
                    <div class="col-md-1">
                        <div class="btn-group w-100 shadow-sm">
                            <button class="btn btn-primary btn-sm" onclick="exportarExcel('filtrado')"
                                title="Exportar con filtros actuales">
                                <i class="fa-solid fa-filter me-1"></i> Exportar
                            </button>
                            <button class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split"
                                data-bs-toggle="dropdown"></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item small" href="javascript:void(0)"
                                        onclick="exportarExcel('todos')"><i
                                            class="fa-solid fa-globe text-success me-2"></i> Global</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla Unificada -->
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white p-0 border-bottom-0">
                <ul class="nav nav-tabs nav-tabs-custom" id="mensualidadesTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="tab-general" data-tab="general" type="button">
                            <i class="fas fa-list-ul me-2"></i>General 
                            <span class="badge bg-primary" id="count-general">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link tab-sae-pendiente" id="tab-sae-pendiente" data-tab="sae_pendiente" type="button">
                            <i class="fas fa-times-circle me-2"></i>No Cargado SAE Plus 
                            <span class="badge bg-danger" id="count-sae-pendiente">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link tab-sae-cargado" id="tab-sae-cargado" data-tab="sae_cargado" type="button">
                            <i class="fas fa-check-double me-2"></i>Cargados SAE Plus 
                            <span class="badge bg-success" id="count-sae-cargado">0</span>
                        </button>
                    </li>
                </ul>
                <input type="hidden" id="active_tab" value="general">
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="display table table-striped table-hover w-100" id="tabla_mensualidades_unica">
                        <thead class="bg-light">
                            <tr>
                                <th>Fecha de registro</th>
                                <th>Cédula</th>
                                <th>Referencia</th>
                                <th>Cliente</th>
                                <th>Concepto</th>
                                <th>Detalle/Justificación</th>
                                <th>Monto</th>
                                <th>Cuenta</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Estado SAE Plus</th>
                                <th>Cód. SAE</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ================= MODALES (Migrados de gestión de cobros) ================= -->


<!-- Modal Pagar -->
<div class="modal fade" id="modalPagar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Registrar Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="procesar_pago.php" method="POST" id="form_pagar_simple">
                <div class="modal-body p-4">
                    <input type="hidden" name="id_cobro" id="id_cobro_modal">
                    <input type="hidden" name="tasa_bcv_pagar" id="tasa_bcv_pagar_hidden" value="0">

                    <div class="mb-4 text-center">
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Monto Pendiente</h6>
                        <h2 class="text-success fw-bold display-6" id="monto_display_modal"></h2>
                        <p class="text-muted mb-0">Cliente: <strong id="cliente_nombre_modal"
                                class="text-dark"></strong></p>
                    </div>

                    <!-- Selección de Moneda -->
                    <div class="mb-3 text-center">
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="moneda_pagar" id="moneda_pagar_usd" value="usd"
                                checked>
                            <label class="btn btn-outline-success" for="moneda_pagar_usd"><i
                                    class="fas fa-dollar-sign"></i> USD</label>

                            <input type="radio" class="btn-check" name="moneda_pagar" id="moneda_pagar_bs" value="bs">
                            <label class="btn btn-outline-primary" for="moneda_pagar_bs">Bs (Bolívares)</label>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-secondary small">Monto a Pagar</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-lg"
                            id="input_monto_pagar" required>
                        <input type="hidden" name="monto_pagado" id="monto_pagado_hidden"> <!-- Valor final en USD -->
                        <div id="equiv_pagar" class="form-text text-end fw-bold text-primary mt-1"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Referencia</label>
                        <input type="text" class="form-control" name="referencia_pago" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Banco / Cuenta</label>
                        <select class="form-select" name="id_banco" id="select_banco_modal" required>
                            <option value="">Seleccione...</option>
                            <!-- Llenado por JS -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success">Confirmar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Generar Cobro -->
<div class="modal fade" id="modalGenerarCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Generar Cargo Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_generar_cobro" action="generar_cobro_manual.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4 text-start">
                    <!-- METADATOS BIMONETARIOS OCULTOS -->
                    <input type="hidden" name="moneda_enviada" id="moneda_enviada_hidden" value="usd">
                    <input type="hidden" name="tasa_aplicada" id="tasa_aplicada_hidden" value="0">
                    <input type="hidden" name="monto_credito_aplicado" id="input_credito_aplicado" value="0">
                    
                    <div class="row g-0">
                        <!-- COLUMNA IZQUIERDA: DATOS Y DESGLOSE -->
                        <div class="col-md-7 border-end p-4">
                            <div class="mb-4 position-relative">
                                <label class="form-label fw-bold text-dark small"><i class="fas fa-search me-1 text-success"></i> 1. Buscar Contrato</label>
                                <div class="input-group shadow-sm border rounded">
                                    <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-user-circle"></i></span>
                                    <input type="text" class="form-control border-0 ps-0" id="contrato_search_modal" placeholder="ID, Nombre o Cédula" required autocomplete="off">
                                </div>
                                <input type="hidden" name="id_contrato" id="id_contrato_hidden_modal" required>
                                <div id="contrato_search_results_modal" class="list-group shadow-lg position-absolute w-100" style="z-index: 1060;"></div>
                                <!-- Nuevo: Info del plan -->
                                <div id="info_plan_cliente" class="mt-2 d-none">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 w-100 text-start">
                                        <i class="fas fa-satellite-dish me-1"></i> Plan: <strong id="val_plan_nombre">---</strong>
                                    </span>
                                </div>
                                <!-- Nuevo: Saldo a Favor -->
                                <div id="info_saldo_favor" class="mt-2 d-none">
                                    <div class="alert alert-success d-flex align-items-center mb-0 py-2 px-3 border-success shadow-sm" role="alert">
                                        <i class="fas fa-coins me-2"></i>
                                        <div class="flex-grow-1">
                                            El cliente tiene un <strong>Saldo a Favor de $<span id="val_saldo_favor">0.00</span></strong>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-success fw-bold px-3 ms-2" id="btn_aplicar_credito" onclick="aplicarSaldoAFavor()">
                                            <i class="fas fa-check-circle me-1"></i> Usar Saldo
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 bg-light p-3 rounded border shadow-sm">
                                <label class="form-label fw-bold text-dark small d-block mb-3">2. Monto Declarado del Pago</label>
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <div class="btn-group w-100 border rounded bg-white p-1" role="group">
                                            <input type="radio" class="btn-check" name="moneda_cobro" id="moneda_cobro_usd" value="usd">
                                            <label class="btn btn-outline-success border-0 btn-sm py-2" for="moneda_cobro_usd"><i class="fas fa-dollar-sign"></i> USD</label>
                                            <input type="radio" class="btn-check" name="moneda_cobro" id="moneda_cobro_bs" value="bs" checked>
                                            <label class="btn btn-outline-primary border-0 btn-sm py-2" for="moneda_cobro_bs">Bs</label>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-muted fw-bold" id="moneda_label_principal">Bs.</span>
                                            <input type="text" class="form-control form-control-lg fw-bold text-success border-0 shadow-none decimal-input" id="input_monto_cobro" required placeholder="0,00" style="background-color: transparent;" inputmode="decimal">
                                        </div>
                                        <input type="hidden" name="monto" id="monto_cobro_hidden">
                                        <div id="equiv_cobro" class="form-text fw-bold text-primary mt-1 px-1" style="font-size: 0.8rem;"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted small mb-1"><i class="fas fa-calendar-day"></i> Fecha (Pago Real)</label>
                                    <input type="date" class="form-control form-control-sm shadow-sm" name="fecha_pago" id="input_fecha_generar_cobro" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted small mb-1"><i class="fas fa-hashtag"></i> N° Referencia</label>
                                    <input type="text" class="form-control form-control-sm shadow-sm" name="referencia_pago" id="input_ref_generar_cobro" required placeholder="Nro de operación">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-muted small mb-1"><i class="fas fa-university"></i> Banco Receptor</label>
                                    <select class="form-select form-select-sm shadow-sm" name="id_banco_pago" id="select_banco_cobro" required>
                                        <option value="">Seleccione banco...</option>
                                    </select>
                                </div>
                            </div>

                            <h6 class="fw-bold text-success small mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-layer-group me-1"></i> 3. Desglose del Pago</span>
                                <span id="badge_indicador_suma" class="badge bg-danger text-white border-0 py-1 px-3" style="font-size: 0.65rem;">PENDIENTE</span>
                            </h6>
                            
                            <!-- Área de Scroll para el Desglose -->
                            <div class="desglose-scroll pe-2" style="max-height: 280px; overflow-y: auto;">
                                <!-- Mensualidad -->
                                <div class="mb-2 bg-white border-start border-4 border-primary rounded p-2 shadow-sm">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch" type="checkbox" id="switch_mensualidad" name="desglose_mensualidad_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="switch_mensualidad">Mensualidad</label>
                                    </div>
                                    <div class="d-none desglose-fields row g-2 mt-1" id="fields_mensualidad">
                                        <div class="col-4">
                                            <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto</label>
                                            <input type="text" class="form-control form-control-sm desglose-monto decimal-input" name="monto_mensualidad" placeholder="0,00" inputmode="decimal">
                                            <div class="equiv-desglose text-primary fw-bold mt-1" style="font-size: 0.7rem;"></div>
                                        </div>
                                        <div class="col-3">
                                            <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Cant.</label>
                                            <input type="number" step="1" min="1" max="3" class="form-control form-control-sm meses-cantidad" name="meses_mensualidad" value="1">
                                        </div>
                                        <div class="col-5">
                                            <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Meses a Pagar</label>
                                            <div class="container-meses-dinamicos d-flex flex-wrap gap-1">
                                                <!-- Se genera vía JS -->
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Instalación -->
                                <div class="mb-2 bg-white border-start border-4 border-info rounded p-2 shadow-sm">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch" type="checkbox" id="switch_instalacion" name="desglose_instalacion_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="switch_instalacion">Instalación</label>
                                    </div>
                                    <div class="d-none desglose-fields mt-1" id="fields_instalacion">
                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto</label>
                                        <input type="text" class="form-control form-control-sm desglose-monto decimal-input" name="monto_instalacion" placeholder="0,00" inputmode="decimal">
                                        <div class="equiv-desglose text-primary fw-bold mt-1" style="font-size: 0.7rem;"></div>
                                    </div>
                                </div>

                                <!-- Equipos -->
                                <div class="mb-2 bg-white border-start border-4 border-secondary rounded p-2 shadow-sm">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch" type="checkbox" id="switch_equipo" name="desglose_equipo_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="switch_equipo">Equipos/Materiales</label>
                                    </div>
                                    <div class="d-none desglose-fields mt-1" id="fields_equipo">
                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto</label>
                                        <input type="text" class="form-control form-control-sm desglose-monto decimal-input" name="monto_equipo" placeholder="0,00" inputmode="decimal">
                                        <div class="equiv-desglose text-primary fw-bold mt-1" style="font-size: 0.7rem;"></div>
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <div class="bg-white border-start border-4 border-danger rounded p-2 shadow-sm h-100">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="form-check form-switch me-2 mb-0">
                                                    <input class="form-check-input desglose-switch" type="checkbox" id="switch_prorrateo" name="desglose_prorrateo_activado" value="1">
                                                </div>
                                                <label class="form-check-label fw-bold text-dark small mb-0" for="switch_prorrateo" style="font-size: 0.75rem;">Prorrateo</label>
                                            </div>
                                            <div class="d-none desglose-fields mt-1" id="fields_prorrateo">
                                                <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto</label>
                                                <input type="text" class="form-control form-control-sm desglose-monto decimal-input" name="monto_prorrateo" placeholder="0,00" inputmode="decimal">
                                                <div class="equiv-desglose text-primary fw-bold mt-1" style="font-size: 0.7rem;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mensualidad Extra -->
                                <div class="mb-3 bg-white border rounded p-3 shadow-sm border-info mt-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch" type="checkbox" id="switch_extra" name="desglose_extra_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-info me-auto small mb-0" for="switch_extra">Pago de Terceros</label>
                                    </div>
                                    <div class="d-none desglose-fields" id="fields_extra">
                                        <div id="contenedor_extras">
                                            <div class="fila-extra mb-3 border-bottom pb-3">
                                                <div class="position-relative mb-2">
                                                    <label class="small text-muted fw-bold mb-1">Usuario / Contrato</label>
                                                    <input type="text" class="form-control form-control-sm extra-search" placeholder="ID, Nombre o Cédula..." autocomplete="off">
                                                    <input type="hidden" name="extra_contrato[]" class="extra-hidden">
                                                    <div class="list-group shadow-lg position-absolute w-100 extra-results" style="z-index: 1080; max-height: 100px; overflow-y: auto;"></div>
                                                    <div class="extra-plan-info mt-1 d-none" style="font-size: 0.65rem;">
                                                        <span class="text-primary fw-bold"><i class="fas fa-tag me-1"></i> <span class="extra-plan-name">---</span></span>
                                                    </div>
                                                </div>
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-3">
                                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto</label>
                                                        <input type="text" class="form-control form-control-sm desglose-monto decimal-input" name="extra_monto[]" placeholder="0,00" inputmode="decimal">
                                                        <div class="equiv-desglose text-primary fw-bold mt-1" style="font-size: 0.7rem;"></div>
                                                    </div>
                                                    <div class="col-2">
                                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Cant.</label>
                                                        <input type="number" step="1" min="1" max="3" class="form-control form-control-sm meses-cantidad" name="extra_meses[]" value="1">
                                                    </div>
                                                    <div class="col-5">
                                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Meses a Pagar</label>
                                                        <div class="container-meses-dinamicos d-flex flex-wrap gap-1">
                                                            <!-- Se genera vía JS -->
                                                        </div>
                                                    </div>
                                                    <div class="col-2 text-end">
                                                        <button type="button" class="btn btn-sm text-danger border-0 btn-remove-extra" disabled><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link text-info p-0 mt-1" id="btn_add_extra"><i class="fas fa-plus-circle"></i> Añadir otro Usuario</button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-dark rounded p-3 mb-2 border-0 shadow-sm mt-3 mx-0 text-white">
                                <div class="row g-2 text-center text-md-start">
                                    <div class="col-4 border-end border-secondary">
                                        <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.55rem;">Monto Pagado</div>
                                        <div class="fw-bold text-white fs-5"><span class="sym_res"></span><span id="val_monto_total">0.00</span></div>
                                        <div id="equiv_total_declarado" class="small opacity-75" style="font-size: 0.6rem;"></div>
                                    </div>
                                    <div class="col-4 border-end border-secondary">
                                        <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.55rem;">Monto Total a Pagar</div>
                                        <div class="fw-bold text-info fs-5"><span class="sym_res"></span><span id="val_suma_desglose">0.00</span></div>
                                        <div id="equiv_total_desglose" class="small opacity-75" style="font-size: 0.6rem;"></div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.55rem;">Saldo Pendiente</div>
                                        <div class="fw-bold fs-5" id="container_restante"><span class="sym_res"></span><span id="val_monto_restante">0.00</span></div>
                                        <div id="equiv_restante" class="small opacity-75" style="font-size: 0.6rem;"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Aviso Tasa Histórica -->
                            <div id="alerta_tasa_historica" class="d-none alert alert-info py-2 px-3 border-0 shadow-sm rounded mb-3 animate__animated animate__fadeIn">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-history me-2"></i>
                                    <span class="small fw-bold text-dark">Tasa BCV calculada para el <span id="txt_fecha_tasa" class="text-primary">hoy</span>: <strong class="fs-6 text-success">Bs. <span id="val_tasa_historica">--</span></strong></span>
                                </div>
                            </div>
                            <!-- Aviso de Saldo sin Asignar -->
                            <div id="aviso_saldo_descuadrado" class="d-none alert alert-warning py-2 px-3 border-0 shadow-sm rounded mb-3 animate__animated animate__shakeX">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <span class="small fw-bold">Tienes <span id="val_monto_aviso">0.00</span> sin distribuir en el desglose.</span>
                                </div>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-muted small mb-1">Autorizado por</label>
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" class="form-control shadow-sm" name="autorizado_por" id="input_autorizado_por" required placeholder="Firma administrativa">
                                        <button class="btn btn-outline-primary" type="button" onclick="document.getElementById('input_autorizado_por').value = '<?php echo addslashes($user_name); ?>'">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-muted small mb-1">Justificación del Cargo</label>
                                    <textarea class="form-control form-control-sm shadow-sm" name="justificacion" rows="2" placeholder="Notas adicionales (opcional)..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMNA DERECHA: OCR Y CAPTURE -->
                        <div class="col-md-5 bg-light p-4 rounded-end d-flex flex-column" style="min-height: 520px;">
                            <h6 class="fw-bold text-primary mb-3"><i class="fas fa-camera-retro me-1"></i> 4. Comprobante de Pago</h6>
                            <div class="mb-4 bg-white p-3 rounded shadow-sm border">
                                <input class="form-control form-control-sm mb-0 shadow-none border-0" type="file" id="capture_upload" name="capture_archivo" accept="image/*">
                                <div id="ocr_status" class="mt-2 small text-info fw-bold d-none">
                                    <i class="fas fa-sync fa-spin"></i> Procesando OCR...
                                </div>
                            </div>

                            <!-- Previsualización -->
                            <div id="capture_preview_container" class="d-none">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-secondary">Imagen del Capture:</span>
                                    <button type="button" class="btn btn-sm text-danger p-0 border-0" onclick="clearCaptureUpload()">Eliminar</button>
                                </div>
                                <div class="position-relative border rounded shadow-lg overflow-hidden bg-white" style="border: 2px solid #ccc !important;">
                                    <div class="zoom-box scroll-premium" style="height: 520px; overflow-y: auto; background: #fafafa;">
                                        <img id="capture_preview_img" src="" alt="Capture" style="width: 100%; height: auto;">
                                    </div>
                                    <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-50 text-white text-center" style="font-size: 0.6rem;">
                                        <i class="fas fa-eye"></i> MODO VERIFICACIÓN
                                    </div>
                                </div>
                            </div>

                            <!-- Placeholder -->
                            <div id="capture_placeholder" class="text-center py-5 border rounded bg-white border-dashed flex-grow-1 d-flex flex-column justify-content-center align-items-center" style="border: 2px dashed #dbdde0 !important; min-height: 400px; color: #adb5bd;">
                                <i class="fas fa-file-invoice-dollar fa-5x mb-3 opacity-25"></i>
                                <h6 class="fw-bold opacity-50">Esperando comprobante</h6>
                                <p class="small opacity-50 px-4 text-center">Selecciona la imagen para visualizarla aquí.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-danger"><i class="fas fa-exclamation-triangle fa-3x"></i></div>
                <h5 class="fw-bold">¿Eliminar Cobro?</h5>
                <form id="formEliminar" method="POST" action="elimina_cobro.php">
                    <input type="hidden" name="id" id="id_cobro_eliminar">
                    <input type="hidden" name="clave" id="delete_password_hidden">
                    <p class="text-muted small mb-4">Se eliminará el cobro #<strong id="id_display_eliminar"></strong>
                        de <strong id="cliente_nombre_eliminar"></strong></p>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger fw-medium">Sí, Eliminar</button>
                        <button type="button" class="btn btn-light text-secondary fw-medium"
                            data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Justificación (Cargos Manuales) -->
<div class="modal fade" id="modalJustificacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-info-circle me-2"></i>Detalles del Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="justif_loader" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="small text-muted mt-2">Cargando detalles...</p>
                </div>
                <div id="justif_content" class="d-none">
                    <h6 class="fw-bold text-primary mb-1" id="justif_cliente_nombre">---</h6>
                    <p class="small text-muted mb-3">Contrato #<span id="justif_id_contrato">---</span> | Factura #<span id="justif_id_cobro">---</span></p>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="small text-muted fw-bold d-block mb-1">Monto Cargado</label>
                            <span class="badge bg-danger text-white fs-6 px-3" id="justif_monto">$0.00</span>
                        </div>
                        <div class="col-6 text-end">
                            <label class="small text-muted fw-bold d-block mb-1">F. Creación</label>
                            <span class="small fw-bold" id="justif_fecha_creacion">--/--/----</span>
                        </div>
                    </div>

                    <div class="bg-light rounded p-3 border mb-4 shadow-sm">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small text-muted fw-bold d-block mb-0" style="font-size: 0.65rem;">Referencia</label>
                                <span class="fw-bold text-dark" id="justif_referencia">---</span>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted fw-bold d-block mb-0" style="font-size: 0.65rem;">Banco Receptor</label>
                                <span class="fw-bold text-dark" id="justif_banco">---</span>
                            </div>
                            <div class="col-12 mt-2">
                                <label class="small text-muted fw-bold d-block mb-0" style="font-size: 0.65rem;">Autorizado por</label>
                                <span class="fw-bold text-success" id="justif_autorizado">---</span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold small text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Conceptos del Pago:</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered bg-white small mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Factura #</th>
                                    <th>Descripción / Concepto</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody id="justif_conceptos_body">
                                <!-- Dinámico -->
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold bg-light">
                                    <td colspan="2" class="text-end">Total Pagado:</td>
                                    <td class="text-end text-success" id="justif_total_pagado">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="alert alert-secondary border-0 bg-light p-3 small mb-3 scroll-premium" style="max-height: 120px; overflow-y: auto; line-height: 1.5;" id="justif_texto">
                        ---
                    </div>

                    <h6 class="fw-bold small text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Evidencia del Capture:</h6>
                    <div id="justif_capture_container" class="border rounded bg-white text-center p-2 d-none shadow-sm">
                        <a href="" target="_blank" id="justif_capture_link">
                            <img src="" id="justif_capture_img" class="img-fluid rounded" style="max-height: 350px;" alt="Capture de pago">
                        </a>
                    </div>
                    <div id="justif_no_capture" class="text-center py-3 bg-light rounded border border-dashed d-none">
                        <i class="fas fa-image-slash fa-2x opacity-25 mb-2"></i>
                        <p class="small text-muted mb-0">Sin comprobante digital disponible</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Historial -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">Historial de Pagos - <span id="hist_cliente_nombre"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover w-100" style="font-size: 0.85rem;">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th>ID</th>
                                <th>Emisión</th>
                                <th>Vencimiento</th>
                                <th>Fecha Pago</th>
                                <th>Concepto</th>
                                <th>Monto</th>
                                <th>Referencia</th>
                                <th>Detalle/Justificación</th>
                            </tr>
                        </thead>
                        <tbody id="hist_table_body">
                            <!-- Data populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Cobro Potenciado -->
<div class="modal fade" id="modalEditarCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Modificar Pago #<span id="edit_id_cobro_display"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarCobro" enctype="multipart/form-data">
                <div class="modal-body p-0">
                    <div id="edit_modal_loader" class="text-center py-5 d-none">
                        <i class="fas fa-spinner fa-spin fa-3x text-warning"></i>
                        <p class="mt-2 fw-bold text-muted">Cargando datos del pago...</p>
                    </div>
                    <div id="edit_modal_content" class="row g-0">
                        <!-- COLUMNA IZQUIERDA: DESGLOSE -->
                        <div class="col-md-7 border-end p-4">
                            <input type="hidden" name="id_cobro" id="edit_id_cobro">
                            <input type="hidden" name="id_grupo_pago" id="edit_id_grupo_pago">
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small mb-1">Cliente / Contrato</label>
                                    <div class="p-2 bg-light border rounded fw-bold text-dark small" id="edit_cliente_info_static">
                                        ---
                                    </div>
                                    <input type="hidden" name="id_contrato" id="edit_id_contrato_hidden">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small mb-1">Estado del Pago</label>
                                    <select name="estado" id="edit_estado" class="form-select form-select-sm shadow-sm" required>
                                        <option value="PENDIENTE">PENDIENTE</option>
                                        <option value="PAGADO">PAGADO</option>
                                        <option value="RECHAZADO">RECHAZADO</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4 bg-light p-3 rounded border shadow-sm">
                                <label class="form-label fw-bold text-dark small d-block mb-3">Monto Total Reportado</label>
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-7">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white text-muted fw-bold">$</span>
                                            <input type="text" class="form-control form-control-lg fw-bold text-success border-0 shadow-none decimal-input" id="edit_input_monto_total" name="monto_total_visible" required placeholder="0,00" inputmode="decimal">
                                        </div>
                                        <input type="hidden" name="monto_total" id="edit_monto_total_hidden">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="small text-muted fw-bold d-block mb-1">Vencimiento</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_vencimiento" id="edit_fecha_vencimiento" required>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-7"></div>
                                    <div class="col-md-5">
                                        <label class="small text-muted fw-bold d-block mb-1">Fecha Emisión / Pago</label>
                                        <input type="date" class="form-control form-control-sm" name="fecha_pago" id="edit_fecha_pago" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small mb-1"><i class="fas fa-hashtag"></i> N° Referencia</label>
                                    <input type="text" class="form-control form-control-sm shadow-sm" name="referencia_pago" id="edit_input_referencia" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted small mb-1"><i class="fas fa-university"></i> Banco Receptor</label>
                                    <select class="form-select form-select-sm shadow-sm" name="id_banco_pago" id="edit_select_banco" required>
                                        <option value="">Seleccione banco...</option>
                                    </select>
                                </div>
                            </div>

                            <h6 class="fw-bold text-warning small mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-layer-group me-1"></i> Desglose Actual del Pago</span>
                                <span id="edit_badge_indicador_suma" class="badge bg-success text-white border-0 py-1 px-3" style="font-size: 0.65rem;">CALCULANDO...</span>
                            </h6>
                            
                            <div class="desglose-scroll pe-2" style="max-height: 280px; overflow-y: auto;">
                                <!-- Mensualidad -->
                                <div class="mb-2 bg-white border-start border-4 border-primary rounded p-2 shadow-sm">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch-edit" type="checkbox" id="edit_switch_mensualidad" name="desglose_mensualidad_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="edit_switch_mensualidad">Mensualidad</label>
                                    </div>
                                    <div class="d-none desglose-fields-edit row g-2 mt-1" id="edit_fields_mensualidad">
                                        <div class="col-4">
                                            <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto $</label>
                                            <input type="text" class="form-control form-control-sm desglose-monto-edit decimal-input" name="monto_mensualidad" placeholder="0,00" inputmode="decimal">
                                        </div>
                                        <div class="col-3">
                                            <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Cant.</label>
                                            <input type="number" step="1" min="1" max="12" class="form-control form-control-sm meses-cantidad-edit" name="meses_mensualidad" value="1">
                                        </div>
                                        <div class="col-5">
                                             <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Meses a Pagar</label>
                                             <div id="edit_container_meses_mensualidad" class="container-meses-dinamicos d-flex flex-wrap gap-1">
                                                 <!-- Se genera vía JS -->
                                             </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Instalación -->
                                <div class="mb-2 bg-white border-start border-4 border-info rounded p-2 shadow-sm">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch-edit" type="checkbox" id="edit_switch_instalacion" name="desglose_instalacion_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="edit_switch_instalacion">Instalación</label>
                                    </div>
                                    <div class="d-none desglose-fields-edit mt-1" id="edit_fields_instalacion">
                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto $</label>
                                        <input type="text" class="form-control form-control-sm desglose-monto-edit decimal-input" name="monto_instalacion" placeholder="0,00" inputmode="decimal">
                                    </div>
                                </div>

                                <!-- Equipos -->
                                <div class="mb-2 bg-white border-start border-4 border-secondary rounded p-2 shadow-sm">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch-edit" type="checkbox" id="edit_switch_equipo" name="desglose_equipo_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-dark small mb-0" for="edit_switch_equipo">Equipos/Materiales</label>
                                    </div>
                                    <div class="d-none desglose-fields-edit mt-1" id="edit_fields_equipo">
                                        <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto $</label>
                                        <input type="text" class="form-control form-control-sm desglose-monto-edit decimal-input" name="monto_equipo" placeholder="0,00" inputmode="decimal">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <div class="bg-white border-start border-4 border-danger rounded p-2 shadow-sm h-100">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="form-check form-switch me-2 mb-0">
                                                    <input class="form-check-input desglose-switch-edit" type="checkbox" id="edit_switch_prorrateo" name="desglose_prorrateo_activado" value="1">
                                                </div>
                                                <label class="form-check-label fw-bold text-dark small mb-0" for="edit_switch_prorrateo" style="font-size: 0.75rem;">Prorrateo</label>
                                            </div>
                                            <div class="d-none desglose-fields-edit mt-1" id="edit_fields_prorrateo">
                                                <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto $</label>
                                                <input type="text" class="form-control form-control-sm desglose-monto-edit decimal-input" name="monto_prorrateo" placeholder="0,00" inputmode="decimal">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mensualidad Extra (Pago de Tercero) -->
                                <div class="mb-3 bg-white border rounded p-3 shadow-sm border-info mt-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <div class="form-check form-switch me-3 mb-0">
                                            <input class="form-check-input desglose-switch-edit" type="checkbox" id="edit_switch_extra" name="desglose_extra_activado" value="1">
                                        </div>
                                        <label class="form-check-label fw-bold text-info me-auto small mb-0" for="edit_switch_extra">Pago de Terceros</label>
                                    </div>
                                    <div class="d-none desglose-fields-edit" id="edit_fields_extra">
                                        <div id="edit_contenedor_extras">
                                            <!-- Se genera vía JS -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link text-info p-0 mt-1" id="edit_btn_add_extra"><i class="fas fa-plus-circle"></i> Añadir otro Usuario</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Caja de Resumen Económico -->
                            <div class="bg-dark rounded p-3 mb-2 border-0 shadow-sm mt-3 mx-0 text-white">
                                <div class="row g-2 text-center text-md-start">
                                    <div class="col-4 border-end border-secondary">
                                        <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.55rem;">Monto Pagado</div>
                                        <div class="fw-bold text-white fs-5">$ <span id="edit_val_monto_total">0.00</span></div>
                                    </div>
                                    <div class="col-4 border-end border-secondary">
                                        <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.55rem;">Monto Total a Pagar</div>
                                        <div class="fw-bold text-info fs-5">$ <span id="edit_val_suma_desglose">0.00</span></div>
                                    </div>
                                    <div class="col-4">
                                        <div class="small fw-bold text-uppercase opacity-75" style="font-size: 0.55rem;">Saldo Pendiente</div>
                                        <div class="fw-bold fs-5" id="edit_container_restante">$ <span id="edit_val_monto_restante">0.00</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-muted small mb-1">Autorizado por</label>
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" class="form-control shadow-sm" name="autorizado_por" id="edit_autorizado_por" required placeholder="Firma administrativa">
                                        <button class="btn btn-outline-primary" type="button" onclick="document.getElementById('edit_autorizado_por').value = '<?php echo addslashes($user_name); ?>'">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-muted small mb-1">Justificación del Cargo</label>
                                    <textarea class="form-control form-control-sm shadow-sm" name="justificacion" id="edit_pago_justificacion" rows="2" placeholder="Notas adicionales (opcional)..."></textarea>
                                </div>
                            </div>

                        </div>

                        <!-- COLUMNA DERECHA: CAPTURE -->
                        <div class="col-md-5 ps-lg-4 bg-light p-4 rounded-end d-flex flex-column">
                            <h6 class="fw-bold text-primary mb-3"><i class="fas fa-image me-1"></i> Evidencia del Pago</h6>
                            
                            <div class="mb-3 bg-white p-3 rounded shadow-sm border">
                                <label class="small fw-bold text-muted mb-2 d-block">Actualizar/Subir Nuevo Capture</label>
                                <input class="form-control form-control-sm" type="file" name="capture_archivo" id="edit_capture_upload" accept="image/*">
                            </div>

                            <!-- Previsualización Actual -->
                            <div id="edit_capture_preview_container" class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-secondary">Imagen Actual/Nueva:</span>
                                    <button type="button" class="btn btn-sm text-danger p-0 border-0 d-none" id="btn_clear_edit_capture">Limpiar Selección</button>
                                </div>
                                <div class="border rounded shadow-sm bg-white" style="height: 600px; overflow-y: auto;">
                                    <img id="edit_capture_preview_img" src="" alt="Capture" class="img-fluid" style="width: 100%;">
                                    <div id="edit_capture_empty" class="h-100 d-flex flex-column justify-content-center align-items-center text-muted opacity-50">
                                        <i class="fas fa-image fa-4x mb-2"></i>
                                        <p class="small">Sin imagen registrada</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <div class="me-auto">
                         <span class="small text-muted fw-bold">Total Desglose: $<span id="edit_val_suma_desglose">0.00</span></span>
                    </div>
                    <button type="button" class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning shadow-sm border-0 fw-bold px-4">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Exito -->
<div class="modal fade" id="modalExito" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-success"><i class="fas fa-check-circle fa-4x"></i></div>
                <h5 class="fw-bold mb-2" id="modalExitoLabel">Operación Exitosa</h5>
                <p class="text-muted mb-4" id="modal_ex_mensaje_principal">Acción completada.</p>
                <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>


<!-- Scripts JS -->
<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/datatables.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function () {
        // === TASA DE CAMBIO ===
        let TASA_BCV = 0;
        let TASA_BCV_HOY = 0;
        let historicoTasas = null;

        // Obtener Tasa al Cargar
        fetch('get_tasa_dolar.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    TASA_BCV = parseFloat(data.promedio);
                    TASA_BCV_HOY = TASA_BCV;
                    // *** CRÍTICO: Sincronizar la tasa inicial con el campo oculto del formulario ***
                    const inputTasaHidden = document.getElementById('tasa_aplicada_hidden');
                    if (inputTasaHidden) inputTasaHidden.value = TASA_BCV;
                    $('#tasa_display').html(`<strong>Tasa BCV/Ref:</strong> Bs. ${TASA_BCV.toFixed(2)}`);
                } else {
                    $('#tasa_display').html(`<span class="text-danger">Error Tasa</span>`);
                }
            });

        // Lógica de Tasa Histórica (BCV)
        const inputFechaPago = document.getElementById('input_fecha_generar_cobro');
        const alertaTasa = document.getElementById('alerta_tasa_historica');
        const txtFechaTasa = document.getElementById('txt_fecha_tasa');
        const valTasaHistorica = document.getElementById('val_tasa_historica');

        function aplicarTasa(tasa, fechaTexto) {
            TASA_BCV = tasa;
            const inputTasaHidden = document.getElementById('tasa_aplicada_hidden');
            if(inputTasaHidden) inputTasaHidden.value = tasa;
            
            if(alertaTasa && valTasaHistorica) {
                valTasaHistorica.textContent = tasa.toFixed(2);
                txtFechaTasa.textContent = fechaTexto;
                alertaTasa.classList.remove('d-none');
            }
            if(typeof calcCobro === 'function') calcCobro('rate');
        }

        function buscarAplicarTasaHistorica(targetDate) {
            // El array viene ordenado ascendentemente. Iteramos al revés para buscar la fecha <= targetDate
            let foundTasa = null;
            let foundFecha = null;
            for (let i = historicoTasas.length - 1; i >= 0; i--) {
                if (historicoTasas[i].fecha <= targetDate) {
                    foundTasa = historicoTasas[i].promedio;
                    foundFecha = historicoTasas[i].fecha;
                    break;
                }
            }
            
            if (foundTasa) {
                if(alertaTasa) {
                    alertaTasa.classList.remove('alert-warning');
                    alertaTasa.classList.add('alert-info');
                }
                const [y, m, d] = foundFecha.split('-');
                aplicarTasa(foundTasa, `${d}/${m}/${y}`);
            } else {
                aplicarTasa(TASA_BCV_HOY, 'hoy (sin datos)');
                if (alertaTasa) alertaTasa.classList.add('d-none');
            }
        }

        if (inputFechaPago) {
            inputFechaPago.addEventListener('change', function() {
                const selectedDate = this.value;
                const today = new Date().toISOString().split('T')[0];
                
                // Si la fecha elegida es hoy, a futuro, o vacía, regresamos a la tasa actual
                if (selectedDate >= today || !selectedDate) {
                    aplicarTasa(TASA_BCV_HOY, 'hoy');
                    if (alertaTasa) alertaTasa.classList.add('d-none'); // Ocultar si es hoy
                    return;
                }

                // Mostrar mensaje de "buscando"
                if(alertaTasa && valTasaHistorica) {
                    alertaTasa.classList.remove('alert-info');
                    alertaTasa.classList.add('alert-warning');
                    txtFechaTasa.textContent = 'buscando en BCV...';
                    valTasaHistorica.textContent = '...';
                    alertaTasa.classList.remove('d-none');
                }

                // Si ya descargamos las tasas, buscar directamente
                if (historicoTasas) {
                    buscarAplicarTasaHistorica(selectedDate);
                } else {
                    // Descargar historial (ve.dolarapi.com/v1/historicos/dolares/oficial)
                    fetch('https://ve.dolarapi.com/v1/historicos/dolares/oficial')
                        .then(r => r.json())
                        .then(data => {
                            historicoTasas = data;
                            buscarAplicarTasaHistorica(selectedDate);
                        })
                        .catch(err => {
                            console.error("Error obteniendo histórico:", err);
                            aplicarTasa(TASA_BCV_HOY, 'hoy (fallo API)');
                            if(alertaTasa) {
                                alertaTasa.classList.remove('alert-warning');
                                alertaTasa.classList.add('alert-info');
                            }
                        });
                }
            });
        }

        // === VALIDACIÓN DE FECHAS ===
        const inputDesde = document.getElementById('fecha_inicio');
        const inputHasta = document.getElementById('fecha_fin');

        const syncFechas = () => {
            if (inputDesde && inputHasta) {
                inputDesde.setAttribute('max', inputHasta.value);
                inputHasta.setAttribute('min', inputDesde.value);
            }
        };

        if (inputDesde && inputHasta) {
            inputDesde.addEventListener('change', function () {
                if (this.value > inputHasta.value) {
                    inputHasta.value = this.value;
                    $(inputHasta).trigger('change');
                }
                syncFechas();
            });

            inputHasta.addEventListener('change', function () {
                if (this.value < inputDesde.value) {
                    inputDesde.value = this.value;
                    $(inputDesde).trigger('change');
                }
                syncFechas();
            });

            syncFechas(); // Inicializar limites
        }

        // === VALIDACIÓN DE MONTOS (SOLO NÚMEROS Y POSITIVOS) ===
        const amountInputs = ['input_monto_pagar', 'input_monto_cobro', 'monto_pagado_hoy'];
        amountInputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                // 1. Bloquear teclas no numéricas al escribir
                input.addEventListener('keydown', function (e) {
                    // Permitir: backspace, delete, tab, escape, enter, dot, comma
                    if ([46, 8, 9, 27, 13, 110, 190, 188].indexOf(e.keyCode) !== -1 ||
                        // Permitir: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+R
                        (e.ctrlKey === true && [65, 67, 86, 88, 82].indexOf(e.keyCode) !== -1) ||
                        // Permitir: home, end, left, right
                        (e.keyCode >= 35 && e.keyCode <= 39)) {
                        return;
                    }
                    // Bloquear si no es un número
                    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                        e.preventDefault();
                    }
                });

                // 2. Limpiar pegado de texto no numérico
                input.addEventListener('paste', function (e) {
                    const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    if (/[^0-9,.]/.test(pasteData)) {
                        e.preventDefault();
                        const cleanedData = pasteData.replace(/[^0-9,.]/g, '');
                        // Insertar texto limpio manualmente si es necesario o simplemente avisar
                        document.execCommand('insertText', false, cleanedData);
                    }
                });

                // 3. Asegurar positivo al final
                input.addEventListener('input', function () {
                    // Por si pasó algún carácter raro (como 'e')
                    this.value = this.value.replace(/[^0-9.,]/g, '');

                    if (parseFloat(this.value) < 0) {
                        this.value = 0;
                    }
                    $(this).trigger('input'); // Disparar cálculos dependientes (conversión Bs/$)
                });
            }
        });


        // === CONVERSIÓN EN MODAL GENERAR COBRO ===
        const inputMontoCobro = document.getElementById('input_monto_cobro');
        const inputMontoCobroHidden = document.getElementById('monto_cobro_hidden');
        const inputMonedaEnviadaHidden = document.getElementById('moneda_enviada_hidden');
        const displayEquivCobro = document.getElementById('equiv_cobro');
        const radiosMonedaCobro = document.getElementsByName('moneda_cobro');
        let currentStateMoneda = document.querySelector('input[name="moneda_cobro"]:checked') ? document.querySelector('input[name="moneda_cobro"]:checked').value : 'usd';
        if (inputMonedaEnviadaHidden) inputMonedaEnviadaHidden.value = currentStateMoneda;

        // Nuevo: Monto Pagado Hoy
        const inputPagoHoy = document.getElementById('monto_pagado_hoy');
        const displayEquivPagoHoy = document.getElementById('equiv_pago_hoy');

        function calcCobro(source = 'input') {
            if (!TASA_BCV) return;
            let esBs = document.getElementById('moneda_cobro_bs').checked;

            if (source === 'rate' && esBs) {
                // Si cambió la tasa y estamos en Bs, recalculamos Bs desde el ancla USD
                let usd = parseFloat(inputMontoCobroHidden.value) || 0;
                let bs = usd * TASA_BCV;
                inputMontoCobro.value = bs.toFixed(2).replace('.', ',');
                displayEquivCobro.textContent = `Equivalente: $${usd.toFixed(2)}`;
            } else {
                // Modo estándar: Leer input y actualizar ancla
                let val = parseFloat(inputMontoCobro.value.replace(',', '.')) || 0;
                if (esBs) {
                    let usd = val / TASA_BCV;
                    inputMontoCobroHidden.value = usd.toFixed(2);
                    displayEquivCobro.textContent = `Equivalente: $${usd.toFixed(2)}`;
                } else {
                    inputMontoCobroHidden.value = val.toFixed(2);
                    let bs = val * TASA_BCV;
                    displayEquivCobro.textContent = `Equivalente: Bs. ${bs.toFixed(2)}`;
                }
            }

            // Auto-llenar monto pagado hoy
            if (inputPagoHoy && !inputPagoHoy.dataset.manuallyChanged) {
                let usdVal = parseFloat(inputMontoCobroHidden.value) || 0;
                inputPagoHoy.value = usdVal.toFixed(2);
                if (displayEquivPagoHoy) {
                    displayEquivPagoHoy.textContent = `Equivalente: Bs. ${(usdVal * TASA_BCV).toFixed(2)}`;
                }
            }

            // Sincronizar todos los equivalentes del desglose
            updateAllDesgloseEquivalents(source === 'rate');
        }

        function updateAllDesgloseEquivalents(isRateChange = false) {
            if (!TASA_BCV) return;
            let esBs = document.getElementById('moneda_cobro_bs').checked;
            
            // Buscar todos los montos de desglose
            const inputsDesglose = document.querySelectorAll('#modalGenerarCobro .desglose-monto');
            inputsDesglose.forEach(input => {
                if (isRateChange && esBs && input.dataset.basePrice) {
                    // Si cambió la tasa y estamos en Bs, actualizamos el input desde el ancla USD
                    let base = parseFloat(input.dataset.basePrice) || 0;
                    input.value = (base * TASA_BCV).toFixed(2).replace('.', ',');
                }

                let val = parseFloat(input.value.replace(',', '.')) || 0;
                let container = input.parentElement.querySelector('.equiv-desglose');
                if (!container) return;

                if (val <= 0) {
                    container.textContent = '';
                    return;
                }

                if (esBs) {
                    let usd = val / TASA_BCV;
                    container.textContent = `Equivalente: $${usd.toFixed(2)}`;
                } else {
                    let bs = val * TASA_BCV;
                    container.textContent = `Equivalente: Bs. ${bs.toFixed(2)}`;
                }
            });
        }

        if (inputMontoCobro) {
            inputMontoCobro.addEventListener('input', (e) => {
                if (inputPagoHoy) inputPagoHoy.dataset.manuallyChanged = ''; // Reset flag if total changes
                calcCobro();
            });
            radiosMonedaCobro.forEach(r => r.addEventListener('change', function() {
                const newState = this.value;
                if (newState !== currentStateMoneda && TASA_BCV > 0) {
                    const isToBs = (newState === 'bs');
                    
                    // Convertir Input Principal
                    let currentMain = parseFloat(inputMontoCobro.value.replace(',', '.')) || 0;
                    if (currentMain > 0) {
                        inputMontoCobro.value = isToBs ? (currentMain * TASA_BCV).toFixed(2) : (currentMain / TASA_BCV).toFixed(2);
                    }
                    
                    // Convertir todos los Inputs del Desglose
                    const desgloseInputs = document.querySelectorAll('#modalGenerarCobro .desglose-monto');
                    desgloseInputs.forEach(input => {
                        let val = parseFloat(input.value.replace(',', '.')) || 0;
                        if (val > 0) {
                            input.value = isToBs ? (val * TASA_BCV).toFixed(2) : (val / TASA_BCV).toFixed(2);
                        }
                    // Mantener basePrice siempre en USD (como ancla de precio)
                    if (input.dataset.basePrice) {
                        // No convertir el ancla, solo usarla para recalcular el nuevo monto visible
                        let base = parseFloat(input.dataset.basePrice) || 0;
                        input.value = isToBs ? (base * TASA_BCV).toFixed(2).replace('.', ',') : base.toFixed(2);
                    }
                });

                currentStateMoneda = newState;
                if (inputMonedaEnviadaHidden) inputMonedaEnviadaHidden.value = currentStateMoneda;
                
                // Actualizar Label Visual Principal ($ -> Bs.)
                const labelPrincipal = document.getElementById('moneda_label_principal');
                if (labelPrincipal) {
                    labelPrincipal.textContent = (newState === 'bs') ? 'Bs.' : '$';
                }
            }
            calcCobro();
            if (typeof validarSumatoriaDesglose === 'function') {
                validarSumatoriaDesglose();
            }
        }));

            // Escuchar cambios en cualquier monto de desglose
            document.querySelector('#modalGenerarCobro').addEventListener('input', (e) => {
                if (e.target.classList.contains('desglose-monto')) {
                    // Actualizar ancla de precio (USD) al editar manualmente
                    let val = parseFloat(e.target.value.replace(',', '.')) || 0;
                    let esBs = document.getElementById('moneda_cobro_bs').checked;
                    if (TASA_BCV > 0) {
                        e.target.dataset.basePrice = esBs ? (val / TASA_BCV).toFixed(2) : val.toFixed(2);
                    }
                    
                    updateAllDesgloseEquivalents();
                    if (typeof validarSumatoriaDesglose === 'function') validarSumatoriaDesglose();
                }
            });

            // *** FIX DEFINITIVO: Capturar tasa y moneda justoantes de enviar el formulario ***
            // Esto garantiza que el backend siempre recibe la tasa correcta aunque el fetch
            // sea asíncrono o el campo oculto no se haya actualizado antes.
            document.getElementById('form_generar_cobro').addEventListener('submit', function() {
                const tasaHidden = document.getElementById('tasa_aplicada_hidden');
                const monedaHidden = document.getElementById('moneda_enviada_hidden');
                if (tasaHidden && TASA_BCV > 0) tasaHidden.value = TASA_BCV;
                if (monedaHidden) {
                    const radioChecked = document.querySelector('input[name="moneda_cobro"]:checked');
                    monedaHidden.value = radioChecked ? radioChecked.value : 'usd';
                }
            });
        }

        if (inputPagoHoy) {
            inputPagoHoy.addEventListener('input', function () {
                this.dataset.manuallyChanged = 'true';
                let val = parseFloat(this.value) || 0;
                if (displayEquivPagoHoy) {
                    displayEquivPagoHoy.textContent = `Equivalente: Bs. ${(val * TASA_BCV).toFixed(2)}`;
                }
            });
        }


        // === CONVERSIÓN EN MODAL PAGAR ===
        const inputMontoPagar = document.getElementById('input_monto_pagar');
        const inputMontoPagarHidden = document.getElementById('monto_pagado_hidden');
        const displayEquivPagar = document.getElementById('equiv_pagar');
        const radiosMonedaPagar = document.getElementsByName('moneda_pagar');

        $('#modalPagar').on('shown.bs.modal', function () {
            calcPagar();
        });

        function calcPagar() {
            if (!TASA_BCV) return;
            let val = parseFloat(inputMontoPagar.value) || 0;
            let esBs = document.getElementById('moneda_pagar_bs').checked;

            if (esBs) {
                let usd = val / TASA_BCV;
                if (inputMontoPagarHidden) inputMontoPagarHidden.value = usd.toFixed(2);
                displayEquivPagar.textContent = `Equivalente: $${usd.toFixed(2)}`;
            } else {
                if (inputMontoPagarHidden) inputMontoPagarHidden.value = val.toFixed(2);
                let bs = val * TASA_BCV;
                displayEquivPagar.textContent = `Equivalente: Bs. ${bs.toFixed(2)}`;
            }
        }

        if (inputMontoPagar) {
            inputMontoPagar.addEventListener('input', calcPagar);
            radiosMonedaPagar.forEach(r => r.addEventListener('change', calcPagar));
        }

        // Sincronizar tasa BCV justo antes de enviar el form "Pagar"
        const formPagarSimple = document.getElementById('form_pagar_simple');
        if (formPagarSimple) {
            formPagarSimple.addEventListener('submit', function () {
                const tasaField = document.getElementById('tasa_bcv_pagar_hidden');
                if (tasaField && TASA_BCV > 0) tasaField.value = TASA_BCV;
                // Asegurarse de que monto_pagado_hidden tenga el valor USD correcto
                calcPagar();
            });
        }

        // === TABLA UNIFICADA (server_process_mensualidades.php) ===
        window.tablaUnica = $('#tabla_mensualidades_unica').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 10,
            "language": {
                "sProcessing": "Procesando...",
                "sLengthMenu": "Mostrar _MENU_ registros",
                "sZeroRecords": "No se encontraron resultados",
                "sEmptyTable": "Ningún dato disponible en esta tabla",
                "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sSearch": "Buscar:",
                "oPaginate": {
                    "sFirst": "Primero",
                    "sLast": "Último",
                    "sNext": "Siguiente",
                    "sPrevious": "Anterior"
                }
            },
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "server_process_mensualidades.php",
                "type": "POST",
                "data": function (d) {
                    d.fecha_inicio = $('#fecha_inicio').val();
                    d.fecha_fin = $('#fecha_fin').val();
                    d.id_banco = $('#filtro_cuenta').val();
                    d.estado_sae = $('#filtro_sae').val();
                    d.estado_pago = $('#filtro_estado').val();
                    d.filtro_tipo = $('#filtro_tipo').val();
                    d.meses_mora = $('#filtro_meses_mora').val();
                    d.mes_cobrado = $('#filtro_mes_cobrado').val();
                    d.referencia = $('#filtro_referencia').val();
                    d.tab = $('#active_tab').val();
                    d.sSearch = d.search.value;
                },
                "error": function(xhr, error, thrown) {
                    console.error("Error Ajax en Tabla Mensualidades:", thrown);
                    console.log("Respuesta del servidor:", xhr.responseText);
                    // No mostrar el alert por defecto de DataTables para una mejor UX
                    if (xhr.status === 500) {
                        Swal.fire('Error del Servidor', 'El servidor tardó demasiado en responder o encontró un error interno. Intenta ajustar los filtros de fecha.', 'error');
                    }
                }
            },
            "columns": [
                { "data": 0 }, { "data": 1 }, { "data": 2 }, { "data": 3 }, { "data": 4 }, 
                { "data": 5 }, { "data": 6 }, { "data": 7 }, { "data": 8 }, { "data": 9 }, 
                { "data": 10 }, { "data": 11 },
                { "data": 12, "orderable": false, "searchable": false, "className": "text-end" }
            ],
            "createdRow": function(row, data, dataIndex) {
                // Asignar ID de cobro para doble clic
                if (data.id_cobro) {
                    $(row).attr('data-id', data.id_cobro);
                }

                // El UUID de grupo viene en el metadato (index id_grupo_pago del objeto)
                const uuid = data.id_grupo_pago;
                if (uuid) {
                    $(row).attr('data-grupo', uuid);
                    $(row).addClass('grupo-pago-row');
                    // Añadir un pequeño indicador visual al inicio de la primera celda
                    const indic = $('<span class="mt-1 d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background: #6c757d;" title="Pago Agrupado"></span>');
                    $(row).find('td:first').prepend(indic);
                }
            },
            "drawCallback": function(settings) {
                // Actualizar contadores desde la respuesta del servidor (Solo si se recalculan, evitado en paginación)
                if (settings.json && settings.json.tabCounts) {
                    const counts = settings.json.tabCounts;
                    if (counts.sae_pendiente !== -1) {
                        $('#count-general').text(counts.general || 0);
                        $('#count-sae-pendiente').text(counts.sae_pendiente || 0);
                        $('#count-sae-cargado').text(counts.sae_cargado || 0);
                    }
                }

                // Lógica de colores para grupos al dibujar la tabla
                const rows = $(this).find('tbody tr');
                let lastUuid = null;
                let lastRef = null;
                
                rows.each(function() {
                    // Group by UUID
                    const uuid = $(this).attr('data-grupo');
                    if (uuid) {
                        if (uuid === lastUuid) {
                            $(this).find('td:first span').css('background', '#0d6efd'); // Color azul para vinculación
                        }
                        lastUuid = uuid;
                    }
                    
                    // Group visually by Reference (col index 2)
                    const refCell = $(this).find('td').eq(2);
                    const currentRef = refCell.text().trim();
                    
                    if (currentRef && currentRef !== '-' && currentRef !== '') {
                        if (currentRef === lastRef) {
                            // Style the row to show it's grouped with the previous one
                            $(this).css('border-top', 'none');
                            $(this).prev('tr').css('border-bottom', 'none');
                            // Add a subtle left border to indicate grouping
                            $(this).find('td:first').css('border-left', '3px solid #6c757d');
                            $(this).prev('tr').find('td:first').css('border-left', '3px solid #6c757d');
                            
                            // Make the repeated reference text lighter
                            refCell.css('color', '#adb5bd');
                        } else {
                            // Reset style for new reference
                        }
                        lastRef = currentRef;
                    } else {
                        lastRef = null;
                    }
                });
            },
            "dom": '<"d-flex justify-content-between mb-3"lf>rt<"d-flex justify-content-between mt-3"ip>'
        });
        
        // --- DOBLE CLIC PARA EDITAR ---
        $('#tabla_mensualidades_unica tbody').on('dblclick', 'tr', function () {
            const id = $(this).attr('data-id');
            if (id) {
                confirmarEdicionCobro(id);
            }
        });

        $('#fecha_inicio, #fecha_fin, #filtro_cuenta, #filtro_sae, #filtro_estado, #filtro_tipo, #filtro_meses_mora, #filtro_mes_cobrado').on('change', function () {
            tablaUnica.ajax.reload();
        });

        // --- CAMBIO DE PESTAÑA ---
        $('#mensualidadesTabs button').on('click', function() {
            const tab = $(this).data('tab');
            $('#mensualidadesTabs button').removeClass('active');
            $(this).addClass('active');
            $('#active_tab').val(tab);
            
            // Recargar tabla con el nuevo filtro de pestaña
            window.tablaUnica.ajax.reload();
        });

        // --- CAMBIO DE PESTAÑA ---
        $('#mensualidadesTabs button').on('click', function() {
            const tab = $(this).data('tab');
            $('#mensualidadesTabs button').removeClass('active');
            $(this).addClass('active');
            $('#active_tab').val(tab);
            
            // Recargar tabla con el nuevo filtro de pestaña
            window.tablaUnica.ajax.reload();
        });

        // Trigger reload on reference type with delay
        let filterTimer;
        $('#filtro_referencia').on('input', function() {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(function() {
                tablaUnica.ajax.reload();
            }, 400); // 400ms delay to prevent too many requests
        });

        // Handler para cambio de estado SAE Plus
        $(document).on('change', '.sae-status-select', function () {
            const select = $(this);
            const id = select.data('id');
            const nuevoEstado = select.val();

            select.prop('disabled', true);

            fetch('actualizar_estado_sae.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id_cobro=${id}&estado_sae_plus=${nuevoEstado}`
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // Cambiar clase para feedback visual
                        if (nuevoEstado === 'CARGADO') {
                            select.removeClass('text-danger').addClass('text-success fw-bold');
                        } else {
                            select.removeClass('text-success fw-bold').addClass('text-danger');
                        }
                    } else {
                        alert('Error al actualizar estado: ' + res.message);
                    }
                })
                .catch(err => {
                    alert('Error técnico: ' + err);
                })
                .finally(() => {
                    select.prop('disabled', false);
                });
        });

        // === MODAL PAGAR LÓGICA ===
        $('#modalPagar').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var modal = $(this);
            modal.find('#id_cobro_modal').val(button.data('id'));
            document.getElementById('moneda_pagar_usd').checked = true;
            document.getElementById('input_monto_pagar').value = button.data('monto');
            if (inputMontoPagarHidden) inputMontoPagarHidden.value = button.data('monto');
            modal.find('#monto_display_modal').text('$' + parseFloat(button.data('monto')).toFixed(2));
            modal.find('#cliente_nombre_modal').text(button.data('nombre'));
            setTimeout(calcPagar, 100);
        });

        // === MODAL GENERAR COBRO (NUEVO DESGLOSE) LÓGICA ===
        const btnSubmitCobro = document.querySelector('#modalGenerarCobro form button[type="submit"]');
        const switchesDesglose = document.querySelectorAll('.desglose-switch');
        const inputsMontoDesglose = document.querySelectorAll('.desglose-monto');
        const NOMBRES_MESES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

        function actualizarMesesDinamicos(inputCant) {
            const row = inputCant.closest('.desglose-fields, .fila-extra');
            if (!row) return;
            const container = row.querySelector('.container-meses-dinamicos');
            if (!container) return;

            const cant = parseInt(inputCant.value) || 1;
            const isExtra = inputCant.name.includes('extra');
            // Nota: Para extras, usaremos un nombre de array que PHP pueda mapear por fila si es posible, 
            // pero para simplificar la compatibilidad con el backend actual, usaremos un truco de concatenación si es necesario o nombres array.
            const name = isExtra ? 'extra_meses_seleccionados[]' : 'meses_seleccionados_mensualidad[]';
            
            let html = '';
            const mesBaseIndex = new Date().getMonth();

            for (let i = 0; i < cant; i++) {
                const mesSugeridoIndex = (mesBaseIndex + i) % 12;
                html += `<select class="form-select form-select-sm mb-1" name="${name}" style="flex: 1 1 80px; min-width: 80px;">`;
                NOMBRES_MESES.forEach((m, idx) => {
                    const selected = (idx === mesSugeridoIndex) ? 'selected' : '';
                    html += `<option value="${m}" ${selected}>${m}</option>`;
                });
                html += `</select>`;
            }
            container.innerHTML = html;
        }

        function validarSumatoriaDesglose() {
            let totalDeclaradoInput = parseFloat(inputMontoCobro.value) || 0;
            let esBs = document.getElementById('moneda_cobro_bs').checked;
            let sumatoriaDesglose = 0;

            // Recorrer todos los switches activados
            switchesDesglose.forEach(sw => {
                if (sw.checked) {
                    const container = sw.closest('.rounded, .fila-extra, .bg-white').querySelector('.desglose-fields');
                    if (container) {
                        const montos = container.querySelectorAll('.desglose-monto');
                        montos.forEach(m => {
                            sumatoriaDesglose += parseFloat(m.value.replace(',', '.')) || 0;
                        });
                    }
                }
            });

            let restante = sumatoriaDesglose - totalDeclaradoInput;
            let sym = esBs ? 'Bs. ' : '$ ';

            // Actualizar UI
            document.getElementById('val_monto_total').textContent = totalDeclaradoInput.toFixed(2);
            document.getElementById('val_suma_desglose').textContent = sumatoriaDesglose.toFixed(2);
            document.getElementById('val_monto_restante').textContent = Math.abs(restante).toFixed(2);

            // Actualizar Símbolos en la franja negra
            document.querySelectorAll('.sym_res').forEach(el => el.textContent = sym);

            // Mostrar Equivalentes en la franja negra
            if (TASA_BCV > 0) {
                const equivDeclarado = document.getElementById('equiv_total_declarado');
                const equivDesglose = document.getElementById('equiv_total_desglose');
                const equivRestante = document.getElementById('equiv_restante');

                if (esBs) {
                    equivDeclarado.textContent = `Equiv: $${(totalDeclaradoInput / TASA_BCV).toFixed(2)}`;
                    equivDesglose.textContent = `Equiv: $${(sumatoriaDesglose / TASA_BCV).toFixed(2)}`;
                    equivRestante.textContent = `Equiv: $${(Math.abs(restante) / TASA_BCV).toFixed(2)}`;
                } else {
                    equivDeclarado.textContent = `Equiv: Bs. ${(totalDeclaradoInput * TASA_BCV).toFixed(2)}`;
                    equivDesglose.textContent = `Equiv: Bs. ${(sumatoriaDesglose * TASA_BCV).toFixed(2)}`;
                    equivRestante.textContent = `Equiv: Bs. ${(Math.abs(restante) * TASA_BCV).toFixed(2)}`;
                }
            }

            // Validar
            const badgeIndicador = document.getElementById('badge_indicador_suma');
            const hiddenIdContrato = document.getElementById('id_contrato_hidden_modal').value;
            const containerRestante = document.getElementById('container_restante');
            
            const tieneCliente = hiddenIdContrato && hiddenIdContrato !== "0";
            const tieneCargos = sumatoriaDesglose > 0;

            if (tieneCliente && tieneCargos) {
                btnSubmitCobro.disabled = false;
                if (badgeIndicador) {
                    if (Math.abs(restante) < 0.01) {
                        badgeIndicador.textContent = 'PAGO EXACTO';
                        badgeIndicador.className = 'badge bg-success text-white border-0 py-1 px-3';
                        containerRestante.className = 'fw-bold fs-5';
                        containerRestante.style.color = '#2ecc71'; // Verde brillante
                    } else if (restante > 0) {
                        badgeIndicador.textContent = 'SALDO PENDIENTE';
                        badgeIndicador.className = 'badge bg-danger text-white border-0 py-1 px-3';
                        containerRestante.className = 'fw-bold fs-5';
                        containerRestante.style.color = '#ff4d4d'; // Rojo brillante
                    } else {
                        badgeIndicador.textContent = 'SALDO A FAVOR';
                        badgeIndicador.className = 'badge bg-info text-dark border-0 py-1 px-3';
                        containerRestante.className = 'fw-bold fs-5';
                        containerRestante.style.color = '#00d1ff'; // Cyan brillante
                    }
                }
            } else {
                btnSubmitCobro.disabled = true;
                if (badgeIndicador) {
                    badgeIndicador.textContent = 'ESPERANDO SELECCIÓN';
                    badgeIndicador.className = 'badge bg-secondary text-white border-0 py-1 px-3';
                    containerRestante.className = 'fw-bold fs-5';
                    containerRestante.style.color = '#ffffff'; // Blanco para visibilidad máxima en espera
                }
            }
        }

        // Eventos para recalcular
        switchesDesglose.forEach(sw => {
            sw.addEventListener('change', function() {
                const container = this.closest('.rounded').querySelector('.desglose-fields');
                if (this.checked) {
                    container.classList.remove('d-none');
                    // Generar meses iniciales si es mensualidad
                    const inputCant = container.querySelector('.meses-cantidad');
                    if (inputCant) actualizarMesesDinamicos(inputCant);
                    
                    // Hacer inputs 'required'
                    container.querySelectorAll('input, select').forEach(inp => {
                        if (inp.type !== 'hidden' && !inp.classList.contains('extra-search')) {
                          inp.setAttribute('required', 'required');
                        }
                    });
                } else {
                    container.classList.add('d-none');
                    // Quitar 'required' y vaciar
                    container.querySelectorAll('input').forEach(inp => {
                        inp.removeAttribute('required');
                        if (inp.type !== 'hidden') inp.value = '';
                    });
                }
                validarSumatoriaDesglose();
            });
        });

        // Recalcular cuando se escribe en los montos del desglose
        document.addEventListener('input', function(e) {
            // Manejo de decimales con coma o punto
            if (e.target.classList.contains('decimal-input')) {
                // Permitir solo números, una coma o un punto
                let val = e.target.value;
                
                // Si intenta poner más de una coma o punto, limpiar
                const parts = val.split(/[.,]/);
                if (parts.length > 2) {
                    e.target.value = parts[0] + ',' + parts.slice(1).join('');
                }
                
                // Remover cualquier carácter que no sea número, coma o punto
                e.target.value = e.target.value.replace(/[^0-9.,]/g, '');
            }

            
            // Validación estricta para números enteros (meses)
            if (e.target.type === 'number' && e.target.classList.contains('form-control-sm')) {
                // Si es un campo de meses (entero positivo)
                if (e.target.step === '1') {
                    // Solo números
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                    
                    // Limitar a máximo 3 si tiene el atributo 'max="3"'
                    if (e.target.max === '3' && parseInt(e.target.value) > 3) {
                        e.target.value = 3;
                    }
                } else {
                    // Si es un campo de monto (decimal positivo)
                    // Permitimos solo números y un punto
                    let val = e.target.value;
                    if (val.includes('-')) {
                        e.target.value = val.replace('-', '');
                    }
                }
            }

            if(e.target.classList.contains('desglose-monto') || e.target.id === 'input_monto_cobro' || e.target.classList.contains('meses-cantidad')) {
                // Si cambiamos meses, recalculamos monto si hay precio base y actualizamos selectores
                if (e.target.classList.contains('meses-cantidad')) {
                    actualizarMesesDinamicos(e.target);
                    const row = e.target.closest('.rounded, .fila-extra');
                    const montoInput = row.querySelector('.desglose-monto');
                    if (montoInput && montoInput.dataset.basePrice) {
                        const base = parseFloat(montoInput.dataset.basePrice) || 0;
                        const cant = parseInt(e.target.value) || 0;
                        const esBs = document.getElementById('moneda_cobro_bs').checked;
                        const finalMonto = esBs ? (base * cant * TASA_BCV) : (base * cant);
                        montoInput.value = finalMonto.toFixed(2).replace('.', ',');
                    }
                }
                setTimeout(validarSumatoriaDesglose, 50); // Dar tiempo a que el hidden se asiente si es el principal
            }
        });

        // Bloqueo de teclas prohibidas (signo menos y tecla 'e')
        document.addEventListener('keydown', function(e) {
            if (e.target.type === 'number' && e.target.classList.contains('form-control-sm')) {
                if (e.key === '-' || e.key === 'e' || e.key === 'E' || e.key === '+') {
                    e.preventDefault();
                }
            }
        });

        // Dinamismo "Mensualidades Extra"
        const btnAddExtra = document.getElementById('btn_add_extra');
        const containerExtras = document.getElementById('contenedor_extras');
        if (btnAddExtra) {
            btnAddExtra.addEventListener('click', function() {
                const filaOriginal = containerExtras.querySelector('.fila-extra');
                const nuevaFila = filaOriginal.cloneNode(true);
                
                // Limpiar valores
                nuevaFila.querySelectorAll('input').forEach(inp => inp.value = (inp.classList.contains('meses-cantidad') ? '1' : ''));
                nuevaFila.querySelector('.container-meses-dinamicos').innerHTML = '';
                nuevaFila.querySelector('.extra-results').innerHTML = '';
                
                // Habilitar botón eliminar
                const btnRemove = nuevaFila.querySelector('.btn-remove-extra');
                btnRemove.disabled = false;
                btnRemove.addEventListener('click', function() {
                    nuevaFila.remove();
                    validarSumatoriaDesglose();
                });

                // Attach autocomplete to new row
                attachAutocompleteToRow(nuevaFila);

                containerExtras.appendChild(nuevaFila);
            });
        }

        // Attach autocomplete a la primera fila original
        if (containerExtras) {
            attachAutocompleteToRow(containerExtras.querySelector('.fila-extra'));
        }

        function attachAutocompleteToRow(row) {
            const searchInput = row.querySelector('.extra-search');
            const hiddenInput = row.querySelector('.extra-hidden');
            const resultsContainer = row.querySelector('.extra-results');
            const montoInput = row.querySelector('.desglose-monto');

            let timer;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                const q = this.value.trim();
                if (q.length < 3) { resultsContainer.innerHTML = ''; return; }
                timer = setTimeout(() => {
                    fetch(`buscar_contratos.php?q=${encodeURIComponent(q)}`)
                        .then(r => r.json())
                        .then(data => {
                            resultsContainer.innerHTML = '';
                            if (data.length) {
                                data.forEach(c => {
                                    const a = document.createElement('a');
                                    a.className = 'list-group-item list-group-item-action py-1 px-2 small';
                                    let extraPlanInfo = c.nombre_plan ? ` | <span class="badge bg-success-subtle text-success">$${parseFloat(c.monto_plan).toFixed(2)}</span>` : '';
                                    a.innerHTML = `<strong>ID ${c.id}</strong>: ${c.nombre_completo} <br><small class="text-muted">C.I.: ${c.cedula || 'N/A'}${extraPlanInfo}</small>`;
                                    a.onclick = (e) => {
                                        e.preventDefault();
                                        searchInput.value = `ID ${c.id}: ${c.nombre_completo}`;
                                        hiddenInput.value = c.id;
                                        resultsContainer.innerHTML = '';
                                        
                                        // Auto-rellenar monto si se tiene precio de plan
                                        if (c.monto_plan && parseFloat(c.monto_plan) > 0) {
                                            const switchExtra = document.getElementById('switch_extra');
                                            if (!switchExtra.checked) {
                                                switchExtra.checked = true;
                                                switchExtra.dispatchEvent(new Event('change'));
                                            }

                                            const esBs = document.getElementById('moneda_cobro_bs').checked;
                                            const montoSugerido = esBs ? (parseFloat(c.monto_plan) * TASA_BCV) : parseFloat(c.monto_plan);

                                            montoInput.value = montoSugerido.toFixed(2).replace('.', ',');
                                            montoInput.dataset.basePrice = c.monto_plan;
                                            montoInput.readOnly = true;
                                            const cantInput = row.querySelector('[name="extra_meses[]"]');
                                            cantInput.value = 1;
                                            cantInput.dispatchEvent(new Event('input', { bubbles: true }));
                                            
                                            // Mostrar nombre del plan en la fila
                                            const planInfoDiv = row.querySelector('.extra-plan-info');
                                            const planNameSpan = row.querySelector('.extra-plan-name');
                                            if (planInfoDiv && planNameSpan) {
                                                planNameSpan.textContent = c.nombre_plan || 'Sin Plan';
                                                planInfoDiv.classList.remove('d-none');
                                            }

                                            // Activar validacion
                                            const eventInput = new Event('input', { bubbles: true });
                                            montoInput.dispatchEvent(eventInput);
                                        } else {
                                            montoInput.readOnly = false;
                                            const planInfoDiv = row.querySelector('.extra-plan-info');
                                            if (planInfoDiv) planInfoDiv.classList.add('d-none');
                                        }
                                    };
                                    resultsContainer.appendChild(a);
                                });
                            } else {
                                resultsContainer.innerHTML = '<div class="list-group-item disabled py-1 px-2 small">Sin resultados</div>';
                            }
                        });
                }, 300);
            });
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.innerHTML = '';
                }
            });
        }

        // === MODAL ELIMINAR LÓGICA ===
        window.confirmarEliminarCobro = async function (id, nombre) {
            const proceeds = await solicitarClaveAdmin('Eliminar Cobro');
            if (proceeds) {
                // Check if can delete (contract status)
                fetch(`check_can_delete_payment.php?id=${id}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            if (res.can_delete) {
                                $('#id_cobro_eliminar').val(id);
                                $('#id_display_eliminar').text(id);
                                $('#cliente_nombre_eliminar').text(nombre);
                                // The password from 'solicitarClaveAdmin' is verified, 
                                // but we need it for the final POST 'elimina_cobro.php' 
                                // so we'll reuse the one from the successful validation if possible 
                                // or just prompt again. Since solicitarClaveAdmin is separate, 
                                // I'll modify solicitarClaveAdmin to return the password instead of just true/false.
                                // Actually, let's just use the hidden input and store the password there.
                                // I will update solicitarClaveAdmin to return the text if success.
                                
                                var modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
                                modal.show();
                            } else {
                                Swal.fire('No se puede eliminar', res.message, 'warning');
                            }
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    });
            }
        };

        $('#modalEliminar').on('show.bs.modal', function (event) {
            // Already handled by confirmingEliminarCobro for new flow
        });

        $('#formEliminar').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('elimina_cobro.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: res.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalEliminar')).hide();
                        tablaUnica.ajax.reload(null, false);
                    }, 500);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Error técnico al eliminar.', 'error');
            });
        });

        // === BUSCADOR AUTOCOMPLETE CONTRATOS ===
        const searchInput = document.getElementById('contrato_search_modal');
        const resultsContainer = document.getElementById('contrato_search_results_modal');
        const hiddenInput = document.getElementById('id_contrato_hidden_modal');

        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                const q = this.value.trim();
                if (q.length < 3) { resultsContainer.innerHTML = ''; return; }
                timer = setTimeout(() => {
                    fetch(`buscar_contratos.php?q=${encodeURIComponent(q)}`)
                        .then(r => r.json())
                        .then(data => {
                            resultsContainer.innerHTML = '';
                            if (data.length) {
                                data.forEach(c => {
                                    const a = document.createElement('a');
                                    a.className = 'list-group-item list-group-item-action';
                                    
                                    // 1. Numeración (#N) si tiene múltiples contratos
                                    let nroLabel = (c.total_contratos > 1) ? `<span class="bg-primary text-white px-2 py-0 rounded-pill me-1" style="font-size:0.7rem;">#${c.nro_orden}</span>` : '';
                                    
                                    // 2. Información de Pago (Último y Próximo)
                                    let pagoInfoHtml = '';
                                    const MESES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                                    let ultimoMes = null;
                                    
                                    if (c.ultimo_justif) {
                                        const match = c.ultimo_justif.match(/\[(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)\]/i);
                                        if (match) {
                                            ultimoMes = match[1];
                                            const idx = MESES.indexOf(ultimoMes);
                                            const proximoMes = MESES[(idx + 1) % 12];
                                            
                                            pagoInfoHtml = `
                                                <div class="mt-1 d-flex gap-1 flex-wrap">
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.7rem;">Último pago de mensualidad fue: ${ultimoMes}</span>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.7rem;">Próximo a cobrar: ${proximoMes}</span>
                                                </div>
                                            `;
                                        } else {
                                            pagoInfoHtml = `<div class="mt-1"><span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">Sin mensualidades registradas</span></div>`;
                                        }
                                    } else {
                                        pagoInfoHtml = `<div class="mt-1"><span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">Sin mensualidades registradas</span></div>`;
                                    }

                                    let extraPlanInfo = c.nombre_plan ? ` | <span class="badge bg-success-subtle text-success">$${parseFloat(c.monto_plan).toFixed(2)}</span>` : '';
                                    let creditBadge = (c.saldo_favor && parseFloat(c.saldo_favor) > 0) ? ` <span class="badge bg-success text-white" style="font-size: 0.65rem;"><i class="fas fa-coins me-1"></i>Crédito: $${parseFloat(c.saldo_favor).toFixed(2)}</span>` : '';
                                    
                                    a.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                ${nroLabel} <strong>ID ${c.id}: ${c.nombre_completo}</strong>
                                                <br><small class="text-muted">C.I.: ${c.cedula || 'N/A'}${extraPlanInfo}${creditBadge}</small>
                                                ${pagoInfoHtml}
                                            </div>
                                            <i class="fas fa-chevron-right text-light mt-2"></i>
                                        </div>
                                    `;
                                        a.onclick = (e) => {
                                            e.preventDefault();
                                            const idContrato = c.id;
                                            searchInput.value = `ID ${idContrato}: ${c.nombre_completo}`;
                                            hiddenInput.value = idContrato;
                                            resultsContainer.innerHTML = '';
                                            
                                            const infoSaldo = document.getElementById('info_saldo_favor');
                                            const valSaldo = document.getElementById('val_saldo_favor');
                                            const inputCredito = document.getElementById('input_credito_aplicado');
                                            const btnSaldo = document.getElementById('btn_aplicar_credito');
                                            
                                            // Resetear estado al cambiar contrato
                                            if (infoSaldo) infoSaldo.classList.add('d-none');
                                            if (inputCredito) inputCredito.value = 0;
                                            if (btnSaldo) {
                                                btnSaldo.innerHTML = '<i class="fas fa-check-circle me-1"></i> Usar Saldo';
                                                btnSaldo.classList.remove('btn-danger');
                                                btnSaldo.classList.add('btn-success');
                                            }
                                            
                                            // Usamos la info que ya viene en 'c' (nuestra búsqueda optimizada)
                                            if (parseFloat(c.saldo_favor) > 0) {
                                                if (valSaldo) valSaldo.textContent = parseFloat(c.saldo_favor).toFixed(2);
                                                if (infoSaldo) infoSaldo.classList.remove('d-none');
                                            } else {
                                                // Fallback si por alguna razón no vino en el search (fetch directo)
                                                fetch(`get_client_credit.php?id_contrato=${idContrato}`)
                                                    .then(r => r.json())
                                                    .then(data => {
                                                        if (data.success && data.saldo_favor > 0) {
                                                            if (valSaldo) valSaldo.textContent = data.saldo_favor.toFixed(2);
                                                            if (infoSaldo) infoSaldo.classList.remove('d-none');
                                                        }
                                                    });
                                            }
                                            // ------------------------------------

                                            // Auto-seleccionar Mensualidad basado en el plan del cliente (Monto Sugerido)
                                            if (c.monto_plan && parseFloat(c.monto_plan) > 0) {
                                            const switchMensualidad = document.getElementById('switch_mensualidad');
                                            if (!switchMensualidad.checked) {
                                                switchMensualidad.checked = true;
                                                switchMensualidad.dispatchEvent(new Event('change'));
                                            }
                                            const inputMonto = document.querySelector('[name="monto_mensualidad"]');
                                            const esBs = document.getElementById('moneda_cobro_bs').checked;
                                            const montoSugerido = esBs ? (parseFloat(c.monto_plan) * TASA_BCV) : parseFloat(c.monto_plan);
                                            
                                            inputMonto.value = montoSugerido.toFixed(2).replace('.', ',');
                                            inputMonto.dataset.basePrice = c.monto_plan; // El ancla siempre es USD
                                            inputMonto.readOnly = false; // A petición del usuario: Editable
                                            const inputCant = document.querySelector('[name="meses_mensualidad"]');
                                            inputCant.value = 1;
                                            inputCant.dispatchEvent(new Event('input', { bubbles: true }));
                                            
                                            // Mostrar nombre del plan
                                            const infoPlan = document.getElementById('info_plan_cliente');
                                            const valPlanNombre = document.getElementById('val_plan_nombre');
                                            if (infoPlan && valPlanNombre) {
                                                valPlanNombre.textContent = c.nombre_plan || 'Sin Plan';
                                                infoPlan.classList.remove('d-none');
                                            }

                                            // Activar validacion de sumatoria
                                            const eventInput = new Event('input', { bubbles: true });
                                            inputMonto.dispatchEvent(eventInput);
                                        } else {
                                            // Si no hay plan, liberar el campo por si acaso
                                            const inputMonto = document.querySelector('[name="monto_mensualidad"]');
                                            if (inputMonto) inputMonto.readOnly = false;
                                            const infoPlan = document.getElementById('info_plan_cliente');
                                            if (infoPlan) infoPlan.classList.add('d-none');
                                        }
                                    };
                                    resultsContainer.appendChild(a);
                                });
                            } else {
                                resultsContainer.innerHTML = '<div class="list-group-item disabled">Sin resultados</div>';
                            }
                        });
                }, 300);
            });
            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.innerHTML = '';
                }
            });
        }

        // === ÉXITO PARAMS ===
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('message') || urlParams.has('pago_exitoso') || urlParams.has('eliminacion_exitosa')) {
            var exitoModal = new bootstrap.Modal(document.getElementById('modalExito'));
            document.getElementById('modal_ex_mensaje_principal').textContent = urlParams.get('message') || 'Operación realizada con éxito.';
            exitoModal.show();
            if (history.replaceState) {
                var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({ path: newUrl }, '', newUrl);
            }
        }

        // Toggle Pago Inmediato ya no existe, se removió.
        // Forzar validación inicial para que el botón arranque bloqueado
        setTimeout(validarSumatoriaDesglose, 200);

        cargarBancos();
    });

    // === FUNCIONES GLOBALES ===
    window.aplicarSaldoAFavor = function() {
        const btn = document.getElementById('btn_aplicar_credito');
        const saldoDisponible = parseFloat(document.getElementById('val_saldo_favor').textContent) || 0;
        const inputMonto = document.getElementById('input_monto_cobro');
        const hiddenCredito = document.getElementById('input_credito_aplicado');
        const esBs = document.getElementById('moneda_cobro_bs').checked;
        const yaAplicado = (parseFloat(hiddenCredito.value) > 0);

        if (saldoDisponible <= 0) return;

        // Convertir monto visual según moneda actual
        let montoVisual = saldoDisponible;
        if (esBs) {
            montoVisual = saldoDisponible * TASA_BCV;
        }

        let montoActualInput = parseFloat(inputMonto.value.replace(',', '.')) || 0;

        if (!yaAplicado) {
            // MODO APLICAR: Sumamos al monto actual
            inputMonto.value = (montoActualInput + montoVisual).toFixed(2);
            hiddenCredito.value = saldoDisponible;
            
            btn.innerHTML = '<i class="fas fa-times-circle me-1"></i> Quitar Saldo';
            btn.classList.replace('btn-success', 'btn-danger');

            Swal.fire({
                title: 'Saldo Aplicado',
                text: `Se agregaron $${saldoDisponible.toFixed(2)} al pago actual.`,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            // MODO QUITAR: Restamos del monto actual
            inputMonto.value = Math.max(0, montoActualInput - montoVisual).toFixed(2);
            hiddenCredito.value = 0;
            
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Usar Saldo';
            btn.classList.replace('btn-danger', 'btn-success');

            Swal.fire({
                title: 'Saldo Removido',
                text: 'Se ha descontado el saldo a favor del monto pagado.',
                icon: 'info',
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Disparar evento para que se actualice el resumen y validaciones del modal
        inputMonto.dispatchEvent(new Event('input', { bubbles: true }));
    };
    function exportarExcel(tipo) {
        var fecha_inicio = $('#fecha_inicio').val();
        var fecha_fin = $('#fecha_fin').val();
        var id_banco = $('#filtro_cuenta').val();
        var filtro_estado = $('#filtro_estado').val();
        var filtro_tipo = $('#filtro_tipo').val();
        var mes_cobrado = $('#filtro_mes_cobrado').val();
        
        var url = 'exportar_mensualidades.php?tipo=' + tipo;
        if (fecha_inicio && fecha_fin) {
            url += '&fecha_inicio=' + encodeURIComponent(fecha_inicio) + '&fecha_fin=' + encodeURIComponent(fecha_fin);
        }
        if (tipo === 'filtrado') {
            if (id_banco) url += '&id_banco=' + encodeURIComponent(id_banco);
            if (filtro_estado) url += '&estado_pago=' + encodeURIComponent(filtro_estado);
            if (filtro_tipo) url += '&filtro_tipo=' + encodeURIComponent(filtro_tipo);
            if (mes_cobrado) url += '&mes_cobrado=' + encodeURIComponent(mes_cobrado);
        }
        window.open(url, '_blank');
    }

    function cargarBancos() {
        fetch('json_bancos_api.php?action=get&limit=100') // Solicitar más límite para traer todos en el modal
            .then(r => r.json())
            .then(result => {
                const bancosArray = result.data || [];
                const filtro = document.getElementById('filtro_cuenta');
                const modalSelect = document.getElementById('select_banco_modal');
                const cobroSelect = document.getElementById('select_banco_cobro');
                const editSelect = document.getElementById('edit_select_banco');

                const valFiltro = filtro ? filtro.value : '';
                const valModal = modalSelect ? modalSelect.value : '';
                const valCobro = cobroSelect ? cobroSelect.value : '';
                const valEdit = editSelect ? editSelect.value : '';

                if (filtro) filtro.innerHTML = '<option value="">Todas las Cuentas</option>';
                if (modalSelect) modalSelect.innerHTML = '<option value="">Seleccione...</option>';
                if (cobroSelect) cobroSelect.innerHTML = '<option value="">Seleccione...</option>';
                if (editSelect) editSelect.innerHTML = '<option value="">Seleccione...</option>';

                bancosArray.forEach(b => {
                    const nombreLabel = b.nombre_banco + (b.numero_cuenta ? ' (' + b.numero_cuenta.slice(-4) + ')' : '');
                    if (filtro) filtro.add(new Option(nombreLabel, b.id_banco));
                    if (modalSelect) modalSelect.add(new Option(nombreLabel, b.id_banco));
                    if (cobroSelect) cobroSelect.add(new Option(nombreLabel, b.id_banco));
                    if (editSelect) editSelect.add(new Option(nombreLabel, b.id_banco));
                });
                if (valFiltro && filtro) filtro.value = valFiltro;
                if (valModal && modalSelect) modalSelect.value = valModal;
                if (valCobro && cobroSelect) cobroSelect.value = valCobro;
                if (valEdit && editSelect) editSelect.value = valEdit;
            });
    }

    async function solicitarClaveAdmin(titulo = 'Confirmar Acción') {
        // Fix for Bootstrap modal focus trap
        const focusHandler = (e) => {
            if (e.target.closest(".swal2-container")) {
                e.stopImmediatePropagation();
            }
        };
        document.addEventListener('focusin', focusHandler, true);

        const { value: password } = await Swal.fire({
            title: titulo,
            input: 'password',
            inputLabel: 'Ingrese la clave de administrador para proceder',
            inputPlaceholder: 'Clave de seguridad',
            inputAttributes: {
                autocapitalize: 'off',
                autocorrect: 'off'
            },
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
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
                // Store password for subsequent form submissions if needed
                $('#delete_password_hidden').val(password);
                return true;
            }
            Swal.fire('Error', 'Clave incorrecta', 'error');
        }
        return false;
    }

    window.clearCaptureUpload = function() {
        document.getElementById('capture_upload').value = '';
        document.getElementById('capture_preview_container').classList.add('d-none');
        document.getElementById('capture_placeholder').classList.remove('d-none');
        document.getElementById('capture_preview_img').src = '';
        const statusDiv = document.getElementById('ocr_status');
        if (statusDiv) {
            statusDiv.classList.add('d-none');
            statusDiv.innerHTML = '<i class="fas fa-sync fa-spin"></i> Procesando OCR...';
        }
    };


    // === OCR TESSERACT.JS LOGIC ===
    const scriptTesseract = document.createElement('script');
    scriptTesseract.src = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";
    document.head.appendChild(scriptTesseract);

    document.getElementById('capture_upload').addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) {
            clearCaptureUpload();
            return;
        }

        // Mostrar Previsualización
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('capture_preview_img').src = event.target.result;
            document.getElementById('capture_preview_container').classList.remove('d-none');
            document.getElementById('capture_placeholder').classList.add('d-none');
        };
        reader.readAsDataURL(file);

        const statusDiv = document.getElementById('ocr_status');
        statusDiv.classList.remove('d-none');
        statusDiv.classList.replace('text-success', 'text-info');
        statusDiv.classList.replace('text-danger', 'text-info');
        statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analizando comprobante OCR, por favor espera...';

        try {
            const worker = await Tesseract.createWorker('spa'); 
            const ret = await worker.recognize(file);
            const text = ret.data.text;
            console.log("OCR TEXT:", text);
            
            let foundMonto = false;
            let foundRef = false;
            let foundBanco = false;

            // 1. Regex de Monto (Busca numero seguido o precedido de Bs)
            const montoRegex = /([\d\.\,]+)\s*Bs/i;
            const montoMatch = text.match(montoRegex);
            if (montoMatch) {
                let numStr = montoMatch[1].replace(/\./g, '').replace(',', '.');
                let amt = parseFloat(numStr);
                if (!isNaN(amt)) {
                    document.getElementById('moneda_cobro_bs').checked = true;
                    document.getElementById('moneda_cobro_bs').dispatchEvent(new Event('change'));
                    
                    document.getElementById('input_monto_cobro').value = amt;
                    document.getElementById('input_monto_cobro').dispatchEvent(new Event('input', { bubbles: true }));
                    foundMonto = true;
                }
            }

            // 2. Regex de Referencia Avanzado (Blindado contra encabezados y texto)
            const keywords = [
                'Nro\\.?\\s+de\\s+referencia',
                'N[uú]mero\\s+de\\s+referencia',
                'Nro\\.?\\s+de\\s+operaci[oó]n',
                'N[uú]mero\\s+de\\s+operaci[oó]n',
                '\\bReferencia\\b',
                '\\bOperaci[oó]n\\b',
                '\\bOperacion\\b',
                '\\bConfirmaci[oó]n\\b',
                '\\bAprobaci[oó]n\\b',
                '\\bRef\\b\\.?',
            ].join('|');
            
            // Excluimos "Comprobante de operación" o "Transferencias" para no confundir con etiquetas de datos
            if (text.toLowerCase().includes('comprobante de operaci')) {
                // Si el texto tiene encabezados, intentamos buscar la etiqueta que esté más abajo
                // (Tesseract a veces lee de arriba a abajo)
            }

            // Buscamos la keyword y capturamos SOLO el bloque numérico que le sigue
            // [^\d\n]* salta cualquier ruido que no sea número hasta encontrar el primer dígito
            const refRegex = new RegExp(`(?:${keywords})(?:\\s*:)?\\s*[^0-9\\n]*(\\d[\\d\\s\\.]{5,20})`, 'i');
            const refMatch = text.match(refRegex);
            
            if (refMatch) {
                // Limpiar espacios y puntos internos
                let cleanRef = refMatch[1].trim().replace(/[\s\.]/g, '').toUpperCase();
                // Validar longitud mínima
                if (cleanRef.length >= 6) {
                    const refInput = document.getElementById('input_ref_generar_cobro');
                    if (refInput) refInput.value = cleanRef;
                    foundRef = true;
                }
            } 
            
            if (!foundRef) {
                // Intento B (Fallback): Número largo (8-25 dígitos) que puede tener ceros iniciales
                const flatText = text.replace(/\n/g, ' '); 
                const allNumbers = flatText.match(/(?:\d[\s\.]*){8,25}/g);
                if (allNumbers && allNumbers.length > 0) {
                    const sortedNumbers = allNumbers
                        .map(n => n.replace(/[\s\.]/g, ''))
                        .filter(n => n.length >= 8)
                        .sort((a,b) => b.length - a.length);
                        
                    if (sortedNumbers.length > 0) {
                        const refInput = document.getElementById('input_ref_generar_cobro');
                        if (refInput) refInput.value = sortedNumbers[0];
                        foundRef = true;
                    }
                }
            }

            // 3. Regex Banco Destino (Super Flexible)
            const selectBanco = document.getElementById('select_banco_cobro');
            
            // a) Buscar por los últimos 4 dígitos de la cuenta que recibe
            const destinoRegex = /Destino[^\d]*(\d{4})/i;
            const destMatch = text.match(destinoRegex);
            
            if (destMatch && selectBanco) {
                let codBanco = destMatch[1]; // ej. 9811
                for (let i = 0; i < selectBanco.options.length; i++) {
                    if (selectBanco.options[i].text.includes(codBanco)) {
                        selectBanco.selectedIndex = i;
                        foundBanco = true;
                        break;
                    }
                }
            }

            // b) Buscar por nombre (Mapeo Inteligente ignorando espacios)
            if (!foundBanco && selectBanco) {
                let textLimpio = text.toUpperCase().replace(/\s+/g, ''); // Quitamos espacios al capture
                
                for (let i = 0; i < selectBanco.options.length; i++) {
                    let optTarget = selectBanco.options[i].text.toUpperCase().replace(/\s+/g, ''); // Ej: BANCAAMIGA(0000)
                    
                    let targetKeywords = [];
                    if (optTarget.includes('BANCAMIGA') || optTarget.includes('BANCAAMIGA') || optTarget.includes('AMIGA')) targetKeywords.push('BANCAMIGA', 'BANCAAMIGA');
                    if (optTarget.includes('BANESCO')) targetKeywords.push('BANESCO');
                    if (optTarget.includes('MERCANTIL')) targetKeywords.push('MERCANTIL');
                    if (optTarget.includes('PROVINCIAL')) targetKeywords.push('PROVINCIAL');
                    if (optTarget.includes('VENEZUELA')) targetKeywords.push('VENEZUELA', 'BDV');
                    if (optTarget.includes('BNC') || optTarget.includes('CREDITO')) targetKeywords.push('BNC', 'NACIONALDECREDITO');
                    
                    for (let kw of targetKeywords) {
                        if (textLimpio.includes(kw)) {
                            selectBanco.selectedIndex = i;
                            foundBanco = true;
                            break; // Rompe búsqueda de keywords
                        }
                    }
                    if (foundBanco) break; // Rompe loop de opciones
                }
            }

            let resultMsg = "OCR Finalizado. ";
            resultMsg += foundRef ? "Ref OK. " : "";
            resultMsg += foundBanco ? "Banco OK. " : "";

            statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + resultMsg;
            statusDiv.classList.replace('text-info', 'text-success');

            await worker.terminate();

        } catch (err) {
            console.error(err);
            statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error al analizar imagen.';
            statusDiv.classList.replace('text-info', 'text-danger');
        }
    });

    // === LÓGICA MODAL EDITAR POTENCIADO ===
    const NOMBRES_MESES_EDIT = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    const editSwitches = document.querySelectorAll('.desglose-switch-edit');
    const editInputsMonto = document.querySelectorAll('.desglose-monto-edit');
    const editBtnSubmit = document.querySelector('#modalEditarCobro form button[type="submit"]');

    function editActualizarMesesDinamicos(input) {
        const parentRow = input.closest('.row'); 
        if (!parentRow) return;
        const container = parentRow.querySelector('.container-meses-dinamicos');
        if (!container) return;
        
        const cant = parseInt(input.value) || 0;
        const isExtra = !!input.closest('.fila-extra-edit');
        const name = isExtra ? 'extra_meses_seleccionados[]' : 'meses_seleccionados_mensualidad[]';
        
        let html = '';
        const mesBaseIndex = new Date().getMonth();

        for (let i = 0; i < cant; i++) {
            const mesSugeridoIndex = (mesBaseIndex + i) % 12;
            html += `<select class="form-select form-select-sm mb-1" name="${name}" style="flex: 1 1 80px; min-width: 80px;">`;
            NOMBRES_MESES_EDIT.forEach((m, idx) => {
                const selected = (idx === mesSugeridoIndex) ? 'selected' : '';
                html += `<option value="${m}" ${selected}>${m}</option>`;
            });
            html += `</select>`;
        }
        container.innerHTML = html;
    }

    function editValidarSumatoria() {
        let totalDeclarado = parseFloat(document.getElementById('edit_monto_total_hidden').value) || 0;
        let sumatoria = 0;

        const allEditSwitches = document.querySelectorAll('.desglose-switch-edit');
        allEditSwitches.forEach(sw => {
            if (sw.checked) {
                const row = sw.closest('.rounded, .row, .bg-white, .fila-extra-edit');
                const montos = row.querySelectorAll('.desglose-monto-edit');
                montos.forEach(m => {
                    sumatoria += parseFloat(m.value.replace(',', '.')) || 0;
                });
            }
        });

        let restante = sumatoria - totalDeclarado;

        document.getElementById('edit_val_monto_total').textContent = totalDeclarado.toFixed(2);
        document.getElementById('edit_val_suma_desglose').textContent = sumatoria.toFixed(2);
        
        const spanRestante = document.getElementById('edit_val_monto_restante');
        const containerRestante = document.getElementById('edit_container_restante');
        spanRestante.textContent = Math.abs(restante).toFixed(2);

        const badge = document.getElementById('edit_badge_indicador_suma');
        
        if (sumatoria > 0) {
            editBtnSubmit.disabled = false;
            if (Math.abs(restante) < 0.01) {
                badge.textContent = 'PAGO CUADRADO';
                badge.className = 'badge bg-success text-white border-0 py-1 px-3';
                containerRestante.className = 'fw-bold text-success fs-5';
            } else if (restante > 0) {
                badge.textContent = 'SOBREGIRO DE DESGLOSE';
                badge.className = 'badge bg-danger text-white border-0 py-1 px-3';
                containerRestante.className = 'fw-bold text-danger fs-5';
            } else {
                badge.textContent = 'FALTA DISTRIBUIR';
                badge.className = 'badge bg-warning text-dark border-0 py-1 px-3';
                containerRestante.className = 'fw-bold text-warning fs-5';
            }
        } else {
            editBtnSubmit.disabled = true;
            badge.textContent = 'SIN CONCEPTOS';
            badge.className = 'badge bg-secondary text-white border-0 py-1 px-3';
            containerRestante.className = 'fw-bold text-muted fs-5';
        }
    }

    // --- Lógica Extras Edit ---
    const editBtnAddExtra = document.getElementById('edit_btn_add_extra');
    const editContainerExtras = document.getElementById('edit_contenedor_extras');
    
    function editAttachAutocompleteToRow(row) {
        const searchInput = row.querySelector('.extra-search');
        const hiddenInput = row.querySelector('.extra-hidden');
        const resultsContainer = row.querySelector('.extra-results');
        const montoInput = row.querySelector('.desglose-monto-edit');

        let timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 3) { resultsContainer.innerHTML = ''; return; }
            timer = setTimeout(() => {
                fetch(`buscar_contratos.php?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(data => {
                        resultsContainer.innerHTML = '';
                        data.forEach(c => {
                            const a = document.createElement('a');
                            a.className = 'list-group-item list-group-item-action py-1 px-2 small';
                            a.innerHTML = `<strong>ID ${c.id}</strong>: ${c.nombre_completo}`;
                            a.onclick = (e) => {
                                e.preventDefault();
                                searchInput.value = `ID ${c.id}: ${c.nombre_completo}`;
                                hiddenInput.value = c.id;
                                resultsContainer.innerHTML = '';
                                if (c.monto_plan) montoInput.value = parseFloat(c.monto_plan).toFixed(2);
                                editValidarSumatoria();
                            };
                            resultsContainer.appendChild(a);
                        });
                    });
            }, 300);
        });
    }

    if (editBtnAddExtra) {
        editBtnAddExtra.addEventListener('click', function() {
            crearFilaExtraEdit();
        });
    }

    function crearFilaExtraEdit(data = null) {
        const div = document.createElement('div');
        div.className = 'fila-extra-edit mb-3 border-bottom pb-3';
        div.innerHTML = `
            <div class="position-relative mb-2">
                <label class="small text-muted fw-bold mb-1">Usuario / Contrato</label>
                <input type="text" class="form-control form-control-sm extra-search" placeholder="ID, Nombre..." autocomplete="off" value="${data ? 'ID '+data.id_contrato+': '+data.nombre_cliente : ''}">
                <input type="hidden" name="extra_contrato[]" class="extra-hidden" value="${data ? data.id_contrato : ''}">
                <div class="list-group shadow-lg position-absolute w-100 extra-results" style="z-index: 1080;"></div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-3">
                    <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Monto $</label>
                    <input type="text" class="form-control form-control-sm desglose-monto-edit decimal-input" name="extra_monto[]" placeholder="0,00" value="${data ? parseFloat(data.monto_total).toFixed(2) : ''}">
                </div>
                <div class="col-2">
                    <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Cant.</label>
                    <input type="number" step="1" min="1" max="12" class="form-control form-control-sm meses-cantidad-edit" name="extra_meses[]" value="${data ? 1 : 1}">
                </div>
                <div class="col-5">
                     <label class="small text-muted fw-bold mb-1" style="font-size: 0.65rem;">Meses</label>
                     <div class="container-meses-dinamicos d-flex flex-wrap gap-1"></div>
                </div>
                <div class="col-2 text-end">
                    <button type="button" class="btn btn-sm text-danger border-0 btn-remove-extra-edit"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
        div.querySelector('.btn-remove-extra-edit').addEventListener('click', function() {
            div.remove();
            editValidarSumatoria();
        });
        const cantInp = div.querySelector('.meses-cantidad-edit');
        cantInp.addEventListener('input', function() {
            editActualizarMesesDinamicos(this);
        });
        
        editAttachAutocompleteToRow(div);
        editContainerExtras.appendChild(div);

        // Si hay data, intentar poblar meses
        if (data && data.justificacion) {
            const desc = data.justificacion.toUpperCase();
            const systemPart = desc.split(' - ')[0];
            const match = systemPart.match(/(\d+)\s*MES\/ES/) || systemPart.match(/(\d+)\s*MESES/);
            if (match) {
                cantInp.value = match[1];
                editActualizarMesesDinamicos(cantInp);
                // Mapear meses seleccionados
                const selects = div.querySelectorAll('select[name="extra_meses_seleccionados[]"]');
                const foundMonths = NOMBRES_MESES_EDIT.filter(m => desc.includes(m.toUpperCase()));
                selects.forEach((sel, idx) => {
                    if (foundMonths[idx]) sel.value = foundMonths[idx];
                });
            } else {
                editActualizarMesesDinamicos(cantInp);
            }
        } else {
            editActualizarMesesDinamicos(cantInp);
        }

        editValidarSumatoria();
    }

    document.getElementById('edit_switch_extra').addEventListener('change', function() {
        const container = document.getElementById('edit_fields_extra');
        if (this.checked) {
            container.classList.remove('d-none');
            if (editContainerExtras.children.length === 0) crearFilaExtraEdit();
        } else {
            container.classList.add('d-none');
            editContainerExtras.innerHTML = '';
        }
        editValidarSumatoria();
    });

    const editSwListener = (sw) => {
        sw.addEventListener('change', function() {
            // No procesar extra aquí, tiene su propio listener para manejar el contenedor de filas
            if (this.id === 'edit_switch_extra') return;

            const row = this.closest('.rounded, .row, .bg-white');
            if (!row) return;
            const container = row.querySelector('.desglose-fields-edit');
            if (this.checked) {
                if(container) container.classList.remove('d-none');
            } else {
                if(container) container.classList.add('d-none');
                const montoInput = row.querySelector('.desglose-monto-edit');
                if(montoInput) {
                    montoInput.value = '';
                }
            }
            editValidarSumatoria();
        });
    };
    document.querySelectorAll('.desglose-switch-edit').forEach(editSwListener);

    // --- Listener global para inputs de monto y cantidad en edición ---
    document.getElementById('modalEditarCobro').addEventListener('input', function(e) {
        if (e.target.classList.contains('desglose-monto-edit') || e.target.id === 'edit_input_monto_total') {
            if (e.target.id === 'edit_input_monto_total') {
                let val = parseFloat(e.target.value.replace(',', '.')) || 0;
                document.getElementById('edit_monto_total_hidden').value = val.toFixed(2);
            }
            editValidarSumatoria();
        }
        if (e.target.classList.contains('meses-cantidad-edit')) {
            editActualizarMesesDinamicos(e.target);
            editValidarSumatoria();
        }
    });

    // Delegación para cambios en selects de meses para recalcular si fuera necesario
    document.getElementById('modalEditarCobro').addEventListener('change', function(e) {
        if (e.target.name === 'meses_seleccionados_mensualidad[]' || e.target.name === 'extra_meses_seleccionados[]') {
            editValidarSumatoria();
        }
    });

    // Capture Upload Edit Preview
    document.getElementById('edit_capture_upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('edit_capture_preview_img').src = event.target.result;
                document.getElementById('edit_capture_preview_img').classList.remove('d-none');
                document.getElementById('edit_capture_empty').classList.add('d-none');
                document.getElementById('btn_clear_edit_capture').classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('btn_clear_edit_capture').addEventListener('click', function() {
        document.getElementById('edit_capture_upload').value = '';
        this.classList.add('d-none');
        // Restaurar imagen original si existe o dejar vacío
        const originalPath = document.getElementById('edit_id_cobro').dataset.originalCapture;
        if (originalPath) {
            document.getElementById('edit_capture_preview_img').src = '../../' + originalPath;
        } else {
            document.getElementById('edit_capture_preview_img').src = '';
            document.getElementById('edit_capture_preview_img').classList.add('d-none');
            document.getElementById('edit_capture_empty').classList.remove('d-none');
        }
    });

    // Helper para limpiar justificación técnica (corchetes) antes de editar
    function cleanJustificationForEdit(justif) {
        if (!justif) return '';
        let clean = justif;
        // Eliminar recursivamente cualquier bloque entre corchetes al inicio
        // Esto limpia [EXTRA] [ID: 49] [CUALQUIER_OTRO] de forma segura
        while (clean.match(/^\[[^\]]+\]\s*/)) {
            clean = clean.replace(/^\[[^\]]+\]\s*/, '');
        }
        // Eliminar restos de descripciones estándar que no estén en corchetes
        clean = clean.replace(/^-?\s*Mensualidad \(\d+ mes\/es\)\s*/i, '');
        clean = clean.replace(/^-?\s*Pago de Terceros\s*\(ID:\s*\d+\)\s*/i, ''); 
        // Eliminar guiones y espacios iniciales sobrantes
        return clean.replace(/^[-\s]+/, '').trim();
    }

    // Modal de Edición de Cobro (desde gestión general)
    window.confirmarEdicionCobro = async function (id_cobro) {
        const proceeds = await solicitarClaveAdmin('Modificar Cobro');
        if (!proceeds) return;

        const loader = document.getElementById('edit_modal_loader');
        const content = document.getElementById('edit_modal_content');
        
        loader.classList.remove('d-none');
        content.classList.add('d-none');

        var modal = new bootstrap.Modal(document.getElementById('modalEditarCobro'));
        modal.show();

        // Reset inputs
        const formEscapado = document.getElementById('formEditarCobro');
        formEscapado.reset();
        editContainerExtras.innerHTML = ''; // Limpiar extras
        document.getElementById('edit_switch_extra').checked = false;
        document.getElementById('edit_fields_extra').classList.add('d-none');

        const allEditSw = document.querySelectorAll('.desglose-switch-edit');
        allEditSw.forEach(sw => {
            sw.checked = false;
            const row = sw.closest('.rounded, .row, .bg-white, .fila-extra-edit');
            if (!row) return;
            const container = row.querySelector('.desglose-fields-edit');
            if(container) container.classList.add('d-none');
            const montoInput = row.querySelector('.desglose-monto-edit');
            if(montoInput) {
                montoInput.value = '';
            }
            const mesesCont = row.querySelector('.container-meses-dinamicos');
            if(mesesCont) mesesCont.innerHTML = '';
        });

        // Poblar bancos si está vacío (aunque ya vienen en PHP, nos aseguramos)
        const selectBanco = document.getElementById('edit_select_banco');
        if (selectBanco.options.length <= 1) {
            const bancosJson = <?php echo json_encode($bancos_data); ?>;
            bancosJson.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id_banco;
                opt.textContent = b.nombre_banco;
                selectBanco.appendChild(opt);
            });
        }

        fetch(`get_cobro_data.php?id=${id_cobro}&t=${new Date().getTime()}`)
            .then(r => r.json())
            .then(res => {
                loader.classList.add('d-none');
                if (res.success) {
                    const d = res.data;
                    const concepts = res.all_concepts || [];

                    $('#edit_id_cobro').val(d.id_cobro);
                    $('#edit_id_cobro').attr('data-original-capture', d.capture_pago || '');
                    $('#edit_id_cobro_display').text(d.id_cobro);
                    $('#edit_id_grupo_pago').val(d.id_grupo_pago || '');
                    $('#edit_cliente_info_static').text(`${d.nombre_cliente} (Contrato #${d.id_contrato})`);
                    $('#edit_id_contrato_hidden').val(d.id_contrato);
                    $('#edit_estado').val(d.estado);
                    // Calcular monto total del grupo para el encabezado
                    let montoTotalGrupo = 0;
                    concepts.forEach(c => {
                        montoTotalGrupo += parseFloat(c.monto_total) || 0;
                    });

                    $('#edit_input_monto_total').val(montoTotalGrupo.toFixed(2));
                    $('#edit_monto_total_hidden').val(montoTotalGrupo.toFixed(2));
                    if (d.fecha_vencimiento) $('#edit_fecha_vencimiento').val(d.fecha_vencimiento.substring(0, 10));
                    const f_pago = (d.fecha_pago || d.fecha_emision || '');
                    if (f_pago) $('#edit_fecha_pago').val(f_pago.substring(0, 10));
                    $('#edit_input_referencia').val(d.referencia_pago);
                    $('#edit_select_banco').val(d.id_banco);
                    $('#edit_autorizado_por').val(d.autorizado_por);
                    $('#edit_pago_justificacion').val(cleanJustificationForEdit(d.justificacion));

                    // Poblar Desglose con Agregador
                    let aggregated = {
                        mensualidad: { monto: 0, cant: 0, meses: [] },
                        instalacion: { monto: 0 },
                        equipo: { monto: 0 },
                        prorrateo: { monto: 0 }
                    };

                    concepts.forEach(c => {
                        const desc = (c.justificacion || '').toUpperCase();
                        const montoVal = parseFloat(c.monto_cargado || c.monto_total) || 0;

                        // 1. Prioridad: ¿Es un EXTRA por ID o etiqueta?
                        if (String(c.id_contrato) !== String(d.id_contrato) || desc.includes('[EXTRA]')) {
                            const swExtra = document.getElementById('edit_switch_extra');
                            if (swExtra && !swExtra.checked) {
                                swExtra.checked = true;
                                swExtra.dispatchEvent(new Event('change'));
                            }
                            crearFilaExtraEdit(c);
                        } 
                        // 2. ¿Es una categoría específica por etiqueta?
                        else if (desc.includes('[MENSUALIDAD]') || desc.includes('MENSUAL') || desc.match(/\bMES\b/)) {
                            aggregated.mensualidad.monto += montoVal;
                            const systemPart = desc.split(' - ')[0];
                            const match = systemPart.match(/(\d+)\s*MES\/ES/) || systemPart.match(/(\d+)\s*MESES/);
                            aggregated.mensualidad.cant += match ? parseInt(match[1]) : 1;
                            // Extraer mes (Busca [Mes] o simplemente Mes en la justificación)
                            NOMBRES_MESES_EDIT.forEach(m => {
                                const regexMes = new RegExp('\\b' + m + '\\b', 'i');
                                if (regexMes.test(desc)) {
                                    aggregated.mensualidad.meses.push(m);
                                }
                            });
                        } else if (desc.includes('[INSTALACION]') || desc.includes('INSTALACIÓN')) {
                            aggregated.instalacion.monto += montoVal;
                        } else if (desc.includes('[PRORRATEO]') || desc.includes('PRORRATEO')) {
                            aggregated.prorrateo.monto += montoVal;
                        } else if (desc.includes('[EQUIPO]') || desc.includes('MATERIAL') || desc.includes('EQUIPOS')) {
                            aggregated.equipo.monto += montoVal;
                        } 
                        // 3. Fallback: Si es el mismo contrato pero no tiene etiqueta clara
                        else {
                            aggregated.mensualidad.monto += montoVal;
                            aggregated.mensualidad.cant += 1;
                        }
                    });

                    // Helper para activar switches nativamente y asegurar visibilidad
                    const activateSwitch = (id, val) => {
                        const sw = document.getElementById(id);
                        if (sw) {
                            if (!sw.checked) {
                                sw.checked = true;
                                sw.dispatchEvent(new Event('change'));
                            }
                            const row = sw.closest('.rounded, .row, .bg-white');
                            const input = row.querySelector('.desglose-monto-edit');
                            if (input) input.value = val.toFixed(2);
                        }
                    };

                    // Aplicar montos agregados
                    if (aggregated.mensualidad.monto > 0) {
                        activateSwitch('edit_switch_mensualidad', aggregated.mensualidad.monto);
                        const cantInp = document.querySelector('#modalEditarCobro input[name="meses_mensualidad"]');
                        if (cantInp) {
                            cantInp.value = aggregated.mensualidad.cant;
                            editActualizarMesesDinamicos(cantInp);
                        }
                        
                        setTimeout(() => {
                            const selects = document.querySelectorAll('#edit_fields_mensualidad select[name="meses_seleccionados_mensualidad[]"]');
                            selects.forEach((sel, idx) => {
                                if (aggregated.mensualidad.meses[idx]) {
                                     sel.value = aggregated.mensualidad.meses[idx];
                                }
                            });
                        }, 100);
                    }
                    if (aggregated.instalacion.monto > 0) activateSwitch('edit_switch_instalacion', aggregated.instalacion.monto);
                    if (aggregated.equipo.monto > 0) activateSwitch('edit_switch_equipo', aggregated.equipo.monto);
                    if (aggregated.prorrateo.monto > 0) activateSwitch('edit_switch_prorrateo', aggregated.prorrateo.monto);

                    // Final sum refresh
                    editValidarSumatoria();

                    // Capture Preview
                    const img = document.getElementById('edit_capture_preview_img');
                    const empty = document.getElementById('edit_capture_empty');
                    
                    // Actualizar cuadro resumen
                    document.getElementById('edit_val_monto_total').textContent = parseFloat(d.monto_total).toFixed(2);
                    if (d.capture_pago) {
                        let path = d.capture_pago;
                        if (path.startsWith('../../')) path = path.replace('../../', '');
                        img.src = '../../' + path;
                        img.classList.remove('d-none');
                        empty.classList.add('d-none');
                    } else {
                        img.src = '';
                        img.classList.add('d-none');
                        empty.classList.remove('d-none');
                    }

                    content.classList.remove('d-none');
                    setTimeout(editValidarSumatoria, 100);
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
    };

    function formatConcept(justif, montoVal = 0) {
        if (!justif) return 'Sin descripción';
        // Limpiar etiquetas [TAG] y formatear de forma más humana
        let clean = justif
            .replace(/\[MENSUALIDAD\]/gi, 'Mensualidad')
            .replace(/\[INSTALACION\]/gi, 'Instalación')
            .replace(/\[EQUIPOS\]/gi, 'Equipos')
            .replace(/\[ABONO\]/gi, 'Abono')
            .replace(/\[PRORRATEO\]/gi, 'Prorrateo')
            .replace(/\[EXTRA\]/gi, 'Pago Tercero')
            .replace(/\[|\]/g, '') // Quitar corchetes restantes
            .split(' - ')[0] // Tomar solo la parte principal del concepto
            .trim();
        
        // Si el concepto es "Mensualidad Mayo", solo mostrar "Mensualidad Mayo"
        // Si hay redundancia (ej. "Mensualidad Mensualidad Mayo"), limpiar.
        let conceptoFinal = clean.replace(/Mensualidad\s+Mensualidad/gi, 'Mensualidad');
        
        // Inferir el plan en base al monto si es de tipo Mensualidad
        if (conceptoFinal.toLowerCase().includes('mensualidad') && montoVal > 0 && typeof planesDisponibles !== 'undefined') {
            for (let plan of planesDisponibles) {
                let planMonto = parseFloat(plan.monto);
                if (planMonto > 0) {
                    let meses = montoVal / planMonto;
                    // Si el monto pagado es múltiplo exacto de este plan
                    if (Math.abs(meses - Math.round(meses)) < 0.01 && Math.round(meses) > 0) {
                        conceptoFinal += ` (${plan.nombre_plan})`;
                        break;
                    }
                }
            }
        }
        
        return conceptoFinal;
    }

    window.verHistorialPago = function(idContrato, nombreCliente) {
        document.getElementById('hist_cliente_nombre').textContent = nombreCliente;
        const tbody = document.getElementById('hist_table_body');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando historial...</td></tr>';
        
        var modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
        modal.show();

        fetch(`get_historial_pagos.php?id_contrato=${idContrato}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    tbody.innerHTML = '';
                    if (res.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No se encontraron pagos registrados.</td></tr>';
                    } else {
                        res.data.forEach(p => {
                            const justif = p.justificacion || '';
                            let conceptosArr = [];

                            if (justif.includes('[MENSUALIDAD]')) conceptosArr.push('Mensualidad');
                            if (justif.includes('[INSTALACION]')) conceptosArr.push('Instalación');
                            if (justif.includes('[EQUIPOS]')) conceptosArr.push('Equipos');
                            if (justif.includes('[ABONO]')) conceptosArr.push('Abono');
                            if (justif.includes('[PRORRATEO]')) conceptosArr.push('Prorrateo');

                            let concepto = '';
                            if (conceptosArr.length > 0) {
                                concepto = conceptosArr.join(' + ');
                            } else if (justif && !justif.includes('||')) {
                                concepto = 'Cargo Manual / Otro';
                            } else if (p.nombre_plan) {
                                concepto = 'Mensualidad / ' + p.nombre_plan;
                            } else {
                                concepto = 'Varios / Otros';
                            }

                            let displayJustif = justif.split(' || ').join(' | ');
                            let justifHtml = displayJustif || '-';
                            if (justifHtml.length > 55) {
                                justifHtml = `<span title="${justifHtml}" style="cursor:help">${justifHtml.substring(0, 52)}...</span>`;
                            }

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${p.id_cobro}</td>
                                <td>${p.fecha_emision}</td>
                                <td>${p.fecha_vencimiento}</td>
                                <td>${p.fecha_pago}</td>
                                <td><span class="badge bg-secondary opacity-75">${concepto}</span></td>
                                <td class="fw-bold text-success">$${parseFloat(p.monto_total).toFixed(2)}</td>
                                <td>${p.referencia_pago || '-'}</td>
                                <td class="small text-muted" style="max-width: 250px; white-space: normal;">${justifHtml}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">${res.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error al cargar datos.</td></tr>';
            });
    };

    window.verJustificacion = function (idCobro) {
        const loader = document.getElementById('justif_loader');
        const content = document.getElementById('justif_content');
        
        loader.classList.remove('d-none');
        content.classList.add('d-none');

        var modal = new bootstrap.Modal(document.getElementById('modalJustificacion'));
        modal.show();

        fetch(`get_justificacion_data.php?id_cobro=${idCobro}`)
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
                    document.getElementById('justif_texto').innerHTML = d.justificacion.replace(/\n/g, '<br>');
                    
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
                                <td><span class="fw-bold text-dark">${formatConcept(c.justificacion, montoVal)}</span></td>
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
                        // Limpiar ruta si empieza con ../../ (depende de donde se guarde)
                        let path = d.capture_pago;
                        if (path.startsWith('../../')) path = path.replace('../../', '');
                        
                        img.src = '../../' + path; // Ajustar relativo al root o carpeta actual
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
    };

    // === SUBMIT EDITAR COBRO POTENCIADO ===
    document.getElementById('formEditarCobro').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fd = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;

        // Validar que el monto coincida (opcional, pero recomendado)
        const total = parseFloat(document.getElementById('edit_monto_total_hidden').value) || 0;
        const suma = parseFloat(document.getElementById('edit_val_suma_desglose').textContent) || 0;
        
        if (Math.abs(total - suma) > 0.01) {
            Swal.fire('Atención', 'El desglose no coincide con el monto total reportado. Por favor ajuste los valores.', 'warning');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Actualizando...';

        fetch('actualizar_cobro.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({
                    title: '¡Actualizado!',
                    text: res.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                bootstrap.Modal.getInstance(document.getElementById('modalEditarCobro')).hide();
                if (window.tablaUnica) {
                    window.tablaUnica.ajax.reload(null, false);
                } else {
                    // Fallback extremo si por alguna razón la referencia global falla
                    $('#tabla_mensualidades_unica').DataTable().ajax.reload(null, false);
                }
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error técnico al actualizar el cobro.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
</script>

<?php require_once '../includes/layout_foot.php'; ?>