<?php
// Muestra todos los errores de PHP para una mejor depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carga el autoloader de Dompdf, retrocediendo dos directorios
require '../../dompdf/vendor/autoload.php';

// Importa las clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Incluye el archivo de conexión a la base de datos
require_once '../conexion.php';

// ---------------------------------------------------------------------------------
// AÑADIR: INCLUIR EL ENCABEZADO REUTILIZABLE
// ---------------------------------------------------------------------------------
require_once 'encabezado_reporte.php';

// Consulta para obtener todos los vendedores desde el JSON
$json_path = '../principal/data/vendedores.json';
$data = [];
if (file_exists($json_path)) {
    $data = json_decode(file_get_contents($json_path), true) ?: [];
}
$conn->close();

// Construye el HTML para el PDF
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/jpg" href="../../images/logo.jpg"/>
    <meta charset="UTF-8">
    <title>Reporte de Vendedores</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        /* Se remueve h1 { text-align: center; } ya que el encabezado lo maneja */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #59acffff; padding: 8px; text-align: left; }
        th { background-color: #8fd0ffff; }
    </style>
</head>
<body>';

// ---------------------------------------------------------------------------------
// REEMPLAZAR: USAR LA FUNCIÓN DEL ENCABEZADO
// ---------------------------------------------------------------------------------
$html .= generar_encabezado_empresa('Reporte de Vendedores');

// Continuación de la tabla
$html .= '
    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>Nombre del Vendedor</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($data)) {
    $contador = 1;
    foreach ($data as $vendedor) {
        $html .= '<tr>';
        $html .= '<td>' . $contador++ . '</td>';
        $html .= '<td>' . htmlspecialchars($vendedor) . '</td>';
        $html .= '</tr>';
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
$dompdf = new Dompdf($options);

// Carga el HTML en Dompdf
$dompdf->loadHtml($html);

// Configura el tamaño y la orientación del papel
$dompdf->setPaper('A4', 'portrait');

// Renderiza el HTML como PDF
$dompdf->render();

// Envía el PDF al navegador
$dompdf->stream("reporte_vendedores.pdf", ["Attachment" => false]);
exit(0);