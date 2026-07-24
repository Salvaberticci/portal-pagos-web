<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
@include_once __DIR__ . '/../config/wisphub_credentials.php';

$nodo = $_GET['nodo'] ?? 'sitelco';
$cedula = $_GET['cedula'] ?? '30236536';

$accountMap = ['jalisco' => 'jalisco', 'sitelco' => 'sitelco', 'pampanito' => 'pampanito'];
$ref = $accountMap[$nodo] ?? 'sitelco';

$creds = $WISPHUB_ACCOUNTS[$ref];
$baseUrl = rtrim($creds['base_url'], '/') . '/';

// Use Guzzle directly with longer timeouts
$http = new \GuzzleHttp\Client([
    'base_uri'        => $baseUrl,
    'timeout'         => 20,
    'connect_timeout' => 10,
    'verify'          => $creds['verify_ssl'] ?? false,
    'headers'         => [
        'Authorization' => "Api-Key {$creds['api_key']}",
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
]);

echo "<h2>Diagnóstico: $ref</h2>";
echo "<p>Base URL: $baseUrl</p>";
echo "<p>Hora: " . date('Y-m-d H:i:s') . "</p>";
flush();

// 1. Find client via API
echo "<h3>1. Buscar cliente por cédula $cedula...</h3>";
flush();
try {
    $resp = $http->get('clientes/', ['query' => ['cedula' => $cedula, 'limit' => 1]]);
    $body = json_decode((string)$resp->getBody(), true);
    $data = $body['data'] ?? $body['results'] ?? $body;
    $cliente = is_array($data) ? (reset($data) ?: []) : [];
    $serviceId = $cliente['service_id'] ?? $cliente['id_servicio'] ?? '';
    $username = $cliente['usuario'] ?? $cliente['username'] ?? '';
    echo "<p>Service ID: <strong>$serviceId</strong> | Username: <strong>$username</strong></p>";
} catch (\Exception $e) {
    echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
    exit;
}
flush();

// 2. Get invoices directly (without profile)
echo "<h3>2. Facturas pendientes (estado=1)...</h3>";
flush();
try {
    $resp = $http->get('facturas/', ['query' => ['cliente' => $username, 'estado' => 1, 'limit' => 50]]);
    $invoices = json_decode((string)$resp->getBody(), true);
    $results = $invoices['results'] ?? $invoices['data'] ?? $invoices;
    echo "<p>Count: " . count($results) . "</p>";
    foreach ($results as $inv) {
        $id = $inv['id_factura'] ?? $inv['id'] ?? 0;
        $total = floatval($inv['total'] ?? 0);
        $cobrado = floatval($inv['total_cobrado'] ?? 0);
        $pendiente = max(0, $total - $cobrado);
        $desc = '';
        foreach (($inv['articulos'] ?? []) as $art) {
            $desc .= ($art['descripcion'] ?? '') . ' | ';
        }
        echo "<p>#$id: total=\${$total} cobrado=\${$cobrado} pendiente=\${$pendiente} | " . htmlspecialchars(substr($desc, 0, 100)) . "</p>";
    }
} catch (\Exception $e) {
    echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
}
flush();

// 3. All invoices
echo "<h3>3. Todas facturas (sin filtro)...</h3>";
flush();
try {
    $resp = $http->get('facturas/', ['query' => ['cliente' => $username, 'limit' => 20]]);
    $all = json_decode((string)$resp->getBody(), true);
    $results2 = $all['results'] ?? $all['data'] ?? $all;
    echo "<p>Count: " . count($results2) . "</p>";
    foreach ($results2 as $inv) {
        $id = $inv['id_factura'] ?? $inv['id'] ?? 0;
        $estado = $inv['estado'] ?? '?';
        $total = floatval($inv['total'] ?? 0);
        $cobrado = floatval($inv['total_cobrado'] ?? 0);
        echo "<p>#$id | estado: $estado | total: \$$total | cobrado: \$$cobrado</p>";
    }
} catch (\Exception $e) {
    echo "<p style='color:red'>ERROR: " . $e->getMessage() . "</p>";
}
echo "<p>FIN - " . date('Y-m-d H:i:s') . "</p>";
