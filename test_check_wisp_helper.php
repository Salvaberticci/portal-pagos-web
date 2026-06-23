<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

require_once __DIR__ . '/portal/wisp_helper.php';

$_GET['refreshed'] = 1; // force refresh
$data = wisp_get_cached_data($client, '902');

echo "Facturas devueltas por wisp_get_cached_data:\n";
echo "Count: " . count($data['invoices']) . "\n";
foreach ($data['invoices'] as $inv) {
    echo "- ID: {$inv['id']}, Total: {$inv['total']}, Estado: " . ($inv['estado'] ?? 'N/A') . "\n";
}
