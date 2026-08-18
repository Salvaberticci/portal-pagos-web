<?php
/**
 * Test: Regla de monto_pendiente (portal/wisp_helper.php)
 *
 * Valida wisp_normalize_invoice() y wisp_filter_saldo_pendiente().
 * Cubre el bug de la factura #10873 (Jorcelis Linares, service 795):
 *   WispHub devuelve total=30, total_cobrado=30, saldo=10, saldo_nuevo=0,
 *   estado "Pendiente de Pago" → la deuda real es $30 (el total), NO $10.
 *
 * El caso 12 es de integración (live, read-only) contra la API real y
 * solo valida que los clientes con ese patrón muestren monto_pendiente = total.
 *
 * Uso: php tests/test_monto_pendiente.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
require_once __DIR__ . '/../portal/wisp_helper.php';

$tests_passed = 0;
$tests_failed = 0;
$tests_skipped = 0;

function run_assert(bool $ok, string $msg): void {
    global $tests_passed, $tests_failed;
    if ($ok) { echo "  [OK] $msg\n"; $tests_passed++; }
    else      { echo "  [FAIL] $msg\n"; $tests_failed++; }
}

// ── 1. Casos unitarios: wisp_normalize_invoice ─────────────────────────────
echo "\n── 1. Regla de monto_pendiente (wisp_normalize_invoice) ──\n";

// Caso 1: el bug real #10873 (residuo: cobrado >= total, pendiente)
$inv = wisp_normalize_invoice([
    'id_factura'    => 10873,
    'total'         => 30,
    'total_cobrado' => 30,
    'saldo'         => 10,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pendiente de Pago',
    'articulos'     => [['descripcion' => 'Renta de red']],
]);
run_assert($inv['monto_pendiente'] === 30.0, "#10873 (total=30, cobrado=30, saldo=10, pendiente) → monto_pendiente = 30");

// Caso 2: pendiente sin cobros
$inv = wisp_normalize_invoice([
    'id_factura'    => 2001,
    'total'         => 20,
    'total_cobrado' => 0,
    'saldo'         => 0,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pendiente de Pago',
]);
run_assert($inv['monto_pendiente'] === 20.0, "Pendiente sin cobros (total=20) → monto_pendiente = 20");

// Caso 3: residuo sin abono real (cobrado=0, pero saldo=10) → saldo NO reduce la deuda
$inv = wisp_normalize_invoice([
    'id_factura'    => 2002,
    'total'         => 30,
    'total_cobrado' => 0,
    'saldo'         => 10,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pendiente de Pago',
]);
run_assert($inv['monto_pendiente'] === 30.0, "Residuo sin abono (total=30, cobrado=0, saldo=10) → monto_pendiente = 30 (saldo no reduce)");

// Caso 4: abono parcial legítimo
$inv = wisp_normalize_invoice([
    'id_factura'    => 2003,
    'total'         => 30,
    'total_cobrado' => 20,
    'saldo_nuevo'   => 10,
    'saldo'         => 0,
    'estado'        => 'Pendiente de Pago',
]);
run_assert($inv['monto_pendiente'] === 10.0, "Abono parcial legitimo (total=30, cobrado=20, saldo_nuevo=10) → monto_pendiente = 10");

// Caso 5: saldo_nuevo mayor a la base con abono real
$inv = wisp_normalize_invoice([
    'id_factura'    => 2004,
    'total'         => 30,
    'total_cobrado' => 20,
    'saldo_nuevo'   => 15,
    'saldo'         => 0,
    'estado'        => 'Pendiente de Pago',
]);
run_assert($inv['monto_pendiente'] === 15.0, "saldo_nuevo mayor que la base (total=30, cobrado=20, saldo_nuevo=15) → monto_pendiente = 15");

// Caso 6: factura pagada → monto 0
$inv = wisp_normalize_invoice([
    'id_factura'    => 2005,
    'total'         => 20,
    'total_cobrado' => 20,
    'saldo'         => 0,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pagada',
]);
run_assert($inv['monto_pendiente'] === 0.0, "Pagada (total=20, cobrado=20) → monto_pendiente = 0");

// Caso 7: vencida con residuo
$inv = wisp_normalize_invoice([
    'id_factura'    => 2006,
    'total'         => 30,
    'total_cobrado' => 30,
    'saldo'         => 10,
    'saldo_nuevo'   => 0,
    'estado'        => 'Vencida',
]);
run_assert($inv['monto_pendiente'] === 30.0, "Vencida con residuo (total=30, cobrado=30, saldo=10) → monto_pendiente = 30");

// Caso 8: sin total, usa sub_total
$inv = wisp_normalize_invoice([
    'id_factura'    => 2007,
    'total'         => 0,
    'sub_total'     => 20,
    'total_cobrado' => 0,
    'saldo'         => 0,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pendiente de Pago',
]);
run_assert($inv['monto_pendiente'] === 20.0, "Sin total, con sub_total=20 (pendiente) → monto_pendiente = 20");

// Caso 9: nota de crédito (total negativo) → nunca negativo
$inv = wisp_normalize_invoice([
    'id_factura'    => 2008,
    'total'         => -10,
    'total_cobrado' => 0,
    'saldo'         => 0,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pendiente de Pago',
]);
run_assert($inv['monto_pendiente'] === 0.0, "Nota de credito (total=-10) → monto_pendiente = 0 (no negativo)");

// Caso 10: estructura de salida y null sin id
$inv = wisp_normalize_invoice([
    'id_factura'    => 3001,
    'total'         => 20,
    'total_cobrado' => 0,
    'saldo'         => 0,
    'saldo_nuevo'   => 0,
    'estado'        => 'Pendiente de Pago',
    'articulos'     => [['descripcion' => 'Renta 20$']],
]);
run_assert($inv['id'] === 3001 && $inv['id_factura'] === 3001, "Output: id e id_factura mapeados");
run_assert(isset($inv['fecha_emision'], $inv['fecha_vencimiento'], $inv['total'], $inv['sub_total'], $inv['saldo'], $inv['saldo_nuevo'], $inv['total_cobrado'], $inv['estado'], $inv['monto_pendiente'], $inv['articulos']), "Output: todos los campos presentes");
run_assert($inv['articulos'][0]['descripcion'] === 'Renta 20$', "Output: articulos preservados");
$invNull = wisp_normalize_invoice(['total' => 20, 'estado' => 'Pendiente de Pago']);
run_assert($invNull === null, "Factura sin id → null (se omite)");

// ── 2. Filtro de facturas hija (wisp_filter_saldo_pendiente) ───────────────
echo "\n── 2. Filtro de facturas saldo pendiente ──\n";

$lista = [
    wisp_normalize_invoice(['id_factura' => 10280, 'total' => 20, 'total_cobrado' => 10, 'saldo' => 0, 'saldo_nuevo' => 0, 'estado' => 'Pendiente de Pago', 'articulos' => [['descripcion' => 'Renta']]]),
    wisp_normalize_invoice(['id_factura' => 10408, 'total' => 10, 'total_cobrado' => 0, 'saldo' => 0, 'saldo_nuevo' => 0, 'estado' => 'Pendiente de Pago', 'articulos' => [['descripcion' => 'Saldo pendiente tras abono - Factura #10280']]]),
    wisp_normalize_invoice(['id_factura' => 10873, 'total' => 30, 'total_cobrado' => 30, 'saldo' => 10, 'saldo_nuevo' => 0, 'estado' => 'Pendiente de Pago', 'articulos' => [['descripcion' => 'Renta']]]),
];
$filtradas = wisp_filter_saldo_pendiente($lista);
$ids = array_map(fn($i) => $i['id'], $filtradas);
run_assert(in_array(10408, $ids) && in_array(10873, $ids) && !in_array(10280, $ids), "Factura padre #10280 oculta; hija #10408 y #10873 visibles");

// ── 3. Integración live (read-only) ────────────────────────────────────────
echo "\n── 3. Integración live (service 795, API real, read-only) ──\n";
try {
    $wispConfig = include __DIR__ . '/../config/wisp_hub.php';
    $wispClient = new \Services\WispHubClient($wispConfig);
    $pending = $wispClient->getPendingInvoices('795');
    if (empty($pending)) {
        echo "  [SKIP] No se pudo consultar la API (sin red o sin facturas pendientes)\n";
        $tests_skipped++;
    } else {
        $ok = 0;
        foreach ($pending as $raw) {
            $norm = wisp_normalize_invoice($raw);
            if ($norm === null) continue;
            if ($norm['monto_pendiente'] === $norm['total'] && $norm['total'] > 0) {
                $ok++;
            } else {
                run_assert(false, "Factura #{$norm['id']}: monto_pendiente=" . $norm['monto_pendiente'] . " != total=" . $norm['total']);
            }
        }
        run_assert($ok === count($pending), "{$ok}/" . count($pending) . " facturas pendientes de service 795 muestran monto_pendiente = total (lo que WispHub reporta)");
    }
} catch (\Throwable $e) {
    echo "  [SKIP] Excepción consultando API: " . $e->getMessage() . "\n";
    $tests_skipped++;
}

// ── Resumen ────────────────────────────────────────────────────────────────
echo "\n=== RESUMEN ===\n";
echo "  Pasados: $tests_passed\n";
echo "  Fallidos: $tests_failed\n";
echo "  Omitidos: $tests_skipped\n";
exit($tests_failed === 0 ? 0 : 1);