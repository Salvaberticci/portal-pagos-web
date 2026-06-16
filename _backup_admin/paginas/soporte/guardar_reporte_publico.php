<?php
// paginas/soporte/guardar_reporte_publico.php
// Scripts para guardar el REPORTE TÉCNICO (Versión Pública)

require_once '../conexion.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Función auxiliar para guardar firmas
    function saveSignature($base64_string, $prefix)
    {
        if (empty($base64_string))
            return null;

        $data = explode(',', $base64_string);
        // Validar formato data:image/png;base64,...
        if (count($data) < 2)
            return null;

        $imgData = base64_decode($data[1]);
        $fileName = $prefix . '_' . uniqid() . '.png';
        $uploadDir = '../../uploads/firmas/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filePath = $uploadDir . $fileName; // Ruta relativa desde este script

        if (file_put_contents($filePath, $imgData)) {
            return $fileName;
        }
        return null;
    }

    $id_contrato = isset($_POST['id_contrato']) ? intval($_POST['id_contrato']) : 0;
    $fecha = isset($_POST['fecha']) ? $conn->real_escape_string($_POST['fecha']) : date('Y-m-d');
    $hora_solucion = isset($_POST['hora_solucion']) ? $conn->real_escape_string($_POST['hora_solucion']) : date('H:i:s');
    $tiempo_transcurrido = isset($_POST['tiempo_transcurrido']) ? $conn->real_escape_string($_POST['tiempo_transcurrido']) : '';

    // Datos Técnicos
    $sector = isset($_POST['sector']) ? $conn->real_escape_string($_POST['sector']) : '';
    $tipo_servicio = isset($_POST['tipo_servicio']) ? $conn->real_escape_string($_POST['tipo_servicio']) : '';
    $ip = isset($_POST['ip']) ? $conn->real_escape_string($_POST['ip']) : '';

    $estado_onu = isset($_POST['estado_onu']) ? $conn->real_escape_string($_POST['estado_onu']) : '';
    $estado_router = isset($_POST['estado_router']) ? $conn->real_escape_string($_POST['estado_router']) : '';
    $modelo_router = isset($_POST['modelo_router']) ? $conn->real_escape_string($_POST['modelo_router']) : '';

    $bw_bajada = isset($_POST['bw_bajada']) ? $conn->real_escape_string($_POST['bw_bajada']) : '';
    $bw_subida = isset($_POST['bw_subida']) ? $conn->real_escape_string($_POST['bw_subida']) : '';
    $bw_ping = isset($_POST['bw_ping']) ? $conn->real_escape_string($_POST['bw_ping']) : '';
    $num_dispositivos = isset($_POST['num_dispositivos']) ? intval($_POST['num_dispositivos']) : 0;

    $estado_antena = isset($_POST['estado_antena']) ? $conn->real_escape_string($_POST['estado_antena']) : '';
    $valores_antena = isset($_POST['valores_antena']) ? $conn->real_escape_string($_POST['valores_antena']) : '';

    $observaciones = isset($_POST['observaciones']) ? $conn->real_escape_string($_POST['observaciones']) : '';
    $tipo_falla = isset($_POST['tipo_falla']) ? $conn->real_escape_string($_POST['tipo_falla']) : '';
    $sugerencias = isset($_POST['sugerencias']) ? $conn->real_escape_string($_POST['sugerencias']) : '';
    $solucion_completada = isset($_POST['solucion_completada']) ? 1 : 0;
    $id_olt = (isset($_POST['id_olt']) && !empty($_POST['id_olt'])) ? intval($_POST['id_olt']) : 'NULL';
    $id_pon = (isset($_POST['id_pon']) && !empty($_POST['id_pon'])) ? intval($_POST['id_pon']) : 'NULL';

    // Monto (Costo de visita)
    $monto_total = isset($_POST['monto_total']) ? floatval($_POST['monto_total']) : 0.00;
    $monto_pagado = isset($_POST['monto_pagado']) ? floatval($_POST['monto_pagado']) : 0.00;
    $saldo_pendiente = $monto_total - $monto_pagado;

    // Firmas (Base64)
    $firma_tecnico_b64 = isset($_POST['firma_tecnico_data']) ? $_POST['firma_tecnico_data'] : '';
    $firma_cliente_b64 = isset($_POST['firma_cliente_data']) ? $_POST['firma_cliente_data'] : '';

    // Prioridad
    $prioridad = isset($_POST['prioridad']) ? $conn->real_escape_string($_POST['prioridad']) : 'NIVEL 1';

    if ($id_contrato > 0) {
        $conn->begin_transaction();
        try {
            // Guardar imagenes
            $path_firma_tecnico = saveSignature($firma_tecnico_b64, 'tech');
            $path_firma_cliente = saveSignature($firma_cliente_b64, 'cli');

            // Descripción autogenerada para el listado simple
            $descripcion_corta = "Visita Técnica ($tipo_servicio) - $tipo_falla";
            $tecnico_nombre = !empty($_POST['tecnico']) ? $conn->real_escape_string($_POST['tecnico']) : "Reporte Digital";
            $telefono = isset($_POST['telefono']) ? $conn->real_escape_string($_POST['telefono']) : "";

            $sql = "INSERT INTO soportes (
                id_contrato, telefono, descripcion, monto_total, monto_pagado, fecha_soporte, hora_solucion, tiempo_transcurrido, tecnico_asignado, observaciones, tipo_falla,
                sector, tipo_servicio, ip_address, estado_onu, estado_router, modelo_router,
                bw_bajada, bw_subida, bw_ping, num_dispositivos,
                estado_antena, valores_antena, sugerencias, solucion_completada,
                firma_tecnico, firma_cliente, prioridad, id_olt, id_pon
            ) VALUES (
                '$id_contrato', '$telefono', '$descripcion_corta', '$monto_total', '$monto_pagado', '$fecha', '$hora_solucion', '$tiempo_transcurrido', '$tecnico_nombre', '$observaciones', '$tipo_falla',
                '$sector', '$tipo_servicio', '$ip', '$estado_onu', '$estado_router', '$modelo_router',
                '$bw_bajada', '$bw_subida', '$bw_ping', '$num_dispositivos',
                '$estado_antena', '$valores_antena', '$sugerencias', '$solucion_completada',
                '$path_firma_tecnico', '$path_firma_cliente', '$prioridad', $id_olt, $id_pon
            )";

            if (!$conn->query($sql)) {
                throw new Exception("Error SQL: " . $conn->error);
            }
            $id_soporte = $conn->insert_id;

            // Sync with junction table for multi-OLT support (even for single OLT reports)
            if ($id_olt !== 'NULL') {
                $stmt_soa = $conn->prepare("INSERT INTO soporte_olts_afectados (id_soporte, id_olt, id_pon) VALUES (?, ?, ?)");
                $pon_val = ($id_pon !== 'NULL') ? $id_pon : null;
                $stmt_soa->bind_param("iii", $id_soporte, $id_olt, $pon_val);
                $stmt_soa->execute();
                $stmt_soa->close();
            }

            // Generar Deuda (Si hay cobro)
            if ($monto_total > 0) {
                $fecha_vencimiento = date('Y-m-d', strtotime($fecha . ' + 7 days'));

                $sql_cxc = "INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado) 
                            VALUES ('$id_contrato', '$fecha', '$fecha_vencimiento', '$monto_total', 'PENDIENTE')";

                if ($conn->query($sql_cxc)) {
                    $id_cobro = $conn->insert_id;
                    $conn->query("UPDATE soportes SET id_cobro = '$id_cobro' WHERE id_soporte = '$id_soporte'");

                    $justificacion = "Visita Técnica #$id_soporte (Reporte Digital)";
                    $sql_historial = "INSERT INTO cobros_manuales_historial (id_cobro_cxc, id_contrato, autorizado_por, justificacion, monto_cargado) 
                                      VALUES ('$id_cobro', '$id_contrato', 'Sistema Web', '$justificacion', '$monto_total')";
                    $conn->query($sql_historial);
                }
            }

            // Register as debtor if outstanding balance exists
            if ($saldo_pendiente > 0) {
                // Check if debtor already exists for this contract
                $check_deudor = $conn->query("SELECT id FROM clientes_deudores 
                                              WHERE id_contrato = '$id_contrato' 
                                              AND estado = 'PENDIENTE'");

                if ($check_deudor->num_rows > 0) {
                    // Update existing debtor record
                    $sql_update_deudor = "UPDATE clientes_deudores 
                                          SET monto_total = monto_total + $monto_total,
                                              saldo_pendiente = saldo_pendiente + $saldo_pendiente,
                                              notas = CONCAT(notas, ' | Reporte Técnico #$id_soporte')
                                          WHERE id_contrato = '$id_contrato' 
                                          AND estado = 'PENDIENTE'";
                    $conn->query($sql_update_deudor);
                } else {
                    // Insert new debtor record
                    $sql_deudor = "INSERT INTO clientes_deudores 
                                   (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado, notas) 
                                   VALUES ('$id_contrato', '$monto_total', '$monto_pagado', '$saldo_pendiente', 
                                           'PENDIENTE', 'Reporte Técnico #$id_soporte')";
                    $conn->query($sql_deudor);
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'msg' => 'Reporte guardado correctamente.', 'id_soporte' => $id_soporte]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Datos incompletos o cliente no seleccionado.']);
    }
}
?>