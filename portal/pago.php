<?php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

require '../paginas/conexion.php';

$id_contrato = isset($_GET['id_contrato']) ? intval($_GET['id_contrato']) : 0;
$cedula = $_SESSION['cliente_cedula'];

if ($id_contrato <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Obtener detalles del contrato (Optimizado con JOIN)
$sql = "SELECT c.*, p.nombre_plan,
               SUM(CASE WHEN cxc.estado IN ('PENDIENTE', 'VENCIDO') THEN cxc.monto_total ELSE 0 END) as deuda_mensualidades
        FROM contratos c
        LEFT JOIN planes p ON c.id_plan = p.id_plan
        LEFT JOIN cuentas_por_cobrar cxc ON cxc.id_contrato = c.id
        WHERE c.id = ? AND c.cedula = ? AND c.estado != 'ELIMINADO'
        GROUP BY c.id, c.cedula, c.direccion, c.estado, c.monto_plan, p.nombre_plan";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id_contrato, $cedula);
$stmt->execute();
$res = $stmt->get_result();
$contrato = $res->fetch_assoc();

if (!$contrato) {
    header('Location: dashboard.php');
    exit;
}

$deuda = floatval($contrato['deuda_mensualidades'] ?? 0);
$monto_plan = floatval($contrato['monto_plan']);

// Tasa BCV (con cache de 1 hora)
$tasa_bcv = 1;
$cache_file = 'tasa_cache.json';
$cache_time = 3600; // 1 hora

if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
    $data_cache = json_decode(file_get_contents($cache_file), true);
    $tasa_bcv = $data_cache['tasa'] ?? 1;
} else {
    $url_bcv = "https://ve.dolarapi.com/v1/dolares/oficial";
    $ch = curl_init($url_bcv);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $resp_bcv = curl_exec($ch);
    if (!curl_errno($ch)) {
        $data_bcv = json_decode($resp_bcv, true);
        if (isset($data_bcv['promedio'])) {
            $tasa_bcv = floatval($data_bcv['promedio']);
            @file_put_contents($cache_file, json_encode(['tasa' => $tasa_bcv, 'fecha' => date('Y-m-d H:i:s')]));
        }
    }
    curl_close($ch);
}

