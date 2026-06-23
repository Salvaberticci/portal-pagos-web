<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$ids = [
    9818 => 5,
    9817 => 20,
    9816 => 20,
    9815 => 20
];

foreach ($ids as $id => $total) {
    echo "Pagando factura de prueba: $id por $total...\n";
    $res = $client->notifyPayment([
        'invoice_id' => $id,
        'total_cobrado' => $total,
        'referencia' => 'TEST_CLEANUP_' . $id,
        'accion' => 1 // 1 = Pagar y activar
    ]);
    print_r($res);
}
