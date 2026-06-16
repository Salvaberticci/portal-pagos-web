<?php
require_once '../conexion.php';

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$params = json_decode($json, true);

$action = $params['action'] ?? '';

if (!$action) {
    echo json_encode(["success" => false, "message" => "Acción no especificada"]);
    exit;
}

try {
    if ($action === 'limpiar_temporales') {
        // Borrar temporales que ya fueron procesadas (o simplemente todas las no regulares si se desea limpieza total)
        // Usaremos el criterio de borrar las NO REGULARES que ya tienen pago detectado en el mes actual o simplemente las que ya pasaron
        $sql = "DELETE FROM prorrogas WHERE prorroga_regular = 'NO' AND estado = 'PROCESADO'";
        $conn->query($sql);
        $affected = $conn->affected_rows;
        echo json_encode(["success" => true, "message" => "Se eliminaron $affected prórrogas temporales procesadas"]);
    } 
    elseif ($action === 'avanzar_mes') {
        // Avanzar la fecha de corte exactamente un mes para las permanentes
        $sql = "UPDATE prorrogas SET fecha_corte = DATE_ADD(fecha_corte, INTERVAL 1 MONTH) WHERE prorroga_regular = 'SI'";
        $conn->query($sql);
        $affected = $conn->affected_rows;
        echo json_encode(["success" => true, "message" => "Se actualizó la fecha de $affected prórrogas permanentes"]);
    } 
    else {
        echo json_encode(["success" => false, "message" => "Acción no válida"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
