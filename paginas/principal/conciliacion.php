<?php
// conciliacion.php
require_once '../conexion.php';

$path_to_root = "../../";
$page_title = "Conciliación Bancaria (Excel vs Capture)";
$breadcrumb = ["Cobranzas"];
$back_url = "../menu.php";
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';
?>

<style>
    .drop-zone {
        border: 2px dashed rgba(13, 110, 253, 0.5);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        background: rgba(255, 255, 255, 0.03);
        cursor: pointer;
        transition: all 0.3s;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 200px;
        color: var(--text-main);
    }

    .drop-zone:hover {
        background: rgba(13, 110, 253, 0.08);
        border-color: #0a58ca;
    }

    .drop-zone.active {
        background: rgba(25, 135, 84, 0.1);
        border-color: #198754;
    }

    .drop-zone h6 {
        color: var(--text-main);
    }

    #preview-image {
        max-width: 100%;
        max-height: 300px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        display: none;
        margin: 0 auto;
    }

    .result-card {
        transition: all 0.3s ease;
    }

    .step-number {
        width: 30px;
        height: 30px;
        color: white;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 10px;
        flex-shrink: 0;
    }

    /* DataTables spacing */
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

    /* Result cards dark mode */
    .result-inner-card {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
    }
    .result-inner-card .ref-value {
        color: var(--text-main);
    }
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="fw-bold text-gradient"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Conciliación:
                        Capture vs Banco</h3>
                    <p class="text-muted">Sube el Excel del Banco y el Capture del Pago Móvil para verificar si la
                        operación fue exitosa.</p>
                </div>
            </div>

            <div class="row g-4">
                <!-- Columna Izquierda: Entradas -->
                <div class="col-lg-5">
                    <!-- Paso 1: Cargar Excel -->
                    <div class="glass-panel mb-4">
                        <div class="card-header bg-transparent border-bottom border-white border-opacity-10 py-3 px-4">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center">
                                <span class="step-number bg-success">1</span> Archivo del Banco (Excel)
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div id="drop-zone-excel" class="drop-zone">
                                <i class="fa-solid fa-file-excel fa-3x text-success mb-3"></i>
                                <h6 id="excel-label">Arrastra el Excel (.xls, .xlsx)</h6>
                                <p class="text-muted small mb-0">Debe tener columna "Referencia"</p>
                                <input type="file" id="excel-input" accept=".xlsx, .xls, .csv" style="display: none;">
                            </div>
                            <div id="excel-info" class="mt-3 text-center text-success fw-bold small"
                                style="display: none;"></div>
                        </div>
                    </div>

                    <!-- Paso 2: Cargar Capture -->
                    <div class="glass-panel">
                        <div class="card-header bg-transparent border-bottom border-white border-opacity-10 py-3 px-4">
                            <h6 class="mb-0 fw-bold text-primary d-flex align-items-center">
                                <span class="step-number bg-primary">2</span> Capture de Pago (Imagen)
                            </h6>
                        </div>
                        <div class="card-body p-4">
                            <div id="drop-zone-img" class="drop-zone">
                                <i class="fa-solid fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h6 id="img-label">Sube el Capture del Pago</h6>
                                <input type="file" id="img-input" accept="image/*" style="display: none;">
                            </div>
                            <div class="mt-3 text-center">
                                <img id="preview-image" alt="Vista previa">
                            </div>

                            <!-- Búsqueda Manual -->
                            <div class="manual-search-section">
                                <h6 class="fw-bold text-muted mb-3 small text-uppercase">O ingresa la referencia
                                    manualmente:</h6>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-keyboard"></i></span>
                                    <input type="text" id="manual-ref-input" class="form-control"
                                        placeholder="Ej: 12345678">
                                    <button class="btn btn-primary" type="button"
                                        onclick="handleManualSearch()">Verificar</button>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Búsqueda Masiva Excel Secundario -->
                            <div class="manual-search-section">
                                <h6 class="fw-bold text-muted mb-3 small text-uppercase">O compara otro archivo Excel:
                                </h6>
                                <div id="drop-zone-excel-secondary" class="drop-zone"
                                    style="min-height: 120px; padding: 15px;">
                                    <i class="fa-solid fa-file-csv fa-2x text-secondary mb-2"></i>
                                    <h6 id="excel-secondary-label" class="fs-6">Sube Excel Secundario</h6>
                                    <input type="file" id="excel-secondary-input" accept=".xlsx, .xls, .csv"
                                        style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Resultados -->
                <div class="col-lg-7">
                    <div class="glass-panel h-100">
                        <div class="card-header bg-transparent border-bottom border-white border-opacity-10 py-3 px-4 fw-bold text-main">
                            <i class="fa-solid fa-magnifying-glass-chart me-2"></i>Resultados del Análisis
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center p-4">

                            <!-- Estado del Proceso -->
                            <div id="processing-status" class="text-center py-5" style="display: none;">
                                <div class="spinner-border text-primary mb-3" role="status"
                                    style="width: 3rem; height: 3rem;"></div>
                                <h5 class="fw-bold animate__animated animate__pulse animate__infinite">Analizando imagen
                                    con IA...</h5>
                                <p class="text-muted" id="ocr-status-text">Extrayendo número de operación...</p>
                                <div class="progress mt-3 mx-auto" style="width: 80%; height: 8px;">
                                    <div id="ocr-progress"
                                        class="progress-bar progress-bar-striped progress-bar-animated"
                                        style="width: 0%"></div>
                                </div>
                            </div>

                            <!-- Estado Inicial -->
                            <div id="initial-state" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-arrow-left fa-3x mb-3 opacity-25"></i>
                                <h5>Esperando archivos...</h5>
                                <p>Carga primero el Excel del banco y luego la imagen del capture.</p>
                            </div>

                            <!-- Resultado: ÉXITO -->
                            <div id="result-success" class="result-card text-center py-4" style="display: none;">
                                <div class="mb-3">
                                    <span class="fa-stack fa-4x text-success">
                                        <i class="fa-solid fa-circle fa-stack-2x"></i>
                                        <i class="fa-solid fa-check fa-stack-1x fa-inverse"></i>
                                    </span>
                                </div>
                                <h3 class="fw-bold text-success mb-2">¡PAGO ENCONTRADO!</h3>
                                <p class="lead mb-4">La referencia coincide con un registro del banco.</p>

                                <div class="card result-inner-card border-success mx-4 text-start">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 border-end border-white border-opacity-10">
                                                <h6 class="fw-bold text-muted small text-uppercase">Detectado en Capture
                                                </h6>
                                                <p class="fs-4 fw-bold ref-value mb-0" id="ref-ocr">---</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold text-success small text-uppercase"><i
                                                        class="fa-solid fa-database me-1"></i> Datos del Banco</h6>
                                                <ul class="list-unstyled mb-0 small" id="bank-details-list">
                                                    <!-- Detalles llenados por JS -->
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección de Base de Datos (Integración) -->
                                <div id="db-check-section" class="mt-4 mx-4" style="display: none;">
                                    <div class="card border-info shadow-sm">
                                        <div class="card-header bg-info text-white fw-bold py-2">
                                            <i class="fa-solid fa-server me-2"></i>Estado en Sistema (DB)
                                        </div>
                                        <div class="card-body" id="db-status-container">
                                            <!-- Llenado por JS -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Resultado: NO ENCONTRADO -->
                            <div id="result-error" class="result-card text-center py-4" style="display: none;">
                                <div class="mb-3">
                                    <span class="fa-stack fa-4x text-danger">
                                        <i class="fa-solid fa-circle fa-stack-2x"></i>
                                        <i class="fa-solid fa-xmark fa-stack-1x fa-inverse"></i>
                                    </span>
                                </div>
                                <h3 class="fw-bold text-danger mb-2">NO ENCONTRADO</h3>
                                <p class="lead mb-4">El número no aparece en el Excel cargado.</p>

                                <div class="card result-inner-card border-danger mx-4 text-start">
                                    <div class="card-body">
                                        <p class="mb-1"><strong>🔍 Número Buscado (OCR):</strong> <span
                                                id="ref-ocr-error" class="fw-bold fs-5 text-danger"></span></p>
                                        <hr class="border-white border-opacity-10">
                                        <p class="text-muted small mt-2 mb-0">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Posibles
                                                causas:</strong><br>
                                            1. El número de referencia está mal escrito en el Excel.<br>
                                            2. El OCR leyó mal un dígito (ver imagen).<br>
                                            3. El pago aún no se ha hecho efectivo en este estado de cuenta.
                                        </p>
                                    </div>
                                </div>
                                <button class="btn btn-outline-secondary mt-3" onclick="retryOCR()">Intentar de
                                    nuevo</button>
                            </div>

                            <!-- Resultado: Búsqueda Masiva Excel -->
                            <div id="result-bulk-compare" class="result-card py-4" style="display: none;">
                                <div class="text-center mb-4">
                                    <h3 class="fw-bold text-primary mb-2">RESULTADOS DE COMPARACIÓN</h3>
                                </div>
                                <div class="mx-3">
                                    <ul class="nav nav-pills mb-3 justify-content-center" id="pills-tab" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active bg-success text-white px-4 m-1 fw-bold"
                                                id="pills-found-tab" data-bs-toggle="pill" data-bs-target="#pills-found"
                                                type="button" role="tab"><i
                                                    class="fa-solid fa-check me-2"></i>Encontradas (<span
                                                    id="count-found">0</span>)</button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link bg-danger text-white px-4 m-1 fw-bold"
                                                id="pills-missing-tab" data-bs-toggle="pill"
                                                data-bs-target="#pills-missing" type="button" role="tab"><i
                                                    class="fa-solid fa-xmark me-2"></i>No Encontradas (<span
                                                    id="count-missing">0</span>)</button>
                                        </li>
                                    </ul>
                                    <div class="tab-content glass-panel" id="pills-tabContent"
                                        style="max-height: 400px; overflow-y: auto;">
                                        <!-- Encontradas -->
                                        <div class="tab-pane fade show active p-3" id="pills-found" role="tabpanel">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Referencia</th>
                                                        <th>Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="table-found-body"></tbody>
                                            </table>
                                        </div>
                                        <!-- No Encontradas -->
                                        <div class="tab-pane fade p-3" id="pills-missing" role="tabpanel">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Referencia Buscada</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="table-missing-body"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
