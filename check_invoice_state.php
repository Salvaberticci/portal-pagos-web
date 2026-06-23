<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "Invoices for service 902:\n";
$invoices = $wispClient->getInvoices(['cliente' => 'onu_prueba_oficina@sitelco', 'limit' => 5, 'ordering' => '-id']);
foreach ($invoices as $inv) {
    echo "ID: {$inv['id_factura']} | Total: {$inv['total']} | Cobrado: {$inv['total_cobrado']} | Saldo: {$inv['saldo']} | Estado: {$inv['estado']} | Vence: {$inv['fecha_vencimiento']}\n";
}

echo "\nPending Invoices for service 902:\n";
$pending = $wispClient->getPendingInvoices('902');
foreach ($pending as $inv) {
    echo "ID: " . ($inv['id'] ?? $inv['id_factura']) . " | Total: {$inv['total']} | Vence: {$inv['fecha_vencimiento']}\n";
}
