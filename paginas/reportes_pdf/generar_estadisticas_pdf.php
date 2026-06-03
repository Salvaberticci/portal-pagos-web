<?php
// Muestra todos los errores de PHP para una mejor depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(300);


// Carga el autoloader de Dompdf
require '../../dompdf/vendor/autoload.php';

// Importa las clases de Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// Incluye conexión
require_once '../conexion.php';

// Incluye encabezado estándar
require_once 'encabezado_reporte.php';

// === CAPTURA DE FILTROS ===
$start_date = $_POST['start'] ?? $_GET['start'] ?? '';
$end_date = $_POST['end'] ?? $_GET['end'] ?? '';
$installer = $_POST['installer'] ?? $_GET['installer'] ?? '';
$vendor_id = $_POST['vendor'] ?? $_GET['vendor'] ?? '';
$contract_type = $_POST['type'] ?? $_GET['type'] ?? '';
$install_type = $_POST['install_type'] ?? $_GET['install_type'] ?? '';

// === CONSTRUCCIÓN DE QUERIES (Lógica sincronizada con Versión 4) ===
$where = [];
$params = [];
$types = "";

// Filtro Fechas
if (!empty($start_date) && !empty($end_date)) {
    $where[] = "fecha_instalacion BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
}

// Filtro Vendedor (Sincronizado con vendedor_texto)
if (!empty($vendor_id)) {
    $where[] = "vendedor_texto = ?";
    $params[] = $vendor_id;
    $types .= "s";
}

// Filtro Instalador (Exacto)
if (!empty($installer)) {
    $where[] = "instalador = ?";
    $params[] = $installer;
    $types .= "s";
}

// Filtro Tipo Conexión (tipo_conexion)
if (!empty($contract_type)) {
    $where[] = "tipo_conexion = ?";
    $params[] = $contract_type;
    $types .= "s";
}

// Filtro Tipo de Instalación (tipo_instalacion)
if (!empty($install_type)) {
    $where[] = "tipo_instalacion = ?";
    $params[] = $install_type;
    $types .= "s";
}

$sql_where = "";
if (count($where) > 0) {
    $sql_where = "WHERE " . implode(" AND ", $where);
}

// 1. Zonas de Ventas (Ubicación y Vendedor)
$sql_location = "SELECT 
                    COALESCE(p.nombre_parroquia, 'Sin Parroquia') as nombre_parroquia,
                    COALESCE(NULLIF(c.vendedor_texto, ''), 'Sin Asignar') as nombre_vendedor,
                    COUNT(*) as total
                 FROM contratos c
                 LEFT JOIN parroquia p ON c.id_parroquia = p.id_parroquia
                 $sql_where
                 GROUP BY nombre_parroquia, nombre_vendedor
                 ORDER BY total DESC";

// 2. Instalaciones por Tipo (tipo_instalacion)
$sql_type = "SELECT 
                    COALESCE(NULLIF(tipo_instalacion, ''), 'Sin Definir') as tipo, 
                    COUNT(*) as total 
                   FROM contratos 
                   $sql_where 
                   GROUP BY tipo 
                   ORDER BY total DESC";

// 3. Instalaciones por Fecha e Instalador (Fecha Instalación Completa)
// Filtramos fechas <= '1970-01-01' para limpiar ruido de registros mal migrados o nulos
$monthly_where = "WHERE (fecha_instalacion > '1970-01-01' OR fecha_instalacion IS NULL)";
if (count($where) > 0) {
    $monthly_where .= " AND " . implode(" AND ", $where);
}

$sql_monthly = "SELECT 
                    fecha_instalacion as fecha, 
                    COALESCE(NULLIF(instalador, ''), 'Sin Asignar') as nombre_instalador,
                    COUNT(*) as total 
                   FROM contratos 
                   $monthly_where 
                   GROUP BY fecha, nombre_instalador 
                   ORDER BY fecha ASC, total DESC";

// 4. Tipos de Conexión (tipo_conexion)
$sql_connection = "SELECT 
                    COALESCE(NULLIF(tipo_conexion, ''), 'Sin Definir') as conexion, 
                    COUNT(*) as total 
                   FROM contratos 
                   $sql_where 
                   GROUP BY conexion 
                   ORDER BY total DESC";

