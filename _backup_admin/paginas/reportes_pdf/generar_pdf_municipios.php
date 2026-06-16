<?php
// Carga el autoloader de Composer desde la carpeta superior
require '../../dompdf/autoload.inc.php';

// Importa las clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Incluye el archivo de conexión a la base de datos
require_once '../conexion.php';

// ---------------------------------------------------------------------------------
// AÑADIR: INCLUIR EL ENCABEZADO REUTILIZABLE
// ---------------------------------------------------------------------------------
require_once 'encabezado_reporte.php'; 

// Consulta para obtener todos los municipios y sus parroquias
$sql = "SELECT m.nombre_municipio, p.nombre_parroquia 
        FROM `municipio` m
        LEFT JOIN `parroquia` p ON m.id_municipio = p.id_municipio
        ORDER BY m.nombre_municipio ASC, p.nombre_parroquia ASC";
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
    <title>Reporte de Municipios y Parroquias</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        /* Se remueve h1 { text-align: center; } ya que el encabezado lo maneja */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #047df7ff; padding: 8px; text-align: left; }
        th { background-color: #8fd0ffff; }
        .municipio-row td { background-color: #abf5ffff; font-weight: bold; }
        .parroquia-row { padding-left: 20px !important; }
    </style>
</head>
<body>';

// ---------------------------------------------------------------------------------
// REEMPLAZAR: USAR LA FUNCIÓN DEL ENCABEZADO
// ---------------------------------------------------------------------------------
$html .= generar_encabezado_empresa('Reporte de Municipios y Parroquias');

// Continuación de la tabla
$html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Municipio</th>
                <th style="width: 50%;">Parroquia</th>
            </tr>
        </thead>
        <tbody>';

$current_municipio = null;
if (!empty($data)) {
    foreach ($data as $row) {
        // Encabezado del municipio (solo si es diferente al anterior)
        if ($current_municipio !== $row['nombre_municipio']) {
            $html .= '<tr class="municipio-row">';
            $html .= '<td colspan="2">' . htmlspecialchars($row['nombre_municipio']) . '</td>';
            $html .= '</tr>';
            $current_municipio = $row['nombre_municipio'];
        }
        // Agrega una fila para la parroquia
        if ($row['nombre_parroquia']) {
            $html .= '<tr>';
            $html .= '<td></td>';
            $html .= '<td class="parroquia-row" >' . htmlspecialchars($row['nombre_parroquia']) . '</td>';
            $html .= '</tr>';
        }
    }
} else {
    $html .= '<tr><td colspan="2">No se encontraron registros.</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// Instancia y configura Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Carga el HTML en Dompdf
$dompdf->loadHtml($html);

// Configura el tamaño y la orientación del papel
$dompdf->setPaper('A4', 'portrait');

// Renderiza el HTML como PDF
$dompdf->render();

// Comprueba si el parámetro 'accion' existe y...
$dompdf->stream("reporte_municipios.pdf", ["Attachment" => false]);
exit(0);