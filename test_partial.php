<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$res = $client->notifyPayment([
    'invoice_id' => 9814, // some test invoice
    'total_cobrado' => 0.01,
    'referencia' => 'TEST_PARTIAL_PAYMENT_1',
    'accion' => 1
]);
print_r($res);
