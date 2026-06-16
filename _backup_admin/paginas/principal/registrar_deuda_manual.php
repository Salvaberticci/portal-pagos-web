<?php
/**
 * Registrar Deuda Manualmente
 */
require_once '../conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_contrato = isset($_POST['id_contrato']) ? intval($_POST['id_contrato']) : 0;
    $monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
    $notas = isset($_POST['notas']) ? $conn->real_escape_string($_POST['notas']) : '';

    if ($id_contrato <= 0) {
        echo json_encode(['success' => false, 'message' => 'Por favor, seleccione un contrato válido del buscador.']);
        exit;
    }

    if ($monto <= 0) {
        echo json_encode(['success' => false, 'message' => 'El monto de la deuda debe ser mayor a cero.']);
        exit;
    }

    // Verificar si el contrato existe
    $check = $conn->query("SELECT id FROM contratos WHERE id = $id_contrato");
    if ($check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'El contrato seleccionado ya no existe en el sistema.']);
        exit;
    }

    // Insertar en clientes_deudores
    // saldo_pendiente es igual al monto_total inicialmente ya que monto_pagado es 0
    $sql = "INSERT INTO clientes_deudores (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado, notas, fecha_registro) 
            VALUES (?, ?, 0, ?, 'PENDIENTE', ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error de preparación SQL: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("idds", $id_contrato, $monto, $monto, $notas);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'La deuda se ha registrado correctamente para el cliente seleccionado.',
            'id_deuda' => $stmt->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar el registro: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}

$conn->close();
