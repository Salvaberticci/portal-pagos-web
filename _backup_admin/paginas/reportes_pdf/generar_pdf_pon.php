<?php
// generar_pdf_pon.php

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

// Consulta SIMPLIFICADA para obtener todos los PONs (eliminando comunidades).
$sql = "SELECT 
            p.id_pon, 
            p.nombre_pon, 
            p.descripcion
        FROM pon p
        ORDER BY p.nombre_pon ASC";

$result = $conn->query($sql);

// La línea 35 del error original ya no existe, el error se corregía con la simplificación de la consulta.
// La nueva línea 35 sería: '$data = [];' (o similar, dependiendo del formato exacto del código).

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
    <title>Reporte de PONs</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        /* Ajustamos el padding y la alineación */
        th, td { border: 1px solid #59acffff; padding: 8px; text-align: left; vertical-align: top; } 
        th { background-color: #8fd0ffff; }
    </style>
</head>
<body>';

// Usamos la función de encabezado que ya tienes
$html .= generar_encabezado_empresa('Reporte de PONs (Puntos de Distribución)');

// Continuación de la tabla
$html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">ID</th>
                <th style="width: 30%;">Nombre del PON</th>
                <th style="width: 60%;">Descripción</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($data)) {
    foreach ($data as $row) {
        // Se utilizan solo las columnas necesarias
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_pon']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nombre_pon']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['descripcion']) . '</td>';
        $html .= '</tr>';
    }
} else {
    // La colspan se ajusta a 3 columnas
    $html .= '<tr><td colspan="3">No se encontraron PONs registrados.</td></tr>';
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

// Configura el tamaño y la orientación del papel
$dompdf->setPaper('A4', 'portrait');

// Renderiza el HTML como PDF
$dompdf->render();

// Envía el PDF al navegador
$dompdf->stream("reporte_pons.pdf", ["Attachment" => false]);
exit(0);
// Fin del archivo