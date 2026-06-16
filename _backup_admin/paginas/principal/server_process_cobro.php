<?php
// ¡AÑADE ESTO PARA DEBUGGING!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// FIN DE DEBUGGING

// FORZAR QUE EL NAVEGADOR ESPERE JSON, Y EVITAR CUALQUIER SALIDA ANTES.
header('Content-Type: application/json; charset=utf-8');

/**
 * Script para cargar datos del lado del servidor en DataTables para la gestión de Cobros.
 */

require '../conexion.php';

// 1. Definición de la tabla principal y las uniones (JOINs)
$sTabla = "
    cuentas_por_cobrar cxc
    JOIN contratos co ON cxc.id_contrato = co.id
";

// 2. Definición de las columnas
// Nota: La columna 6 es para 'es_manual' (Calculada) y la 7 es para las Acciones.
$aColumnas = [
    'cxc.id_cobro',
    'co.nombre_completo',    // Cliente (columna 1)
    'cxc.fecha_emision',
    'cxc.fecha_vencimiento',
    'cxc.monto_total',
    'cxc.estado',
    // La columna 'es_manual' no se añade aquí directamente ya que es una subconsulta.
    // La añadiremos manualmente al SELECT y la manejaremos en el bucle de resultado.
    'co.id' // Necesario para el botón de Historial (columna 6)
];
$sIndexColumn = "cxc.id_cobro";

// Definición de las columnas que DataTables puede buscar y ordenar:
$aSearchColumns = [
    'cxc.id_cobro',
    'co.nombre_completo',
    'cxc.fecha_emision',
    'cxc.fecha_vencimiento',
    'cxc.monto_total',
    'cxc.estado'
];

// ----------------------------------------------------------------------------------
// LÓGICA DE PAGINACIÓN, ORDENAMIENTO Y BÚSQUEDA (IDÉNTICA A server_process.php)
// ----------------------------------------------------------------------------------

$sLimit = '';
$start = $_GET['iDisplayStart'] ?? 0;
$length = $_GET['iDisplayLength'] ?? -1;

$sLimit = '';
if ($length != -1) {
    $sLimit = "LIMIT {$start}, {$length}";
}

$sOrder = '';
// 1. Manejar el ordenamiento enviado por DataTables (si existe)
if (isset($_GET['iSortCol_0'])) {
    $sOrder = 'ORDER BY ';
    $sortingCols = intval($_GET['iSortingCols'] ?? 0);
    
    // Recorrer las columnas que DataTables quiere ordenar
    for ($i = 0; $i < $sortingCols; $i++) {
        $colIndex = intval($_GET["iSortCol_{$i}"] ?? -1);
        $bSortable = $_GET["bSortable_{$i}"] ?? "false";
        
        // Usamos $aSearchColumns para el ordenamiento
        if ($colIndex >= 0 && $colIndex < count($aSearchColumns) && $bSortable == "true") {
            $sortDir = $_GET["sSortDir_{$i}"] ?? "asc";
            // ¡Importante! Usar la columna real de la DB, no el índice.
            $sOrder .= "{$aSearchColumns[$colIndex]} {$sortDir}, ";
        }
    }
}

// 2. Agregar el ordenamiento por prioridad de ESTADO (siempre debe ir)
$orden_estado = "
    CASE cxc.estado 
        WHEN 'PENDIENTE' THEN 1 
        WHEN 'VENCIDO' THEN 2 
        ELSE 3 
    END ASC, 
    cxc.fecha_vencimiento ASC
";

// 3. Combinar las cláusulas
if ($sOrder === 'ORDER BY ') {
    // Si no hubo ordenamiento de DataTables, solo usamos el orden por estado
    $sOrder = "ORDER BY " . $orden_estado;
} elseif ($sOrder !== '') {
    // Si hubo ordenamiento de DataTables, añadimos el orden por estado al final
    // Quitamos la última coma del orden de DataTables
    $sOrder = rtrim($sOrder, ', ');
    
    // Si DataTables ordenó por ESTADO, no lo repetimos. Si no, lo añadimos.
    // Para simplificar, siempre lo añadimos para asegurar la prioridad
    $sOrder .= ", " . $orden_estado;
} else {
     // Caso en que no hay ningún ordenamiento (debería ser el mismo que el segundo if)
     $sOrder = "ORDER BY " . $orden_estado;
}

$searchConditions = [];
$searchValue = $_GET['sSearch'] ?? ''; 
if ($searchValue != "") {
    // Buscar globalmente en las columnas que definimos como buscables
    foreach ($aSearchColumns as $column) {
        $searchConditions[] = "$column LIKE '%" . $conn->real_escape_string($searchValue) . "%'";
    }
}

$individualSearchConditions = [];
for ($i = 0; $i < intval($_GET['iColumns'] ?? 0); $i++) {
    $isSearchable = $_GET['bSearchable_' . $i] ?? 'false';
    $sSearchTerm = $_GET['sSearch_' . $i] ?? '';
    
    // Aplicar búsqueda individual solo a las columnas buscables ($aSearchColumns)
    if ($i < count($aSearchColumns) && $isSearchable == 'true' && $sSearchTerm != '') {
        $individualSearchConditions[] = "{$aSearchColumns[$i]} LIKE '%{$conn->real_escape_string($sSearchTerm)}%'";
    }
}

$whereConditions = [];
if (!empty($searchConditions)) {
    // La búsqueda global busca en todas las columnas (OR). 
    $whereConditions[] = "(" . implode(' OR ', $searchConditions) . ")";
}