// 5. Instaladores
$sql_installers = "SELECT 
                    COALESCE(NULLIF(instalador, ''), 'Sin Asignar') as nombre, 
                    COUNT(*) as total 
                   FROM contratos 
                   $sql_where 
                   GROUP BY nombre 
                   ORDER BY total DESC";

// 6. Ventas (Vendedor)
$sql_vendors = "SELECT 
                    COALESCE(NULLIF(vendedor_texto, ''), 'Sin Asignar') as nombre_vendedor, 
                    COUNT(*) as total 
                   FROM contratos 
                   $sql_where 
                   GROUP BY nombre_vendedor 
                   ORDER BY total DESC";


// 0. Total Global de Instalaciones (para Fiabilidad SAE Plus)
$sql_total_global = "SELECT COUNT(*) as total FROM contratos $sql_where";
$total_global = 0;
$stmt = $conn->prepare($sql_total_global);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc())
        $total_global = (int) $row['total'];
    $stmt->close();
}

// Ejecutar Queries
$stats_location = [];
$stmt = $conn->prepare($sql_location);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $stats_location[] = ['ubicacion' => "{$row['nombre_parroquia']} - {$row['nombre_vendedor']}", 'total' => $row['total']];
    }
    $stmt->close();
}

$stats_type = [];
$stmt = $conn->prepare($sql_type);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc())
        $stats_type[] = $row;
    $stmt->close();
}

$stats_monthly = [];
$stmt = $conn->prepare($sql_monthly);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $fullDate = $row['fecha'];
        $formattedDate = formatDescriptiveDatePHP($fullDate);
        $stats_monthly[] = [
            'mes' => "{$formattedDate}<br><span style='font-size:8.5px; color:#6b7280;'>{$row['nombre_instalador']}</span>",
            'total' => $row['total']
        ];
    }
    $stmt->close();
}

$stats_connection = [];
$stmt = $conn->prepare($sql_connection);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc())
        $stats_connection[] = $row;
    $stmt->close();
}

$stats_sae = [];
$sql_sae = "SELECT 
                COALESCE(NULLIF(tipo_conexion, ''), 'Sin Definir') as conexion,
                CASE WHEN sae_plus IS NOT NULL AND sae_plus != '' THEN 'CARGADO' ELSE 'NO CARGADO' END as sae_status,
                COUNT(*) as total 
             FROM contratos 
             $sql_where 
             GROUP BY conexion, sae_status 
             ORDER BY conexion DESC, sae_status DESC";

$stmt = $conn->prepare($sql_sae);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $stats_sae[] = [
            'status' => "{$row['conexion']} ({$row['sae_status']})",
            'total' => $row['total']
        ];
    }
    $stmt->close();
}




$stats_installers = [];
$stmt = $conn->prepare($sql_installers);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc())
        $stats_installers[] = $row;
    $stmt->close();
}

$stats_vendors = [];
$stmt = $conn->prepare($sql_vendors);
if ($stmt) {
    if (!empty($types))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc())
        $stats_vendors[] = $row;
    $stmt->close();
}

$conn->close();

function formatDescriptiveDatePHP($dateStr)
{
    if (!$dateStr || $dateStr === '0000-00-00' || $dateStr === 'null')
        return 'Sin Fecha';
    $timestamp = strtotime($dateStr);
    if (!$timestamp)
        return $dateStr;

    $months = [
        "January" => "Enero",
        "February" => "Febrero",
        "March" => "Marzo",
        "April" => "Abril",
        "May" => "Mayo",
        "June" => "Junio",
        "July" => "Julio",
        "August" => "Agosto",
        "September" => "Septiembre",
        "October" => "Octubre",
        "November" => "Noviembre",
        "December" => "Diciembre"
    ];

    $day = date('d', $timestamp);
    $monthEn = date('F', $timestamp);
    $monthEs = $months[$monthEn] ?? $monthEn;
    $year = date('Y', $timestamp);

    return "{$day} de {$monthEs} del {$year}";
}

// ─────────────────────────────────────────────────────────────────────────────
// DESIGN CONSTANTS (Mirrored from Modal UI)
// ─────────────────────────────────────────────────────────────────────────────
$C_NAVY = '#0d2441';
$C_BLUE = '#0d6efd';  // Primary
$C_GREEN = '#198754';  // Success
$C_CYAN = '#0dcaf0';  // Info
$C_YELLOW = '#ffc107';  // Warning
$C_RED = '#dc3545';  // Danger
$C_GRAY = '#6c757d';  // Secondary
$C_BLACK = '#212529';  // Dark
$C_LIGHT = '#e8f0fe';
$C_ACCENT = '#3a7bd5';
$C_STRIP = '#f8f9fa';
$C_WHITE = '#ffffff';
$C_TEXT = '#1a1a2e';

