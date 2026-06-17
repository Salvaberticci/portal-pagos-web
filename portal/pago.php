<?php
require_once 'security_helper.php';
enforce_https();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

$pago_err = $_SESSION['pago_err'] ?? '';
unset($_SESSION['pago_err']);

$wisp_service_id = isset($_GET['id_contrato']) ? $_GET['id_contrato'] : '';
$cedula = $_SESSION['cliente_cedula'];

if (empty($wisp_service_id)) {
    header('Location: dashboard.php');
    exit;
}

// Conectar a WispHub
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$profileRes = $wispClient->getServiceProfile($wisp_service_id);
if ($profileRes['status'] !== 200 || empty($profileRes['data'])) {
    header('Location: dashboard.php');
    exit;
}
$c_perfil = $profileRes['data'];

$invoices = $wispClient->getPendingInvoices($wisp_service_id);
$deuda = 0;
foreach ($invoices as $inv) {
    $deuda += floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
}

$monto_plan = floatval($c_perfil['plan_internet_precio'] ?? 0);
if ($monto_plan <= 0 && count($invoices) > 0) {
    $monto_plan = floatval($invoices[0]['monto'] ?? 0);
}
if ($monto_plan <= 0) $monto_plan = 15.0; // Default fallback

// Para los inputs
$id_contrato = $wisp_service_id;

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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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

