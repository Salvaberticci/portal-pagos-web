<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "Buscando clientes con saldo < totalFacturas...\n";
$page = 0;
$found = 0;
while ($page < 20) {
    $res = $wispClient->listClients(['limit' => 100, 'offset' => $page * 100]);
    if ($res['status'] !== 200 || empty($res['data']['results'])) {
        break;
    }
    
    foreach ($res['data']['results'] as $c) {
        $saldo = floatval($c['saldo'] ?? 0);
        if ($saldo > 0) {
            $id = $c['id_servicio'] ?? $c['id'];
            $saldoRes = $wispClient->getServiceBalance($id);
            if ($saldoRes['status'] === 200 && !empty($saldoRes['data'])) {
                $facturas = $saldoRes['data']['facturas'] ?? [];
                $totalFacturas = 0;
                foreach ($facturas as $f) {
                    $totalFacturas += floatval($f['total'] ?? $f['monto_pendiente'] ?? 0);
                }
                if ($totalFacturas > 0 && $saldo < $totalFacturas) {
                    echo "Encontrado (saldo < facturas): " . $c['nombre'] . " | Cedula: " . $c['cedula'] . " | Saldo: " . $saldo . " | Facturas: " . $totalFacturas . "\n";
                    $found++;
                    if ($found >= 3) break 2;
                }
            }
        }
    }
    $page++;
}

if ($found === 0) {
    echo "No se encontraron clientes.\n";
}
