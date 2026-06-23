<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);
$res = $client->getInvoices(['cliente' => 'onu_prueba_oficina@sitelco', 'estado' => 1]);
print_r($res);
