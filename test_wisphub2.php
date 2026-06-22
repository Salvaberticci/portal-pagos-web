<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "=== Test registrar-pago con 851396 ===\n";
// Llamar a registerPaymentAndActivate con ID de factura explícito
$res = $wispClient->registerPaymentAndActivate(
    '858',
    10.00,
    '851396',
    '2026-06-20',
    1,
    false,
    '',
    [9784]
);
print_r($res);
