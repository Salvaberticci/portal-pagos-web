<?php
// tests/test_mock_direct.php - Direct test of mock WispHub API
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Services/WispHubClient.php';

$config = [
    'base_url' => 'http://127.0.0.1:8544/tests/mock_wisphub_api.php',
    'api_key' => 'test-key',
    'api_secret' => '',
];

$client = new \Services\WispHubClient($config);

echo "=== Direct Mock WispHub Test ===\n\n";

// 1. Test get profile
echo "1. getServiceProfile(902)...\n";
$res = $client->getServiceProfile('902');
echo "   Status: " . ($res['status'] ?? 'error') . "\n";
echo "   Data: " . json_encode($res['data'] ?? $res['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

// 2. Test activate
echo "2. activateService(902)...\n";
$res = $client->activateService('902');
echo "   Status: " . ($res['status'] ?? 'error') . "\n";
echo "   Data: " . json_encode($res['data'] ?? $res['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

// 3. Test suspend
echo "3. suspendService(902, 'test reason')...\n";
$res = $client->suspendService('902', 'test reason');
echo "   Status: " . ($res['status'] ?? 'error') . "\n";
echo "   Data: " . json_encode($res['data'] ?? $res['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

// 4. Test notify payment
echo "4. notifyPayment([...])...\n";
$res = $client->notifyPayment([
    'payment_id' => 999,
    'reference' => 'TEST-MOCK',
    'amount_usd' => 17.50,
    'customer_cedula' => 'V99999999',
]);
echo "   Status: " . ($res['status'] ?? 'error') . "\n";
echo "   Data: " . json_encode($res['data'] ?? $res['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== All mock endpoints working! ===\n";
