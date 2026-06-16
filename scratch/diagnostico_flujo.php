<?php
/**
 * Diagnóstico completo del flujo: BDV API → Pago → WispHub
 * Ejecutar: php scratch/diagnostico_flujo.php
 */

echo "=== DIAGNÓSTICO COMPLETO DEL FLUJO DE PAGO ===\n\n";

// ── 1. BDV API ───────────────────────────────────────────────────────────────
echo "1) API Banco de Venezuela\n";
echo "   Endpoint: https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos\n";

$ch = curl_init('https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'cuenta' => '01020589150000001371',
        'fechaIni' => date('d/m/Y', strtotime('-7 days')),
        'fechaFin' => date('d/m/Y'),
        'tipoMoneda' => 'VES',
        'nroMovimiento' => '',
    ]),
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-KEY: 650D973744E70DFD936382F9B734405A',
    ],
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "   ❌ Error de conexión: $err\n\n";
} else {
    $data = json_decode($resp, true);
    echo "   HTTP $http\n";
    echo "   Code: " . ($data['code'] ?? 'N/A') . "\n";
    echo "   Message: " . ($data['message'] ?? 'N/A') . "\n";
    echo "   Movimientos: " . ($data['data']['totalOfMovements'] ?? 0) . "\n";
    if (!empty($data['data']['movs'])) {
        foreach (array_slice($data['data']['movs'], 0, 5) as $m) {
            echo "     - {$m['fecha']} Ref:{$m['referencia']} {$m['Tipo']} Bs {$m['importe']}\n";
        }
    }
    echo "\n";
}

// ── 2. WispHub: getServiceProfile(902) ───────────────────────────────────────
echo "2) WispHub: getServiceProfile(902)\n";
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = @include __DIR__ . '/../config/wisp_hub.php';
if (!is_array($wispConfig) || empty($wispConfig['api_key'])) {
    echo "   ❌ wisphub_credentials.php no configurado o inválido\n\n";
} else {
    try {
        $client = new \Services\WispHubClient($wispConfig);
        $profile = $client->getServiceProfile('902');
        echo "   Status: " . ($profile['status'] ?? 0) . "\n";
        if (($profile['status'] ?? 0) === 200) {
            $d = $profile['data'];
            echo "   ✅ Cliente: {$d['nombre']} {$d['apellidos']} (Cédula: {$d['cedula']})\n";
        } else {
            echo "   ❌ Error: " . json_encode($profile['data']) . "\n";
        }
    } catch (\Throwable $e) {
        echo "   ❌ Excepción: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ── 3. WispHub: getServiceBalance(902) ───────────────────────────────────────
echo "3) WispHub: getServiceBalance(902)\n";
if (isset($client)) {
    try {
        $balance = $client->getServiceBalance('902');
        echo "   Status: " . ($balance['status'] ?? 0) . "\n";
        if (($balance['status'] ?? 0) === 200) {
            echo "   Saldo: " . ($balance['data']['saldo'] ?? 'N/A') . "\n";
            echo "   Estado: " . ($balance['data']['estado'] ?? 'N/A') . "\n";
            echo "   Facturas pendientes: " . count($balance['data']['facturas'] ?? []) . "\n";
            if (!empty($balance['data']['facturas'])) {
                foreach ($balance['data']['facturas'] as $f) {
                    echo "     - Factura #{$f['id']}: {$f['total']} USD (Vence: {$f['fecha_vencimiento']})\n";
                }
            }
        } else {
            echo "   Error: " . json_encode($balance['data']) . "\n";
        }
    } catch (\Throwable $e) {
        echo "   ❌ Excepción: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ── 4. WispHub: registerPaymentAndActivate with serviceId=902 ───────────────
echo "4) WispHub: registerPaymentAndActivate(serviceId=902, amount=1.00, ref=TEST_DIAG_...)\n";
if (isset($client)) {
    try {
        $ref = 'TEST_DIAG_' . date('YmdHis');
        $payResult = $client->registerPaymentAndActivate(
            '902',
            1.00,
            $ref,
            date('Y-m-d H:i'),
            \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
            false,
            ''
        );
        echo "   Status: " . ($payResult['status'] ?? 0) . "\n";
        echo "   Service ID: " . ($payResult['service_id'] ?? 'N/A') . "\n";
        echo "   Facturas encontradas: " . ($payResult['invoices_found'] ?? 0) . "\n";
        echo "   Pagos registrados: " . count($payResult['payments_registered'] ?? []) . "\n";
        foreach ($payResult['payments_registered'] ?? [] as $p) {
            echo "     - Factura #{$p['invoice_id']}: pagado {$p['payment_applied']} USD (HTTP {$p['status']})\n";
        }
        if (!empty($payResult['activation'])) {
            $act = $payResult['activation'];
            echo "   Activación: HTTP " . ($act['status'] ?? 0) . "\n";
        }
    } catch (\Throwable $e) {
        echo "   ❌ Excepción: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ── 5. Verificar que WispHub muestra servicio activo ───────────────────────
echo "5) Verificación post-pago: getServiceBalance(902)\n";
if (isset($client)) {
    try {
        sleep(1);
        $balance = $client->getServiceBalance('902');
        if (($balance['status'] ?? 0) === 200) {
            echo "   Estado actual: " . ($balance['data']['estado'] ?? 'N/A') . "\n";
            echo "   Facturas pendientes: " . count($balance['data']['facturas'] ?? []) . "\n";
        }
    } catch (\Throwable $e) {
        echo "   ❌ Excepción: " . $e->getMessage() . "\n";
    }
}

echo "\n=== DIAGNÓSTICO COMPLETADO ===\n";
