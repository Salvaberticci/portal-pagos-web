<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$c = new \Services\WispHubClient($config);

// Get pending invoices
$invs = $c->getInvoices(['estado' => 1, 'limit' => 5]); // 1 = Pendiente
print_r($invs);
