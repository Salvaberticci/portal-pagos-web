<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../conexion.php';

$path_to_root = "../../";
$page_title = "Gestión de Prórrogas";
$breadcrumb = ["Cobranzas"];
$back_url = "../menu.php";
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';

// Automated Maintenance: Avanzar Mes (Permanentes) y Limpiar Temporales Procesadas
// Se ejecuta silenciosamente al cargar la página para mantener la lista al día.

// 1. Avanzar mes para permanentes cuya fecha de corte ya pasó
$conn->query("UPDATE prorrogas 
              SET fecha_corte = DATE_ADD(fecha_corte, INTERVAL 1 MONTH) 
              WHERE prorroga_regular = 'SI' 
                AND fecha_corte < CURRENT_DATE");

// 2. Limpiar temporales que ya pasaron Y fueron procesadas (o simplemente ignorarlas en el SELECT si se prefiere)
// Por ahora borramos las que el cliente ya pagó y son temporales para no saturar.
$conn->query("DELETE FROM prorrogas 
              WHERE prorroga_regular = 'NO' 
                AND fecha_corte < DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY) 
                AND estado = 'PROCESADO'");

$planes = $conn->query("SELECT id_plan, nombre_plan FROM planes ORDER BY nombre_plan ASC");
?>

<link href="<?php echo $path_to_root; ?>css/datatables.min.css" rel="stylesheet">
<style>
    .form-section-title {
        font-size: 0.85rem;
        background: rgba(255,255,255,0.05);
        padding: 8px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #4facfe;
        border-left: 4px solid #4facfe;
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    .modal-header-internal {
        background: linear-gradient(135deg, #6c757d 0%, #343a40 100%);
    }

    .dropzone-area {
        border: 2px dashed #dee2e6;
        padding: 30px;
        text-align: center;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .dropzone-area:hover {
        border-color: #0d6efd;
        background: #f0f7ff;
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
                    <h4 class="fw-bold text-gradient mb-1">Centro de Prórrogas</h4>
                    <p class="text-muted small mb-0">Gestione solicitudes internas de prórroga.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-premium px-4 fw-bold" data-bs-toggle="modal"
                        data-bs-target="#modalInternal">
                        <i class="fa-solid fa-user-clock me-1"></i> Nueva Prórroga
                    </button>
                    <div class="vr mx-1 opacity-25"></div>
                    <button type="button" class="btn btn-glass btn-sm px-3" onclick="exportExcel()">
                        <i class="fa-solid fa-file-excel me-1 text-success"></i> Exportar
                    </button>
                    <button type="button" class="btn btn-glass btn-sm px-3" data-bs-toggle="modal"
                        data-bs-target="#modalImportExcel">
                        <i class="fa-solid fa-file-import me-1 text-primary"></i> Importar
                    </button>
                </div>
            </div>
            
            <div class="p-4 pt-2 border-bottom border-white border-opacity-5">
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-glass btn-xs py-1" onclick="limpiarTemporales()">
                        <i class="fa-solid fa-broom me-1"></i> Limpiar Temporales
                    </button>
                    <button type="button" class="btn btn-glass btn-xs py-1" onclick="avanzarMesPermanentes()">
                        <i class="fa-solid fa-rotate me-1"></i> Forzar Avance
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tabla_prorrogas" class="table table-hover align-middle w-100 mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Fecha Reg.</th>
                                <th>Cliente / Titular</th>
                                <th>Regular?</th>
                                <th>Corte</th>
                                <th>ID SAE Plus</th>
                                <th>Estado / Pago</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Interno: Galanet-Prórroga -->
<div class="modal fade" id="modalInternal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <div>
                    <h5 class="modal-title fw-bold text-white">Galanet-Prórroga</h5>
                    <small class="text-white opacity-75">Solicitud Interna de Extensión</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formInternal" action="guardar_prorroga.php" method="POST">
                <input type="hidden" name="tipo_solicitud" value="PRORROGA">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm small py-2 mb-4">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        Los datos solicitados serán utilizados para cargar la solicitud de prórroga. Asegúrese de
                        ingresar la información exacta del titular.
                    </div>

                    <h6 class="form-section-title fw-bold text-uppercase">Datos del Titular del Servicio</h6>

                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-bold">Vincular Contrato Existente (Opcional)</label>
                        <input type="text" id="search_contrato_internal" class="form-control form-control-sm"
                            placeholder="Buscar por Nombre o ID...">
                        <input type="hidden" name="id_contrato_asociado" id="id_contrato_internal">
                        <div id="results_contrato_internal" class="list-group position-absolute w-100 shadow-sm"
                            style="z-index:1000"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cédula de Identidad *</label>
                        <input type="text" name="cedula_titular" class="form-control" placeholder="Ejemplo: 12345678"
                            required pattern="[0-9]+">
                        <div class="form-text xsmall text-danger">⚠️ Colocar la cédula sin espacios ni puntos "." solo
                            números.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombres y Apellidos *</label>
                        <input type="text" name="nombre_titular" class="form-control" placeholder="Titular del Servicio"
                            required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Fecha del Corte *</label>
                            <input type="date" name="fecha_corte" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">¿Existe en SAEPLUS? *</label>
                            <select name="existe_saeplus" class="form-select" required>
                                <option value="SI">SÍ</option>
                                <option value="NO" selected>NO</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">¿La Prórroga es Regular? *</label>
                            <select name="prorroga_regular" class="form-select" required>
                                <option value="SI" selected>SÍ</option>
                                <option value="NO">NO</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">ID SAE Plus</label>
                        <input type="text" name="codigo_sae_plus" class="form-control" placeholder="Ej: 12345" maxlength="50">
                        <div class="form-text text-muted small">Código del cliente en el sistema SAE Plus (opcional).</div>
                    </div>

                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-glass px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow fw-bold">Registrar Prórroga</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal Editar Prórroga -->
<div class="modal" id="modalEditarProrroga" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <h5 class="modal-title fw-bold text-white">Editar Prórroga</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditar" action="editar_prorroga.php" method="POST">
                <input type="hidden" name="id_prorroga" id="edit_id_prorroga">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre del Titular</label>
                        <input type="text" name="nombre_titular" id="edit_nombre_titular" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cédula</label>
                        <input type="text" name="cedula_titular" id="edit_cedula_titular" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Fecha de Corte</label>
                        <input type="date" name="fecha_corte" id="edit_fecha_corte" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">¿Es Permanente (Regular)?</label>
                        <select name="prorroga_regular" id="edit_prorroga_regular" class="form-select" required>
                            <option value="SI">SÍ (Permanente)</option>
                            <option value="NO">NO (Temporal)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ID SAE Plus</label>
                        <input type="text" name="codigo_sae_plus" id="edit_codigo_sae_plus" class="form-control" placeholder="Ej: 12345" maxlength="50">
                    </div>
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<!-- Modal Procesar Pago -->
<div class="modal fade" id="modalProcesarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <h5 class="modal-title fw-bold text-white">Convertir en Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="procesar_prorroga_pago.php" method="POST">
                <input type="hidden" name="id_prorroga" id="pago_id_prorroga">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-money-bill-transfer fa-3x text-success mb-3"></i>
                        <h6 class="text-muted small fw-bold text-uppercase mb-1">Solicitud de <span
                                id="pago_tipo"></span></h6>
                        <h5 class="fw-bold" id="pago_nombre_titular"></h5>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monto a Cobrar ($) *</label>
                        <input type="number" step="0.01" name="monto"
                            class="form-control form-control-lg fw-bold text-success" required placeholder="0.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Referencia de Pago *</label>
                        <input type="text" name="referencia" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Banco / Cuenta *</label>
                        <select name="id_banco" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php
                            $b = $conn->query("SELECT id_banco, nombre_banco FROM bancos");
                            while ($r = $b->fetch_assoc())
                                echo "<option value='" . $r['id_banco'] . "'>" . $r['nombre_banco'] . "</option>";
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Justificación / Nota</label>
                        <textarea name="nota" class="form-control" rows="2"
                            placeholder="Ej: Pago de prórroga aprobada"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Confirmar y Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Importar Excel -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header modal-header-gradient border-0">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-file-import me-2"></i>Importar desde Excel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="dropzone-excel" class="dropzone-area mb-3">
                    <i class="fa-solid fa-file-circle-plus fa-3x text-success mb-3"></i>
                    <h6>Arrastra tu archivo Excel aquí</h6>
                    <p class="text-muted small">O haz clic para seleccionar (Formato .xlsx, .xls)</p>
                    <input type="file" id="input-excel-import" accept=".xlsx, .xls" style="display: none;">
                </div>
                <div id="import-preview" style="display:none;">
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Se han detectado <span id="import-count" class="fw-bold">0</span> registros listos para
                        procesar.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-white border-opacity-10">
                <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirm-import" class="btn btn-primary px-4 fw-bold" disabled>Procesar Importación</button>
            </div>
        </div>
    </div>
</div>


<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
    $(document).ready(function () {
        var table = $('#tabla_prorrogas').DataTable({
            "ajax": "get_prorrogas_data.php",
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
            },
            "columns": [
                { "data": "fecha_registro" },
                { 
                    "data": "nombre_titular", "render": function (data, type, row) {
                        return `<div><span class="fw-bold">${data}</span><br><small class="text-muted">${row.cedula_titular}</small></div>`;
                    }
                },
                {
                    "data": "prorroga_regular", "render": function (data) {
                        return data == 'SI' ? '<span class="badge bg-primary-light text-primary">Permanente</span>' : '<span class="badge bg-secondary-light text-muted">Temporal</span>';
                    }
                },
                { "data": "fecha_corte" },
                {
                    "data": "codigo_sae_plus", "render": function (data) {
                        if (data) return `<span class="badge bg-dark font-monospace">${data}</span>`;
                        return '<span class="text-muted small">—</span>';
                    }
                },
                {
                    "data": "estado", "render": function (data, type, row) {
                        let html = '';
                        let badge = 'bg-warning-light text-warning';
                        if (data == 'PROCESADO') badge = 'bg-success-light text-success';
                        if (data == 'RECHAZADO') badge = 'bg-danger-light text-danger';
                        html += `<span class="badge ${badge}">${data}</span>`;
                        
                        // Nuevo: Indicator de pago detectado
                        if (row.pagos_mes_actual > 0) {
                            html += `<br><span class="badge bg-success-subtle text-success border border-success mt-1"><i class="fa-solid fa-check-double me-1"></i>Pago Detectado</span>`;
                        }
                        return html;
                    }
                },
                {
                    "data": "id_prorroga", "className": "text-end pe-4", "render": function (id, type, row) {
                        return `<div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-glass text-primary btn-editar-prorroga" title="Editar" data-id="${id}"><i class="fa-solid fa-edit"></i></button>
                            <button class="btn btn-sm btn-glass text-danger" title="Eliminar" onclick="eliminarProrroga(${id})"><i class="fa-solid fa-trash"></i></button>
                        </div>`;
                    }
                }
            ],
            "order": [[0, "desc"]]
        });

        // Event delegation for edit button
        $('#tabla_prorrogas tbody').on('click', '.btn-editar-prorroga', function() {
            var id = $(this).data('id');
            abrirModalEditar(id);
        });

        // Autocomplete para el Modal Interno
        const searchInt = document.getElementById('search_contrato_internal');
        const resultsInt = document.getElementById('results_contrato_internal');
        const hiddenInt = document.getElementById('id_contrato_internal');

        if (searchInt) {
            let timer;
            searchInt.addEventListener('input', function () {
                clearTimeout(timer);
                const q = this.value.trim();
                if (q.length < 3) { resultsInt.innerHTML = ''; return; }
                timer = setTimeout(() => {
                    fetch(`buscar_contratos.php?q=${encodeURIComponent(q)}`)
                        .then(r => r.json())
                        .then(data => {
                            resultsInt.innerHTML = '';
                            data.forEach(c => {
                                const a = document.createElement('a');
                                a.className = 'list-group-item list-group-item-action small';
                                a.innerHTML = `<strong>ID ${c.id}</strong>: ${c.nombre_completo}`;
                                a.onclick = (e) => {
                                    e.preventDefault();
                                    searchInt.value = c.nombre_completo;
                                    hiddenInt.value = c.id;
                                    // Auto-llenar campos
                                    document.querySelector('#formInternal input[name="cedula_titular"]').value = c.cedula;
                                    document.querySelector('#formInternal input[name="nombre_titular"]').value = c.nombre_completo;
                                    resultsInt.innerHTML = '';
                                };
                                resultsInt.appendChild(a);
                            });
                        });
                }, 300);
            });
        }
    });

    function eliminarProrroga(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('eliminar_prorroga.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Eliminado', res.message, 'success');
                            $('#tabla_prorrogas').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                    });
            }
        });
    }

    function abrirModalEditar(id) {
        console.log("Intentando abrir modal para ID:", id);
        var table = $('#tabla_prorrogas').DataTable();
        var data = table.rows().data().toArray().find(r => r.id_prorroga == id);
        
        if (!data) {
            Swal.fire('Error', 'No se encontraron los datos de esta prórroga.', 'error');
            return;
        }

        // Poblar campos
        $('#edit_id_prorroga').val(data.id_prorroga);
        $('#edit_nombre_titular').val(data.nombre_titular);
        $('#edit_cedula_titular').val(data.cedula_titular);
        $('#edit_fecha_corte').val(data.fecha_corte);
        $('#edit_prorroga_regular').val(data.prorroga_regular);
        $('#edit_codigo_sae_plus').val(data.codigo_sae_plus || '');
        
        // Abrir modal usando el API nativo de Bootstrap 
        var modalEl = document.getElementById('modalEditarProrroga');
        
        // Mover al body si no está ahí para evitar clipping
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();

        // Forzar visibilidad (Hack)
        setTimeout(() => {
            modalEl.style.display = 'block';
            modalEl.style.opacity = '1';
            console.log("Modal display forzado a block.");
        }, 100);
    }

    $("#formEditar").on("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('editar_prorroga.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire('Éxito', res.message, 'success');
                $('#modalEditarProrroga').modal('hide');
                $('#tabla_prorrogas').DataTable().ajax.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        });
    });

    function limpiarTemporales() {
        Swal.fire({
            title: '¿Limpiar Prórrogas Temporales?',
            text: "Se eliminarán las prórrogas temporales ya procesadas para limpiar tu listado.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarAccionBulk('limpiar_temporales');
            }
        });
    }

    function avanzarMesPermanentes() {
        Swal.fire({
            title: '¿Avanzar Mes a Permanentes?',
            text: "Se sumará 1 mes a la fecha de corte de todos los clientes con prórroga regular/permanente.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, avanzar fecha',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarAccionBulk('avanzar_mes');
            }
        });
    }

    function ejecutarAccionBulk(action) {
        fetch('acciones_bulk_prorrogas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire('Completado', res.message, 'success');
                $('#tabla_prorrogas').DataTable().ajax.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        });
    }

    async function exportExcel() {
        fetch('get_prorrogas_data.php')
            .then(r => r.json())
            .then(async res => {
                const data = res.data.filter(r => r.tipo_solicitud === 'PRORROGA');

                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet('Prórrogas');

                // 1. Título y Mes (Fila 1)
                const monthName = new Intl.DateTimeFormat('es-ES', { month: 'long' }).format(new Date()).toUpperCase();
                worksheet.mergeCells('A1:G1');
                const titleCell = worksheet.getCell('A1');
                titleCell.value = "Galanet-Prórroga " + new Date().getFullYear();
                titleCell.font = { name: 'Arial', family: 4, size: 14, bold: true };
                titleCell.alignment = { vertical: 'middle', horizontal: 'left' };

                const monthCell = worksheet.getCell('H1');
                monthCell.value = monthName;
                monthCell.font = { name: 'Arial', family: 4, size: 12, bold: true };
                monthCell.alignment = { vertical: 'middle', horizontal: 'right' };

                // 2. Cabeceras (Fila 2)
                const headers = ["CEDULA", "NOMBRE", "SAEPLUS", "ID SAE PLUS", "CORTE", "PRORROGA", "REGULAR?", "CARGADO"];
                const headerRow = worksheet.getRow(2);
                headerRow.values = headers;
                headerRow.font = { bold: true, color: { argb: 'FFFFFFFF' } };
                headerRow.alignment = { vertical: 'middle', horizontal: 'center' };

                headerRow.eachCell((cell) => {
                    cell.fill = {
                        type: 'pattern',
                        pattern: 'solid',
                        fgColor: { argb: 'FF1E4D2B' } // Verde oscuro tipo imagen
                    };
                    cell.border = {
                        top: { style: 'thin' },
                        left: { style: 'thin' },
                        bottom: { style: 'thin' },
                        right: { style: 'thin' }
                    };
                });

                // 3. Filas de Datos
                data.forEach((r, index) => {
                    const row = worksheet.addRow([
                        r.cedula_titular || '',
                        r.nombre_titular || '',
                        r.existe_saeplus || 'NO',
                        r.codigo_sae_plus || '',
                        r.fecha_corte ? new Date(r.fecha_corte + ' 00:00:00').getDate() : '',
                        r.dia_prorroga || '',
                        r.prorroga_regular || 'SI',
                        r.estado || 'PENDIENTE'
                    ]);

                    row.eachCell((cell, colNumber) => {
                        cell.border = {
                            top: { style: 'thin' },
                            left: { style: 'thin' },
                            bottom: { style: 'thin' },
                            right: { style: 'thin' }
                        };

                        // Colores por estado en la última columna
                        if (colNumber === 7) {
                            const status = (r.estado || 'PENDIENTE').toUpperCase();
                            if (status === 'PROCESADO') {
                                cell.font = { color: { argb: 'FF157347' }, bold: true }; // Verde éxito
                            } else if (status === 'RECHAZADO') {
                                cell.font = { color: { argb: 'FFBB2D3B' }, bold: true }; // Rojo error
                            } else {
                                cell.font = { color: { argb: 'FF6C757D' }, bold: true }; // Gris pendiente
                            }
                        }
                    });
                });

                // Configurar anchos de columna
                worksheet.getColumn(1).width = 15; // CEDULA
                worksheet.getColumn(2).width = 45; // NOMBRE
                worksheet.getColumn(3).width = 10; // SAEPLUS
                worksheet.getColumn(4).width = 15; // ID SAE PLUS
                worksheet.getColumn(5).width = 10; // CORTE
                worksheet.getColumn(6).width = 12; // PRORROGA
                worksheet.getColumn(7).width = 12; // REGULAR?
                worksheet.getColumn(8).width = 15; // CARGADO

                // Descargar archivo
                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = "Galanet-Prorroga_" + new Date().getFullYear() + ".xlsx";
                a.click();
                window.URL.revokeObjectURL(url);
            })
            .catch(err => {
                console.error(err);
                alert('Error al generar el Excel con estilos');
            });
    }

    // Lógica de Importación de Excel
    const dropzone = document.getElementById('dropzone-excel');
    const inputExcel = document.getElementById('input-excel-import');
    let dataToImport = [];

    dropzone.onclick = () => inputExcel.click();

    inputExcel.onchange = (e) => {
        const file = e.target.files[0];
        if (file) handleExcelFile(file);
    };

    dropzone.ondragover = (e) => { e.preventDefault(); dropzone.classList.add('bg-light', 'border-primary'); };
    dropzone.ondragleave = () => dropzone.classList.remove('bg-light', 'border-primary');
    dropzone.ondrop = (e) => {
        e.preventDefault();
        dropzone.classList.remove('bg-light', 'border-primary');
        const file = e.dataTransfer.files[0];
        if (file) handleExcelFile(file);
    };

    function handleExcelFile(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const sheet = workbook.Sheets[workbook.SheetNames[0]];

            // Buscar la cabecera real
            const rows = XLSX.utils.sheet_to_json(sheet, { header: 1 });
            let headerIdx = -1;
            for (let i = 0; i < Math.min(rows.length, 10); i++) {
                if (rows[i].some(c => String(c).toUpperCase().includes('CEDULA'))) {
                    headerIdx = i;
                    break;
                }
            }

            if (headerIdx === -1) {
                alert('No se encontró la columna CEDULA en las primeras filas.');
                return;
            }

            const json = XLSX.utils.sheet_to_json(sheet, { range: headerIdx });
            dataToImport = json.map(row => {
                // Mapear nombres de columnas insensibles a mayúsculas
                const mapped = {};
                Object.keys(row).forEach(k => {
                    const key = k.toUpperCase().trim();
                    if (key.includes('CEDULA')) mapped.cedula = row[k];
                    if (key.includes('NOMBRE')) mapped.nombre = row[k];
                    if (key.includes('SAEPLUS')) mapped.saeplus = row[k];
                    if (key.includes('CORTE')) mapped.corte = row[k];
                    if (key.includes('PRORROGA')) mapped.prorroga = row[k];
                    if (key.includes('REGULAR')) mapped.regular = row[k];
                    if (key.includes('ID SAE PLUS') || key.includes('CODIGO SAE PLUS')) mapped.codigo_sae_plus = row[k];
                });
                return mapped;
            }).filter(r => r.cedula && r.nombre);

            if (dataToImport.length > 0) {
                document.getElementById('import-preview').style.display = 'block';
                document.getElementById('import-count').innerText = dataToImport.length;
                document.getElementById('btn-confirm-import').disabled = false;
            }
        };
        reader.readAsArrayBuffer(file);
    }

    document.getElementById('btn-confirm-import').onclick = function () {
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Procesando...';

        fetch('importar_prorrogas_excel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ data: dataToImport })
        })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Importación completada: ' + res.imported + ' registros nuevos.');
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                    this.disabled = false;
                    this.innerHTML = 'Procesar Importación';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de conexión');
                this.disabled = false;
                this.innerHTML = 'Procesar Importación';
            });
    };
</script>

<?php require_once '../includes/layout_foot.php'; ?>