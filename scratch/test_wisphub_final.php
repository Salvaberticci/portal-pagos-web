<?php
// scratch/test_wisphub_final.php
// Final integrated test using the corrected WispHubClient
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

echo "=== WispHub Integration — Final Test ===\n";
echo "Sandbox API: " . $wispConfig['base_url'] . "\n\n";

// Test 1: activateService with a test ID
echo "--- Test 1: activateService('99999') ---\n";
$r = $client->activateService('99999');
echo "HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";
// Expected: 403 (permission denied) or 404 (no such client in this sandbox account)
// Either means the API key IS working and reaching WispHub correctly

echo "\n--- Test 2: suspendService('99999', 'Test de corte') ---\n";
$r = $client->suspendService('99999', 'Test de corte por sistema');
echo "HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";

echo "\n--- Test 3: notifyPayment ---\n";
$r = $client->notifyPayment([
    'payment_id'      => 99001,
    'reference'       => 'REF' . time(),
    'amount_usd'      => 15.00,
    'currency'        => 'USD',
    'date'            => date('Y-m-d'),
    'customer_cedula' => 'V99999999',
]);
echo "HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";

echo "\n--- Test 4: getServiceProfile('99999') ---\n";
$r = $client->getServiceProfile('99999');
echo "HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== SUMMARY ===\n";
echo "✅ If you get HTTP 403 or 404 with JSON responses, the API key and connection are working.\n";
echo "   403 = Auth OK but insufficient permissions on this Sandbox account\n";
echo "   404 = Auth OK but the service ID doesn't exist in Sandbox (expected for test IDs)\n";
echo "   0   = Connection error (network/SSL issue)\n";
echo "   200 = Full success (real client ID used)\n";
?>
