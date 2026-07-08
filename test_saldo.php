<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$c = new \Services\WispHubClient($config);

// Get all pending invoices and find saldo pendiente ones
$invs = $c->getInvoices(['estado' => 1, 'limit' => 50]);
echo "Facturas pendientes con 'Saldo pendiente':\n";
foreach ($invs as $inv) {
    $id = $inv['id_factura'] ?? 'N/A';
    $cedula = $inv['cliente']['cedula'] ?? 'N/A';
    $nombre = $inv['cliente']['nombre'] ?? 'N/A';
    $desc = trim(preg_replace('/\s+/', ' ', $inv['articulos'][0]['descripcion'] ?? ''));
    if (stripos($desc, 'Saldo pendiente') !== false || stripos($desc, 'Saldo Pendiente') !== false) {
        echo "  #$id [$cedula] $nombre - total:\${$inv['total']} - $desc\n";
    }
}
