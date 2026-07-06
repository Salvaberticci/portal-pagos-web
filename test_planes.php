<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$c = include 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($c);

$reflection = new \ReflectionMethod($wispClient, 'request');
$reflection->setAccessible(true);
$plan = $reflection->invoke($wispClient, 'GET', 'planes/319642/');
print_r($plan);
