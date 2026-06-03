<?php
// Redirigir al nuevo Portal de Clientes
header("Location: portal/index.php");
exit;
?>
<?php
// reportar_pago.php - Formulario público para que clientes reporten sus pagos (DEPRECATED)
include 'paginas/conexion.php';

// Cargar bancos para el combo
$json_bancos = @file_get_contents('paginas/principal/bancos.json');
$bancosArr = json_decode($json_bancos, true) ?: [];

// Generar lista de meses base (nombres en español)
$meses_nombres = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportar Pago - Wireless Supply</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #0d6efd;
            --color-success: #198754;
        }
        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #f0f9ff 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .payment-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.10);
        }
        .header-gradient {
            background: linear-gradient(135deg, #0d6efd, #0dcaf0);
            color: white;
            border-radius: 18px 18px 0 0;
        }
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
        }
        .form-label { font-weight: 600; color: #495057; }
        .important-note { font-size: 0.82rem; color: #6c757d; }
        .btn-report {
            background: linear-gradient(135deg, #198754, #20c997);
            border: none;
            padding: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-report:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 18px rgba(25,135,84,0.35);
        }
        #contrato_search_results {
            max-height: 250px;
            overflow-y: auto;
            z-index: 1050;
        }
        .search-item { cursor: pointer; transition: background 0.2s; }
        .search-item:hover { background-color: #f0f7ff; }

        /* Plan Info Panel */
        #plan_info_panel {
            background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
            border: 1.5px solid #81c784;
            border-radius: 12px;
            padding: 14px 18px;
            display: none;
        }
        #plan_info_panel .plan-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #2e7d32;
        }
        #plan_info_panel .plan-price {
            font-size: 0.85rem;
            color: #555;
        }


        /* Amount dual field */
        .amount-group .input-group-text { font-weight: 700; min-width: 45px; justify-content: center; }
        .equiv-display {
            font-size: 0.82rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            margin-top: 4px;
            display: inline-block;
        }
        .equiv-usd { background: #e8f5e9; color: #2e7d32; }
        .equiv-bs  { background: #e3f2fd; color: #1565c0; }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="text-center mb-4">
                    <img src="images/logo.jpg" alt="Logo" class="img-fluid rounded-circle shadow-sm mb-3"
                        style="max-height: 90px;">
                    <h2 class="fw-bold">Wireless Supply, C.A.</h2>
                    <p class="text-muted mb-0">Reporte de Pago de Mensualidad</p>
                </div>


                <div class="card payment-card">
                    <div class="card-header header-gradient p-4 text-center">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Formulario de Reporte de Pago</h5>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form action="procesar_reporte_pago.php" method="POST" enctype="multipart/form-data" id="formReportePago">
                            <input type="hidden" name="id_contrato_asociado" id="id_contrato_asociado">
                            <input type="hidden" name="monto_usd" id="monto_usd_hidden">
                            <input type="hidden" name="tasa_dolar" id="tasa_dolar_hidden">

                            <!-- SECCIÓN 1: DATOS DEL TITULAR -->
                            <div class="section-title"><i class="fas fa-user me-2"></i>Datos del Titular del Servicio</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6 position-relative">
                                    <label class="form-label">Cédula o Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="cedula" id="cedula"
                                        placeholder="Buscar por cédula o nombre..." required autocomplete="off">
                                    <div id="contrato_search_results" class="list-group shadow position-absolute w-100 mt-1 d-none"></div>
                                    <div class="important-note mt-1">
                                        <i class="fas fa-search text-primary me-1"></i> Escribe tu cédula o nombre para autocompletar.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nombres y Apellidos <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre"
                                        placeholder="Ej: Juan Pérez" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Número de Teléfono <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono"
                                        placeholder="Ej: 04121234567" required pattern="[0-9+\-\s]{7,15}">
                                </div>
                            </div>

                            <!-- Plan Info Panel (shown after contract selection) -->
                            <div id="plan_info_panel" class="mb-4">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-satellite-dish fa-lg text-success"></i>
                                    <div>
                                        <div class="plan-name" id="plan_nombre_display">---</div>
                                        <div class="plan-price">
                                            Precio del plan:
                                            <strong id="plan_precio_usd_display">$0.00</strong> USD
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: DATOS DEL PAGO -->
                            <div class="section-title"><i class="fas fa-money-bill-transfer me-2"></i>Datos del Pago</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fecha del Pago <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="fecha_pago"
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                    <select class="form-select" name="metodo_pago" id="metodo_pago" required>
                                        <option value="">Seleccione...</option>
                                        <option value="Pago Móvil">Pago Móvil</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Divisas">Divisas</option>
                                        <option value="Zelle">Zelle</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="div_banco">
                                    <label class="form-label">¿A qué banco pagó? <span class="text-danger">*</span></label>
                                    <select class="form-select" name="id_banco_destino" id="id_banco_destino" required>
                                        <option value="">Seleccione el banco receptor...</option>
                                        <?php foreach ($bancosArr as $b): ?>
                                            <option value="<?php echo $b['id_banco']; ?>">
                                                <?php echo htmlspecialchars($b['nombre_banco']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6" id="div_referencia">
                                    <label class="form-label">Número de Referencia <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="referencia" id="referencia"
                                        placeholder="Últimos 4 o 6 dígitos" inputmode="numeric" pattern="[0-9]{4,20}" required>
                                </div>
                            </div>

                            <!-- Montos Dual Currency -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Monto en Bolívares (Bs) <span class="text-danger">*</span></label>
                                    <div class="input-group amount-group">
                                        <span class="input-group-text bg-primary text-white">Bs.</span>
                                        <input type="number" step="0.01" class="form-control fw-bold fs-5"
                                            id="input_monto_bs" placeholder="0.00">
                                    </div>
                                    <div class="important-note mt-1">Ingrese el monto exacto del comprobante.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Monto en Dólares (USD)</label>
                                    <div class="input-group amount-group">
                                        <span class="input-group-text bg-success text-white">$</span>
                                        <input type="number" step="0.01" class="form-control fw-bold fs-5 text-success"
                                            id="input_monto_usd_vis" placeholder="0.00">
                                    </div>
                                    <div class="important-note mt-1">Ingrese el monto correspondiente en divisas si aplica.</div>
                                </div>
                            </div>

                            <!-- Meses -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="paga_varios_meses" name="paga_varios_meses">
                                        <label class="form-check-label fw-bold" for="paga_varios_meses">¿Pagará más de un mes?</label>
                                    </div>
                                    <div id="container_meses">
                                        <label class="form-label">Concepto del Pago (Mes) <span class="text-danger">*</span></label>
                                        <select class="form-select selector-mes" name="meses[]" required>
                                            <option value="">Seleccione mes...</option>
                                        </select>
                                    </div>
                                    <div id="add_mes_btn" class="mt-2 d-none">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarMes()">
                                            <i class="fas fa-plus me-1"></i> Agregar otro mes
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Notas y Capture -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Notas / Concepto Adicional</label>
                                    <textarea class="form-control" name="concepto" rows="2"
                                        placeholder="Ej: Si paga por un tercero, indique a quién corresponde el pago."></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Comprobante / Capture <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="capture_pago" accept="image/*" required>
                                    <div class="important-note mt-1">Solo se aceptan imágenes (JPG, PNG).</div>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-report text-white btn-lg" id="btnEnviar">
                                    <i class="fas fa-paper-plane me-2"></i> ENVIAR REPORTE DE PAGO
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-light text-center py-3 border-0" style="border-radius: 0 0 18px 18px;">
                        <p class="mb-0 text-muted small">&copy; <?php echo date('Y'); ?> Wireless Supply, C.A. Todos los derechos reservados.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // ================================================================
        //  GLOBALS
        // ================================================================

        let planPrecioBase = 0; // USD
        const todosLosBancos = <?php echo json_encode($bancosArr); ?>;
        const mesesNombres = <?php echo json_encode($meses_nombres); ?>;

        const inputBs  = document.getElementById('input_monto_bs');
        const inputUsd = document.getElementById('input_monto_usd_vis');
        const hiddenUsd = document.getElementById('monto_usd_hidden');

        function formatBs(val) { return val.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}); }
        function formatUsd(val) { return val.toFixed(2); }

        // Actualizar el valor oculto cuando se edite el monto USD manualmente
        inputUsd.addEventListener('input', function() {
            const usd = parseFloat(this.value) || 0;
            hiddenUsd.value = formatUsd(usd);
        });

        // ================================================================
        //  3. PANEL DEL PLAN
        // ================================================================
        function actualizarPanelPlan(precioUsd, nombrePlan) {
            if (precioUsd > 0) {
                const panel = document.getElementById('plan_info_panel');
                panel.style.display = 'block';
                if (nombrePlan) document.getElementById('plan_nombre_display').textContent = nombrePlan;
                document.getElementById('plan_precio_usd_display').textContent = '$' + formatUsd(precioUsd);
            }
        }

        // ================================================================
        //  4. BÚSQUEDA POR CÉDULA / AUTOCOMPLETE
        // ================================================================
        const searchInput = document.getElementById('cedula');
        const resultsContainer = document.getElementById('contrato_search_results');
        const hiddenIdInput = document.getElementById('id_contrato_asociado');
        const nombreInput = document.getElementById('nombre');
        const telefonoInput = document.getElementById('telefono');
        let searchTimer;

        searchInput?.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length < 3) { resultsContainer.classList.add('d-none'); resultsContainer.innerHTML = ''; return; }

            searchTimer = setTimeout(() => {
                fetch(`paginas/principal/buscar_contratos.php?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(data => {
                        resultsContainer.innerHTML = '';
                        if (data.length > 0) {
                            resultsContainer.classList.remove('d-none');
                            data.forEach(c => {
                                const item = document.createElement('a');
                                item.className = 'list-group-item list-group-item-action search-item py-2';
                                const planLabel = c.nombre_plan
                                    ? `<span class="badge bg-success-subtle text-success border border-success-subtle ms-1">$${parseFloat(c.monto_plan||0).toFixed(2)} / ${c.nombre_plan}</span>`
                                    : '';
                                item.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-primary">${c.nombre_completo}</div>
                                            <small class="text-muted">C.I: ${c.cedula} | Contrato #${c.id} ${planLabel}</small>
                                        </div>
                                    </div>`;
                                item.onclick = (e) => {
                                    e.preventDefault();
                                    searchInput.value = c.cedula;
                                    nombreInput.value  = c.nombre_completo;
                                    telefonoInput.value = c.telefono || '';
                                    hiddenIdInput.value = c.id;

                                    // Cargar plan
                                    planPrecioBase = c.monto_plan ? parseFloat(c.monto_plan) : 0;
                                    actualizarPanelPlan(planPrecioBase, c.nombre_plan || 'Sin plan registrado');

                                    // Auto-rellenar monto en USD con el precio del plan
                                    if (planPrecioBase > 0) {
                                        inputUsd.value = formatUsd(planPrecioBase);
                                        hiddenUsd.value = formatUsd(planPrecioBase);
                                    }

                                    recalcularMontoPorMeses();
                                    resultsContainer.classList.add('d-none');
                                    resultsContainer.innerHTML = '';
                                };
                                resultsContainer.appendChild(item);
                            });
                        } else {
                            resultsContainer.classList.add('d-none');
                        }
                    })
                    .catch(err => console.error('Error en búsqueda:', err));
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.classList.add('d-none');
            }
        });

        // ================================================================
        //  5. MESES
        // ================================================================
        const checkVariosMeses = document.getElementById('paga_varios_meses');
        const btnAddMes = document.getElementById('add_mes_btn');
        const containerMeses = document.getElementById('container_meses');
        const inputFecha = document.querySelector('[name="fecha_pago"]');

        checkVariosMeses.addEventListener('change', function() {
            if (this.checked) { btnAddMes.classList.remove('d-none'); }
            else {
                btnAddMes.classList.add('d-none');
                const selects = containerMeses.querySelectorAll('.selector-mes');
                for (let i = 1; i < selects.length; i++) selects[i].parentElement.remove();
            }
        });

        function generarOpcionesMeses(fechaStr) {
            const fecha = new Date(fechaStr + 'T00:00:00');
            const options = [];
            for (let i = -6; i <= 6; i++) {
                const d = new Date(fecha.getFullYear(), fecha.getMonth() + i, 1);
                options.push(mesesNombres[d.getMonth()] + ' ' + d.getFullYear());
            }
            return options;
        }

        function actualizarTodosLosSelects() {
            const fechaVal = inputFecha.value;
            if (!fechaVal) return;
            const opciones = generarOpcionesMeses(fechaVal);
            const fechaObj = new Date(fechaVal + 'T00:00:00');
            const mesActualLabel = mesesNombres[fechaObj.getMonth()] + ' ' + fechaObj.getFullYear();

            document.querySelectorAll('.selector-mes').forEach(select => {
                const selectedVal = select.value;
                select.innerHTML = '<option value="">Seleccione mes...</option>';
                opciones.forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt; el.textContent = opt;
                    if (opt === selectedVal || (!selectedVal && opt === mesActualLabel)) el.selected = true;
                    select.appendChild(el);
                });
            });
        }

        inputFecha.addEventListener('change', actualizarTodosLosSelects);
        actualizarTodosLosSelects();

        function recalcularMontoPorMeses() {
            if (planPrecioBase <= 0) return;
            const cantMeses = containerMeses.querySelectorAll('.selector-mes').length;
            const totalUsd = planPrecioBase * cantMeses;
            inputUsd.value = formatUsd(totalUsd);
            hiddenUsd.value = formatUsd(totalUsd);
        }

        window.agregarMes = function() {
            const selects = containerMeses.querySelectorAll('.selector-mes');
            if (selects.length >= 3) { alert('Máximo 3 meses por reporte.'); return; }
            const div = document.createElement('div');
            div.className = 'mt-2 d-flex align-items-center month-row';
            div.innerHTML = `
                <select class="form-select selector-mes me-2" name="meses[]" required>
                    <option value="">Seleccione mes...</option>
                </select>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerMes(this)">
                    <i class="fas fa-times"></i>
                </button>`;
            containerMeses.appendChild(div);
            actualizarTodosLosSelects();
            verificarLimiteMeses();
            recalcularMontoPorMeses();
        };

        window.removerMes = function(btn) {
            btn.parentElement.remove();
            verificarLimiteMeses();
            recalcularMontoPorMeses();
        };

        function verificarLimiteMeses() {
            const n = containerMeses.querySelectorAll('.selector-mes').length;
            if (n >= 3) btnAddMes.classList.add('d-none');
            else if (checkVariosMeses.checked) btnAddMes.classList.remove('d-none');
        }

        // ================================================================
        //  6. FILTRO DE BANCOS POR MÉTODO DE PAGO
        // ================================================================
        const metodoPago = document.getElementById('metodo_pago');
        const inputBancoSelects = document.getElementById('id_banco_destino');

        metodoPago.addEventListener('change', function() {
            const selectedMetodo = this.value;
            inputBancoSelects.innerHTML = '<option value="">Seleccione el banco receptor...</option>';
            if (selectedMetodo) {
                const filtrados = todosLosBancos.filter(b => (b.metodos_pago || []).includes(selectedMetodo));
                if (filtrados.length > 0) {
                    filtrados.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id_banco; opt.textContent = b.nombre_banco;
                        inputBancoSelects.appendChild(opt);
                    });
                } else {
                    // Mostrar todos si no hay filtro configurado
                    todosLosBancos.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id_banco; opt.textContent = b.nombre_banco;
                        inputBancoSelects.appendChild(opt);
                    });
                }
            }
        });

        // ================================================================
        //  7. INPUT VALIDATIONS
        // ================================================================
        nombreInput?.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-zñÑáéíóúÁÉÍÓÚ\s]/g, '');
        });
        telefonoInput?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\-\s]/g, '');
        });
        document.getElementById('referencia')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // ================================================================
        //  8. FORM SUBMIT GUARD
        // ================================================================
        document.getElementById('formReportePago').addEventListener('submit', function(e) {
            const usd = parseFloat(hiddenUsd.value) || 0;
            if (usd <= 0) {
                e.preventDefault();
                alert('Por favor ingrese el monto del pago.');
                inputBs.focus();
                return;
            }
            // Make sure hidden USD is set
            hiddenUsd.value = usd.toFixed(2);
        });
    </script>
</body>
</html>