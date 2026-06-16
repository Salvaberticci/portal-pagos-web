<?php
require 'paginas/conexion.php';

// Simularemos la función de guarda.php:
function getMunicipioId($conn, $nombre)
{
    $nombre = trim($conn->real_escape_string($nombre));
    if (empty($nombre))
        return null;
    $sql = "SELECT id_municipio FROM municipio WHERE nombre_municipio = '$nombre' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0)
        return $res->fetch_assoc()['id_municipio'];
    if ($conn->query("INSERT INTO municipio (nombre_municipio) VALUES ('$nombre')"))
        return $conn->insert_id;
    return null;
}

function getParroquiaId($conn, $nombre, $id_municipio)
{
    $nombre = trim($conn->real_escape_string($nombre));
    if (empty($nombre) || empty($id_municipio))
        return null;
    $sql = "SELECT id_parroquia FROM parroquia WHERE nombre_parroquia = '$nombre' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0)
        return $res->fetch_assoc()['id_parroquia'];
    if ($conn->query("INSERT INTO parroquia (nombre_parroquia, id_municipio) VALUES ('$nombre', $id_municipio)"))
        return $conn->insert_id;
    return null;
}

$mun = "Test Muni";
$par = "Test Parroquia";

$id_mun = getMunicipioId($conn, $mun);
$id_par = getParroquiaId($conn, $par, $id_mun);

echo "ID Mun: $id_mun \n";
echo "ID Par: $id_par \n";

// Emular insert
$sql = "INSERT INTO contratos (
    cedula, nombre_completo, telefono, id_municipio, id_parroquia, municipio_texto, parroquia_texto, plan_prorrateo_nombre, observaciones,
    direccion, ident_caja_nap, puerto_nap, num_presinto_odn
) VALUES (
    '12345678', 'Test User', '0000', ?, ?, ?, ?, 'Test', 'TEST CONTRACT',
    'TEST DIR', 'CAJA001', '1', 'PRE001'
)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $id_mun, $id_par, $mun, $par);
if ($stmt->execute()) {
    echo "Contrato insertado ID: " . $conn->insert_id . "\n";
    
    // Test fetch
    $q = $conn->query("SELECT c.id, m.nombre_municipio, p.nombre_parroquia, c.municipio_texto, c.parroquia_texto 
        FROM contratos c 
        LEFT JOIN municipio m ON c.id_municipio = m.id_municipio
        LEFT JOIN parroquia p ON c.id_parroquia = p.id_parroquia
        WHERE c.id = " . $conn->insert_id);
    
    print_r($q->fetch_assoc());
} else {
    echo "Error: " . $conn->error;
}
?>
