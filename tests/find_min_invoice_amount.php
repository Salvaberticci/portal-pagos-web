<?php
/**
 * Buscar el monto mínimo que acepta la API de WispHub para crear facturas.
 * Prueba varios montos para encontrar el límite.
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
$descripcion = "Renta y mantenimiento de la red: CCR2116_ESCUQUE\nPlan de Internet: PLAN_GALA650M 25.00 $";

$montos = [0.01, 0.10, 0.25, 0.50, 0.75, 0.90, 0.99];

echo "=== Buscando monto mínimo para crear factura ===\n";
foreach ($montos as $monto) {
    $payload = [
        'tipo_factura'     => 1,
        'cliente'          => $usuario,
        'fecha_emision'    => $hoy,
        'fecha_pago'       => $hoy,
        'fecha_vencimiento'=> $vence,
        'articulos'        => [
            [
                'cantidad'    => 1,
                'descripcion' => $descripcion,
                'precio'      => $monto,
            ],
        ],
    ];

    try {
        $resp = $client->post('facturas/', [
            'body' => json_encode($payload),
        ]);
        $result = json_decode($resp->getBody(), true);
        $msg = $result['messages'] ?? '';
        if (strpos($msg, 'correctamente') !== false) {
            echo "  ✅ \${$monto} -> {$msg}\n";
            $facturaId = preg_replace('/[^0-9]/', '', $msg);
            if ($facturaId) {
                echo "     Eliminando factura #{$facturaId}...\n";
                $client->delete("facturas/{$facturaId}/");
                usleep(300000);
            }
        } else {
            echo "  ❌ \${$monto} -> {$msg}\n";
        }
    } catch (\Exception $e) {
        $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
        echo "  ❌ \${$monto} -> HTTP {$status}\n";
    }
    usleep(300000);
}

echo "\n=== LISTO ===\n";