<script src="https://unpkg.com/tesseract.js@5.0.3/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let bankData = [];
    let ocrResultText = "";
    let workbook = null;

    const dropZoneExcel = document.getElementById('drop-zone-excel');
    const excelInput = document.getElementById('excel-input');
    const excelInfo = document.getElementById('excel-info');

    // Click simple para Excel
    dropZoneExcel.addEventListener('click', function (e) {
        if (e.target !== excelInput) {
            excelInput.click();
        }
    });

    dropZoneExcel.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZoneExcel.classList.add('active');
    });

    dropZoneExcel.addEventListener('dragleave', function () {
        dropZoneExcel.classList.remove('active');
    });

    dropZoneExcel.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZoneExcel.classList.remove('active');
        if (e.dataTransfer.files[0]) handleExcel(e.dataTransfer.files[0]);
    });

    excelInput.addEventListener('click', function (e) { e.target.value = null; });
    excelInput.addEventListener('change', function (e) {
        if (e.target.files[0]) handleExcel(e.target.files[0]);
    });

    function handleExcel(file) {
        const validTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv'
        ];
        const fileExt = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();

        if (!validTypes.includes(file.type) && !['.xlsx', '.xls', '.csv'].includes(fileExt)) {
            Swal.fire('Formato Incorrecto', 'Por favor sube un Excel válido.', 'error');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];

                // 1. Detectar cabecera real buscando palabras clave
                // Banks suelen tener filas basura al inicio
                const rawData = XLSX.utils.sheet_to_json(worksheet, { header: 1 }); // Array de arrays
                let headerRow = 0;

                const keywords = ['fecha', 'referencia', 'ref', 'documento', 'doc', 'descripcion', 'descripción', 'concepto', 'monto', 'saldo', 'crédito', 'credito', 'débito', 'debito'];

                for (let r = 0; r < Math.min(rawData.length, 25); r++) {
                    let row = rawData[r];
                    let matches = 0;
                    if (row && row.length > 0) {
                        for (let c = 0; c < row.length; c++) {
                            if (row[c] && typeof row[c] === 'string') {
                                let val = row[c].toLowerCase().trim();
                                for (let k = 0; k < keywords.length; k++) {
                                    if (val.indexOf(keywords[k]) !== -1) {
                                        matches++;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if (matches >= 1) { // Si hay match, es la cabecera
                        headerRow = r;
                        break;
                    }
                }

                // 2. Leer JSON final usando esa fila como header
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { range: headerRow, defval: "" });

                if (jsonData.length > 0) {
                    bankData = jsonData;
                    excelInfo.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Cargado ' + bankData.length + ' filas';
                    excelInfo.style.display = 'block';
                    document.getElementById('excel-label').innerText = "Excel Cargado OK";
                    dropZoneExcel.classList.add('border-success');
                    dropZoneExcel.classList.add('bg-light');

                    if (ocrResultText) processExtractedTokens(ocrResultText.match(/\b\d{5,25}\b/g) || [], true);
                } else {
                    Swal.fire('Error', 'Excel vacío.', 'error');
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'No se pudo leer el Excel.', 'error');
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // OCR Logic
    const dropZoneImg = document.getElementById('drop-zone-img');
    const imgInput = document.getElementById('img-input');
    const previewImage = document.getElementById('preview-image');

    const statusDiv = document.getElementById('processing-status');
    const initialStateDiv = document.getElementById('initial-state');
    const resultSuccess = document.getElementById('result-success');
    const resultError = document.getElementById('result-error');
    const resultBulk = document.getElementById('result-bulk-compare');
    const ocrProgress = document.getElementById('ocr-progress');
    const ocrStatusText = document.getElementById('ocr-status-text');

    dropZoneImg.addEventListener('click', function (e) {
        if (e.target !== imgInput) {
            imgInput.click();
        }
    });

    dropZoneImg.addEventListener('dragover', function (e) { e.preventDefault(); dropZoneImg.classList.add('active'); });
    dropZoneImg.addEventListener('dragleave', function () { dropZoneImg.classList.remove('active'); });
    dropZoneImg.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZoneImg.classList.remove('active');
        if (e.dataTransfer.files[0]) processImage(e.dataTransfer.files[0]);
    });

    imgInput.addEventListener('click', function (e) { e.target.value = null; });
    imgInput.addEventListener('change', function (e) {
        if (e.target.files[0]) processImage(e.target.files[0]);
    });

    async function processImage(file) {
        if (!file.type.startsWith('image/')) {
            Swal.fire('Error', 'Sube una imagen válida.', 'error');
            return;
        }

        if (bankData.length === 0) {
            Swal.fire('Atención', 'Sube primero el Excel del banco.', 'warning');
        }

        initialStateDiv.style.display = 'none';
        resultSuccess.style.display = 'none';
        resultError.style.display = 'none';
        resultBulk.style.display = 'none';
        statusDiv.style.display = 'block';
        ocrProgress.style.width = '0%';
        ocrResultText = "";
        window.ocrDetectedAmount = null; // Reset monto detectado
        window.ocrFields = {}; // Reset campos detectados

        const reader = new FileReader();
        reader.onload = function (e) {
            previewImage.src = e.target.result;
            previewImage.style.display = 'block';
            document.getElementById('img-label').innerText = "Imagen Cargada";
            dropZoneImg.classList.add('border-primary');
        };
        reader.readAsDataURL(file);

        ocrStatusText.innerText = "Inicializando OCR...";

        try {
            const result = await Tesseract.recognize(
                file, 'spa',
                {
                    logger: function (m) {
                        if (m.status === 'recognizing text') {
                            let prog = Math.round(m.progress * 100);
                            ocrProgress.style.width = prog + '%';
                            ocrStatusText.innerText = 'Leyendo... ' + prog + '%';
                        }
                    }
                }
            );

            ocrResultText = result.data.text;

            // Extraer posibles montos en Bolívares (Bs)
            const amountMatches = ocrResultText.match(/(?:Bs|VES|Monto|Pagado|Importe)[.:\s]*([\d.,]+)/gi) || [];
            if (amountMatches.length > 0) {
                for (let match of amountMatches) {
                    let clean = match.replace(/(?:Bs|VES|Monto|Pagado|Importe)[.:\s]*/i, '').trim();
                    if (clean.includes(',') && clean.includes('.')) {
                        clean = clean.replace(/\./g, '').replace(',', '.');
                    } else if (clean.includes(',')) {
                        clean = clean.replace(',', '.');
                    }
                    let val = parseFloat(clean);
                    if (!isNaN(val) && val > 0) {
                        window.ocrDetectedAmount = val;
                        window.ocrFields['Monto Detectado'] = `Bs. ${clean}`;
                        break;
                    }
                }
            }

            // Extraer otros campos específicos (basado en formato Galanet/Bancamiga)
            const fieldPatterns = {
                'Fecha de Operación': /Fecha:\s*([\d/]+)/i,
                'Nombre/Beneficiario': /Nombre:\s*([^\n]+)/i,
                'Identificación': /Identificación:\s*([\d]+)/i,
                'Banco': /Banco:\s*([^\n]+)/i,
                'Referencia': /Operación:\s*([\d]+)/i
            };

            for (let [label, pattern] of Object.entries(fieldPatterns)) {
                const match = ocrResultText.match(pattern);
                if (match && match[1]) {
                    window.ocrFields[label] = match[1].trim();
                }
            }

            // Extraer posibles referencias (números de 5 a 25 dígitos)
            const tokens = ocrResultText.match(/\b\d{5,25}\b/g) || [];

            if (tokens.length === 0) {
                statusDiv.style.display = 'none';
                showError("No se detectaron números en la imagen.");
                return;
            }

            processExtractedTokens(tokens, true);

        } catch (err) {
            console.error(err);
            statusDiv.style.display = 'none';
            initialStateDiv.style.display = 'block';
            Swal.fire('Error OCR', 'No se pudo leer la imagen.', 'error');
        }
    }

    // Nueva función para búsqueda manual
    function handleManualSearch() {
        const manualRef = document.getElementById('manual-ref-input').value.trim();

        if (!manualRef) {
            Swal.fire('Atención', 'Ingresa un número de referencia.', 'warning');
            return;
        }

        if (bankData.length === 0) {
            Swal.fire('Atención', 'Sube primero el Excel del banco.', 'warning');
            return;
        }

        // Limpiar estados anteriores
        initialStateDiv.style.display = 'none';
        resultSuccess.style.display = 'none';
        resultError.style.display = 'none';
        resultBulk.style.display = 'none';

        processExtractedTokens([manualRef], false);
    }

    // Lógica Excel Secundario
    const dropZoneExcelSec = document.getElementById('drop-zone-excel-secondary');
    const excelSecInput = document.getElementById('excel-secondary-input');

    dropZoneExcelSec.addEventListener('click', function (e) {
        if (e.target !== excelSecInput) excelSecInput.click();
    });
    dropZoneExcelSec.addEventListener('dragover', function (e) { e.preventDefault(); dropZoneExcelSec.classList.add('active'); });
    dropZoneExcelSec.addEventListener('dragleave', function () { dropZoneExcelSec.classList.remove('active'); });
    dropZoneExcelSec.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZoneExcelSec.classList.remove('active');
        if (e.dataTransfer.files[0]) handleSecondaryExcel(e.dataTransfer.files[0]);
    });
    excelSecInput.addEventListener('click', function (e) { e.target.value = null; });
    excelSecInput.addEventListener('change', function (e) {
        if (e.target.files[0]) handleSecondaryExcel(e.target.files[0]);
    });

    function handleSecondaryExcel(file) {
        if (bankData.length === 0) {
            Swal.fire('Atención', 'Sube primero el archivo Excel principal del banco.', 'warning');
            return;
        }

        const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
        const fileExt = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();

        if (!validTypes.includes(file.type) && !['.xlsx', '.xls', '.csv'].includes(fileExt)) {
            Swal.fire('Formato Incorrecto', 'Por favor sube un Excel válido.', 'error');
            return;
        }

        initialStateDiv.style.display = 'none';
        resultSuccess.style.display = 'none';
        resultError.style.display = 'none';
        resultBulk.style.display = 'none';
        statusDiv.style.display = 'block';
        ocrStatusText.innerText = "Extrayendo referencias del archivo...";
        ocrProgress.style.width = '100%';

        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                document.getElementById('excel-secondary-label').innerText = file.name;
                dropZoneExcelSec.classList.add('border-primary');

                const data = new Uint8Array(e.target.result);
                const wb = XLSX.read(data, { type: 'array' });
                const ws = wb.Sheets[wb.SheetNames[0]];

                // Extraer todo a JSON 1D crudo para buscar cualquier número de más de 4 dígitos
                const rawJson = XLSX.utils.sheet_to_json(ws, { header: 1 });
                let potentialRefs = new Set(); // Evitar duplicados

                rawJson.forEach(row => {
                    row.forEach(cell => {
                        let str = String(cell).trim();
                        // Buscar secuencias numéricas (evitar decimales si es posible, o tomar la parte entera grande)
                        let matches = str.match(/\b\d{5,25}\b/g);
                        if (matches) {
                            matches.forEach(m => potentialRefs.add(m));
                        }
                    });
                });

                const tokensArray = Array.from(potentialRefs);
                if (tokensArray.length > 0) {
                    processBulkSearch(tokensArray);
                } else {
                    statusDiv.style.display = 'none';
                    initialStateDiv.style.display = 'block';
                    Swal.fire('Atención', 'No se encontraron referencias numéricas válidas en el archivo secundario.', 'warning');
                }
            } catch (error) {
                console.error(error);
                statusDiv.style.display = 'none';
                initialStateDiv.style.display = 'block';
                Swal.fire('Error', 'No se pudo leer el Excel.', 'error');
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // Funciones Helper para la Búsqueda Masiva
    function processBulkSearch(tokens) {
        let foundRefs = [];
        let missingRefs = [];

        // Determinar la columna "Referencia" en bankData (Igual que processExtractedTokens)
        const headers = Object.keys(bankData[0]);
        let colRef = null;
        for (let i = 0; i < headers.length; i++) {
            let hLower = headers[i].toLowerCase();
            if (hLower.indexOf('referencia') !== -1 || hLower.indexOf('ref') !== -1 || hLower.indexOf('operacion') !== -1 || hLower.indexOf('doc') !== -1) {
                colRef = headers[i];
                break;
            }
        }

        tokens.forEach(token => {
            let cleanToken = token.trim();
            if (cleanToken.length < 5) return; // Ignorar muy cortos

            let matched = false;
            for (let r = 0; r < bankData.length; r++) {
                let cellValue = colRef ? String(bankData[r][colRef]).trim() : Object.values(bankData[r]).join(" ");
                if (cellValue.indexOf(cleanToken) !== -1) {
                    matched = true;
                    // Guardar ref y row data para "Verificar" botón
                    foundRefs.push({ ref: cleanToken, row: bankData[r] });
                    break;
                }
            }
            if (!matched) {
                missingRefs.push(cleanToken);
            }
        });

        statusDiv.style.display = 'none';
        showBulkResults(foundRefs, missingRefs);
    }

    function showBulkResults(found, missing) {
        resultSuccess.style.display = 'none';
        resultError.style.display = 'none';
        resultBulk.style.display = 'block';

        document.getElementById('count-found').innerText = found.length;
        document.getElementById('count-missing').innerText = missing.length;

        let foundHtml = '';
        if (found.length > 0) {
            found.forEach((item, index) => {
                foundHtml += `
                    <tr>
                        <td class="align-middle fw-bold text-success">${item.ref}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-success" onclick='abrirVerificacionUnica("${item.ref}", ${JSON.stringify(item.row)})'>
                                Verificar DB
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            foundHtml = '<tr><td colspan="2" class="text-center text-muted">Ninguna coincidencia encontrada</td></tr>';
        }
        document.getElementById('table-found-body').innerHTML = foundHtml;

        let missingHtml = '';
        if (missing.length > 0) {
            missing.forEach(ref => {
                missingHtml += `<tr><td class="text-secondary">${ref}</td></tr>`;
            });
        } else {
            missingHtml = '<tr><td class="text-center text-muted">Todo fue encontrado</td></tr>';
        }
        document.getElementById('table-missing-body').innerHTML = missingHtml;
    }

    // Modal para verificar una sola referencia desde el bulk list
    function abrirVerificacionUnica(refString, rowData) {
        // Aprovechar showSuccess y checkDatabase que ya existen para mostrar UI de una sola
        resultBulk.style.display = 'none';
        showSuccess(refString, rowData, false);
        checkDatabase(refString);

        // Agregar botón de volver a la vista en bloque
        const dbSection = document.getElementById('db-check-section');
        let backBtn = document.getElementById('btn-back-bulk');
        if (!backBtn) {
            backBtn = document.createElement('button');
            backBtn.id = 'btn-back-bulk';
            backBtn.className = 'btn btn-outline-secondary mt-3 mx-4';
            backBtn.innerHTML = '<i class="fa-solid fa-arrow-left me-1"></i> Volver a Lista';
            backBtn.onclick = () => {
                resultSuccess.style.display = 'none';
                resultBulk.style.display = 'block';
            };
            resultSuccess.appendChild(backBtn);
        }
    }

    function processExtractedTokens(tokens, isOCR) {
        if (bankData.length === 0) {
            statusDiv.style.display = 'none';
            initialStateDiv.style.display = 'block';
            return;
        }

        let foundMatch = null;
        let matchedRef = "";

        const headers = Object.keys(bankData[0]);
        let colRef = null;

        for (let i = 0; i < headers.length; i++) {
            let h = headers[i];
            let hLower = h.toLowerCase();
            if (hLower.indexOf('referencia') !== -1 || hLower.indexOf('ref') !== -1 || hLower.indexOf('operacion') !== -1 || hLower.indexOf('doc') !== -1) {
                colRef = h;
                break;
            }
        }

        for (let t = 0; t < tokens.length; t++) {
            let cleanToken = tokens[t].trim();

            for (let r = 0; r < bankData.length; r++) {
                let row = bankData[r];
                let cellValue = "";

                if (colRef) {
                    cellValue = String(row[colRef]).trim();
                } else {
                    cellValue = Object.values(row).join(" ");
                }

                if (cellValue.indexOf(cleanToken) !== -1 && cleanToken.length > 4) {
                    foundMatch = row;
                    matchedRef = cleanToken;
                    break;
                }
            }
            if (foundMatch) break;
        }

        statusDiv.style.display = 'none';

        if (foundMatch) {
            showSuccess(matchedRef, foundMatch, isOCR);
            checkDatabase(matchedRef); // Verificar en base de datos al encontrar match
        } else {
            showError(tokens.join(", "), isOCR);
        }
    }

    async function checkDatabase(ref) {
        const container = document.getElementById('db-status-container');
        const section = document.getElementById('db-check-section');

        section.style.display = 'block';
        container.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                <span class="ms-2">Buscando en sistema...</span>
            </div>
        `;

        try {
            const response = await fetch('buscar_referencias_conciliacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ referencias: [ref] })
            });

            const results = await response.json();
            const data = results[0];

            if (data && data.encontrado) {

                let html = `
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <p class="mb-1"><strong>Cliente:</strong> ${data.nombre_completo}</p>
                            <p class="mb-1"><strong>Tipo:</strong> <span class="badge ${data.tipo === 'PAGO REGISTRADO' ? 'bg-success' : 'bg-warning'}">${data.tipo}</span></p>
                            ${data.capture_path ? `
                                <a href="../../${data.capture_path}" target="_blank" class="btn btn-sm btn-outline-info mt-2">
                                    <i class="fa-solid fa-image me-1"></i> Ver Comprobante
                                </a>
                            ` : '<p class="text-muted small mt-1"><em>Sin comprobante digital</em></p>'}
                        </div>
                        <div class="col-md-5 text-end">
                `;

                if (data.tipo === 'REPORTE_WEB') {
                    if (data.estado === 'PENDIENTE') {
                        html += `
                            <button class="btn btn-success btn-sm w-100 fw-bold" onclick='aprobarReporte(${JSON.stringify(data).replace(/'/g, "&apos;")})'>
                                <i class="fa-solid fa-check-circle me-1"></i> Aprobar Pago
                            </button>
                        `;
                    } else if (data.estado === 'APROBADO') {
                        html += `<span class="text-success fw-bold"><i class="fa-solid fa-check-double me-1"></i> YA APROBADO</span>`;
                    } else {
                        html += `<span class="text-muted fw-bold">${data.estado}</span>`;
                    }
                } else {
                    html += `<span class="text-success fw-bold"><i class="fa-solid fa-check-double me-1"></i> REGISTRADO</span>`;
                }

                html += `</div></div>`;
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="alert alert-warning mb-0 py-2 small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> El cliente aún no ha reportado este pago en la web.
                    </div>
                `;
            }
        } catch (err) {
            console.error(err);
            container.innerHTML = `<span class="text-danger">Error al consultar DB</span>`;
        }
    }

    async function aprobarReporte(reporte) {
        // PRIORIDAD: Monto detectado por OCR (siempre de la imagen como pidió el usuario)
        let montoSugerido = window.ocrDetectedAmount || "";

        // Si no hay monto por OCR, intentar buscar algún campo que parezca monto en el resultado visual (backup)
        if (!montoSugerido) {
            const bankRows = document.querySelectorAll('#bank-details-list li');
            bankRows.forEach(li => {
                if (li.innerText.toLowerCase().includes('monto') || li.innerText.toLowerCase().includes('valor')) {
                    const val = li.innerText.split(':')[1].trim();
                    montoSugerido = val.replace(/[^0-9.,]/g, '').replace(',', '.');
                }
            });
        }

        const { value: confirmData, isConfirmed } = await Swal.fire({
            title: 'Confirmar Aprobación de Pago',
            html: `
                <div class="text-start mb-3 p-2 bg-light border rounded small">
                    <strong>Cliente Detectado:</strong> ${reporte.nombre_completo || 'No especificado'}<br>
                    <strong>Referencia:</strong> ${reporte.referencia}
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Monto final a registrar ($):</label>
                    <input type="number" step="0.01" id="swal-monto" class="form-control text-center fs-3 fw-bold text-success" value="${montoSugerido}" placeholder="0.00">
                    <div class="form-text text-primary"><i class="fa-solid fa-camera me-1"></i> Sugerido desde Capture</div>
                </div>
                <hr>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">ID de Contrato para el Sistema:</label>
                    <input type="number" id="swal-contrato" class="form-control form-control-sm text-center" value="${reporte.id_contrato || ''}" placeholder="Opcional">
                    <div class="form-text xsmall">* Si se deja vacío, solo se marcará el reporte como aprobado.</div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirmar y Aprobar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            preConfirm: () => {
                const monto = document.getElementById('swal-monto').value;
                const contrato = document.getElementById('swal-contrato').value;
                if (!monto || parseFloat(monto) <= 0) {
                    Swal.showValidationMessage('Por favor ingrese un monto válido');
                }
                return { monto, contrato };
            }
        });

        if (!isConfirmed) return;

        Swal.fire({
            title: 'Procesando...',
            didOpen: () => { Swal.showLoading(); },
            allowOutsideClick: false
        });

        try {
            const response = await fetch('aprobar_pago_json.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_reporte: reporte.id_reporte,
                    id_contrato: confirmData.contrato,
                    monto_total: parseFloat(confirmData.monto),
                    fecha_pago: reporte.fecha_pago,
                    referencia: reporte.referencia,
                    id_banco: reporte.id_banco_destino,
                    accion: 'APROBAR'
                })
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire('¡Éxito!', result.message, 'success');
                checkDatabase(reporte.referencia); // Refrescar estado en UI
            } else {
                Swal.fire('Error', result.message, 'error');
            }
        } catch (err) {
            console.error(err);
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    }

    function showSuccess(ref, rowData, isOCR) {
        document.getElementById('db-check-section').style.display = 'none'; // Limpiar previo
        resultSuccess.style.display = 'block';
        resultError.style.display = 'none';

        const detectLabel = isOCR ? "Detectado en Capture" : "Ingresado Manualmente";
        const titleText = document.querySelector('#result-success h6.text-muted');
        if (titleText) titleText.innerText = detectLabel;

        document.getElementById('ref-ocr').innerText = ref;

        let listHtml = '';

        // Si es OCR, priorizar los campos extraídos directamente de la imagen
        if (isOCR && window.ocrFields && Object.keys(window.ocrFields).length > 0) {
            for (let [key, val] of Object.entries(window.ocrFields)) {
                listHtml += `<li class="mb-1 text-primary"><strong><i class="fa-solid fa-barcode me-1"></i> ${key}:</strong> ${val}</li>`;
            }
            listHtml += '<hr class="my-2">';
            listHtml += '<li class="text-muted small mb-2"><em>Confirmado contra registros bancarios (Excel)</em></li>';
        } else {
            // Si es manual o falló la extracción de campos, mostrar datos crudos del Excel
            const keys = Object.keys(rowData);
            for (let k = 0; k < keys.length; k++) {
                let key = keys[k];
                let val = rowData[key];
                // ... rest of existing logic for renaming __EMPTY fields ...
                if (key.indexOf('__EMPTY') !== -1) {
                    if (!val || String(val).trim() === '') continue;
                    if (!isNaN(val) && val > 40000 && val < 50000 && String(val).indexOf('.') !== -1) {
                        key = "Fecha (Aprox)";
                        try { let dateObj = new Date(Math.round((val - 25569) * 86400 * 1000)); val = dateObj.toLocaleDateString(); } catch (e) { }
                    } else if (!isNaN(val) && String(val).length > 6) { key = "Referencia/Doc"; }
                    else if (String(val).length > 15) { key = "Descripción"; }
                    else if (!isNaN(val)) { key = "Monto/Valor"; }
                    else { key = "Dato"; }
                }
                if (val && String(val).trim() !== '') {
                    listHtml += '<li class="mb-1"><strong>' + key + ':</strong> ' + val + '</li>';
                }
            }
        }
        document.getElementById('bank-details-list').innerHTML = listHtml;
    }

    function showError(refAttempt, isOCR) {
        resultSuccess.style.display = 'none';
        resultError.style.display = 'block';
        let displayRef = refAttempt;
        if (displayRef.length > 50) displayRef = displayRef.substring(0, 50) + "...";

        const errorLabel = document.querySelector('#result-error strong');
        if (errorLabel) {
            errorLabel.innerText = isOCR ? "🔍 Número Buscado (OCR):" : "🔍 Número Buscado (Manual):";
        }

        document.getElementById('ref-ocr-error').innerText = displayRef;
    }

    function retryOCR() {
        imgInput.value = '';
        document.getElementById('drop-zone-img').click();
    }
</script>

<?php require_once '../includes/layout_foot.php'; ?>