<?php
/**
 * Exportar Mensualidades a Excel (.xls)
 * Columnas: Fecha, Ref, Cliente, Concepto, Monto, Cuenta
 */
require '../conexion.php';

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$id_banco = isset($_GET['id_banco']) ? $_GET['id_banco'] : '';
$filtro_tipo = isset($_GET['filtro_tipo']) ? $_GET['filtro_tipo'] : '';

// Aumentar el límite de GROUP_CONCAT para evitar truncamiento en justificaciones largas
$conn->query("SET SESSION group_concat_max_len = 10000");

// Construir consulta
$sWhere = "WHERE 1=1";

if (!empty($fecha_inicio)) {
    $sWhere .= " AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) >= '" . $conn->real_escape_string($fecha_inicio) . "'";
}
if (!empty($fecha_fin)) {
    $sWhere .= " AND COALESCE(cxc.fecha_pago, cxc.fecha_emision) <= '" . $conn->real_escape_string($fecha_fin) . "'";
}

if ($tipo === 'filtrado' && !empty($id_banco)) {
    $sWhere .= " AND cxc.id_banco = '" . $conn->real_escape_string($id_banco) . "'";
}

    if (!empty($filtro_tipo)) {
    if ($filtro_tipo === 'Mensualidad') {
        $sWhere .= " AND (h.justificacion LIKE '%[MENSUALIDAD]%' OR (h.justificacion IS NULL AND pl.nombre_plan IS NOT NULL) OR h.justificacion LIKE '%mensualidad%')";
    } elseif ($filtro_tipo === 'Instalacion') {
        $sWhere .= " AND (h.justificacion LIKE '%[INSTALACION]%' OR h.justificacion LIKE '%instalación%' OR h.justificacion LIKE '%instalacion%')";
    } elseif ($filtro_tipo === 'Equipos') {
        $sWhere .= " AND (h.justificacion LIKE '%[EQUIPOS]%' OR h.justificacion LIKE '%equipo%' OR h.justificacion LIKE '%material%')";
    } elseif ($filtro_tipo === 'Prorrateo') {
        $sWhere .= " AND (h.justificacion LIKE '%[PRORRATEO]%' OR h.justificacion LIKE '%prorrateo%')";
    } elseif ($filtro_tipo === 'Abono') {
        $sWhere .= " AND (h.justificacion LIKE '%[ABONO]%' OR h.justificacion LIKE '%abono%' OR h.justificacion LIKE '%saldo a favor%' OR h.justificacion LIKE '%[ABONO_DEUDA]%')";
    } elseif ($filtro_tipo === 'Extra') {
        $sWhere .= " AND (h.justificacion LIKE '%[EXTRA]%' OR h.justificacion LIKE '%terceros%' OR h.justificacion LIKE '%extra%')";
    }
}

$mes_cobrado = isset($_GET['mes_cobrado']) ? $_GET['mes_cobrado'] : '';
if (!empty($mes_cobrado)) {
    $mes = $conn->real_escape_string($mes_cobrado);
    
    // Mapeo de meses en español a números para el fallback de fecha_emision
    $mesesMapNum = [
        'Enero' => 1, 'Febrero' => 2, 'Marzo' => 3, 'Abril' => 4, 'Mayo' => 5, 'Junio' => 6,
        'Julio' => 7, 'Agosto' => 8, 'Septiembre' => 9, 'Octubre' => 10, 'Noviembre' => 11, 'Diciembre' => 12
    ];
    $numMes = isset($mesesMapNum[$mes]) ? $mesesMapNum[$mes] : 0;

    $sWhere .= " AND (
        cxc.id_cobro IN (SELECT id_cobro_cxc FROM cobros_manuales_historial WHERE justificacion LIKE '%[$mes]%')
        OR 
        (
            NOT EXISTS (SELECT 1 FROM cobros_manuales_historial WHERE id_cobro_cxc = cxc.id_cobro) 
            AND MONTH(cxc.fecha_emision) = $numMes
        )
    )";
}
// Si es 'todos' (global), NO aplicamos el filtro de banco, pero sí el de fechas si existen.

// Cargar Bancos JSON
$json_bancos = file_get_contents('bancos.json');
$bancosArr = json_decode($json_bancos, true) ?: [];
$bancosMap = [];
foreach($bancosArr as $b) {
    if(isset($b['id_banco'])) $bancosMap[$b['id_banco']] = $b['nombre_banco'];
}

// Verificar si las columnas bimonetarias ya existen en la BD
$tiene_cols_bimonetarias = false;
$check_cols = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_NAME='cuentas_por_cobrar' AND COLUMN_NAME='monto_total_bs' LIMIT 1");
if ($check_cols && $check_cols->num_rows > 0) {
    $tiene_cols_bimonetarias = true;
}

// (Las columnas bimonetarias ya se manejan con COALESCE en la query principal)

$sql = "
    SELECT 
        cxc.fecha_emision,
        cxc.fecha_pago,
        cxc.referencia_pago,
        co.cedula,
        co.nombre_completo,
        pl.nombre_plan,
        -- Monto real: usa el sumatorio del historial si existe, si no el monto facturado
        COALESCE(SUM(h.monto_cargado), cxc.monto_total) AS monto_total,
        COALESCE(SUM(h.monto_cargado_bs), cxc.monto_total_bs) AS monto_total_bs,
        COALESCE(MAX(h.tasa_bcv), cxc.tasa_bcv) AS tasa_bcv,
        cxc.id_banco,
        cxc.estado,
        GROUP_CONCAT(h.justificacion ORDER BY h.id SEPARATOR ' || ') AS justificacion
    FROM cuentas_por_cobrar cxc
    INNER JOIN contratos co ON cxc.id_contrato = co.id
    LEFT JOIN planes pl ON co.id_plan = pl.id_plan
    LEFT JOIN cobros_manuales_historial h ON cxc.id_cobro = h.id_cobro_cxc
    $sWhere
    GROUP BY cxc.id_cobro
    ORDER BY cxc.id_cobro DESC
