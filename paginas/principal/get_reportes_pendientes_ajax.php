<?php
// get_reportes_pendientes_ajax.php - Retorna fragmento HTML con las filas de reportes pendientes
require_once '../conexion.php';

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

if ($resultado && $resultado->num_rows > 0): ?>
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
                <?php if ($row['id_contrato_asociado'] > 0): ?>
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
                <a href="../../<?php echo $row['capture_path']; ?>" target="_blank" class="btn btn-sm btn-glass text-info">
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
                        onclick="confirmarRechazo(<?php echo $row['id_reporte']; ?>)" title="Rechazar">
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
<?php
$conn->close();
?>