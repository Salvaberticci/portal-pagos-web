<?php
// Archivo: mostrar_frase_random.php

// Asegúrate de incluir la conexión, asumiendo que está un nivel arriba.
// El archivo 'conexion.php' debe estar accesible desde la ruta de 'menu.php'
// Depende de dónde lo guardes, puede que necesites '../conexion.php' o 'conexion.php'.
require_once 'conexion.php'; 

// 1. Consulta SQL para obtener una frase aleatoria
// ORDER BY RAND() es la forma más simple de obtener un registro al azar en MySQL.
$sql_frase = "SELECT frase, autor FROM frases_motivacionales ORDER BY RAND() LIMIT 1";
$resultado_frase = $conn->query($sql_frase);

$frase = "¡Bienvenido!";
$autor = "Wireless Supply, C.A.";

if ($resultado_frase && $resultado_frase->num_rows > 0) {
    $data = $resultado_frase->fetch_assoc();
    $frase = htmlspecialchars($data['frase']);
    $autor = htmlspecialchars($data['autor']);
}

// 2. Generar el HTML para mostrar la frase con estilos Bootstrap
echo "<div class='text-center p-3' style='max-width: 600px; margin: 20px auto;'>";
echo "<p class='lead mb-1 text-primary'>\"" . $frase . "\"</p>";
echo "<footer class='blockquote-footer'>" . $autor . "</footer>";
echo "</div>";

// NOTA: Es crucial NO cerrar la conexión ($conn->close()) aquí
// si planeas usarla en el resto de tu aplicación.
?>