if ($cedula === TEST_USER_CEDULA) {
    $deuda = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
    $monto_plan = 1.00 / ($tasa_bcv > 0 ? $tasa_bcv : 1);
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="id_contrato" value="<?php echo $id_contrato; ?>">
            <input type="hidden" name="tasa_dolar" value="<?php echo $tasa_bcv; ?>">
            <input type="hidden" name="monto_usd" id="input_monto_usd" value="">
            <input type="hidden" name="metodo_pago" id="input_metodo" value="">
            <input type="hidden" name="id_banco_destino" id="input_banco" value="">

            <!-- PASO 1: Selección de Método -->
            <div class="wizard-step active" id="step-1">
                <?php if (!empty($pago_err)): ?>
                    <div class="alert alert-danger glass-panel mb-4 text-center border-0 shadow-sm" style="background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444 !important; border-radius: 12px; font-size: 0.95rem;">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                        <?php echo $pago_err; ?>
                    </div>
                <?php endif; ?>

                <h3 class="mb-4">¿Cómo vas a pagar?</h3>
                <div class="selection-card animate-fade" onclick="selectMethod('Pago Móvil', this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-mobile-alt"></i></div>
                        <div>
                            <span class="fw-bold d-block text-white">Pago Móvil</span>
                            <span class="badge bg-warning text-dark" style="font-size: 0.6rem;">SE ACREDITA AL INSTANTE</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>

                <div class="selection-card animate-fade" onclick="selectMethod('Transferencia', this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i class="fas fa-university"></i></div>
                        <div>
                            <span class="fw-bold d-block text-white">Transferencia Bancaria</span>
                            <small class="text-light opacity-75">Desde cualquier banco</small>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>

                <div class="selection-card animate-fade" onclick="selectMethod('Zelle', this)">
                    <div class="d-flex align-items-center">
                        <div class="selection-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;"><i class="fas fa-bolt"></i></div>
                        <div>
                            <span class="fw-bold d-block text-white">Zelle</span>
                            <small class="text-light opacity-75">Pagos en USD</small>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </div>

            <!-- PASO 2: Selección de Banco -->
            <div class="wizard-step" id="step-2">
                <h3 class="mb-4">Selecciona el banco destino</h3>
                <div id="bancos-list">
                    <!-- Se llena con JS -->
                </div>
            </div>

            <!-- PASO 3: Datos de la Operación y Reporte -->
            <div class="wizard-step" id="step-3">
                <div class="glass-panel p-4 mb-4">
                    <h5 class="fw-bold mb-3 text-info text-center" id="final-bank-name">Datos para Transferir</h5>
                    <div id="bank-details-fields" class="mb-2">
                        <!-- Se llena con JS -->
                    </div>
                </div>

                <h4 class="mb-3">Ingresa los datos de tu pago</h4>

                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">FECHA DE OPERACIÓN</label>
                    <input type="date" name="fecha_pago" id="field_fecha_pago" class="form-control glass-input" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-white small fw-bold">NÚMERO DE REFERENCIA</label>
                    <input type="text" name="referencia" id="field_referencia" class="form-control glass-input" placeholder="Ingresa la referencia completa o los últimos 6-8 dígitos" required>
                </div>

                <!-- Campo de Monto (Solo para métodos manuales como Zelle) -->
                <div class="mb-3" id="monto_manual_wrapper" style="display: none;">
                    <label class="form-label text-white small fw-bold">MONTO PAGADO ($ USD)</label>
                    <input type="number" step="0.01" name="monto_manual" id="field_monto_manual" class="form-control glass-input" placeholder="Ej: 15.00">
                </div>

                <div class="mb-4">
                    <label class="form-label text-white small fw-bold">COMPROBANTE (CAPTURE - OPCIONAL)</label>
                    <div class="upload-area glass-panel p-3 text-center" onclick="document.getElementById('capture_input').click()">
                        <i class="fas fa-cloud-upload-alt fa-lg text-primary mb-1"></i>
                        <p class="mb-0 small text-light">Presiona para subir la imagen del comprobante</p>
                        <input type="file" id="capture_input" name="capture_pago" class="d-none" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <div id="image-preview" class="mt-2 d-none text-center">
                        <img src="" class="img-fluid rounded shadow-sm" style="max-height: 120px;">
                    </div>
                </div>

                <button type="button" class="btn btn-premium w-100 py-3" id="btn-verificar-pago" onclick="verificarPagoAJAX()">
                    VERIFICAR PAGO <i class="fas fa-search ms-2"></i>
                </button>
            </div>

            <!-- PASO 4: Confirmación y Resultados -->
            <div class="wizard-step" id="step-4">
                <h3 class="mb-3 text-center">Verificación de Pago</h3>
                
                <div class="glass-panel p-4 mb-4 text-center">
                    <div id="verification-status-icon" class="mb-3">
                        <!-- Icono animado de carga o éxito -->
                    </div>
                    <h4 class="fw-bold text-white mb-2" id="verification-title">Buscando pago...</h4>
                    <p class="text-light opacity-75" id="verification-details">Estamos comprobando tu referencia directamente con los movimientos recientes del banco.</p>

                    <div id="payment-details-box" style="display: none;" class="text-start mt-4 border-top border-secondary pt-3">
                        <!-- Se llena dinámicamente -->
                    </div>
                </div>

                <button type="submit" class="btn btn-premium w-100 py-3" id="btn-confirmar-pago" style="display: none;">
                    APLICAR PAGO Y ACTIVAR <i class="fas fa-check-circle ms-2"></i>
                </button>
                
                <button type="button" class="btn btn-secondary w-100 py-2 mt-2" onclick="nextStep(3)">
                    <i class="fas fa-arrow-left me-2"></i> Corregir datos
                </button>
            </div>
        </form>
    </div>

    <!-- Barra de Resumen Inferior -->
    <div class="bottom-bar" style="display: none;">
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
        const totalSteps = 4;
        const todosLosBancos = <?php echo json_encode($bancosArr); ?>;
        const tasaBcv = <?php echo $tasa_bcv; ?>;
        
        let selectedMethod = '';
        let selectedBankId = null;

        function updateSummary() {
            // Títulos de pasos
            const titles = ["", "Método de Pago", "Banco Destino", "Ingresar Pago", "Verificación de Pago"];
            document.getElementById('wizard-title').textContent = titles[currentStep];
        }

        function nextStep(step) {
            document.querySelector('.wizard-step.active').classList.remove('active');
            currentStep = step;
            document.getElementById('step-' + step).classList.add('active');
            window.scrollTo(0, 0);
            updateSummary();
        }

        function selectMethod(method, el) {
            selectedMethod = method;
            document.getElementById('input_metodo').value = method;
            
            document.querySelectorAll('#step-1 .selection-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            
            // Mostrar input de monto manual solo si es Zelle
            const montoManual = document.getElementById('monto_manual_wrapper');
            const fieldMonto = document.getElementById('field_monto_manual');
            if (method === 'Zelle') {
                montoManual.style.display = 'block';
                fieldMonto.setAttribute('required', 'required');
            } else {
                montoManual.style.display = 'none';
                fieldMonto.removeAttribute('required');
            }

            // Cargar bancos
            const list = document.getElementById('bancos-list');
            list.innerHTML = '';
            const filtrados = todosLosBancos.filter(b => (b.metodos_pago || []).includes(method) && b.activo !== false);
            
            filtrados.forEach(b => {
                const div = document.createElement('div');
                div.className = 'selection-card animate-fade';
                div.onclick = () => selectBank(b, div);
                div.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="selection-icon"><i class="fas fa-university"></i></div>
                        <div>
                            <span class="fw-bold d-block text-white">${b.nombre_banco}</span>
                            <small class="text-light opacity-75">${method}</small>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                `;
                list.appendChild(div);
            });
            
            updateSummary();
            
            setTimeout(() => {
                nextStep(2);
            }, 200);
        }

        function selectBank(bank, el) {
            selectedBankId = bank.id_banco;
            document.getElementById('input_banco').value = bank.id_banco;
            document.querySelectorAll('#step-2 .selection-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateSummary();
            
            setTimeout(() => {
                prepareStep3();
                nextStep(3);
            }, 200);
        }

        function prepareStep3() {
            const bank = todosLosBancos.find(b => b.id_banco == selectedBankId);
            document.getElementById('final-bank-name').textContent = bank.nombre_banco;

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
            }

            fields.forEach((f, index) => {
                const id = 'field-' + index;
                const html = `
                    <div class="d-flex justify-content-between align-items-center mb-2 small border-bottom border-secondary pb-1">
                        <div>
                            <small class="text-light opacity-75 d-block">${f.label}</small>
                            <span class="fw-bold text-white" id="${id}">${f.value}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-glass copy-btn py-0 px-2" onclick="copyText('${id}', event)"><i class="far fa-copy"></i></button>
                    </div>
                `;
                detailsDiv.innerHTML += html;
            });
        }

        function verificarPagoAJAX() {
            const ref = document.getElementById('field_referencia').value.trim();
            const fecha = document.getElementById('field_fecha_pago').value;
            const montoManual = document.getElementById('field_monto_manual').value;

            if (!ref || !fecha) {
                alert('Por favor, ingresa la fecha y el número de referencia.');
                return;
            }

            // Cambiar a paso 4 para mostrar cargando
            nextStep(4);

            const statusIcon = document.getElementById('verification-status-icon');
            const title = document.getElementById('verification-title');
            const details = document.getElementById('verification-details');
            const detailsBox = document.getElementById('payment-details-box');
            const btnConfirmar = document.getElementById('btn-confirmar-pago');

            statusIcon.innerHTML = '<div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>';
            title.textContent = 'Buscando pago...';
            details.textContent = 'Comprobando referencia con los movimientos del banco en tiempo real.';
            detailsBox.style.display = 'none';
            btnConfirmar.style.display = 'none';

            // Zelle / Manual
            if (selectedMethod === 'Zelle') {
                if (!montoManual || parseFloat(montoManual) <= 0) {
                    alert('Debes ingresar un monto válido para reportar Zelle.');
                    nextStep(3);
                    return;
                }
                
                // Configurar montos para envío manual
                document.getElementById('input_monto_usd').value = parseFloat(montoManual).toFixed(2);
                
                statusIcon.innerHTML = '<i class="fas fa-info-circle text-info fa-3x animate-fade"></i>';
                title.textContent = 'Verificación Manual';
                details.textContent = 'Los pagos de Zelle requieren validación administrativa. Tu reporte será procesado a la brevedad.';
                
                detailsBox.style.display = 'block';
                detailsBox.innerHTML = `
                    <div class="alert alert-info py-2" style="background: rgba(14, 165, 233, 0.1);">
                        <strong>Monto reportado:</strong> $${parseFloat(montoManual).toFixed(2)} USD<br>
                        <strong>Referencia:</strong> ${ref}<br>
                        <strong>Fecha:</strong> ${fecha}
                    </div>
                `;
                btnConfirmar.style.display = 'block';
                btnConfirmar.textContent = 'ENVIAR REPORTE';
                return;
            }

            // BDV API
            const csrf = document.querySelector('input[name="csrf_token"]').value;
            const url = `api_verificar_pago.php?id_banco=${selectedBankId}&referencia=${encodeURIComponent(ref)}&fecha_pago=${fecha}&metodo_pago=${encodeURIComponent(selectedMethod)}&id_contrato=${encodeURIComponent(document.querySelector('input[name="id_contrato"]').value)}&csrf_token=${csrf}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'verified') {
                        statusIcon.innerHTML = '<i class="fas fa-check-circle text-success fa-3x animate-fade"></i>';
                        title.textContent = '¡Pago Verificado!';
                        details.textContent = 'Hemos validado tu transacción de forma exitosa.';
                        
                        // Guardar el monto USD conciliado
                        document.getElementById('input_monto_usd').value = data.monto_usd;

                        detailsBox.style.display = 'block';
                        detailsBox.innerHTML = `
                            <div class="p-3 rounded mb-3" style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981;">
                                <div class="mb-2"><strong>Monto encontrado:</strong> Bs ${data.monto_bs.toLocaleString('es-VE', {minimumFractionDigits: 2})}</div>
                                <div class="mb-2"><strong>Equivalente en USD:</strong> $${data.monto_usd.toFixed(2)} USD</div>
                                <div class="mb-2"><strong>Tu deuda actual:</strong> $${data.deuda_usd.toFixed(2)} USD</div>
                                <div class="mt-2 pt-2 border-top border-secondary small text-light font-italic">${data.descripcion}</div>
                            </div>
                        `;
                        btnConfirmar.style.display = 'block';
                        btnConfirmar.textContent = 'APLICAR PAGO Y ACTIVAR';
                    } else if (data.status === 'manual') {
                        statusIcon.innerHTML = '<i class="fas fa-info-circle text-info fa-3x"></i>';
                        title.textContent = 'Verificación Manual';
                        details.textContent = data.message;
                        
                        btnConfirmar.style.display = 'block';
                        btnConfirmar.textContent = 'ENVIAR REPORTE';
                    } else {
                        statusIcon.innerHTML = '<i class="fas fa-times-circle text-danger fa-3x animate-fade"></i>';
                        title.textContent = 'Pago No Encontrado';
                        details.textContent = data.message;
                    }
                })
                .catch(err => {
                    statusIcon.innerHTML = '<i class="fas fa-exclamation-triangle text-warning fa-3x"></i>';
                    title.textContent = 'Error de Red';
                    details.textContent = 'No pudimos conectar con el servidor de validación. Por favor, reintenta.';
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

        // Loading modal on form submit
        document.getElementById('paymentForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
    </script>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:99999;justify-content:center;align-items:center;flex-direction:column;">
        <div style="width:50px;height:50px;border:4px solid rgba(255,255,255,0.2);border-top-color:#3b82f6;border-radius:50%;animation:loadingSpin 0.8s linear infinite;"></div>
        <p style="color:#fff;font-size:1.2rem;font-weight:600;margin-top:20px;">Procesando pago...</p>
        <p style="color:#94a3b8;font-size:0.9rem;margin-top:5px;">Por favor espera, esto puede tomar unos segundos.</p>
    </div>

    <style>
        @keyframes loadingSpin {
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
