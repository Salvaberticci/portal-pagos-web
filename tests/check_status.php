<?php
require_once __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config/wisp_hub.php';

$client = new \GuzzleHttp\Client([
    'base_uri' => $config['base_url'] . '/',
    'timeout'  => 15,
    'verify'   => $config['verify_ssl'] ?? false,
    'headers'  => [
        'Authorization' => "Api-Key {$config['api_key']}",
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
]);

echo "=== Estado del servicio 902 ===\n";
$resp = $client->get('clientes/902/saldo/');
$data = json_decode($resp->getBody(), true);
echo "Estado: " . ($data['estado'] ?? 'N/A') . "\n";
echo "Saldo (deuda): " . ($data['saldo'] ?? 'N/A') . "\n";

$resp2 = $client->get('clientes/902/');
$data2 = json_decode($resp2->getBody(), true);
echo "Estado servicio: " . ($data2['estado'] ?? 'N/A') . "\n";
echo "facturas_pagadas: " . ($data2['facturas_pagadas'] ?? 'N/A') . "\n";
