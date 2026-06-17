<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$c = new \Services\WispHubClient(include 'config/wisp_hub.php');
$res = $c->findClientByDocument('V20788775');
echo "findClientByDocument:\n";
var_dump($res['data']['data']['estado'] ?? 'no-estado');
var_dump($res);
