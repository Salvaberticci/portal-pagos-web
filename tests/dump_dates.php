<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$c = new \Services\WispHubClient(include 'config/wisp_hub.php');
echo "=== PROFILE ===\n";
print_r($c->getServiceProfile('902')['data']);
echo "=== BALANCE ===\n";
print_r($c->getServiceBalance('902')['data']);
