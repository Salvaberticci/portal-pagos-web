<?php
/**
 * tests/test_tipo_factura_saldo.php
 *
 * Verifica que al crear una factura de saldo parcial se use:
 *   - tipo_factura = 2 (Manual/Extra)
 *   - fecha_emision y fecha_pago heredadas de la factura original
 *
 * IMPORTANTE: crea y luego elimina facturas de prueba en WispHub (sitelco).
 * Solo GETs y DELETEs propios - no modifica pagos reales.
 *
 * Uso: php tests/test_tipo_factura_saldo.php
 */

define('TESTS_RUNNING', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

use Services\WispHubClient;

$apiKey  = 'ubxyK8jE.BoTLrjCN8zRDaaybVL6E3X270cojY15W';
$baseUrl = 'https://api.wisphub.net/api';
$client  = new WispHubClient(['base_url' => $baseUrl, 'api_key' => $apiKey, 'verify_ssl' => false]);

$testUsername  = 'onu_prueba_oficina@sitelco';
$testServiceId = '902';

$pass = 0;
$fail = 0;
$createdIds = [];

function ok(string $msg): void   { global $pass; $pass++; echo "  OK PASS: {$msg}\n"; }
function err(string $msg): void  { global $fail; $fail++; echo "  XX FAIL: {$msg}\n"; }
function info(string $msg): void { echo "  -- {$msg}\n"; }

echo "\n";
echo "=== TEST: tipo_factura=2 en facturas de saldo parcial ===\n\n";

// PASO 1: Crear factura BASE (tipo=1)
echo "-- Paso 1: Crear factura BASE (tipo=1, mensualidad simulada) --\n";
$fechaEmisionBase = '2026-08-01';
$fechaPagoBase    = '2026-08-01';
$fechaVence       = '2026-09-01';
$montoBase        = 0.05;

$resBase = $client->createInvoice(
    $testUsername, $montoBase,
    '[TEST] Mensualidad agosto - base de prueba',
    $fechaVence, $testServiceId,
    1, $fechaEmisionBase, $fechaPagoBase
);

$statusBase = $resBase['status'] ?? 0;
info("HTTP status: {$statusBase}");

if (!in_array($statusBase, [200, 201])) {
    err("No se pudo crear factura base. Respuesta: " . json_encode($resBase['data'] ?? $resBase));
    goto cleanup;
}

$msgBase = $resBase['data']['messages'] ?? $resBase['data']['message'] ?? '';
if (is_array($msgBase)) $msgBase = implode(' ', $msgBase);
preg_match('/factura\s*#?(\d+)/i', $msgBase, $m);
$baseFacturaId = isset($m[1]) ? (int)$m[1] : 0;

if ($baseFacturaId === 0) {
    err("No se pudo extraer ID de factura base. Mensaje: {$msgBase}");
    goto cleanup;
}
$createdIds[] = $baseFacturaId;
ok("Factura base #{$baseFacturaId} creada (tipo=1, emision={$fechaEmisionBase})");

// PASO 2: getInvoiceDetail para heredar fechas
echo "\n-- Paso 2: Obtener detalle de factura base --\n";
$detalle = $client->getInvoiceDetail((string)$baseFacturaId);

if (empty($detalle)) {
    err("getInvoiceDetail devolvio vacio para #{$baseFacturaId}");
    goto cleanup;
}

$fechaEmisionHeredada = !empty($detalle['fecha_emision']) ? substr($detalle['fecha_emision'], 0, 10) : null;
$fechaPagoHeredada    = !empty($detalle['fecha_pago'])    ? substr($detalle['fecha_pago'], 0, 10)    : null;
info("fecha_emision recibida: " . ($fechaEmisionHeredada ?? '(null)'));
info("fecha_pago recibida:    " . ($fechaPagoHeredada ?? '(null)'));

if (empty($fechaEmisionHeredada)) {
    err("La factura base no devuelve 'fecha_emision' - claves: " . implode(', ', array_keys($detalle)));
    goto cleanup;
}
ok("Detalle obtenido. Fechas: emision={$fechaEmisionHeredada} / pago={$fechaPagoHeredada}");

// PASO 3: Crear factura SALDO (tipo=2, fechas heredadas)
echo "\n-- Paso 3: Crear factura de SALDO (tipo=2, fechas heredadas) --\n";
$saldoRestante      = 0.02;
$fechaLimitePromesa = date('Y-m-d', strtotime('+7 days'));

$resSaldo = $client->createInvoice(
    $testUsername, $saldoRestante,
    '[TEST] Saldo pendiente tras abono - Factura #' . $baseFacturaId,
    $fechaLimitePromesa, $testServiceId,
    2, $fechaEmisionHeredada, $fechaPagoHeredada
);

$statusSaldo = $resSaldo['status'] ?? 0;
info("HTTP status: {$statusSaldo}");

if (!in_array($statusSaldo, [200, 201])) {
    err("No se pudo crear factura de saldo: " . json_encode($resSaldo['data'] ?? $resSaldo));
    goto cleanup;
}

$msgSaldo = $resSaldo['data']['messages'] ?? $resSaldo['data']['message'] ?? '';
if (is_array($msgSaldo)) $msgSaldo = implode(' ', $msgSaldo);
preg_match('/factura\s*#?(\d+)/i', $msgSaldo, $m2);
$saldoFacturaId = isset($m2[1]) ? (int)$m2[1] : 0;

if ($saldoFacturaId === 0) {
    err("No se pudo extraer ID de factura saldo. Mensaje: {$msgSaldo}");
    goto cleanup;
}
$createdIds[] = $saldoFacturaId;
ok("Factura de saldo #{$saldoFacturaId} creada");

// PASO 4: Verificar campos en WispHub
echo "\n-- Paso 4: Verificar campos de la factura de saldo en WispHub --\n";
sleep(1);
$detalleSaldo = $client->getInvoiceDetail((string)$saldoFacturaId);

if (empty($detalleSaldo)) {
    err("getInvoiceDetail vacio para #{$saldoFacturaId}");
    goto cleanup;
}
info("Claves recibidas: " . implode(', ', array_keys($detalleSaldo)));

// Verificar tipo_factura
$tipoRecibido = $detalleSaldo['tipo_factura'] ?? $detalleSaldo['tipo'] ?? null;
info("tipo_factura recibido: " . var_export($tipoRecibido, true));
$tipoInt = is_array($tipoRecibido) ? ($tipoRecibido['id'] ?? null) : (int)$tipoRecibido;

if ($tipoInt === 2) {
    ok("tipo_factura = 2 (Manual/Extra -- no afecta ciclo recurrente)");
} else {
    err("tipo_factura = {$tipoInt} -- DEBERIA ser 2. WispHub puede estar ignorando el campo.");
    info("Respuesta completa: " . json_encode($detalleSaldo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Verificar fecha_emision
$fechaEmisionRecibida = $detalleSaldo['fecha_emision'] ?? null;
info("fecha_emision recibida: " . ($fechaEmisionRecibida ?? '(null)'));
if ($fechaEmisionRecibida === $fechaEmisionHeredada) {
    ok("fecha_emision = {$fechaEmisionRecibida} (heredada correctamente)");
} else {
    err("fecha_emision = {$fechaEmisionRecibida} -- DEBERIA ser {$fechaEmisionHeredada}");
}

// Verificar fecha_pago
$fechaPagoRecibida = !empty($detalleSaldo['fecha_pago']) ? substr($detalleSaldo['fecha_pago'], 0, 10) : null;
info("fecha_pago recibida (truncada): " . ($fechaPagoRecibida ?? '(null)'));
if ($fechaPagoRecibida === $fechaPagoHeredada) {
    ok("fecha_pago = {$fechaPagoRecibida} (heredada correctamente)");
} else {
    err("fecha_pago = {$fechaPagoRecibida} -- DEBERIA ser {$fechaPagoHeredada}");
}

// LIMPIEZA
cleanup:
echo "\n-- Limpieza: eliminando facturas de prueba --\n";
$guzzle = new \GuzzleHttp\Client([
    'base_uri' => $baseUrl . '/',
    'timeout'  => 15,
    'verify'   => false,
    'headers'  => [
        'Authorization' => "Api-Key {$apiKey}",
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ],
]);

foreach ($createdIds as $fId) {
    try {
        $guzzle->delete("facturas/{$fId}/");
        info("Factura #{$fId} eliminada.");
    } catch (\Exception $e) {
        $code = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
        info("Factura #{$fId}: DELETE HTTP {$code}");
    }
    usleep(400000);
}

// RESUMEN
$total = $pass + $fail;
echo "\n";
echo "=== Resultado: {$pass}/{$total} tests pasados ===\n";
if ($fail === 0) {
    echo "El fix funciona correctamente.\n\n";
} else {
    echo "Hay {$fail} errores - revisar lineas con XX FAIL arriba.\n\n";
}
exit($fail > 0 ? 1 : 0);
