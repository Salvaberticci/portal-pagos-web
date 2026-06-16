<?php
// test_search_api.php
require_once __DIR__ . '/../paginas/conexion.php';

echo "Testing search query logic...\n";

$res = $conn->query("SELECT nombre_completo, cedula FROM contratos WHERE estado = 'ACTIVO' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $q = $row['cedula'];
    echo "Searching for: $q\n";
    
    // Test the search SQL directly (avoids header/CLI issues with buscar_contratos.php)
    $search_query = $conn->real_escape_string($q);
    $sql = "SELECT c.id, c.nombre_completo, c.cedula, c.telefono
            FROM contratos c
            WHERE c.nombre_completo LIKE '%$search_query%' 
               OR c.cedula LIKE '%$search_query%'
            LIMIT 10";
    
    $resultado = $conn->query($sql);
    $data = [];
    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $data[] = $fila;
        }
    }
    
    if (!empty($data)) {
        echo "Found " . count($data) . " results.\n";
        echo "Example Result:\n";
        print_r($data[0]);
        
        if (isset($data[0]['telefono'])) {
            echo "SUCCESS: 'telefono' field is present.\n";
        } else {
            echo "FAILURE: 'telefono' field is MISSING.\n";
        }
    } else {
        echo "FAILURE: No results found for '$q'.\n";
    }
} else {
    echo "No active contracts found to test with.\n";
}
