<?php
$jsonInstaladores = '../../paginas/principal/data/instaladores.json';
$instaladoresList = [];
if (file_exists($jsonInstaladores)) {
    $instaladoresList = json_decode(file_get_contents($jsonInstaladores), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Técnico - Wireless</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-nivel3 {
            background-color: #dc3545;
            color: white;
        }

        /* Estilos para badges de prioridad seleccionables */
        .priority-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            opacity: 0.6;
            font-size: 0.85rem;
        }

        .priority-badge:hover {
            transform: translateY(-2px);
            opacity: 1;
        }

        .priority-badge.active {
            border-color: #000;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            transform: scale(1.05);
            opacity: 1;
        }

        .priority-nivel1 {
            background-color: #ffff00;
            color: #000;
        }

        .priority-nivel2 {
            background-color: #fd7e14;
            color: white;
        }

        .priority-nivel3 {
            background-color: #dc3545;
            color: white;
        }

        body {
            background-color: #f8f9fa;
        }

        .signature-pad {
            border: 2px dashed #ccc;
            border-radius: 5px;
            width: 100%;
            height: 200px;
            background-color: #fff;
            touch-action: none;
        }

        .section-title {
            background-color: #e9ecef;
            padding: 8px 15px;
            border-left: 4px solid #0d6efd;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h4 class="mb-0"><i class="fas fa-file-contract me-2"></i>Reporte de Visita Técnica</h4>
                    </div>
                    <div class="card-body p-4">
                        <form id="formReporte">

                            <!-- 1. Encabezado -->
                            <div class="row mb-3">
                                <div class="col-md-3 mb-2">
                                    <label class="form-label fw-bold">Fecha</label>
                                    <input type="date" class="form-control" name="fecha"
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label fw-bold">Hora Solución</label>
                                    <input type="time" class="form-control" name="hora_solucion"
                                        value="<?php echo date('H:i'); ?>" step="1" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label fw-bold">Técnico</label>
                                    <select class="form-select" name="tecnico">
                                        <option value="">-- Seleccionar --</option>
                                        <?php foreach ($instaladoresList as $instalador): ?>
                                            <option value="<?php echo htmlspecialchars($instalador); ?>">
                                                <?php echo htmlspecialchars($instalador); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label fw-bold">Sector</label>
                                    <input type="text" class="form-control" name="sector" id="sector" placeholder="Ej. Las Malvinas">
                                </div>
                            </div>

                            <!-- 2. Cliente -->
                            <div class="section-title">Datos del Cliente</div>
                            <div class="mb-3 position-relative">
                                <label class="form-label">Buscar Titular</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="cliente_search"
                                        placeholder="Buscar por Nombre, ID o Cédula..." autocomplete="off">
                                </div>
                                <input type="hidden" name="id_contrato" id="id_contrato" required>
                                <input type="hidden" name="id_olt" id="id_olt">
                                <input type="hidden" name="id_pon" id="id_pon">
                                <div id="search_results" class="list-group position-absolute w-100 shadow"
                                    style="z-index: 1000; display: none;"></div>
                                <div id="cliente_seleccionado" class="form-text text-success fw-bold mt-2"></div>
                            </div>

                            <!-- 3. Servicio & Equipos -->
                            <div class="section-title">Detalles del Servicio</div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Tipo Servicio</label>
                                    <select class="form-select" name="tipo_servicio" id="tipo_servicio" onchange="actualizarVisibilidadServicio()">
                                        <option value="FTTH">FTTH (Fibra)</option>
                                        <option value="RADIO">Radio/Antena</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">IP Asignada</label>
                                    <input type="text" class="form-control" id="rt_ip" name="ip" placeholder="0.0.0.0"
                                        pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" readonly
                                        style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Teléfono Contacto</label>
                                    <input type="text" class="form-control" name="telefono" id="rt_telefono" placeholder="Ej. 04121234567">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4" id="seccion_onu">
                                    <label class="form-label text-muted small">Estado ONU</label>
                                    <select class="form-select" name="estado_onu">
                                        <option value="ON">ON (Encendido)</option>
                                        <option value="OFF">OFF (Apagado)</option>
                                        <option value="LOS">LOS (Sin Señal)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small">Estado Router</label>
                                    <select class="form-select" name="estado_router">
                                        <option value="ON">ON (Encendido)</option>
                                        <option value="OFF">OFF (Apagado)</option>
                                        <option value="RESET">Reset fábrica</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small">Modelo Router</label>
                                    <input type="text" class="form-control" name="modelo_router"
                                        placeholder="Ej. TpLink WR840N">
                                </div>
                            </div>

                            <!-- 4. Medición -->
                            <div class="section-title">Medición Ancho de Banda</div>
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <label class="form-label small">Bajada (MB)</label>
                                    <input type="text" class="form-control" id="bw_bajada" name="bw_bajada"
                                        placeholder="00" inputmode="decimal" pattern="[0-9]+([.][0-9]+)?">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">Subida (MB)</label>
                                    <input type="text" class="form-control" id="bw_subida" name="bw_subida"
                                        placeholder="00" inputmode="decimal" pattern="[0-9]+([.][0-9]+)?">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">Ping (ms)</label>
                                    <input type="text" class="form-control" id="bw_ping" name="bw_ping"
                                        placeholder="00 ms" inputmode="decimal" pattern="[0-9]+([.][0-9]+)?">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dispositivos Conectados</label>
                                <input type="number" class="form-control" name="num_dispositivos"
                                    placeholder="Cantidad">
                            </div>

                            <!-- 5. Antena (Caso Radio) -->
                            <div id="seccion_antena">
                                <div class="section-title">Estado Antena (Solo Radio)</div>
                                <div class="row">
                                    <div class="col-6">
                                        <label class="form-label">Estado</label>
                                        <input type="text" class="form-control" name="estado_antena"
                                            placeholder="Ej. Alineada">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Valores (dBm)</label>
                                        <input type="text" class="form-control" id="valores_antena" name="valores_antena"
                                            placeholder="Ej. -55" pattern="-?[0-9.]+">
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Observaciones -->
                            <div class="section-title">Diagnóstico y Solución</div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Prioridad / Clasificación</label>
                                <div class="d-flex gap-2 flex-wrap mb-2">
                                    <span class="priority-badge priority-nivel1 active" data-priority="NIVEL 1">
                                        <i class="fa-brands fa-whatsapp me-1"></i>NIVEL 1 (WhatsApp)
                                    </span>
                                    <span class="priority-badge priority-nivel2" data-priority="NIVEL 2">
                                        <i class="fa-solid fa-house-chimney-user me-1"></i>NIVEL 2 (Visita Técnico)
                                    </span>
                                </div>
                                <input type="hidden" id="prioridad" name="prioridad" value="NIVEL 1">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Tipo de Falla</label>
                                <select class="form-select" name="tipo_falla" id="tipo_falla" required>
                                    <option value="">Cargando opciones...</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones Adicionales</label>
                                <textarea class="form-control" name="observaciones" rows="3"
                                    placeholder="Detalles adicionales sobre la falla o solución aplicada..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sugerencias al Cliente</label>
                                <textarea class="form-control" name="sugerencias" rows="2"
                                    placeholder="Recomendaciones..."></textarea>
                            </div>

                            <div class="form-check form-switch mb-3 ms-3 p-3 bg-light rounded">
                                <input class="form-check-input" type="checkbox" id="solucion_completada"
                                    name="solucion_completada" checked>
                                <label class="form-check-label fw-bold" for="solucion_completada">¿Solución de Falla
                                    Completada?</label>
                            </div>

                            <!-- Payment Section -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-danger">Costo de Visita ($)</label>
                                    <input type="number" step="0.01" class="form-control form-control-lg"
                                        id="monto_total" name="monto_total" value="0.00" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-success">Monto Pagado ($)</label>
                                    <input type="number" step="0.01" class="form-control form-control-lg"
                                        id="monto_pagado" name="monto_pagado" value="0.00" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-warning">Saldo Pendiente ($)</label>
                                    <input type="number" step="0.01" class="form-control form-control-lg bg-light"
                                        id="saldo_pendiente" name="saldo_pendiente" value="0.00" readonly>
                                </div>
                            </div>
                            <div id="payment_warning" class="alert alert-warning d-none" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Atención:</strong> El cliente quedará registrado en la lista de deudores con un
                                saldo pendiente de $<span id="warning_amount">0.00</span> (<span
                                    id="warning_amount_bs">0.00</span> Bs.).
                            </div>

                            <!-- 7. Firmas -->
                            <div class="section-title">Conformidad</div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Firma del Técnico</label>
                                <canvas id="sigTech" class="signature-pad"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                    onclick="clearPad('tech')">Limpiar</button>
                                <input type="hidden" name="firma_tecnico_data" id="firma_tecnico_data">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Firma del Cliente</label>
                                <canvas id="sigCli" class="signature-pad"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                    onclick="clearPad('cli')">Limpiar</button>
                                <input type="hidden" name="firma_cliente_data" id="firma_cliente_data">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="btnGuardar">
                                    <i class="fas fa-save me-2"></i> Guardar Reporte
                                </button>
                                <button type="button" class="btn btn-outline-success btn-lg" id="btnGenerarLink">
                                    <i class="fa-brands fa-whatsapp me-2"></i> Guardar y Generar Link de Firma
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Link Generado -->
    <div class="modal fade" id="modalLink" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-link me-2"></i>Enlace Generado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        onclick="location.reload()"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="mb-4">
                        <i class="fa-regular fa-circle-check text-success fa-4x mb-3"></i>
                        <h4>¡Soporte Guardado!</h4>
                        <p class="text-muted">El soporte ha sido guardado como pendiente de firma.</p>
                    </div>

                    <label class="form-label fw-bold text-start w-100">Enlace para el Cliente:</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="linkInput" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copiarLink()">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="#" id="btnWhatsapp" target="_blank" class="btn btn-success">
                            <i class="fa-brands fa-whatsapp me-2"></i> Enviar por WhatsApp
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="location.reload()">
                            Cerrar y Nuevo Registro
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // =================================================================
        // VALIDACIÓN Y RESTRICCIÓN DE CAMPOS EN TIEMPO REAL
        // =================================================================

        function actualizarVisibilidadServicio() {
            const tipo = document.getElementById('tipo_servicio').value;
            const seccionAntena = document.getElementById('seccion_antena');
            const seccionOnu = document.getElementById('seccion_onu');

            if (tipo === 'RADIO') {
                seccionAntena.classList.remove('d-none');
                seccionOnu.classList.add('d-none');
                
                // Habilitar inputs de antena, deshabilitar ONU
                seccionAntena.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
                seccionOnu.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
            } else {
                seccionAntena.classList.add('d-none');
                seccionOnu.classList.remove('d-none');
                
                // Deshabilitar inputs de antena, habilitar ONU
                seccionAntena.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
                seccionOnu.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
            }
        }

        // Ejecutar al cargar para establecer estado inicial
        document.addEventListener('DOMContentLoaded', actualizarVisibilidadServicio);

        // IP: solo dígitos y puntos
        document.getElementById('rt_ip')?.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9.]/g, '');
        });

        // Ancho de banda y ping: solo dígitos y punto decimal
        ['bw_bajada', 'bw_subida', 'bw_ping'].forEach(function (id) {
            document.getElementById(id)?.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9.]/g, '');
            });
        });

        // Valores antena (dBm): dígitos, punto decimal y un guión inicial
        document.getElementById('valores_antena')?.addEventListener('input', function () {
            let val = this.value.replace(/[^0-9.-]/g, '');
            if (val.indexOf('-') > 0) val = val.substring(0, val.indexOf('-')) + val.substring(val.indexOf('-') + 1);
            this.value = val;
        });

        // Inicializar Signature Pads
        const canvasTech = document.getElementById('sigTech');
        const canvasCli = document.getElementById('sigCli');

        // Ajustar tamaño canvas a display
        function resizeCanvas(canvas) {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
        }
        window.onresize = function () { resizeCanvas(canvasTech); resizeCanvas(canvasCli); };
        resizeCanvas(canvasTech); resizeCanvas(canvasCli);

        const padTech = new SignaturePad(canvasTech);
        const padCli = new SignaturePad(canvasCli);

        function clearPad(type) {
            if (type === 'tech') padTech.clear();
            if (type === 'cli') padCli.clear();
        }

        // Payment calculation logic
        const montoTotalInput = document.getElementById('monto_total');
        const montoPagadoInput = document.getElementById('monto_pagado');
        const saldoPendienteInput = document.getElementById('saldo_pendiente');
        const paymentWarning = document.getElementById('payment_warning');
        const warningAmount = document.getElementById('warning_amount');
        const warningAmountBs = document.getElementById('warning_amount_bs');

        let tasaDolar = 0;

        // Fetch exchange rate on load
        fetch('../principal/get_tasa_dolar.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    tasaDolar = data.promedio;
                    console.log('Tasa Dólar cargada:', tasaDolar);
                    calcularSaldo(); // Recalcular con la tasa cargada
                }
            })
            .catch(err => console.error('Error al obtener tasa:', err));

        function calcularSaldo() {
            const total = parseFloat(montoTotalInput.value) || 0;
            const pagado = parseFloat(montoPagadoInput.value) || 0;

            // Validation: paid amount cannot exceed total
            if (pagado > total) {
                montoPagadoInput.value = total.toFixed(2);
                return;
            }

            const saldo = total - pagado;
            saldoPendienteInput.value = saldo.toFixed(2);

            // Show/hide warning
            if (saldo > 0) {
                warningAmount.textContent = saldo.toFixed(2);

                // Conversión a Bs.
                if (tasaDolar > 0) {
                    const saldoBs = saldo * tasaDolar;
                    warningAmountBs.textContent = saldoBs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    warningAmountBs.textContent = "---";
                }

                paymentWarning.classList.remove('d-none');
            } else {
                paymentWarning.classList.add('d-none');
            }
        }

        montoTotalInput.addEventListener('input', calcularSaldo);
        montoPagadoInput.addEventListener('input', calcularSaldo);
        montoPagadoInput.addEventListener('blur', function () {
            // Ensure paid amount doesn't exceed total on blur
            const total = parseFloat(montoTotalInput.value) || 0;
            const pagado = parseFloat(this.value) || 0;
            if (pagado > total) {
                this.value = total.toFixed(2);
                calcularSaldo();
            }
        });

        // Lógica para Generar Link
        const btnGenerarLink = document.getElementById('btnGenerarLink');
        const modalLink = new bootstrap.Modal(document.getElementById('modalLink'));

        btnGenerarLink.addEventListener('click', function () {
            const form = document.getElementById('formReporte');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            formData.append('generate_link', '1');

            // Validar si hay cliente seleccionado
            if (!formData.get('id_contrato')) {
                Swal.fire('Error', 'Seleccione un cliente primero', 'warning');
                return;
            }

            // UI Loading
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando...';

            fetch('guardar_soporte.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    this.classList.remove('btn-outline-success'); // fix visual state if needed
                    this.disabled = false;
                    this.innerHTML = originalText;

                    if (data.status === 'success') {
                        // Mostrar Modal
                        const baseUrl = window.location.origin + window.location.pathname.split('/paginas/')[0] + '/paginas/soporte/';
                        const linkCompleto = baseUrl + data.link;
                        document.getElementById('linkInput').value = linkCompleto;

                        // Configurar WhatsApp
                        // Obtener telefono del cliente limpiando caracteres
                        /* Nota: No tenemos el telefono a mano fácil en el DOM salvo que lo hayamos guardado al seleccionar cliente.
                           Podemos intentar obtenerlo o dejar el link para que el usuario seleccione contacto. */
                        const mensaje = `Hola, aquí tienes el enlace para firmar el reporte técnico: ${linkCompleto}`;
                        const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(mensaje)}`;
                        document.getElementById('btnWhatsapp').href = whatsappUrl;

                        modalLink.show();
                    } else {
                        Swal.fire('Error', data.msg, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    this.disabled = false;
                    this.innerHTML = originalText;
                    Swal.fire('Error', 'Error de conexión', 'error');
                });
        });

        function copiarLink() {
            const copyText = document.getElementById("linkInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);

            const btn = document.querySelector('button[onclick="copiarLink()"]');
            const icon = btn.querySelector('i');
            icon.className = 'fa-solid fa-check';
            setTimeout(() => { icon.className = 'fa-regular fa-copy'; }, 2000);
        }

        // Cargar opciones de falla dinámicamente
        function cargarOpcionesFalla() {
            const nivel = $('#prioridad').val(); // NIVEL 1 o NIVEL 2
            
            fetch('admin_opciones.php?action=read')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('tipo_falla');
                        select.innerHTML = '<option value="">-- Seleccionar tipo de falla --</option>';
                        
                        // Filtrar por el nivel actual
                        const opciones = data.data[nivel] || [];
                        
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

        // Recargar opciones cuando cambie el nivel
        $('.priority-badge').click(function () {
            $('.priority-badge').removeClass('active');
            $(this).addClass('active');
            $('#prioridad').val($(this).data('priority'));
            cargarOpcionesFalla(); // Recargar al cambiar nivel
        });

        // Iniciar carga
        cargarOpcionesFalla();

        // Buscador AJAX
        const searchInput = document.getElementById('cliente_search');
        const resultsDiv = document.getElementById('search_results');
        const idInput = document.getElementById('id_contrato');
        const selectedDiv = document.getElementById('cliente_seleccionado');

        searchInput.addEventListener('input', function () {
            const term = this.value;
            if (term.length < 3) { resultsDiv.style.display = 'none'; return; }

            fetch(`../principal/buscar_contratos.php?q=${term}`)
                .then(r => r.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const a = document.createElement('a');
                            a.className = 'list-group-item list-group-item-action';
                            a.innerHTML = `<strong>${item.nombre_completo}</strong><br><small class="text-muted">ID: ${item.id} | Cédula: ${item.cedula}</small>`;
                            a.href = '#';
                            a.onclick = (e) => {
                                e.preventDefault();
                                searchInput.value = item.nombre_completo;
                                idInput.value = item.id;
                                selectedDiv.textContent = 'Cliente Seleccionado: ' + item.nombre_completo;
                                resultsDiv.style.display = 'none';

                                // --- AUTOMATIC DATA POPULATION ---
                                // Fetch full details to get community, IP, etc.
                                fetch(`../principal/get_contrato_detalle.php?id=${item.id}`)
                                    .then(r => r.json())
                                    .then(details => {
                                        if (details.error) return;

                                        // 1. Sector y Infraestructura
                                        const sectorInput = document.getElementById('sector');
                                        const idOltInput = document.getElementById('id_olt');
                                        const idPonInput = document.getElementById('id_pon');

                                        if (sectorInput) sectorInput.value = details.nombre_comunidad || details.nombre_parroquia || "";
                                        if (idOltInput) idOltInput.value = details.id_olt || "";
                                        if (idPonInput) idPonInput.value = details.id_pon || "";

                                        // 2. IP y Contacto
                                        const ipInput = document.getElementById('rt_ip');
                                        const telInput = document.getElementById('rt_telefono');
                                        if (ipInput) ipInput.value = details.ip_onu || "";
                                        if (telInput) telInput.value = details.telefono || "";

                                        // 3. Tipo Servicio (Mapeo de 'FTTH'/'RADIO' o similares)
                                        const serviceSelect = document.querySelector('select[name="tipo_servicio"]');
                                        if (serviceSelect && details.tipo_conexion) {
                                            const type = details.tipo_conexion.toUpperCase();
                                            if (type.includes('FIBRA') || type.includes('FTTH')) {
                                                serviceSelect.value = 'FTTH';
                                            } else if (type.includes('RADIO') || type.includes('ANTENA') || type.includes('INALAMBRICO')) {
                                                serviceSelect.value = 'RADIO';
                                            }
                                        }

                                        // 4. Modelo Router (Si existe en contrato)
                                        const routerInput = document.querySelector('input[name="modelo_router"]');
                                        if (routerInput && details.modelo_router) {
                                            routerInput.value = details.modelo_router;
                                        }

                                        // 5. Valores Antena (Si es Radio y existe valor)
                                        const antenaInput = document.getElementById('valores_antena');
                                        if (antenaInput && details.valor_conexion_dbm) {
                                            antenaInput.value = details.valor_conexion_dbm;
                                        }

                                        Swal.fire({
                                            title: 'Datos Importados',
                                            text: 'Se han cargado los datos del contrato automáticamente.',
                                            icon: 'info',
                                            timer: 2000,
                                            showConfirmButton: false,
                                            toast: true,
                                            position: 'top-end'
                                        });

                                        // Actualizar visibilidad después de importar
                                        actualizarVisibilidadServicio();
                                    })
                                    .catch(err => console.error('Error fetching contract details:', err));
                            };
                            resultsDiv.appendChild(a);
                        });
                        resultsDiv.style.display = 'block';
                    }
                });
        });


        // Enviar Formulario
        document.getElementById('formReporte').addEventListener('submit', function (e) {
            e.preventDefault();

            if (!idInput.value) {
                Swal.fire('Error', 'Debe buscar y seleccionar un cliente.', 'error');
                return;
            }

            // Guardar firmas en inputs ocultos
            if (!padTech.isEmpty()) document.getElementById('firma_tecnico_data').value = padTech.toDataURL();
            if (!padCli.isEmpty()) document.getElementById('firma_cliente_data').value = padCli.toDataURL();

            const formData = new FormData(this);

            document.getElementById('btnGuardar').disabled = true;
            document.getElementById('btnGuardar').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            fetch('guardar_reporte_publico.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            title: '¡Guardado!',
                            text: 'Reporte registrado con éxito.',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fas fa-file-pdf"></i> Ver PDF',
                            cancelButtonText: 'Cerrar',
                            confirmButtonColor: '#0dcaf0'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('generar_pdf_reporte.php?id=' + data.id_soporte, '_blank');
                            }
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.msg, 'error');
                        document.getElementById('btnGuardar').disabled = false;
                        document.getElementById('btnGuardar').textContent = 'Guardar Reporte';
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Error de conexión con el servidor', 'error');
                    document.getElementById('btnGuardar').disabled = false;
                });
        });

    </script>

</body>

</html>