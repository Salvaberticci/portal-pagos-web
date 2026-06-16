<?php
/**
 * Búsqueda AJAX de Clientes
 * Endpoint para Select2 - busca por nombre, cédula o IP
 */

header('Content-Type: application/json');
require_once '../conexion.php';

try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Si no hay query, retornar array vacío
    if (empty($query)) {
        echo json_encode([]);
        exit;
    }

    // Escapar query para evitar SQL injection
    $query = $conn->real_escape_string($query);

    // Buscar en nombre, cédula o IP (sin filtro de estado por si no existe el campo)
    $sql = "SELECT 
                id,
                nombre_completo,
                cedula,
                ip,
                telefono,
                direccion,
                sector
            FROM contratos
            WHERE (
                nombre_completo LIKE '%$query%' 
                OR cedula LIKE '%$query%'
                OR ip LIKE '%$query%'
            )
            ORDER BY nombre_completo ASC
            LIMIT 20";

    $result = $conn->query($sql);

    $clientes = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clientes[] = [
                'id' => $row['id'],
                'nombre_completo' => $row['nombre_completo'] ?? '',
                'cedula' => $row['cedula'] ?? '',
                'ip' => $row['ip'] ?? '',
                'telefono' => $row['telefono'] ?? '',
                'direccion' => $row['direccion'] ?? '',
                'sector' => $row['sector'] ?? ''
            ];
        }
    }

    $conn->close();

    // SIEMPRE retornar array, nunca objeto con error
    echo json_encode($clientes);

} catch (Exception $e) {
    // En caso de error, retornar array vacío (Select2 requiere array)
    error_log("Error en buscar_clientes_ajax.php: " . $e->getMessage());
    echo json_encode([]);
}
?>