<?php
// paginas/soporte/historial_soportes.php
// Listado de soportes técnicos realizados

$path_to_root = "../../";
$page_title = "Historial de Soportes";
$breadcrumb = ["Soporte"];
$back_url = "../menu.php";
include_once $path_to_root . 'paginas/conexion.php';
include $path_to_root . 'paginas/includes/layout_head.php';
include $path_to_root . 'paginas/includes/sidebar.php';
include $path_to_root . 'paginas/includes/header.php';
?>

<style>
    .section-title {
        background: rgba(0, 0, 0, 0.05) !important;
        border-left: 4px solid var(--primary);
        color: var(--text-main) !important;
        padding: 8px 12px !important;
        margin-bottom: 15px !important;
        font-weight: 700 !important;
        border-radius: 4px;
        margin-top: 10px;
    }

    [data-theme="dark"] .section-title {
        background: rgba(255, 255, 255, 0.05) !important;
    }

    .section-title.border-danger {
        border-left-color: var(--danger) !important;
    }

    [data-theme="dark"] .modal-content {
        background-color: var(--modal-bg) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--border-glass) !important;
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
    <div class="page-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h3 fw-bold text-gradient mb-1">Historial de Soportes</h2>
                        <p class="text-muted mb-0">Registro de trabajos realizados.</p>
                    </div>
                    <a href="reporte_tecnico.php" target="_blank" class="btn btn-premium">
                        <i class="fas fa-plus me-2"></i> Nuevo Soporte
                    </a>
                </div>
            </div>

            <!-- Alertas -->
            <?php if (isset($_GET['status'])): ?>
                <div class="alert alert-<?php echo $_GET['status'] == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show"
                    role="alert">
                    <?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : ''; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Script to remove GET parameters from URL so refresh doesn't show alert again -->
            <script>
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.pathname);
                }
            </script>

            <div class="card glass-panel border-0 shadow-lg animate-fade">
                <div class="card-body p-0">
                    <div class="table-responsive p-4">
                        <table id="tablaSoportes" class="table table-hover table-orange-header w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Descripción</th>
                                    <th>Técnico</th>
                                    <th>Total ($)</th>
                                    <th>Pagado ($)</th>
                                    <th>Estado Pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- Modal Abonar -->
