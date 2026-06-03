<?php
// webhook_pagoflash.php
// Este archivo recibe la confirmación de PagoFlash

require '../paginas/conexion.php';
require 'config_pagoflash.php';

// Log para depurar (útil en ambiente de pruebas)
$log_data = date('Y-m-d H:i:s') . " - RECIBIDO DE PAGOFLASH:\n";
$log_data .= "GET: " . print_r($_GET, true) . "\n";
$log_data .= "POST: " . print_r($_POST, true) . "\n";
$log_data .= "------------------------\n";
file_put_contents(__DIR__ . '/pagoflash.log', $log_data, FILE_APPEND);

// Identificar de dónde viene la data (POST o GET)
$data = !empty($_POST) ? $_POST : $_GET;

// Variables comunes en la respuesta de PagoFlash
$status   = isset($data['status']) ? intval($data['status']) : null; 
$order_id = isset($data['order']) ? $data['order'] : (isset($data['p_order_id']) ? $data['p_order_id'] : null);

if ($status === 1) { // 1 = Aprobado/Procesado
    // Extraer ID de contrato del order_id (Formato: ORD-IDCONTRATO-TIMESTAMP)
    $partes = explode('-', $order_id);
    if (count($partes) >= 2 && $partes[0] === 'ORD') {
        $id_contrato = intval($partes[1]);
        
        // 1. Obtener datos del cliente y deuda
        $stmt = $conn->prepare("SELECT saldo_pendiente FROM clientes_deudores WHERE id_contrato = ? AND estado = 'PENDIENTE' LIMIT 1");
        $stmt->bind_param("i", $id_contrato);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $deuda = $res->fetch_assoc();
            $monto_pagado = floatval($deuda['saldo_pendiente']);
            
            // 2. Marcar deuda como pagada en clientes_deudores
            $stmt_update = $conn->prepare("UPDATE clientes_deudores SET estado = 'PAGADO', monto_pagado = monto_pagado + ? WHERE id_contrato = ? AND estado = 'PENDIENTE'");
            $stmt_update->bind_param("di", $monto_pagado, $id_contrato);
            $stmt_update->execute();
            
            // 3. Registrar el pago en pagos_reportados como aprobado directamente
            // Nota: Para integrarlo limpiamente, lo registramos como Aprobado.
            $metodo = "PagoFlash (Automático)";
            $referencia = isset($data['authorization']) ? $data['authorization'] : $order_id;
            $fecha_pago = date('Y-m-d');
            $concepto = "Pago electrónico de deuda pendiente vía PagoFlash";
            $id_banco_destino = 0; // O el ID del banco comodín
            
            $sql_insert = "INSERT INTO pagos_reportados 
                (id_contrato_asociado, fecha_pago, metodo_pago, referencia, monto_usd, concepto) 
                VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_ins = $conn->prepare($sql_insert);
            if ($stmt_ins) {
                $stmt_ins->bind_param("isssds", $id_contrato, $fecha_pago, $metodo, $referencia, $monto_pagado, $concepto);
                $stmt_ins->execute();
            }
        }
    }
    
    // Si el usuario es redirigido aquí a través del navegador, mostrar éxito
    session_start();
    $_SESSION['pago_msg'] = "¡Pago exitoso! Tu cuenta ha sido actualizada automáticamente.";
    header("Location: dashboard.php");
    exit;

} else {
    // Pago no completado o error
    session_start();
    $_SESSION['login_error'] = "El pago no pudo ser completado o fue cancelado.";
    header("Location: dashboard.php");
    exit;
}
