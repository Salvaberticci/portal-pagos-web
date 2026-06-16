<?php
require '../conexion.php';

echo "<h2>Iniciando Limpieza de Residuos de Deuda</h2>";

// 1. Contar cuántos registros califican
$count_res = $conn->query("SELECT COUNT(*) as total FROM clientes_deudores WHERE saldo_pendiente <= 0.50 AND saldo_pendiente > 0 AND estado = 'PENDIENTE'");
$total = $count_res->fetch_assoc()['total'];

if ($total == 0) {
    echo "<p>No se encontraron residuos menores o iguales a $0.50. La base de datos está limpia.</p>";
} else {
    echo "<p>Se han detectado <strong>$total</strong> registros con residuos (<= $0.50).</p>";
    
    // 2. Ejecutar la limpia
    $sql = "UPDATE clientes_deudores 
            SET estado = 'PAGADO', 
                saldo_pendiente = 0, 
                monto_pagado = monto_total, 
                notas = CONCAT(IFNULL(notas, ''), ' | Liquidación automática de residuo insignificante (<$0.50)') 
            WHERE saldo_pendiente <= 0.50 AND estado = 'PENDIENTE'";
    
    if ($conn->query($sql)) {
        echo "<h3 style='color: green;'>✓ Éxito: Se han procesado y limpiado $total registros.</h3>";
        echo "<p>Estos clientes ya no aparecerán en la lista de deudores pendientes.</p>";
    } else {
        echo "<h3 style='color: red;'>✗ Error: " . $conn->error . "</h3>";
    }
}

echo "<br><a href='gestion_deudores.php'>Volver a Deudores</a>";
?>