<div class="modal fade" id="modalAbonar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="procesar_abono.php" method="POST" class="modal-content border-0">
            <div class="modal-header modal-header-gradient text-white">
                <h5 class="modal-title fw-bold">Registrar Abono</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_soporte" id="id_soporte_abono">
                <p>Abonar al Soporte <strong>#<span id="txt_id_soporte"></span></strong></p>
                <div class="mb-3">
                    <label class="form-label">Deuda Actual: $<span id="txt_deuda_actual"></span></label>
                </div>
                <div class="mb-3">
                    <label for="monto_abono" class="form-label fw-bold">Monto a Abonar ($)</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="monto_abono" id="monto_abono"
                        required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Registrar Pago</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="actualizar_soporte.php" method="POST" class="modal-content border-0" id="formEditarSoporte">
            <div class="modal-header modal-header-gradient text-white">
                <h5 class="modal-title fw-bold">Editar Soporte #<span id="edit_modal_id_display"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_soporte_edit" id="id_soporte_edit">
                <input type="hidden" name="origen" value="historial_soportes">

                <!-- Tipo de Falla -->
                <div class="section-title border-danger">Información de Falla</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipo de Falla</label>
                        <select class="form-select" name="tipo_falla_edit" id="tipo_falla_edit_hist">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="es_caida_critica_edit"
                                id="es_caida_critica_edit_hist" value="1">
                            <label class="form-check-label text-danger fw-bold" for="es_caida_critica_edit_hist">¿Caída
                                Crítica?</label>
                        </div>
                    </div>
                </div>

                <!-- 1. Encabezado -->
                <div class="row mb-3">
                    <div class="col-md-3 mb-3">
                        <label for="fecha_edit" class="form-label fw-bold">Fecha</label>
                        <input type="date" class="form-control" name="fecha_edit" id="fecha_edit" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="tecnico_edit" class="form-label fw-bold">Técnico Asignado</label>
                        <input type="text" class="form-control" name="tecnico_edit" id="tecnico_edit" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Sector</label>
                        <input type="text" class="form-control" name="sector" id="sector_edit"
                            placeholder="Ej. Las Malvinas">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Prioridad</label>
                        <select class="form-select" name="prioridad_edit" id="prioridad_edit">
                            <option value="NIVEL 1">NIVEL 1 (WhatsApp)</option>
                            <option value="NIVEL 2">NIVEL 2 (Visita)</option>
                            <option value="NIVEL 3">NIVEL 3 (Red)</option>
                        </select>
                    </div>
                </div>

                <!-- 3. Detalles de Servicio -->
                <div class="section-title">Detalles Técnicos</div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tipo Servicio</label>
                        <select class="form-select" name="tipo_servicio" id="tipo_servicio_edit">
                            <option value="FTTH">FTTH (Fibra)</option>
                            <option value="RADIO">Radio/Antena</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">IP Asignada</label>
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
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Modelo Router</label>
                        <input type="text" class="form-control" name="modelo_router" id="modelo_router_edit"
                            placeholder="Ej. TPLink">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Dispositivos</label>
                        <input type="number" class="form-control" name="num_dispositivos" id="num_dispositivos_edit"
                            placeholder="Cant.">
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-4">
                                <label class="form-label small">Bajada</label>
                                <input type="text" class="form-control" name="bw_bajada" id="bw_bajada_edit"
                                    placeholder="MB">
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Subida</label>
                                <input type="text" class="form-control" name="bw_subida" id="bw_subida_edit"
                                    placeholder="MB">
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Ping</label>
                                <input type="text" class="form-control" name="bw_ping" id="bw_ping_edit"
                                    placeholder="ms">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-box-premium mx-1">
                    <div class="col-12 mb-2"><small class="text-muted fw-bold text-uppercase" style="letter-spacing: 1px;">Solo Radio / Antena:</small></div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Estado Antena</label>
                            <input type="text" class="form-control" name="estado_antena" id="estado_antena_edit"
                                placeholder="Ej. Alineada">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Valores dBm</label>
                            <input type="text" class="form-control" name="valores_antena" id="valores_antena_edit"
                                placeholder="Ej. -55">
                        </div>
                    </div>
                </div>

                <!-- 4. Observaciones -->
                <div class="section-title">Diagnóstico y Solución</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="descripcion_edit" class="form-label">Observaciones / Problema</label>
                        <textarea class="form-control" name="descripcion_edit" id="descripcion_edit" rows="3"
                            required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sugerencias al Cliente</label>
                        <textarea class="form-control" name="sugerencias" id="sugerencias_edit" rows="3"
                            placeholder="Recomendaciones..."></textarea>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notas Internas (Solo Admin)</label>
                    <textarea class="form-control" name="notas_internas_edit" id="notas_internas_edit"
                        rows="2"></textarea>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="solucion_completada_edit"
                        name="solucion_completada">
                    <label class="form-check-label fw-bold" for="solucion_completada_edit">¿Falla Solucionada?</label>
                </div>

                <!-- 5. Costos -->
                <div class="section-title">Costos y Facturación</div>
                <div class="mb-3">
                    <label for="monto_total_edit" class="form-label fw-bold">Monto Total ($)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="monto_total_edit"
                        id="monto_total_edit" required>
                    <div class="form-text text-muted">Nota: Al modificar el total, la deuda del cliente se recalculará
                        automáticamente.</div>
                </div>

                <!-- 6. Firmas -->
                <div class="section-title">Actualizar Firmas (Opcional)</div>
                <div class="row mb-4">
                    <p class="text-muted small">Deje los lienzos en blanco para conservar las firmas originales.</p>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Firma Técnico</label>
                        <div class="mb-2 text-center" id="container_firma_tech_edit" style="display:none;">
                            <span class="badge bg-info mb-1">Firma Actual Guardada</span><br>
                            <img id="imgFirmaTech_edit" src="" alt="Firma Técnico Actual"
                                style="max-height: 80px; border: 1px dashed #ccc;">
                        </div>
                        <canvas id="sigTechEdit"
                            style="border: 1px solid #ccc; width: 100%; height: 150px; border-radius: 4px; background-color: #fcfcfc;"></canvas>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                            onclick="clearPadEdit('tech')">Limpiar Lienzo</button>
                        <input type="hidden" name="firma_tecnico_data" id="firma_tecnico_data_edit">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Firma Cliente</label>
                        <div class="mb-2 text-center" id="container_firma_cli_edit" style="display:none;">
                            <span class="badge bg-info mb-1">Firma Actual Guardada</span><br>
                            <img id="imgFirmaCli_edit" src="" alt="Firma Cliente Actual"
                                style="max-height: 80px; border: 1px dashed #ccc;">
                        </div>
                        <canvas id="sigCliEdit"
                            style="border: 1px solid #ccc; width: 100%; height: 150px; border-radius: 4px; background-color: #fcfcfc;"></canvas>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                            onclick="clearPadEdit('cli')">Limpiar Lienzo</button>
                        <input type="hidden" name="firma_cliente_data" id="firma_cliente_data_edit">
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light bg-opacity-5 border-top border-white border-opacity-10">
                <button type="button" class="btn btn-glass px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-premium px-4" id="btnGuardarEdicion">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="eliminar_soporte.php" method="POST" class="modal-content border-0">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold">Eliminar Soporte</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_soporte_eliminar" id="id_soporte_eliminar">
                <p class="fw-bold">¿Estás seguro de eliminar el soporte #<span id="txt_id_eliminar"></span>?</p>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Advertencia:</strong> Esta acción también
                    eliminará cualquier deuda o cobro asociado a este soporte en el módulo de cobranzas. Esta acción es
                    irreversible.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Confirmar Eliminación</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo $path_to_root; ?>js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="<?php echo $path_to_root; ?>js/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
