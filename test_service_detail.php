<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$c = include 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($c);

$detail = $wispClient->getServiceDetail('902');
print_r($detail);
