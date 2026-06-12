<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

// Probar con producción
$config = [
    'api_key'    => 'ubxyK8jE.BoTLrjCN8zRDaaybVL6E3X270cojY15W',
    'api_secret' => '',
    'base_url'   => 'https://api.wisphub.net/api',
];

$client = new \Services\WispHubClient($config);

echo "=== Probando endpoints contra PRODUCCION ===\n\n";

echo "1. listClients()\n";
$r = $client->listClients();
echo "   HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

echo "2. getServiceProfile('1')\n";
$r = $client->getServiceProfile('1');
echo "   HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

echo "3. getServiceProfile('prueba')\n";
$r = $client->getServiceProfile('prueba');
echo "   HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

echo "4. notifyPayment (para ver si el endpoint existe y qué account_id devuelve)\n";
$r = $client->notifyPayment([
    'payment_id' => 99999,
    'reference' => 'TEST-' . time(),
    'amount_usd' => 1.00,
    'currency' => 'USD',
    'date' => date('Y-m-d'),
    'customer_cedula' => 'V99999999',
]);
echo "   HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Si ves HTTP 200/201 con account_id en notifyPayment, ese es el ID que necesitas ===\n";
echo "=== Tambien prueba con getServiceProfile('TU_ID') si conoces algun ID ===\n";
