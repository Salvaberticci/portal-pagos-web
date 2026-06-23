<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

// Verificar detalle de la factura 9821 para ver si tiene promesa
echo "=== Detalle factura 9821 ===\n";
$detail = $wispClient->getInvoiceDetail('9821');
print_r($detail);

// Listar todas las facturas del cliente para ver estado 
echo "\n=== Facturas del cliente (por nombre de usuario) ===\n";
$all_inv = $wispClient->getInvoices(['cliente' => 'onu_prueba_oficina@sitelco', 'limit' => 10]);
foreach ($all_inv as $inv) {
    $id = $inv['id_factura'] ?? $inv['id'] ?? 0;
    $estado = $inv['estado'] ?? 'N/A';
    $total = $inv['total'] ?? 0;
    $cobrado = $inv['total_cobrado'] ?? 0;
    $saldo = $inv['saldo'] ?? $inv['saldo_nuevo'] ?? 0;
    echo "ID: $id | Estado: $estado | Total: $total | Cobrado: $cobrado | Saldo: $saldo\n";
}

// Listar facturas pendientes del servicio
echo "\n=== Facturas pendientes service 902 ===\n";
$pending = $wispClient->getPendingInvoices('902');
print_r($pending);
