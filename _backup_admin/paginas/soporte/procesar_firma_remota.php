<?php
// paginas/soporte/procesar_firma_remota.php
// Backend para guardar la firma recibida desde firmar_remoto.php

require_once '../conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$token = isset($_POST['token']) ? $conn->real_escape_string($_POST['token']) : '';
$type = isset($_POST['type']) ? $conn->real_escape_string($_POST['type']) : '';
$firma_data = isset($_POST['firma_data']) ? $_POST['firma_data'] : '';

if (empty($token) || empty($firma_data)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// 1. Guardar imagen de firma
function saveSignatureFromBase64($base64_string, $prefix)
{
    $data = explode(',', $base64_string);
    if (count($data) < 2)
        return null;

    $imgData = base64_decode($data[1]);
    $fileName = $prefix . '_Remoto_' . uniqid() . '.png';
    $uploadDir = '../../uploads/firmas/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (file_put_contents($uploadDir . $fileName, $imgData)) {
        return $fileName;
    }
    return null;
}

$firmaPath = saveSignatureFromBase64($firma_data, 'cliente');

if (!$firmaPath) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen de la firma']);
    exit;
}

// 2. Actualizar Tabla correspondiente
try {
    $conn->begin_transaction();

    if ($type === 'soporte') {
        // Verificar validez del token
        $check = $conn->query("SELECT id_soporte FROM soportes WHERE token_firma = '$token' AND estado_firma = 'PENDIENTE'");
        if ($check->num_rows === 0) {
            throw new Exception("Token inválido o documento ya firmado");
        }

        $sql = "UPDATE soportes SET 
                firma_cliente = '$firmaPath', 
                estado_firma = 'COMPLETADO', 
                token_firma = NULL 
                WHERE token_firma = '$token'";

        if (!$conn->query($sql)) {
            throw new Exception("Error al actualizar soporte: " . $conn->error);
        }

    } else if ($type === 'contrato') {
        // Verificar validez del token
        $check = $conn->query("SELECT id FROM contratos WHERE token_firma = '$token' AND estado_firma = 'PENDIENTE'");
        if ($check->num_rows === 0) {
            throw new Exception("Token inválido o documento ya firmado");
        }

        $sql = "UPDATE contratos SET 
                firma_cliente = '$firmaPath', 
                estado_firma = 'COMPLETADO', 
                token_firma = NULL 
                WHERE token_firma = '$token'";

        if (!$conn->query($sql)) {
            throw new Exception("Error al actualizar contrato: " . $conn->error);
        }
    } else {
        throw new Exception("Tipo de documento desconocido");
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>