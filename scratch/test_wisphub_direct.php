<?php
// scratch/test_wisphub_direct.php
// Direct cURL tests to understand the correct endpoint format for WispHub Sandbox

$apiKey = 'ubxyK8jE.BoTLrjCN8zRDaaybVL6E3X270cojY15W';

function wisphub_call($method, $url, $apiKey, $data = null) {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    $isJson = strlen($response) > 0 && ($response[0] === '{' || $response[0] === '[');
    return [
        'code' => $httpCode,
        'json' => $isJson,
        'body' => $isJson ? json_decode($response, true) : substr(strip_tags($response), 0, 200),
        'error' => $err,
    ];
}

echo "=== WispHub Sandbox — Direct cURL Tests ===\n\n";

// URLs to test (without Guzzle path interference)
$baseUrl = 'https://sandbox-api.wisphub.net/api/';

// Test GET /clientes/
echo "1. GET /clientes/\n";
$r = wisphub_call('GET', $baseUrl . 'clientes/', $apiKey);
echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
if ($r['json']) {
    echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
}

// Test POST /clientes/ (WispHub list uses POST)
echo "\n2. POST /clientes/ (empty body)\n";
$r = wisphub_call('POST', $baseUrl . 'clientes/', $apiKey, []);
echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
if ($r['json']) {
    echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
}

// Test POST /clientes/ with a test service ID pattern
$testIds = ['1', '100', '1000', '10000'];
foreach ($testIds as $testId) {
    echo "\n3. POST /clientes/activar/$testId/\n";
    $r = wisphub_call('POST', $baseUrl . "clientes/activar/$testId/", $apiKey, []);
    echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
    if ($r['json']) {
        echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
        if ($r['code'] !== 404) break; // Found a valid format; stop testing IDs
    } else {
        echo "   Body: {$r['body']}\n";
    }
    
    echo "\n4. POST /clientes/suspender/$testId/\n";
    $r = wisphub_call('POST', $baseUrl . "clientes/suspender/$testId/", $apiKey, ['reason' => 'Test de corte']);
    echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
    if ($r['json']) {
        echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    if ($r['json'] && $r['code'] !== 404) break;
}

// Test the activar/desactivar endpoint with different action path patterns  
echo "\n5. POST /clientes/activar/ (with list body)\n";
$r = wisphub_call('POST', $baseUrl . "clientes/activar/", $apiKey, ['id_servicios' => [1]]);
echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
if ($r['json']) {
    echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n6. POST /clientes/desactivar/ (with list body)\n";
$r = wisphub_call('POST', $baseUrl . "clientes/desactivar/", $apiKey, ['id_servicios' => [1]]);
echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
if ($r['json']) {
    echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
}

// Test /facturas/ to see if read access works for any endpoint
echo "\n7. GET /facturas/\n";
$r = wisphub_call('GET', $baseUrl . 'facturas/', $apiKey);
echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
if ($r['json']) {
    echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n8. POST /facturas/ (empty body)\n";
$r = wisphub_call('POST', $baseUrl . 'facturas/', $apiKey, []);
echo "   HTTP {$r['code']} | JSON: " . ($r['json'] ? 'YES' : 'NO') . "\n";
if ($r['json']) {
    echo "   Body: " . json_encode($r['body'], JSON_UNESCAPED_UNICODE) . "\n";
}
?>
