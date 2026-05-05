<?php
require __DIR__ . '/../paginas/conexion.php';

// Verificar si la tabla 'bancos' existe
$tablaExiste = $conn->query("SHOW TABLES LIKE 'bancos'");
if ($tablaExiste && $tablaExiste->num_rows > 0) {
    echo "TABLA BANCOS: EXISTE\n";
    $r = $conn->query("SELECT id_banco, nombre_banco, numero_cuenta FROM bancos ORDER BY id_banco ASC");
    if ($r) {
        while ($b = $r->fetch_assoc()) {
            echo $b['id_banco'] . " | " . $b['nombre_banco'] . " | " . $b['numero_cuenta'] . "\n";
        }
    } else {
        echo "Error al consultar: " . $conn->error . "\n";
    }
} else {
    echo "TABLA BANCOS: NO EXISTE\n";
    echo "Usando JSON como fuente alternativa.\n";
    $json = file_get_contents(__DIR__ . '/../paginas/principal/bancos.json');
    $bancos = json_decode($json, true);
    foreach ($bancos as $b) {
        echo $b['id_banco'] . " | " . $b['nombre_banco'] . "\n";
    }
}
