<?php
$db = new PDO('mysql:host=localhost;dbname=portal_pagos;charset=utf8', 'root', '');
$stmt = $db->query("SELECT * FROM pagos_registrados WHERE referencia LIKE '%139627%'");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Resultados:\n";
print_r($results);
