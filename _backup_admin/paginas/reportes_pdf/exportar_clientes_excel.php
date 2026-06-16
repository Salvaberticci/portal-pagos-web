<?php
// Archivo: exportar_clientes_excel.php

// Incluye tu conexión a la base de datos
require_once '../conexion.php';

// -------------------------------------------------------------------------
// 1. CAPTURA Y CONSTRUCCIÓN DE LA CLÁUSULA WHERE
// -------------------------------------------------------------------------

// Captura de parámetros
$id_municipio_filtro = isset($_GET['municipio']) ? $_GET['municipio'] : 'TODOS';
$id_parroquia_filtro = isset($_GET['parroquia']) ? $_GET['parroquia'] : 'TODOS';
$estado_contrato_filtro = isset($_GET['estado_contrato']) ? $_GET['estado_contrato'] : 'TODOS';
$vendedor_texto_filtro = isset($_GET['vendedor']) ? $_GET['vendedor'] : 'TODOS';
$id_plan_filtro = isset($_GET['plan']) ? $_GET['plan'] : 'TODOS';
$cobros_estado_filtro = isset($_GET['estado_cobros']) ? $_GET['estado_cobros'] : 'TODOS';
// --- NUEVOS FILTROS OLT Y PON ---
$id_olt_filtro = isset($_GET['olt']) ? $_GET['olt'] : 'TODOS';
$id_pon_filtro = isset($_GET['pon']) ? $_GET['pon'] : 'TODOS';
// --------------------------------

$where_clause = " WHERE 1=1 ";

// Cláusula JOIN completa, necesaria para obtener los nombres descriptivos
$join_clause = "
    LEFT JOIN municipio m ON c.id_municipio = m.id_municipio
    LEFT JOIN parroquia pa ON c.id_parroquia = pa.id_parroquia
    LEFT JOIN planes pl ON c.id_plan = pl.id_plan
    LEFT JOIN olt ol ON c.id_olt = ol.id_olt
    LEFT JOIN pon p ON c.id_pon = p.id_pon
";

// Aplicación de Filtros (utilizando real_escape_string para seguridad)
if ($id_municipio_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_municipio = '" . $conn->real_escape_string($id_municipio_filtro) . "'";
}
if ($id_parroquia_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_parroquia = '" . $conn->real_escape_string($id_parroquia_filtro) . "'";
}
if ($vendedor_texto_filtro !== 'TODOS') {
    $where_clause .= " AND c.vendedor_texto = '" . $conn->real_escape_string($vendedor_texto_filtro) . "'";
}
if ($id_plan_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_plan = '" . $conn->real_escape_string($id_plan_filtro) . "'";
}
if ($id_olt_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_olt = '" . $conn->real_escape_string($id_olt_filtro) . "'";
}
if ($id_pon_filtro !== 'TODOS') {
    $where_clause .= " AND c.id_pon = '" . $conn->real_escape_string($id_pon_filtro) . "'";
}

// Filtros de texto/estado
if ($estado_contrato_filtro !== 'TODOS') {
    $where_clause .= " AND c.estado = '" . $conn->real_escape_string($estado_contrato_filtro) . "'";
}
if ($cobros_estado_filtro !== 'TODOS') {
    // Requiere JOIN y condición en la tabla de cobros
    $join_clause .= " JOIN cuentas_por_cobrar cxc ON c.id = cxc.id_contrato ";
    $where_clause .= " AND cxc.estado = '" . $conn->real_escape_string($cobros_estado_filtro) . "'";
}

// -----------------------------------------------------------
// 2. CONSULTA SQL FINAL COMPLETA
// -----------------------------------------------------------

$query = "
    SELECT 
        c.id, 
        c.cedula, 
        c.nombre_completo, 
        c.telefono, 
        c.correo, 
        c.direccion, 
        c.fecha_instalacion, 
        c.estado,
        c.ip_onu,
        m.nombre_municipio,
        pa.nombre_parroquia,
        pl.nombre_plan,
        c.vendedor_texto AS nombre_vendedor,
        ol.nombre_olt, /* AÑADIDO */
        p.nombre_pon   /* AÑADIDO */
    FROM 
        contratos c
    {$join_clause}
    {$where_clause} 
    GROUP BY c.id 
    ORDER BY 
        c.id
";

$resultado = $conn->query($query);

if ($resultado === FALSE) {
    die("Error en la consulta SQL: " . $conn->error);
}

if ($resultado->num_rows === 0) {
    die("No hay clientes para exportar con los filtros seleccionados.");
}

// -------------------------------------------------------------------------
// 3. Cabeceras y Generación del Contenido CSV
// -------------------------------------------------------------------------

$fecha_actual = date('Ymd_His');
$nombre_archivo = "Reporte_Clientes_Filtros_" . $fecha_actual . ".csv";

// Estas cabeceras fuerzan la descarga del archivo
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

$output = fopen('php://output', 'w');

// Escribe los encabezados (Añadidos campos descriptivos, OLT y PON)
fputcsv($output, [
    'ID',
    'CEDULA',
    'NOMBRE COMPLETO',
    'TELEFONO',
    'CORREO',
    'DIRECCION',
    'FECHA INSTALACION',
    'ESTADO CONTRATO',
    'IP',
    'MUNICIPIO',
    'PARROQUIA',
    'PLAN',
    'VENDEDOR',
    'OLT',
    'PON'
], ';');

// Escribe los datos de cada fila
while ($fila = $resultado->fetch_assoc()) {
    fputcsv($output, [
        $fila['id'],
        $fila['cedula'],
        $fila['nombre_completo'],
        $fila['telefono'],
        $fila['correo'],
        $fila['direccion'],
        $fila['fecha_instalacion'],
        $fila['estado'],
        $fila['ip_onu'],
        $fila['nombre_municipio'],
        $fila['nombre_parroquia'],
        $fila['nombre_plan'],
        $fila['nombre_vendedor'],
        $fila['nombre_olt'] ?? '',
        $fila['nombre_pon'] ?? ''
    ], ';');
}

fclose($output);
exit;
?>