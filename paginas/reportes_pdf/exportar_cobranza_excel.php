<?php
/**
 * Archivo: exportar_cobranza_excel.php
 * Propósito: Exportar reporte de cobranzas a Excel con múltiples pestañas y estilos premium.
 */

require_once '../conexion.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// 1. CAPTURA DE FILTROS (Mismo que reporte_cobranza.php)
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : 'TODOS';
$banco_filtro = isset($_GET['id_banco']) ? $_GET['id_banco'] : '';
$origen_filtro = isset($_GET['origen']) ? $_GET['origen'] : '';
$ref_filtro = isset($_GET['referencia']) ? $_GET['referencia'] : '';
$plan_filtro = isset($_GET['id_plan']) ? $_GET['id_plan'] : '';
$sae_plus_filtro = isset($_GET['estado_sae_plus']) ? $_GET['estado_sae_plus'] : 'TODOS';
$mes_cobrado = isset($_GET['mes_cobrado']) ? $_GET['mes_cobrado'] : '';

// 2. CONSTRUCCIÓN DE LA CONSULTA
$where_clause = " WHERE 1=1 ";
$params = [];
$types = '';

if ($estado_filtro !== 'TODOS') {
    $where_clause .= " AND cxc.estado = ? ";
    $params[] = $estado_filtro; $types .= 's';
}
if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $where_clause .= " AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) >= ? AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) <= ? ";
    $params[] = $fecha_inicio; $params[] = $fecha_fin; $types .= 'ss';
}
if (!empty($mes_cobrado)) {
    // Mapeo de meses en español a números para el fallback de fecha_emision
    $mesesMapNum = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12
    ];
    $numMes = isset($mesesMapNum[$mes_cobrado]) ? $mesesMapNum[$mes_cobrado] : 0;

    $where_clause .= " AND (
        cxc.id_cobro IN (SELECT id_cobro_cxc FROM cobros_manuales_historial WHERE justificacion LIKE ?)
        OR 
        (
            NOT EXISTS (SELECT 1 FROM cobros_manuales_historial WHERE id_cobro_cxc = cxc.id_cobro) 
            AND MONTH(cxc.fecha_emision) = ?
        )
    )";
    $params[] = "%[$mes_cobrado]%";
    $params[] = $numMes;
    $types .= 'si';
}
if (!empty($banco_filtro)) {
    $where_clause .= " AND cxc.id_banco = ? ";
    $params[] = $banco_filtro; $types .= 'i';
}
if (!empty($origen_filtro)) {
    $where_clause .= " AND cxc.origen = ? ";
    $params[] = $origen_filtro; $types .= 's';
}
if (!empty($ref_filtro)) {
    $where_clause .= " AND cxc.referencia_pago LIKE ? ";
    $params[] = "%$ref_filtro%"; $types .= 's';
}
if (!empty($plan_filtro)) {
    $where_clause .= " AND co.id_plan = ? ";
    $params[] = $plan_filtro; $types .= 'i';
}
if ($sae_plus_filtro !== 'TODOS') {
    $where_clause .= " AND cxc.estado_sae_plus = ? ";
    $params[] = $sae_plus_filtro; $types .= 's';
}

$sql = "
    SELECT 
        cxc.id_cobro, 
        cxc.id_contrato,
        cxc.estado_sae_plus,
        cxc.fecha_emision, 
        cxc.fecha_vencimiento, 
        cxc.fecha_pago,
        cxc.monto_total, 
        cxc.monto_total_bs,
        cxc.tasa_bcv,
        cxc.estado,
        cxc.referencia_pago,
        cxc.origen,
        cxc.id_plan_cobrado,
        co.nombre_completo AS cliente,
        co.cedula,
        co.sae_plus,
        p.nombre_plan,
        b.nombre_banco,
        cxc.id_banco
    FROM cuentas_por_cobrar cxc
    JOIN contratos co ON cxc.id_contrato = co.id
    LEFT JOIN planes p ON co.id_plan = p.id_plan
    LEFT JOIN bancos b ON cxc.id_banco = b.id_banco
    " . $where_clause . "
    ORDER BY cxc.fecha_pago DESC, cxc.fecha_emision DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();
$data = $resultado->fetch_all(MYSQLI_ASSOC);

// 3. INICIALIZAR EXCEL
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0); // Eliminar la hoja por defecto

