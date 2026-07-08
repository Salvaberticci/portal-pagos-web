<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$c = new \Services\WispHubClient($config);

// Get specific invoice 9852
$inv = $c->getInvoiceDetail('9852');
print_r($inv);