";

$result = $conn->query($sql);

// Nombre del archivo
$suffix = ($tipo === 'filtrado' ? 'filtrado' : 'global');
if ($fecha_inicio) $suffix .= "_" . str_replace('-', '', $fecha_inicio);
$filename = "mensualidades_" . $suffix . "_" . date('Ymd') . ".xls";

// Headers para descarga en formato Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);

// BOM para UTF-8
echo "\xEF\xBB\xBF";

// Empezamos la tabla HTML que Excel interpretará como celdas
echo "<table border='1'>";
echo "<thead>
        <tr style='background-color: #0d6efd; color: white;'>
            <th>Fecha</th>
            <th>Cédula</th>
            <th>Referencia</th>
            <th>Cliente</th>
            <th>Concepto</th>
            <th>Detalle</th>
            <th>Monto ($)</th>
            <th>Monto (Bs)</th>
            <th>Tasa BCV</th>
            <th>Cuenta</th>
            <th>Estado</th>
        </tr>
      </thead>";
echo "<tbody>";

// Datos
while ($row = $result->fetch_assoc()) {
    $fecha_display = $row['fecha_pago'] ? $row['fecha_pago'] : $row['fecha_emision'];
    $fecha_fmt = date('d/m/Y', strtotime($fecha_display));
    
    // Concepto Parsing (Sincronizado con tabla web)
    $justif = $row['justificacion'] ?: '';
    $conceptosArr = [];

    if (strpos($justif, '[MENSUALIDAD') !== false) $conceptosArr[] = 'Mensualidad';
    if (strpos($justif, '[INSTALACION') !== false) $conceptosArr[] = 'Instalación';
    if (strpos($justif, '[EQUIPOS') !== false) $conceptosArr[] = 'Equipos / Materiales';
    if (strpos($justif, '[PRORRATEO') !== false) $conceptosArr[] = 'Prorrateo';
    if (strpos($justif, '[ABONO') !== false && strpos($justif, '[ABONO_DEUDA]') === false) $conceptosArr[] = 'Abono / Saldo a Favor';
    if (strpos($justif, '[EXTRA') !== false) $conceptosArr[] = 'Pago de Terceros';
    if (strpos($justif, '[REGISTRO_CONTRATO') !== false) $conceptosArr[] = 'Registro de Contrato';
    if (strpos($justif, '[PAGO_DEUDA]') !== false) $conceptosArr[] = 'Pago de Deuda';
    if (strpos($justif, '[ABONO_DEUDA]') !== false) $conceptosArr[] = 'Abono de Deuda';

    if (count($conceptosArr) > 0) {
        // Mapear con nombre del plan si es mensualidad
        $mapped = array_map(function($c) use ($row) {
            if ($c === 'Mensualidad' && !empty($row['nombre_plan'])) {
                return 'Mensualidad / ' . $row['nombre_plan'];
            }
            return $c;
        }, $conceptosArr);
        $concepto_main = implode(' + ', $mapped);
    } elseif ($justif && strpos($justif, '||') === false) {
        $concepto_main = 'Cargo Manual / Otro';
    } elseif ($row['nombre_plan']) {
        $concepto_main = 'Mensualidad / ' . $row['nombre_plan'];
    } else {
        $concepto_main = 'Varios / Otros';
    }
    
    $detalle = str_replace(' || ', ' | ', $justif);
    if (empty($detalle)) $detalle = '-';
    
    $monto_val = $row['monto_total'];
    $monto_bs_val = $row['monto_total_bs'];
    $tasa_bcv_val = $row['tasa_bcv'];
    
    // Protección contra error de guardado (USD == BS cuando tasa > 1)
    if (!empty($monto_bs_val) && !empty($tasa_bcv_val) && $monto_bs_val == $monto_val && $tasa_bcv_val > 1.01) {
        $monto_bs_val = $monto_val * $tasa_bcv_val;
    }

    $monto = number_format($monto_val, 2, ',', '.');
    $monto_bs = !empty($monto_bs_val) ? number_format($monto_bs_val, 2, ',', '.') : '-';
    $tasa_bcv = !empty($tasa_bcv_val) ? number_format($tasa_bcv_val, 2, ',', '.') : '-';
    
    // Banco name from JSON
    $id_banco = $row['id_banco'];
    $nombre_banco = isset($bancosMap[$id_banco]) ? $bancosMap[$id_banco] : ($id_banco ? 'Desconocido' : '-');

    echo "<tr>";
    echo "<td>" . $fecha_fmt . "</td>";
    echo "<td style='mso-number-format:\"\\@\";'>" . htmlspecialchars($row['cedula'] ?? '-') . "</td>";
    echo "<td style='mso-number-format:\"\\@\";'>" . ($row['referencia_pago'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nombre_completo']) . "</td>";
    echo "<td>" . htmlspecialchars($concepto_main) . "</td>";
    echo "<td>" . htmlspecialchars($detalle) . "</td>";
    echo "<td>" . $monto . "</td>";
    echo "<td>" . $monto_bs . "</td>";
    echo "<td>" . $tasa_bcv . "</td>";
    echo "<td>" . htmlspecialchars($nombre_banco) . "</td>";
    echo "<td>" . htmlspecialchars($row['estado']) . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

$conn->close();
exit();
?>
