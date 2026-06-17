<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$cfg = include 'config/wisp_hub.php';
$c = new \Services\WispHubClient($cfg);
echo "Suspendiendo...\n";
var_dump($c->suspendService('902', 'test'));
echo "Activando...\n";
var_dump($c->activateService('902'));
