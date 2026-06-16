<?php
/**
 * test_dashboard_alerts.php
 * Simula los 5 escenarios de alertas del dashboard:
 *   1. Sin facturas → "Estás al día"
 *   2. Factura vence en +10 días → azul info
 *   3. Factura vence en 3 días → naranja urgente
 *   4. Factura vence HOY → naranja "HOY"
 *   5. Factura VENCIDA hace 2 días → roja peligro
 *
 * También prueba la conexión real con WispHub para
 * verificar estructura de datos del cliente 902.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

echo "=== TEST: LÓGICA DE ALERTAS DE VENCIMIENTO DEL DASHBOARD ===\n\n";

$tests_passed = 0;
$tests_failed = 0;

function run_assert(bool $ok, string $msg): void {
    global $tests_passed, $tests_failed;
    if ($ok) { echo "✅ [OK]    $msg\n"; $tests_passed++; }
    else      { echo "❌ [FAIL]  $msg\n"; $tests_failed++; }
}

// ─────────────────────────────────────────────
// FUNCIÓN QUE REPLICA LA LÓGICA DEL DASHBOARD
// ─────────────────────────────────────────────
function calcular_alerta(array $invoices): array {
    $default = [
        'tipo'  => 'primary',
        'texto' => 'RECUERDA CANCELAR LOS PRIMEROS 5 DE CADA MES',
        'icono' => 'fa-bell',
    ];

    if (empty($invoices)) {
        return [
            'tipo'  => 'success',
            'texto' => '¡ESTÁS AL DÍA! NO TIENES FACTURAS PENDIENTES.',
            'icono' => 'fa-check-circle',
        ];
    }

    $fecha_vencimiento = null;
    foreach ($invoices as $inv) {
        if (!empty($inv['fecha_vencimiento'])) {
            $fv = strtotime($inv['fecha_vencimiento']);
            if ($fecha_vencimiento === null || $fv < $fecha_vencimiento) {
                $fecha_vencimiento = $fv;
            }
        }
    }

    if (!$fecha_vencimiento) return $default;

    $hoy           = strtotime(date('Y-m-d'));
    $fv_date       = strtotime(date('Y-m-d', $fecha_vencimiento));
    $diferencia    = (int) round(($fv_date - $hoy) / 86400);
    $fecha_str     = date('d/m/Y', $fecha_vencimiento);

    if ($diferencia < 0) {
        return ['tipo' => 'danger', 'texto' => "VENCIDA HACE " . abs($diferencia) . " DÍA(S) ($fecha_str)", 'icono' => 'fa-exclamation-triangle'];
    } elseif ($diferencia === 0) {
        return ['tipo' => 'warning', 'texto' => "VENCE HOY ($fecha_str)", 'icono' => 'fa-clock'];
    } elseif ($diferencia <= 5) {
        return ['tipo' => 'warning', 'texto' => "FALTAN $diferencia DÍA(S) ($fecha_str)", 'icono' => 'fa-calendar-day'];
    } else {
        return ['tipo' => 'info', 'texto' => "PRÓXIMO VENCIMIENTO EN $diferencia DÍAS ($fecha_str)", 'icono' => 'fa-calendar-check'];
    }
}

// ─────────────────────────────────────────────
// ESCENARIOS SIMULADOS
// ─────────────────────────────────────────────
echo "── FASE 1: Escenarios simulados de alertas ──\n";

// Escenario A: sin facturas
$a = calcular_alerta([]);
run_assert($a['tipo'] === 'success', "Sin facturas → alerta 'success' (estás al día)");

// Escenario B: factura en +10 días
$fechaFutura10 = date('Y-m-d', strtotime('+10 days'));
$b = calcular_alerta([['fecha_vencimiento' => $fechaFutura10]]);
run_assert($b['tipo'] === 'info', "Factura en +10 días → alerta 'info'");
run_assert(str_contains($b['texto'], '10'), "Texto debe mencionar '10'");

// Escenario C: factura en 3 días
$fechaFutura3 = date('Y-m-d', strtotime('+3 days'));
$c = calcular_alerta([['fecha_vencimiento' => $fechaFutura3]]);
run_assert($c['tipo'] === 'warning', "Factura en 3 días → alerta 'warning'");
run_assert(str_contains($c['texto'], '3'), "Texto debe mencionar '3'");

// Escenario D: vence HOY
$d = calcular_alerta([['fecha_vencimiento' => date('Y-m-d')]]);
run_assert($d['tipo'] === 'warning', "Vence hoy → alerta 'warning'");
run_assert(str_contains(strtolower($d['texto']), 'hoy'), "Texto debe decir HOY");

// Escenario E: vencida hace 2 días
$fechaVencida = date('Y-m-d', strtotime('-2 days'));
$e = calcular_alerta([['fecha_vencimiento' => $fechaVencida]]);
run_assert($e['tipo'] === 'danger', "Vencida hace 2 días → alerta 'danger'");
run_assert(str_contains($e['texto'], '2'), "Texto debe mencionar '2'");

// Escenario F: múltiples facturas → toma la más antigua
$f = calcular_alerta([
    ['fecha_vencimiento' => date('Y-m-d', strtotime('+8 days'))],
    ['fecha_vencimiento' => date('Y-m-d', strtotime('+2 days'))], // la más urgente
]);
run_assert($f['tipo'] === 'warning', "Múltiples facturas → toma la más urgente (2 días → warning)");
run_assert(str_contains($f['texto'], '2'), "Texto debe mencionar '2' (la más urgente)");

echo "\n";

// ─────────────────────────────────────────────
// FASE 2: CONEXIÓN REAL CON WISPHUB
// ─────────────────────────────────────────────
echo "── FASE 2: Conexión real con WispHub (cliente 902) ──\n";

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$client     = new \Services\WispHubClient($wispConfig);

// Perfil
$profileRes = $client->getServiceProfile('902');
run_assert($profileRes['status'] === 200, "getServiceProfile(902) devuelve HTTP 200");
run_assert(is_array($profileRes['data']), "El perfil es un array");

$perfil = $profileRes['data'];
// WispHub devuelve: nombre, cedula, usuario, saldo, direccion, etc.
$nombre_cliente = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellidos'] ?? ''));
run_assert(!empty($perfil['cedula'] ?? null) || !empty($perfil['usuario'] ?? null),
    "El perfil tiene datos del cliente (cedula o usuario)");

// WispHub no devuelve 'estado' directamente en el perfil del servicio
// El estado se determina por las facturas pendientes (saldo > 0 = tiene deuda)
$saldo = floatval($perfil['saldo'] ?? 0);
$nombre_cliente = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellidos'] ?? ''));
echo "  → Cliente 902: $nombre_cliente\n";
echo "  → Cédula: " . ($perfil['cedula'] ?? 'N/A') . "\n";
echo "  → Saldo WispHub: \$$saldo\n";

// Facturas pendientes
$invoices = $client->getPendingInvoices('902');
run_assert(is_array($invoices), "getPendingInvoices devuelve un array");

if (count($invoices) > 0) {
    echo "  → Facturas pendientes: " . count($invoices) . "\n";
    $inv0 = $invoices[0];
    echo "  → Primera factura:\n";
    echo "      Monto:      " . ($inv0['monto'] ?? $inv0['total'] ?? 'N/A') . " USD\n";
    echo "      Vencimiento: " . ($inv0['fecha_vencimiento'] ?? 'N/A') . "\n";
    echo "      Estado:      " . ($inv0['estado'] ?? 'N/A') . "\n";

    // Calcular alerta con datos reales
    $alertaReal = calcular_alerta($invoices);
    echo "  → Alerta generada: [{$alertaReal['tipo']}] {$alertaReal['texto']}\n";
    run_assert(!empty($alertaReal['tipo']), "Alerta calculada con datos reales de WispHub");
} else {
    echo "  → Sin facturas pendientes para cliente 902\n";
    $alertaReal = calcular_alerta([]);
    echo "  → Alerta generada: [{$alertaReal['tipo']}] {$alertaReal['texto']}\n";
    run_assert($alertaReal['tipo'] === 'success', "Sin facturas → alerta success en datos reales");
}

echo "\n";

// ─────────────────────────────────────────────
// FASE 3: VERIFICAR CAMPOS CLAVE EN WISPHUB
// ─────────────────────────────────────────────
echo "── FASE 3: Verificación de campos críticos de la API ──\n";

// Verificar que hay campos clave presentes
run_assert(!empty($perfil['cedula'] ?? null), "El perfil tiene campo 'cedula'");
run_assert(!empty($perfil['nombre'] ?? null), "El perfil tiene campo 'nombre'");
run_assert(isset($perfil['saldo']), "El perfil tiene campo 'saldo'");

// Verificar que el saldo (deuda) es numérico
$saldo = floatval($perfil['saldo'] ?? -1);
run_assert($saldo >= 0, "El saldo es un número válido (\$$saldo)");

// Dump completo de todos los campos que devuelve WispHub
echo "\n  → Campos disponibles en el perfil:\n";
foreach ($perfil as $k => $v) {
    $val = is_array($v) ? json_encode($v) : $v;
    echo "      $k: $val\n";
}

echo "\n";

// ─────────────────────────────────────────────
// RESUMEN
// ─────────────────────────────────────────────
echo "=== RESUMEN ===\n";
echo "✅ Pasados: $tests_passed\n";
echo "❌ Fallidos: $tests_failed\n";
if ($tests_failed === 0) {
    echo "\n🎉 TODOS LOS TESTS DE ALERTAS PASARON EXITOSAMENTE\n";
} else {
    echo "\n⚠️  Algunos tests fallaron. Revisa los detalles arriba.\n";
}
