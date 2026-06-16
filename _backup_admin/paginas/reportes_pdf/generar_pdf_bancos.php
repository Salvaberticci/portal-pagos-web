<?php
// Carga el autoloader de Dompdf, retrocediendo dos directorios
require '../../dompdf/autoload.inc.php';

// Importa las clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Incluye el archivo de conexión a la base de datos
require_once '../conexion.php';

// ---------------------------------------------------------------------------------
// 1. INCLUIR EL ENCABEZADO REUTILIZABLE
// Ajustamos la ruta asumiendo que está en el mismo nivel que conexion.php (../)
// ---------------------------------------------------------------------------------
require_once 'encabezado_reporte.php'; 


// Consulta para obtener todos los bancos
$sql = "SELECT * FROM bancos ORDER BY nombre_banco ASC";
$result = $conn->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
$conn->close();

// ---------------------------------------------------------------------------------
// 2. CONSTRUYE EL HTML PARA EL PDF (Usando la función del encabezado)
// ---------------------------------------------------------------------------------
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
   <link rel="icon" type="image/jpg" href="../../images/logo.jpg"/>
    <meta charset="UTF-8">
    <title>Reporte de Bancos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        /* La función generar_encabezado_empresa ya maneja el estilo del título,
           pero ajustamos las tablas y celdas */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #59acffff; padding: 8px; text-align: left; }
        th { background-color: #8fd0ffff; }
    </style>
</head>
<body>';

// ¡INYECCIÓN DEL ENCABEZADO!
$html .= generar_encabezado_empresa('Reporte de Bancos'); 

// Continuación de la tabla
$html .= '
    <table>
        <thead>
            <tr>
                <th>ID Banco</th>
                <th>Nombre Banco</th>
                <th>Número Cuenta</th>
                <th>Cédula Propietario</th>
                <th>Nombre Propietario</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($data)) {
    foreach ($data as $row) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_banco']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nombre_banco']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['numero_cuenta']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['cedula_propietario']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nombre_propietario']) . '</td>';
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="5">No se encontraron registros.</td></tr>';
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

// Envía el PDF al navegador
$dompdf->stream("reporte_bancos.pdf", ["Attachment" => false]);
exit(0);