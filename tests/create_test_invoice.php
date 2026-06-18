<?php
/**
 * Crear factura de prueba final de ~$0.01 (≈ 6 Bs)
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

$usuario = 'onu_prueba_oficina@sitelco';
$hoy = date('Y-m-d');
$vence = date('Y-m-d', strtotime('+7 days'));

echo "=== Eliminar facturas pendientes existentes ===\n";
try {
    $resp = $client->get('facturas/', [
        'query' => ['estado' => 1, 'cliente' => $usuario, 'limit' => 50],
    ]);
    $data = json_decode($resp->getBody(), true);
    foreach ($data['results'] ?? [] as $f) {
        $id = $f['id_factura'];
        $client->delete("facturas/{$id}/");
        echo "  ✅ Eliminada #{$id}\n";
        usleep(300000);
    }
} catch (\Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n=== Crear factura de prueba ($0.01 ≈ 6 Bs) ===\n";
$payload = [
    'tipo_factura'     => 1,
    'cliente'          => $usuario,
    'fecha_emision'    => $hoy,
    'fecha_pago'       => $hoy,
    'fecha_vencimiento'=> $vence,
    'articulos'        => [
        [
            'cantidad'    => 1,
            'descripcion' => "Renta y mantenimiento de la red: CCR2116_ESCUQUE\nPlan de Internet: PLAN_GALA650M",
            'precio'      => 0.01,
        ],
    ],
];

try {
    $resp = $client->post('facturas/', [
        'body' => json_encode($payload),
    ]);
    $result = json_decode($resp->getBody(), true);
    $msg = $result['messages'] ?? '';
    echo "  {$msg}\n";

    if (strpos($msg, 'correctamente') !== false) {
        echo "  ✅ Factura creada!\n";

        $facturaId = preg_replace('/[^0-9]/', '', $msg);
        $monto_bs = 0.01 * 602.33;
        echo "  Monto: $0.01 USD ≈ Bs " . number_format($monto_bs, 2, ',', '.') . "\n";
        echo "  Vencimiento: {$vence}\n";
    }
} catch (\Exception $e) {
    $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
    $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : '';
    echo "  ❌ HTTP {$status}: {$body}\n";
}

echo "\n=== Verificar facturas pendientes ===\n";
try {
    $resp = $client->get('facturas/', [
        'query' => ['estado' => 1, 'cliente' => $usuario, 'limit' => 50],
    ]);
    $data = json_decode($resp->getBody(), true);
    $facturas = $data['results'] ?? [];
    echo "  Pendientes: " . count($facturas) . "\n";
    foreach ($facturas as $f) {
        $monto_bs = $f['total'] * 602.33;
        echo "  - ID: {$f['id_factura']} | Total: \${$f['total']} ≈ Bs " . number_format($monto_bs, 2, ',', '.') . " | Vence: {$f['fecha_vencimiento']}\n";
    }
} catch (\Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
}
