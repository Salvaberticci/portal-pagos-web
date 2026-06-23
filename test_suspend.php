<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$serviceId = '902';
echo "Test suspend...\n";

// Try various endpoints
$endpoints = [
    "POST /clientes/{$serviceId}/desactivar/",
    "POST /clientes/{$serviceId}/suspender/",
    "POST /estado-servicio/{$serviceId}/ (estado=Desactivado)",
];

// Let's try desactivar
$res = $wispClient->request('POST', "clientes/{$serviceId}/desactivar/");
echo "Response desactivar:\n";
print_r($res);

// If 404, try suspender
if ($res['status'] == 404) {
    $res = $wispClient->request('POST', "clientes/{$serviceId}/suspender/");
    echo "Response suspender:\n";
    print_r($res);
}

// Let's check status
$res = $wispClient->getServiceDetail($serviceId);
echo "\nEstado actual: " . ($res['estado'] ?? 'Desconocido') . "\n";
