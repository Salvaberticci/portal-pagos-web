<?php
// obtener_parroquias.php
require_once '../conexion.php'; 

header('Content-Type: application/json');

// Captura el ID del municipio enviado por AJAX
$id_municipio = isset($_GET['id_municipio']) ? (int)$_GET['id_municipio'] : 0;

$parroquias = [];

if ($id_municipio > 0) {
    // Usar prepared statement para seguridad
    $sql = "SELECT id_parroquia, nombre_parroquia FROM parroquia WHERE id_municipio = ? ORDER BY nombre_parroquia";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $id_municipio);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        while ($fila = $resultado->fetch_assoc()) {
            $parroquias[] = $fila;
        }
        $stmt->close();
    }
}

// Devuelve el resultado en formato JSON
echo json_encode($parroquias);
?>