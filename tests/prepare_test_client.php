<?php
/**
 * Preparar cliente de prueba para test real:
 * 1. Verificar saldo actual
 * 2. Suspender servicio (servicio cortado)
 * 3. Verificar resultado
 */

require_once __DIR__ . '/../vendor/autoload.php';
$config = include __DIR__ . '/../config/wisp_hub.php';

$client = new \GuzzleHttp\Client([
    'base_uri' => $config['base_url'] . '/',
    'timeout'  => 15,
    'verify'   => $config['verify_ssl'] ?? false,
    'headers'  => [
        'Authorization' => "Api-Key {$config['api_key']}",
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
]);

$serviceId = '902';

echo "=== PASO 1: Verificar estado actual del servicio ===\n";
try {
    $resp = $client->get("clientes/{$serviceId}/saldo/");
    $data = json_decode($resp->getBody(), true);
    echo "  Estado: " . ($data['estado'] ?? 'N/A') . "\n";
    echo "  Saldo: " . ($data['saldo'] ?? 'N/A') . "\n";
    echo "  Saldo favor: " . ($data['saldo_favor'] ?? 'N/A') . "\n";
    $facturas = $data['facturas'] ?? [];
    echo "  Facturas pendientes: " . count($facturas) . "\n";
    foreach ($facturas as $f) {
        echo "    - ID: {$f['id_factura']} | Total: \${$f['total']} | Pendiente: " . ($f['monto_pendiente'] ?? $f['total']) . "\n";
    }
    echo "  Response completa: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
    echo "  ❌ HTTP {$status}: " . $e->getMessage() . "\n";
}

echo "\n=== PASO 2: Suspender servicio (cortar servicio) ===\n";
try {
    $resp = $client->post('clientes/desactivar/', [
        'body' => json_encode([
            'servicios' => [$serviceId],
            'motivo'    => 'Prueba portal pagos - servicio cortado',
        ]),
    ]);
    $result = json_decode($resp->getBody(), true);
    echo "  Respuesta: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
    $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
    echo "  ❌ HTTP {$status}: {$body}\n";
}

echo "\n=== PASO 3: Verificar estado después de suspensión ===\n";
try {
    $resp = $client->get("clientes/{$serviceId}/saldo/");
    $data = json_decode($resp->getBody(), true);
    echo "  Estado: " . ($data['estado'] ?? 'N/A') . "\n";
    echo "  Saldo favor: " . ($data['saldo_favor'] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "  ❌ " . $e->getMessage() . "\n";
}

echo "\n=== PASO 4: Verificar perfil del cliente ===\n";
try {
    $resp = $client->get("clientes/{$serviceId}/");
    $data = json_decode($resp->getBody(), true);
    echo "  Estado servicio: " . ($data['estado'] ?? $data['data']['estado'] ?? 'N/A') . "\n";
    echo "  Response: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
    echo "  ❌ HTTP {$status}: " . $e->getMessage() . "\n";
}
