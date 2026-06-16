<?php
// paginas/soporte/server_process_soportes.php

// 1. Configuración de errores para debugging (puedes comentar esto en producción si prefieres)
ini_set('display_errors', 0); // No mostrar errores en la salida para no romper el JSON
error_reporting(E_ALL);

// 2. Header para JSON
header('Content-Type: application/json; charset=utf-8');

require_once '../conexion.php';

// Definición de columnas para la SQL
// Índices: 0=>s.id_soporte, 1=>s.fecha_soporte, ...
$aColumns = [
    's.id_soporte', 
    's.fecha_soporte', 
    'c.nombre_completo', 
    's.descripcion', 
    's.tecnico_asignado', 
    's.monto_total', 
    's.monto_pagado'
];

// Columnas para búsqueda (Searchable)
$aSearchColumns = [
    's.id_soporte', 
    'c.nombre_completo', 
    's.descripcion', 
    's.tecnico_asignado'
];

$sTable = "soportes s";
$sJoin = " JOIN contratos c ON s.id_contrato = c.id ";

// =================================================================================
// 1. Paginación
// =================================================================================
$sLimit = "";
if (isset($_GET['iDisplayStart']) && $_GET['iDisplayLength'] != '-1') {
    $start = intval($_GET['iDisplayStart']);
    $length = intval($_GET['iDisplayLength']);
    $sLimit = "LIMIT $start, $length";
}

// =================================================================================
// 2. Ordenamiento
// =================================================================================
$sOrder = "";
if (isset($_GET['iSortCol_0'])) {
    $sOrder = "ORDER BY  ";
    $sortingCols = intval($_GET['iSortingCols']);
    for ($i = 0; $i < $sortingCols; $i++) {
        $sortColIndex = intval($_GET['iSortCol_' . $i]);
        $sortable = $_GET['bSortable_' . $sortColIndex];
        
        if ($sortable == "true") {
            $sortDir = $_GET['sSortDir_' . $i] === 'asc' ? 'asc' : 'desc';
            // Mapeo directo del índice de columna a la columna SQL
            if (isset($aColumns[$sortColIndex])) {
                $sOrder .= $aColumns[$sortColIndex] . " " . $sortDir . ", ";
            }
        }
    }
    $sOrder = substr_replace($sOrder, "", -2);
    if ($sOrder == "ORDER BY") {
        $sOrder = "";
    }
}

// =================================================================================
// 3. Filtrado
// =================================================================================
$sWhere = "";
$searchTerms = isset($_GET['sSearch']) ? $_GET['sSearch'] : '';
if ($searchTerms != "") {
    $sWhere = "WHERE (";
    foreach ($aSearchColumns as $col) {
        $sWhere .= $col . " LIKE '%" . $conn->real_escape_string($searchTerms) . "%' OR ";
    }
    $sWhere = substr_replace($sWhere, "", -3);
    $sWhere .= ')';
}

// =================================================================================
// 4. Ejecución de la Consulta
// =================================================================================
$sSelect = "SELECT SQL_CALC_FOUND_ROWS " . implode(", ", $aColumns);
$sQuery = "
    $sSelect
    FROM   $sTable
    $sJoin
    $sWhere
    $sOrder
    $sLimit
";

$rResult = $conn->query($sQuery);

// Total de registros filtrados
$sQueryFoundRows = "SELECT FOUND_ROWS()";
$rResultFilterTotal = $conn->query($sQueryFoundRows);
$aResultFilterTotal = $rResultFilterTotal->fetch_row();
$iFilteredTotal = $aResultFilterTotal[0];

// Total de registros en la tabla
$sQueryTotal = "SELECT COUNT(s.id_soporte) FROM soportes s";
$rResultTotal = $conn->query($sQueryTotal);
$aResultTotal = $rResultTotal->fetch_row();
$iTotal = $aResultTotal[0];

// =================================================================================
// 5. Construcción de la Salida
// =================================================================================
$output = array(
    "sEcho" => intval($_GET['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => array()
);

while ($aRow = $rResult->fetch_assoc()) {
    $row = array();
    
    // IMPORTANTE: DataTables espera un array indexado numéricamente para cada fila 
    // en el mismo orden que las columnas definidas en el JS.
    
    // 0: ID
    $row[] = $aRow['id_soporte'];
    
    // 1: Fecha (Formateada)
    $row[] = date('d/m/Y', strtotime($aRow['fecha_soporte']));
    
    // 2: Cliente
    $row[] = htmlspecialchars($aRow['nombre_completo']);
    
    // 3: Descripción
    $row[] = htmlspecialchars($aRow['descripcion']);
    
    // 4: Técnico
    $row[] = htmlspecialchars($aRow['tecnico_asignado']);
    
    // 5: Monto Total (Raw para el render en JS, o formateado)
    $row[] = $aRow['monto_total']; 
    
    // 6: Monto Pagado (Raw)
    $row[] = $aRow['monto_pagado'];
    
    // 7: Estado (Calculado en JS, pero podemos mandar un flag o nulo)
    $row[] = null; 

    $output['aaData'][] = $row;
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);
?>
