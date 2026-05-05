<?php
// aprobar_pagos.php - Panel administrativo para revisar pagos reportados por clientes
require_once '../conexion.php';

// Configuración Layout
$path_to_root = "../../";
$page_title = "Aprobar Reportes de Pago";
$breadcrumb = ["Cobranzas"];
$back_url = "../menu.php";
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';

// Consulta de pagos pendientes
$sql = "
    SELECT 
        pr.*,
        b.nombre_banco AS banco_destino
    FROM pagos_reportados pr
    LEFT JOIN bancos b ON pr.id_banco_destino = b.id_banco
    WHERE pr.estado = 'PENDIENTE'
    ORDER BY pr.fecha_registro DESC
    LIMIT 100
";
$resultado = $conn->query($sql);
?>





<style>
    .page-content {
        padding: 1.5rem;
        flex: 1;
    }
    
    .main-content {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    @keyframes pulse-mismatch {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(0.98); }
        100% { opacity: 1; transform: scale(1); }
    }
    .animate-pulse {
        animation: pulse-mismatch 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    .badge-ocr-mismatch {
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
    }
    
    /* Optimization: Reduce transitions during layout heavy sections */
    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
    }
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content container-fluid">
        <div class="glass-panel animate-fade mb-4">
            <div class="card-header bg-transparent border-bottom border-white border-opacity-10 py-4 px-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold text-gradient mb-1">Reportes de Pago Pendientes</h4>
                        <p class="text-muted small mb-0">Revisión manual de reportes enviados por clientes vía portal</p>
                    </div>
                    <div class="col-auto">
                        <a href="historial_pagos_reportados.php" class="btn btn-glass btn-sm">
                            <i class="fas fa-history me-1"></i> Ver Historial
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['message'])): ?>
                <div class="px-4 pt-3">
                    <div class="alert alert-<?php echo $_GET['class'] ?? 'info'; ?> alert-dismissible fade show"
                        role="alert">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablaAprobacion">
                        <thead>
                            <tr>
                                <th class="ps-4">Fecha Reporte</th>
                                <th>Cliente / Cédula</th>
                                <th>Detalle Pago</th>
                                <th>Monto (Bs)</th>
                                <th>Meses</th>
                                <th>Comprobante</th>
                                <th class="text-center pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyReportes">
                            <?php if ($resultado && $resultado->num_rows > 0): ?>
                                <?php while ($row = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="d-block fw-bold text-main">
                                                <?php echo date('d/m/Y', strtotime($row['fecha_registro'])); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($row['fecha_registro'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="d-block fw-bold text-main">
                                                <?php echo htmlspecialchars($row['nombre_titular']); ?>
                                            </span>
                                            <small class="badge bg-primary-light text-primary">
                                                <?php echo htmlspecialchars($row['cedula_titular']); ?>
                                            </small>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($row['telefono_titular']); ?>
                                            </div>
                                            <?php if ($row['id_contrato_asociado']): ?>
                                                <div class="text-success small mt-1"><i class="fas fa-link"></i> Contrato detectado
                                                    #
                                                    <?php echo $row['id_contrato_asociado']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <strong>Monto:</strong> (Revisar Capture)<br>
                                                <strong>Método:</strong>
                                                <?php echo htmlspecialchars($row['metodo_pago']); ?><br>
                                                <?php if ($row['referencia']): ?>
                                                    <strong>Ref:</strong>
                                                    <?php echo htmlspecialchars($row['referencia']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($row['banco_destino']): ?>
                                                    <strong>Banco:</strong>
                                                    <?php echo htmlspecialchars($row['banco_destino']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary fs-6">
                                                Bs. <?php echo number_format($row['monto_bs'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $meses_array = array_filter(explode(',', $row['meses_pagados']));
                                            $cant_meses = count($meses_array);
                                            ?>
                                            <span class="badge bg-info text-dark mb-1 d-inline-block">
                                                <?php echo $cant_meses; ?> mes<?php echo $cant_meses != 1 ? 'es' : ''; ?>
                                            </span>
                                            <div class="text-wrap small text-muted">
                                                <?php echo htmlspecialchars($row['meses_pagados']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="../../<?php echo $row['capture_path']; ?>" target="_blank"
                                                class="btn btn-sm btn-glass text-info">
                                                <i class="fas fa-image me-1"></i> Ver Foto
                                            </a>
                                        </td>
                                        <td class="text-center pe-4">
                                            <div class="d-flex justify-content-center gap-2">
                                                <button type="button" class="btn btn-sm btn-glass text-success"
                                                    onclick="prepararAprobacion(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                    title="Aprobar y Registrar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-glass text-danger"
                                                    onclick="confirmarRechazo(<?php echo $row['id_reporte']; ?>)"
                                                    title="Rechazar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-glass text-muted"
                                                    onclick="confirmarEliminacion(<?php echo $row['id_reporte']; ?>)"
                                                    title="Eliminar Reporte Permanentemente">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-light mb-3"></i>
                                        <p class="text-muted">No hay reportes de pago pendientes por revisar.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Aprobar Pago -->
    <div class="modal fade" id="modalConfirmarAprobacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content glass-panel border-0">
                <div class="modal-header modal-header-gradient border-0">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="fas fa-check-circle me-2"></i> Confirmar Aprobación de Pago
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="procesar_aprobacion_admin.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_reporte" id="ap_id_reporte">
                        <input type="hidden" name="accion" value="APROBAR">

                        <div class="row g-4">
                            <!-- Columna Izquierda: Capture -->
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold mb-3"><i class="fas fa-image me-1"></i> Comprobante de Pago</h6>
                                <div class="bg-light rounded p-2 text-center"
                                    style="min-height: 400px; display: flex; align-items: center; justify-content: center;">
                                    <img id="ap_img_capture" src="" class="img-fluid rounded shadow-sm"
                                        style="max-height: 600px; cursor: pointer;"
                                        onclick="window.open(this.src, '_blank')">
                                </div>
                                <div class="mt-3 small text-muted text-center">
                                    Haga clic en la imagen para verla en tamaño completo.
                                </div>
                            </div>

                            <!-- Columna Derecha: Formulario -->
                            <div class="col-md-6">
                                <div class="alert alert-info py-2 small mb-3 border-start-0 border-end-0 rounded-0 bg-opacity-10 bg-info">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-5">
                                            <div class="fw-bold text-dark">Reportado por: <span id="val_cliente_reporta" class="text-primary">---</span></div>
                                            <div class="small text-muted">Tel: <span id="val_tel_reporta">---</span></div>
                                        </div>
                                        <div class="col-md-7 text-end border-start ps-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">Monto (Usuario vs OCR):</span>
                                                <span>
                                                    <span id="val_monto_usuario" class="badge bg-primary">0,00 Bs.</span>
                                                    <i class="fas fa-arrows-left-right mx-1 text-muted small"></i>
                                                    <span id="val_monto_ocr" class="badge bg-secondary">Esperando...</span>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Ref. (Usuario vs OCR):</span>
                                                <span>
                                                    <span id="val_ref_usuario" class="badge bg-primary">---</span>
                                                    <i class="fas fa-arrows-left-right mx-1 text-muted small"></i>
                                                    <span id="val_ref_ocr" class="badge bg-secondary">Esperando...</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="mismatch-warning" class="mt-2 text-danger fw-bold small d-none animate-pulse">
                                        <i class="fas fa-triangle-exclamation me-1"></i> ATENCIÓN: Los datos del comprobante no coinciden con lo reportado.
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-bold text-white">Buscar Contratante (Manual)</label>
                                        <div class="input-group">
                                            <span class="input-group-text glass-input border-end-0"><i class="fas fa-search text-primary"></i></span>
                                            <input type="text" id="busqueda_manual_contrato" class="form-control glass-input border-start-0"
                                                placeholder="Nombre, Cédula o ID...">
                                            <button type="button" class="btn btn-primary px-3"
                                                onclick="buscarContratoManual()">Buscar</button>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold">Contrato Asociado</label>
                                        <select name="id_contrato" id="ap_id_contrato" class="form-select" required>
                                            <option value="">Seleccione contrato...</option>
                                        </select>
                                        <div class="small text-muted mt-1">Si no aparece el cliente correcto, use el
                                            buscador de arriba.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Monto a Registrar ($)</label>
                                        <input type="number" step="0.01" name="monto_total" id="ap_monto_total"
                                            class="form-control fw-bold" required placeholder="Detectando...">
                                        <div id="ocrStatus" class="small mt-1">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            </div>
                                            <span class="text-primary ms-1" id="ocrText">Analizando capture con
                                                OCR...</span>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Fecha Pago</label>
                                        <input type="date" name="fecha_pago" id="ap_fecha_pago" class="form-control"
                                            required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Referencia</label>
                                        <input type="text" name="referencia" id="ap_referencia" class="form-control">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Banco Receptor</label>
                                        <select name="id_banco" id="ap_id_banco" class="form-select" required>
                                            <?php
                                            // Recargar bancos para el modal desde JSON (nuevo sistema) o BD antigua
                                            $jsonFile = 'bancos.json';
                                            if (file_exists($jsonFile)) {
                                                $bancosData = json_decode(file_get_contents($jsonFile), true);
                                                if ($bancosData) {
                                                    foreach ($bancosData as $b) {
                                                        echo "<option value='" . htmlspecialchars($b['id_banco']) . "'>" . htmlspecialchars($b['nombre_banco']) . "</option>";
                                                    }
                                                }
                                            } else {
                                                // Fallback si no hay JSON
                                                $res_banks = $conn->query("SELECT id_banco, nombre_banco FROM bancos");
                                                while ($b = $res_banks->fetch_assoc()) {
                                                    echo "<option value='" . $b['id_banco'] . "'>" . htmlspecialchars($b['nombre_banco']) . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-bold">Resumen del Reporte</label>
                                        <div id="ap_meses_notas" class="alert alert-light border small mb-0"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-white border-opacity-10">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">APROBAR Y REGISTRAR PAGO</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Rechazar -->
    <div class="modal fade" id="modalRechazar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel border-0">
                <div class="modal-header modal-header-gradient border-0">
                    <h5 class="modal-title fw-bold text-white">Rechazar Reporte de Pago</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="procesar_aprobacion_admin.php" method="POST">
                    <div class="modal-body text-center p-4">
                        <input type="hidden" name="id_reporte" id="rej_id_reporte">
                        <input type="hidden" name="accion" value="RECHAZAR">
                        <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
                        <h5 class="mb-3">¿Seguro que desea rechazar este reporte?</h5>
                        <p class="text-muted">Esta acción no registrará el pago y marcará el reporte como rechazado.</p>
                        
                        <div class="mb-3 text-start">
                            <label class="form-label text-white small">Motivo del rechazo (opcional)</label>
                            <textarea name="motivo" class="form-control glass-input" rows="3" placeholder="Ej: Referencia inválida, capture ilegible..."></textarea>
                        </div>

                        <div class="modal-footer border-top-0 pt-0">
                            <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger px-4 fw-bold">RECHAZAR PAGO</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Reporte -->
    <div class="modal fade" id="modalEliminarReporte" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content glass-panel border-0">
                <div class="modal-header modal-header-gradient border-0">
                    <h5 class="modal-title fw-bold text-white">Eliminar Reporte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="procesar_aprobacion_admin.php" method="POST">
                    <div class="modal-body text-center p-4">
                        <input type="hidden" name="id_reporte" id="el_id_reporte">
                        <input type="hidden" name="accion" value="ELIMINAR">
                        <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                        <h6 class="fw-bold">Â¿Eliminar permanentemente?</h6>
                        <p class="text-muted small">Esto borrará el registro y su imagen del servidor.</p>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">No</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">Sí, Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</main>

<!-- Scripts moved before inline logic to fix ReferenceError: $ is not defined -->
<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="https://unpkg.com/tesseract.js@5.0.3/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Global variables for modals and OCR
    let modalAprobar, modalRechazar;
    let currentCapturePath = '';

    $(document).ready(function () {
        // Limpiar parámetros de URL para que el mensaje no se repita al recargar
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('message');
            url.searchParams.delete('class');
            url.searchParams.delete('maintenance_done');
            window.history.replaceState({}, document.title, url.toString());
        }

        // Initialize modals safely after Bootstrap is loaded from layout_foot.php
        const elAprobar = document.getElementById('modalConfirmarAprobacion');
        const elRechazar = document.getElementById('modalRechazar');

        if (elAprobar) modalAprobar = new bootstrap.Modal(elAprobar);
        if (elRechazar) modalRechazar = new bootstrap.Modal(elRechazar);

        // AUTO-REFRESH cada 5 segundos
        let isModalOpen = false;

        // Detectar si hay algún modal abierto para pausar el refresco
        $(document).on('show.bs.modal', '.modal', function () { isModalOpen = true; });
        $(document).on('hidden.bs.modal', '.modal', function () { isModalOpen = false; });

        setInterval(function () {
            if (!isModalOpen) {
                console.log("Refrescando tabla de reportes...");
                fetch('get_reportes_pendientes_ajax.php')
                    .then(r => r.text())
                    .then(html => {
                        const container = document.getElementById('tbodyReportes');
                        if (container) container.innerHTML = html;
                    })
                    .catch(err => console.error("Error en auto-refresh:", err));
            }
        }, 15000);

        // Permitir buscar al presionar Enter
        $('#busqueda_manual_contrato').on('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarContratoManual();
            }
        });
    });

    window.prepararAprobacion = function (data) {
        currentCapturePath = data.capture_path; // Guardar path para OCR
        document.getElementById('ap_id_reporte').value = data.id_reporte;
        document.getElementById('ap_fecha_pago').value = data.fecha_pago;
        document.getElementById('ap_referencia').value = data.referencia;
        document.getElementById('ap_id_banco').value = data.id_banco_destino || '';
        document.getElementById('ap_monto_total').value = ''; // Limpiar previo

        // NUEVO: Mostrar imagen
        document.getElementById('ap_img_capture').src = '../../' + data.capture_path;

        // NUEVO: Mostrar info cliente reportada
        document.getElementById('val_cliente_reporta').innerText = data.nombre_titular;
        document.getElementById('val_tel_reporta').innerText = data.telefono_titular;
        document.getElementById('busqueda_manual_contrato').value = ''; // Limpiar búsqueda previa

        // Guardar valores reportados para comparación posterior en OCR
        window.montoReportadoUsuario = parseFloat(data.monto_bs || 0);
        window.referenciaReportadaUsuario = data.referencia || '';

        // Mostrar monto reportado por el usuario
        document.getElementById('val_monto_usuario').innerText = window.montoReportadoUsuario.toLocaleString('es-VE', { minimumFractionDigits: 2 }) + ' Bs.';
        document.getElementById('val_monto_ocr').innerText = 'Procesando...';
        document.getElementById('val_monto_ocr').className = 'badge bg-secondary';

        // Mostrar referencia reportada por el usuario
        document.getElementById('val_ref_usuario').innerText = window.referenciaReportadaUsuario;
        document.getElementById('val_ref_ocr').innerText = 'Procesando...';
        document.getElementById('val_ref_ocr').className = 'badge bg-secondary';

        // Reset mismatch warning
        document.getElementById('mismatch-warning').classList.add('d-none');

        document.getElementById('ap_meses_notas').innerHTML = `<strong>Meses reportados:</strong> ${data.meses_pagados}<br><strong>Justificación:</strong> ${data.concepto || 'N/A'}`;

        // Reset OCR UI
        document.getElementById('ocrStatus').style.display = 'block';
        document.getElementById('ocrText').innerText = 'Analizando capture con OCR...';
        const spinner = document.querySelector('#ocrStatus .spinner-border');
        if (spinner) spinner.style.display = 'inline-block';

        console.log("Preparando aprobación para reporte:", data);

        const select = document.getElementById('ap_id_contrato');
        select.innerHTML = '<option value="">Cargando contratos...</option>';

        fetch(`buscar_contratos.php?q=${data.cedula_titular}&limit=10`)
            .then(r => r.json())
            .then(contratos => {
                select.innerHTML = '<option value="">Seleccione contrato...</option>';
                contratos.forEach(c => {
                    const selected = (c.id == data.id_contrato_asociado) ? 'selected' : '';
                    select.innerHTML += `<option value="${c.id}" ${selected}>#${c.id} - ${c.nombre_completo} (${c.cedula})</option>`;
                });
            });

        if (modalAprobar) {
            modalAprobar.show();
        } else {
            modalAprobar = new bootstrap.Modal(document.getElementById('modalConfirmarAprobacion'));
            modalAprobar.show();
        }

        // Auto-trigger OCR after short delay to allow modal animation
        setTimeout(() => ejecutarOCR(), 500);
    }

    window.confirmarRechazo = function (id) {
        document.getElementById('rej_id_reporte').value = id;
        if (modalRechazar) {
            modalRechazar.show();
        } else {
            modalRechazar = new bootstrap.Modal(document.getElementById('modalRechazar'));
            modalRechazar.show();
        }
    }

    window.confirmarEliminacion = function (id) {
        const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminarReporte'));
        document.getElementById('el_id_reporte').value = id;
        modalEliminar.show();
    }

    // NUEVO: Búsqueda manual de contrato
    window.buscarContratoManual = function () {
        const query = document.getElementById('busqueda_manual_contrato').value;
        if (query.trim().length < 3) {
            alert("Ingrese al menos 3 caracteres para buscar (Nombre, Cédula o ID)");
            return;
        }

        const select = document.getElementById('ap_id_contrato');
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        btn.disabled = true;

        fetch(`buscar_contratos.php?q=${encodeURIComponent(query)}&limit=20`)
            .then(r => r.json())
            .then(contratos => {
                select.innerHTML = '<option value="">Seleccione contrato...</option>';
                if (contratos.length === 0) {
                    select.innerHTML += '<option disabled>No se encontraron resultados</option>';
                    alert("No se encontraron contratos con ese criterio.");
                } else {
                    contratos.forEach(c => {
                        select.innerHTML += `<option value="${c.id}">#${c.id} - ${c.nombre_completo} (${c.cedula})</option>`;
                    });
                    if (contratos.length === 1) {
                        select.value = contratos[0].id;
                    }
                }
            })
            .catch(err => {
                console.error("Error buscando contrato:", err);
                alert("Error al buscar contrato.");
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }

    // --- LÓGICA OCR (Auto-ejecuta al abrir modal) ---
    async function ejecutarOCR() {
        if (!currentCapturePath) {
            document.getElementById('ocrStatus').style.display = 'none';
            return;
        }

        const status = document.getElementById('ocrStatus');
        const statusText = document.getElementById('ocrText');
        const inputMonto = document.getElementById('ap_monto_total');

        status.style.display = 'block';
        statusText.innerText = 'Inicializando OCR...';

        try {
            // Path absoluto para Tesseract (considerando que estamos en paginas/principal/)
            const imageUrl = `../../${currentCapturePath}`;

            const result = await Tesseract.recognize(
                imageUrl,
                'spa',
                {
                    logger: m => {
                        if (m.status === 'recognizing text') {
                            let prog = Math.round(m.progress * 100);
                            statusText.innerText = `Leyendo... ${prog}%`;
                        }
                    }
                }
            );

            const text = result.data.text;
            console.log("OCR Text extracted:", text);

            // Regex mejorada: Busca patrones como "Bs. 1.234,56", "Monto: 500.00" o "1.234,56 Bs"
            // Caso 1: Etiqueta -> Numero (Bs. 1.000)
            const regexLabelNum = /(?:Bs|VES|Monto|Pagado|Importe|Total)[.:\s]*([\d.]+,\d{2}|[\d,]+.\d{2}|[\d.,]+)/gi;
            // Caso 2: Numero -> Etiqueta (1.000 Bs)
            const regexNumLabel = /([\d.]+,\d{2}|[\d,]+.\d{2}|[\d.,]+)[.:\s]*(?:Bs|VES)/gi;

            let detectedVal = null;

            // Intentar Caso 1
            let m1 = [...text.matchAll(regexLabelNum)];
            for (const match of m1) {
                let clean = match[1].trim();
                let val = parseOCRNumber(clean);
                if (val > 0) { detectedVal = val; break; }
            }

            // Intentar Caso 2 si no se detectó nada
            if (!detectedVal) {
                let m2 = [...text.matchAll(regexNumLabel)];
                for (const match of m2) {
                    let clean = match[1].trim();
                    let val = parseOCRNumber(clean);
                    if (val > 0) { detectedVal = val; break; }
                }
            }

            function parseOCRNumber(str) {
                // Normalizar formato (quitar puntos de miles, cambiar coma a punto decimal)
                let clean = str;
                if (clean.includes(',') && clean.includes('.')) {
                    // Formato XXX.XXX,XX o XXX,XXX.XX (depende del OCR)
                    // Asumimos el más común en Venezuela: . miles , decimal
                    if (clean.lastIndexOf(',') > clean.lastIndexOf('.')) {
                        clean = clean.replace(/\./g, '').replace(',', '.');
                    } else {
                        clean = clean.replace(/,/g, '');
                    }
                } else if (clean.includes(',')) {
                    clean = clean.replace(',', '.');
                }
                return parseFloat(clean);
            }

            if (detectedVal) {
                inputMonto.value = detectedVal.toFixed(2);
                const displayVal = detectedVal.toLocaleString('es-VE', { minimumFractionDigits: 2 }) + ' Bs.';
                statusText.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Monto detectado en capture: <strong>Bs. ' + displayVal + '</strong></span>';

                // Actualizar comparativa en el modal
                const valOcrEl = document.getElementById('val_monto_ocr');
                valOcrEl.innerText = displayVal;
                
                // Comparar con reporte de usuario
                if (Math.abs(detectedVal - window.montoReportadoUsuario) < 0.01) {
                    valOcrEl.className = 'badge bg-success';
                } else {
                    valOcrEl.className = 'badge bg-danger';
                    document.getElementById('mismatch-warning').classList.remove('d-none');
                }
            } else {
                statusText.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Monto no detectado — ingrese manualmente</span>';
                document.getElementById('val_monto_ocr').innerText = 'No detectado';
                document.getElementById('val_monto_ocr').className = 'badge bg-warning text-dark';
                document.getElementById('mismatch-warning').classList.remove('d-none');
            }

            // --- Extraer Referencia (Mejorada) ---
            let detectedRef = null;
            const refRegex = /(?:[Rr]ef(?:erencia)?|[Oo]p(?:eraci[oó]n)?|ID)[.:\s]*(\d{4,12})/i;
            const mRef = text.match(refRegex);
            if (mRef && mRef[1] && mRef[1].length >= 4) {
                detectedRef = mRef[1];
            } else {
                // Buscamos secuencias de 6 a 12 dígitos como fallback
                const fallbackRefRegex = /(?<![\d.,])(\d{6,12})(?![\d.,])/g;
                let matches = [...text.matchAll(fallbackRefRegex)];
                if (matches.length > 0) {
                     detectedRef = matches[matches.length - 1][1]; 
                }
            }
            if (detectedRef) {
                document.getElementById('ap_referencia').value = detectedRef;
                statusText.innerHTML += `<br><span class="text-success small"><i class="fas fa-check"></i> Referencia sugerida: <strong>${detectedRef}</strong></span>`;
                
                // Actualizar comparativa de referencia
                const refOcrEl = document.getElementById('val_ref_ocr');
                refOcrEl.innerText = detectedRef;

                // Comparar con reporte de usuario (limpiar posibles ceros a la izquierda o espacios)
                const refUser = window.referenciaReportadaUsuario.trim().replace(/^0+/, '');
                const refDetected = detectedRef.trim().replace(/^0+/, '');

                if (refUser === refDetected || refUser.endsWith(refDetected) || refDetected.endsWith(refUser)) {
                    refOcrEl.className = 'badge bg-success';
                } else {
                    refOcrEl.className = 'badge bg-danger';
                    document.getElementById('mismatch-warning').classList.remove('d-none');
                }
            } else {
                document.getElementById('val_ref_ocr').innerText = 'No detectada';
                document.getElementById('val_ref_ocr').className = 'badge bg-warning text-dark';
                document.getElementById('mismatch-warning').classList.remove('d-none');
            }

            // --- Extraer Fecha ---
            let detectedDate = null;
            // Buscar DD/MM/YYYY, DD-MM-YYYY o DD/MM/YY
            const dateRegex = /\b(\d{2})[\/\-](\d{2})[\/\-](\d{2,4})\b/g;
            let mDate = [...text.matchAll(dateRegex)];
            if (mDate.length > 0) {
                 let day = mDate[0][1];
                 let month = mDate[0][2];
                 let year = mDate[0][3];
                 if (year.length === 2) year = "20" + year; // Asumir 20xx para años de 2 dígitos
                 detectedDate = `${year}-${month}-${day}`;
            }
            if (detectedDate) {
                 document.getElementById('ap_fecha_pago').value = detectedDate;
                 // Formato español para visualización
                 const [y, m, d] = detectedDate.split('-');
                 statusText.innerHTML += `<br><span class="text-success small"><i class="fas fa-check"></i> Fecha de comprobante: <strong>${d}/${m}/${y}</strong></span>`;
            }

        } catch (err) {
            console.error("OCR Error:", err);
            statusText.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Error procesando OCR comprobante</span>';
        } finally {
            // Eliminar spinner al concluir el OCR para indicar que la carga finalizó
            const spinner = document.querySelector('#ocrStatus .spinner-border');
            if (spinner) spinner.style.display = 'none';
        }
    }
    // === VALIDACIÓN DE MONTOS (SOLO NÚMEROS Y POSITIVOS) ===
    document.addEventListener('DOMContentLoaded', function () {
        const inputMonto = document.getElementById('ap_monto_total');
        if (inputMonto) {
            inputMonto.addEventListener('keydown', function (e) {
                if ([46, 8, 9, 27, 13, 110, 190, 188].indexOf(e.keyCode) !== -1 ||
                    (e.ctrlKey === true && [65, 67, 86, 88, 82].indexOf(e.keyCode) !== -1) ||
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                    return;
                }
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            });

            inputMonto.addEventListener('paste', function (e) {
                const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                if (/[^0-9,.]/.test(pasteData)) {
                    e.preventDefault();
                    const cleanedData = pasteData.replace(/[^0-9,.]/g, '');
                    document.execCommand('insertText', false, cleanedData);
                }
            });

            inputMonto.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9.,]/g, '');
                if (parseFloat(this.value) < 0) this.value = 0;
            });
        }
    });
</script>
<?php require_once '../includes/layout_foot.php'; ?>