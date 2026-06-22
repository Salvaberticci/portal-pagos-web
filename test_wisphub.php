<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "=== Test registerPaymentAndActivate con invoices == 0 ===\n";
// El cliente 858 tiene 1 factura pendiente. Vamos a probar con un invoice_ids ficticio para forzar que invoices_found == 0
$res = $wispClient->registerPaymentAndActivate(
    '858',
    10.00,
    '851396',
    '2026-06-21',
    1,
    false,
    '',
    [9999999] // ID falso
);

print_r($res);
