<?php
/**
 * test_search_api.php
 *
 * Prueba la búsqueda de clientes en WispHub por cédula/documento.
 * Reemplaza la búsqueda local en BD con la API de WispHub.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

echo "Testing WispHub search/lookup logic...\n\n";

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

// ── Test 1: Buscar cliente de prueba V20788775 ──────────────────────────────
$cedula = 'V20788775';
echo "Searching for: $cedula\n";

$result = $client->getClientByDocument($cedula);
echo "HTTP Status (getClientByDocument): " . ($result['status'] ?? '?') . "\n";

if ($result['status'] === 200 && !empty($result['data'])) {
    $cliente = $result['data']['data'] ?? $result['data'];
    echo "Found client:\n";
    echo "  Nombre: " . ($cliente['nombre'] ?? $cliente['nombre_completo'] ?? 'N/A') . "\n";
    echo "  Cédula: " . ($cliente['cedula'] ?? 'N/A') . "\n";
    echo "  Service ID: " . ($cliente['service_id'] ?? $cliente['id_servicio'] ?? 'N/A') . "\n";
    echo "  Estado: " . ($cliente['estado'] ?? 'N/A') . "\n";

    if (!empty($cliente['service_id'] ?? $cliente['id_servicio'] ?? '')) {
        echo "\nSUCCESS: Client found and service_id is present.\n";
    } else {
        echo "\nWARNING: Client found but service_id is missing.\n";
    }
} else {
    // Intentar búsqueda alternativa
    echo "Retrying with findClientByDocument...\n";
    $result2 = $client->findClientByDocument($cedula);
    echo "HTTP Status (findClientByDocument): " . ($result2['status'] ?? '?') . "\n";

    if ($result2['status'] === 200 && !empty($result2['data'])) {
        $cliente = $result2['data']['data'] ?? $result2['data'];
        echo "Found via findClientByDocument:\n";
        print_r($cliente);
        echo "\nSUCCESS: Client found via alternative search.\n";
    } else {
        echo "Client not found. Response:\n";
        echo json_encode($result2['data'] ?? $result2['error'] ?? 'No response', JSON_UNESCAPED_UNICODE) . "\n";
        echo "\nINFO: Check WispHub API key in config/wisp_hub.php\n";
    }
}

echo "\n";

// ── Test 2: Búsqueda por número sin prefijo ──────────────────────────────────
$cedula_sin_prefijo = '20788775';
echo "Searching without prefix: $cedula_sin_prefijo\n";
$result3 = $client->getClientByDocument($cedula_sin_prefijo);
echo "HTTP Status: " . ($result3['status'] ?? '?') . "\n";
if ($result3['status'] === 200 && !empty($result3['data'])) {
    echo "SUCCESS: Also found without prefix.\n";
} else {
    echo "INFO: Not found without prefix (expected if API requires prefix).\n";
}

echo "\n=== Search API Test Completed ===\n";
