<?php
require '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $field = isset($_POST['field']) ? $_POST['field'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    
    // Lista blanca de campos permitidos para editar inline
    $allowedFields = ['vendedor_texto', 'sae_plus'];
    
    if ($id > 0 && in_array($field, $allowedFields)) {
        // Prepare statement
        $value = $conn->real_escape_string($value);
        $sql = "UPDATE contratos SET $field = '$value' WHERE id = $id";
        
        if ($conn->query($sql)) {
            echo "OK";
        } else {
            http_response_code(500);
            echo "Error: " . $conn->error;
        }
    } else {
        http_response_code(400);
        echo "Invalid Request";
    }
}
$conn->close();
?>
