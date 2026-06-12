<?php
/**
 * tests/test_full_flow.php
 *
 * Prueba completa del flujo: registrar pago en WispHub + activar servicio.
 *
 * Uso:
 *   php tests/test_full_flow.php
 *
 * Prerrequisitos:
 *   - WispHub producción configurado en config/wisp_hub.php
 *   - BD local configurada
 *   - Opcional: ?accion=setup desde test_setup.php para tener datos locales
 */

// ─── Configuración ───────────────────────────────────────────────────
$serviceId = '902';
$cedula_test = 'V99999999';
$amount = 5.00;
$reference = 'TEST_AUTO_' . date('YmdHis');
$paymentDate = date('Y-m-d H:i');

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
echo "  PRUEBA COMPLETA: Pago + Activación WispHub\n";
echo "  Servicio ID: {$serviceId} | Fecha: {$paymentDate}\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// ─── Cargar dependencias ─────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

// Conexión BD opcional (puede fallar desde CLI)
$conn = null;
$dbError = '';
try {
    require_once __DIR__ . '/../paginas/conexion.php';
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

echo "▶ Conexión WispHub: {$wispConfig['base_url']}\n\n";

// ─── 1. Verificar conexión básica ─────────────────────────────────
echo "─── 1. Verificar conexión básica ───────────────────────────\n";

$profile = $wispClient->getServiceProfile($serviceId);
assert_test('getServiceProfile devuelve HTTP 200', 
    ($profile['status'] ?? 0) === 200,
    'HTTP ' . ($profile['status'] ?? '?'));
assert_test('Servicio ' . $serviceId . ' existe', 
    !empty($profile['data']['id_servicio']),
    'id_servicio: ' . ($profile['data']['id_servicio'] ?? 'N/A'));
echo "   Nombre: " . ($profile['data']['nombre'] ?? 'N/A') . "\n\n";

// ─── 2. Estado actual del servicio ─────────────────────────────────
echo "─── 2. Estado actual ───────────────────────────────────────\n";

$balance = $wispClient->getServiceBalance($serviceId);
$estado = $balance['data']['estado'] ?? 'desconocido';
$facturas = $balance['data']['facturas'] ?? [];
$saldo = $balance['data']['saldo'] ?? 'N/A';

assert_test('getServiceBalance devuelve HTTP 200',
    ($balance['status'] ?? 0) === 200);
echo "   Estado actual: {$estado}\n";
echo "   Saldo: \${$saldo}\n";
echo "   Facturas pendientes: " . count($facturas) . "\n";

if (!empty($facturas)) {
    foreach ($facturas as $f) {
        echo "     • #{$f['id']} — \${$f['total']} — vence: {$f['fecha_vencimiento']}\n";
    }
}
echo "\n";

// ─── 3. Probar getPendingInvoices ──────────────────────────────────
echo "─── 3. getPendingInvoices ──────────────────────────────────\n";

$pending = $wispClient->getPendingInvoices($serviceId);
assert_test('getPendingInvoices devuelve array',
    is_array($pending));
assert_test('Coincide con facturas del saldo',
    count($pending) === count($facturas));
echo "\n";

// ─── 4. Probar registerPaymentAndActivate ───────────────────────────
echo "─── 4. registerPaymentAndActivate ──────────────────────────\n";
echo "   Monto: \${$amount} | Ref: {$reference}\n\n";

$result = $wispClient->registerPaymentAndActivate(
    $serviceId,
    $amount,
    $reference,
    $paymentDate
);

assert_test('Responde con status 200',
    ($result['status'] ?? 0) === 200,
    'HTTP ' . ($result['status'] ?? '?'));
assert_test('Invoices encontrado es número',
    is_int($result['invoices_found'] ?? 'X'));
assert_test('Payments_registered es array',
    is_array($result['payments_registered'] ?? null));
assert_test('Activation tiene status',
    isset($result['activation']['status']),
    json_encode($result['activation'] ?? []));

echo "\n   Detalle:\n";
echo "   Facturas encontradas: {$result['invoices_found']}\n";
foreach ($result['payments_registered'] as $p) {
    $ok_icon = ($p['status'] === 200 || $p['status'] === 201) ? '✅' : '❌';
    echo "   {$ok_icon} Factura #{$p['invoice_id']}: HTTP {$p['status']}\n";
}
$act_ok = ($result['activation']['status'] ?? 0) === 200;
$act_icon = $act_ok ? '✅' : '❌';
$act_msg = $result['activation']['data']['message'] ?? json_encode($result['activation']['data'] ?? []);
echo "   {$act_icon} Activación: HTTP {$result['activation']['status']} — {$act_msg}\n";

echo "\n";

// ─── 5. Verificar estado después ────────────────────────────────────
echo "─── 5. Estado después del pago ─────────────────────────────\n";

$balance2 = $wispClient->getServiceBalance($serviceId);
$estado2 = $balance2['data']['estado'] ?? 'desconocido';
$facturas2 = $balance2['data']['facturas'] ?? [];
echo "   Estado: {$estado2}\n";
echo "   Facturas pendientes: " . count($facturas2) . "\n";

if (count($facturas) > 0) {
    assert_test('Facturas pendientes disminuyeron o quedaron en 0',
        count($facturas2) <= count($facturas),
        'Antes: ' . count($facturas) . ', Después: ' . count($facturas2));
}

echo "\n";

// ─── 6. Probar idempotencia (llamar de nuevo) ───────────────────────
echo "─── 6. Idempotencia: llamar de nuevo ───────────────────────\n";

$result2 = $wispClient->registerPaymentAndActivate(
    $serviceId,
    $amount,
    $reference . '_DUP',
    $paymentDate
);

assert_test('Segunda llamada responde 200',
    ($result2['status'] ?? 0) === 200);
$is_idempotent = ($result2['invoices_found'] === 0) // sin facturas nuevas
    && ($result2['activation']['data']['message'] ?? '') === 'Servicio ya activo';
assert_test('Es idempotente (0 facturas, ya activo)',
    $is_idempotent,
    'invoices_found=' . $result2['invoices_found']
    . ', activation=' . ($result2['activation']['data']['message'] ?? 'N/A'));

echo "\n";

// ─── 7. Verificar datos locales (si hay conexión BD) ────────────────
echo "─── 7. Verificar datos locales en BD ───────────────────────\n";

if (isset($conn) && $conn) {
    $q = $conn->query("SELECT id, estado FROM contratos WHERE cedula = '{$cedula_test}' LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) {
        echo "   Contrato #{$row['id']}: {$row['estado']}\n";
        assert_test('Contrato existe en BD', true);

        $ql = $conn->query("SELECT wisp_account_id, status FROM wisp_hub_links WHERE contract_id = {$row['id']} ORDER BY id DESC LIMIT 1");
        if ($ql && $lr = $ql->fetch_assoc()) {
            echo "   WispHub Link: ID={$lr['wisp_account_id']} [{$lr['status']}]\n";
        }
    } else {
        echo "   ℹ️ No hay contrato para {$cedula_test}. Ejecuta primero:\n";
        echo "     http://localhost/sistemas-administrativo-tecnico-wireless/portal/test_setup.php?accion=setup\n";
    }
} else {
    echo "   ℹ️ Sin conexión BD local\n";
}

echo "\n";

// ─── Resumen ─────────────────────────────────────────────────────────
echo "═══════════════════════════════════════════════════════════\n";
echo "  RESULTADOS: {$passed}/{$total} pruebas pasaron\n";
if ($failed > 0) {
    echo "  ⚠️  {$failed} prueba(s) fallaron\n";
} else {
    echo "  ✅ TODAS LAS PRUEBAS PASARON\n";
}
echo "═══════════════════════════════════════════════════════════\n";

if (isset($conn) && $conn) {
    $conn->close();
}

// ─── Instrucciones para prueba desde navegador ──────────────────────
echo "\n\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  PRUEBA DESDE NAVEGADOR (BDV mock + WispHub real)\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "  1. Abre el portal: http://localhost/sistemas-administrativo-tecnico-wireless/portal/index.php\n";
echo "  2. Ingresa con cédula: {$cedula_test}\n";
echo "  3. Ve a 'Nuevo Reporte de Pago' o 'Reportar Pago'\n";
echo "  4. Selecciona: Banco de Venezuela (Pago Móvil)\n";
echo "  5. Referencia: 999222\n";
echo "  6. Monto: 1.00 USD (o el monto de la deuda)\n";
echo "  7. Enviar\n\n";
echo "  Resultado esperado:\n";
echo "    ✅ El mock BDV encuentra la referencia 999222\n";
echo "    ✅ El pago se auto-aprueba\n";
echo "    ✅ Se registra el pago en WispHub contra la factura pendiente\n";
echo "    ✅ El servicio {$serviceId} queda Activo\n\n";
echo "  Para verificar:\n";
echo "    • http://localhost/sistemas-administrativo-tecnico-wireless/portal/test_setup.php?accion=status\n";
echo "    • Ejecutar este test de nuevo: php tests/test_full_flow.php\n";
echo "═══════════════════════════════════════════════════════════\n";
