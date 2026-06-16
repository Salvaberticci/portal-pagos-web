<?php
// registro_contrato_instalador.php
// Standalone form for installers — mirrors nuevo.php exactly, no session required.

require_once '../conexion.php';

// --- QUERY: all municipalities ---
$sql_municipios = "SELECT id_municipio, nombre_municipio FROM municipio ORDER BY nombre_municipio ASC";
$resultado_municipios = $conn->query($sql_municipios);

// --- QUERY: all OLTs ---
$olts = [];
$sql_olts = "SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt ASC";
$resultado_olts = $conn->query($sql_olts);
if ($resultado_olts && $resultado_olts->num_rows > 0) {
    while ($row = $resultado_olts->fetch_assoc()) {
        $olts[] = $row;
    }
}

// --- QUERY: plans ---
$sql_planes = "SELECT id_plan, nombre_plan, monto FROM planes ORDER BY nombre_plan ASC";
$resultado_planes = $conn->query($sql_planes);

// --- JSON: installers and vendors ---
$jsonInstaladores = '../../paginas/principal/data/instaladores.json';
$instaladoresList = [];
if (file_exists($jsonInstaladores)) {
    $instaladoresList = json_decode(file_get_contents($jsonInstaladores), true) ?: [];
}

// --- Tipo de Conexión JSON ---
$jsonFileTypes = '../../paginas/principal/data/tipos_conexion.json';
$tiposConexion = ['FTTH', 'RADIO'];
if (file_exists($jsonFileTypes)) {
    $typesData = json_decode(file_get_contents($jsonFileTypes), true);
    if (is_array($typesData)) {
        $tiposConexion = $typesData;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Contrato - Instalador</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- intl-tel-input CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.4/build/css/intlTelInput.css">
    <style>
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
            border-left: 4px solid #198754;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 15px;
            border-radius: 3px;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }

        .campo-ftth,
        .campo-radio {
            display: none;
        }

        /* Estilos para intl-tel-input */
        .iti {
            width: 100%;
            display: block;
        }

        .iti__country-list {
            z-index: 1056;
            background-color: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-success text-white text-center py-3">
                        <h4 class="mb-0"><i class="fas fa-file-signature me-2"></i>Registro de Nuevo Contrato —
                            Instalador</h4>
                    </div>
                    <div class="card-body p-4">

                        <form id="formContrato" action="guardar_contrato_instalador.php" method="POST"
                            enctype="multipart/form-data" class="row g-3" autocomplete="off">
                            <input type="hidden" name="sae_plus" value="Excel">

                            <!-- ═══════════════════════════════════════════ -->
                            <!-- DATOS DEL CLIENTE                          -->
                            <!-- ═══════════════════════════════════════════ -->
                            <div class="col-12">
                                <div class="section-title">Datos del Cliente</div>
                            </div>

                            <div class="col-md-6">
                                <label for="cedula" class="form-label">Cédula / RIF <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select" name="tipo_cedula" id="tipo_cedula"
                                        style="max-width: 80px;" required>
                                        <option value="V" selected>V</option>
                                        <option value="E">E</option>
                                        <option value="J">J</option>
                                    </select>
                                    <input type="text" class="form-control" id="cedula" name="cedula" required
                                        pattern="^[VEJ][0-9]+" placeholder="V12345678">
                                </div>
                                <div class="form-text small">Seleccione tipo e ingrese solo números.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="nombre_completo" class="form-label">Nombre Completo <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo"
                                    required pattern="[A-Za-zñÑáéíóúÁÉÍÓÚ\s]+" title="Solo letras y espacios">
                            </div>

                            <div class="col-md-6">
                                <label for="telefono" class="form-label fw-bold">Teléfono de Contacto 1 <span
                                        class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" required
                                    placeholder="0424-1234567">
                            </div>

                            <div class="col-md-6">
                                <label for="correo" class="form-label">Correo</label>
                                <input type="email" class="form-control" id="correo" name="correo"
                                    placeholder="ejemplo@correo.com">
                            </div>

                            <div class="col-md-6">
                                <label for="telefono_secundario" class="form-label">Teléfono de Contacto 2</label>
                                <input type="tel" class="form-control" id="telefono_secundario"
                                    name="telefono_secundario" placeholder="0414-7654321">
                            </div>

                            <div class="col-md-6">
                                <label for="correo_adicional" class="form-label">Correo Adicional</label>
                                <input type="email" class="form-control" id="correo_adicional" name="correo_adicional"
                                    placeholder="otro@correo.com">
                            </div>

                            <div class="col-md-6">
                                <label for="municipio" class="form-label">Municipio</label>
                                <select name="id_municipio" id="municipio" class="form-select" required>
                                    <option value="">Cargando municipios...</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="parroquia" class="form-label">Parroquia</label>
                                <select name="id_parroquia" id="parroquia" class="form-select" disabled required>
                                    <option value="">-- Primero seleccione un municipio --</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="id_plan" class="form-label">Plan</label>
                                <select name="id_plan" id="id_plan" class="form-select" required>
                                    <option value="">-- Seleccione un Plan --</option>
                                    <?php
                                    if ($resultado_planes && $resultado_planes->num_rows > 0) {
                                        while ($fila = $resultado_planes->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($fila["id_plan"]) . '" data-monto-plan="' . htmlspecialchars($fila["monto"]) . '">'
                                                . htmlspecialchars($fila["nombre_plan"]) . ' ($' . htmlspecialchars($fila["monto"]) . ')'
                                                . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="monto_plan" id="monto_plan">
                            </div>

                            <div class="col-md-6">
                                <label for="vendedor_texto" class="form-label">Vendedor</label>
                                <select name="vendedor_texto" id="vendedor_texto" class="form-select" required>
                                    <option value="">Cargando vendedores...</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="direccion" class="form-label">Dirección (Referencia de localidad) <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="3"
                                    required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="fecha_instalacion" class="form-label">Fecha de Instalación</label>
                                <input type="date" class="form-control" id="fecha_instalacion" name="fecha_instalacion"
                                    required max="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="estado_contrato" class="form-label">Estado del Contrato</label>
                                <select class="form-select" id="estado_contrato" name="estado_contrato" required>
                                    <option value="">-- Seleccione el Estado --</option>
                                    <option value="ACTIVO" selected>ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                    <option value="SUSPENDIDO">SUSPENDIDO</option>
                                </select>
                            </div>



                            <!-- ═══════════════════════════════════════════ -->
                            <!-- INSTALACIÓN Y PAGO                         -->
                            <!-- ═══════════════════════════════════════════ -->
                            <div class="col-12">
                                <div class="section-title">Información de Instalación y Pago</div>
                            </div>

                            <div class="col-md-6">
                                <label for="tipo_conexion" class="form-label">Tipo de Conexión</label>
                                <select name="tipo_conexion" id="tipo_conexion" class="form-select" required>
                                    <option value="">-- Seleccione Conexión --</option>
                                    <?php foreach ($tiposConexion as $type) {
                                        echo '<option value="' . htmlspecialchars($type) . '">' . htmlspecialchars($type) . '</option>';
                                    } ?>
                                </select>
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="id_olt" class="form-label">OLT</label>
                                <select name="id_olt" id="id_olt" class="form-select" required>
                                    <option value="">-- Seleccione una OLT --</option>
                                    <?php
                                    if (!empty($olts)) {
                                        foreach ($olts as $olt) {
                                            echo '<option value="' . htmlspecialchars($olt["id_olt"]) . '">'
                                                . htmlspecialchars($olt["nombre_olt"])
                                                . '</option>';
                                        }
                                    } else {
                                        echo '<option value="" disabled>No se encontraron OLTs.</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="id_pon" class="form-label">PON</label>
                                <select name="id_pon" id="id_pon" class="form-select" required disabled>
                                    <option value="">-- Seleccione una OLT primero --</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="tipo_instalacion" class="form-label">Tipo de Instalación <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="tipo_instalacion" name="tipo_instalacion" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php
                                    $jsonTiposInst = '../../data/tipos_instalacion.json';
                                    if (file_exists($jsonTiposInst)) {
                                        $tiposInst = json_decode(file_get_contents($jsonTiposInst), true);
                                        foreach ($tiposInst as $t)
                                            echo '<option value="' . $t . '">' . $t . '</option>';
                                    } else {
                                        $defaultsInst = ["Nivel 1", "Nivel 2", "Nivel 3", "Mudanza", "Migración", "Onu", "Reactivación"];
                                        foreach ($defaultsInst as $t)
                                            echo '<option value="' . $t . '">' . $t . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="monto_instalacion" class="form-label">Monto Instalación ($) <span
                                        class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="monto_instalacion"
                                    name="monto_instalacion" required value="0">
                            </div>

                            <div class="col-md-6">
                                <label for="gastos_adicionales" class="form-label">Gastos Adicionales ($)</label>
                                <input type="number" step="0.01" class="form-control" id="gastos_adicionales"
                                    name="gastos_adicionales" value="0">
                            </div>

                            <!-- Switch de Prorrateo -->
                            <div class="col-12 mt-3 mb-2">
                                <div
                                    class="form-check form-switch bg-light border rounded p-3 d-flex align-items-center gap-3">
                                    <input class="form-check-input ms-0 mt-0" type="checkbox" role="switch"
                                        id="incluye_prorrateo" name="incluye_prorrateo" value="SI"
                                        style="width: 3em; height: 1.5em; cursor: pointer;">
                                    <label class="form-check-label mb-0 fw-bold text-success" for="incluye_prorrateo"
                                        style="cursor: pointer;">¿Aplica días de prorrateo?</label>
                                </div>
                            </div>

                            <!-- Contenedor Oculto Prorrateo -->
                            <div class="col-12" id="contenedor_prorrateo" style="display: none;">
                                <div class="row p-3 border rounded bg-white mt-1 shadow-sm">
                                    <div class="col-md-4 mb-3">
                                        <label for="plan_prorrateo" class="form-label fw-semibold text-secondary">Plan
                                            para Prorrateo</label>
                                        <select class="form-select" id="plan_prorrateo" name="plan_prorrateo_nombre">
                                            <option value="">Cargando planes...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="dias_prorrateo" class="form-label fw-semibold text-secondary">Días
                                            de Prorrateo</label>
                                        <input type="number" min="0" class="form-control" id="dias_prorrateo"
                                            name="dias_prorrateo" value="0" placeholder="0">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="monto_prorrateo_usd"
                                            class="form-label fw-semibold text-secondary">Monto Prorrateo ($)</label>
                                        <input type="number" step="0.01" class="form-control fw-bold bg-light"
                                            id="monto_prorrateo_usd" name="monto_prorrateo_usd" readonly
                                            placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="monto_pagar" class="form-label">Monto Total a Pagar ($)</label>
                                <input type="number" step="0.01" class="form-control" id="monto_pagar"
                                    name="monto_pagar" readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="monto_pagado" class="form-label">Monto Pagado</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="monto_pagado"
                                        name="monto_pagado" required>
                                    <select class="form-select" id="moneda_pago" name="moneda_pago"
                                        style="max-width: 100px;">
                                        <option value="USD" selected>USD</option>
                                        <option value="BS">BS</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6" id="div_saldo_pendiente" style="display:none;">
                                <label for="saldo_pendiente" class="form-label text-danger fw-bold">Saldo Pendiente
                                    ($)</label>
                                <input type="number" step="0.01" class="form-control border-danger text-danger fw-bold"
                                    id="saldo_pendiente" name="saldo_pendiente" readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="medio_pago" class="form-label">Medio de Pago <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="medio_pago" name="medio_pago" required>
                                    <option value="">-- Seleccione --</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Pago Móvil">Pago Móvil</option>
                                    <option value="Zelle">Zelle</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones"
                                    rows="2"></textarea>
                            </div>

                            <!-- ═══════════════════════════════════════════ -->
                            <!-- DETALLES TÉCNICOS DE CONEXIÓN              -->
                            <!-- ═══════════════════════════════════════════ -->
                            <div class="col-12">
                                <div class="section-title">Detalles Técnicos de Conexión</div>
                            </div>

                            <!-- CAMPOS FTTH -->
                            <div class="col-md-6 campo-ftth">
                                <label for="mac_onu" class="form-label">MAC o Serial de la ONU <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="mac_onu" name="mac_onu"
                                    pattern="[A-Fa-f0-9:\.\-]{8,20}" placeholder="FABBCC112233">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="ip_onu" class="form-label">Dirección IP Asignada a la ONU <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ip_onu" name="ip_onu" value="192.168."
                                    pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                    placeholder="192.168.1.1">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="ident_caja_nap" class="form-label">Identificación Caja NAP <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ident_caja_nap" name="ident_caja_nap">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="puerto_nap" class="form-label">Puerto NAP <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="puerto_nap" name="puerto_nap">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="nap_tx_power" class="form-label">NAP TX Power (dBm) <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nap_tx_power" name="nap_tx_power"
                                    pattern="-?[0-9.]+" placeholder="-25.5">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="onu_rx_power" class="form-label">ONU RX Power (dBm) <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="onu_rx_power" name="onu_rx_power"
                                    pattern="-?[0-9.]+" placeholder="-27.5">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="distancia_drop" class="form-label">Distancia Drop (m) <span
                                        class="text-danger">*</span></label>
                                <input type="number" step="1" class="form-control" id="distancia_drop"
                                    name="distancia_drop" placeholder="50">
                            </div>

                            <div class="col-md-6 campo-ftth">
                                <label for="num_presinto_odn" class="form-label text-primary fw-bold">Precinto ODN <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control border-primary shadow-sm" id="num_presinto_odn"
                                    name="num_presinto_odn" placeholder="Ej. A-123">
                            </div>

                            <!-- CAMPOS RADIO -->
                            <div class="col-md-6 campo-radio">
                                <label for="ip" class="form-label">Dirección IP <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ip" name="ip_onu" placeholder="192.168.x.x"
                                    pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$">
                            </div>

                            <div class="col-md-6 campo-radio">
                                <label for="punto_acceso" class="form-label">Punto de Acceso <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="punto_acceso" name="punto_acceso">
                            </div>

                            <div class="col-md-12">
                                <label for="evidencia_fibra" class="form-label">Evidencia</label>
                                <input type="text" class="form-control" id="evidencia_fibra" name="evidencia_fibra" placeholder="Detalle o ID de evidencia de fibra o radio">
                            </div>

                            <div class="col-md-6 campo-radio">
                                <label for="valor_conexion_dbm" class="form-label">Valor Conexión (dBm) <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="valor_conexion_dbm"
                                    name="valor_conexion_dbm" pattern="-?[0-9.]+" placeholder="-55.0">
                            </div>

                            <!-- Instalador FTTH -->
                            <div class="col-md-6 campo-ftth">
                                <label for="instalador_ftth" class="form-label fw-bold">Instalador FTTH</label>
                                <select name="instalador_ftth" id="instalador_ftth" class="form-select">
                                    <option value="">-- Seleccione un Instalador --</option>
                                    <?php
                                    if (!empty($instaladoresList)) {
                                        foreach ($instaladoresList as $inst) {
                                            echo '<option value="' . htmlspecialchars($inst) . '">' . htmlspecialchars($inst) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Instalador Radio -->
                            <div class="col-md-6 campo-radio">
                                <label for="instalador_radio" class="form-label fw-bold">Instalador Radio</label>
                                <select name="instalador_radio" id="instalador_radio" class="form-select">
                                    <option value="">-- Seleccione un Instalador --</option>
                                    <?php
                                    if (!empty($instaladoresList)) {
                                        foreach ($instaladoresList as $inst) {
                                            echo '<option value="' . htmlspecialchars($inst) . '">' . htmlspecialchars($inst) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- ═══════════════════════════════════════════ -->
                            <!-- EVIDENCIA FOTOGRÁFICA                      -->
                            <!-- ═══════════════════════════════════════════ -->
                            <div class="col-12">
                                <div class="section-title">Evidencia y Documentación</div>
                            </div>

                            <div class="col-md-6">
                                <label for="evidencia_foto" class="form-label">Evidencia Fotográfica
                                    (Instalación)</label>
                                <input type="file" class="form-control" id="evidencia_foto" name="evidencia_foto"
                                    accept="image/*">
                                <div id="preview_foto" class="mt-2 text-center"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="foto_documento" class="form-label">Documento de Identidad</label>
                                <input type="file" class="form-control" id="foto_documento"
                                    name="evidencia_documento_file" accept="image/*">
                                <div id="preview_documento" class="mt-2 text-center"></div>
                            </div>

                            <!-- ═══════════════════════════════════════════ -->
                            <!-- FIRMAS DIGITALES                           -->
                            <!-- ═══════════════════════════════════════════ -->
                            <div class="col-12">
                                <div class="section-title">Firmas Digitales</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Firma del Cliente <span
                                        class="text-danger">*</span></label>
                                <canvas id="sigCliente" class="signature-pad"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                    onclick="clearPad('cliente')">Limpiar</button>
                                <input type="hidden" name="firma_cliente_data" id="firma_cliente_data">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Firma del Técnico <span
                                        class="text-danger">*</span></label>
                                <canvas id="sigTecnico" class="signature-pad"></canvas>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                    onclick="clearPad('tecnico')">Limpiar</button>
                                <input type="hidden" name="firma_tecnico_data" id="firma_tecnico_data">
                            </div>

                            <!-- ═══════════════════════════════════════════ -->
                            <!-- SUBMIT                                      -->
                            <!-- ═══════════════════════════════════════════ -->
                            <div class="col-12 d-grid mt-3">
                                <button type="submit" class="btn btn-success btn-lg" id="btnGuardar">
                                    <i class="fas fa-save me-2"></i>Registrar Contrato
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
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-link me-2"></i>Enlace de Contrato Generado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="mb-4">
                        <i class="fa-regular fa-circle-check text-primary fa-4x mb-3"></i>
                        <h4>¡Contrato Pre-registrado!</h4>
                        <p class="text-muted">El contrato está pendiente de firma del cliente.</p>
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
                            <i class="fa-brands fa-whatsapp me-2"></i>Enviar por WhatsApp
                        </a>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal"
                                onclick="mostrarOpcionesExito(window.lastSavedId)">
                                <i class="fa-solid fa-arrow-left me-1"></i>Volver
                            </button>
                            <button type="button" class="btn btn-primary w-100" onclick="location.reload()">
                                Finalizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>

    <!-- ═══════════════════════════════════════════ -->
    <!-- MODAL DE CONFIRMACIÓN (Sync with nuevo.php) -->
    <!-- ═══════════════════════════════════════════ -->
    <script>
        function mostrarConfirmacionRegistro(e) {
            e.preventDefault();
            const form = e.target;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const cedula = $('#cedula').val();
            const prefijo = $('#tipo_cedula').val();
            // Extraer solo los números para la API
            const cedulaNum = cedula.startsWith(prefijo) ? cedula.substring(prefijo.length) : cedula.replace(/[^0-9]/g, '');

            // VALIDACIÓN DE DUPLICADOS (Nuevo paso)
            $.ajax({
                url: '../principal/check_cedula_api.php',
                type: 'GET',
                data: {
                    cedula: cedulaNum,
                    tipo_cedula: prefijo
                },
                dataType: 'json',
                success: function (response) {
                    if (response.exists) {
                        Swal.fire({
                            title: '¡Cédula Detectada!',
                            html: `Ya existe un contrato registrado con esta cédula (<b>${prefijo}-${cedulaNum}</b>) a nombre de:<br><b>${response.nombre_completo}</b><br><br>¿Quieres registrar un nuevo contrato con esta misma cédula?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#198754',
                            cancelButtonColor: '#dc3545',
                            confirmButtonText: 'Sí, registrar otro',
                            cancelButtonText: 'No, cambiar cédula'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                procederAlResumen(form);
                            } else {
                                Swal.fire({
                                    title: 'Cambio de Cédula',
                                    text: 'Entonces, por favor ingresa otra cédula para registrar este contrato.',
                                    icon: 'info',
                                    confirmButtonText: 'Entendido'
                                }).then(() => {
                                    $('#cedula').val('').focus();
                                });
                            }
                        });
                    } else {
                        procederAlResumen(form);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error al verificar cédula:", error);
                    procederAlResumen(form);
                }
            });
        }

        function procederAlResumen(form) {
            // Extraer datos para el resumen
            const cedula = $('#cedula').val();
            const nombre = $('#nombre_completo').val();
            const plan = $('#id_plan option:selected').text();
            const montoTotal = $('#monto_pagar').val();
            const montoPagado = $('#monto_pagado').val();
            const moneda = $('#moneda_pago').val();
            const medio = $('#medio_pago').val();
            const tecnico = $('#instaladores').val();

            const htmlResumen = `
                <div class="text-start">
                    <table class="table table-sm table-bordered mt-2">
                        <tbody>
                            <tr><th class="bg-light">Cédula:</th><td>${cedula}</td></tr>
                            <tr><th class="bg-light">Titular:</th><td>${nombre}</td></tr>
                            <tr><th class="bg-light">Plan:</th><td>${plan}</td></tr>
                            <tr><th class="bg-light">Monto Total:</th><td>$${montoTotal}</td></tr>
                            <tr><th class="bg-light">Monto Pagado:</th><td>${montoPagado} ${moneda}</td></tr>
                            <tr><th class="bg-light">Medio de Pago:</th><td>${medio}</td></tr>
                            <tr><th class="bg-light">Instalador:</th><td>${tecnico || 'No asignado'}</td></tr>
                        </tbody>
                    </table>
                    <p class="text-center fw-bold text-success mt-3">¿Desea registrar este contrato con estos datos?</p>
                </div>
            `;

            Swal.fire({
                title: 'Confirmar Registro',
                html: htmlResumen,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Registrar',
                cancelButtonText: 'Verificar'
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarGuardado(form);
                }
            });
        }

        function ejecutarGuardado(form) {
             // Capturar firmas antes de enviar
            if (window.padCliente && !window.padCliente.isEmpty()) {
                $('#firma_cliente_data').val(window.padCliente.toDataURL());
            }
            if (window.padTecnico && !window.padTecnico.isEmpty()) {
                $('#firma_tecnico_data').val(window.padTecnico.toDataURL());
            }

            const formData = new FormData(form);

            Swal.fire({
                title: 'Procesando...',
                text: 'Guardando registro...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('guardar_contrato_instalador.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'Contrato registrado correctamente.',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', data.msg, 'error');
                }
            })
            .catch(error => {
                console.error(error);
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }

        $(document).ready(function() {
            $('#formContrato').on('submit', mostrarConfirmacionRegistro);
        });
    </script>

    <!-- ═══════════════════════════════════════════ -->
    <!-- SIGNATURE PAD INIT                         -->
    <!-- ═══════════════════════════════════════════ -->
    <script>
        const canvasCliente = document.getElementById('sigCliente');
        const canvasTecnico = document.getElementById('sigTecnico');

        const padCliente = new SignaturePad(canvasCliente);
        const padTecnico = new SignaturePad(canvasTecnico);

        function resizeCanvas(canvas, pad) {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var newWidth = canvas.offsetWidth * ratio;
            var newHeight = canvas.offsetHeight * ratio;

            if (canvas.width !== newWidth || canvas.height !== newHeight) {
                const data = pad ? pad.toData() : null;
                canvas.width = newWidth;
                canvas.height = newHeight;
                canvas.getContext("2d").scale(ratio, ratio);
                if (pad) {
                    pad.clear();
                    if (data) pad.fromData(data);
                }
            }
        }

        window.onresize = function () { 
            resizeCanvas(canvasCliente, padCliente); 
            resizeCanvas(canvasTecnico, padTecnico); 
        };
        resizeCanvas(canvasCliente, padCliente); 
        resizeCanvas(canvasTecnico, padTecnico);

        window.clearPad = function (type) {
            if (type === 'cliente') padCliente.clear();
            if (type === 'tecnico') padTecnico.clear();
        };

        window.padCliente = padCliente;
        window.padTecnico = padTecnico;
    </script>

    <!-- ═══════════════════════════════════════════ -->
    <!-- INPUT VALIDATION & FIELD MASKING           -->
    <!-- ═══════════════════════════════════════════ -->
    <script>
        // 1. Cédula/RIF: LÓGICA DE PREFIJO AUTOMÁTICO
        const $tipoCedula = $('#tipo_cedula');
        const $cedulaInput = $('#cedula');

        function actualizarPrefijo() {
            const prefijo = $tipoCedula.val();
            let currentVal = $cedulaInput.val();

            if (currentVal === '') {
                $cedulaInput.val(prefijo);
                return;
            }

            const soloNumeros = currentVal.replace(/[^0-9]/g, '');
            $cedulaInput.val(prefijo + soloNumeros);
        }

        $tipoCedula.on('change', function() {
            actualizarPrefijo();
        });

        $cedulaInput.on('input', function() {
            const prefijo = $tipoCedula.val();
            let val = $(this).val();

            if (!val.startsWith(prefijo)) {
                const soloNumeros = val.replace(/[^0-9]/g, '');
                $(this).val(prefijo + soloNumeros);
            } else {
                const rest = val.substring(prefijo.length).replace(/[^0-9]/g, '');
                $(this).val(prefijo + rest);
            }
        });

        if ($cedulaInput.val() === '') {
            actualizarPrefijo();
        }

        // 1.1 VERIFICACIÓN DE CÉDULA DUPLICADA
        $cedulaInput.on('blur', function () {
            const val = $(this).val().trim();
            const prefijo = $tipoCedula.val();
            const $el = $(this);
            // Extraer solo los números para la API
            const cedulaNum = val.startsWith(prefijo) ? val.substring(prefijo.length) : val.replace(/[^0-9]/g, '');

            if (cedulaNum.length > 0) {
                $.ajax({
                    url: '../principal/check_cedula_api.php',
                    type: 'GET',
                    data: {
                        cedula: cedulaNum,
                        tipo_cedula: prefijo
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.exists) {
                            Swal.fire({
                                title: '¡Cédula Detectada!',
                                html: `Ya existe un contrato registrado con esta cédula (<b>${prefijo}-${cedulaNum}</b>) a nombre de:<br><b>${response.data.nombre_completo}</b><br><br>¿Quieres registrar un nuevo contrato cargando los datos de este cliente?`,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#198754',
                                cancelButtonColor: '#dc3545',
                                confirmButtonText: 'Sí, cargar datos',
                                cancelButtonText: 'No, llenar manualmente',
                                denyButtonText: 'Cambiar cédula',
                                showDenyButton: true,
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // AUTOCOMPLETAR DATOS
                                    const d = response.data;
                                    $('#nombre_completo').val(d.nombre_completo);
                                    $('#correo').val(d.correo);
                                    $('#correo_adicional').val(d.correo_adicional);
                                    $('#direccion').val(d.direccion);
                                    
                                    // Teléfonos con intl-tel-input
                                    if (window.iti1) window.iti1.setNumber(d.telefono || '');
                                    else $('#telefono').val(d.telefono);
                                    
                                    if (window.iti2) window.iti2.setNumber(d.telefono_secundario || '');
                                    else $('#telefono_secundario').val(d.telefono_secundario);

                                    // Localización
                                    if (d.municipio_texto) {
                                        $('#municipio').val(d.municipio_texto).trigger('change');
                                        // Timeout para esperar que carguen las parroquias
                                        setTimeout(() => {
                                            if (d.parroquia_texto) {
                                                $('#parroquia').val(d.parroquia_texto).trigger('change');
                                            }
                                        }, 500);
                                    }

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Datos cargados',
                                        text: 'Se han completado los campos con la información existente del cliente.',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });

                                } else if (result.isDenied) {
                                    $el.val(prefijo).focus();
                                }
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error al verificar cédula:", error);
                    }
                });
            }
        });

        // 2. IP validation
        $('#ip, #ip_onu').on('input', function () {
            let val = $(this).val().replace(/[^0-9.]/g, '');
            let parts = val.split('.');
            for (let i = 0; i < parts.length; i++) {
                if (parts[i] !== '' && parseInt(parts[i]) > 255) parts[i] = '255';
                if (i >= 4) { parts.splice(4); break; }
            }
            $(this).val(parts.join('.'));
        });

        // 3. Nombre: solo letras y espacios
        $('#nombre_completo').on('input', function () {
            let val = $(this).val().replace(/[^A-Za-zñÑáéíóúÁÉÍÓÚ\s]/g, '');
            $(this).val(val);
        });

        // 4. MAC: hex, colon, dot, dash
        $('#mac_onu').on('input', function () {
            let val = $(this).val().toUpperCase().replace(/[^A-F0-9:.-]/g, '');
            $(this).val(val);
        });

        // 5. Potencias (dBm): numbers, dots, one leading dash
        $('#nap_tx_power, #onu_rx_power, #valor_conexion_dbm').on('input', function () {
            let val = $(this).val().replace(/[^0-9.-]/g, '');
            if (val.indexOf('-') > 0) val = val.substring(0, val.indexOf('-')) + val.substring(val.indexOf('-') + 1);
            $(this).val(val);
        });

        // 6. Image preview
        function setupPreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.addEventListener('change', function () {
                const preview = document.getElementById(previewId);
                preview.innerHTML = '';
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.maxWidth = '180px';
                        img.style.maxHeight = '180px';
                        img.style.borderRadius = '6px';
                        img.style.border = '1px solid #dee2e6';
                        img.style.marginTop = '8px';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        setupPreview('evidencia_foto', 'preview_foto');
        setupPreview('foto_documento', 'preview_documento');
    </script>

    <!-- ═══════════════════════════════════════════ -->
    <!-- MAIN LOGIC (jQuery ready)                  -->
    <!-- ═══════════════════════════════════════════ -->
    <script>
        $(document).ready(function () {
            $('#id_plan').on('change', function () {
                const selectedOption = $(this).find('option:selected');
                const monto = selectedOption.data('monto-plan');
                if (monto !== undefined) {
                    $('#monto_plan').val(monto);
                }
            });

            // === 1. CARGAR DATOS DINÁMICOS ===

            let ubicacionesData = [];
            $.get('../principal/api_ubicaciones.php', function (data) {
                ubicacionesData = data;
                let options = '<option value="">-- Seleccione un Municipio --</option>';
                ubicacionesData.forEach(function (item) {
                    options += `<option value="${item.municipio}">${item.municipio}</option>`;
                });
                $('#municipio').html(options);
            });

            $.get('../principal/json_personal_api.php?action=get_planes_prorrateo', function (data) {
                let options = '<option value="">-- Seleccione un Plan --</option>';
                if (data && data.length > 0) {
                    data.forEach(function (p) {
                        options += `<option value="${p.nombre}" data-precio="${p.precio}">${p.nombre} - $${p.precio}</option>`;
                    });
                } else {
                    options = '<option value="">Sin planes registrados</option>';
                }
                $('#plan_prorrateo').html(options);
            });

            $.get('../principal/json_personal_api.php?action=get_vendedores', function (data) {
                let options = '<option value="">-- Seleccione un Vendedor --</option>';
                if (data && data.length > 0) {
                    data.forEach(function (v) {
                        options += `<option value="${v}">${v}</option>`;
                    });
                } else {
                    options = '<option value="">Sin vendedores registrados</option>';
                }
                $('#vendedor_texto').html(options);
            });

            // === 2. CASCADAS (Municipio->Parroquia, OLT->PON) ===

            $('#municipio').on('change', function () {
                const munNombre = $(this).val();
                let options = '<option value="">-- Seleccione --</option>';
                if (munNombre) {
                    const municipioObj = ubicacionesData.find(m => m.municipio === munNombre);
                    if (municipioObj && municipioObj.parroquias) {
                        municipioObj.parroquias.forEach(function (p) {
                            options += `<option value="${p.nombre}">${p.nombre}</option>`;
                        });
                        $('#parroquia').html(options).prop('disabled', false);
                    } else {
                        $('#parroquia').html('<option value="">No hay parroquias</option>').prop('disabled', true);
                    }
                } else {
                    $('#parroquia').html('<option value="">-- Primero seleccione un municipio --</option>').prop('disabled', true);
                }
            });

            function cargarPons(idOlt) {
                var $ponSelect = $('#id_pon');
                $ponSelect.html('<option value="">Cargando PONs...</option>').prop('disabled', true);
                if (idOlt) {
                    $.ajax({
                        url: '../principal/gets_pon_by_olt.php',
                        type: 'GET',
                        data: { id_olt: idOlt },
                        dataType: 'json',
                        success: function (response) {
                            $ponSelect.empty();
                            if (!response.error && response.pons && response.pons.length > 0) {
                                $ponSelect.append('<option value="">-- Seleccione un PON --</option>');
                                $.each(response.pons, function (index, pon) {
                                    $ponSelect.append('<option value="' + pon.id_pon + '">' + pon.nombre_pon + '</option>');
                                });
                                $ponSelect.prop('disabled', false);
                            } else {
                                $ponSelect.append('<option value="" disabled>' + (response.message || 'No se encontraron PONs.') + '</option>');
                            }
                        },
                        error: function () {
                            $ponSelect.html('<option value="" disabled>Error de comunicación al cargar PONs.</option>');
                        }
                    });
                } else {
                    $ponSelect.html('<option value="">-- Seleccione una OLT primero --</option>');
                }
            }

            $('#id_olt').on('change', function () {
                cargarPons($(this).val());
            });

            // === 3. TIPO DE CONEXIÓN (FTTH/RADIO) ===

            $('#tipo_conexion').on('change', function () {
                var tipo = $(this).val();
                // Ocultar todos primero, quitar required y desactivar para evitar errores de validación en campos ocultos
                $('.campo-ftth, .campo-radio').hide();
                $('.campo-ftth :input, .campo-radio :input').prop('required', false).prop('disabled', true);

                if (tipo === 'FTTH') {
                    $('.campo-ftth').show();
                    $('.campo-ftth :input').prop('disabled', false); // Habilitar campos FTTH
                    $('#mac_onu, #ip_onu, #ident_caja_nap, #puerto_nap, #nap_tx_power, #onu_rx_power, #distancia_drop, #num_presinto_odn').prop('required', true);
                    
                    // Si la IP está vacía, restaurar el prefijo por defecto
                    if ($('#ip_onu').val() === '') {
                        $('#ip_onu').val('192.168.');
                    }
                } else if (tipo === 'RADIO') {
                    $('.campo-radio').show();
                    $('.campo-radio :input').prop('disabled', false); // Habilitar campos RADIO
                    $('#ip, #punto_acceso, #valor_conexion_dbm').prop('required', true);
                    
                    // Limpiar IP de la ONU para que no dé error de "pattern" si estaba a medias
                    if ($('#ip_onu').val() === '192.168.') {
                        $('#ip_onu').val('');
                    }
                }
            }).trigger('change');

            // === 4. PRORRATEO Y TOTALES ===

            $('#incluye_prorrateo').on('change', function () {
                if ($(this).is(':checked')) {
                    $('#contenedor_prorrateo').slideDown();
                } else {
                    $('#contenedor_prorrateo').slideUp();
                    $('#plan_prorrateo').val('');
                    $('#dias_prorrateo').val('0');
                    $('#monto_prorrateo_usd').val('0.00');
                    calcularTotal();
                }
            });

            $('#plan_prorrateo, #dias_prorrateo').on('change input', function () {
                calcularProrrateo();
                calcularTotal();
            });

            $('#gastos_adicionales, #monto_instalacion').on('input', function () {
                calcularTotal();
            });

            function calcularProrrateo() {
                var selected = $('#plan_prorrateo option:selected');
                var montoPlan = parseFloat(selected.data('precio')) || 0;
                var diasProrrateo = parseInt($('#dias_prorrateo').val()) || 0;
                var prorrateo = (montoPlan / 30) * diasProrrateo;
                $('#monto_prorrateo_usd').val(prorrateo.toFixed(2));
            }

            function calcularTotal() {
                var instalacion = parseFloat($('#monto_instalacion').val()) || 0;
                var adicionales = parseFloat($('#gastos_adicionales').val()) || 0;
                var prorrateo = parseFloat($('#monto_prorrateo_usd').val()) || 0;
                var total = instalacion + adicionales + prorrateo;
                $('#monto_pagar').val(total.toFixed(2));
                calcularSaldo();
            }

            $('#monto_pagado').on('input', function () {
                calcularSaldo();
            });

            // === 5. CONVERSIÓN DE MONEDA Y SALDO ===

            let tasaBCV = 0;
            $.get('../principal/get_tasa_dolar.php', function (data) {
                if (data.success) {
                    tasaBCV = data.promedio;
                }
            });

            const mPagado = $('#monto_pagado');
            const mMoneda = $('#moneda_pago');
            const mMedio = $('#medio_pago');

            function cargarMediosDePago() {
                $.get('../principal/json_bancos_api.php?action=get&limit=100', function (response) {
                    if (response && response.data) {
                        mMedio.empty().append('<option value="">-- Seleccione --</option>');
                        response.data.forEach(banco => {
                            if (banco.metodos_pago && Array.isArray(banco.metodos_pago)) {
                                banco.metodos_pago.forEach(metodo => {
                                    mMedio.append(`<option value="${metodo}">${banco.nombre_banco} - ${metodo}</option>`);
                                });
                            }
                        });
                    }
                });
            }

            mMoneda.on('change', function () {
                const moneda = $(this).val();
                const monto = parseFloat(mPagado.val()) || 0;
                if (tasaBCV > 0 && monto > 0) {
                    if (moneda === 'BS') {
                        mPagado.val((monto * tasaBCV).toFixed(2));
                    } else {
                        mPagado.val((monto / tasaBCV).toFixed(2));
                    }
                }
                calcularSaldo();
            });

            // Cargar medios al inicio
            cargarMediosDePago();

            function calcularSaldo() {
                var total = parseFloat($('#monto_pagar').val()) || 0;
                var pagado = parseFloat(mPagado.val()) || 0;
                var moneda = mMoneda.val();
                var pagadoUSD = pagado;

                if (moneda === 'BS' && tasaBCV > 0) {
                    pagadoUSD = pagado / tasaBCV;
                }

                if (pagadoUSD > (total + 0.01)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Monto Excedido',
                        text: 'El monto pagado no puede ser mayor al total a pagar ($' + total.toFixed(2) + ').',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    if (moneda === 'BS' && tasaBCV > 0) {
                        mPagado.val((total * tasaBCV).toFixed(2));
                    } else {
                        mPagado.val(total.toFixed(2));
                    }
                    calcularSaldo();
                    return;
                }

                var saldo = (total - pagadoUSD).toFixed(2);
                saldo = parseFloat(saldo);

                if (saldo > 0) {
                    $('#saldo_pendiente').val(saldo.toFixed(2));
                    $('#div_saldo_pendiente').fadeIn();
                } else {
                    $('#div_saldo_pendiente').fadeOut();
                    $('#saldo_pendiente').val("0.00");
                }
            }

            // Initial trigger
            calcularProrrateo();
            calcularTotal();

            // === 6. ENVÍO DEL FORMULARIO ===

            $('#formContrato').on('submit', function (e) {
                e.preventDefault();
                const form = this;

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const cedula = $('#cedula').val();
                const nombre = $('#nombre_completo').val();
                const plan = $('#id_plan option:selected').text();
                const montoTotal = $('#monto_pagar').val();
                const montoPagado = mPagado.val();
                const moneda = mMoneda.val();
                const medio = mMedio.val();
                const tipoConex = $('#tipo_conexion').val();
                const tecnico = (tipoConex === 'FTTH') ? $('#instalador_ftth').val() : $('#instalador_radio').val();

                const htmlResumen = `
                    <div class="text-start">
                        <table class="table table-sm table-bordered mt-2">
                            <tbody>
                                <tr><th class="bg-light">Cédula:</th><td>${cedula}</td></tr>
                                <tr><th class="bg-light">Titular:</th><td>${nombre}</td></tr>
                                <tr><th class="bg-light">Plan:</th><td>${plan}</td></tr>
                                <tr><th class="bg-light">Monto Total:</th><td>$${montoTotal}</td></tr>
                                <tr><th class="bg-light">Monto Pagado:</th><td>${montoPagado} ${moneda}</td></tr>
                                <tr><th class="bg-light">Medio de Pago:</th><td>${medio}</td></tr>
                                <tr><th class="bg-light">Instalador:</th><td>${tecnico || 'No asignado'}</td></tr>
                            </tbody>
                        </table>
                        <p class="text-center fw-bold text-primary mt-3">¿Desea registrar este contrato con estos datos?</p>
                    </div>
                `;

                Swal.fire({
                    title: 'Confirmar Registro',
                    html: htmlResumen,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, Registrar',
                    cancelButtonText: 'Cancelar y Verificar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Capturar firmas
                        if (window.padCliente && !window.padCliente.isEmpty()) {
                            $('#firma_cliente_data').val(window.padCliente.toDataURL());
                        }
                        if (window.padTecnico && !window.padTecnico.isEmpty()) {
                            $('#firma_tecnico_data').val(window.padTecnico.toDataURL());
                        }

                        Swal.fire({
                            title: 'Procesando...',
                            text: 'Guardando registro.',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form)
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    window.lastSavedId = data.id;
                                    mostrarOpcionesExito(data.id);
                                } else {
                                    Swal.fire({
                                        title: 'Error al Guardar',
                                        html: `<div class="text-danger fw-bold">${data.msg}</div>`,
                                        icon: 'error'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error(error);
                                Swal.fire('Error', 'Error de conexión con el servidor', 'error');
                            });
                    }
                });
            });

        }); // end document.ready

        // === 7. OPCIONES POST-GUARDADO ===

        function mostrarOpcionesExito(id) {
            Swal.fire({
                title: '¡Contrato Registrado!',
                text: 'El contrato fue guardado correctamente.',
                icon: 'success',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#198754',
                denyButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-link"></i> Generar Link de Firma',
                denyButtonText: '<i class="fa-solid fa-plus"></i> Nuevo Registro',
                cancelButtonText: 'Cerrar',
                allowOutsideClick: false
            }).then((res) => {
                if (res.isConfirmed) {
                    generarLink(id);
                } else if (res.isDenied) {
                    location.reload();
                }
            });
        }

        function generarLink(idContrato) {
            Swal.fire({
                title: 'Generando...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.post('../principal/generar_token_firma.php', { id: idContrato }, function (resp) {
                if (resp.success) {
                    const baseUrl = window.location.origin + window.location.pathname.split('/paginas/')[0] + '/paginas/soporte/firmar_remoto.php';
                    const link = `${baseUrl}?token=${resp.token}&type=contrato`;
                    document.getElementById('linkInput').value = link;

                    const telefono = document.getElementById('telefono').value.replace(/\D/g, '');
                    const mensaje = encodeURIComponent(`Hola, por favor firma tu contrato de servicio en el siguiente enlace: ${link}`);
                    document.getElementById('btnWhatsapp').href = `https://wa.me/${telefono}?text=${mensaje}`;

                    Swal.close();
                    new bootstrap.Modal(document.getElementById('modalLink')).show();
                } else {
                    Swal.fire('Error', resp.message || 'Error al generar el link', 'error');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            });
        }

        function copiarLink() {
            const linkInput = document.getElementById("linkInput");
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(linkInput.value).then(() => {
                Swal.fire({ icon: 'success', title: '¡Copiado!', text: 'Enlace copiado al portapapeles', timer: 1500, showConfirmButton: false });
            });
        }
    </script>

    <!-- intl-tel-input JS -->
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.4/build/js/intlTelInput.min.js"></script>
    <script>
        const telInput1 = document.querySelector("#telefono");
        const telInput2 = document.querySelector("#telefono_secundario");

        window.iti1 = window.intlTelInput(telInput1, {
            initialCountry: "ve",
            separateDialCode: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.4/build/js/utils.js",
        });

        window.iti2 = window.intlTelInput(telInput2, {
            initialCountry: "ve",
            separateDialCode: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.4/build/js/utils.js",
        });

        const handlePhoneZeroCorrection = (input, iti) => {
            input.addEventListener('input', () => {
                let val = input.value;
                const data = iti.getSelectedCountryData();
                if (data.iso2 === 've' && val.startsWith('0')) {
                    input.value = val.substring(1);
                }
            });
        };

        handlePhoneZeroCorrection(telInput1, iti1);
        handlePhoneZeroCorrection(telInput2, iti2);

        // Block negative sign in numeric fields
        [document.querySelector("#monto_instalacion"), document.querySelector("#dias_prorrateo")].forEach(el => {
            if (el) {
                el.addEventListener('keydown', (e) => {
                    if (e.key === '-' || e.key === 'e') e.preventDefault();
                });
                el.addEventListener('input', () => {
                    if (el.value < 0) el.value = 0;
                });
            }
        });
    </script>

</body>

</html>