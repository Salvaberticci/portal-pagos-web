<?php
/**
 * tests/test_suspension_flow.php
 *
 * Prueba del flujo de corte por vencimiento:
 *   Estado Activo → Deuda vencida → Cron corta → Servicio Suspendido
 *
 * Uso:
 *   php tests/test_suspension_flow.php
 *
 * Requiere:
 *   - Servicio WispHub ID 902 actualmente Activo
 *   - BD local XAMPP funcionando (para datos locales)
 */

$serviceId = '902';

$passed = 0;
$failed = 0;
$total = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  ✅ {$name}\n";
    } else {
        $failed++;
        echo "  ❌ {$name}";
        if ($detail) echo " — {$detail}";
        echo "\n";
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  PRUEBA: Corte por vencimiento (suspensión en WispHub)\n";
echo "  Servicio ID: {$serviceId}\n";
echo "═══════════════════════════════════════════════════════════\n\n";

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

// ─── 1. Verificar estado actual ─────────────────────────────────
echo "─── 1. Estado actual del servicio ───────────────────────\n";

$balance = $wispClient->getServiceBalance($serviceId);
$estadoInicial = $balance['data']['estado'] ?? 'desconocido';
assert_test('Servicio responde', ($balance['status'] ?? 0) === 200);
echo "   Estado inicial: {$estadoInicial}\n";
echo "   Facturas pendientes: " . count($balance['data']['facturas'] ?? []) . "\n";
$saldo_actual = $balance['data']['saldo'] ?? 'N/A';
echo "   Saldo: \${$saldo_actual}\n\n";

if ($estadoInicial !== 'Activo') {
    echo "⚠️  El servicio {$serviceId} no está Activo. Estado actual: {$estadoInicial}\n";
    echo "   Es necesario reactivarlo antes de probar el corte.\n";
    echo "   Opciones:\n";
    echo "   1. Pagar factura pendiente y reactivar desde el portal\n";
    echo "   2. Ejecutar: php tests/test_full_flow.php\n";
    echo "   3. Usar test_setup.php?accion=pago\n\n";
}

// ─── 2. Suspender el servicio en WispHub ─────────────────────────
echo "─── 2. Suspender servicio en WispHub ─────────────────────\n";
echo "   Llamando suspendService({$serviceId})...\n";

$reason = "Corte por vencimiento - Prueba " . date('Y-m-d H:i:s');
$suspendResult = $wispClient->suspendService($serviceId, $reason);

$suspendOk = ($suspendResult['status'] === 200 || $suspendResult['status'] === 201);
$taskId = $suspendResult['data']['task_id'] ?? '';

assert_test('suspendService responde OK',
    $suspendOk,
    'HTTP ' . ($suspendResult['status'] ?? 'error'));

if ($taskId) {
    echo "   Tarea asíncrona: {$taskId}\n";
    // Esperar y verificar
    echo "   Esperando 3 segundos para que se procese...\n";
    sleep(3);
    $taskStatus = $wispClient->getTaskStatus($taskId);
    $taskResultStatus = $taskStatus['data']['task']['status'] ?? $taskStatus['data']['status'] ?? '';
    $taskOk = ($taskStatus['status'] === 200 && $taskResultStatus === 'SUCCESS');
    assert_test('Tarea de suspensión completada',
        $taskOk,
        json_encode($taskStatus['data'] ?? []));
}

echo "\n";

// ─── 3. Verificar estado después del corte ───────────────────────
echo "─── 3. Verificar estado después del corte ───────────────\n";

$balance2 = $wispClient->getServiceBalance($serviceId);
$estadoFinal = $balance2['data']['estado'] ?? 'desconocido';

echo "   Estado después: {$estadoFinal}\n";
assert_test('Servicio aparece como Suspendido en WispHub',
    $estadoFinal === 'Suspendido' || $estadoFinal === 'Suspendido (Corte)',
    "Estado actual: {$estadoFinal}");

// También verificar perfil para más detalle
$profile = $wispClient->getServiceProfile($serviceId);
echo "   Usuario: " . ($profile['data']['usuario'] ?? 'N/A') . "\n";

echo "\n";

// ─── 4. Resumen ──────────────────────────────────────────────────
echo "═══════════════════════════════════════════════════════════\n";
echo "  RESULTADOS: {$passed}/{$total} pruebas pasaron\n";
if ($failed > 0) {
    echo "  ⚠️  {$failed} prueba(s) fallaron\n";
} else {
    echo "  ✅ TODAS LAS PRUEBAS PASARON\n";
}
echo "═══════════════════════════════════════════════════════════\n";

if ($passed === $total && $total > 0) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  PRUEBA DE CORTE COMPLETADA\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    echo "  El servicio {$serviceId} ahora está SUSPENDIDO.\n\n";
    echo "  Para REACTIVAR (probar pago → activación):\n";
    echo "    1. php tests/test_full_flow.php\n";
    echo "    2. O desde navegador: portal/test_setup.php?accion=pago\n";
    echo "    3. O pagando desde el portal con V99999999\n\n";
    echo "  Para probar el CRON real con datos locales:\n";
    echo "    1. portal/test_setup.php?accion=setup   (crear contrato + CxC)\n";
    echo "    2. portal/test_setup.php?accion=expire  (vencer CxC)\n";
    echo "    3. php cron/cortar_servicios_vencidos.php 0  (ejecutar cron)\n";
    echo "═══════════════════════════════════════════════════════════\n";
}
