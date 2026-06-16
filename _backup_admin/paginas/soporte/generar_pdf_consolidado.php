<?php
/**
 * Generar PDF Consolidado de Reportes Técnicos
 * Incluye estadísticas del período y tabla resumida de todos los reportes filtrados
 */

ini_set('memory_limit', '256M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

require_once '../conexion.php';

// Parámetros de filtro
$fecha_desde = isset($_GET['fecha_desde']) ? $conn->real_escape_string($_GET['fecha_desde']) : date('Y-m-d', strtotime('-1 month'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $conn->real_escape_string($_GET['fecha_hasta']) : date('Y-m-d');
$tipo_falla = isset($_GET['tipo_falla']) ? $conn->real_escape_string($_GET['tipo_falla']) : '';

// Construir WHERE clause
$where = "WHERE s.fecha_soporte BETWEEN '$fecha_desde' AND '$fecha_hasta'";
if (!empty($tipo_falla)) {
    $where .= " AND s.tipo_falla = '$tipo_falla'";
}
if (isset($_GET['filtro_prioridad']) && !empty($_GET['filtro_prioridad'])) {
    $prioridad = $conn->real_escape_string($_GET['filtro_prioridad']);
    $where .= " AND s.prioridad = '$prioridad'";
} elseif (isset($_GET['excluir_nivel_3']) && $_GET['excluir_nivel_3'] == '1') {
    $where .= " AND s.prioridad != 'NIVEL 3'";
} elseif (isset($_GET['solo_nivel_3']) && $_GET['solo_nivel_3'] == '1') {
    $where .= " AND s.prioridad = 'NIVEL 3'";
}

// Estadísticas generales
$sql_stats = "SELECT 
                COUNT(*) as total_reportes
              FROM soportes s $where";

$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();

// Reportes por tipo de falla
$sql_tipos = "SELECT tipo_falla, COUNT(*) as cantidad 
              FROM soportes s $where AND tipo_falla IS NOT NULL
              GROUP BY tipo_falla ORDER BY cantidad DESC LIMIT 10";
$result_tipos = $conn->query($sql_tipos);
$tipos_falla = [];
while ($row = $result_tipos->fetch_assoc()) {
    $tipos_falla[] = $row;
}

// Listado de reportes
$sql_reportes = "SELECT 
                    s.id_soporte,
                    DATE_FORMAT(s.fecha_soporte, '%d/%m/%Y') as fecha,
                    c.nombre_completo,
                     s.tipo_falla,
                     s.tecnico_asignado,
                     s.clientes_afectados,
                     s.zona_afectada,
                     s.solucion_completada,
                     s.tipo_servicio
                  FROM soportes s
                  INNER JOIN contratos c ON s.id_contrato = c.id
                  $where
                  ORDER BY s.fecha_soporte DESC";

$result_reportes = $conn->query($sql_reportes);
$conn->close();

// Construir HTML
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Consolidado de Fallas Técnicas</title>
    <style>
        body {
            font-family: "Helvetica", Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #333;
            margin: 15px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0d6efd;
            font-size: 18pt;
            margin: 0;
        }
        .header p {
            color: #666;
            margin: 3px 0;
        }
        .stats-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .stat-box {
            width: 23%;
            border: 2px solid #0d6efd;
            padding: 10px;
            text-align: center;
            background-color: #f8f9fa;
        }
        .stat-box .value {
            font-size: 16pt;
            font-weight: bold;
            color: #0d6efd;
        }
        .stat-box .label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
        }
        .section-title {
            background-color: #0d6efd;
            color: white;
            padding: 6px 10px;
            font-size: 11pt;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        th {
            background-color: #eaf1f8;
            color: #0d6efd;
            font-weight: bold;
            padding: 6px 4px;
            border: 1px solid #ddd;
            text-align: left;
        }
        td {
            padding: 4px;
            border: 1px solid #ddd;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }
        .badge-success { background-color: #198754; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>WIRELESS SUPPLY, C.A.</h1>
        <p>REPORTE CONSOLIDADO DE FALLAS TÉCNICAS</p>
        <p>Período: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)) . '</p>
        ' . (!empty($tipo_falla) ? '<p>Filtro: ' . htmlspecialchars($tipo_falla) . '</p>' : '') . '
    </div>

    <div class="section-title">LISTADO DETALLADO DE REPORTES</div>
    <table>
        <thead>
            <tr>
                <th style="width: 6%;">ID</th>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 22%;">Cliente / Referencia</th>
                <th style="width: 17%;">Tipo Falla</th>
                <th style="width: 15%;">Técnico</th>';

if (isset($_GET['filtro_prioridad']) && $_GET['filtro_prioridad'] == 'NIVEL 3') {
    $html .= '
                <th style="width: 10%;">C. Afectados</th>
                <th style="width: 15%;">Zona</th>';
} else {
    $html .= '
                <th style="width: 15%;">Zona</th>
                <th style="width: 10%;">Servicio</th>';
}

$html .= '
                <th style="width: 5%;">Estado</th>
            </tr>
        </thead>
        <tbody>';

while ($rep = $result_reportes->fetch_assoc()) {
    $html .= '
            <tr>
                <td class="text-center">' . $rep['id_soporte'] . '</td>
                <td>' . $rep['fecha'] . '</td>
                <td>' . htmlspecialchars(substr($rep['nombre_completo'], 0, 30)) . '</td>
                <td>' . htmlspecialchars(substr($rep['tipo_falla'], 0, 20)) . '</td>
                <td>' . htmlspecialchars(substr($rep['tecnico_asignado'], 0, 15)) . '</td>';
    
    if (isset($_GET['filtro_prioridad']) && $_GET['filtro_prioridad'] == 'NIVEL 3') {
        $html .= '<td class="text-center"><strong>' . intval($rep['clientes_afectados']) . '</strong></td>';
        $html .= '<td>' . htmlspecialchars(substr($rep['zona_afectada'] ?? 'N/A', 0, 15)) . '</td>';
    } else {
        $html .= '<td>' . htmlspecialchars(substr($rep['zona_afectada'] ?? 'N/A', 0, 15)) . '</td>';
        $html .= '<td class="text-center">' . htmlspecialchars($rep['tipo_servicio'] ?? 'N/A') . '</td>';
    }
    
    $html .= '
                <td class="text-center">' . ($rep['solucion_completada'] ? 'OK' : 'ACT') . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Wireless Supply, C.A. | Sistema de Gestión Técnica | Generado: ' . date('d/m/Y H:i') . '</p>
    </div>
</body>
</html>';

// Generar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Reporte_Consolidado_" . date('Ymd', strtotime($fecha_desde)) . "_" . date('Ymd', strtotime($fecha_hasta)) . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);
exit(0);
?>