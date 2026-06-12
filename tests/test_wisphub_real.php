<?php
// tests/test_wisphub_real.php - Test against live production WispHub API
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Services/WispHubClient.php';

$config = include dirname(__DIR__) . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($config);

$serviceId = '902';

echo "=== WispHub API v1.2 — Test contra Producción ===\n\n";

echo "1. Listar clientes (página 1, 2 resultados)...\n";
$res = $client->listClients(['page' => 1, 'limit' => 2]);
echo "   HTTP {$res['status']}: " . ($res['data']['count'] ?? '?') . " clientes total\n";
if (!empty($res['data']['results'][0])) {
    $first = $res['data']['results'][0];
    echo "   Primer cliente: ID={$first['id_servicio']} - {$first['nombre']} [{$first['estado']}]\n";
}
echo "\n";

echo "2. Perfil del servicio $serviceId...\n";
$res = $client->getServiceProfile($serviceId);
echo "   HTTP {$res['status']}: {$res['data']['nombre']} {$res['data']['apellidos']} (C.I: {$res['data']['cedula']})\n";
echo "\n";

echo "3. Saldo del servicio $serviceId...\n";
$res = $client->getServiceBalance($serviceId);
echo "   HTTP {$res['status']}: Estado={$res['data']['estado']}, Saldo=\${$res['data']['saldo']}\n";
if (!empty($res['data']['facturas'])) {
    foreach ($res['data']['facturas'] as $f) {
        echo "   Factura #{$f['id']}: \${$f['total']} vence {$f['fecha_vencimiento']}\n";
    }
}
echo "\n";

echo "4. Activar servicio $serviceId...\n";
$res = $client->activateService($serviceId);
echo "   HTTP {$res['status']}: " . json_encode($res['data'] ?? $res['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";
echo "\n";

echo "5. Desactivar servicio $serviceId...\n";
$res = $client->suspendService($serviceId, 'Corte por vencimiento - prueba');
echo "   HTTP {$res['status']}: " . json_encode($res['data'] ?? $res['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";
echo "\n";

echo "=== Prueba completada ===\n";
