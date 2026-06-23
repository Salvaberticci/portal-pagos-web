<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$invoices = $client->getInvoices([
    'cliente' => 'onu_prueba_oficina@sitelco',
    'estado'  => 1,
    'limit'   => 5
]);

foreach ($invoices as $inv) {
    echo "- ID: {$inv['id_factura']}, Total: {$inv['total']}, Cobrado: {$inv['total_cobrado']}, Saldo: {$inv['saldo']}\n";
}
