<?php
ob_start();
/**
 * Obtener Métricas Avanzadas
 * Endpoint AJAX para métricas de gestión avanzada de fallas
 */

error_reporting(0);
ini_set('display_errors', 0);
require_once '../conexion.php';

// Parámetros de filtro
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-1 month'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$tipo_falla_filt = isset($_GET['tipo_falla']) ? $conn->real_escape_string($_GET['tipo_falla']) : '';
$tecnico_filt = isset($_GET['tecnico']) ? $conn->real_escape_string($_GET['tecnico']) : '';
$estado_pago_filt = isset($_GET['estado_pago']) ? $_GET['estado_pago'] : '';

$fecha_desde = $conn->real_escape_string($fecha_desde);
$fecha_hasta = $conn->real_escape_string($fecha_hasta);

$where_filtros = " AND fecha_reporte BETWEEN '$fecha_desde' AND '$fecha_hasta'";
if ($tipo_falla_filt != '') {
    $where_filtros .= " AND tipo_falla = '$tipo_falla_filt'";
}
if ($tecnico_filt != '') {
    $where_filtros .= " AND tecnico_asignado LIKE '%$tecnico_filt%'";
}
if ($estado_pago_filt == 'PAGADO') {
    $where_filtros .= " AND (monto_total - monto_pagado) <= 0.01";
} elseif ($estado_pago_filt == 'PENDIENTE') {
    $where_filtros .= " AND (monto_total - monto_pagado) > 0.01";
}

$response = [
    'success' => true,
    'tiempo_respuesta' => [],
    'fallas_criticas' => [],
    'clientes_recurrentes' => [],
    'zonas_afectadas' => [],
    'caidas_recientes' => []
];

// 1. TIEMPO DE RESPUESTA
try {
    // Promedio general
    $sql_tiempo = "SELECT 
                    AVG(TIMESTAMPDIFF(HOUR, fecha_reporte, fecha_atencion)) as promedio_respuesta_horas,
                    AVG(TIMESTAMPDIFF(HOUR, fecha_atencion, fecha_resolucion)) as promedio_resolucion_horas,
                    AVG(TIMESTAMPDIFF(HOUR, fecha_reporte, fecha_resolucion)) as promedio_total_horas
                   FROM soportes
                   WHERE fecha_atencion IS NOT NULL
                   AND fecha_resolucion IS NOT NULL
                   AND 1=1 $where_filtros";

    $result = $conn->query($sql_tiempo);
    if ($result && $row = $result->fetch_assoc()) {
        $response['tiempo_respuesta']['promedio_respuesta'] = round($row['promedio_respuesta_horas'] ?? 0, 1);
        $response['tiempo_respuesta']['promedio_resolucion'] = round($row['promedio_resolucion_horas'] ?? 0, 1);
        $response['tiempo_respuesta']['promedio_total'] = round($row['promedio_total_horas'] ?? 0, 1);
    }

    // Tiempo por mes (dentro del rango seleccionado)
    $sql_tiempo_mes = "SELECT 
                        DATE_FORMAT(fecha_reporte, '%Y-%m') as mes,
                        AVG(TIMESTAMPDIFF(HOUR, fecha_reporte, fecha_atencion)) as promedio_horas
                       FROM soportes
                       WHERE fecha_atencion IS NOT NULL
                       AND 1=1 $where_filtros
                       GROUP BY mes
                       ORDER BY mes ASC";

    $result = $conn->query($sql_tiempo_mes);
    $por_mes = [];
    while ($result && $row = $result->fetch_assoc()) {
        $por_mes[$row['mes']] = round($row['promedio_horas'], 1);
    }
    $response['tiempo_respuesta']['por_mes'] = $por_mes;

} catch (Exception $e) {
    $response['tiempo_respuesta']['error'] = $e->getMessage();
}

// 2. FALLAS CRÍTICAS
try {
    // Activas (no resueltas) dentro del periodo
    $sql_criticas_activas = "SELECT COUNT(*) as total
                             FROM soportes
                             WHERE es_caida_critica = 1
                             AND (fecha_resolucion IS NULL OR solucion_completada = 0)
                             AND 1=1 $where_filtros";

    $result = $conn->query($sql_criticas_activas);
    $row = $result->fetch_assoc();
    $response['fallas_criticas']['activas'] = intval($row['total']);

    // Resueltas hoy
    $sql_criticas_hoy = "SELECT COUNT(*) as total
                         FROM soportes
                         WHERE es_caida_critica = 1
                         AND DATE(fecha_resolucion) = CURDATE()";

    $result = $conn->query($sql_criticas_hoy);
    $row = $result->fetch_assoc();
    $response['fallas_criticas']['resueltas_hoy'] = intval($row['total']);

    // Total del mes seleccionado (si aplica) o segun filtros
    $sql_criticas_filt = "SELECT COUNT(*) as total
                          FROM soportes
                          WHERE es_caida_critica = 1
                          AND 1=1 $where_filtros";

    $result = $conn->query($sql_criticas_filt);
    $row = $result->fetch_assoc();
    $response['fallas_criticas']['total_periodo'] = intval($row['total']);

} catch (Exception $e) {
    $response['fallas_criticas']['error'] = $e->getMessage();
}

