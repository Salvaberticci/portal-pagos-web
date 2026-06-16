<?php
// Script para mostrar la justificación detallada de un cobro manual
require_once '../conexion.php';

$id_cobro = isset($_GET['id_cobro']) ? intval($_GET['id_cobro']) : 0;
$detalle = null;

if ($id_cobro > 0) {
    // Consulta para obtener el detalle y el cliente asociado
    $sql = "
        SELECT 
            h.autorizado_por, 
            h.justificacion, 
            h.fecha_creacion, 
            h.monto_cargado,
            cxc.fecha_emision,
            co.nombre_completo AS nombre_cliente,
            co.id AS id_contrato
        FROM cobros_manuales_historial h
        JOIN cuentas_por_cobrar cxc ON h.id_cobro_cxc = cxc.id_cobro
        JOIN contratos co ON cxc.id_contrato = co.id
        WHERE h.id_cobro_cxc = $id_cobro
    ";

    $resultado = $conn->query($sql);
    if ($resultado && $resultado->num_rows > 0) {
        $detalle = $resultado->fetch_assoc();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Cargo Manual #<?php echo $id_cobro; ?></title>
    <link href="../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="fas fa-search me-2"></i> Detalle de Cargo Manual (Factura
                    #<?php echo $id_cobro; ?>)</h4>
            </div>
            <div class="card-body">
                <?php if ($detalle): ?>
                    <h5 class="card-title text-primary"><?php echo htmlspecialchars($detalle['nombre_cliente']); ?>
                        (Contrato #<?php echo htmlspecialchars($detalle['id_contrato']); ?>)</h5>
                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Monto Cargado:</strong> <span
                                    class="badge bg-danger fs-5">$<?php echo number_format($detalle['monto_cargado'], 2); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Fecha de Creación:</strong>
                                <?php echo date('d/m/Y H:i', strtotime($detalle['fecha_creacion'])); ?></p>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Autorizado por:</strong> <span
                                    class="text-success"><?php echo htmlspecialchars($detalle['autorizado_por']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Fecha de Emisión CXC:</strong>
                                <?php echo htmlspecialchars($detalle['fecha_emision']); ?></p>
                        </div>
                    </div>

                    <h6>Justificación Detallada:</h6>
                    <div class="alert alert-light border p-3">
                        <?php echo nl2br(htmlspecialchars($detalle['justificacion'])); ?>
                    </div>

                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> No se encontró el registro de historial para la
                        Factura #<?php echo $id_cobro; ?>.
                        <p class="mt-2">Esto podría significar que el cobro no fue generado manualmente o la tabla de
                            historial no existe aún.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="gestion_mensualidades.php" class="btn btn-secondary">Volver a Cobros</a>
            </div>
        </div>
    </div>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>

</html>