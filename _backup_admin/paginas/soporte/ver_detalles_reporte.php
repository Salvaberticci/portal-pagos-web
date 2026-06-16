<?php
/**
 * Ver Detalles Completos del Reporte Técnico
 */
$path_to_root = "../../";
$page_title = "Detalles del Reporte";
$breadcrumb = ["Soporte", "Gestión de Fallas"];
$back_url = "gestion_fallas.php";
require_once $path_to_root . 'paginas/conexion.php';
require_once $path_to_root . 'paginas/includes/layout_head.php';
require_once $path_to_root . 'paginas/includes/sidebar.php';

$id_soporte = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_soporte == 0) {
    header('Location: gestion_fallas.php');
    exit;
}
// Consultar datos completos del reporte
$sql = "SELECT s.*, c.nombre_completo, c.cedula, c.ip_onu as ip, c.direccion, c.telefono,
               o.nombre_olt, p.nombre_pon
        FROM soportes s 
        LEFT JOIN contratos c ON s.id_contrato = c.id 
        LEFT JOIN olt o ON s.id_olt = o.id_olt
        LEFT JOIN pon p ON s.id_pon = p.id_pon
        WHERE s.id_soporte = $id_soporte";

$result = $conn->query($sql);
if ($result->num_rows == 0) {
    header('Location: gestion_fallas.php');
    exit;
}

$reporte = $result->fetch_assoc();
$saldo = $reporte['monto_total'] - $reporte['monto_pagado'];
?>