<script>
    let padTechEdit = null, padCliEdit = null;

    function cargarOpcionesFallaEditHist(callback) {
        fetch('admin_opciones.php?accion=listar&tipo=tipos_falla')
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('tipo_falla_edit_hist');
                if (!sel) { if (callback) callback(); return; }
                const current = sel.value;
                sel.innerHTML = '<option value="">-- Seleccionar --</option>';
                (data.tipos_falla || []).forEach(op => {
                    const o = document.createElement('option');
                    o.value = op; o.textContent = op;
                    sel.appendChild(o);
                });
                if (current) sel.value = current;
                if (callback) callback();
            })
            .catch(() => { if (callback) callback(); });
    }

    function abrirEditar(id) {
        document.getElementById('id_soporte_edit').value = id;
        document.getElementById('edit_modal_id_display').textContent = id;
        document.getElementById('container_firma_tech_edit').style.display = 'none';
        document.getElementById('container_firma_cli_edit').style.display = 'none';

        cargarOpcionesFallaEditHist(() => {
            fetch('get_soporte_detalle.php?id=' + id)
                .then(r => r.json())
                .then(d => {
                    if (d.error) { alert('Error: ' + d.error); return; }
                    document.getElementById('fecha_edit').value = d.fecha_soporte_form || '';
                    document.getElementById('tecnico_edit').value = d.tecnico_asignado || '';
                    document.getElementById('sector_edit').value = d.sector || '';
                    document.getElementById('prioridad_edit').value = d.prioridad || 'NIVEL 1';
                    document.getElementById('tipo_falla_edit_hist').value = d.tipo_falla || '';
                    document.getElementById('es_caida_critica_edit_hist').checked = d.es_caida_critica == 1;
                    document.getElementById('tipo_servicio_edit').value = d.tipo_servicio || 'FTTH';
                    document.getElementById('ip_edit').value = d.ip_address || '';
                    document.getElementById('estado_onu_edit').value = d.estado_onu || '';
                    document.getElementById('estado_router_edit').value = d.estado_router || '';
                    document.getElementById('modelo_router_edit').value = d.modelo_router || '';
                    document.getElementById('num_dispositivos_edit').value = d.num_dispositivos || '';
                    document.getElementById('bw_bajada_edit').value = d.bw_bajada || '';
                    document.getElementById('bw_subida_edit').value = d.bw_subida || '';
                    document.getElementById('bw_ping_edit').value = d.bw_ping || '';
                    document.getElementById('estado_antena_edit').value = d.estado_antena || '';
                    document.getElementById('valores_antena_edit').value = d.valores_antena || '';
                    document.getElementById('descripcion_edit').value = d.observaciones || '';
                    document.getElementById('sugerencias_edit').value = d.sugerencias || '';
                    document.getElementById('notas_internas_edit').value = d.notas_internas || '';
                    document.getElementById('solucion_completada_edit').checked = d.solucion_completada == 1;
                    document.getElementById('monto_total_edit').value = parseFloat(d.monto_total || 0).toFixed(2);

                    const pathRoot = '../../';
                    if (d.firma_tecnico) {
                        document.getElementById('imgFirmaTech_edit').src = pathRoot + 'uploads/firmas/' + d.firma_tecnico;
                        document.getElementById('container_firma_tech_edit').style.display = 'block';
                    }
                    if (d.firma_cliente) {
                        document.getElementById('imgFirmaCli_edit').src = pathRoot + 'uploads/firmas/' + d.firma_cliente;
                        document.getElementById('container_firma_cli_edit').style.display = 'block';
                    }

                    new bootstrap.Modal(document.getElementById('modalEditar')).show();
                })
                .catch(() => alert('Error al cargar datos.'));
        });
    }

    // --- Inicializar SignaturePad cuando se abra el modal ---
    $('#modalEditar').on('shown.bs.modal', function () {
        const canvasTech = document.getElementById('sigTechEdit');
        const canvasCli = document.getElementById('sigCliEdit');

        // Redimensionar para que coincida con el CSS
        function resizeCanvas(canvas) {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
        }

        resizeCanvas(canvasTech);
        resizeCanvas(canvasCli);

        if (!padTechEdit) padTechEdit = new SignaturePad(canvasTech);
        if (!padCliEdit) padCliEdit = new SignaturePad(canvasCli);

        padTechEdit.clear();
        padCliEdit.clear();
    });

    function clearPadEdit(type) {
        if (type === 'tech' && padTechEdit) padTechEdit.clear();
        if (type === 'cli' && padCliEdit) padCliEdit.clear();
    }

    // --- On form submit, prepare signatures ---
    document.getElementById('formEditarSoporte').addEventListener('submit', function (e) {
        // Guardar firmas en hidden inputs si no están vacías
        if (padTechEdit && !padTechEdit.isEmpty()) {
            document.getElementById('firma_tecnico_data_edit').value = padTechEdit.toDataURL();
        } else {
            document.getElementById('firma_tecnico_data_edit').value = '';
        }

        if (padCliEdit && !padCliEdit.isEmpty()) {
            document.getElementById('firma_cliente_data_edit').value = padCliEdit.toDataURL();
        } else {
            document.getElementById('firma_cliente_data_edit').value = '';
        }

        const btn = document.getElementById('btnGuardarEdicion');
        btn.disabled = true;
        btn.innerHTML = 'Guardando...';

        // Debug
        const formData = new FormData(this);
        console.log("FormData to be submitted:", Object.fromEntries(formData.entries()));
    });


    $(document).ready(function () {
        $('#tablaSoportes').DataTable({
            "order": [[0, "desc"]],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "server_process_soportes.php",
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
            },
            "aoColumnDefs": [
                { "mData": 0, "aTargets": [0] }, // ID
                { "mData": 1, "aTargets": [1] }, // Fecha
                { "mData": 2, "aTargets": [2] }, // Cliente
                { "mData": 3, "aTargets": [3] }, // Descripción
                { "mData": 4, "aTargets": [4] }, // Técnico
                {
                    "mData": 5,
                    "aTargets": [5],
                    "mRender": function (data, type, row) {
                        return '$' + parseFloat(data).toFixed(2);
                    }
                }, // Total
                {
                    "mData": 6,
                    "aTargets": [6],
                    "mRender": function (data, type, row) {
                        return '$' + parseFloat(data).toFixed(2);
                    }
                }, // Pagado
                {
                    "mData": 7, // Deuda / Estado
                    "aTargets": [7],
                    "mRender": function (data, type, row) {
                        var total = parseFloat(row[5]);
                        var pagado = parseFloat(row[6]);

                        if (pagado >= (total - 0.01)) {
                            return '<span class="badge bg-success">Pagado</span>';
                        } else {
                            var deuda = total - pagado;
                            return '<span class="badge bg-danger">Debe: $' + deuda.toFixed(2) + '</span>';
                        }
                    }
                },
                {
                    "mData": null, // Acciones
                    "aTargets": [8],
                    "bSortable": false,
                    "mRender": function (data, type, row) {
                        var id = row[0];
                        var total = parseFloat(row[5]);
                        var pagado = parseFloat(row[6]);
                        var deuda = total - pagado;

                        var btnPdf = `<a href="generar_pdf_reporte.php?id=${id}" target="_blank" class="btn btn-sm btn-info me-1" title="Ver PDF">
                        <i class="fas fa-file-pdf"></i></a>`;

                        var btnEdit = `<button type="button" class="btn btn-sm btn-warning me-1" title="Editar"
                        onclick="abrirEditar('${id}')">
                        <i class="fas fa-edit"></i></button>`;

                        var btnPay = '';
                        if (deuda > 0.01) {
                            btnPay = `<button type="button" class="btn btn-sm btn-success me-1" title="Abonar"
                            onclick="abrirAbonar('${id}', '${deuda.toFixed(2)}')">
                            <i class="fas fa-dollar-sign"></i></button>`;
                        }

                        var btnDel = `<button type="button" class="btn btn-sm btn-danger" title="Eliminar"
                        onclick="abrirEliminar('${id}')">
                        <i class="fas fa-trash-alt"></i></button>`;

                        return '<div class="d-flex justify-content-center">' + btnPdf + btnEdit + btnPay + btnDel + '</div>';
                    }
                }
            ]
        });
    });

    function abrirAbonar(id, deuda) {
        $('#id_soporte_abono').val(id);
        $('#txt_id_soporte').text(id);
        $('#txt_deuda_actual').text(deuda);
        $('#monto_abono').attr('max', deuda); // No permitir pagar más de la deuda
        var modal = new bootstrap.Modal(document.getElementById('modalAbonar'));
        modal.show();
    }

    function abrirEditar(id) {
        // Fetch full data using AJAX with cache-busting parameter
        var timestamp = new Date().getTime();
        fetch('get_soporte_detalle.php?id=' + id + '&_t=' + timestamp, { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                $('#edit_modal_id_display').text(id);
                $('#id_soporte_edit').val(id);

                $('#fecha_edit').val(data.fecha_soporte_form);
                $('#tecnico_edit').val(data.tecnico_asignado);
                $('#sector_edit').val(data.sector);
                $('#tipo_servicio_edit').val(data.tipo_servicio);
                $('#ip_edit').val(data.ip_address);
                $('#estado_onu_edit').val(data.estado_onu);
                $('#estado_router_edit').val(data.estado_router);
                $('#modelo_router_edit').val(data.modelo_router);
                $('#num_dispositivos_edit').val(data.num_dispositivos);
                $('#bw_bajada_edit').val(data.bw_bajada);
                $('#bw_subida_edit').val(data.bw_subida);
                $('#bw_ping_edit').val(data.bw_ping);
                $('#estado_antena_edit').val(data.estado_antena);
                $('#valores_antena_edit').val(data.valores_antena);

                $('#descripcion_edit').val(data.descripcion);
                $('#sugerencias_edit').val(data.sugerencias);
                $('#notas_internas_edit').val(data.notas_internas);
                $('#prioridad_edit').val(data.prioridad);
                $('#monto_total_edit').val(data.monto_total);

                // Form checkbox
                $('#solucion_completada_edit').prop('checked', data.solucion_completada == 1);

                // Mostrar firmas existentes si las hay
                if (data.firma_tecnico) {
                    $('#imgFirmaTech_edit').attr('src', '../../uploads/firmas/' + data.firma_tecnico);
                    $('#container_firma_tech_edit').show();
                } else {
                    $('#container_firma_tech_edit').hide();
                }

                if (data.firma_cliente) {
                    $('#imgFirmaCli_edit').attr('src', '../../uploads/firmas/' + data.firma_cliente);
                    $('#container_firma_cli_edit').show();
                } else {
                    $('#container_firma_cli_edit').hide();
                }

                var modal = new bootstrap.Modal(document.getElementById('modalEditar'));
                modal.show();
            })
            .catch(error => {
                console.error('Error fetching support details:', error);
                alert("Error cargando los detalles del soporte.");
            });
    }

    function abrirEliminar(id) {
        $('#id_soporte_eliminar').val(id);
        $('#txt_id_eliminar').text(id);
        var modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
        modal.show();
    }
</script>
</body>

</html>