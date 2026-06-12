<?php
/**
 * Test: Live WispHub Sandbox Integration
 *
 * Prueba contra el SANDBOX real de WispHub (no mock).
 * Usa mock BDV interno (no llama API real de BDV).
 *
 * Requisitos:
 *   - config/wisphub_credentials.php debe tener credenciales sandbox válidas
 *   - La URL base debe ser https://sandbox-api.wisphub.net/api
 *
 * Advertencia: Este test hace llamadas HTTP reales al sandbox de WispHub.
 * NO ejecutar en producción.
 */

echo "=== TEST: Live WispHub Sandbox Integration ===\n\n";
echo "ADVERTENCIA: Este test hace llamadas HTTP reales al sandbox de WispHub.\n\n";

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';

echo "Configuración actual:\n";
echo "  Base URL: " . $wispConfig['base_url'] . "\n";
echo "  API Key: " . substr($wispConfig['api_key'] ?? '', 0, 10) . "...\n\n";

if (strpos($wispConfig['base_url'], 'sandbox-api.wisphub.net') === false) {
    echo "ADVERTENCIA: La URL base NO apunta al sandbox.\n";
    echo "  Asegúrate de que apunte a: https://sandbox-api.wisphub.net/api\n\n";
}

$client = new \Services\WispHubClient($wispConfig);
$allPassed = true;
$results = [];

// ── Test 1: notifyPayment ──────────────────────────────────────────────
echo "--- Test 1: notifyPayment (simulando pago) ---\n";
try {
    $r = $client->notifyPayment([
        'payment_id'      => 99999,
        'reference'       => 'SANDBOX-REF-' . time(),
        'amount_usd'      => 17.50,
        'currency'        => 'USD',
        'date'            => date('Y-m-d'),
        'customer_cedula' => 'V99999999',
        'contract_id'     => 99999,
    ]);
    $ok = ($r['status'] === 200 || $r['status'] === 201);
    echo "  HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '') . "\n";
    $results[] = ['name' => 'notifyPayment', 'status' => $r['status'], 'ok' => $ok];
    if (!$ok) $allPassed = false;
} catch (Exception $e) {
    echo "  EXCEPCIÓN: " . $e->getMessage() . "\n";
    $results[] = ['name' => 'notifyPayment', 'status' => 'EXCEPTION', 'ok' => false];
    $allPassed = false;
}

// ── Test 2: activateService ────────────────────────────────────────────
echo "\n--- Test 2: activateService (con ID de prueba) ---\n";
try {
    $r = $client->activateService('test-service-id-001');
    echo "  HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '') . "\n";
    $ok = ($r['status'] === 200 || $r['status'] === 201);
    $results[] = ['name' => 'activateService', 'status' => $r['status'], 'ok' => $ok];
    if (!$ok) $allPassed = false;
} catch (Exception $e) {
    echo "  EXCEPCIÓN: " . $e->getMessage() . "\n";
    $results[] = ['name' => 'activateService', 'status' => 'EXCEPTION', 'ok' => false];
    $allPassed = false;
}

// ── Test 3: suspendService ─────────────────────────────────────────────
echo "\n--- Test 3: suspendService (con ID de prueba) ---\n";
try {
    $r = $client->suspendService('test-service-id-001', 'Test sandbox - corte por vencimiento simulado');
    echo "  HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '') . "\n";
    $ok = ($r['status'] === 200 || $r['status'] === 201);
    $results[] = ['name' => 'suspendService', 'status' => $r['status'], 'ok' => $ok];
    if (!$ok) $allPassed = false;
} catch (Exception $e) {
    echo "  EXCEPCIÓN: " . $e->getMessage() . "\n";
    $results[] = ['name' => 'suspendService', 'status' => 'EXCEPTION', 'ok' => false];
    $allPassed = false;
}

// ── Test 4: getServiceProfile ──────────────────────────────────────────
echo "\n--- Test 4: getServiceProfile (con ID de prueba) ---\n";
try {
    $r = $client->getServiceProfile('test-service-id-001');
    echo "  HTTP {$r['status']}: " . json_encode($r['data'] ?? $r['error'] ?? '') . "\n";
    $ok = ($r['status'] === 200 || $r['status'] === 201);
    $results[] = ['name' => 'getServiceProfile', 'status' => $r['status'], 'ok' => $ok];
    if (!$ok) $allPassed = false;
} catch (Exception $e) {
    echo "  EXCEPCIÓN: " . $e->getMessage() . "\n";
    $results[] = ['name' => 'getServiceProfile', 'status' => 'EXCEPTION', 'ok' => false];
    $allPassed = false;
}

// ── Resumen ────────────────────────────────────────────────────────────
echo "\n=== RESULTADOS ===\n";
echo str_pad('Endpoint', 25) . str_pad('Status', 12) . "Resultado\n";
echo str_repeat('-', 55) . "\n";
foreach ($results as $res) {
    echo str_pad($res['name'], 25) . str_pad($res['status'], 12) . ($res['ok'] ? 'OK' : 'FALLO') . "\n";
}
echo "\n";

if ($allPassed) {
    echo "=> Todos los endpoints del sandbox respondieron correctamente.\n";
    echo "=> La integración con WispHub Sandbox está funcional.\n";
} else {
    echo "=> Algunos endpoints fallaron. Revisa:\n";
    echo "   1. Credenciales API Key/Secret en config/wisphub_credentials.php\n";
    echo "   2. Conexión a internet\n";
    echo "   3. Que el sandbox de WispHub esté accesible\n";
    echo "   4. Los IDs de servicio de prueba pueden no existir en tu cuenta sandbox (errores 404/403 son esperados)\n";
}

echo "\nInterpretación de códigos:\n";
echo "  200/201 = Éxito completo\n";
echo "  403     = Autenticación OK pero sin permisos en esta cuenta\n";
echo "  404     = El ID de servicio no existe (esperado para IDs de prueba)\n";
echo "  0       = Error de conexión/red (verificar URL e internet)\n";
