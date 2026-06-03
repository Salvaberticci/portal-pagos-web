<?php
/**
 * Registro Rápido de Fallas
 * Formulario simplificado para registrar fallas antes de la visita técnica
 */
$path_to_root = "../../";
$page_title = "Registro de Falla Masiva (NIVEL 3)";
$breadcrumb = ["Soporte", "Falla Masiva"];
$back_url = "gestion_fallas.php";
require_once $path_to_root . 'paginas/conexion.php';
require_once $path_to_root . 'paginas/includes/layout_head.php';

// Obtener OLTs
$olts = [];
$res_olt = $conn->query("SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt ASC");
while ($row = $res_olt->fetch_assoc()) {
    $olts[] = $row;
}
?>

<style>
    .priority-badge {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        opacity: 0.6;
    }

    .priority-badge:hover {
        transform: translateY(-2px);
        opacity: 1;
    }

    .priority-badge.active {
        border-color: var(--primary);
        box-shadow: 0 0 15px rgba(37, 99, 235, 0.3);
        transform: scale(1.05);
        opacity: 1;
    }

    .priority-nivel1 { background-color: #ffff00; color: #000; }
    .priority-nivel2 { background-color: #fd7e14; color: white; }
    .priority-nivel3 { background-color: #dc3545; color: white; }

    .form-section {
        background: var(--bg-card);
        backdrop-filter: blur(var(--glass-blur));
        border: 1px solid var(--border-glass);
        padding: 25px;
        border-radius: 20px;
        margin-bottom: 25px;
        box-shadow: var(--card-shadow);
        transition: transform 0.3s ease;
    }

    .form-section:hover {
        transform: translateY(-2px);
    }

    .section-title {
        background: rgba(0, 0, 0, 0.05) !important;
        border-left: 4px solid var(--primary);
        color: var(--text-main) !important;
        padding: 10px 15px !important;
        margin-bottom: 20px !important;
        font-weight: 700 !important;
        border-radius: 4px;
        display: flex;
        align-items: center;
    }

    [data-theme="dark"] .section-title {
        background: rgba(255, 255, 255, 0.05) !important;
    }

    .critical-alert {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.2);
        padding: 20px;
        border-radius: 12px;
        color: var(--text-main);
    }

    .signature-pad {
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        width: 100%;
        height: 150px;
        background-color: var(--input-bg);
        touch-action: none;
    }

    .btn-cancel-custom {
        background-color: rgba(128, 128, 128, 0.1) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--border-glass) !important;
        transition: all 0.3s ease !important;
    }

    .btn-cancel-custom:hover {
        background-color: rgba(128, 128, 128, 0.2) !important;
        color: var(--primary) !important;
    }

    /* Custom Switch for Dark Mode */
    .form-check-input:checked {
        background-color: var(--danger);
        border-color: var(--danger);
    }

    .input-group-text {
        background-color: var(--table-header-bg);
        border: 1px solid var(--input-border);
        color: var(--text-muted);
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
    }

    .bg-premium-footer {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(10px);
    }

</style>

<main class="main-content">
    <?php include $path_to_root . 'paginas/includes/header.php'; ?>

    <div class="page-content">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h3 fw-bold text-gradient mb-1">
                            <i class="fa-solid fa-network-wired me-2"></i>Registro de Falla Masiva (NIVEL 3)
                        </h2>
                        <p class="text-muted">Reporte de caídas de red, OLTs o zonas completas</p>
                    </div>
                    <a href="gestion_fallas.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i>Volver
                    </a>
                </div>
            </div>
        </div>

        <form id="formRegistroFalla" class="needs-validation" novalidate>
            <!-- Sección 1: Información de Referencia -->
            <div class="form-section">
                <div class="section-title"><i class="fa-solid fa-user me-2"></i>Cliente Reportante (Referencia)</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Buscar un cliente afectado como referencia <span class="text-danger">*</span></label>
                        <div class="row g-2 align-items-center">
                            <div class="col-md-9">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" class="form-control" id="cliente_search"
                                        placeholder="Nombre, ID o Cédula..." autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="telefono" id="telefono" placeholder="Teléfono Contacto">
                            </div>
                        </div>
                        <input type="hidden" name="id_contrato" id="id_contrato" required>
                        <div id="search_results" class="list-group position-absolute w-100 shadow mt-1"
                            style="z-index: 1000; display: none; max-height: 300px; overflow-y: auto;"></div>
                        <div id="cliente_seleccionado" class="form-text text-success fw-bold mt-2"></div>
                        <div class="invalid-feedback">Debe seleccionar al menos un cliente de referencia</div>
                    </div>
                </div>
            </div>


            <!-- Sección 2: Clasificación de la Falla -->
            <div class="form-section">
                <div class="section-title border-danger"><i class="fa-solid fa-tag me-2"></i>Clasificación de la Falla</div>

                <div id="olts-container">
                    <!-- Fila 1 (siempre visible) -->
                    <div class="olt-row row g-3 mb-3 align-items-start" data-index="0">
                        <div class="col-md-5">
                            <label class="form-label fw-bold">OLT Afectada <span class="text-danger">*</span></label>
                            <select class="form-select olt-select" name="olts[]" required>
                                <option value="">Seleccione OLT...</option>
                                <?php foreach ($olts as $olt): ?>
                                    <option value="<?php echo $olt['id_olt']; ?>"><?php echo htmlspecialchars($olt['nombre_olt']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">PON Afectado <span class="text-muted fw-normal">(Opcional)</span></label>
                            <select class="form-select pon-select" name="pons[]">
                                <option value="">Primero seleccione OLT...</option>
                            </select>
                            <small class="text-muted">Deja en blanco si es caída total de la OLT</small>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-olt d-none" title="Eliminar fila">
                                <i class="fa-solid fa-trash-can"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" id="btn-add-olt" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-plus me-1"></i> Agregar otra OLT / PON
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Tipo de Falla <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_falla" name="tipo_falla" required>
                            <option value="">Cargando opciones...</option>
                        </select>
                        <div class="invalid-feedback">Debe seleccionar un tipo de falla</div>
                    </div>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <div class="p-3 border border-danger border-opacity-20 rounded bg-danger bg-opacity-5 h-100">
                            <label class="form-label fw-bold text-danger"><i class="fa-solid fa-users me-2"></i>Estimación de Clientes Afectados</label>
                            <input type="number" class="form-control form-control-lg border-danger border-opacity-50" id="clientes_afectados" name="clientes_afectados"
                                min="1" value="50">
                            <small class="text-muted">Introduce una cifra estimada (Ej. 100, 200...)</small>
                        </div>
                        <input type="hidden" name="es_caida_critica" value="1">
                    </div>
                    <div class="col-md-6">
                        <div class="critical-alert h-100 mb-0 shadow-sm border-danger" style="display: block;">
                            <h6 class="fw-bold mb-2">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>Reporte de Gravedad
                            </h6>
                            <p class="mb-0 small">
                                Las fallas de Nivel 3 activan alertas inmediatas para el equipo de infraestructura.
                                Asegúrese de que la OLT esté correctamente seleccionada.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 3: Ubicación -->
            <div class="form-section">
                <div class="section-title"><i class="fa-solid fa-map-marker-alt me-2"></i>Ubicación y Zona</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Sector</label>
                        <input type="text" class="form-control" id="sector" name="sector">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Zona Afectada</label>
                        <input type="text" class="form-control" id="zona_afectada" name="zona_afectada"
                            placeholder="Ej: Urbanización Los Pinos, Calle Principal">
                        <small class="text-muted">Especifica la zona geográfica para análisis</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion">
                    </div>
                </div>
            </div>

            <!-- Sección 4: Descripción -->
            <div class="form-section">
                <div class="section-title"><i class="fa-solid fa-file-lines me-2"></i>Descripción de la Falla</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Síntomas Reportados <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" required
                            minlength="10" placeholder="Describe los síntomas reportados por el cliente..."></textarea>
                        <div class="invalid-feedback">Debe describir los síntomas (mínimo 10 caracteres)</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Equipos Potencialmente Afectados</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="afecta_onu" value="ONU">
                                <label class="form-check-label" for="afecta_onu">ONU</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="afecta_router" value="Router">
                                <label class="form-check-label" for="afecta_router">Router</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="afecta_antena" value="Antena">
                                <label class="form-check-label" for="afecta_antena">Antena</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="afecta_fibra" value="Fibra">
                                <label class="form-check-label" for="afecta_fibra">Fibra</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="afecta_cable" value="Cable">
                                <label class="form-check-label" for="afecta_cable">Cable</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 5: Asignación -->
            <div class="form-section">
                <div class="section-title"><i class="fa-solid fa-user-gear me-2"></i>Asignación</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Técnico Asignado <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tecnico_asignado" name="tecnico_asignado" required>
                        <div class="invalid-feedback">Debe asignar un técnico</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha/Hora de Reporte</label>
                        <input type="datetime-local" class="form-control" id="fecha_reporte" name="fecha_reporte"
                            value="<?php echo date('Y-m-d\TH:i'); ?>" readonly>
                        <small class="text-muted">Se registra automáticamente</small>
                    </div>
                </div>
            </div>

            <!-- Sección 6: Notas Internas -->
            <div class="form-section">
                <div class="section-title"><i class="fa-solid fa-note-sticky me-2"></i>Notas Internas</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Notas del Operador</label>
                        <textarea class="form-control" id="notas_internas" name="notas_internas" rows="3"
                            placeholder="Notas internas que no serán visibles en el reporte al cliente..."></textarea>
                        <small class="text-muted">Estas notas son solo para uso interno</small>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end p-4 bg-premium-footer rounded-pill shadow-lg mb-5">
                        <a href="gestion_fallas.php" class="btn btn-cancel-custom px-4">
                            <i class="fa-solid fa-times me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-premium px-5">
                            <i class="fa-solid fa-save me-1"></i>REGISTRAR FALLA MASIVA
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>




<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {

        // =========================================================
        // --- Lógica de filas dinámicas OLT / PON ---
        // =========================================================

        // Cargar PONs para un select concreto dado el OLT seleccionado
        function cargarPons(oltSelect) {
            const id_olt = $(oltSelect).val();
            const ponSelect = $(oltSelect).closest('.olt-row').find('.pon-select');

            ponSelect.empty().append('<option value="">Cargando...</option>');

            if (!id_olt) {
                ponSelect.html('<option value="">Primero seleccione OLT...</option>');
                return;
            }

            $.ajax({
                url: 'get_pons_ajax.php',
                data: { id_olt: id_olt },
                dataType: 'json',
                success: function(data) {
                    ponSelect.empty().append('<option value="">Toda la OLT (sin PON específico)</option>');
                    data.forEach(function(pon) {
                        ponSelect.append(`<option value="${pon.id_pon}">${pon.nombre_pon}</option>`);
                    });
                },
                error: function() {
                    ponSelect.html('<option value="">Error al cargar PONs</option>');
                }
            });
        }

        // Delegación de evento: cuando cambia un select OLT dentro del contenedor
        $('#olts-container').on('change', '.olt-select', function() {
            cargarPons(this);
        });

        // Agregar nueva fila OLT/PON
        let rowIndex = 1;
        $('#btn-add-olt').click(function() {
            const newRow = $(`
                <div class="olt-row row g-3 mb-3 align-items-start" data-index="${rowIndex}">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">OLT Afectada <span class="text-danger">*</span></label>
                        <select class="form-select olt-select" name="olts[]" required>
                            <option value="">Seleccione OLT...</option>
                            <?php foreach ($olts as $olt): echo '<option value="' . $olt['id_olt'] . '">' . htmlspecialchars($olt['nombre_olt']) . '</option>'; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">PON Afectado <span class="text-muted fw-normal">(Opcional)</span></label>
                        <select class="form-select pon-select" name="pons[]">
                            <option value="">Primero seleccione OLT...</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-olt" title="Eliminar fila">
                            <i class="fa-solid fa-trash-can"></i> Eliminar
                        </button>
                    </div>
                </div>
            `);
            $('#olts-container').append(newRow);
            rowIndex++;
        });

        // Eliminar fila
        $('#olts-container').on('click', '.btn-remove-olt', function() {
            $(this).closest('.olt-row').remove();
        });

        // Buscador AJAX de clientes (igual que en reporte_tecnico.php)
        const searchInput = document.getElementById('cliente_search');
        const resultsDiv = document.getElementById('search_results');
        const idInput = document.getElementById('id_contrato');
        const selectedDiv = document.getElementById('cliente_seleccionado');
        let clienteData = null;

        searchInput.addEventListener('input', function () {
            const term = this.value;
            if (term.length < 3) {
                resultsDiv.style.display = 'none';
                return;
            }

            fetch(`../principal/buscar_contratos.php?q=${term}`)
                .then(r => r.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const a = document.createElement('a');
                            a.className = 'list-group-item list-group-item-action';
                            a.innerHTML = `<strong>${item.nombre_completo}</strong><br><small class="text-muted">ID: ${item.id} | Cédula: ${item.cedula} | IP: ${item.ip}</small>`;
                            a.href = '#';
                            a.onclick = (e) => {
                                e.preventDefault();
                                searchInput.value = item.nombre_completo;
                                idInput.value = item.id;
                                selectedDiv.textContent = '✓ Cliente Seleccionado: ' + item.nombre_completo;
                                resultsDiv.style.display = 'none';

                                // Guardar datos del cliente
                                clienteData = item;
                                $('#direccion').val(item.direccion || '');
                                $('#sector').val(item.sector || '');
                                $('#telefono').val(item.telefono || '');
                            };
                            resultsDiv.appendChild(a);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="list-group-item text-muted">No se encontraron resultados</div>';
                        resultsDiv.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Error en búsqueda:', err);
                    resultsDiv.innerHTML = '<div class="list-group-item text-danger">Error al buscar</div>';
                    resultsDiv.style.display = 'block';
                });
        });

        // Ocultar resultados al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });

        // Manejo de selección de prioridad (kept for consistency, though fixed to NIVEL 3)
        $('.priority-badge').click(function () {
            $('.priority-badge').removeClass('active');
            $(this).addClass('active');
            $('#prioridad').val($(this).data('priority'));
        });

        // Cargar opciones de falla Nivel 3
        function cargarOpcionesFalla() {
            fetch('admin_opciones.php?action=read')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('tipo_falla');
                        select.innerHTML = '<option value="">-- Seleccionar tipo de falla Nivel 3 --</option>';
                        
                        const opciones = data.data['NIVEL 3'] || [];
                        opciones.forEach(opcion => {
                            const option = document.createElement('option');
                            option.value = opcion;
                            option.textContent = opcion;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error cargando opciones:', error));
        }
        cargarOpcionesFalla();

        // Validación y envío del formulario
        $('#formRegistroFalla').submit(function (e) {
            e.preventDefault();

            if (!this.checkValidity()) {
                e.stopPropagation();
                $(this).addClass('was-validated');
                return;
            }

            // Recopilar equipos afectados
            const equiposAfectados = [];
            $('input[type="checkbox"]:checked').each(function () {
                if ($(this).val()) {
                    equiposAfectados.push($(this).val());
                }
            });

            const formData = {
                id_contrato: $('#id_contrato').val(),
                prioridad: $('#prioridad').val(),
                tipo_falla: $('#tipo_falla').val(),
                es_caida_critica: 1,
                clientes_afectados: $('#clientes_afectados').val() || 50,
                sector: $('#sector').val(),
                zona_afectada: $('#zona_afectada').val(),
                observaciones: $('#observaciones').val(),
                equipos_afectados: equiposAfectados.join(', '),
                tecnico_asignado: $('#tecnico_asignado').val(),
                telefono: $('#telefono').val(),
                notas_internas: $('#notas_internas').val(),
                fecha_reporte: $('#fecha_reporte').val(),
                'olts[]': [],
                'pons[]': []
            };

            // Recopilar todos los pares OLT/PON
            let hasOlt = false;
            $('#olts-container .olt-row').each(function() {
                const oltVal = $(this).find('.olt-select').val();
                const ponVal = $(this).find('.pon-select').val();
                if (oltVal) {
                    formData['olts[]'].push(oltVal);
                    formData['pons[]'].push(ponVal || '');
                    hasOlt = true;
                }
            });

            if (!hasOlt) {
                Swal.fire({ icon: 'warning', title: 'OLT requerida', text: 'Debe seleccionar al menos una OLT afectada.' });
                return;
            }

            // Enviar datos
            $.ajax({
                url: 'guardar_falla.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Falla Registrada!',
                            html: `<p>Ticket #<strong>${response.id_soporte}</strong> creado exitosamente</p>
                               <p class="text-muted small">Técnico asignado: ${formData.tecnico_asignado}</p>`,
                            confirmButtonText: 'Ver Gestión de Fallas',
                            showCancelButton: true,
                            cancelButtonText: 'Registrar Otra'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'gestion_fallas.php';
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'No se pudo registrar la falla'
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error Detalles:', { xhr, status, error });
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        html: `<p>No se pudo conectar con el servidor.</p>
                               <small class="text-muted">Estado: ${xhr.status} ${status}<br>Error: ${error}</small>`
                    });
                }
            });
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>

<?php require_once $path_to_root . 'paginas/includes/layout_foot.php'; ?>