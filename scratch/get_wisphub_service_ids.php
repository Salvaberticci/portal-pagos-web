<?php
// scratch/get_wisphub_service_ids.php
// Tries all known ways to list clients in WispHub Sandbox to find real service IDs

$apiKey = 'ubxyK8jE.BoTLrjCN8zRDaaybVL6E3X270cojY15W';
$base   = 'https://sandbox-api.wisphub.net/api/';

function wh($method, $url, $apiKey, $data = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ]);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $r    = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($r, true);
    return ['code' => $code, 'raw' => $r, 'json' => $j, 'is_json' => ($j !== null)];
}

echo "=== Looking for Sandbox client service IDs ===\n\n";

// Try all known variations to list clients
$attempts = [
    ['GET',  $base . 'clientes/', null],
    ['GET',  $base . 'clientes/?page=1', null],
    ['GET',  $base . 'clientes/?limite=5', null],
    ['POST', $base . 'clientes/', ['page' => 1]],
    ['GET',  $base . 'clientes/?status=activo', null],
    ['GET',  $base . 'servicios/', null],
    ['GET',  $base . 'servicios/?page=1', null],
];

foreach ($attempts as [$method, $url, $data]) {
    $path = str_replace($base, '', $url);
    echo "[$method $path]\n";
    $r = wh($method, $url, $apiKey, $data);
    echo "  HTTP {$r['code']}";
    if ($r['is_json']) {
        echo " | JSON response:\n";
        // Look for service IDs in the response
        $json = $r['json'];
        if (isset($json['results']) && is_array($json['results'])) {
            echo "  count: " . count($json['results']) . "\n";
            foreach (array_slice($json['results'], 0, 3) as $item) {
                $id = $item['id_servicio'] ?? $item['id'] ?? '?';
                $name = $item['nombre'] ?? $item['name'] ?? '';
                echo "  → ID: $id | Name: $name\n";
            }
        } else {
            echo "  " . json_encode($json, JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo " | Non-JSON response\n";
    }
    echo "\n";
}

// Also try the /me/ or /user/ endpoint to see what company this key belongs to
echo "[GET empresa/]\n";
$r = wh('GET', $base . 'empresa/', $apiKey);
echo "  HTTP {$r['code']}: " . ($r['is_json'] ? json_encode($r['json'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : "(HTML)") . "\n";

echo "\n[GET me/]\n";
$r = wh('GET', $base . 'me/', $apiKey);
echo "  HTTP {$r['code']}: " . ($r['is_json'] ? json_encode($r['json'], JSON_UNESCAPED_UNICODE) : "(HTML)") . "\n";

echo "\n[GET usuarios/me/]\n";
$r = wh('GET', $base . 'usuarios/me/', $apiKey);
echo "  HTTP {$r['code']}: " . ($r['is_json'] ? json_encode($r['json'], JSON_UNESCAPED_UNICODE) : "(HTML)") . "\n";
?>
