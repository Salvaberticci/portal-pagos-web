<?php
// scratch/test_wisphub_auth.php
// Tests multiple authentication formats to find the correct one for WispHub
require_once __DIR__ . '/../vendor/autoload.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';

$apiKey = $wispConfig['api_key'];
$baseUrl = 'https://sandbox-api.wisphub.net/api/';

echo "Testing WispHub Sandbox API authentication\n";
echo "Base URL: $baseUrl\n";
echo "API Key: $apiKey\n";
echo str_repeat('-', 60) . "\n\n";

// Format the base auth attempts
$authFormats = [
    'Bearer token' => "Bearer $apiKey",
    'Api-Key header' => "Api-Key $apiKey",
    'Token format' => "Token $apiKey",
    'Raw API Key' => $apiKey,
];

foreach ($authFormats as $format => $authValue) {
    echo "Testing auth format: '$format' => '$authValue'\n";
    
    $ch = curl_init($baseUrl . 'clientes/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authValue,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        echo "  ❌ cURL error: $err\n\n";
        continue;
    }
    
    // Check if response looks like JSON vs HTML
    $isJson = strlen($response) > 0 && ($response[0] === '{' || $response[0] === '[');
    $firstLine = substr($response, 0, 150);
    
    if ($isJson) {
        $parsed = json_decode($response, true);
        echo "  ✅ HTTP $httpCode — JSON response received!\n";
        echo "  Response: " . json_encode(array_slice((array)$parsed, 0, 3), JSON_PRETTY_PRINT) . "\n\n";
    } else {
        $isLoginPage = strpos($response, 'id_login') !== false || strpos($response, 'Iniciar sesión') !== false;
        echo "  ⚠️  HTTP $httpCode — " . ($isLoginPage ? "Login page (REJECTED - auth failed)" : "Non-JSON response") . "\n";
        echo "  First 100 chars: " . substr(strip_tags($firstLine), 0, 100) . "\n\n";
    }
}

// Also test the correct format from the OpenAPI spec: "Api-Key" in name field
// The spec says `name: Authorization` and `type: apiKey` which means the full header
// should just be: Authorization: <key>  (no prefix)
echo "\nTesting with correct OpenAPI apiKey format (Authorization: <raw_key>):\n";
$ch = curl_init($baseUrl . 'clientes/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$isJson = strlen($response) > 0 && ($response[0] === '{' || $response[0] === '[');
if ($isJson) {
    echo "✅ HTTP $httpCode — JSON with raw key! Response: " . substr($response, 0, 300) . "\n";
} else {
    $isLoginPage = strpos($response, 'id_login') !== false;
    echo "⚠️  HTTP $httpCode — " . ($isLoginPage ? "Login page (REJECTED)" : "Unknown response") . "\n";
}

// Check GET /clientes/ with a comma-separated list format (WispHub style with pagination)
echo "\n\nTesting GET /clientes/ with Authorization: Api-Key <key>:\n";
$ch = curl_init($baseUrl . 'clientes/?page=1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,  // Don't follow - see the actual redirect
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HEADER => true,           // Include headers in response
    CURLOPT_HTTPHEADER => [
        'Authorization: Api-Key ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $httpCode\nFull response (first 500 chars):\n" . substr($response, 0, 500) . "\n";
?>
