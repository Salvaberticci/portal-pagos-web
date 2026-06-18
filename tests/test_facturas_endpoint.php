<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$wispConfig = include 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "--- Probando facturas del cliente 902 ---\n";
// Usamos reflexión para invocar el método privado request
$refObj = new ReflectionObject($wispClient);
$refMethod = $refObj->getMethod('request');
$refMethod->setAccessible(true);

$res1 = $refMethod->invoke($wispClient, 'GET', 'facturas/', ['id_servicio' => 902]);
echo "GET facturas/ status: " . $res1['status'] . "\n";
if ($res1['status'] === 200) {
    echo "Facturas encontradas: " . count($res1['data']['results'] ?? []) . "\n";
    if (!empty($res1['data']['results'])) {
        foreach ($res1['data']['results'] as $f) {
            echo "Recibo:\n";
            echo "  ID: " . ($f['id'] ?? 'N/A') . "\n";
            echo "  Monto: " . ($f['total'] ?? 'N/A') . "\n";
            echo "  Fecha Pago: " . ($f['fecha_pago'] ?? 'N/A') . "\n";
            echo "  Estado: " . ($f['estado'] ?? 'N/A') . "\n";
        }
    }
} else {
    print_r($res1);
}

echo "\n--- Probando clientes/902/saldo/ ---\n";
$res3 = $refMethod->invoke($wispClient, 'GET', 'clientes/902/saldo/');
echo "GET clientes/902/saldo/ status: " . $res3['status'] . "\n";
if ($res3['status'] === 200) {
    print_r($res3['data']);
}
