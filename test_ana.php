<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$c = new \Services\WispHubClient($config);

// Get pending invoices for Ana Villegas
$invs = $c->getInvoices(['cedula' => 'V26094384', 'estado' => 1]);
echo "Facturas pendientes de V26094384:\n";
foreach ($invs as $inv) {
    $id = $inv['id_factura'] ?? 'N/A';
    $desc = trim(preg_replace('/\s+/', ' ', $inv['articulos'][0]['descripcion'] ?? 'N/A'));
    echo "  #$id - total: \${$inv['total']} - $desc\n";
}
echo "\nTodas las facturas de V26094384:\n";
$all = $c->getInvoices(['cedula' => 'V26094384']);
foreach ($all as $inv) {
    $id = $inv['id_factura'] ?? 'N/A';
    $estado = $inv['estado'] ?? 'N/A';
    $desc = trim(preg_replace('/\s+/', ' ', $inv['articulos'][0]['descripcion'] ?? 'N/A'));
    echo "  #$id [$estado] total:\${$inv['total']} - " . substr($desc, 0, 80) . "\n";
}
