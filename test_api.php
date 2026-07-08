<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$c = new \Services\WispHubClient($config);

// Get specific invoice 9905 (from previous dump)
$inv = $c->getInvoiceDetail('9905');
echo "Factura #9905:\n";
echo "fecha_emision: " . $inv['fecha_emision'] . "\n";
echo "fecha_vencimiento: " . $inv['fecha_vencimiento'] . "\n";
echo "fecha_pago: " . $inv['fecha_pago'] . "\n";
