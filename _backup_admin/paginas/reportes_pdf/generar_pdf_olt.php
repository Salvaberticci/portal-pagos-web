<?php
// generar_pdf_olt.php

// Muestra todos los errores de PHP para una mejor depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carga el autoloader de Dompdf
require '../../dompdf/vendor/autoload.php';

// Importa las clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Incluye el archivo de conexión a la base de datos y el encabezado
require_once '../conexion.php';
require_once 'encabezado_reporte.php'; 

// Consulta para obtener todas las OLTs y sus parroquias asociadas.
// Utilizamos GROUP_CONCAT para listar todas las parroquias por cada OLT.
$sql = "SELECT 
            o.id_olt, 
            o.nombre_olt, 
            o.marca,
            o.modelo,
            o.descripcion,
            GROUP_CONCAT(pa.nombre_parroquia ORDER BY pa.nombre_parroquia SEPARATOR ', ') AS parroquias_atendidas
        FROM olt o
        LEFT JOIN olt_parroquia op ON o.id_olt = op.olt_id
        LEFT JOIN parroquia pa ON op.parroquia_id = pa.id_parroquia
        GROUP BY o.id_olt
        ORDER BY o.nombre_olt ASC";

$result = $conn->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
$conn->close();

// Construye el HTML para el PDF
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/jpg" href="../../images/logo.jpg"/>
    <meta charset="UTF-8">
    <title>Reporte de OLTs</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        /* Ajustamos el padding y la alineación para listas largas */
        th, td { border: 1px solid #59acffff; padding: 6px; text-align: left; vertical-align: top; } 
        th { background-color: #8fd0ffff; }
    </style>
</head>
<body>';

// Usamos la función de encabezado que ya tienes
$html .= generar_encabezado_empresa('Reporte de OLTs (Terminales de Línea Óptica)');

// Continuación de la tabla
$html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 15%;">Nombre</th>
                <th style="width: 10%;">Marca</th>
                <th style="width: 10%;">Modelo</th>
                <th style="width: 40%;">Parroquias Atendidas</th>
                <th style="width: 20%;">Descripción</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($data)) {
    foreach ($data as $row) {
        $parroquias = htmlspecialchars($row['parroquias_atendidas'] ?: 'Ninguna');
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_olt']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nombre_olt']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['marca']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['modelo']) . '</td>';
        $html .= '<td>' . $parroquias . '</td>';
        $html .= '<td>' . htmlspecialchars($row['descripcion']) . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="6">No se encontraron OLTs registradas.</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// Instancia y configura Dompdf
$options = new Options();
$dompdf = new Dompdf($options);

// Carga el HTML en Dompdf
$dompdf->loadHtml($html);

// Configura el tamaño y la orientación del papel: CAMBIADO A HORIZONTAL
$dompdf->setPaper('A4', 'landscape'); 

// Renderiza el HTML como PDF
$dompdf->render();

// Envía el PDF al navegador
$dompdf->stream("reporte_olts.pdf", ["Attachment" => false]);
exit(0);