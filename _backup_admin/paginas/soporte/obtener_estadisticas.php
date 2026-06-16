<?php
/**
 * Obtener Estadísticas de Reportes Técnicos
 * Endpoint AJAX que retorna JSON con estadísticas para el dashboard
 */
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

// Helper: convierte float de forma segura (evita INF o NaN que rompen json_encode)
function safe_float($v)
{
    $f = (float) $v;
    return (is_finite($f)) ? $f : 0.0;
}

// Helper: limpia bytes UTF-8 inválidos de cualquier string o array anidado.
// Necesario porque el servidor guardó algunos datos con codificación incorrecta.
function sanitize_utf8($value)
{
    if (is_array($value)) {
        return array_map('sanitize_utf8', $value);
    }
    if (is_string($value)) {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
    return $value;
}

require_once '../conexion.php';

try {
    // Parámetros de filtro (opcional)
    $fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-6 months'));
    $fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
    $tipo_falla_filt = isset($_GET['tipo_falla']) ? $conn->real_escape_string($_GET['tipo_falla']) : '';
    $tecnico_filt = isset($_GET['tecnico']) ? $conn->real_escape_string($_GET['tecnico']) : '';
    $estado_pago_filt = isset($_GET['estado_pago']) ? $_GET['estado_pago'] : '';

    $where_filtros = " AND fecha_soporte BETWEEN '$fecha_desde' AND '$fecha_hasta'";
    if ($tipo_falla_filt != '') {
        if (strpos($tipo_falla_filt, 'NIVEL') === 0) {
            $where_filtros .= " AND prioridad = '$tipo_falla_filt'";
        } else {
            $where_filtros .= " AND tipo_falla = '$tipo_falla_filt'";
        }
    }
    if ($tecnico_filt != '') {
        $where_filtros .= " AND tecnico_asignado LIKE '%$tecnico_filt%'";
    }
    if ($estado_pago_filt == 'PAGADO') {
        $where_filtros .= " AND (monto_total - monto_pagado) <= 0.01";
    } elseif ($estado_pago_filt == 'PENDIENTE') {
        $where_filtros .= " AND (monto_total - monto_pagado) > 0.01";
    }

    // 1. Estadísticas generales
    $sql_general = "SELECT 
                        COUNT(*) as total_reportes,
                        SUM(monto_total) as total_facturado,
                        SUM(monto_pagado) as total_cobrado,
                        SUM(CASE WHEN (monto_total - monto_pagado) > 0.01 THEN 1 ELSE 0 END) as reportes_pendientes,
                        SUM(CASE WHEN (monto_total - monto_pagado) <= 0.01 THEN 1 ELSE 0 END) as reportes_pagados
                    FROM soportes
                    WHERE 1=1 $where_filtros";

    $result = $conn->query($sql_general);
    $stats_general = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [
        'total_reportes' => 0,
        'total_facturado' => 0,
        'total_cobrado' => 0,
        'reportes_pendientes' => 0,
        'reportes_pagados' => 0
    ];

    // 2. Fallas por tipo
    $sql_tipo = "SELECT tipo_falla, COUNT(*) as cantidad
                 FROM soportes
                 WHERE tipo_falla IS NOT NULL 
                 AND tipo_falla != ''
                 AND 1=1 $where_filtros
                 GROUP BY tipo_falla
                 ORDER BY cantidad DESC";

    $result_tipo = $conn->query($sql_tipo);
    $fallas_por_tipo = [];
    if ($result_tipo) {
        while ($row = $result_tipo->fetch_assoc()) {
            $fallas_por_tipo[$row['tipo_falla']] = (int) $row['cantidad'];
        }
    }

    // 3. Reportes por mes
    $sql_mes = "SELECT 
                    DATE_FORMAT(fecha_soporte, '%Y-%m') as mes,
                    DATE_FORMAT(fecha_soporte, '%b %Y', 'es_ES') as mes_nombre,
                    COUNT(*) as cantidad
                FROM soportes
                WHERE 1=1 $where_filtros
                GROUP BY mes, mes_nombre
                ORDER BY mes ASC";

    $result_mes = $conn->query($sql_mes);
    $reportes_por_mes = [];
    $meses_labels = [];
    if ($result_mes) {
        while ($row = $result_mes->fetch_assoc()) {
            $reportes_por_mes[$row['mes']] = (int) $row['cantidad'];
            $meses_labels[] = $row['mes_nombre'];
        }
    }

    // 4. Reportes por técnico
    $sql_tecnico = "SELECT 
                        tecnico_asignado,
                        COUNT(*) as cantidad,
                        SUM(monto_total) as total_facturado
                    FROM soportes
                    WHERE tecnico_asignado IS NOT NULL 
                    AND tecnico_asignado != ''
                    AND 1=1 $where_filtros
                    GROUP BY tecnico_asignado
                    ORDER BY cantidad DESC
                    LIMIT 10";

    $result_tecnico = $conn->query($sql_tecnico);
    $reportes_por_tecnico = [];
    if ($result_tecnico) {
        while ($row = $result_tecnico->fetch_assoc()) {
            $reportes_por_tecnico[$row['tecnico_asignado']] = [
                'cantidad' => (int) $row['cantidad'],
                'total_facturado' => (float) $row['total_facturado']
            ];
        }
    }

    // 5. Ingresos por mes
    $sql_ingresos = "SELECT 
                        DATE_FORMAT(fecha_soporte, '%Y-%m') as mes,
                        DATE_FORMAT(fecha_soporte, '%b %Y', 'es_ES') as mes_nombre,
                        SUM(monto_total) as total,
                        SUM(monto_pagado) as pagado
                    FROM soportes
                    WHERE 1=1 $where_filtros
                    GROUP BY mes, mes_nombre
                    ORDER BY mes ASC";

    $result_ingresos = $conn->query($sql_ingresos);
    $ingresos_por_mes = [];
    if ($result_ingresos) {
        while ($row = $result_ingresos->fetch_assoc()) {
            $ingresos_por_mes[$row['mes']] = [
                'total' => (float) $row['total'],
                'pagado' => (float) $row['pagado']
            ];
        }
    }

    // 6. Fallas por Nivel de Prioridad (Excluir MEDIA)
    $sql_nivel = "SELECT prioridad, COUNT(*) as cantidad
                  FROM soportes
                  WHERE prioridad IS NOT NULL
                  AND prioridad != 'MEDIA'
                  AND 1=1 $where_filtros
                  GROUP BY prioridad
                  ORDER BY FIELD(prioridad, 'NIVEL 3', 'NIVEL 2', 'NIVEL 1')";
    $result_nivel = $conn->query($sql_nivel);
    $fallas_por_nivel = [];
    if ($result_nivel) {
        while ($row = $result_nivel->fetch_assoc()) {
            $prioridad_nombre = empty($row['prioridad']) ? 'NO ASIGNADO' : $row['prioridad'];
            $fallas_por_nivel[$prioridad_nombre] = (int) $row['cantidad'];
        }
    }

    // Construir respuesta JSON
    $response = [
        'success' => true,
        'general' => [
            'total_reportes' => (int) $stats_general['total_reportes'],
            'reportes_pendientes' => (int) $stats_general['reportes_pendientes'],
            'reportes_pagados' => (int) $stats_general['reportes_pagados'],
            'total_facturado' => safe_float($stats_general['total_facturado']),
            'total_cobrado' => safe_float($stats_general['total_cobrado']),
            'saldo_pendiente' => safe_float($stats_general['total_facturado']) - safe_float($stats_general['total_cobrado'])
        ],
        'fallas_por_tipo' => $fallas_por_tipo,
        'reportes_por_mes' => $reportes_por_mes,
        'meses_labels' => $meses_labels,
        'reportes_por_tecnico' => $reportes_por_tecnico,
        'ingresos_por_mes' => $ingresos_por_mes,
        'fallas_por_nivel' => $fallas_por_nivel,
        'periodo' => [
            'desde' => $fecha_desde,
            'hasta' => $fecha_hasta
        ]
    ];

    $conn->close();

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    // Sanitizar todos los strings antes de codificar para eliminar bytes UTF-8 inválidos
    $response = sanitize_utf8($response);
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        echo json_encode(['success' => false, 'error' => 'json_encode failed: ' . json_last_error_msg()]);
    } else {
        echo $json;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof mysqli)
        $conn->close();

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>