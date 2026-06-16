<?php
// Asegúrate de que esta ruta sea correcta
require_once '../../dompdf/autoload.inc.php';
require_once '../conexion.php';
require_once 'encabezado_reporte.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ----------------------------------------------------------------------
// 1. CAPTURA Y SANEO DE PARÁMETROS (IDÉNTICO A reporte_cobranza.php)
// ----------------------------------------------------------------------
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'TODOS';
$sae_plus_filtro = isset($_GET['estado_sae_plus']) ? $_GET['estado_sae_plus'] : 'CARGADO';
$params = [];

$types = '';
$where_clause = " WHERE 1=1 ";
$cobros = [];
$total_monto = 0;

if ($estado_filtro !== 'TODOS') {
    $where_clause .= " AND cxc.estado = ? ";
    $params[] = $estado_filtro;
    $types .= 's';
}

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    // Usamos fecha_emision para un filtrado general
    $where_clause .= " AND cxc.fecha_emision >= ? AND cxc.fecha_emision <= ? ";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
    $types .= 'ss';
}

if ($sae_plus_filtro !== 'TODOS') {
    $where_clause .= " AND cxc.estado_sae_plus = ? ";
    $params[] = $sae_plus_filtro;
    $types .= 's';
}


// 2. CONSULTA SQL
$sql = "
    SELECT 
        cxc.id_cobro, 
        cxc.fecha_emision, 
        cxc.fecha_vencimiento, 
        cxc.monto_total, 
        cxc.estado,
        co.nombre_completo AS cliente,
        DATEDIFF(CURRENT_DATE(), cxc.fecha_vencimiento) AS dias_vencido
    FROM cuentas_por_cobrar cxc
    JOIN contratos co ON cxc.id_contrato = co.id
    " . $where_clause . "
    ORDER BY cxc.fecha_emision DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        // Enlaza los parámetros dinámicamente
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $cobros[] = $fila;
        $total_monto += $fila['monto_total'];
    }
    $stmt->close();
}

// *** CÁLCULO DE LA CANTIDAD DE CLIENTES/COBROS ***
$cantidad_clientes = count($cobros);
// ----------------------------------------------------------------------
// 3. CAPTURA DEL HTML PARA DOMPDF
// ----------------------------------------------------------------------

// Inicia la captura del buffer de salida
ob_start();
?>

<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="icon" type="image/jpg" href="../../images/logo.jpg" />
    <?php
    // Llamada a la función para obtener el encabezado estandarizado
    echo generar_encabezado_empresa('Reporte Filtrado de Cuentas por Cobrar');
    ?>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
        }

        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .resumen {
            margin-bottom: 20px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #59acffff;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background-color: #8fd0ffff;
            text-align: center;
            font-size: 10px;
        }

        .total {
            font-weight: bold;
        }

        .danger {
            color: red;
            font-weight: bold;
        }

        .success {
            color: green;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }
    </style>
</head>

<body>


    <p class="resumen">
        <strong>Filtros:</strong> Estado: <?php echo htmlspecialchars($estado_filtro); ?> | Rango:
        <?php echo htmlspecialchars($fecha_inicio); ?> al <?php echo htmlspecialchars($fecha_fin); ?><br>
        <strong>CANTIDAD DE CLIENTES:</strong> <?php echo $cantidad_clientes; ?><br>
        <strong>TOTAL MONTO REPORTADO:</strong> $<?php echo number_format($total_monto, 2); ?>
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 30%;">CLIENTE</th>
                <th style="width: 15%;">EMISIÓN</th>
                <th style="width: 15%;">VENCIMIENTO</th>
                <th style="width: 10%;">DÍAS</th>
                <th style="width: 15%;">MONTO</th>
                <th style="width: 10%;">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cobros as $fila): ?>
                <tr>
                    <td class="center"><?php echo htmlspecialchars($fila['id_cobro']); ?></td>
                    <td><?php echo htmlspecialchars($fila['cliente']); ?></td>
                    <td class="center"><?php echo htmlspecialchars($fila['fecha_emision']); ?></td>
                    <td class="center"><?php echo htmlspecialchars($fila['fecha_vencimiento']); ?></td>
                    <td class="center">
                        <?php
                        $dias = '';
                        $class = '';
                        if ($fila['estado'] !== 'PAGADO' && $fila['dias_vencido'] > 0) {
                            $dias = $fila['dias_vencido'];
                            $class = 'danger';
                        } else {
                            $dias = '-';
                        }
                        echo "<span class='{$class}'>{$dias}</span>";
                        ?>
                    </td>
                    <td class="right">$<?php echo number_format($fila['monto_total'], 2); ?></td>
                    <td class="center">
                        <span class="<?php echo ($fila['estado'] == 'PAGADO') ? 'success' : (($fila['estado'] == 'RECHAZADO') ? 'muted' : 'danger'); ?>">
                            <?php echo htmlspecialchars($fila['estado']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>

</html>

<?php
// Captura el contenido del buffer
$html = ob_get_clean();

// 4. CONFIGURACIÓN Y GENERACIÓN DEL PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8'); // Es crucial usar UTF-8 para que las tildes funcionen
$dompdf->setPaper('letter', 'portrait');

// Renderiza el HTML a PDF
$dompdf->render();

// Envía el PDF al navegador para descarga
$dompdf->stream("Reporte_Cobranza_" . date('Ymd') . ".pdf", array("Attachment" => false));

?>