// Bancos
$json_bancos = @file_get_contents('../paginas/principal/bancos.json');
$bancosArr = json_decode($json_bancos, true) ?: [];
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realizar Pago - Wireless Supply</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <header class="glass-header py-3">
        <div class="container d-flex align-items-center">
            <a href="dashboard.php" class="text-decoration-none me-3">
                <i class="fas fa-chevron-left fa-lg"></i>
            </a>
            <h5 class="mb-0 fw-bold" id="wizard-title">Realizar Pago</h5>
        </div>
    </header>

    <div class="container py-4" style="margin-bottom: 120px;">
        <form id="paymentForm" action="procesar_pago_cliente.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_contrato" value="<?php echo $id_contrato; ?>">
            <input type="hidden" name="tasa_dolar" value="<?php echo $tasa_bcv; ?>">
            <input type="hidden" name="monto_usd" id="input_monto_usd" value="">
            <input type="hidden" name="metodo_pago" id="input_metodo" value="">
            <input type="hidden" name="id_banco_destino" id="input_banco" value="">

            <!-- PASO 1: Selección de Monto -->
            <div class="wizard-step active" id="step-1">
                <h3 class="mb-4">¿Cuánto quieres pagar hoy?</h3>
                <div class="selection-card" onclick="selectAmount(<?php echo $deuda; ?>, 0, this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div>
                            <span class="fw-bold d-block">Deuda Actual</span>
                            <small class="text-muted">Paga el saldo pendiente</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold fs-5 d-block">$<?php echo number_format($deuda, 2); ?></span>
                        <small class="text-ves">Bs <?php echo number_format($deuda * $tasa_bcv, 2, ',', '.'); ?></small>
                    </div>
                </div>

                <div class="selection-card" onclick="selectAmount(<?php echo $deuda + $monto_plan; ?>, 1, this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon"><i class="fas fa-plus-circle"></i></div>
                        <div>
                            <span class="fw-bold d-block">Deuda + 1 Mes</span>
                            <small class="text-muted">Mantente al día por adelantado</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold fs-5 d-block">$<?php echo number_format($deuda + $monto_plan, 2); ?></span>
                        <small class="text-ves">Bs <?php echo number_format(($deuda + $monto_plan) * $tasa_bcv, 2, ',', '.'); ?></small>
                    </div>
                </div>

                <div class="selection-card" onclick="selectAmount(<?php echo $deuda + ($monto_plan * 3); ?>, 3, this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon"><i class="fas fa-gem"></i></div>
                        <div>
                            <span class="fw-bold d-block">Plan Trimestral</span>
                            <small class="text-muted">Paga 3 meses y olvídate</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold fs-5 d-block">$<?php echo number_format($deuda + ($monto_plan * 3), 2); ?></span>
                        <small class="text-ves">Bs <?php echo number_format(($deuda + ($monto_plan * 3)) * $tasa_bcv, 2, ',', '.'); ?></small>
                    </div>
                </div>
                
                <input type="hidden" name="meses_adelanto" id="input_meses" value="0">
            </div>

            <!-- PASO 2: Selección de Método -->
            <div class="wizard-step" id="step-2">
                <h3 class="mb-4">¿Cómo vas a pagar?</h3>
                <div class="selection-card" onclick="selectMethod('Pago Móvil', this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-mobile-alt"></i></div>
                        <div>
                            <span class="fw-bold d-block">Pago Móvil</span>
                            <span class="badge bg-warning text-dark" style="font-size: 0.6rem;">SE ACREDITA MÁS RÁPIDO</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>

                <div class="selection-card" onclick="selectMethod('Transferencia', this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i class="fas fa-university"></i></div>
                        <div>
                            <span class="fw-bold d-block">Transferencia Bancaria</span>
                            <small class="text-muted">Desde cualquier banco</small>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>

                <div class="selection-card" onclick="selectMethod('Zelle', this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;"><i class="fas fa-bolt"></i></div>
                        <div>
                            <span class="fw-bold d-block">Zelle</span>
                            <small class="text-muted">Pagos en USD</small>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </div>

            <!-- PASO 3: Selección de Banco -->
            <div class="wizard-step" id="step-3">
                <h3 class="mb-4">Selecciona el banco destino</h3>
                <div id="bancos-list">
                    <!-- Se llena con JS -->
                </div>
            </div>

            <!-- PASO 4: Datos del Pago -->
            <div class="wizard-step" id="step-4">
                <div class="text-center mb-4">
                    <h3 class="mb-2">Información de tu pago</h3>
                    <p class="text-muted">Realiza la operación con estos datos:</p>
                </div>

                <div class="glass-panel p-4 mb-4">
                    <div class="bank-logo-placeholder" id="final-bank-logo">
                        <i class="fas fa-university text-primary fa-2x"></i>
                    </div>
                    <h5 class="text-center fw-bold mb-4" id="final-bank-name">Nombre del Banco</h5>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <small class="text-muted d-block">Monto a pagar</small>
                            <span class="fw-bold fs-5" id="final-amount-text">Bs 0,00</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-glass copy-btn" onclick="copyText('final-amount-raw')"><i class="far fa-copy me-1"></i> Copiar</button>
                        <span id="final-amount-raw" class="d-none"></span>
                    </div>

                    <div id="bank-details-fields">
                        <!-- Se llena con JS -->
                    </div>
                </div>

                <button type="button" class="btn btn-premium w-100 py-3 mb-3" onclick="nextStep(5)">
                    YA PAGUÉ, INGRESAR REFERENCIA <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>

            <!-- PASO 5: Reporte Final -->
            <div class="wizard-step" id="step-5">
                <h3 class="mb-4">Reportar Pago</h3>
                
                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">FECHA DE OPERACIÓN</label>
                    <input type="date" name="fecha_pago" class="form-control glass-input" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">NÚMERO DE REFERENCIA</label>
                    <input type="text" name="referencia" class="form-control glass-input" placeholder="Ingresa los últimos 6 dígitos o el número completo" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">COMPROBANTE DE PAGO (CAPTURE)</label>
                    <div class="upload-area glass-panel p-4 text-center" onclick="document.getElementById('capture_input').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                        <p class="mb-0 small">Presiona para subir la imagen</p>
                        <input type="file" id="capture_input" name="capture_pago" class="d-none" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <div id="image-preview" class="mt-3 d-none text-center">
                        <img src="" class="img-fluid rounded shadow-sm" style="max-height: 200px;">
                    </div>
                </div>

                <button type="submit" class="btn btn-premium w-100 py-3">
                    FINALIZAR REPORTE <i class="fas fa-check-circle ms-2"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Barra de Resumen Inferior -->
    <div class="bottom-bar">
        <div class="container d-flex justify-content-center align-items-center text-center">
            <div>
                <small class="text-muted d-block" style="letter-spacing: 0.5px; font-weight: 600;">TOTAL A PAGAR</small>
                <span class="fw-bold fs-4 text-gradient" id="summary-usd">$0.00</span>
                <span class="text-muted small ms-2 d-block d-sm-inline" id="summary-bs">Bs 0,00</span>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 5;
        const todosLosBancos = <?php echo json_encode($bancosArr); ?>;
        const tasaBcv = <?php echo $tasa_bcv; ?>;
        
        let selectedAmountUsd = 0;
        let selectedMethod = '';
        let selectedBankId = null;

        function updateSummary() {
            document.getElementById('summary-usd').textContent = '$' + selectedAmountUsd.toFixed(2);
            document.getElementById('summary-bs').textContent = 'Bs ' + (selectedAmountUsd * tasaBcv).toLocaleString('es-VE', {minimumFractionDigits: 2});
            
            // Step titles
            const titles = ["", "Realizar Pago", "Método de Pago", "Banco Destino", "Datos del Pago", "Reportar Pago"];
            document.getElementById('wizard-title').textContent = titles[currentStep];

            // Show/Hide bottom bar button logic
            const btn = document.getElementById('btn-next-global');
            if (btn) {
                if (currentStep === 1) {
                    btn.disabled = selectedAmountUsd === 0;
                } else if (currentStep === 2) {
                    btn.disabled = selectedMethod === '';
                } else if (currentStep === 3) {
                    btn.disabled = selectedBankId === null;
                } else {
                    btn.style.display = 'none'; // Step 4 and 5 handle their own buttons
                }
                
                if (currentStep < 4) {
                    btn.style.display = 'block';
                }
            }
        }

        function nextStep(step) {
            document.querySelector('.wizard-step.active').classList.remove('active');
            currentStep = step;
            document.getElementById('step-' + step).classList.add('active');
            window.scrollTo(0, 0);
            updateSummary();
        }

        function handleNextMain() {
            if (currentStep === 3) {
                prepareStep4();
            }
            nextStep(currentStep + 1);
        }

        function selectAmount(usd, meses, el) {
            selectedAmountUsd = usd;
            document.getElementById('input_monto_usd').value = usd;
            document.getElementById('input_meses').value = meses;
            
            document.querySelectorAll('#step-1 .selection-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateSummary();
            
            // Auto-avanzar al Paso 2
            setTimeout(() => {
                nextStep(2);
            }, 250);
        }

        function selectMethod(method, el) {
            selectedMethod = method;
            document.getElementById('input_metodo').value = method;
            
            document.querySelectorAll('#step-2 .selection-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            
            // Cargar bancos
            const list = document.getElementById('bancos-list');
            list.innerHTML = '';
            const filtrados = todosLosBancos.filter(b => (b.metodos_pago || []).includes(method) && b.activo !== false);
            
            filtrados.forEach(b => {
                const div = document.createElement('div');
                div.className = 'selection-card';
                div.onclick = () => selectBank(b, div);
                div.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="selection-icon"><i class="fas fa-university"></i></div>
                        <div>
                            <span class="fw-bold d-block">${b.nombre_banco}</span>
                            <small class="text-muted">${method}</small>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                `;
                list.appendChild(div);
            });
            
            updateSummary();
            
            // Auto-avanzar al Paso 3
            setTimeout(() => {
                nextStep(3);
            }, 250);
        }

        function selectBank(bank, el) {
            selectedBankId = bank.id_banco;
            document.getElementById('input_banco').value = bank.id_banco;
            document.querySelectorAll('#step-3 .selection-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateSummary();
            
            // Auto-avanzar al Paso 4 preparando primero la información final
            setTimeout(() => {
                prepareStep4();
                nextStep(4);
            }, 250);
        }

        function prepareStep4() {
            const bank = todosLosBancos.find(b => b.id_banco == selectedBankId);
            document.getElementById('final-bank-name').textContent = bank.nombre_banco;
            
            const montoBs = selectedAmountUsd * tasaBcv;
            const isUsd = (selectedMethod === 'Zelle' || selectedMethod === 'Divisas');
            const montoText = isUsd ? '$' + selectedAmountUsd.toFixed(2) : 'Bs ' + montoBs.toLocaleString('es-VE', {minimumFractionDigits: 2});
            const montoRaw = isUsd ? selectedAmountUsd.toFixed(2) : montoBs.toFixed(2);
            
            document.getElementById('final-amount-text').textContent = montoText;
            document.getElementById('final-amount-raw').textContent = montoRaw;

            const detailsDiv = document.getElementById('bank-details-fields');
            detailsDiv.innerHTML = '';

            const fields = [];
            if (selectedMethod === 'Pago Móvil') {
                fields.push({ label: 'Teléfono', value: bank.numero_cuenta });
                fields.push({ label: 'Cédula/RIF', value: bank.cedula_propietario });
            } else if (selectedMethod === 'Transferencia') {
                fields.push({ label: 'N° de Cuenta', value: bank.numero_cuenta });
                fields.push({ label: 'Titular', value: bank.nombre_propietario });
                fields.push({ label: 'RIF', value: bank.cedula_propietario });
            } else if (selectedMethod === 'Zelle') {
                fields.push({ label: 'Correo', value: bank.numero_cuenta });
                fields.push({ label: 'Titular', value: bank.nombre_propietario });
            } else {
                fields.push({ label: 'Referencia', value: bank.numero_cuenta });
            }

            fields.forEach((f, index) => {
                const id = 'field-' + index;
                const html = `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <small class="text-muted d-block">${f.label}</small>
                            <span class="fw-bold" id="${id}">${f.value}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-glass copy-btn" onclick="copyText('${id}', event)"><i class="far fa-copy"></i></button>
                    </div>
                `;
                detailsDiv.innerHTML += html;
            });
        }

        function copyText(id, event) {
            const text = document.getElementById(id).textContent;
            navigator.clipboard.writeText(text);
            const btn = event.currentTarget;
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i>';
            btn.classList.add('text-success');
            setTimeout(() => {
                btn.innerHTML = original;
                btn.classList.remove('text-success');
            }, 2000);
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('image-preview');
                    preview.classList.remove('d-none');
                    preview.querySelector('img').src = e.target.result;
                    
                    // Optimización: Redimensionar imagen para ahorrar ancho de banda
                    resizeImage(file, 1024, 1024).then(resizedBlob => {
                        const resizedFile = new File([resizedBlob], file.name, { type: 'image/jpeg' });
                        
                        // Reemplazar el archivo en el input usando DataTransfer
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(resizedFile);
                        input.files = dataTransfer.files;
                        console.log("Imagen redimensionada con éxito: ", Math.round(resizedBlob.size / 1024), "KB");
                    });
                }
                reader.readAsDataURL(file);
            }
        }

        async function resizeImage(file, maxWidth, maxHeight) {
            return new Promise((resolve) => {
                const img = new Image();
                img.src = URL.createObjectURL(file);
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > maxWidth) {
                            height *= maxWidth / width;
                            width = maxWidth;
                        }
                    } else {
                        if (height > maxHeight) {
                            width *= maxHeight / height;
                            height = maxHeight;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob((blob) => {
                        resolve(blob);
                    }, 'image/jpeg', 0.7); // 0.7 calidad para buen balance peso/calidad
                };
            });
        }

        // Initialize theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Initial summary
        updateSummary();
    </script>
</body>
</html>
