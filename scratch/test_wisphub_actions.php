<?php
// scratch/test_wisphub_actions.php
// Tests suspend and activate endpoints against a real client in the Sandbox
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$apiKey = $wispConfig['api_key'];
$baseUrl = 'https://sandbox-api.wisphub.net/api/';

echo "=== WispHub Sandbox — Action Tests ===\n";
echo "Base URL: $baseUrl\n";
echo "API Key: $apiKey\n\n";

// 1. First, POST /clientes/ to get the list of clients in this Sandbox account
echo "--- Step 1: Listing clients (POST /clientes/) ---\n";
$ch = curl_init($baseUrl . 'clientes/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['page' => 1, 'page_size' => 5]),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $httpCode: ";
$data = json_decode($response, true);
if ($data !== null) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} else {
    echo "(not JSON) " . substr($response, 0, 200) . "\n\n";
}

// 2. Try to get the first service ID from the response
$firstServiceId = null;
if (isset($data['results']) && is_array($data['results']) && count($data['results']) > 0) {
    $firstServiceId = $data['results'][0]['id_servicio'] ?? $data['results'][0]['id'] ?? null;
    echo "Found first client service ID: $firstServiceId\n\n";
} elseif (isset($data[0])) {
    $firstServiceId = $data[0]['id_servicio'] ?? $data[0]['id'] ?? null;
    echo "Found first client service ID: $firstServiceId\n\n";
}

// 3. Test suspend endpoint
if ($firstServiceId) {
    echo "--- Step 2: Testing suspend on service ID: $firstServiceId ---\n";
    $result = $client->suspendService($firstServiceId, 'Test de corte por sistema administrativo');
    echo "HTTP Status: " . $result['status'] . "\n";
    if (isset($result['data'])) {
        echo "Response data: " . json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } elseif (isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n\n";
    }
    
    // 4. Test activate endpoint
    echo "--- Step 3: Testing activate on service ID: $firstServiceId ---\n";
    $result = $client->activateService($firstServiceId);
    echo "HTTP Status: " . $result['status'] . "\n";
    if (isset($result['data'])) {
        echo "Response data: " . json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } elseif (isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n\n";
    }
} else {
    echo "No service IDs found. Trying with a test ID (123456)...\n";
    $testId = '123456';
    
    echo "--- Suspend service $testId ---\n";
    $result = $client->suspendService($testId, 'Test de corte');
    echo "HTTP " . $result['status'] . ": " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "--- Activate service $testId ---\n";
    $result = $client->activateService($testId);
    echo "HTTP " . $result['status'] . ": " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n\n";
}

// 5. Test notifyPayment
echo "--- Step 4: Testing notifyPayment ---\n";
$paymentPayload = [
    'payment_id'      => 99999,
    'contract_id'     => null,
    'reference'       => 'REF' . time(),
    'amount_usd'      => 15.00,
    'amount_bs'       => 600.00,
    'currency'        => 'USD',
    'date'            => date('Y-m-d'),
    'customer_cedula' => 'V99999999',
];
$result = $client->notifyPayment($paymentPayload);
echo "HTTP " . $result['status'] . ": " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
?>
