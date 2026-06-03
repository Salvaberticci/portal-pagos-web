<?php
/**
 * Página principal que muestra la tabla de registros
 */
require '../conexion.php';

$path_to_root = "../../";
$page_title = "Gestión de Contratos";
$breadcrumb = ["Admin"];
$back_url = "../menu.php";
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';
?>

<!-- DataTables CSS -->
<link href="<?php echo $path_to_root; ?>css/datatables.min.css" rel="stylesheet">
<!-- Chart.js & Datalabels -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<style>
    /* Estilos para tabla ancha y DataTables */
    .dataTables_wrapper {
        width: 100%;
        padding: 0;
        background: transparent;
    }

    .table-responsive {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid var(--border-glass);
        overflow-x: auto;
    }

    #mitabla {
        border-collapse: collapse !important;
        width: auto !important;
        min-width: 100%;
        background: transparent !important;
    }

    #mitabla thead th {
        background: var(--table-header-bg) !important;
        color: var(--text-muted) !important;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        padding: 15px 12px !important;
        border: none !important;
        border-bottom: 2px solid var(--border-glass) !important;
        white-space: nowrap !important;
        text-align: center !important;
    }

    table.dataTable tbody tr.odd, table.dataTable tbody tr.even, table.dataTable tbody tr {
        background-color: var(--bg-card) !important;
        transition: all 0.2s ease;
    }

    table.dataTable tbody tr:hover, table.dataTable tbody tr:hover > td.sorting_1 {
        background-color: var(--table-header-bg) !important;
    }

    table.dataTable tbody td, table.dataTable tbody tr > td.sorting_1 {
        font-size: 0.85rem;
        color: var(--text-main) !important;
        padding: 12px !important;
        background-color: transparent !important;
        border: none !important;
        border-bottom: 1px solid var(--table-border) !important;
        white-space: nowrap !important;
        text-align: center !important;
    }

    /* Stats Cards */
    .stat-card {
        background: var(--bg-card) !important;
        backdrop-filter: blur(var(--glass-blur));
        -webkit-backdrop-filter: blur(var(--glass-blur));
        border: 1px solid var(--border-glass) !important;
        border-radius: 20px !important;
        box-shadow: var(--card-shadow) !important;
    }

    .stat-icon-wrapper {
        width: 65px;
        height: 65px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        font-size: 1.8rem;
        background: var(--table-header-bg);
        border: 1px solid var(--border-glass);
    }

    .stat-number {
        color: var(--text-main) !important;
    }

    /* Botones Glass */
    .btn-glass {
        background: var(--table-header-bg) !important;
        border: 1px solid var(--border-glass) !important;
        color: var(--text-main) !important;
        font-weight: 600 !important;
        backdrop-filter: blur(var(--glass-blur));
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .btn-glass:hover {
        background: var(--table-border) !important;
        border-color: var(--primary) !important;
        color: var(--primary) !important;
        transform: translateY(-2px);
    }

    .dataTables_filter input {
        width: 250px !important;
    }
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">
        <div class="glass-panel animate-fade mb-4">
            <!-- ── HEADER: TÍTULO Y BOTÓN PRINCIPAL ── -->
            <div class="card-header border-0 pt-4 px-4 pb-2">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold text-gradient mb-1">Gestión de Contratos</h4>
                        <p class="text-muted small mb-0">Control centralizado de suscripciones y servicios</p>
                    </div>
                    <a href="nuevo.php" class="btn btn-premium btn-sm d-flex align-items-center gap-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nuevo Contrato</span>
                    </a>
                </div>

                <!-- ── FILA 2: BARRA DE HERRAMIENTAS SECUNDARIA ── -->
                <div class="d-flex flex-wrap gap-2 align-items-center pt-3 border-top border-white border-opacity-5">

                    
                    <!-- Reportes y Datos -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalStats" id="btnOpenStats">
                            <i class="fa-solid fa-chart-bar text-success"></i>
                            <span class="d-none d-lg-inline">Estadísticas</span>
                        </button>
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            onclick="exportExcel()">
                            <i class="fa-solid fa-file-excel text-success"></i>
                            <span class="d-none d-lg-inline">Exportar</span>
                        </button>
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                            <i class="fa-solid fa-file-import text-success"></i>
                            <span class="d-none d-lg-inline">Importar</span>
                        </button>
                    </div>

                    <div class="vr mx-1 opacity-25"></div>

                    <!-- Configuración Técnica -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalTipos">
                            <i class="fa-solid fa-network-wired text-info"></i>
                            <span class="d-none d-xl-inline">Conexiones</span>
                        </button>
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalTiposInstalacion">
                            <i class="fa-solid fa-tools text-info"></i>
                            <span class="d-none d-xl-inline">Instalaciones</span>
                        </button>
                    </div>

                    <div class="vr mx-1 opacity-25"></div>

                    <!-- Catálogos y Personal -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalInstaladores">
                            <i class="fa-solid fa-helmet-safety text-muted"></i>
                            <span class="d-none d-xl-inline">Instaladores</span>
                        </button>
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalVendedores">
                            <i class="fa-solid fa-user-tie text-muted"></i>
                            <span class="d-none d-xl-inline">Vendedores</span>
                        </button>
                    </div>

                    <div class="vr mx-1 opacity-25"></div>

                    <!-- Configuración General -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalProrrateo">
                            <i class="fa-solid fa-dollar-sign text-warning"></i>
                            <span class="d-none d-lg-inline">Planes</span>
                        </button>
                        <button type="button" class="btn btn-glass d-flex align-items-center gap-2"
                            data-bs-toggle="modal" data-bs-target="#modalUbicaciones">
                            <i class="fa-solid fa-map-marked-alt text-primary"></i>
                            <span class="d-none d-lg-inline">Ubicaciones</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body px-4">
                <!-- Filtros Extra -->
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0 text-primary" style="border-color: var(--input-border);">
                                <i class="fa-solid fa-filter"></i>
                            </span>
                            <select id="filter_empty" class="form-select border-start-0" style="border-color: var(--input-border);">
                                <option value="">Todos los registros</option>
                                <option value="1">Vacío: SAR (Fecha)</option>
                                <option value="2">Vacío: Cédula</option>
                                <option value="3">Vacío: Cliente</option>
                                <option value="4">Vacío: Plan ($)</option>
                                <option value="5">Vacío: Municipio</option>
                                <option value="6">Vacío: Parroquia</option>
                                <option value="7">Vacío: Dirección</option>
                                <option value="8">Vacío: Telf. 1</option>
                                <option value="9">Vacío: Telf. 2</option>
                                <option value="10">Vacío: Correo</option>
                                <option value="11">Vacío: Correo (Alt)</option>
                                <option value="12">Vacío: F. Instalación</option>
                                <option value="13">Vacío: Medio Pago</option>
                                <option value="14">Vacío: Monto Pagar</option>
                                <option value="15">Vacío: Monto Pagado</option>
                                <option value="16">Vacío: Días Prorrateo</option>
                                <option value="17">Vacío: Monto Prorr. ($)</option>
                                <option value="18">Vacío: Observaciones</option>
                                <option value="19">Vacío: Tipo Conex.</option>
                                <option value="20">Vacío: Tipo Instal.</option>
                                <option value="21">Vacío: MAC/Serial</option>
                                <option value="22">Vacío: IP ONU</option>
                                <option value="23">Vacío: Caja NAP</option>
                                <option value="24">Vacío: Puerto NAP</option>
                                <option value="25">Vacío: NAP TX (dBm)</option>
                                <option value="26">Vacío: ONU RX (dBm)</option>
                                <option value="27">Vacío: Dist. Drop (m)</option>
                                <option value="28">Vacío: Instalador FTTH</option>
                                <option value="29">Vacío: Instalador Radio</option>
                                <option value="30">Vacío: Evidencia Fibra</option>
                                <option value="31">Vacío: Punto Acceso</option>
                                <option value="38">Vacío: SAE Plus (ID)</option>
                                <option value="37">Vacío: Vendedor</option>
                                <option value="40">Vacío: OLT</option>
                                <option value="41">Vacío: PON</option>
                                <option value="32">Vacío: Val. Conex. (dBm)</option>
                                <option value="33">Vacío: Precinto ODN</option>
                                <option value="34">Vacío: Foto</option>
                                <option value="35">Vacío: Firma Cliente</option>
                                <option value="36">Vacío: Firma Técnico</option>
                                <option value="42">Vacío: Estado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-glass w-100" id="btn_clear_main_filters">
                            <i class="fa-solid fa-eraser me-2"></i>Limpiar
                        </button>
                    </div>
                </div>

                <!-- Dashboard Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card border-0 shadow-sm overflow-hidden">
                            <div class="card-body d-flex align-items-center p-4">
                                <div class="stat-icon-wrapper text-primary me-4">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div>
                                    <p class="text-muted small fw-bold mb-1 text-uppercase" style="letter-spacing: 1.5px;">Total Clientes</p>
                                    <h2 class="mb-0 fw-bold stat-number" id="statTotalClients">...</h2>
                                </div>
                            </div>
                            <div class="position-absolute end-0 bottom-0 opacity-10 p-3">
                                <i class="fa-solid fa-users fa-4x"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card border-0 shadow-sm overflow-hidden">
                            <div class="card-body d-flex align-items-center p-4">
                                <div class="stat-icon-wrapper text-success me-4">
                                    <i class="fa-solid fa-file-contract"></i>
                                </div>
                                <div>
                                    <p class="text-muted small fw-bold mb-1 text-uppercase" style="letter-spacing: 1.5px;">Total Contratos</p>
                                    <h2 class="mb-0 fw-bold stat-number" id="statTotalContracts">...</h2>
                                </div>
                            </div>
                            <div class="position-absolute end-0 bottom-0 opacity-10 p-3">
                                <i class="fa-solid fa-file-contract fa-4x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Wrapper responsive para scroll horizontal -->
                <div class="table-responsive">
                    <table class="display table table-hover w-100" id="mitabla">
                        <thead>
                            <tr>
                                <!-- 0 ID (Hidden) -->
                                <th>ID</th>

                                <!-- Info Cliente -->
                                <th title="Marca temporal de registro">SAR</th>
                                <th>Cédula</th>
                                <th>Cliente</th>
                                <th>Plan ($)</th>
                                <th>Municipio</th>
                                <th>Parroquia</th>
                                <th>Dirección</th>
                                <th>Telf. 1</th>
                                <th>Telf. 2</th>
                                <th>Correo</th>
                                <th>Correo (Alt)</th>

                                <!-- Instalacion y Cobro -->
                                <th>F. Instalación</th>
                                <th>Medio Pago</th>
                                <th>Monto Pagar</th>
                                <th>Monto Pagado</th>
                                <th>Días Prorrateo</th>
                                <th>Monto Prorr. ($)</th>
                                <th title="Observaciones">Observ.</th>

                                <!-- Tecnicos -->
                                <th>Tipo Conex.</th>
                                <th>Tipo Instal.</th>
                                <th>MAC/Serial</th>
                                <th>IP ONU</th>
                                <th>Caja NAP</th>
                                <th>Puerto NAP</th>
                                <th>NAP TX (dBm)</th>
                                <th>ONU RX (dBm)</th>
                                <th>Dist. Drop (m)</th>
                                <th>Instalador FTTH</th>
                                <th>Punto Acceso</th>
                                <th>Val. Conex. (dBm)</th>

                                <!-- Cierre -->
                                <th title="Instalador Radio">Instalador Radio</th>
                                <th title="Evidencia">Evidencia</th>
                                <th title="Sugerencias/Observaciones">Sugerencias</th>
                                <th>Precinto ODN</th>
                                <th>Foto</th>
                                <th>Firma Cliente</th>
                                <th>Firma Técnico</th>

                                <!-- EXTRAS -->
                                <th class="table-info">Vendedor (Edit)</th>
                                <th class="table-info">SAE Plus (Edit)</th>
                                <th>Plan</th>
                                <th>OLT</th>
                                <th>PON</th>
                                <th>Estado</th>

                                <!-- Acciones -->
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4 text-center">
            <a href="../menu.php" class="btn btn-outline-secondary px-4">
                <i class="fa-solid fa-arrow-left me-2"></i>Volver al Menú
            </a>
        </div>
    </div>
    <!-- MODAL GESTION TIPOS INSTALACION -->
    <div class="modal fade" id="modalTipos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-tags me-2"></i>Tipos de Conexión</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group mb-3" id="listTipos" style="max-height: 400px; overflow-y: auto;">
                        <!-- Items generados por JS -->
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="newTipo" placeholder="Nuevo Tipo (Ej. FTTH, RADIO)">
                        <button class="btn btn-success" type="button" id="btnAddTipo">
                            <i class="fa-solid fa-plus" id="iconTipoAction"></i>
                        </button>
                        <button class="btn btn-secondary d-none" type="button" id="btnCancelEditTipo">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <small class="text-muted me-auto">Los cambios se guardan automáticamente.</small>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GESTION TIPOS INSTALACION NUEVO -->
    <div class="modal fade" id="modalTiposInstalacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-wrench me-2"></i>Tipos de Instalación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group mb-3" id="listTiposInstalacion" style="max-height: 400px; overflow-y: auto;">
                        <!-- Items generados por JS -->
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="newTipoInstalacion" placeholder="Nuevo Tipo (Ej. Nivel 1, Mudanza)">
                        <button class="btn btn-success" type="button" id="btnAddTipoInstalacion">
                            <i class="fa-solid fa-plus" id="iconTipoInstalacionAction"></i>
                        </button>
                        <button class="btn btn-secondary d-none" type="button" id="btnCancelEditTipoInstalacion">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <small class="text-muted me-auto">Los cambios se guardan automáticamente.</small>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GESTION INSTALADORES -->
    <div class="modal fade" id="modalInstaladores" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-helmet-safety me-2"></i>Gestionar Instaladores
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group mb-3" id="listInstaladores" style="max-height: 400px; overflow-y: auto;">
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="newInstalador" placeholder="Nombre Instalador">
                        <button class="btn btn-success" id="btnAddInstalador" type="button" onclick="addInstalador()">
                            <i class="fa-solid fa-plus" id="iconInstaladorAction"></i>
                        </button>
                        <button class="btn btn-secondary d-none" id="btnCancelEditInstalador" type="button"
                            onclick="cancelEditInstalador()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GESTION VENDEDORES -->
    <div class="modal fade" id="modalVendedores" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-tie me-2"></i>Gestionar Vendedores</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group mb-3" id="listVendedores" style="max-height: 400px; overflow-y: auto;"></div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="newVendedor" placeholder="Nombre Vendedor">
                        <button class="btn btn-success" id="btnAddVendedor" type="button" onclick="addVendedor()"><i
                                class="fa-solid fa-plus" id="iconVendedorAction"></i></button>
                        <button class="btn btn-secondary d-none" id="btnCancelEditVendedor" type="button"
                            onclick="cancelEditVendedor()"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GESTION PLANES PRORRATEO -->
    <div class="modal fade" id="modalProrrateo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Planes Prorrateo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group mb-3" id="listProrrateo" style="max-height: 400px; overflow-y: auto;"></div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="newPlanNombre" placeholder="Nombre (Ej. 100 Mbps)">
                        <input type="number" step="0.01" min="0" class="form-control" id="newPlanPrecio"
                            placeholder="Precio ($)">
                        <button class="btn btn-success" type="button" id="btnAddPlanProrrateo"
                            onclick="addPlanProrrateo()" title="Agregar">
                            <i class="fa-solid fa-plus" id="iconPlanAction"></i>
                        </button>
                        <button class="btn btn-secondary d-none" type="button" id="btnCancelEditPlan"
                            onclick="cancelEditPlan()" title="Cancelar Edición">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL GESTION UBICACIONES -->
    <div class="modal fade" id="modalUbicaciones" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-map-location-dot me-2"></i>Gestionar Ubicaciones</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- COLUMNA MUNICIPIOS -->
                        <div class="col-md-5 border-end">
                            <h6 class="text-primary fw-bold mb-3">Municipios</h6>
                            <div class="list-group mb-3" id="listMunicipios"
                                style="max-height: 300px; overflow-y: auto;">
                                <!-- Items generados por JS -->
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" id="newMunicipio" placeholder="Nuevo Municipio">
                                <button class="btn btn-success" type="button" id="btnAddMunicipio"
                                    title="Agregar Municipio">
                                    <i class="fa-solid fa-plus" id="iconMunAction"></i>
                                </button>
                                <button class="btn btn-secondary d-none" type="button" id="btnCancelEditMun"
                                    title="Cancelar Edición">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- COLUMNA PARROQUIAS -->
                        <div class="col-md-7">
                            <h6 class="text-info fw-bold mb-3" id="titleParroquias">Parroquias (Seleccione un Municipio)
                            </h6>
                            <div class="list-group mb-3" id="listParroquias"
                                style="max-height: 300px; overflow-y: auto;">
                                <!-- Items generados por JS -->
                                <div class="text-center text-muted p-3">Seleccione un municipio para ver sus parroquias
                                </div>
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" id="newParroquia" placeholder="Nueva Parroquia"
                                    disabled>
                                <button class="btn btn-success" type="button" id="btnAddParroquia" disabled
                                    title="Agregar Parroquia">
                                    <i class="fa-solid fa-plus" id="iconParAction"></i>
                                </button>
                                <button class="btn btn-secondary d-none" type="button" id="btnCancelEditPar"
                                    title="Cancelar Edición">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <small class="text-muted me-auto">Los cambios se guardan automáticamente.</small>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ESTADÍSTICAS -->
    <div class="modal fade" id="modalStats" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" style="max-width: 97vw; width: 97vw;">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-chart-bar me-2"></i>Estadísticas [VERSIÓN 3 -
                        BARRAS]
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Filtros -->
                    <div class="glass-panel mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Filtros de Búsqueda</h6>
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label small">Fecha Inicio</label>
                                    <input type="date" class="form-control" id="statStartDate">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Fecha Fin</label>
                                    <input type="date" class="form-control" id="statEndDate">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Instalador</label>
                                    <select class="form-select" id="statInstaller">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Vendedor</label>
                                    <select class="form-select" id="statVendor">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Tipo de Conexión</label>
                                    <select class="form-select" id="statContractType">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Tipo Instalación</label>
                                    <select class="form-select" id="statInstallType">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-12 text-end">
                                    <button class="btn btn-outline-secondary me-2" id="btnResetStats">
                                        <i class="fa-solid fa-eraser me-2"></i>Limpiar Filtros
                                    </button>
                                    <button class="btn btn-primary" id="btnFilterStats">
                                        <i class="fa-solid fa-filter me-2"></i>Aplicar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resultados Gráficas -->
                    <div class="row g-4">
                        <!-- FILA 1: Ventas y Ubicación -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success text-center mb-3"><i
                                    class="fa-solid fa-user-tie me-2"></i>Ventas por Vendedor</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:1500px; width:100%">
                                <canvas id="chartVendor"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-info text-center mb-3"><i
                                    class="fa-solid fa-map-location-dot me-2"></i>Contratos por Ubicación</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:800px; width:100%">
                                <canvas id="chartLocation"></canvas>
                            </div>
                        </div>

                        <!-- FILA 2: Tipos -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-warning text-center mb-3"><i
                                    class="fa-solid fa-list-check me-2"></i>Tipo de Instalación</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:1000px; width:100%">
                                <canvas id="chartType"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-secondary text-center mb-3"><i
                                    class="fa-solid fa-tower-broadcast me-2"></i>Tipo de Conexión</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:800px; width:100%">
                                <canvas id="chartConnection"></canvas>
                            </div>
                        </div>

                        <!-- FILA 3: SAE Plus (Ancho completo) -->
                        <div class="col-md-12">
                            <h6 class="fw-bold fs-5 text-center mt-3 mb-3"><i
                                    class="fa-solid fa-database me-2"></i>Desglose Carga en SAE Plus (FTTH / Radio)</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:500px; width:100%">
                                <canvas id="chartSae"></canvas>
                            </div>
                        </div>

                        <!-- FILA 4: Mensuales e Instaladores (Al final) -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger text-center mb-3"><i
                                    class="fa-solid fa-calendar-days me-2"></i>Instalaciones Mensuales</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:2000px; width:100%">
                                <canvas id="chartMonthly"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary text-center mb-3"><i
                                    class="fa-solid fa-helmet-safety me-2"></i>Instalaciones por Instalador</h6>
                            <div class="chart-container shadow-sm border rounded p-3"
                                style="position: relative; height:1200px; width:100%">
                                <canvas id="chartInstaller"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" id="btnExportPDF"><i
                            class="fa-solid fa-file-pdf me-2"></i>Exportar PDF</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts para PDF Export (Usando PHP Backend) -->
    <script>
        function formatDescriptiveDateJS(dateStr) {
            if (!dateStr || dateStr === '0000-00-00' || dateStr === 'null') return 'Sin Fecha';
            const date = new Date(dateStr + 'T12:00:00'); // Use noon to avoid timezone shifts
            if (isNaN(date.getTime())) return dateStr;

            const months = [
                "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
            ];

            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear();

            return `${day} de ${month} del ${year}`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Cargar Dashboard Stats
            fetchStatsDashboard();

            // Cargar Listas para Filtros al abrir modal
            var modalStats = document.getElementById('modalStats');
            modalStats.addEventListener('show.bs.modal', function () {
                fetchStatsLists();
                fetchModalStats(); // Load charts immediately

                // Restricción de fecha: No permitir fechas futuras
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('statStartDate').setAttribute('max', today);
                document.getElementById('statEndDate').setAttribute('max', today);
            });

            // Botón Filtrar
            document.getElementById('btnFilterStats').addEventListener('click', function () {
                fetchModalStats();
            });

            // Botón Limpiar Filtros
            document.getElementById('btnResetStats').addEventListener('click', function () {
                document.getElementById('statStartDate').value = '';
                document.getElementById('statEndDate').value = '';
                document.getElementById('statInstaller').value = '';
                document.getElementById('statVendor').value = '';
                document.getElementById('statContractType').value = '';
                fetchModalStats();
            });

            // Botón Exportar PDF
            document.getElementById('btnExportPDF').addEventListener('click', function () {
                exportStatsPDF();
            });
        });

        function fetchStatsDashboard() {
            fetch('get_contract_stats.php?action=dashboard')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('statTotalContracts').textContent = data.total_contracts;
                    document.getElementById('statTotalClients').textContent = data.total_clients;
                })
                .catch(error => console.error('Error fetching dashboard stats:', error));
        }

        function fetchStatsLists() {
            Promise.all([
                // Fetch Instaladores form JSON API (Same as Management Modal)
                fetch('json_personal_api.php?action=get_instaladores').then(r => r.json()),
                // Fetch Vendedores from JSON API
                fetch('json_personal_api.php?action=get_vendedores').then(r => r.json()),
                // Fetch Tipos from Types API
                fetch('api_tipos_conexion.php').then(r => r.json()),
                // Fetch Tipos Instalacion from Types API
                fetch('api_tipos_instalacion.php').then(r => r.json())
            ]).then(([installers, vendors, typesConn, typesInst]) => {
                const selInst = document.getElementById('statInstaller');
                const selVend = document.getElementById('statVendor');
                const selType = document.getElementById('statContractType');
                const selInstType = document.getElementById('statInstallType');

                // Fill Instaladores
                if (selInst.options.length <= 1) {
                    installers.forEach(inst => selInst.add(new Option(inst, inst)));
                }

                // Fill Vendedores
                if (selVend.options.length <= 1) {
                    vendors.forEach(vend => selVend.add(new Option(vend, vend)));
                }

                // Fill Tipos Conex
                if (selType.options.length <= 1) {
                    typesConn.forEach(t => selType.add(new Option(t, t)));
                }

                // Fill Tipos Instalacion
                if (selInstType.options.length <= 1) {
                    typesInst.forEach(t => selInstType.add(new Option(t, t)));
                }
            }).catch(err => console.error('Error loading lists:', err));
        }

        // Chart Instances
        let chartInstances = {};

        function fetchModalStats() {
            const start = document.getElementById('statStartDate').value;
            const end = document.getElementById('statEndDate').value;
            const inst = document.getElementById('statInstaller').value;
            const vend = document.getElementById('statVendor').value;
            const type = document.getElementById('statContractType').value;
            const instType = document.getElementById('statInstallType').value;

            const params = new URLSearchParams({
                action: 'modal_stats',
                start: start,
                end: end,
                installer: inst,
                vendor: vend,
                type: type,
                install_type: instType,
                _t: new Date().getTime() // Cache busting
            });

            fetch('get_contract_stats.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    // 1. Zonas de Ventas (Horizontal Blue)
                    renderStatsChartV4('chartLocation', data.by_location, 'ubicacion', 'Zonas de Ventas', true, '#3a7bd5');

                    // 2. Instalaciones por Tipo (Horizontal Blue)
                    renderStatsChartV4('chartType', data.by_type, 'tipo', 'Instalaciones por Tipo', true, '#3a7bd5');

                    // 3. Instalaciones Mensuales (Horizontal Blue) - AHORA CON FECHAS DESCRIPTIVAS
                    if (data.by_month) {
                        data.by_month.forEach(item => {
                            const [fullDate, instName] = item.fecha.split(' - ');
                            if (fullDate && fullDate !== 'null') {
                                // En Chart.js, un array genera múltiples líneas.
                                item.fecha_descriptiva = [formatDescriptiveDateJS(fullDate), instName];
                            } else {
                                item.fecha_descriptiva = ['Sin Fecha', instName];
                            }
                        });
                    }
                    renderStatsChartV4('chartMonthly', data.by_month, 'fecha_descriptiva', 'Instalaciones', true, '#3a7bd5');

                    // 4. Tipo de Instalación (Horizontal Multi-color)
                    renderStatsChartV4('chartConnection', data.by_connection, 'conexion', 'Tipos de Conexión', true, null);

                    // 5. Instaladores (Horizontal Blue - Match Location)
                    renderStatsChartV4('chartInstaller', data.by_installer, 'nombre', 'Instaladores', true, '#3a7bd5');

                    // 6. Vendedor (Horizontal Blue)
                    renderStatsChartV4('chartVendor', data.by_vendor, 'nombre_vendedor', 'Ventas', true, '#3a7bd5');

                    // 7. SAE Plus (Horizontal Multi-color)
                    renderStatsChartV4('chartSae', data.by_sae, 'status', 'Carga en SAE Plus', true, null, data.total_global);
                });
        }

        function renderStatsChartV4(canvasId, data, labelKey, title, isHorizontal = false, customColor = null, denominator = null) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            
            // --- Theme-Aware Colors ---
            const textColor = isDark ? '#cbd5e1' : '#1e293b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)';
            const labelBg = isDark ? 'rgba(30, 41, 59, 0.95)' : 'rgba(255, 255, 255, 0.95)';
            const labelBorder = isDark ? '#475569' : '#cbd5e1';
            const labelText = isDark ? '#f8fafc' : '#1e293b';
            const canvasBg = isDark ? '#1e293b' : '#ffffff';

            if (chartInstances[canvasId]) {
                chartInstances[canvasId].destroy();
            }

            const filteredData = data ? data.filter(item => parseInt(item.total) > 0) : [];

            if (filteredData.length === 0) {
                const container = document.getElementById(canvasId).parentElement;
                container.style.height = '450px';

                chartInstances[canvasId] = new Chart(ctx, {
                    type: 'bar',
                    data: { labels: ['Sin Datos'], datasets: [{ data: [0], backgroundColor: [isDark ? '#334155' : '#e9ecef'] }] },
                    options: {
                        indexAxis: isHorizontal ? 'y' : 'x',
                        plugins: { 
                            legend: { display: false }, 
                            title: { display: true, text: 'Sin Resultados', color: textColor } 
                        },
                        scales: {
                            x: { ticks: { color: textColor }, grid: { display: false } },
                            y: { ticks: { color: textColor }, grid: { display: false } }
                        }
                    }
                });
                return;
            }

            if (isHorizontal) {
                const calculatedHeight = Math.max(450, filteredData.length * 60);
                document.getElementById(canvasId).parentElement.style.height = calculatedHeight + 'px';
            }

            const labels = filteredData.map(item => item[labelKey]);
            const values = filteredData.map(item => item.total);

            let colors;
            if (canvasId === 'chartSae') {
                colors = labels.map(lbl => {
                    const isLoaded = lbl.includes('(CARGADO)');
                    const isFTTH = lbl.toUpperCase().includes('FTTH');
                    const isRadio = lbl.toUpperCase().includes('RADIO');
                    if (isFTTH) return isLoaded ? '#10b981' : '#a855f7';
                    if (isRadio) return isLoaded ? '#3b82f6' : '#f59e0b';
                    return isLoaded ? '#059669' : '#94a3b8';
                });
            } else {
                colors = customColor ? new Array(filteredData.length).fill(customColor) : generateColors(filteredData.length);
            }

            const categoryTotals = {};
            if (canvasId === 'chartSae') {
                filteredData.forEach(item => {
                    const lbl = item[labelKey];
                    const category = lbl.split(' (')[0];
                    categoryTotals[category] = (categoryTotals[category] || 0) + parseInt(item.total);
                });
            }

            const maxVal = Math.max(...values);
            const axisMax = maxVal > 0 ? Math.ceil(maxVal * 1.25) : 10;

            chartInstances[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cantidad',
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.startsWith('hsl') ? c.replace('60%', '50%') : c),
                        borderWidth: 1,
                        barPercentage: 0.9,
                        categoryPercentage: 0.9,
                        minBarLength: 5
                    }]
                },
                plugins: [ChartDataLabels, {
                    id: 'custom_canvas_background_color',
                    beforeDraw: (chart) => {
                        const { ctx } = chart;
                        ctx.save();
                        ctx.globalCompositeOperation = 'destination-over';
                        ctx.fillStyle = canvasBg;
                        ctx.fillRect(0, 0, chart.width, chart.height);
                        ctx.restore();
                    }
                }],
                options: {
                    animation: false,
                    indexAxis: isHorizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: false },
                        datalabels: {
                            display: true,
                            anchor: 'end',
                            align: isHorizontal ? 'right' : 'top',
                            backgroundColor: labelBg,
                            borderColor: labelBorder,
                            borderWidth: 1,
                            borderRadius: 4,
                            padding: { top: 2, bottom: 2, left: 4, right: 4 },
                            color: labelText,
                            font: { weight: 'bold', size: canvasId === 'chartSae' ? 14 : 12 },
                            offset: 4,
                            formatter: function (value, context) {
                                if (value <= 0) return '';
                                const label = context.chart.data.labels[context.dataIndex];
                                if (canvasId === 'chartSae') {
                                    const category = label.split(' (')[0];
                                    const catTotal = categoryTotals[category] || 0;
                                    const localPerc = catTotal > 0 ? ((value / catTotal) * 100).toFixed(1) : '0.0';
                                    const fiabPerc = denominator > 0 ? ((value / denominator) * 100).toFixed(1) : '0.0';
                                    return `${value}\n${localPerc}% (${category})\nFiabilidad: ${fiabPerc}%`;
                                } else {
                                    const dataset = context.chart.data.datasets[0].data;
                                    const localTotal = dataset.reduce((a, b) => a + b, 0);
                                    const percentage = localTotal > 0 ? ((value / localTotal) * 100).toFixed(1) : '0.0';
                                    return `${value}\n${percentage}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            grid: { color: gridColor, drawBorder: false },
                            max: isHorizontal ? axisMax : undefined,
                            ticks: {
                                color: textColor,
                                autoSkip: false,
                                maxRotation: isHorizontal ? 0 : 90,
                                minRotation: isHorizontal ? 0 : 45,
                                font: { size: 12 }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            max: !isHorizontal ? axisMax : undefined,
                            ticks: {
                                color: textColor,
                                stepSize: 1,
                                precision: 0,
                                font: { size: 12 }
                            },
                            grid: { color: gridColor, drawBorder: false }
                        }
                    }
                }
            });
        }

        function generateColors(count) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const hue = (i * 137.508) % 360; // Golden angle approx
                colors.push(`hsl(${hue}, 70%, 60%)`);
            }
            return colors;
        }

        function exportStatsPDF() {
            const fields = {
                start: document.getElementById('statStartDate').value,
                end: document.getElementById('statEndDate').value,
                installer: document.getElementById('statInstaller').value,
                vendor: document.getElementById('statVendor').value,
                type: document.getElementById('statContractType').value,
                install_type: document.getElementById('statInstallType').value
            };

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../../paginas/reportes_pdf/generar_estadisticas_pdf.php';
            form.target = '_blank';

            for (const key in fields) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>

</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<!-- Modals -->
<div class="modal fade" id="eliminaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <div class="mb-3 text-danger"><i class="fa-solid fa-trash-can fa-3x"></i></div>
                <h5 class="fw-bold">Eliminar Contrato</h5>
                <p class="text-muted small">¿Confirma eliminar este registro permanentemente? Esta acción no se puede
                    deshacer.</p>

                <div class="mb-3 text-start">
                    <label for="delete_password" class="form-label small fw-bold">Confirme su Contraseña</label>
                    <input type="password" class="form-control" id="delete_password"
                        placeholder="Ingrese su contraseña administrativa">
                </div>

                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal Importar Excel -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-excel me-2"></i>Importar Contratos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 shadow-sm small">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    Sube un archivo Excel (.xlsx) con los datos de los contratos.
                    Asegúrate de que las columnas coincidan con el formato de exportación.
                </div>

                <form id="formImportExcel" action="importar_excel_contratos.php" method="POST"
                    enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Archivo Excel</label>
                        <input type="file" name="archivo_excel" class="form-control" accept=".xlsx, .xls" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success shadow-sm">
                            <i class="fa-solid fa-upload me-2"></i> Cargar y Procesar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL EDITAR CONTRATO ===== -->
<div class="modal fade" id="modalEditarContrato" tabindex="-1" aria-labelledby="modalEditarContratoLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalEditarContratoLabel">
                    <i class="fa-solid fa-pen me-2"></i>Editar Contrato #<span id="editContratoId">-</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                <!-- Alert for errors/success -->
                <div id="editContratoAlert" class="alert d-none mb-3" role="alert"></div>

                <!-- ══════════ ACORDEÓN FINANCIERO ══════════ -->
                <div class="accordion accordion-flush mb-4 border rounded shadow-sm" id="accordionFinanciero">

                    <!-- Panel 1: Resumen Financiero -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingResumen">
                            <button class="accordion-button collapsed fw-bold py-2" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapseResumen"
                                aria-expanded="false" aria-controls="collapseResumen"
                                onclick="cargarBalancePanel('resumen')">
                                <i class="fa-solid fa-chart-pie me-2 text-primary"></i>
                                Resumen Financiero
                                <span id="badge_resumen" class="ms-auto me-3"></span>
                            </button>
                        </h2>
                        <div id="collapseResumen" class="accordion-collapse collapse">
                            <div class="accordion-body p-3" id="body_resumen">
                                <div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 2: Historial de Pagos -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingPagos">
                            <button class="accordion-button collapsed fw-bold py-2" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapsePagos"
                                aria-expanded="false" aria-controls="collapsePagos"
                                onclick="cargarBalancePanel('pagos')">
                                <i class="fa-solid fa-receipt me-2 text-success"></i>
                                Historial de Pagos
                                <span id="badge_pagos" class="ms-auto me-3"></span>
                            </button>
                        </h2>
                        <div id="collapsePagos" class="accordion-collapse collapse">
                            <div class="accordion-body p-2" id="body_pagos">
                                <div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 3: Todos los Movimientos -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingMovimientos">
                            <button class="accordion-button collapsed fw-bold py-2" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapseMovimientos"
                                aria-expanded="false" aria-controls="collapseMovimientos"
                                onclick="cargarBalancePanel('movimientos')">
                                <i class="fa-solid fa-list-ul me-2 text-info"></i>
                                Todos los Movimientos
                                <span id="badge_movimientos" class="ms-auto me-3"></span>
                            </button>
                        </h2>
                        <div id="collapseMovimientos" class="accordion-collapse collapse">
                            <div class="accordion-body p-2" id="body_movimientos">
                                <div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel 4: Deuda Activa -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDeuda">
                            <button class="accordion-button collapsed fw-bold py-2" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapseDeuda"
                                aria-expanded="false" aria-controls="collapseDeuda"
                                onclick="cargarBalancePanel('deuda')">
                                <i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>
                                Deuda Activa
                                <span id="badge_deuda" class="ms-auto me-3"></span>
                            </button>
                        </h2>
                        <div id="collapseDeuda" class="accordion-collapse collapse">
                            <div class="accordion-body p-2" id="body_deuda">
                                <div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- ══════════ FIN ACORDEÓN FINANCIERO ══════════ -->

                <!-- Spinner -->
                <div id="editContratoSpinner" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small">Cargando datos...</p>
                </div>

                <form id="formEditarContrato" class="d-none">
                    <input type="hidden" id="edit_id" name="id">

                    <!-- SECCIÓN: INFO CLIENTE -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-primary border-4 mb-3">
                        <i class="fa-solid fa-user me-2 text-primary"></i>Información del Cliente
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Cédula <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_cedula" name="cedula"
                                required pattern="[VJEGPvjegp][0-9]+" placeholder="V12345678"
                                style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Estado <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="edit_estado" name="estado" required>
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                                <option value="SUSPENDIDO">SUSPENDIDO</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nombre Completo <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_nombre"
                                name="nombre_completo" required pattern="[A-Za-zñÑáéíóúÁÉÍÓÚ\s]+">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Documento de Identidad (ID)</label>
                            <div class="input-group input-group-sm">
                                <input type="file" class="form-control" id="edit_evidencia_documento_file" name="evidencia_documento_file" accept="image/*">
                                <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Teléfono</label>
                            <input type="text" class="form-control form-control-sm" id="edit_telefono" name="telefono"
                                inputmode="tel" pattern="[0-9+\s\-]{7,15}" placeholder="0424-1234567">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Teléfono (Alt)</label>
                            <input type="text" class="form-control form-control-sm" id="edit_telefono2"
                                name="telefono_secundario" inputmode="tel" pattern="[0-9+\s-]{7,15}"
                                placeholder="0414-7654321">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Correo</label>
                            <input type="email" class="form-control form-control-sm" id="edit_correo" name="correo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Correo (Alt)</label>
                            <input type="email" class="form-control form-control-sm" id="edit_correo2"
                                name="correo_adicional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Fecha de Instalación</label>
                            <input type="date" class="form-control form-control-sm" id="edit_fecha"
                                name="fecha_instalacion">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-primary">SAE Plus ID</label>
                            <input type="text" class="form-control form-control-sm border-primary" id="edit_sae_plus"
                                name="sae_plus" placeholder="ID de SAE">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Dirección</label>
                            <textarea class="form-control form-control-sm" id="edit_direccion" name="direccion"
                                rows="2"></textarea>
                        </div>
                    </div>

                    <!-- SECCIÓN: UBICACIÓN -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-success border-4 mb-3">
                        <i class="fa-solid fa-map-location-dot me-2 text-success"></i>Ubicación
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Municipio</label>
                            <select class="form-select form-select-sm" id="edit_municipio" name="id_municipio">
                                <option value="">Cargando municipios...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Parroquia</label>
                            <select class="form-select form-select-sm" id="edit_parroquia" name="id_parroquia" disabled>
                                <option value="">-- Seleccione municipio --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Comunidad</label>
                            <select class="form-select form-select-sm" id="edit_comunidad" name="id_comunidad" disabled>
                                <option value="">-- Seleccione parroquia --</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN: CONTRATO / PLAN -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-warning border-4 mb-3">
                        <i class="fa-solid fa-file-contract me-2 text-warning"></i>Plan y Comercial
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Plan</label>
                            <select class="form-select form-select-sm" id="edit_plan" name="id_plan">
                                <option value="">-- Seleccione --</option>
                                <?php
                                $sql_planes = "SELECT id_plan, nombre_plan, monto FROM planes ORDER BY nombre_plan ASC";
                                $res_planes = $conn->query($sql_planes);
                                while ($p = $res_planes->fetch_assoc()) {
                                    echo '<option value="' . $p['id_plan'] . '" data-monto="' . $p['monto'] . '">' . htmlspecialchars($p['nombre_plan']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Monto Plan ($)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="edit_monto_plan"
                                name="monto_plan" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Vendedor</label>
                            <select class="form-select form-select-sm" id="edit_vendedor" name="vendedor_texto">
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Plan Prorrateo</label>
                            <select class="form-select form-select-sm" id="edit_plan_prorrateo"
                                name="plan_prorrateo_nombre">
                                <option value="">Cargando...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Días Prorrateo</label>
                            <input type="number" min="0" class="form-control form-control-sm" id="edit_dias_prorrateo"
                                name="dias_prorrateo">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Monto Prorrateo ($)</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="edit_monto_prorrateo"
                                name="monto_prorrateo_usd" readonly>
                        </div>
                    </div>

                    <!-- SECCIÓN: COSTOS Y PAGOS -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-danger border-4 mb-3">
                        <i class="fa-solid fa-dollar-sign me-2 text-danger"></i>Información Económica
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Monto Pagar ($)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="edit_monto_pagar"
                                name="monto_pagar">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Monto Pagado</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="edit_monto_pagado"
                                name="monto_pagado">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Medio de Pago</label>
                            <select class="form-select form-select-sm" id="edit_medio_pago" name="medio_pago">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Pago Móvil">Pago Móvil</option>
                                <option value="Zelle">Zelle</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN: RED -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-info border-4 mb-3">
                        <i class="fa-solid fa-network-wired me-2 text-info"></i>Red y NAP
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">OLT</label>
                            <select class="form-select form-select-sm" id="edit_olt" name="id_olt">
                                <option value="">-- Seleccione --</option>
                                <?php
                                $sql_olts = "SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt ASC";
                                $res_olts = $conn->query($sql_olts);
                                while ($o = $res_olts->fetch_assoc()) {
                                    echo '<option value="' . $o['id_olt'] . '">' . htmlspecialchars($o['nombre_olt']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">PON</label>
                            <select class="form-select form-select-sm" id="edit_pon" name="id_pon" disabled>
                                <option value="">-- Seleccione OLT --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo de Conexión</label>
                            <select class="form-select form-select-sm" id="edit_tipo_conexion" name="tipo_conexion">
                                <option value="">-- Seleccione --</option>
                                <?php
                                $jsonTipos = 'data/tipos_conexion.json';
                                if (file_exists($jsonTipos)) {
                                    $tipos = json_decode(file_get_contents($jsonTipos), true);
                                    foreach ($tipos as $t)
                                        echo '<option value="' . $t . '">' . $t . '</option>';
                                } else {
                                    echo '<option value="FTTH">FTTH</option><option value="RADIO">RADIO</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tipo Instalación</label>
                            <select class="form-select form-select-sm" id="edit_tipo_instalacion" name="tipo_instalacion">
                                <option value="">-- Seleccione --</option>
                                <?php
                                $jsonTiposInst = 'data/tipos_instalacion.json';
                                if (file_exists($jsonTiposInst)) {
                                    $tiposInst = json_decode(file_get_contents($jsonTiposInst), true);
                                    foreach ($tiposInst as $t)
                                        echo '<option value="' . $t . '">' . $t . '</option>';
                                } else {
                                    // Fallback defaults
                                    $defaultsInst = ["Nivel 1", "Nivel 2", "Nivel 3", "Mudanza", "Migración", "Onu", "Reactivación"];
                                    foreach ($defaultsInst as $t)
                                        echo '<option value="' . $t . '">' . $t . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Caja NAP</label>
                            <input type="text" class="form-control form-control-sm" id="edit_nap" name="ident_caja_nap">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Puerto NAP</label>
                            <input type="text" class="form-control form-control-sm" id="edit_puerto_nap"
                                name="puerto_nap">
                        </div>
                        <div class="col-md-6 campo-edit-ftth">
                            <label class="form-label small fw-bold text-primary">Instalador FTTH</label>
                            <select class="form-select form-select-sm" id="edit_instalador_ftth" name="instalador_ftth">
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>
                        <div class="col-md-6 campo-edit-radio">
                            <label class="form-label small fw-bold text-warning">Instalador Radio</label>
                            <select class="form-select form-select-sm" id="edit_instalador_radio" name="instalador_radio">
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN: TÉCNICO FTTH/RADIO -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-secondary border-4 mb-3">
                        <i class="fa-solid fa-screwdriver-wrench me-2 text-secondary"></i>Datos Técnicos
                    </div>
                    <div class="row g-3 mb-4" id="edit_campos_ftth">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">MAC/Serial ONU</label>
                            <input type="text" class="form-control form-control-sm" id="edit_mac" name="mac_onu"
                                pattern="[A-Fa-f0-9:.\-]{8,20}" placeholder="FABBCC112233">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">IP ONU</label>
                            <input type="text" class="form-control form-control-sm" id="edit_ip_onu" name="ip_onu"
                                pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" placeholder="192.168.1.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">NAP TX Power</label>
                            <input type="text" class="form-control form-control-sm" id="edit_nap_tx" name="nap_tx_power"
                                pattern="-?[0-9.]+" placeholder="-25.5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">ONU RX Power</label>
                            <input type="text" class="form-control form-control-sm" id="edit_onu_rx" name="onu_rx_power"
                                pattern="-?[0-9.]+" placeholder="-27.5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Distancia Drop (m)</label>
                            <input type="number" step="1" class="form-control form-control-sm" id="edit_drop"
                                name="distancia_drop" placeholder="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Precinto ODN</label>
                            <input type="text" class="form-control form-control-sm" id="edit_odn"
                                name="num_presinto_odn">
                        </div>
                    </div>
                    <div class="row g-3 mb-4" id="edit_campos_radio">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Punto de Acceso</label>
                            <input type="text" class="form-control form-control-sm" id="edit_pa" name="punto_acceso">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">IP Servicio</label>
                            <input type="text" class="form-control form-control-sm" id="edit_ip_radio" name="ip_servicio"
                                placeholder="192.168.1.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Valor Conexión (dBm)</label>
                            <input type="text" class="form-control form-control-sm" id="edit_dbm"
                                name="valor_conexion_dbm" pattern="-?[0-9.]+" placeholder="-55.0">
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Observaciones</label>
                            <textarea class="form-control form-control-sm" id="edit_observaciones" name="observaciones"
                                rows="2"></textarea>
                        </div>
                    </div>

                    <!-- SECCIÓN: EVIDENCIA FOOTER -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-info border-4 mb-3 mt-4">
                        <i class="fa-solid fa-camera me-2 text-info"></i>Evidencias y Documentación
                    </div>
                    <div class="row g-4 mb-2 text-center">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold d-block text-uppercase">Instalación</label>
                            <div id="prev_evidencia_foto_div" class="mb-2 border rounded p-1 glass-preview-box" style="min-height: 120px; display: flex; align-items: center; justify-content: center;">
                                <img id="prev_evidencia_foto" src="" alt="Instalación" class="img-fluid rounded" style="max-height: 120px; display:none;">
                                <span id="no_evidencia_foto" class="text-muted small">Sin imagen</span>
                            </div>
                            <input type="file" class="form-control form-control-sm" id="edit_evidencia_foto" name="evidencia_foto" accept="image/*">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold d-block text-uppercase">Documento ID</label>
                            <div id="prev_evidencia_documento_div" class="mb-2 border rounded p-1 glass-preview-box" style="min-height: 120px; display: flex; align-items: center; justify-content: center;">
                                <img id="prev_evidencia_documento" src="" alt="Documento" class="img-fluid rounded" style="max-height: 120px; display:none;">
                                <span id="no_evidencia_documento" class="text-muted small">Sin imagen</span>
                            </div>
                            <input type="file" class="form-control form-control-sm" id="edit_evidencia_documento" name="evidencia_documento_file" accept="image/*">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold d-block text-uppercase">Fibra (ID / Detalle)</label>
                            <div class="input-group input-group-sm mt-1">
                                <input type="text" class="form-control" id="edit_evidencia_fibra" name="evidencia_fibra" placeholder="ID Fibra">
                                <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN: FIRMAS -->
                    <div class="section-title bg-light p-2 fw-bold border-start border-danger border-4 mb-3 mt-4">
                        <i class="fa-solid fa-signature me-2 text-danger"></i>Firmas Digitales
                    </div>
                    <div class="row g-4 mb-2">
                        <!-- Firma Cliente -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold d-block">Firma del Cliente</label>
                            <div id="prev_firma_cliente_div" class="mb-2">
                                <img id="prev_firma_cliente" src="" alt="Firma Cliente" class="signature-prev d-none">
                                <span id="no_firma_cliente" class="badge bg-secondary d-none">Sin firma</span>
                            </div>

                            <div id="pad_firma_cliente_div" class="d-none">
                                <canvas id="edit_sigCliente" class="signature-pad"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                    onclick="clearEditPad('cliente')">
                                    <i class="fa-solid fa-eraser me-1"></i>Limpiar Pad
                                </button>
                                <input type="hidden" name="firma_cliente_data" id="edit_firma_cliente_data">
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary mt-1"
                                id="btnCambiarFirmaCliente">
                                <i class="fa-solid fa-pen me-1"></i>Cambiar Firma
                            </button>
                        </div>

                        <!-- Firma Técnico -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold d-block">Firma del Técnico</label>
                            <div id="prev_firma_tecnico_div" class="mb-2">
                                <img id="prev_firma_tecnico" src="" alt="Firma Técnico" class="signature-prev d-none">
                                <span id="no_firma_tecnico" class="badge bg-secondary d-none">Sin firma</span>
                            </div>

                            <div id="pad_firma_tecnico_div" class="d-none">
                                <canvas id="edit_sigTecnico" class="signature-pad"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                    onclick="clearEditPad('tecnico')">
                                    <i class="fa-solid fa-eraser me-1"></i>Limpiar Pad
                                </button>
                                <input type="hidden" name="firma_tecnico_data" id="edit_firma_tecnico_data">
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary mt-1"
                                id="btnCambiarFirmaTecnico">
                                <i class="fa-solid fa-pen me-1"></i>Cambiar Firma
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarContrato" disabled>
                    <i class="fa-solid fa-floppy-disk me-1"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>
<!-- ===== FIN MODAL EDITAR CONTRATO ===== -->


<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>

<script>
    // === LÓGICA DE FIRMAS PARA EL MODAL DE EDICIÓN ===
    let padEditCliente, padEditTecnico;

    function resizeEditPad(canvas, pad) {
        if (!canvas || !pad) return;
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        pad.clear(); // SignaturePad needs clearing after resize to reset its internal state
    }

    function initEditSignaturePads() {
        const canvasCliente = document.getElementById('edit_sigCliente');
        const canvasTecnico = document.getElementById('edit_sigTecnico');

        if (!canvasCliente || !canvasTecnico) return;

        const penColor = '#000000'; // Always black for consistency

        if (!padEditCliente) padEditCliente = new SignaturePad(canvasCliente, { penColor: penColor });
        if (!padEditTecnico) padEditTecnico = new SignaturePad(canvasTecnico, { penColor: penColor });
    }

    function clearEditPad(type) {
        if (type === 'cliente') padEditCliente.clear();
        if (type === 'tecnico') padEditTecnico.clear();
    }

    function resetEditPadsWorkflows() {
        // Ocultar pads y mostrar previas
        $('#pad_firma_cliente_div').addClass('d-none');
        $('#prev_firma_cliente_div').removeClass('d-none');
        $('#btnCambiarFirmaCliente').removeClass('d-none');
        $('#edit_firma_cliente_data').val('');

        $('#pad_firma_tecnico_div').addClass('d-none');
        $('#prev_firma_tecnico_div').removeClass('d-none');
        $('#btnCambiarFirmaTecnico').removeClass('d-none');
        $('#edit_firma_tecnico_data').val('');

        if (padEditCliente) padEditCliente.clear();
        if (padEditTecnico) padEditTecnico.clear();
    }

    $(document).ready(function () {
        // Inicializar al abrir el modal 
        $('#modalEditarContrato').on('shown.bs.modal', function () {
            initEditSignaturePads();
        });

        // Resetear el acordeón financiero al CERRAR el modal
        $('#modalEditarContrato').on('hidden.bs.modal', function () {
            ['resumen','pagos','movimientos','deuda'].forEach(p => {
                const body = document.getElementById('body_' + p);
                const badge = document.getElementById('badge_' + p);
                if (body) body.innerHTML = '<div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</div>';
                if (badge) badge.innerHTML = '';
            });
            // Colapsar todos los paneles
            document.querySelectorAll('#accordionFinanciero .accordion-collapse.show').forEach(el => {
                el.classList.remove('show');
            });
            // Marcar botones como collapsed
            document.querySelectorAll('#accordionFinanciero .accordion-button').forEach(btn => {
                btn.classList.add('collapsed');
                btn.setAttribute('aria-expanded', 'false');
            });
            // Limpiar caché de paneles
            window._balancePanelLoaded = {};
        });


        $('#btnCambiarFirmaCliente').on('click', function () {
            $('#prev_firma_cliente_div').addClass('d-none');
            $('#pad_firma_cliente_div').removeClass('d-none');
            $(this).addClass('d-none');

            // Forzar resize una vez visible
            const canvas = document.getElementById('edit_sigCliente');
            resizeEditPad(canvas, padEditCliente);
        });

        $('#btnCambiarFirmaTecnico').on('click', function () {
            $('#prev_firma_tecnico_div').addClass('d-none');
            $('#pad_firma_tecnico_div').removeClass('d-none');
            $(this).addClass('d-none');

            // Forzar resize una vez visible
            const canvas = document.getElementById('edit_sigTecnico');
            resizeEditPad(canvas, padEditTecnico);
        });
    });

    // Modificar el submit para capturar la data
    function prepareSignaturesForSubmit() {
    if (padEditCliente && !padEditCliente.isEmpty() && !$('#pad_firma_cliente_div').hasClass('d-none')) {
        $('#edit_firma_cliente_data').val(padEditCliente.toDataURL());
    }
    if (padEditTecnico && !padEditTecnico.isEmpty() && !$('#pad_firma_tecnico_div').hasClass('d-none')) {
        $('#edit_firma_tecnico_data').val(padEditTecnico.toDataURL());
    }
    }
</script>

<script>
    // --- EXPORTAR EXCEL ---
    async function exportExcel() {
        // Mostrar mensaje de carga
        Swal.fire({
            title: 'Generando Excel...',
            html: 'Obteniendo datos del servidor...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            // 1. Obtener TODOS los datos crudosdesde el backend
            const response = await fetch('get_all_contratos_json.php');
            if (!response.ok) throw new Error('Error de red al obtener datos');
            const data = await response.json();

            if (!data || data.length === 0) {
                throw new Error('No hay datos para exportar.');
            }

            // 2. Crear Workbook
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Contratos');

            // 3. Definir Columnas y Encabezados (Mismo orden que el array $colMap en importación)
            worksheet.columns = [
                { header: 'ID', key: 'id_contrato', width: 10 },
                { header: 'SAR', key: 'fecha_registro', width: 15 },
                { header: 'Cédula', key: 'cedula_cliente', width: 15 },
                { header: 'Cliente', key: 'nombre_cliente', width: 25 },
                { header: 'Plan ($)', key: 'monto_plan', width: 15 },
                { header: 'Municipio', key: 'nombre_municipio', width: 15 },
                { header: 'Parroquia', key: 'nombre_parroquia', width: 15 },
                { header: 'Dirección', key: 'direccion_instalacion', width: 30 },
                { header: 'Telf. 1', key: 'telefono_cliente', width: 15 },
                { header: 'Telf. 2', key: 'telefono_extra', width: 15 },
                { header: 'Correo', key: 'email_cliente', width: 25 },
                { header: 'Correo (Alt)', key: 'email_extra', width: 25 },
                { header: 'F. Instalación', key: 'fecha_instalacion', width: 15 },
                { header: 'Medio Pago', key: 'metodo_pago', width: 15 },
                { header: 'Monto Pagar', key: 'costo_instalacion', width: 15 },
                { header: 'Monto Pagado', key: 'monto_pagado', width: 15 },
                { header: 'Días Prorrateo', key: 'dias_prorrateo', width: 10 },
                { header: 'Plan Prorrateo', key: 'plan_prorrateo_nombre', width: 20 },
                { header: 'Monto Prorr. ($)', key: 'monto_prorrateo', width: 15 },
                { header: 'Observ.', key: 'observaciones', width: 30 },
                { header: 'Tipo Conex.', key: 'tipo_conexion', width: 15 },
                { header: 'Tipo Instal.', key: 'tipo_instalacion', width: 15 },

                { header: 'MAC/Serial', key: 'mac_serial', width: 20 },
                { header: 'IP ONU', key: 'ip_onu', width: 15 },
                { header: 'Caja NAP', key: 'caja_nap', width: 10 },
                { header: 'Puerto NAP', key: 'puerto_nap', width: 10 },
                { header: 'NAP TX (dBm)', key: 'potencia_nap_tx', width: 10 },
                { header: 'ONU RX (dBm)', key: 'potencia_onu_rx', width: 10 },
                { header: 'Dist. Drop (m)', key: 'distancia_drop', width: 10 },
                { header: 'Instalador', key: 'id_instalador', width: 15 },
                { header: 'Evidencia Fibra', key: 'evidencia_foto_fibra', width: 20 },
                { header: 'IP Servicio', key: 'ip_servicio', width: 15 },
                { header: 'Punto Acceso', key: 'punto_acceso', width: 15 },
                { header: 'Val. Conex. (dBm)', key: 'valor_conexion', width: 10 },
                { header: 'Precinto ODN', key: 'precinto_odn', width: 15 },
                { header: 'Vendedor (Edit)', key: 'id_vendedor', width: 15 },
                { header: 'SAE Plus (Edit)', key: 'codigo_sae_plus', width: 15 },
                { header: 'Plan', key: 'nombre_plan', width: 15 },
                { header: 'OLT', key: 'nombre_olt', width: 15 },
                { header: 'PON', key: 'nombre_pon', width: 10 },
                { header: 'Estado', key: 'status', width: 10 }
            ];

            // Estilo Header
            const headerRow = worksheet.getRow(1);
            headerRow.eachCell((cell) => {
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: '003366' }
                };
                cell.font = {
                    color: { argb: 'FFFFFF' },
                    bold: true
                };
                cell.alignment = { horizontal: 'center' };
            });

            // 4. Agregar filas directamente (ya vienen limpias del SQL/JSON)
            // Solo necesitamos asegurarnos que las keys coincidan
            worksheet.addRows(data);

            // Generar archivo
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Contratos_Wireless_' + new Date().toISOString().split('T')[0] + '.xlsx';
            a.click();
            window.URL.revokeObjectURL(url);

            Swal.close();

        } catch (error) {
            console.error("Error exportando Excel:", error);
            Swal.fire('Error', 'Hubo un problema al generar el Excel: ' + error.message, 'error');
        }
    }

    // Configuración DataTables original
    $(document).ready(function () {
        var table = $('#mitabla').DataTable({
            "order": [[0, "desc"]], // Ordenar por ID descendente por defecto
            // (más recientes primero)
            "language": {
                "lengthMenu": "Ver _MENU_",
                "zeroRecords": "No hay datos",
                "info": "_START_ - _END_ de _TOTAL_",
                "infoEmpty": "0 reg.",
                "infoFiltered": "(filtrado de _MAX_)",
                "search": "Buscar:",
                "paginate": { "next": ">", "previous": "<" }
            },
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "server_process.php",
            "autoWidth": true, // DataTables measures content and sizes columns automatically
            "fnServerParams": function (aoData) {
                aoData.push({ "name": "empty_filter", "value": $('#filter_empty').val() });
            },
            "aoColumnDefs": [
                { "bVisible": false, "aTargets": [0] }, // Ocultar ID
                { "className": "text-center", "aTargets": "_all" }, // Centrar todo por defecto
                
                // Tooltip en columnas con texto largo
                {
                    "aTargets": [3, 7, 10, 11, 18, 33],
                    "mRender": function (data) {
                        if (!data) return '';
                        var s = data.toString().replace(/"/g, '&quot;');
                        return '<span title="' + s + '">' + data + '</span>';
                    }
                },
                // Badge para Plan ($)
                {
                    "aTargets": [4],
                    "mRender": function (data, type, full) {
                        return '<span class="badge bg-light text-dark border">$' + data + '</span>';
                    }
                }
            ]
        });

        // --- Inline Edit Logic ---
        $('#mitabla tbody').on('blur', '.editable-cell', function () {
            var cell = $(this);
            var id = cell.data('id');
            var field = cell.data('field');
            var value = cell.text(); // text() gets plain text, html() usually not needed for simple inputs

            // Basic UI feedback 'saving'
            cell.css('color', '#6c757d');

            $.post('actualizar_contrato_inline.php', {
                id: id,
                field: field,
                value: value
            }, function (resp) {
                // Success
                cell.css('color', '#198754'); // Green text
                cell.addClass('bg-success bg-opacity-10');
                setTimeout(() => {
                    cell.removeClass('bg-success bg-opacity-10');
                    cell.css('color', '#212529'); // Reset color
                }, 1500);
            }).fail(function () {
                // Fail
                cell.css('color', '#dc3545'); // Red text
                alert("Error al guardar cambios. Verifique su conexión.");
            });
        });

        // Enter key to blur (save)
        $('#mitabla tbody').on('keydown', '.editable-cell', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).blur();
            }
        });

        // Modal Logic
        let idEliminar = null;
        window.confirmarEliminar = function (id) {
            idEliminar = id;
            $('#delete_password').val(''); // Limpiar anterior
            var modal = new bootstrap.Modal(document.getElementById('eliminaModal'));
            modal.show();
        };

        $('#btnConfirmarEliminar').on('click', function () {
            const password = $('#delete_password').val();
            if (!password) {
                Swal.fire('Atención', 'Debe ingresar su contraseña para confirmar la eliminación.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Eliminando...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'elimina.php',
                type: 'POST',
                data: { id: idEliminar, clave: password },
                dataType: 'json',
                success: function (resp) {
                    if (resp.success) {
                        Swal.fire('Eliminado', resp.message, 'success');
                        $('#eliminaModal').modal('hide');
                        table.ajax.reload(null, false); // Recargar sin resetear paginación
                    } else {
                        Swal.fire('Error', resp.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Hubo un problema de conexión con el servidor.', 'error');
                }
            });
        });

        // Escuchar cambio en filtro de vacios
        $('#filter_empty').on('change', function () {
            table.draw();
        });

        // Botón Limpiar Filtros tabla principal
        $('#btn_clear_main_filters').on('click', function () {
            $('#filter_empty').val('');
            table.search('').columns().search('').draw();
        });

        // ========================================================
        // MODAL EDITAR CONTRATO
        // ========================================================

        // Helper Location Logic JSON
        let editUbicacionesData = [];
        $.get('api_ubicaciones.php', function (data) {
            editUbicacionesData = data;
            let options = '<option value="">-- Seleccione Municipio --</option>';
            editUbicacionesData.forEach(function (m) {
                options += `<option value="${m.municipio}">${m.municipio}</option>`;
            });
            $('#edit_municipio').html(options);
        });

        // Helper: load parroquias into #edit_parroquia
        function editLoadParroquias(munName, selectedParName, onDone) {
            var $par = $('#edit_parroquia');
            $par.html('<option value="">Cargando...</option>').prop('disabled', true);
            $('#edit_comunidad').html('<option value="">-- Seleccione parroquia --</option>').prop('disabled', true);

            if (!munName) {
                $par.html('<option value="">-- Seleccione municipio --</option>');
                if (onDone) onDone();
                return;
            }

            let options = '<option value="">-- Seleccione parroquia --</option>';
            const munObj = editUbicacionesData.find(m => m.municipio === munName);
            if (munObj && munObj.parroquias) {
                munObj.parroquias.forEach(p => {
                    options += `<option value="${p.nombre}">${p.nombre}</option>`;
                });
            }
            $par.html(options).prop('disabled', false);

            if (selectedParName) {
                $par.val(selectedParName);
                editLoadComunidades(selectedParName, null, onDone);
            } else if (onDone) onDone();
        }

        // Helper: load comunidades into #edit_comunidad
        function editLoadComunidades(parName, selectedComName, onDone) {
            var $com = $('#edit_comunidad');
            $com.html('<option value="">Cargando...</option>').prop('disabled', true);

            if (!parName) {
                $com.html('<option value="">-- Seleccione parroquia --</option>');
                if (onDone) onDone();
                return;
            }

            const munName = $('#edit_municipio').val();
            let options = '<option value="">-- Seleccione comunidad --</option>';
            const munObj = editUbicacionesData.find(m => m.municipio === munName);
            if (munObj && munObj.parroquias) {
                const parObj = munObj.parroquias.find(p => p.nombre === parName);
                if (parObj && parObj.comunidades) {
                    parObj.comunidades.forEach(c => {
                        options += `<option value="${c}">${c}</option>`;
                    });
                }
            }
            $com.html(options).prop('disabled', false);

            if (selectedComName) $com.val(selectedComName);
            if (onDone) onDone();
        }

        // Helper: load PONs into #edit_pon
        function editLoadPons(oltId, selectedPonId, onDone) {
            var $pon = $('#edit_pon');
            $pon.html('<option value="">Cargando...</option>').prop('disabled', true);
            if (!oltId) { $pon.html('<option value="">-- Seleccione OLT --</option>'); if (onDone) onDone(); return; }
            $.get('gets_pon_by_olt.php', { id_olt: oltId }, function (resp) {
                $pon.html('<option value="">-- Seleccione PON --</option>').prop('disabled', false);
                if (resp.pons) {
                    $.each(resp.pons, function (i, p) {
                        $pon.append('<option value="' + p.id_pon + '">' + p.nombre_pon + '</option>');
                    });
                }
                if (selectedPonId) $pon.val(selectedPonId);
                if (onDone) onDone();
            }, 'json').fail(function () { $pon.html('<option value="">Error al cargar</option>'); if (onDone) onDone(); });
        }

        // Cascading selects: Municipio -> Parroquia -> Comunidad
        $('#edit_municipio').on('change', function () {
            editLoadParroquias($(this).val(), null, null);
        });
        $('#edit_parroquia').on('change', function () {
            editLoadComunidades($(this).val(), null, null);
        });
        $('#edit_olt').on('change', function () {
            editLoadPons($(this).val(), null, null);
        });

        // Tipo conexion visibility
        $('#edit_tipo_conexion').on('change', function () {
            var t = $(this).val();
            $('#edit_campos_ftth, #edit_campos_radio, .campo-edit-ftth, .campo-edit-radio').hide();
            $('#edit_campos_ftth :input, #edit_campos_radio :input, .campo-edit-ftth :input, .campo-edit-radio :input').prop('disabled', true);

            if (t && t.includes('FTTH')) {
                $('#edit_campos_ftth, .campo-edit-ftth').show();
                $('#edit_campos_ftth :input, .campo-edit-ftth :input').prop('disabled', false);
            } else if (t && t.includes('RADIO')) {
                $('#edit_campos_radio, .campo-edit-radio').show();
                $('#edit_campos_radio :input, .campo-edit-radio :input').prop('disabled', false);
            }
        });

        // Auto-fill monto_plan when plan selection changes in edit modal
        $('#edit_plan').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const monto = selectedOption.data('monto');
            if (monto !== undefined) {
                $('#edit_monto_plan').val(monto);
            }
        });

        // Open modal and populate with data

        /* ═══════════════════════ ACORDEÓN FINANCIERO ═══════════════════════ */
        window._balancePanelLoaded = {};

        window.cargarBalancePanel = function (panel) {
            const id = document.getElementById('editContratoId') ? document.getElementById('editContratoId').textContent : 0;
            if (!id || id === '-') return;
            if (window._balancePanelLoaded[panel]) return; // ya cargado
            window._balancePanelLoaded[panel] = true;

            const body  = document.getElementById('body_' + panel);
            const badge = document.getElementById('badge_' + panel);
            if (!body) return;

            // ── RESUMEN ──────────────────────────────────────────────────────
            if (panel === 'resumen') {
                fetch(`get_contract_balance.php?id=${id}&action=resumen`)
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { body.innerHTML = '<p class="text-danger">Error al cargar.</p>'; return; }

                        const colorTotal = d.tiene_deuda_activa ? 'danger' : 'success';
                        const saldoFavorHtml = d.tiene_saldo_favor
                            ? `<div class="col-md-4"><div class="card border-0 bg-info bg-opacity-10 text-center p-3 h-100">
                                <div class="small text-muted fw-bold">Saldo a Favor</div>
                                <h4 class="text-info fw-bold mb-0">$${parseFloat(d.saldo_a_favor).toFixed(2)}</h4>
                               </div></div>` : '';
                        const deudaHtml = d.tiene_deuda_activa
                            ? `<div class="col-md-4"><div class="card border-0 bg-danger bg-opacity-10 text-center p-3 h-100">
                                <div class="small text-muted fw-bold">Deuda Activa</div>
                                <h4 class="text-danger fw-bold mb-0">$${parseFloat(d.saldo_deuda).toFixed(2)}</h4>
                               </div></div>` : '';

                        body.innerHTML = `
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card border-0 bg-primary bg-opacity-10 text-center p-3 h-100">
                                    <div class="small text-muted fw-bold">Total Facturado</div>
                                    <h4 class="text-primary fw-bold mb-0">$${parseFloat(d.total_facturado).toFixed(2)}</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-success bg-opacity-10 text-center p-3 h-100">
                                    <div class="small text-muted fw-bold">Total Pagado</div>
                                    <h4 class="text-success fw-bold mb-0">$${parseFloat(d.total_pagado).toFixed(2)}</h4>
                                    <small class="text-muted">${d.meses_pagados} cobro(s) pagados</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-warning bg-opacity-10 text-center p-3 h-100">
                                    <div class="small text-muted fw-bold">Pendiente por Cobrar</div>
                                    <h4 class="text-warning fw-bold mb-0">$${parseFloat(d.total_pendiente).toFixed(2)}</h4>
                                </div>
                            </div>
                            ${saldoFavorHtml}
                            ${deudaHtml}
                        </div>`;

                        if (badge) badge.innerHTML = d.tiene_deuda_activa
                            ? '<span class="badge bg-danger">Con Deuda</span>'
                            : (d.tiene_saldo_favor ? '<span class="badge bg-info">Saldo a Favor</span>' : '<span class="badge bg-success">Al Día</span>');
                    }).catch(err => { body.innerHTML = `<p class="text-danger small"><b>Error:</b> ${err.message || err}</p>`; console.error('balance resumen:', err); });

            // ── HISTORIAL DE PAGOS ───────────────────────────────────────────
            } else if (panel === 'pagos') {
                fetch(`get_historial_pagos.php?id_contrato=${id}`)
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { body.innerHTML = '<p class="text-danger">Error al cargar.</p>'; return; }
                        if (!d.data.length) {
                            body.innerHTML = '<p class="text-muted text-center py-3">No hay pagos registrados.</p>';
                            badge.innerHTML = '<span class="badge bg-secondary">0 pagos</span>';
                            return;
                        }
                        badge.innerHTML = `<span class="badge bg-success">${d.data.length} pago(s)</span>`;
                        let rows = d.data.map(p => `
                            <tr>
                                <td>${p.fecha_pago || '—'}</td>
                                <td><code>${p.referencia_pago || '—'}</code></td>
                                <td class="small text-muted">${(p.justificacion || '').replace(/\[|\]/g, '').substring(0, 60)}</td>
                                <td class="fw-bold text-success text-end">$${parseFloat(p.monto_total).toFixed(2)}</td>
                            </tr>`).join('');
                        body.innerHTML = `<div class="table-responsive" style="max-height:250px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size:.85rem;">
                                <thead class="table-light sticky-top"><tr><th>Fecha Pago</th><th>Referencia</th><th>Concepto</th><th class="text-end">Monto</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table></div>`;
                    }).catch(err => { body.innerHTML = `<p class="text-danger small"><b>Error:</b> ${err.message || err}</p>`; console.error('balance pagos:', err); });

            // ── TODOS LOS MOVIMIENTOS ────────────────────────────────────────
            } else if (panel === 'movimientos') {
                fetch(`get_contract_balance.php?id=${id}&action=movimientos`)
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { body.innerHTML = '<p class="text-danger">Error al cargar.</p>'; return; }
                        if (!d.data.length) {
                            body.innerHTML = '<p class="text-muted text-center py-3">Sin movimientos registrados.</p>';
                            badge.innerHTML = '<span class="badge bg-secondary">0</span>';
                            return;
                        }
                        badge.innerHTML = `<span class="badge bg-info">${d.data.length} mov.</span>`;
                        const badgeColors = { PAGADO:'success', PENDIENTE:'warning', RECHAZADO:'secondary', ANULADO:'dark', 'ABONO PARCIAL':'info' };
                        let rows = d.data.map(m => {
                            const bc = badgeColors[m.estado] || 'light';
                            const fecha = m.fecha_pago || m.fecha_emision || '—';
                            return `<tr>
                                <td>${fecha}</td>
                                <td><code>${m.referencia_pago || '—'}</code></td>
                                <td class="small text-muted">${(m.concepto_limpio || '').substring(0, 55)}</td>
                                <td>${m.nombre_banco || '—'}</td>
                                <td><span class="badge bg-${bc}">${m.estado}</span></td>
                                <td class="fw-bold text-end">$${parseFloat(m.monto_total||0).toFixed(2)}</td>
                            </tr>`;
                        }).join('');
                        body.innerHTML = `<div class="table-responsive" style="max-height:300px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size:.82rem;">
                                <thead class="table-light sticky-top"><tr><th>Fecha</th><th>Ref.</th><th>Concepto</th><th>Banco</th><th>Estado</th><th class="text-end">Monto</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table></div>`;
                    }).catch(err => { body.innerHTML = `<p class="text-danger small"><b>Error:</b> ${err.message || err}</p>`; console.error('balance movimientos:', err); });

            // ── DEUDA ACTIVA ─────────────────────────────────────────────────
            } else if (panel === 'deuda') {
                fetch(`get_contract_balance.php?id=${id}&action=deuda`)
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { body.innerHTML = '<p class="text-danger">Error al cargar.</p>'; return; }
                        if (!d.data.length) {
                            body.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-circle-check me-2"></i>Sin deuda activa registrada.</div>';
                            badge.innerHTML = '<span class="badge bg-success">Sin deuda</span>';
                            return;
                        }
                        badge.innerHTML = `<span class="badge bg-danger">${d.data.length} deuda(s)</span>`;
                        let rows = d.data.map(d2 => `
                            <tr>
                                <td>${d2.fecha_registro}</td>
                                <td>$${parseFloat(d2.monto_total).toFixed(2)}</td>
                                <td class="text-success">$${parseFloat(d2.monto_pagado).toFixed(2)}</td>
                                <td class="text-danger fw-bold">$${parseFloat(d2.saldo_pendiente).toFixed(2)}</td>
                                <td><span class="badge bg-${d2.estado==='PENDIENTE'?'danger':'success'}">${d2.estado}</span></td>
                            </tr>`).join('');
                        body.innerHTML = `<div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size:.85rem;">
                                <thead class="table-light"><tr><th>Fecha</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table></div>`;
                    }).catch(err => { body.innerHTML = `<p class="text-danger small"><b>Error:</b> ${err.message || err}</p>`; console.error('balance deuda:', err); });
            }
        };
        /* ════════════ FIN ACORDEÓN FINANCIERO ════════════ */

        window.abrirModalEdicion = function (id) {

            // Reset state
            $('#editContratoAlert').addClass('d-none').removeClass('alert-danger alert-success').html('');
            $('#editContratoSpinner').show();
            $('#formEditarContrato').addClass('d-none');
            $('#btnGuardarContrato').prop('disabled', true);
            $('#editContratoId').text(id);

            var modal = new bootstrap.Modal(document.getElementById('modalEditarContrato'));
            modal.show();

            $.getJSON('get_contrato_detalle.php?id=' + id, function (d) {
                if (d.error) {
                    $('#editContratoSpinner').hide();
                    $('#editContratoAlert').removeClass('d-none').addClass('alert-danger').html('Error: ' + d.error);
                    return;
                }

                // Populate simple fields
                $('#edit_id').val(d.id);
                $('#edit_cedula').val(d.cedula);
                $('#edit_nombre').val(d.nombre_completo);
                $('#edit_telefono').val(d.telefono);
                $('#edit_telefono2').val(d.telefono_secundario);
                $('#edit_correo').val(d.correo);
                $('#edit_correo2').val(d.correo_adicional);
                $('#edit_fecha').val(d.fecha_instalacion);
                $('#edit_direccion').val(d.direccion);
                $('#edit_estado').val(d.estado);
                $('#edit_sae_plus').val(d.sae_plus);
                $('#edit_observaciones').val(d.observaciones);

                // --- COSTOS Y PAGOS ---
                $('#edit_monto_pagar').val(d.monto_pagar || 0);
                $('#edit_monto_pagado').val(d.monto_pagado || 0);
                // --- INSTALADORES ---
                $.get('json_personal_api.php?action=get_instaladores', function (insts) {
                    let opts = '<option value="">-- Seleccione --</option>';
                    if (insts && insts.length > 0) {
                        insts.forEach(i => {
                            opts += `<option value="${i}">${i}</option>`;
                        });
                    }
                    $('#edit_instalador_ftth').html(opts).val(d.instalador || '');
                    $('#edit_instalador_radio').html(opts).val(d.instalador_c || '');
                });

                // --- FIRMAS ---
                resetEditPadsWorkflows();

                // Cliente
                if (d.firma_cliente) {
                    $('#prev_firma_cliente').attr('src', '../../uploads/firmas/' + d.firma_cliente).removeClass('d-none');
                    $('#no_firma_cliente').addClass('d-none');
                } else {
                    $('#prev_firma_cliente').addClass('d-none');
                    $('#no_firma_cliente').removeClass('d-none');
                }

                // Técnico
                if (d.firma_tecnico) {
                    $('#prev_firma_tecnico').attr('src', '../../uploads/firmas/' + d.firma_tecnico).removeClass('d-none');
                    $('#no_firma_tecnico').addClass('d-none');
                } else {
                    $('#prev_firma_tecnico').addClass('d-none');
                    $('#no_firma_tecnico').removeClass('d-none');
                }
                $('#edit_plan').val(d.id_plan);
                $('#edit_monto_plan').val(d.monto_plan || 0);

                // Load vendedores dynamically and set value
                $.get('json_personal_api.php?action=get_vendedores', function (vends) {
                    let opts = '<option value="">-- Seleccione --</option>';
                    if (vends && vends.length > 0) {
                        vends.forEach(v => {
                            opts += `<option value="${v}">${v}</option>`;
                        });
                    }
                    $('#edit_vendedor').html(opts).val(d.vendedor_texto || '');
                });

                $('#edit_nap').val(d.ident_caja_nap);
                $('#edit_puerto_nap').val(d.puerto_nap);
                $('#edit_odn').val(d.num_presinto_odn);
                $('#edit_mac').val(d.mac_onu);

                $('#edit_ip_onu').val(d.ip_onu);
                $('#edit_ip_radio').val(d.ip_servicio || '');
                $('#edit_nap_tx').val(d.nap_tx_power);
                $('#edit_onu_rx').val(d.onu_rx_power);
                $('#edit_drop').val(d.distancia_drop);
                $('#edit_pa').val(d.punto_acceso);
                $('#edit_dbm').val(d.valor_conexion_dbm);
                $('#edit_observaciones').val(d.observaciones);

                // --- PREVISUALIZACIÓN DE IMÁGENES ---
                const setPreview = (imgId, noId, path) => {
                    const $img = $('#' + imgId);
                    const $no = $('#' + noId);
                    if (path && path !== 'null') {
                        $img.attr('src', '../../' + path).show();
                        $no.hide();
                    } else {
                        $img.hide();
                        $no.show();
                    }
                };

                setPreview('prev_evidencia_foto', 'no_evidencia_foto', d.evidencia_foto);
                setPreview('prev_evidencia_documento', 'no_evidencia_documento', d.evidencia_documento);
                
                $('#edit_evidencia_fibra').val(d.evidencia_fibra || '');

                // Tipo conexion e instalacion
                $('#edit_tipo_conexion').val(d.tipo_conexion).trigger('change');
                $('#edit_tipo_instalacion').val(d.tipo_instalacion || '');

                // --- PRORRATEO (NUEVO) ---
                $('#edit_dias_prorrateo').val(d.dias_prorrateo || 0);
                $('#edit_monto_prorrateo').val(d.monto_prorrateo_usd || '0.00');

                // Load plans into edit select then set value
                $.get('json_personal_api.php?action=get_planes_prorrateo', function (planes) {
                    let opts = '<option value="">-- Seleccione --</option>';
                    planes.forEach(p => {
                        opts += `<option value="${p.nombre}" data-precio="${p.precio}">${p.nombre} - $${p.precio}</option>`;
                    });
                    $('#edit_plan_prorrateo').html(opts).val(d.plan_prorrateo_nombre);
                });

                // Event calculating on the fly
                $('#edit_plan_prorrateo, #edit_dias_prorrateo').off('change.calc').on('change.calc', function () {
                    const price = parseFloat($('#edit_plan_prorrateo option:selected').data('precio')) || 0;
                    const days = parseInt($('#edit_dias_prorrateo').val()) || 0;
                    const result = (price / 30) * days;
                    $('#edit_monto_prorrateo').val(result.toFixed(2));
                });



                // Cascading location (Using Text Names from Join)
                $('#edit_municipio').val(d.nombre_municipio);
                editLoadParroquias(d.nombre_municipio, d.nombre_parroquia, function () {
                    if (d.nombre_comunidad) {
                        editLoadComunidades(d.nombre_parroquia, d.nombre_comunidad, function () {
                            // Finally OLT
                            finishLoadingModal();
                        });
                    } else {
                        finishLoadingModal();
                    }
                });

                function finishLoadingModal() {
                    $('#edit_olt').val(d.id_olt);
                    editLoadPons(d.id_olt, d.id_pon, function () {
                        $('#editContratoSpinner').hide();
                        $('#formEditarContrato').removeClass('d-none');
                        $('#btnGuardarContrato').prop('disabled', false);
                    });
                }
            }).fail(function () {
                $('#editContratoSpinner').hide();
                $('#editContratoAlert').removeClass('d-none').addClass('alert-danger').html('Error al cargar los datos del contrato.');
            });
        };

        // Double-click on table row to open edit modal
        $('#mitabla tbody').on('dblclick', 'tr', function () {
            var rowData = table.row(this).data();
            if (rowData && rowData[0]) {
                abrirModalEdicion(rowData[0]);
            }
        });

        // ========================================================
        // VALIDACIÓN DE CAMPOS EN MODAL (RERQUERIMIENTO USUARIO)
        // ========================================================
        $('#edit_cedula').on('input', function () {
            let val = $(this).val().toUpperCase().replace(/[^VJEGP0-9]/g, '');
            $(this).val(val);
        });

        $('#edit_ip, #edit_ip_onu, #edit_ip_radio').on('input', function () {
            let val = $(this).val().replace(/[^0-9.]/g, '');
            $(this).val(val);
        });

        $('#edit_nombre').on('input', function () {
            let val = $(this).val().replace(/[^A-Za-zñÑáéíóúÁÉÍÓÚ\s]/g, '');
            $(this).val(val);
        });

        $('#edit_telefono, #edit_telefono2').on('input', function () {
            let val = $(this).val().replace(/[^0-9-+\s]/g, '');
            $(this).val(val);
        });

        $('#edit_mac').on('input', function () {
            let val = $(this).val().toUpperCase().replace(/[^A-F0-9:.-]/g, '');
            $(this).val(val);
        });

        $('#edit_nap_tx, #edit_onu_rx, #edit_dbm').on('input', function () {
            let val = $(this).val().replace(/[^0-9.-]/g, '');
            // Only allow one '-' at the beginning
            if (val.indexOf('-') > 0) val = val.substring(0, val.indexOf('-')) + val.substring(val.indexOf('-') + 1);
            $(this).val(val);
        });

        // Save via AJAX
        $('#btnGuardarContrato').on('click', function () {
            var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');
            prepareSignaturesForSubmit();
            const formData = new FormData(document.getElementById('formEditarContrato'));

            $.ajax({
                url: 'actualizar_contrato_ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    var $alert = $('#editContratoAlert').removeClass('d-none alert-danger alert-success');
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Guardado!',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        if (typeof table !== 'undefined') table.ajax.reload(null, false);
                        else $('#mitabla').DataTable().ajax.reload(null, false);

                        setTimeout(() => {
                            var m = document.getElementById('modalEditarContrato');
                            var bsModal = bootstrap.Modal.getInstance(m);
                            if (bsModal) bsModal.hide();
                        }, 1200);
                    } else {
                        $alert.addClass('alert-danger').html('<i class="fa-solid fa-circle-exclamation me-1"></i>' + res.message);
                    }
                },
                error: function () {
                    $('#editContratoAlert').removeClass('d-none').addClass('alert-danger').html('Error de conexión con el servidor.');
                },
                complete: function () {
                    $('#btnGuardarContrato').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk me-1"></i>Guardar Cambios');
                }
            });
        });

        // ========================================================
        // GESTIÓN DE FIRMA REMOTA
        // ========================================================
        window.gestionarFirma = function (id, token, estado) {
            const baseUrl = window.location.origin + window.location.pathname.split('/paginas/')[0] + '/paginas/soporte/firmar_remoto.php';

            if (token && estado === 'PENDIENTE') {
                const link = `${baseUrl}?token=${token}&type=contrato`;

                Swal.fire({
                    title: 'Enlace de Firma Pendiente',
                    html: `
                    <p>Existe un proceso de firma activo para este contrato.</p>
                    <div class="input-group mb-3">
                        <input type="text" id="linkFirma" class="form-control" value="${link}" readonly>
                        <button class="btn btn-outline-primary" onclick="copiarLinkFirma()">
                            <i class="fa-solid fa-copy"></i> Copiar
                        </button>
                    </div>
                    <p class="small text-muted">Envía este link al cliente por WhatsApp.</p>
                `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-rotate"></i> Regenerar Link',
                    confirmButtonColor: '#ffc107',
                    cancelButtonText: 'Cerrar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        solicitarNuevoToken(id);
                    }
                });
            } else {
                const msj = (estado === 'COMPLETADO') ? 'Este contrato ya ha sido firmado.' : 'No existe un link de firma activo para este contrato.';

                Swal.fire({
                    title: 'Gestión de Firma',
                    text: msj,
                    icon: (estado === 'COMPLETADO') ? 'success' : 'info',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-plus"></i> Generar Nuevo Link',
                    cancelButtonText: 'Cerrar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        solicitarNuevoToken(id);
                    }
                });
            }
        };

        window.solicitarNuevoToken = function (id) {
            Swal.fire({
                title: 'Generando...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.post('generar_token_firma.php', { id: id }, function (resp) {
                if (resp.success) {
                    const baseUrl = window.location.origin + window.location.pathname.split('/paginas/')[0] + '/paginas/soporte/firmar_remoto.php';
                    const link = `${baseUrl}?token=${resp.token}&type=contrato`;

                    Swal.fire({
                        title: '¡Link Generado!',
                        html: `
                        <p>Se ha creado un nuevo enlace de firma:</p>
                        <div class="input-group mb-3">
                            <input type="text" id="linkFirma" class="form-control" value="${link}" readonly>
                            <button class="btn btn-outline-primary" onclick="copiarLinkFirma()">
                                <i class="fa-solid fa-copy"></i> Copiar
                            </button>
                        </div>
                    `,
                        icon: 'success',
                        confirmButtonText: 'Listo'
                    }).then(() => {
                        // Recargar tabla para que el nuevo token se refleje en el botón
                        $('#mitabla').DataTable().ajax.reload(null, false);
                    });
                } else {
                    Swal.fire('Error', resp.message, 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            });
        };

        window.copiarLinkFirma = function () {
            const input = document.getElementById('linkFirma');
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Copiado al portapapeles',
                    showConfirmButton: false,
                    timer: 2000
                });
            });
        };

    });
</script>

<?php require_once '../includes/layout_foot.php'; ?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ==========================================
    // GESTIÓN GENÉRICA LISTAS (Instaladores / Vendedores / Tipos)
    // ==========================================

    // --- INSTALADORES ---
    let instaladoresData = [];
    let editInstaladorIndex = -1;

    function loadInstaladores() {
        console.log("Cargando instaladores...");
        $.ajax({
            url: 'json_personal_api.php?action=get_instaladores',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                console.log("Instaladores recibidos:", data);
                instaladoresData = Array.isArray(data) ? data : [];
                renderInstaladoresList();
            },
            error: function (xhr, status, error) {
                console.error("Error cargando instaladores:", error);
                instaladoresData = [];
                renderInstaladoresList();
            }
        });
    }

    function renderInstaladoresList() {
        const list = $('#listInstaladores');
        list.empty();
        if (instaladoresData.length === 0) {
            list.html('<div class="text-center text-muted p-2">Sin registros</div>');
            return;
        }
        instaladoresData.forEach((item, index) => {
            const row = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${item}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editInstalador(${index})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-danger py-0 px-2" onclick="deleteInstalador(${index})" title="Eliminar"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>`;
            list.append(row);
        });
    }

    window.addInstalador = async function () {
        const nombre = $('#newInstalador').val().trim().toUpperCase();
        if (!nombre) return;

        // Validar que instaladoresData sea un array antes de usar .includes
        if (!Array.isArray(instaladoresData)) {
            console.error("instaladoresData no es un array, reintentando carga...");
            loadInstaladores();
            return;
        }

        if (editInstaladorIndex === -1 && instaladoresData.includes(nombre)) {
            Swal.fire({ target: document.getElementById('modalInstaladores'), title: 'Atención', text: 'Este instalador ya existe', icon: 'warning' });
            return;
        }

        const actionLabel = (editInstaladorIndex > -1) ? 'Actualizar Instalador' : 'Agregar Instalador';
        const ok = await verificarClave(actionLabel, document.getElementById('modalInstaladores'));
        if (!ok) return;

        if (editInstaladorIndex > -1) {
            instaladoresData[editInstaladorIndex] = nombre;
            cancelEditInstalador();
        } else {
            instaladoresData.push(nombre);
            $('#newInstalador').val('');
        }

        saveInstaladores();
        renderInstaladoresList();
    };

    window.editInstalador = function (index) {
        editInstaladorIndex = index;
        $('#newInstalador').val(instaladoresData[index]);
        $('#btnAddInstalador').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar');
        $('#iconInstaladorAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditInstalador').removeClass('d-none');
    };

    window.cancelEditInstalador = function () {
        editInstaladorIndex = -1;
        $('#newInstalador').val('');
        $('#btnAddInstalador').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar');
        $('#iconInstaladorAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditInstalador').addClass('d-none');
    };

    window.deleteInstalador = async function (index) {
        const nombre = instaladoresData[index];

        try {
            const usageResp = await fetch(`verificar_uso_ubicacion.php?tipo=instalador&nombre=${encodeURIComponent(nombre)}`);
            const usageData = await usageResp.json();
            if (usageData.usage > 0) {
                Swal.fire({
                    target: document.getElementById('modalInstaladores'),
                    title: 'No se puede eliminar',
                    text: `El instalador "${nombre}" está asignado a ${usageData.usage} contrato(s). No puede ser eliminado de la lista mientras esté en uso.`,
                    icon: 'error'
                });
                return;
            }
        } catch (err) { console.error("Error validando uso:", err); }

        const ok = await verificarClave('Eliminar Instalador: ' + nombre, document.getElementById('modalInstaladores'));
        if (!ok) return;

        instaladoresData.splice(index, 1);
        saveInstaladores();
        renderInstaladoresList();
        Swal.fire({ target: document.getElementById('modalInstaladores'), title: 'Eliminado', icon: 'success', timer: 1000, showConfirmButton: false });
    };

    function saveInstaladores() {
        $.ajax({
            url: 'json_personal_api.php?action=save_instaladores',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(instaladoresData),
            success: function () { _pageNeedsReload = true; },
            error: function () { Swal.fire('Error', 'No se pudo guardar', 'error'); }
        });
    }

    // --- VENDEDORES ---
    let vendedoresData = [];
    let editVendedorIndex = -1;

    function loadVendedores() {
        console.log("Cargando vendedores...");
        $.ajax({
            url: 'json_personal_api.php?action=get_vendedores',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                console.log("Vendedores recibidos:", data);
                vendedoresData = Array.isArray(data) ? data : [];
                renderVendedoresList();
            },
            error: function (xhr, status, error) {
                console.error("Error cargando vendedores:", error);
                vendedoresData = [];
                renderVendedoresList();
            }
        });
    }

    function renderVendedoresList() {
        const list = $('#listVendedores');
        list.empty();
        if (vendedoresData.length === 0) {
            list.html('<div class="text-center text-muted p-2">Sin registros</div>');
            return;
        }
        vendedoresData.forEach((item, index) => {
            const row = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${item}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editVendedor(${index})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-danger py-0 px-2" onclick="deleteVendedor(${index})" title="Eliminar"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>`;
            list.append(row);
        });
    }

    window.addVendedor = async function () {
        const nombre = $('#newVendedor').val().trim().toUpperCase();
        if (!nombre) return;

        if (!Array.isArray(vendedoresData)) {
            console.error("vendedoresData no es un array, reintentando carga...");
            loadVendedores();
            return;
        }

        if (editVendedorIndex === -1 && vendedoresData.includes(nombre)) {
            Swal.fire({ target: document.getElementById('modalVendedores'), title: 'Atención', text: 'Este vendedor ya existe', icon: 'warning' });
            return;
        }

        const actionLabel = (editVendedorIndex > -1) ? 'Actualizar Vendedor' : 'Agregar Vendedor';
        const ok = await verificarClave(actionLabel, document.getElementById('modalVendedores'));
        if (!ok) return;

        if (editVendedorIndex > -1) {
            vendedoresData[editVendedorIndex] = nombre;
            cancelEditVendedor();
        } else {
            vendedoresData.push(nombre);
            $('#newVendedor').val('');
        }

        saveVendedores();
        renderVendedoresList();
    };

    window.editVendedor = function (index) {
        editVendedorIndex = index;
        $('#newVendedor').val(vendedoresData[index]);
        $('#btnAddVendedor').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar');
        $('#iconVendedorAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditVendedor').removeClass('d-none');
    };

    window.cancelEditVendedor = function () {
        editVendedorIndex = -1;
        $('#newVendedor').val('');
        $('#btnAddVendedor').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar');
        $('#iconVendedorAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditVendedor').addClass('d-none');
    };

    window.deleteVendedor = async function (index) {
        const nombre = vendedoresData[index];

        try {
            const usageResp = await fetch(`verificar_uso_ubicacion.php?tipo=vendedor&nombre=${encodeURIComponent(nombre)}`);
            const usageData = await usageResp.json();
            if (usageData.usage > 0) {
                Swal.fire({
                    target: document.getElementById('modalVendedores'),
                    title: 'No se puede eliminar',
                    text: `El vendedor "${nombre}" está asignado a ${usageData.usage} contrato(s). No puede ser eliminado de la lista mientras esté en uso.`,
                    icon: 'error'
                });
                return;
            }
        } catch (err) { console.error("Error validando uso:", err); }

        const ok = await verificarClave('Eliminar Vendedor: ' + nombre, document.getElementById('modalVendedores'));
        if (!ok) return;

        vendedoresData.splice(index, 1);
        saveVendedores();
        renderVendedoresList();
        Swal.fire({ target: document.getElementById('modalVendedores'), title: 'Eliminado', icon: 'success', timer: 1000, showConfirmButton: false });
    };

    function saveVendedores() {
        $.ajax({
            url: 'json_personal_api.php?action=save_vendedores',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(vendedoresData),
            success: function () { _pageNeedsReload = true; },
            error: function () { Swal.fire('Error', 'No se pudo guardar', 'error'); }
        });
    }

    // --- PLANES PRORRATEO ---
    let prorrateoData = [];
    let editPlanIndex = -1;
    let _pageNeedsReload = false; // Flag: recarga si hubo cambios en vendedores/prorrateo

    function loadPlanesProrrateo() {
        console.log("Cargando planes prorrateo...");
        $.ajax({
            url: 'json_personal_api.php?action=get_planes_prorrateo',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                console.log("Planes prorrateo recibidos:", data);
                prorrateoData = Array.isArray(data) ? data : [];
                renderProrrateoList();
            },
            error: function (xhr, status, error) {
                console.error("Error cargando planes prorrateo:", error);
                prorrateoData = [];
                renderProrrateoList();
            }
        });
    }

    function renderProrrateoList() {
        const list = $('#listProrrateo');
        list.empty();
        if (prorrateoData.length === 0) {
            list.html('<div class="text-center text-muted p-2">Sin registros</div>');
            return;
        }
        prorrateoData.forEach((item, index) => {
            const row = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${item.nombre} - $${item.precio}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editPlanProrrateo(${index})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-danger py-0 px-2" onclick="deletePlanProrrateo(${index})" title="Eliminar"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>`;
            list.append(row);
        });
    }

    window.addPlanProrrateo = async function () {
        const nombre = $('#newPlanNombre').val().trim();
        const precio = $('#newPlanPrecio').val().trim();

        if (!nombre || !precio) {
            Swal.fire({ target: document.getElementById('modalProrrateo'), title: 'Error', text: 'Ingrese nombre y precio', icon: 'warning' });
            return;
        }

        // Requiere clave para Agregar o Editar
        const actionLabel = (editPlanIndex > -1) ? 'Actualizar Plan' : 'Agregar Plan';
        const ok = await verificarClave(actionLabel, document.getElementById('modalProrrateo'));
        if (!ok) return;

        if (editPlanIndex > -1) {
            // Actualizar
            prorrateoData[editPlanIndex] = { nombre: nombre, precio: precio };
            cancelEditPlan(); // Resetear UI y editPlanIndex
        } else {
            // Agregar nuevo
            prorrateoData.push({ nombre: nombre, precio: precio });
            $('#newPlanNombre').val('');
            $('#newPlanPrecio').val('');
        }

        savePlanesProrrateo();
        renderProrrateoList();
    };

    window.editPlanProrrateo = function (index) {
        editPlanIndex = index;
        const plan = prorrateoData[index];
        $('#newPlanNombre').val(plan.nombre);
        $('#newPlanPrecio').val(plan.precio);

        // Cambiar UI a modo edición
        $('#btnAddPlanProrrateo').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar Plan');
        $('#iconPlanAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditPlan').removeClass('d-none');
    };

    window.cancelEditPlan = function () {
        editPlanIndex = -1;
        $('#newPlanNombre').val('');
        $('#newPlanPrecio').val('');

        // Resetear UI a modo agregar
        $('#btnAddPlanProrrateo').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar Plan');
        $('#iconPlanAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditPlan').addClass('d-none');
    };

    window.deletePlanProrrateo = async function (index) {
        const nombre = prorrateoData[index].nombre;

        // 1. Check usage first
        try {
            const usageResp = await fetch(`verificar_uso_ubicacion.php?tipo=plan_prorrateo&nombre=${encodeURIComponent(nombre)}`);
            const usageData = await usageResp.json();
            if (usageData.usage > 0) {
                const { isConfirmed } = await Swal.fire({
                    target: document.getElementById('modalProrrateo'),
                    title: '¿Eliminar Plan en Uso?',
                    text: `El plan "${nombre}" está asignado a ${usageData.usage} contratos. Al eliminarlo de aquí, ya no podrá seleccionarse en nuevos contratos ni ediciones, pero los registros existentes mantendrán el dato como texto. ¿Desea continuar?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, continuar'
                });
                if (!isConfirmed) return;
            }
        } catch (err) { console.error("Error validando uso:", err); }

        const ok = await verificarClave('Eliminar Plan: ' + nombre, document.getElementById('modalProrrateo'));
        if (!ok) return;

        prorrateoData.splice(index, 1);
        savePlanesProrrateo();
        renderProrrateoList();
        Swal.fire({ target: document.getElementById('modalProrrateo'), title: 'Eliminado', icon: 'success', timer: 1000, showConfirmButton: false });
    };

    function savePlanesProrrateo() {
        $.ajax({
            url: 'json_personal_api.php?action=save_planes_prorrateo',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(prorrateoData),
            success: function () { _pageNeedsReload = true; },
            error: function () { Swal.fire('Error', 'No se pudo guardar', 'error'); }
        });
    }


    // --- TIPOS ---
    let tiposData = [];
    let editTipoIndex = -1;

    function loadTipos() {
        console.log("Cargando tipos de conexión...");
        $.ajax({
            url: 'api_tipos_conexion.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                console.log("Tipos de instalación recibidos:", data);
                tiposData = Array.isArray(data) ? data : [];
                renderTipos();
            },
            error: function (xhr, status, error) {
                console.error("Error cargando tipos de instalación:", error);
                tiposData = [];
                renderTipos();
            }
        });
    }

    window.renderTipos = function () {
        const list = $('#listTipos');
        list.empty();
        if (tiposData.length === 0) {
            list.html('<div class="text-center text-muted p-2">Sin registros</div>');
            return;
        }
        tiposData.forEach((t, index) => {
            const row = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${t}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editTipo(${index})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-danger py-0 px-2" onclick="deleteTipo(${index})" title="Eliminar"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>`;
            list.append(row);
        });
    };

    window.editTipo = function (index) {
        editTipoIndex = index;
        $('#newTipo').val(tiposData[index]);
        $('#btnAddTipo').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar');
        $('#iconTipoAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditTipo').removeClass('d-none');
    };

    window.cancelEditTipo = function () {
        editTipoIndex = -1;
        $('#newTipo').val('');
        $('#btnAddTipo').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar');
        $('#iconTipoAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditTipo').addClass('d-none');
    };

    window.deleteTipo = async function (index) {
        const nombre = tiposData[index];

        try {
            const usageResp = await fetch(`verificar_uso_ubicacion.php?tipo=tipo_conexion&nombre=${encodeURIComponent(nombre)}`);
            const usageData = await usageResp.json();
            if (usageData.usage > 0) {
                Swal.fire({
                    target: document.getElementById('modalTipos'),
                    title: 'No se puede eliminar',
                    text: `El tipo de conexión "${nombre}" está asignado a ${usageData.usage} contrato(s). No puede ser eliminado mientras esté en uso.`,
                    icon: 'error'
                });
                return;
            }
        } catch (err) { console.error("Error validando uso:", err); }

        const ok = await verificarClave('Eliminar Tipo: ' + nombre, document.getElementById('modalTipos'));
        if (!ok) return;

        tiposData.splice(index, 1);
        saveTipos();
        renderTipos();
        Swal.fire({ target: document.getElementById('modalTipos'), title: 'Eliminado', icon: 'success', timer: 1000, showConfirmButton: false });
    };

    function saveTipos() {
        $.ajax({
            url: 'api_tipos_conexion.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(tiposData),
            success: function () { _pageNeedsReload = true; },
            error: function () { Swal.fire('Error', 'No se pudo guardar los cambios', 'error'); }
        });
    }

    // --- TIPOS DE INSTALACION ---
    let tiposInstalacionData = [];
    let editTipoInstalacionIndex = -1;

    function loadTiposInstalacion() {
        $.ajax({
            url: 'api_tipos_instalacion.php', 
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                tiposInstalacionData = Array.isArray(data) ? data : [];
                renderTiposInstalacion();
            },
            error: function () {
                tiposInstalacionData = ["Nivel 1", "Nivel 2", "Nivel 3", "Mudanza", "Migración", "Onu", "Reactivación"];
                renderTiposInstalacion();
            }
        });
    }

    function renderTiposInstalacion() {
        const list = $('#listTiposInstalacion');
        list.empty();
        if (tiposInstalacionData.length === 0) {
            list.html('<div class="text-center text-muted p-2">Sin registros</div>');
            return;
        }
        tiposInstalacionData.forEach((t, index) => {
            const row = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${t}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editTipoInstalacion(${index})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-danger py-0 px-2" onclick="deleteTipoInstalacion(${index})" title="Eliminar"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>`;
            list.append(row);
        });
    }

    window.editTipoInstalacion = function (index) {
        editTipoInstalacionIndex = index;
        $('#newTipoInstalacion').val(tiposInstalacionData[index]);
        $('#btnAddTipoInstalacion').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar');
        $('#iconTipoInstalacionAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditTipoInstalacion').removeClass('d-none');
    };

    window.cancelEditTipoInstalacion = function () {
        editTipoInstalacionIndex = -1;
        $('#newTipoInstalacion').val('');
        $('#btnAddTipoInstalacion').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar');
        $('#iconTipoInstalacionAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditTipoInstalacion').addClass('d-none');
    };

    $('#btnAddTipoInstalacion').on('click', async function () {
        const nombre = $('#newTipoInstalacion').val().trim();
        if (!nombre) return;

        if (editTipoInstalacionIndex === -1 && tiposInstalacionData.includes(nombre)) {
            Swal.fire({ target: document.getElementById('modalTiposInstalacion'), title: 'Atención', text: 'Este tipo ya existe', icon: 'warning' });
            return;
        }

        const actionLabel = (editTipoInstalacionIndex > -1) ? 'Actualizar Tipo de Instalación' : 'Agregar Tipo de Instalación';
        const ok = await verificarClave(actionLabel, document.getElementById('modalTiposInstalacion'));
        if (!ok) return;

        if (editTipoInstalacionIndex > -1) {
            tiposInstalacionData[editTipoInstalacionIndex] = nombre;
            cancelEditTipoInstalacion();
        } else {
            tiposInstalacionData.push(nombre);
            $('#newTipoInstalacion').val('');
        }

        saveTiposInstalacion();
        renderTiposInstalacion();
    });

    $('#btnCancelEditTipoInstalacion').on('click', cancelEditTipoInstalacion);

    window.deleteTipoInstalacion = async function (index) {
        const nombre = tiposInstalacionData[index];

        const ok = await verificarClave('Eliminar Tipo de Instalación: ' + nombre, document.getElementById('modalTiposInstalacion'));
        if (!ok) return;

        tiposInstalacionData.splice(index, 1);
        saveTiposInstalacion();
        renderTiposInstalacion();
        Swal.fire({ target: document.getElementById('modalTiposInstalacion'), title: 'Eliminado', icon: 'success', timer: 1000, showConfirmButton: false });
    };

    function saveTiposInstalacion() {
        $.ajax({
            url: 'api_tipos_instalacion.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(tiposInstalacionData),
            success: function () { _pageNeedsReload = true; },
            error: function () { Swal.fire('Error', 'No se pudo guardar los cambios', 'error'); }
        });
    }

    // INITIALIZATION (Ensure it runs on modal open or startup)
    $('#modalTiposInstalacion').on('show.bs.modal', function () {
        if(tiposInstalacionData.length === 0) loadTiposInstalacion();
    });

    // ==========================================
    // LÓGICA GESTIÓN UBICACIONES (JSON)
    // ==========================================
    let ubicacionesData = [];
    let selectedMunicipioIndex = -1;
    let editMunicipioIndex = -1; // Nueva variable para edición inline
    let editParroquiaIndex = -1; // Nueva variable para edición inline

    function loadUbicaciones() {
        $.get('api_ubicaciones.php', function (data) {
            ubicacionesData = data;
            renderMunicipios();
            // Reset selección
            selectedMunicipioIndex = -1;
            $('#listParroquias').html('<div class="text-center text-muted p-3">Seleccione un municipio para ver sus parroquias</div>');
            $('#titleParroquias').text('Parroquias (Seleccione un Municipio)');
            $('#newParroquia, #btnAddParroquia').prop('disabled', true);
        });
    }

    // Exponer globalmente
    window.renderMunicipios = function () {
        const list = $('#listMunicipios');
        list.empty();
        ubicacionesData.forEach((m, index) => {
            const activeClass = (index === selectedMunicipioIndex) ? 'active' : '';
            const item = `
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center ${activeClass}" 
                     onclick="selectMunicipio(${index})" style="cursor: pointer;">
                    <span>${m.municipio}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editMunicipioMode(event, ${index})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-danger py-0 px-2" onclick="deleteMunicipio(event, ${index})" title="Eliminar"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>
            `;
            list.append(item);
        });
    };

    window.selectMunicipio = function (index) {
        selectedMunicipioIndex = index;
        renderMunicipios(); // Para actualizar clase active
        renderParroquias();

        $('#titleParroquias').text(`Parroquias de: ${ubicacionesData[index].municipio}`);
        $('#newParroquia, #btnAddParroquia').prop('disabled', false);
    };

    window.renderParroquias = function () {
        if (selectedMunicipioIndex === -1) return;

        const list = $('#listParroquias');
        list.empty();
        const parroquias = ubicacionesData[selectedMunicipioIndex].parroquias;

        if (parroquias.length === 0) {
            list.html('<div class="text-center text-muted p-2">Sin parroquias registradas</div>');
            return;
        }

        parroquias.forEach((p, pIndex) => {
            const pNombre = typeof p === 'object' ? p.nombre : p;
            const item = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${pNombre}</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="editParroquiaMode(${pIndex})" title="Editar"><i class="fa-solid fa-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="deleteParroquia(${pIndex})" title="Eliminar"><i class="fa-solid fa-trash-can"></i></button>
                    </div>
                </div>
            `;
            list.append(item);
        });
    };

    // Helper: verify password before sensitive action (Generic version)
    async function verificarClave(nombreAccion, targetModal = null) {
        console.log("Iniciando verificación para:", nombreAccion);
        const swalConfig = {
            title: `Confirmar Acción`,
            html: `<p class="text-muted small mb-2">Se requiere contraseña administrativa para: <b>${nombreAccion}</b></p>`
                + '<input id="swal-clave" type="password" class="swal2-input" placeholder="Contraseña">',
            icon: 'warning',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',
            confirmButtonText: 'Confirmar',
            focusConfirm: false,
            preConfirm: () => {
                const c = document.getElementById('swal-clave').value;
                if (!c) {
                    Swal.showValidationMessage('La contraseña es requerida');
                    return false;
                }
                return c;
            }
        };

        if (targetModal) swalConfig.target = targetModal;

        const { value: clave, isConfirmed } = await Swal.fire(swalConfig);

        console.log("Swal result:", { isConfirmed, hasClave: !!clave });
        if (!isConfirmed || !clave) return false;

        try {
            console.log("Enviando petición a verificar_clave.php...");
            const resp = await fetch('verificar_clave.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'clave=' + encodeURIComponent(clave)
            });
            const data = await resp.json();
            console.log("Respuesta servidor:", data);
            if (!data.success) {
                Swal.fire({ target: targetModal, title: 'Error', text: data.message, icon: 'error' });
                return false;
            }
            // Notificar éxito al usuario
            Swal.fire({
                target: targetModal,
                title: 'Verificado',
                text: 'Contraseña correcta, procediendo...',
                icon: 'success',
                timer: 1000,
                showConfirmButton: false
            });
            return true;
        } catch (err) {
            console.error("Error en verificarClave:", err);
            Swal.fire({ target: targetModal, title: 'Error', text: 'Error al verificar la contraseña: ' + err.message, icon: 'error' });
            return false;
        }
    }

    window.deleteMunicipio = async function (e, index) {
        e.stopPropagation();
        const nombre = ubicacionesData[index].municipio;

        // 1. Check usage first
        try {
            const usageResp = await fetch(`verificar_uso_ubicacion.php?tipo=municipio&nombre=${encodeURIComponent(nombre)}`);
            const usageData = await usageResp.json();
            if (usageData.usage > 0) {
                const { isConfirmed } = await Swal.fire({
                    target: document.getElementById('modalUbicaciones'),
                    title: '¿Eliminar Municipio en Uso?',
                    text: `El municipio "${nombre}" está asignado a ${usageData.usage} contratos. Al eliminarlo de aquí, ya no podrá seleccionarse en nuevos contratos ni ediciones, pero los registros existentes mantendrán su dato actual. ¿Desea continuar?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f89406'
                });
                if (!isConfirmed) return;
            }
        } catch (err) { console.error("Error validando uso:", err); }

        const ok = await verificarClave(nombre + ' y todas sus parroquias', document.getElementById('modalUbicaciones'));
        if (!ok) return;

        ubicacionesData.splice(index, 1);
        if (selectedMunicipioIndex === index) {
            selectedMunicipioIndex = -1;
            $('#listParroquias').empty();
            $('#newParroquia, #btnAddParroquia').prop('disabled', true);
        } else if (selectedMunicipioIndex > index) {
            selectedMunicipioIndex--;
        }
        saveData();
        renderMunicipios();
        Swal.fire({ target: document.getElementById('modalUbicaciones'), title: 'Eliminado', icon: 'success', timer: 1200, showConfirmButton: false });
    };

    window.deleteParroquia = async function (pIndex) {
        const pData = ubicacionesData[selectedMunicipioIndex].parroquias[pIndex];
        const nombre = typeof pData === 'object' ? pData.nombre : pData;

        // 1. Check usage first
        try {
            const usageResp = await fetch(`verificar_uso_ubicacion.php?tipo=parroquia&nombre=${encodeURIComponent(nombre)}`);
            const usageData = await usageResp.json();
            if (usageData.usage > 0) {
                const { isConfirmed } = await Swal.fire({
                    target: document.getElementById('modalUbicaciones'),
                    title: '¿Eliminar Parroquia en Uso?',
                    text: `La parroquia "${nombre}" está asignada a ${usageData.usage} contratos. ¿Desea continuar con la eliminación de esta opción?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#f89406'
                });
                if (!isConfirmed) return;
            }
        } catch (err) { console.error("Error validando uso:", err); }

        const ok = await verificarClave(nombre, document.getElementById('modalUbicaciones'));
        if (!ok) return;

        ubicacionesData[selectedMunicipioIndex].parroquias.splice(pIndex, 1);
        saveData();
        renderParroquias();
        Swal.fire({ target: document.getElementById('modalUbicaciones'), title: 'Eliminado', icon: 'success', timer: 1200, showConfirmButton: false });
    };

    // --- MODOS EDICIÓN UBICACIONES ---
    window.editMunicipioMode = function (e, index) {
        e.stopPropagation();
        editMunicipioIndex = index;
        $('#newMunicipio').val(ubicacionesData[index].municipio).focus();
        $('#btnAddMunicipio').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar Municipio');
        $('#iconMunAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditMun').removeClass('d-none');
    };

    window.cancelEditMun = function () {
        editMunicipioIndex = -1;
        $('#newMunicipio').val('');
        $('#btnAddMunicipio').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar Municipio');
        $('#iconMunAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditMun').addClass('d-none');
    };

    window.editParroquiaMode = function (index) {
        editParroquiaIndex = index;
        const pData = ubicacionesData[selectedMunicipioIndex].parroquias[index];
        const pNombre = typeof pData === 'object' ? pData.nombre : pData;
        $('#newParroquia').val(pNombre).focus();
        $('#btnAddParroquia').removeClass('btn-success').addClass('btn-info').attr('title', 'Actualizar Parroquia');
        $('#iconParAction').removeClass('fa-plus').addClass('fa-check');
        $('#btnCancelEditPar').removeClass('d-none');
    };

    window.cancelEditPar = function () {
        editParroquiaIndex = -1;
        $('#newParroquia').val('');
        $('#btnAddParroquia').removeClass('btn-info').addClass('btn-success').attr('title', 'Agregar Parroquia');
        $('#iconParAction').removeClass('fa-check').addClass('fa-plus');
        $('#btnCancelEditPar').addClass('d-none');
    };

    function saveData() {
        $.ajax({
            url: 'api_ubicaciones.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(ubicacionesData),
            success: function (response) { _pageNeedsReload = true; },
            error: function () {
                Swal.fire('Error', 'No se pudo guardar los cambios', 'error');
            }
        });
    }

    // ==========================================
    // EVENTS ON READY
    // ==========================================
    $(document).ready(function () {

        // --- TIPOS ---
        $('#modalTipos').on('show.bs.modal', function () {
            loadTipos();
        });

        // --- INSTALADORES / VENDEDORES ---
        $('#modalInstaladores').on('show.bs.modal', function () { loadInstaladores(); });
        $('#modalVendedores').on('show.bs.modal', function () { loadVendedores(); });
        $('#modalProrrateo').on('show.bs.modal', function () { loadPlanesProrrateo(); });

        // Recargar página al cerrar modal si hubo cambios en cascada
        $('#modalTipos, #modalInstaladores, #modalVendedores, #modalProrrateo').on('hidden.bs.modal', function () {
            if (_pageNeedsReload) { location.reload(); }
        });

        $('#btnAddTipo').click(async function () {
            const nombre = $('#newTipo').val().trim().toUpperCase();
            if (!nombre) return;

            if (editTipoIndex === -1 && tiposData.includes(nombre)) {
                Swal.fire({ target: document.getElementById('modalTipos'), title: 'Error', text: 'El tipo de conexión ya existe', icon: 'warning' });
                return;
            }

            const actionLabel = (editTipoIndex > -1) ? 'Actualizar Tipo' : 'Agregar Tipo';
            const ok = await verificarClave(actionLabel, document.getElementById('modalTipos'));
            if (!ok) return;

            if (editTipoIndex > -1) {
                tiposData[editTipoIndex] = nombre;
                cancelEditTipo();
            } else {
                tiposData.push(nombre);
                $('#newTipo').val('');
            }
            saveTipos();
            renderTipos();
        });

        $('#btnCancelEditTipo').click(function () { cancelEditTipo(); });

        // 0. Bloquear ingreso de negativos en campos de prorrateo
        $('#newPlanPrecio, #edit_dias_prorrateo').on('keydown', function (e) {
            if (e.key === '-' || e.key === 'e') e.preventDefault();
        }).on('input', function () {
            if ($(this).val() < 0) $(this).val(0);
        });

        // --- IMPORTAR EXCEL (AJAX) ---
        $('#formImportExcel').on('submit', function (e) {
            e.preventDefault();

            // Mostrar loading
            Swal.fire({
                title: 'Importando...',
                html: 'Procesando archivo, por favor espere.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData(this);

            fetch('importar_excel_contratos.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            title: 'Importación Exitosa',
                            html: `<p>${data.message}</p>
                               <ul class="text-start">
                                   <li>Actualizados: <b>${data.stats.updated}</b></li>
                                   <li>Nuevos: <b>${data.stats.inserted}</b></li>
                                   <li>Errores: <b class="text-danger">${data.stats.errors}</b></li>
                               </ul>`,
                            icon: 'success'
                        }).then(() => {
                            // Recargar la tabla o la página para ver cambios
                            location.reload();
                        });
                        $('#modalImportExcel').modal('hide');
                        $('#formImportExcel')[0].reset();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error de conexión al importar.', 'error');
                });
        });

        // --- UBICACIONES ---
        $('#modalUbicaciones').on('show.bs.modal', function () {
            loadUbicaciones();
        });
        $('#modalUbicaciones').on('hidden.bs.modal', function () {
            if (_pageNeedsReload) { location.reload(); }
        });

        $('#btnAddMunicipio').click(async function () {
            const nombre = $('#newMunicipio').val().trim();
            if (!nombre) return;

            if (editMunicipioIndex > -1) {
                // Modo Edición
                const oldNombre = ubicacionesData[editMunicipioIndex].municipio;
                const ok = await verificarClave('Actualizar Municipio: ' + nombre, document.getElementById('modalUbicaciones'));
                if (!ok) return;
                ubicacionesData[editMunicipioIndex].municipio = nombre;
                cancelEditMun();
                // Cascada: actualizar contratos en la BD
                await actualizarEnCascada('municipio', oldNombre, nombre);
            } else {
                // Modo Agregar
                if (ubicacionesData.some(m => m.municipio.toLowerCase() === nombre.toLowerCase())) {
                    Swal.fire({ target: document.getElementById('modalUbicaciones'), title: 'Error', text: 'El municipio ya existe', icon: 'warning' });
                    return;
                }
                const ok = await verificarClave('Agregar Municipio: ' + nombre, document.getElementById('modalUbicaciones'));
                if (!ok) return;
                ubicacionesData.push({ municipio: nombre, parroquias: [] });
                $('#newMunicipio').val('');
            }
            saveData();
            renderMunicipios();
        });

        $('#btnCancelEditMun').click(function () { cancelEditMun(); });

        $('#btnAddParroquia').click(async function () {
            const nombre = $('#newParroquia').val().trim();
            if (!nombre || selectedMunicipioIndex === -1) return;

            if (editParroquiaIndex > -1) {
                // Modo Edición
                const pDataPrev = ubicacionesData[selectedMunicipioIndex].parroquias[editParroquiaIndex];
                const oldNombrePar = typeof pDataPrev === 'object' ? pDataPrev.nombre : pDataPrev;
                const ok = await verificarClave('Actualizar Parroquia: ' + nombre, document.getElementById('modalUbicaciones'));
                if (!ok) return;
                if (typeof pDataPrev === 'object') {
                    pDataPrev.nombre = nombre;
                } else {
                    ubicacionesData[selectedMunicipioIndex].parroquias[editParroquiaIndex] = { nombre: nombre, comunidades: [] };
                }
                cancelEditPar();
                // Cascada: actualizar contratos en la BD
                await actualizarEnCascada('parroquia', oldNombrePar, nombre);
            } else {
                // Modo Agregar
                if (ubicacionesData[selectedMunicipioIndex].parroquias.some(p => (typeof p === 'object' ? p.nombre : p).toLowerCase() === nombre.toLowerCase())) {
                    Swal.fire({ target: document.getElementById('modalUbicaciones'), title: 'Error', text: 'La parroquia ya existe', icon: 'warning' });
                    return;
                }
                const ok = await verificarClave('Agregar Parroquia: ' + nombre, document.getElementById('modalUbicaciones'));
                if (!ok) return;
                ubicacionesData[selectedMunicipioIndex].parroquias.push({ nombre: nombre, comunidades: [] });
                $('#newParroquia').val('');
            }
            saveData();
            renderParroquias();
        });

        $('#btnCancelEditPar').click(function () { cancelEditPar(); });
    });

    // Helper: Actualización en cascada de ubicaciones en contratos
    async function actualizarEnCascada(tipo, oldValue, newValue) {
        if (oldValue === newValue) return;
        try {
            const resp = await fetch('actualizar_ubicacion_contratos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `tipo=${encodeURIComponent(tipo)}&old_value=${encodeURIComponent(oldValue)}&new_value=${encodeURIComponent(newValue)}`
            });
            const data = await resp.json();
            if (data.success && data.updated > 0) {
                Swal.fire({
                    target: document.getElementById('modalUbicaciones'),
                    icon: 'info',
                    title: 'Actualización en Cascada',
                    text: `Se actualizaron ${data.updated} contrato(s) con el nuevo nombre.`,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                _pageNeedsReload = true;
            }
        } catch (err) {
            console.error('Error en cascada:', err);
        }
    }
</script>