<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
require_once __DIR__ . '/../paginas/conexion.php';

$config = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($config);

$cedula_test = 'V99999999';

echo "=== Preparando entorno de prueba ===\n\n";

// Buscar contrato
$r = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test'");
if (!$r || $r->num_rows === 0) {
    die("No hay contrato para V99999999\n");
}
$cid = $r->fetch_assoc()['id'];
echo "Contrato #$cid\n";

// 1. Asegurar que wisp_hub_links apunte a 902
$conn->query("UPDATE wisp_hub_links SET wisp_account_id = '902' WHERE contract_id = $cid");
echo "✅ wisp_hub_links → 902\n";

// 2. Verificar que el servicio 902 está SUSPENDIDO en WispHub
$saldo = $client->getServiceBalance('902');
$estadoActual = $saldo['data']['estado'] ?? 'desconocido';
echo "📡 Servicio 902 en WispHub: $estadoActual\n";

// 3. Si está ACTIVO, suspenderlo primero
if (strtolower($estadoActual) === 'activo') {
    echo "   → Suspendiendo servicio para empezar desde estado correcto...\n";
    $client->suspendService('902', 'Preparando entorno de prueba');
    sleep(2);
    echo "   ✅ Suspendido\n";
}

// 4. Crear una CxC PENDIENTE nueva
$conn->query("UPDATE cuentas_por_cobrar SET estado = 'PENDIENTE', fecha_vencimiento = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id_contrato = $cid ORDER BY id_cobro DESC LIMIT 1");
echo "✅ CxC actualizada a PENDIENTE\n";

// 5. Mostrar resumen
echo "\n=== Resumen final ===\n";
echo "Portal: http://localhost/sistemas-administrativo-tecnico-wireless/portal/index.php\n";
echo "Usuario: V99999999\n";
echo "Deuda pendiente: \$1.00\n";
echo "Referencia a reportar: 999222\n";
echo "Monto: 1.00\n\n";
echo "WispHub servicio 902: $estadoActual → se activará al pagar\n\n";

echo "=== SIMULAR PAGO DESDE LÍNEA DE COMANDOS ===\n";
echo "Ejecutar:\n";
echo "  php tests/test_wisphub_activation_direct.php\n\n";
echo "O desde el navegador:\n";
echo "  http://localhost/sistemas-administrativo-tecnico-wireless/portal/index.php\n";

$conn->close();
