<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "Test 1: id_servicio => 902\n";
$res1 = $wispClient->getInvoices(['id_servicio' => 902]);
echo count($res1) . " invoices\n";

echo "Test 2: servicio => 902\n";
$res2 = $wispClient->getInvoices(['servicio' => 902]);
echo count($res2) . " invoices\n";

echo "Test 3: usuario => onu_prueba_oficina@sitelco\n";
$res3 = $wispClient->getInvoices(['usuario' => 'onu_prueba_oficina@sitelco']);
echo count($res3) . " invoices\n";