// 3. CLIENTES RECURRENTES (dentro del rango seleccionado)
try {
    $sql_recurrentes = "SELECT 
                            c.id, 
                            c.nombre_completo, 
                            c.cedula,
                            c.ip_onu,
                            c.telefono,
                            SUM(p.monto_pagado) as total_pagado,
                            MAX(p.fecha_pago) as ultimo_pago
                        FROM mensualidades_pagos p
                        JOIN contratos c ON p.id_contrato = c.id
                        WHERE p.estado = 'VERIFICADO'
                        GROUP BY c.id, c.nombre_completo, c.cedula, c.ip_onu, c.telefono
                        ORDER BY total_pagado DESC
                        LIMIT 5";

    $result = $conn->query($sql_recurrentes);
    $recurrentes = [];
    while ($result && $row = $result->fetch_assoc()) {
        $recurrentes[] = [
            'id_cliente' => $row['id'],
            'nombre' => $row['nombre_completo'],
            'cedula' => $row['cedula'],
            'ip' => $row['ip_onu'],
            'telefono' => $row['telefono'],
            'total_pagado' => $row['total_pagado'],
            'ultimo_pago' => $row['ultimo_pago']
        ];
    }
    $response['clientes_recurrentes'] = $recurrentes;

} catch (Exception $e) {
    $response['clientes_recurrentes'] = [];
    $response['error_recurrentes'] = $e->getMessage();
}

// 4. ZONAS AFECTADAS
try {
    $sql_zonas = "SELECT 
                    zona_afectada,
                    COUNT(*) as total_fallas,
                    SUM(CASE WHEN es_caida_critica = 1 THEN 1 ELSE 0 END) as fallas_criticas
                  FROM soportes
                  WHERE zona_afectada IS NOT NULL
                  AND zona_afectada != ''
                  AND 1=1 $where_filtros
                  GROUP BY zona_afectada
                  ORDER BY total_fallas DESC
                  LIMIT 10";

    $result = $conn->query($sql_zonas);
    $zonas = [];
    while ($result && $row = $result->fetch_assoc()) {
        $zonas[$row['zona_afectada']] = [
            'total' => intval($row['total_fallas']),
            'criticas' => intval($row['fallas_criticas'])
        ];
    }
    $response['zonas_afectadas'] = $zonas;

} catch (Exception $e) {
    $response['zonas_afectadas'] = [];
    $response['error_zonas'] = $e->getMessage();
}

// 5. CAÍDAS CRÍTICAS RECIENTES (dentro del rango seleccionado)
try {
    $sql_caidas = "SELECT 
                    s.id_soporte,
                    s.tipo_falla,
                    s.zona_afectada,
                    s.sector,
                    s.clientes_afectados,
                    s.fecha_reporte,
                    s.fecha_resolucion,
                    s.prioridad,
                    c.nombre_completo,
                    TIMESTAMPDIFF(HOUR, s.fecha_reporte, COALESCE(s.fecha_resolucion, NOW())) as horas_caida
                   FROM soportes s
                   LEFT JOIN contratos c ON s.id_contrato = c.id
                   WHERE s.es_caida_critica = 1
                   AND 1=1 $where_filtros
                   ORDER BY s.fecha_reporte DESC
                   LIMIT 20";

    $result = $conn->query($sql_caidas);
    $caidas = [];
    while ($result && $row = $result->fetch_assoc()) {
        $caidas[] = [
            'id' => $row['id_soporte'],
            'tipo' => $row['tipo_falla'],
            'zona' => $row['zona_afectada'] ?: $row['sector'],
            'clientes_afectados' => intval($row['clientes_afectados']),
            'fecha_reporte' => $row['fecha_reporte'],
            'fecha_resolucion' => $row['fecha_resolucion'],
            'horas_caida' => round($row['horas_caida'], 1),
            'estado' => ($row['fecha_resolucion'] || (isset($row['solucion_completada']) && $row['solucion_completada'] == 1)) ? 'Resuelta' : 'Activa',
            'prioridad' => $row['prioridad']
        ];
    }
    $response['caidas_recientes'] = $caidas;

} catch (Exception $e) {
    $response['caidas_recientes'] = [];
    $response['error_caidas'] = $e->getMessage();
}

// 6. DISTRIBUCIÓN POR PRIORIDAD (Excluir MEDIA)
try {
    $sql_prioridad = "SELECT 
                        prioridad,
                        COUNT(*) as total
                      FROM soportes
                      WHERE 1=1 $where_filtros
                      AND prioridad != 'MEDIA'
                      GROUP BY prioridad
                      ORDER BY FIELD(prioridad, 'NIVEL 3', 'NIVEL 2', 'NIVEL 1')";

    $result = $conn->query($sql_prioridad);
    $por_prioridad = [];
    while ($result && $row = $result->fetch_assoc()) {
        $por_prioridad[$row['prioridad']] = intval($row['total']);
    }
    $response['por_prioridad'] = $por_prioridad;

} catch (Exception $e) {
    if (isset($response)) {
        $response['por_prioridad'] = [];
        $response['error_general'] = $e->getMessage();
    }
}

// Helper: limpia bytes UTF-8 inválidos de cualquier string o array anidado.
function sanitize_utf8_adv($value)
{
    if (is_array($value)) {
        return array_map('sanitize_utf8_adv', $value);
    }
    if (is_string($value)) {
        // Detectar si ya es UTF-8 o si viene con ISO-8859-1 (común en servidores antiguos con Ñ)
        $enc = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1'], true);
        if ($enc === 'ISO-8859-1') {
            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
    return $value;
}

if (isset($conn) && $conn instanceof mysqli)
    $conn->close();

if (ob_get_length())
    ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
$response = sanitize_utf8_adv($response);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
?>