// La búsqueda individual se añade con AND
if (!empty($individualSearchConditions)) {
    $whereConditions[] = "(" . implode(' AND ', $individualSearchConditions) . ")";
}

$sWhere = '';
if (!empty($whereConditions)) {
    $sWhere = "WHERE " . implode(' AND ', $whereConditions);
}

// 3. Construcción de la consulta SELECT
// Añadimos la subconsulta de 'es_manual' al SELECT
$sSelect = implode(', ', $aColumnas) . ",
    (SELECT COUNT(h.id) FROM cobros_manuales_historial h WHERE h.id_cobro_cxc = cxc.id_cobro) AS es_manual";

$sQuery = "
    SELECT SQL_CALC_FOUND_ROWS {$sSelect}
    FROM $sTabla
    $sWhere
    $sOrder
    $sLimit
";
$rResult = $conn->query($sQuery);


// 4. Conteo de Registros (Filtrado y Total)
$rResultFilterTotal = $conn->query("SELECT FOUND_ROWS()");
$iFilteredTotal = $rResultFilterTotal->fetch_array()[0];
// CÓDIGO CORREGIDO: Usa el nombre de columna real sin alias ni variables.
$rResultTotal = $conn->query("SELECT COUNT(id_cobro) FROM cuentas_por_cobrar");
$iTotal = $rResultTotal->fetch_array()[0];

$output = [
    "sEcho" => intval($_GET['sEcho'] ?? 0), 
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => []
];

// 5. Procesamiento de Resultados
while ($aRow = $rResult->fetch_assoc()) {
    $row = [];
    $id_cobro = htmlspecialchars($aRow['id_cobro']);
    $id_contrato = htmlspecialchars($aRow['id']); // id del contrato
    $estado = htmlspecialchars($aRow['estado']);
    $monto_total = htmlspecialchars($aRow['monto_total']);
    $es_manual = $aRow['es_manual'];

    // Columna 0: Factura ID + Badge Manual
    $factura_id_html = $id_cobro;
    if ($es_manual > 0) {
        $factura_id_html .= ' <span class="badge bg-info text-dark" title="Cargo generado manualmente">
                                <i class="fas fa-pencil-alt"></i> Manual
                              </span>';
    }
    $row[] = $factura_id_html;
    
    // Columna 1: Cliente
    $row[] = htmlspecialchars($aRow['nombre_completo']); 
    
    // Columna 2: Emisión (Formato de Fecha)
    $row[] = date('d/m/Y', strtotime($aRow['fecha_emision']));
    
    // Columna 3: Vencimiento (Formato de Fecha)
    $row[] = date('d/m/Y', strtotime($aRow['fecha_vencimiento']));
    
    // Columna 4: Monto
    $row[] = '$' . number_format($monto_total, 2);
    
    // Columna 5: Estado (Badge)
    $badge_class = 'warning'; // PENDIENTE por defecto
    if ($estado == 'PAGADO') {
        $badge_class = 'success';
    } elseif ($estado == 'VENCIDO') {
        $badge_class = 'danger';
    }
    $row[] = '<span class="badge bg-' . $badge_class . '">' . $estado . '</span>';
    
    // Columna 6: Acciones (Botones)
    $acciones_html = '';

    // Modificar
    $acciones_html .= '<a href="modifica_cobro1.php?id=' . $id_cobro . '" class="btn btn-sm btn-warning me-2" title="Modificar Cobro"><i class="fas fa-edit"></i></a>';

    // Historial (usa id_contrato)
    $acciones_html .= '<a href="historial_pagos.php?id=' . $id_contrato . '" class="btn btn-sm btn-info me-2" title="Ver Historial de Pagos"><i class="fas fa-history"></i></a>';
    
    // Justificación (solo si es manual)
    if ($es_manual > 0) {
        $acciones_html .= '<a href="acceder_historial.php?id_cobro=' . $id_cobro . '" class="btn btn-sm btn-dark me-2" title="Ver Justificación y Autorización"><i class="fas fa-eye"></i></a>';
    }

    // Botón ELIMINAR (Nuevo) - Usa modal de confirmación
    // Solo permitir eliminar si el estado es PENDIENTE o VENCIDO
    if ($estado != 'PAGADO') {
        $acciones_html .= '<button type="button" class="btn btn-sm btn-danger me-2" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalEliminar" 
                            data-id="' . $id_cobro . '"
                            data-nombre="' . htmlspecialchars($aRow['nombre_completo']) . '"
                            title="Eliminar Cuenta por Cobrar">
                            <i class="fas fa-trash-alt"></i>
                        </button>';
    }

    // Registrar Pago
    if ($estado == 'PENDIENTE' || $estado == 'VENCIDO') {
        // Los data-* atributos son importantes para el modal
        $acciones_html .= '<button type="button" class="btn btn-sm btn-success" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalPagar" 
                            data-id="' . $id_cobro . '"
                            data-monto="' . $monto_total . '"
                            data-nombre="' . htmlspecialchars($aRow['nombre_completo']) . '">
                            <i class="fas fa-money-bill-wave"></i> Pagar
                        </button>';
    } else {
        $acciones_html .= '<button class="btn btn-sm btn-secondary" disabled>Pagado</button>';
    }

    $row[] = $acciones_html;

    $output['aaData'][] = $row;
}

echo json_encode($output, JSON_UNESCAPED_UNICODE);

