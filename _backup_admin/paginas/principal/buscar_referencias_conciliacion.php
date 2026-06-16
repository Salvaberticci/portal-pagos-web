<?php
// buscar_referencias_conciliacion.php
require_once '../conexion.php';

header('Content-Type: application/json');

// Recibir JSON con lista de referencias: ["12345", "67890", ...]
$data = json_decode(file_get_contents('php://input'), true);
$referencias = $data['referencias'] ?? [];

$resultados = [];

if (!empty($referencias)) {
    foreach ($referencias as $ref) {
        $ref_clean = $conn->real_escape_string($ref);

        // Buscar en cuentas_por_cobrar (pagos procesados de mensualidades)
        $sql = "SELECT cxc.monto_total as monto_pagado, cxc.fecha_pago, c.nombre_completo, c.id as id_contrato, 
                       'PAGO REGISTRADO' as tipo, cxc.capture_pago as capture_path
                FROM cuentas_por_cobrar cxc
                JOIN contratos c ON cxc.id_contrato = c.id
                WHERE cxc.referencia_pago LIKE '%$ref_clean%' 
                   OR cxc.referencia_pago = '$ref_clean'";

        $res = $conn->query($sql);

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $row['referencia'] = $ref;
                $row['encontrado'] = true;
                $resultados[] = $row;
            }
        } else {
            // Buscar en pagos_reportados (pendientes de aprobación)
            $sql2 = "SELECT id_reporte, fecha_pago, 
                            nombre_titular as nombre_completo, id_contrato_asociado as id_contrato, 
                            'REPORTE_WEB' as tipo, estado, cedula_titular, id_banco_destino, 
                            metodo_pago, meses_pagados, concepto, capture_path
                     FROM pagos_reportados
                     WHERE referencia LIKE '%$ref_clean%'
                        OR referencia = '$ref_clean'";

            $res2 = $conn->query($sql2);

            if ($res2 && $res2->num_rows > 0) {
                while ($row = $res2->fetch_assoc()) {
                    $row['monto_pagado'] = 'POR VERIFICAR'; // No hay monto en la tabla pagos_reportados
                    $row['referencia'] = $ref;
                    $row['encontrado'] = true;
                    $resultados[] = $row;
                }
            } else {
                // No encontrado
                $resultados[] = [
                    'referencia' => $ref,
                    'encontrado' => false,
                    'monto_pagado' => 0,
                    'nombre_completo' => 'N/A',
                    'tipo' => 'N/A'
                ];
            }
        }
    }
}

echo json_encode($resultados);
?>