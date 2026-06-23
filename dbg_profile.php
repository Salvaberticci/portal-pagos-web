<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$profileRes = $client->getServiceProfile('902');
echo "=== getServiceProfile ===\n";
print_r($profileRes['data'] ?? $profileRes);

$detailRes = $client->getServiceDetail('902');
echo "\n=== getServiceDetail ===\n";
print_r($detailRes['data'] ?? $detailRes);