// Definir mapeo de hojas basado en id_banco
$sheet_mapping = [
    '4' => 'GALANET',
    '5' => 'SUPPLY',
    '6' => 'WIRELESS',
    '7' => 'ZELLE',
    '9' => 'BDV',
    '10' => 'DIVISAS',
    '11' => 'EFECTIVO'
];

$sheets = [];

// Función para crear hoja con estilos
function createSheet(Spreadsheet $spreadsheet, $title) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(substr($title, 0, 31));
    
    global $mes_cobrado;
    $mes_display = !empty($mes_cobrado) ? strtoupper($mes_cobrado) : strtoupper(date('F'));
    // Título Superior
    $sheet->mergeCells('A1:J1');
    $sheet->setCellValue('A1', $title . " - Reporte de Cobranzas " . date('Y'));
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    
    $sheet->setCellValue('K1', $mes_display);
    $sheet->getStyle('K1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Cabeceras
    $headers = ["CEDULA", "CLIENTE", "PLAN", "FECHA", "REFERENCIA", "ESTADO", "MONTO ($)", "MONTO (BS)", "TASA BCV", "SAE PLUS"];
    $sheet->fromArray($headers, NULL, 'A2');
    
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E4D2B']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A2:J2')->applyFromArray($headerStyle);
    
    // Ajustar anchos
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(40);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(20);
    $sheet->getColumnDimension('I')->setWidth(15);
    $sheet->getColumnDimension('J')->setWidth(15);
    
    return $sheet;
}

// Crear hojas principales
foreach ($sheet_mapping as $id => $name) {
    $sheets[$id] = createSheet($spreadsheet, $name);
    $sheets[$id]->row_count = 3; // Empezar en fila 3
}

// Hojas extras
$sheet_mensualidad = createSheet($spreadsheet, 'MENSUALIDAD');
$sheet_mensualidad->row_count = 3;
$sheet_instalaciones = createSheet($spreadsheet, 'INSTALACIONES-EQUIPOS');
$sheet_instalaciones->row_count = 3;

// 4. DISTRIBUIR DATOS
foreach ($data as $row) {
    $monto_usd = (float)$row['monto_total'];
    $tasa = (float)$row['tasa_bcv'];
    $monto_bs = !empty($row['monto_total_bs']) ? (float)$row['monto_total_bs'] : ($monto_usd * $tasa);
    
    $rowData = [
        $row['cedula'],
        $row['cliente'],
        $row['nombre_plan'],
        !empty($row['fecha_pago']) ? date('d/m/Y', strtotime($row['fecha_pago'])) : date('d/m/Y', strtotime($row['fecha_emision'])),
        $row['referencia_pago'],
        $row['estado'],
        $monto_usd,
        $monto_bs,
        $tasa,
        $row['sae_plus']
    ];
    
    // Distribuir por Banco
    if (isset($sheets[$row['id_banco']])) {
        $sheet = $sheets[$row['id_banco']];
        $sheet->fromArray($rowData, NULL, 'A' . $sheet->row_count);
        $sheet->row_count++;
    }
    
    // Distribuir por Categoría (Mensualidad vs Instalación)
    if (!empty($row['id_plan_cobrado'])) {
        $sheet_mensualidad->fromArray($rowData, NULL, 'A' . $sheet_mensualidad->row_count);
        $sheet_mensualidad->row_count++;
    } else {
        $sheet_instalaciones->fromArray($rowData, NULL, 'A' . $sheet_instalaciones->row_count);
        $sheet_instalaciones->row_count++;
    }
}

// Aplicar estilos de tabla y formatos numéricos a todas las hojas
foreach ($spreadsheet->getAllSheets() as $sheet) {
    $lastRow = (isset($sheet->row_count)) ? $sheet->row_count - 1 : 2;
    if ($lastRow >= 3) {
        $sheet->getStyle('A3:J' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('G3:I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Estilos condicionales para Estado
        for ($i = 3; $i <= $lastRow; $i++) {
            $estado = $sheet->getCell('F' . $i)->getValue();
            $color = 'FF6C757D'; // Gris por defecto
            if ($estado === 'PAGADO') $color = 'FF157347';
            elseif ($estado === 'PENDIENTE') $color = 'FFD39E00';
            elseif ($estado === 'RECHAZADO') $color = 'FFBB2D3B';
            
            $sheet->getStyle('F' . $i)->getFont()->setBold(true)->getColor()->setARGB($color);
        }
    }
}

// 5. SALIDA
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Cobranza_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