// ─────────────────────────────────────────────────────────────────────────────
// HELPER: professional section (chart page + table page)
// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// HELPER: Render Native Bars (HTML/CSS) for PDF
// ─────────────────────────────────────────────────────────────────────────────
function render_native_charts($canvasId, $rows, $navy, $denominator = null)
{
    if (empty($rows)) {
        return "<div style='padding:20px; text-align:center; background:#f1f5f9; color:#94a3b8; border-radius:8px; margin:15px;'>Sin Datos Disponibles</div>";
    }

    $localTotal = 0;
    foreach ($rows as $r) {
        $vals = array_values($r);
        $localTotal += (int) end($vals);
    }

    $isSae = ($canvasId === 'chartSae');
    $categoryTotals = [];
    if ($isSae) {
        foreach ($rows as $r) {
            $vals = array_values($r);
            $group = explode(' (', (string) $vals[0])[0];
            $categoryTotals[$group] = ($categoryTotals[$group] ?? 0) + (int) end($vals);
        }
    }

    // Determine max value for relative width scaling
    $maxVal = 0;
    foreach ($rows as $r) {
        $vals = array_values($r);
        $maxVal = max($maxVal, (int) end($vals));
    }
    if ($maxVal === 0)
        $maxVal = 1;

    $html = "<div style='padding:10px 15px 25px 15px;'>";
    $html .= "<table style='width:100%; border-collapse:collapse; font-family:Helvetica,Arial,sans-serif;'>";

    foreach ($rows as $row) {
        $vals = array_values($row);
        $label = (string) $vals[0];
        $val = (int) end($vals);

        // Percentage calculations
        $perc = ($localTotal > 0) ? ($val / $localTotal) : 0;
        $barWidth = ($val / $maxVal) * 92; // Max 92% to leave space for value text

        // Color Logic
        $color = '#0d6efd'; // Default Blue
        if ($isSae) {
            $isLoaded = stripos($label, '(CARGADO)') !== false;
            $isFTTH = stripos($label, 'FTTH') !== false;
            $isRadio = stripos($label, 'RADIO') !== false;

            if ($isFTTH)
                $color = $isLoaded ? '#10b981' : '#a855f7';
            elseif ($isRadio)
                $color = $isLoaded ? '#3b82f6' : '#f59e0b';
            else
                $color = $isLoaded ? '#059669' : '#94a3b8';
        }

        $html .= "<tr>";
        // Label Column (Stripping tags for clean chart labels)
        $html .= "<td style='padding:6px 0; width:180px; font-size:10px; color:#4a5568; vertical-align:middle; border-bottom:1px solid #edf2f7;'>" . htmlspecialchars(strip_tags($label)) . "</td>";

        // Bar Column
        $html .= "<td style='padding:6px 5px; vertical-align:middle; border-bottom:1px solid #edf2f7;'>";
        $html .= "<div style='width:100%; background:#f1f5f9; height:24px; border-radius:3px; overflow:hidden;'>";
        $html .= "<div style='width:{$barWidth}%; background:{$color}; height:24px; border-radius:3px; position:relative;'>";
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</td>";

        // Value Column (Small badge like Chart.js)
        $html .= "<td style='padding:6px 0; width:90px; vertical-align:middle; border-bottom:1px solid #edf2f7; text-align:right;'>";
        $html .= "<div style='display:inline-block; background:#fff; border:1px solid #ddd; border-radius:3px; padding:4px 6px; font-size:10px; font-weight:bold; line-height:1.2; min-width:60px;'>";
        $html .= "<span style='color:{$navy};'>{$val}</span><br>";
        $html .= "<span style='font-size:8px; color:#666;'>" . number_format($perc * 100, 1) . "%</span>";

        if ($isSae && $denominator > 0) {
            $fiab = ($val / $denominator) * 100;
            $html .= "<br><span style='font-size:7.5px; color:#0d6efd;'>Fiabilidad: " . number_format($fiab, 1) . "%</span>";
        }

        $html .= "</div>";
        $html .= "</td>";

        $html .= "</tr>";
    }

    $html .= "</table>";
    $html .= "</div>";

    return $html;
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPER: professional section (chart page + table page)
// ─────────────────────────────────────────────────────────────────────────────
function section_html($id_num, $title, $canvasId, $rows, $firstColLabel, $primaryColor, $navy, $white, $light, $strip, $textColor, $denominator = null)
{
    $out = "<div class='section'>";
    $out .= "<div style='background:{$primaryColor}; padding:10px 25px;'>";
    $out .= "<div style='color:#fff; font-size:16px; font-weight:bold; letter-spacing:0.5px;'>{$id_num}. " . htmlspecialchars($title) . "</div>";
    $out .= "</div>";

    // Chart Area (Now using Native Code)
    $out .= render_native_charts($canvasId, $rows, $navy, $denominator);

    // Table Area
    $out .= "<div style=\"margin:0 15px 15px 15px; background:{$white}; border:1px solid #e0e6ed; border-radius:8px; overflow:hidden;\">";
    $out .= "<div style=\"background:{$light}; padding:8px 15px; font-weight:bold; color:{$primaryColor}; border-bottom:1px solid #e0e6ed; font-size:12px;\">DATOS DETALLADOS</div>";

    // Porcentajes Logic
    $isSae = ($denominator !== null);
    $localTotal = 0;
    $groupTotals = [];

    foreach ($rows as $r) {
        $vals = array_values($r);
        $count = (int) end($vals);
        $localTotal += $count;
        if ($isSae) {
            $group = explode(' (', (string) $vals[0])[0];
            $groupTotals[$group] = ($groupTotals[$group] ?? 0) + $count;
        }
    }

    $out .= "<table style=\"width:100%; border-collapse:collapse; font-size:11px;\">";
    $out .= "<thead><tr style=\"background:{$navy}; color:#fff;\">";
    $out .= "<th style=\"padding:8px 15px; text-align:left;\">" . htmlspecialchars($firstColLabel) . "</th>";
    $out .= "<th style=\"padding:8px 15px; text-align:center; width:80px;\">TOTAL</th>";
    if ($isSae) {
        $out .= "<th style=\"padding:8px 15px; text-align:center; width:100px;\">% GRUPO</th>";
        $out .= "<th style=\"padding:8px 15px; text-align:center; width:100px;\">FIABILIDAD (%)</th>";
    } else {
        $out .= "<th style=\"padding:8px 15px; text-align:center; width:100px;\">%</th>";
    }
    $out .= "</tr></thead><tbody>";

    $odd = true;
    foreach ($rows as $row) {
        $vals = array_values($row);
        $val = (int) end($vals);
        $bg = $odd ? $strip : '#ffffff';
        $odd = !$odd;

        $out .= "<tr style=\"background:{$bg};\">";
        $out .= "<td style=\"padding:6px 15px; border-bottom:1px solid #edf2f7; color:#4a5568;\">" . htmlspecialchars((string) $vals[0]) . "</td>";
        $out .= "<td style=\"padding:6px 15px; text-align:center; border-bottom:1px solid #edf2f7; font-weight:bold; color:{$navy};\">" . htmlspecialchars((string) $val) . "</td>";

        if ($isSae) {
            $group = explode(' (', (string) $vals[0])[0];
            $gTotal = $groupTotals[$group] ?? 0;
            $percGroup = ($gTotal > 0) ? number_format(($val / $gTotal) * 100, 1) . '%' : '0%';
            $percFiab = ($denominator > 0) ? number_format(($val / $denominator) * 100, 1) . '%' : '0%';

            $out .= "<td style=\"padding:6px 15px; text-align:center; border-bottom:1px solid #edf2f7; color:#2d3748;\">{$percGroup}</td>";
            $out .= "<td style=\"padding:6px 15px; text-align:center; border-bottom:1px solid #edf2f7; color:#2d3748; font-weight:bold;\">{$percFiab}</td>";
        } else {
            $perc = ($localTotal > 0) ? number_format(($val / $localTotal) * 100, 1) . '%' : '0%';
            $out .= "<td style=\"padding:6px 15px; text-align:center; border-bottom:1px solid #edf2f7; color:#2d3748; font-weight:bold;\">{$perc}</td>";
        }
        $out .= "</tr>";
    }

    $out .= "<tr style=\"background:#cbd5e0; font-weight:bold;\">";
    $out .= "<td style=\"padding:8px 15px; color:{$navy};\">TOTAL GENERAL</td>";
    $out .= "<td style=\"padding:8px 15px; text-align:center; color:{$primaryColor}; font-size:13px;\">{$localTotal}</td>";
    if ($isSae) {
        $out .= "<td style=\"padding:8px 15px;\"></td>"; // No aplica total de % grupo fácilmente en una sola celda
        $percFinal = ($denominator > 0) ? number_format(($localTotal / $denominator) * 100, 1) . '%' : '0%';
        $out .= "<td style=\"padding:8px 15px; text-align:center; color:{$navy}; font-size:13px;\">{$percFinal}</td>";
    } else {
        $out .= "<td style=\"padding:8px 15px; text-align:center; color:{$navy}; font-size:13px;\">100%</td>";
    }
    $out .= "</tr></tbody></table></div>";

    $out .= "</div>"; // end section div
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────────
// COVER PAGE & HTML SHELL
// ─────────────────────────────────────────────────────────────────────────────
$meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
$dia = date('d');
$mes_en = date('F');
$anio = date('Y');
$mes_es = $meses[$mes_en] ?? $mes_en;
$today = "{$dia} de {$mes_es} de {$anio}";

$filter_period = (!empty($start_date) && !empty($end_date)) ? "$start_date → $end_date" : 'Histórico completo';
$filter_vend = !empty($vendor_id) ? htmlspecialchars($vendor_id) : 'Todos';
$filter_inst = !empty($installer) ? htmlspecialchars($installer) : 'Todos';
$filter_type = !empty($contract_type) ? htmlspecialchars($contract_type) : 'Todos';
$filter_inst_type = !empty($install_type) ? htmlspecialchars($install_type) : 'Todos';

$html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Estadístico — Wireless Supply, C.A.</title>
    <style>
        @page { margin: 0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Helvetica,Arial,sans-serif; font-size:10px; color:#1a1a2e; background:#f8fafc; margin: 0; padding: 0; }
        .section { width: 100%; margin: 0; padding: 0; margin-bottom: 25px; }
    </style>
</head>
<body>';

// ── COVER PAGE ────────────────────────────────────────────────────────────────
$html .= "<div style=\"width:100%; font-family:Helvetica,Arial,sans-serif;\">";

// Top navy band
$html .= "<div style=\"background:{$C_NAVY}; width:100%; height:18px;\"></div>";

// Branding strip: company name centred on blue
$html .= "<div style=\"background:{$C_BLUE}; padding:14px 20px; text-align:center;\">";
$html .= "<div style=\"color:#fff; font-size:22px; font-weight:bold; letter-spacing:1px;\">Wireless Supply, C.A.</div>";
$html .= "<div style=\"color:rgba(255,255,255,0.7); font-size:9px; margin-top:3px;\">J-50735886-0 &nbsp;|&nbsp; Calle Comercio Con Calle Grupo Escolar, Local Edificio Carper, Sector Centro, Mcpio Escuque, Trujillo</div>";
$html .= "<div style=\"color:rgba(255,255,255,0.7); font-size:9px; margin-top:1px;\">+58 (424) 762-7776 &nbsp;/&nbsp; +58 (424) 733-6576</div>";
$html .= "</div>";

// Accent stripe
$html .= "<div style=\"background:{$C_ACCENT}; height:4px;\"></div>";

// Report title block
$html .= "<div style=\"padding:40px 30px 20px 30px; text-align:center;\">";
$html .= "<div style=\"font-size:9px; text-transform:uppercase; letter-spacing:2px; color:{$C_BLUE}; margin-bottom:8px;\">Reporte Ejecutivo</div>";
$html .= "<div style=\"font-size:24px; font-weight:bold; color:{$C_NAVY}; text-transform:uppercase; letter-spacing:1px;\">Resumen de Instalaciones</div>";
$html .= "<div style=\"width:60px; height:4px; background:{$C_ACCENT}; margin:14px auto;\"></div>";
$html .= "<div style=\"font-size:11px; color:#555;\">Generado el $today</div>";
$html .= "</div>";

// Filters box
$html .= "<div style=\"margin:10px 30px; background:{$C_LIGHT}; border-left:4px solid {$C_BLUE}; padding:14px 18px; border-radius:2px;\">";
$html .= "<div style=\"font-size:10px; font-weight:bold; color:{$C_BLUE}; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;\">Filtros Aplicados</div>";
$html .= "<table style=\"width:100%; border-collapse:collapse; font-size:9.5px;\">";
$html .= "<tr><td style=\"padding:3px 6px; color:#555; width:140px;\">Periodo:</td><td style=\"padding:3px 6px; font-weight:bold; color:{$C_TEXT};\">{$filter_period}</td>
          <td style=\"padding:3px 6px; color:#555; width:140px;\">Tipo de Conexión:</td><td style=\"padding:3px 6px; font-weight:bold; color:{$C_TEXT};\">{$filter_type}</td></tr>";
$html .= "<tr><td style=\"padding:3px 6px; color:#555;\">Vendedor:</td><td style=\"padding:3px 6px; font-weight:bold; color:{$C_TEXT};\">{$filter_vend}</td>
          <td style=\"padding:3px 6px; color:#555;\">Tipo de Instalación:</td><td style=\"padding:3px 6px; font-weight:bold; color:{$C_TEXT};\">{$filter_inst_type}</td></tr>";
$html .= "<tr><td style=\"padding:3px 6px; color:#555;\">Instalador:</td><td style=\"padding:3px 6px; font-weight:bold; color:{$C_TEXT};\">{$filter_inst}</td><td colspan='2'></td></tr>";
$html .= "</table>";
$html .= "</div>";

// Section index
$sections_list = [
    '1' => 'Zonas de Ventas',
    '2' => 'Instalaciones por Tipo',
    '3' => 'Instalaciones por Mes e Instalador',
    '4' => 'Tipos de Conexión',
    '5' => 'Instaladores',
    '6' => 'Ventas por Vendedor',
    '7' => 'Carga en SAE Plus (Desglose)',
];
$html .= "<div style=\"margin:22px 30px 0 30px;\">";
$html .= "<div style=\"font-size:10px; font-weight:bold; color:{$C_NAVY}; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid {$C_BLUE}; padding-bottom:5px; margin-bottom:10px;\">Contenido del Reporte</div>";
$html .= "<table style=\"width:100%; border-collapse:collapse; font-size:9.5px;\">";
foreach ($sections_list as $n => $lbl) {
    $bg = ((int) $n % 2 === 0) ? $C_STRIP : $C_WHITE;
    $html .= "<tr style=\"background:{$bg};\"><td style=\"padding:5px 8px; width:30px; text-align:center; font-weight:bold; color:{$C_BLUE};\">{$n}</td>";
    $html .= "<td style=\"padding:5px 8px; color:{$C_TEXT};\">" . htmlspecialchars($lbl) . "</td></tr>";
}
$html .= "</table></div>";

$html .= "<div style=\"background:{$C_NAVY}; width:100%; height:10px; margin-top:20px;\"></div>";
$html .= "</div>"; // end cover

// ── SECTIONS (Now using Native Chart Rendering) ───────────────────────────
$html .= section_html('1', 'Zonas de Ventas', 'chartLocation', $stats_location, 'Parroquia — Vendedor', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT);
$html .= section_html('2', 'Instalaciones por Tipo', 'chartType', $stats_type, 'Tipo de Instalación', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT);
$html .= section_html('3', 'Instalaciones Mensuales', 'chartMonthly', $stats_monthly, 'Mes — Instalador', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT);
$html .= section_html('4', 'Tipos de Conexión', 'chartConnection', $stats_connection, 'Tipo de Conexión', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT);
$html .= section_html('5', 'Instaladores', 'chartInstaller', $stats_installers, 'Nombre del Instalador', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT);
$html .= section_html('6', 'Ventas por Vendedor', 'chartVendor', $stats_vendors, 'Vendedor', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT);
$html .= section_html('7', 'Carga en SAE Plus (Desglose)', 'chartSae', $stats_sae, 'Conexión (Estado)', $C_BLUE, $C_NAVY, $C_WHITE, $C_LIGHT, $C_STRIP, $C_TEXT, $total_global);

$html .= '</body></html>';

// ── GENERATE PDF ─────────────────────────────────────────────────────────────
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->setPaper('letter', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream("Reporte_Estadistico.pdf", ["Attachment" => false]);
exit(0);