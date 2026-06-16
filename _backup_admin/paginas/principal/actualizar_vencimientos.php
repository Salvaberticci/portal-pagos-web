<?php
// SCRIPT DESACTIVADO - Simplificación de Estados
// Por solicitud del usuario, el estado 'VENCIDO' ha sido unificado con 'PENDIENTE'.
// Todo cargo no pagado permanecerá como 'PENDIENTE' independientemente de su fecha.
/*
require_once '../conexion.php'; 
$fecha_hoy = date('Y-m-d');
$sql = "UPDATE cuentas_por_cobrar SET estado = 'VENCIDO' WHERE estado = 'PENDIENTE' AND fecha_vencimiento < ?";
...
*/
echo "Este script ha sido desactivado permanentemente.";
?>