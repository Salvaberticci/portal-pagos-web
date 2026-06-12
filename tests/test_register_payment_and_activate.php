<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$serviceId = '902';
$amount = 5.00;
$reference = 'TEST_REG_PAGO_' . date('YmdHis');
$paymentDate = date('Y-m-d H:i');

echo "=== 1. Estado actual del servicio $serviceId ===\n";
$balance = $client->getServiceBalance($serviceId);
echo "Status: " . ($balance['status'] ?? '?') . "\n";
if (!empty($balance['data'])) {
    echo "Estado: " . ($balance['data']['estado'] ?? 'N/A') . "\n";
    echo "Facturas pendientes: " . count($balance['data']['facturas'] ?? []) . "\n";
    echo "Saldo: " . ($balance['data']['saldo'] ?? 'N/A') . "\n";
}
echo "\n";

echo "=== 2. registerPaymentAndActivate ===\n";
echo "Monto: \$$amount, Ref: $reference, Fecha: $paymentDate\n";
$result = $client->registerPaymentAndActivate($serviceId, $amount, $reference, $paymentDate);
echo "Invoices found: " . ($result['invoices_found'] ?? '?') . "\n";
echo "Payments registered: " . count($result['payments_registered'] ?? []) . "\n";
foreach ($result['payments_registered'] ?? [] as $p) {
    echo "  Invoice {$p['invoice_id']}: HTTP {$p['status']}\n";
}
echo "Activation:\n";
echo "  " . json_encode($result['activation'], JSON_UNESCAPED_UNICODE) . "\n";
echo "Overall status: " . ($result['status'] ?? '?') . "\n";
echo "\n";

echo "=== 3. Estado después ===\n";
$balance2 = $client->getServiceBalance($serviceId);
echo "Estado: " . ($balance2['data']['estado'] ?? 'N/A') . "\n";
echo "Facturas pendientes: " . count($balance2['data']['facturas'] ?? []) . "\n";
echo "Saldo: " . ($balance2['data']['saldo'] ?? 'N/A') . "\n";

echo "\n=== 4. Probar de nuevo (debe reportar \"Servicio ya activo\") ===\n";
$result2 = $client->registerPaymentAndActivate($serviceId, $amount, 'TEST_DUP_' . date('YmdHis'), $paymentDate);
echo "Activation: " . json_encode($result2['activation'], JSON_UNESCAPED_UNICODE) . "\n";
echo "Payments registered: " . count($result2['payments_registered'] ?? []) . "\n";
