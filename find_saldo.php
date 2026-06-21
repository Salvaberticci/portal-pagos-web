<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';

$wispClient = new \Services\WispHubClient($wispConfig);

echo "Buscando clientes con saldo negativo en la lista...\n";
$page = 0;
$found = 0;
while ($page < 20) {
    $res = $wispClient->listClients(['limit' => 100, 'offset' => $page * 100]);
    if ($res['status'] !== 200 || empty($res['data']['results'])) {
        break;
    }
    
    foreach ($res['data']['results'] as $c) {
        if (isset($c['saldo']) && floatval($c['saldo']) < 0) {
            echo "Encontrado (saldo < 0): " . $c['nombre'] . " | Cedula: " . $c['cedula'] . " | Saldo: " . $c['saldo'] . "\n";
            $found++;
        }
        if (isset($c['saldo_favor']) && floatval($c['saldo_favor']) > 0) {
            echo "Encontrado (saldo_favor > 0): " . $c['nombre'] . " | Cedula: " . $c['cedula'] . " | Saldo: " . $c['saldo_favor'] . "\n";
            $found++;
        }
        if ($found >= 5) break 2;
    }
    $page++;
}

if ($found === 0) {
    echo "No se encontraron clientes.\n";
}
