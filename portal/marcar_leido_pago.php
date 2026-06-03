<?php
// portal/marcar_leido_pago.php
session_start();
require '../paginas/conexion.php';

if (!isset($_SESSION['cliente_cedula'])) {
    http_response_code(403);
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $cedula = $_SESSION['cliente_cedula'];
    
    // Asegurarse de que el reporte pertenezca al cliente en sesión
    $sql = "UPDATE pagos_reportados SET visto_por_cliente = 1 WHERE id_reporte = ? AND cedula_titular = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $id, $cedula);
        $stmt->execute();
        echo json_encode(["status" => "ok"]);
    } else {
        http_response_code(500);
    }
}
?>
