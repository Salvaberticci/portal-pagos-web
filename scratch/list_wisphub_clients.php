<?php
// scratch/list_wisphub_clients.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/wisp_hub.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';

echo "Using API Base URL: " . $wispConfig['base_url'] . "\n";
echo "Using API Key: " . $wispConfig['api_key'] . "\n";

$client = new \GuzzleHttp\Client([
    'base_uri' => $wispConfig['base_url'],
    'timeout'  => 15,
    'verify'   => false,
    'headers'   => [
        'Authorization' => "Bearer {$wispConfig['api_key']}",
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
]);

try {
    // According to OpenAPI, querying /clientes/ via GET or POST will return the client list.
    // In WispHub, it is usually GET /clientes/ or POST /clientes/ with pagination parameters.
    // Let's try GET first. If that fails, we can try POST.
    echo "Sending GET to /clientes/...\n";
    $response = $client->request('GET', 'clientes/');
    echo "HTTP Status: " . $response->getStatusCode() . "\n";
    $body = (string) $response->getBody();
    echo "Raw response body:\n" . $body . "\n";
    $data = json_decode($body, true);
} catch (\Exception $e) {
    echo "GET failed: " . $e->getMessage() . "\n";
    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response body: " . (string) $e->getResponse()->getBody() . "\n";
    }
    
    // Try POST /clientes/
    try {
        echo "\nSending POST to /clientes/...\n";
        $response = $client->request('POST', 'clientes/', [
            'json' => []
        ]);
        echo "HTTP Status: " . $response->getStatusCode() . "\n";
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        echo "Response count: " . (is_array($data) ? count($data) : "not an array") . "\n";
        print_r(array_slice($data, 0, 5));
    } catch (\Exception $e2) {
        echo "POST failed: " . $e2->getMessage() . "\n";
        if ($e2 instanceof \GuzzleHttp\Exception\RequestException && $e2->hasResponse()) {
            echo "Response body: " . (string) $e2->getResponse()->getBody() . "\n";
        }
    }
}
?>