<style>
    .detail-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .detail-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 0.875rem;
    }

    .detail-value {
        font-size: 1rem;
        color: #212529;
    }

    .signature-box {
        border: 2px dashed #dee2e6;
        padding: 10px;
        text-align: center;
        background: white;
        border-radius: 8px;
        min-height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .signature-box img {
        max-width: 100%;
        max-height: 140px;
    }

    .badge-nivel1 {
        background-color: #ffff00;
        color: #000;
    }

    .badge-nivel2 {
        background-color: #fd7e14;
        color: white;
    }

    .badge-nivel3 {
        background-color: #dc3545;
        color: white;
    }
</style>

<main class="main-content">
    <?php include $path_to_root . 'paginas/includes/header.php'; ?>

    <div class="page-content">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h3 fw-bold text-primary mb-1">
                        Detalles del Reporte #
                        <?php echo $reporte['id_soporte']; ?>
                    </h2>
                    <p class="text-muted">Información completa del servicio técnico</p>
                </div>
                <div>
                    <a href="gestion_fallas.php" class="btn btn-outline-secondary me-2">
                        <i class="fa-solid fa-arrow-left me-1"></i>Volver
                    </a>
                    <a href="generar_pdf_reporte.php?id=<?php echo $id_soporte; ?>" target="_blank"
                        class="btn btn-danger">
                        <i class="fa-solid fa-file-pdf me-1"></i>Exportar PDF
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Columna Izquierda -->
            <div class="col-lg-6">
                <!-- Información del Cliente -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-user me-2"></i>Información del Cliente</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Nombre:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['nombre_completo'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Cédula:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['cedula'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">IP Asignada:</div>
                            <div class="col-8 detail-value"><code class="text-pink"><?php echo htmlspecialchars($reporte['ip_address'] ?? '—'); ?></code>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Teléfono:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['telefono'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4 detail-label">Dirección:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['direccion'] ?? '—'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información de la Visita -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-calendar-check me-2"></i>Detalles de la Visita</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Fecha:</div>
                            <div class="col-8 detail-value">
                                <?php echo $reporte['fecha_soporte'] ? date('d/m/Y', strtotime($reporte['fecha_soporte'])) : '—'; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Hora:</div>
                            <div class="col-8 detail-value">
                                <?php echo $reporte['hora_solucion'] ? date('h:i A', strtotime($reporte['hora_solucion'])) : '—'; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Tiempo:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['tiempo_transcurrido'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Técnico:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['tecnico_asignado'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Sector:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['sector'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">OLT:</div>
                            <div class="col-8 detail-value">
                                <span class="badge bg-dark"><?php echo htmlspecialchars($reporte['nombre_olt'] ?? '—'); ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">PON:</div>
                            <div class="col-8 detail-value">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($reporte['nombre_pon'] ?? '—'); ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Tipo Falla:</div>
                            <div class="col-8 detail-value">
                                <?php echo htmlspecialchars($reporte['tipo_falla'] ?? '—'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4 detail-label">Prioridad:</div>
                            <div class="col-8 detail-value">
                                <?php
                                $p = strtoupper($reporte['prioridad'] ?? 'NIVEL 1');
                                if ($p === 'NIVEL 1')
                                    echo '<span class="badge badge-nivel1"><i class="fa-brands fa-whatsapp me-1"></i>NIVEL 1</span>';
                                else if ($p === 'NIVEL 2')
                                    echo '<span class="badge badge-nivel2"><i class="fa-solid fa-house-chimney-user me-1"></i>NIVEL 2</span>';
                                else if ($p === 'NIVEL 3')
                                    echo '<span class="badge badge-nivel3"><i class="fa-solid fa-network-wired me-1"></i>NIVEL 3</span>';
                                else
                                    echo '<span class="badge bg-secondary">' . $p . '</span>';
                                ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4 detail-label">Caída Crítica:</div>
                            <div class="col-8 detail-value">
                                <?php echo ($reporte['es_caida_critica'] == 1) ? '<span class="text-danger fw-bold">Sí</span>' : 'No'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Financiera -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-dollar-sign me-2"></i>Información Financiera</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6 detail-label">Total:</div>
                            <div class="col-6 detail-value text-end">
                                <strong class="text-primary">$
                                    <?php echo number_format($reporte['monto_total'], 2); ?>
                                </strong>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6 detail-label">Pagado:</div>
                            <div class="col-6 detail-value text-end">
                                <strong class="text-success">$
                                    <?php echo number_format($reporte['monto_pagado'], 2); ?>
                                </strong>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6 detail-label">Saldo:</div>
                            <div class="col-6 detail-value text-end">
                                <strong class="<?php echo $saldo > 0.01 ? 'text-danger' : 'text-success'; ?>">
                                    $
                                    <?php echo number_format($saldo, 2); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha -->
            <div class="col-lg-6">
                <!-- Diagnóstico Técnico -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-warning">
                        <h6 class="mb-0"><i class="fa-solid fa-tools me-2"></i>Diagnóstico</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-6 detail-label">ONU:</div>
                            <div class="col-6 detail-value"><span class="badge bg-info">
                                    <?php echo htmlspecialchars($reporte['estado_onu'] ?? '—'); ?>
                                </span></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 detail-label">Router:</div>
                            <div class="col-6 detail-value"><span class="badge bg-info">
                                    <?php echo htmlspecialchars($reporte['estado_router'] ?? '—'); ?>
                                </span></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6 detail-label">Modelo:</div>
                            <div class="col-6 detail-value">
                                <?php echo htmlspecialchars($reporte['modelo_router'] ?? '—'); ?>
                            </div>
                        </div>

                        <hr>
                        <div class="row mb-2">
                            <div class="col-12 detail-label">BW: <span class="text-dark">↓<?php echo htmlspecialchars($reporte['bw_bajada'] ?? '0'); ?> / ↑<?php echo htmlspecialchars($reporte['bw_subida'] ?? '0'); ?> / Ping:<?php echo htmlspecialchars($reporte['bw_ping'] ?? '0'); ?></span></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6 detail-label">Dispositivos:</div>
                            <div class="col-6 detail-value">
                                <?php echo htmlspecialchars($reporte['num_dispositivos'] ?? '0'); ?>
                            </div>
                        </div>

                        <?php if ($reporte['tipo_servicio'] === 'RADIO' || !empty($reporte['estado_antena'])): ?>
                            <hr>
                            <h6 class="text-muted mb-3">Antena (Solo Radio)</h6>
                            <div class="row mb-2">
                                <div class="col-6 detail-label">Estado:</div>
                                <div class="col-6 detail-value">
                                    <?php echo htmlspecialchars($reporte['estado_antena'] ?? '—'); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 detail-label">Valores:</div>
                                <div class="col-6 detail-value">
                                    <?php echo htmlspecialchars($reporte['valores_antena'] ?? '—'); ?> dBm
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-clipboard me-2"></i>Observaciones</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="detail-value">
                                <?php echo !empty($reporte['observaciones']) ? nl2br(htmlspecialchars($reporte['observaciones'])) : '—'; ?>
                            </p>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" <?php echo $reporte['solucion_completada'] ? 'checked' : ''; ?> disabled>
                            <label class="form-check-label fw-bold">
                                Solucionada: <span class="badge <?php echo $reporte['solucion_completada'] ? 'bg-success' : 'bg-danger'; ?>"><?php echo $reporte['solucion_completada'] ? 'SÍ' : 'NO'; ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Firmas -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><i class="fa-solid fa-pen-nib me-2"></i>Firmas Digitales</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6 class="text-center detail-label mb-2">Firma del Técnico</h6>
                                <div class="signature-box">
                                    <?php if (!empty($reporte['firma_tecnico'])): ?>
                                        <img src="<?php echo $path_to_root; ?>uploads/firmas/<?php echo $reporte['firma_tecnico']; ?>"
                                            alt="Firma Técnico">
                                    <?php else: ?>
                                        <span class="text-muted">Sin firma</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <h6 class="text-center detail-label mb-2">Firma del Cliente</h6>
                                <div class="signature-box">
                                    <?php if (!empty($reporte['firma_cliente'])): ?>
                                        <img src="<?php echo $path_to_root; ?>uploads/firmas/<?php echo $reporte['firma_cliente']; ?>"
                                            alt="Firma Cliente">
                                    <?php else: ?>
                                        <span class="text-muted">Sin firma</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once $path_to_root . 'paginas/includes/layout_foot.php'; ?>
/layout_foot.php'; ?>