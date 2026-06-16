<?php
require dirname(__DIR__) . '/paginas/conexion.php';
$cedula = 'V20788775';
echo "=== Contratos de $cedula ===\n";
$r = $conn->query("SELECT id, estado, monto_plan FROM contratos WHERE cedula = '$cedula' AND estado != 'ELIMINADO'");
while ($c = $r->fetch_assoc()) {
    echo "Contrato #{$c['id']}: {$c['estado']} \${$c['monto_plan']}\n";
}
$r = $conn->query("SELECT COUNT(*) as total FROM cuentas_por_cobrar WHERE id_contrato IN (SELECT id FROM contratos WHERE cedula = '$cedula')");
$cnt = $r->fetch_assoc()['total'];
echo "Total CxC registros: $cnt\n";
$r = $conn->query("SELECT estado, COUNT(*) as cantidad FROM cuentas_por_cobrar WHERE id_contrato IN (SELECT id FROM contratos WHERE cedula = '$cedula') GROUP BY estado");
while ($e = $r->fetch_assoc()) {
    echo "  [{$e['estado']}]: {$e['cantidad']}\n";
}
$conn->close();
