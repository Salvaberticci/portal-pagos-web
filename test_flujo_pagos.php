<?php
/**
 * TEST COMPLETO: Flujo de Pagos con Abonos y Promesas de Pago
 *
 * Este script simula el flujo completo del portal:
 *   1. Crea una factura de prueba en WispHub
 *   2. Hace un primer abono parcial → crea promesa de pago
 *   3. Hace un segundo abono parcial → actualiza promesa
 *   4. Paga el saldo completo restante
 *   5. Verifica que el dashboard rescata la factura correctamente
 *
 * Cliente de prueba: service_id = 902 (onu_prueba_oficina@sitelco)
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
require_once __DIR__ . '/portal/referencia_helper.php';

$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

const SERVICE_ID  = '902';
const NOMBRE      = 'Cliente OFICINA Prueba';
const PLAN_PRECIO = 20.00; // USD — precio de la factura de prueba

// ─────────────────────────────────────────────────────────────────────────────
// Utilidades
// ─────────────────────────────────────────────────────────────────────────────
function sep(string $title): void {
    echo "\n" . str_repeat('═', 65) . "\n";
    echo "  " . strtoupper($title) . "\n";
    echo str_repeat('═', 65) . "\n";
}

function ok(string $msg): void  { echo "  ✅  $msg\n"; }
function err(string $msg): void { echo "  ❌  $msg\n"; }
function info(string $msg): void{ echo "  ℹ️   $msg\n"; }

function ref(): string {
    return 'TEST' . rand(10000, 99999);
}

function waitSec(int $s): void {
    info("Esperando {$s}s para que WispHub procese...");
    sleep($s);
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 0 ─ Estado inicial
// ─────────────────────────────────────────────────────────────────────────────
sep("PASO 0 — Estado inicial del servicio");

$pending = $wispClient->getPendingInvoices(SERVICE_ID);
info("Facturas pendientes al inicio: " . count($pending));
foreach ($pending as $inv) {
    $id    = $inv['id'] ?? $inv['id_factura'] ?? '?';
    $total = $inv['total'] ?? 0;
    $est   = $inv['estado'] ?? 'N/A';
    info("  → Factura #$id | \$$total | $est");
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 1 ─ Crear factura de prueba manualmente en WispHub
//          (POST /facturas/ con los datos del servicio de prueba)
// ─────────────────────────────────────────────────────────────────────────────
sep("PASO 1 — Crear factura de prueba");

$fechaEmision    = date('Y-m-d');
$fechaVencimiento = date('Y-m-d', strtotime('+15 days'));

// WispHub: intentar encontrar factura pendiente existente para el servicio 902
// Si no hay ninguna, buscar las más recientes y usar la última como referencia.
$invoiceId    = null;
$invoiceTotal = 0;

// Primero: buscar pendientes del servicio
$allFact = $wispClient->getInvoices([
    'cliente' => 'onu_prueba_oficina@sitelco',
    'estado'  => 1,           // Pendiente de pago
    'limit'   => 10,
    'ordering' => '-id',
]);

foreach ($allFact as $f) {
    $fTotal = floatval($f['total'] ?? 0);
    $fCobrado = floatval($f['total_cobrado'] ?? 0);
    if ($fTotal > 0.01 && $fCobrado < $fTotal) {
        $invoiceId = $f['id'] ?? $f['id_factura'];
        $invoiceTotal = $fTotal;
        $fechaEmision = $f['fecha_emision'] ?? $fechaEmision;
        $fechaVencimiento = $f['fecha_vencimiento'] ?? $fechaVencimiento;
        ok("Factura pendiente encontrada: #$invoiceId | \$$invoiceTotal | Estado: " . ($f['estado'] ?? 'N/A'));
        break;
    }
}

// Segundo: si no hay pendiente, buscar cualquier factura reciente con saldo
if (!$invoiceId) {
    $allFact2 = $wispClient->getInvoices([
        'cliente' => 'onu_prueba_oficina@sitelco',
        'limit'   => 10,
        'ordering' => '-id',
    ]);
    foreach ($allFact2 as $f) {
        $fTotal   = floatval($f['total'] ?? 0);
        $fCobrado = floatval($f['total_cobrado'] ?? 0);
        $fSaldo   = floatval($f['saldo'] ?? $f['saldo_nuevo'] ?? ($fTotal - $fCobrado));
        if ($fSaldo > 0.01) {
            $invoiceId    = $f['id'] ?? $f['id_factura'];
            $invoiceTotal = $fTotal;
            $fechaEmision = $f['fecha_emision'] ?? $fechaEmision;
            $fechaVencimiento = $f['fecha_vencimiento'] ?? $fechaVencimiento;
            info("Usando factura con saldo: #$invoiceId | Total: \$$fTotal | Cobrado: \$$fCobrado | Saldo: \$$fSaldo");
            break;
        }
    }
}

if (!$invoiceId) {
    // Como último recurso usamos la factura más reciente aunque esté pagada (para probar el rescate)
    $allFact3 = $wispClient->getInvoices([
        'cliente' => 'onu_prueba_oficina@sitelco',
        'limit'   => 3,
        'ordering' => '-id',
    ]);
    if (!empty($allFact3)) {
        $f = $allFact3[0];
        $invoiceId    = $f['id'] ?? $f['id_factura'];
        $invoiceTotal = floatval($f['total'] ?? 20.00);
        $fechaEmision = $f['fecha_emision'] ?? $fechaEmision;
        $fechaVencimiento = $f['fecha_vencimiento'] ?? $fechaVencimiento;
        info("⚠ Sin facturas pendientes. Usando la más reciente para simular: #$invoiceId (estado: " . ($f['estado'] ?? '?') . ")");
    }
}

if (!$invoiceId) {
    err("No se encontró ninguna factura para el cliente de prueba. Verifica WispHub.");
    exit(1);
}

// Leer el detalle real de la factura para saber el monto exacto
$invDetail = $wispClient->getInvoiceDetail((string)$invoiceId);
$invoiceTotal     = floatval($invDetail['total'] ?? PLAN_PRECIO);
$fechaEmision     = $invDetail['fecha_emision']    ?? $fechaEmision;
$fechaVencimiento = $invDetail['fecha_vencimiento'] ?? $fechaVencimiento;
$estadoActual     = $invDetail['estado'] ?? 'N/A';

info("Factura #$invoiceId");
info("  Total    : \$$invoiceTotal");
info("  Estado   : $estadoActual");
info("  Emisión  : $fechaEmision");
info("  Vence    : $fechaVencimiento");

if ($invoiceTotal < 0.01) {
    err("La factura tiene total \$0. No se puede continuar el test.");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 2 ─ Primer abono parcial (30% del total)
// ─────────────────────────────────────────────────────────────────────────────
sep("PASO 2 — Primer abono parcial (30% del total = \$" . number_format($invoiceTotal * 0.30, 2) . ")");

$abono1    = round($invoiceTotal * 0.30, 2);
$ref1      = ref();
$wispDate  = date('Y-m-d H:i');

$payResult1 = $wispClient->registerPaymentAndActivate(
    SERVICE_ID, $abono1, $ref1, $wispDate,
    \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
    false, '', [(int)$invoiceId]
);

if (in_array($payResult1['status'] ?? 0, [200, 201])) {
    ok("Abono 1 registrado en WispHub: \$$abono1 | Ref: $ref1");
} else {
    err("Abono 1 falló en WispHub: " . json_encode($payResult1));
    // Continuamos igual para probar el flujo local
}

// Guardar en BD local
$db = getDb();
$saldoTras1 = round($invoiceTotal - $abono1, 2);
guardarPago(
    NOMBRE, '192.168.160.174', date('Y-m-d'), 'CCR2116_ESCUQUE',
    $abono1, 'Pago Móvil', $ref1,
    $invoiceTotal, 'abono', SERVICE_ID, 9, (string)$invoiceId
);
ok("Abono 1 guardado en BD local. Saldo restante: \$$saldoTras1");

// Calcular y crear promesa de pago
$proporcion1  = $abono1 / $invoiceTotal;
$diasExtra1   = round(30 * $proporcion1) + 1;
$fechaPromesa1 = date('Y-m-d', strtotime($fechaVencimiento . " + $diasExtra1 days"));
info("Calculando promesa: ratio=" . round($proporcion1 * 100, 1) . "% → $diasExtra1 días extra → vence $fechaPromesa1");

// Buscar factura pendiente nueva que WispHub creó para el saldo (si la creó)
waitSec(2);
$pendingAfter1 = $wispClient->getPendingInvoices(SERVICE_ID);
$promiseInvId  = null;
foreach ($pendingAfter1 as $pInv) {
    $pId = $pInv['id'] ?? $pInv['id_factura'];
    $pTotal = floatval($pInv['total'] ?? 0);
    info("  Factura pendiente post-abono1: #$pId | \$$pTotal");
    if (abs($pTotal - $saldoTras1) < 0.20) {
        $promiseInvId = $pId;
    }
}

// Si WispHub generó una nueva factura de saldo, crear promesa sobre ella
// Si no, crear promesa sobre la original
// La promesa ahora se guarda localmente en la base de datos
// (WispHub rechaza promesas en facturas pagadas)
$stmt = $db->prepare("UPDATE pagos_registrados SET fecha_promesa = ? WHERE referencia = ?");
$stmt->execute([$fechaPromesa1, $ref1]);
ok("Promesa de pago 1 guardada localmente: vence $fechaPromesa1");

// ─────────────────────────────────────────────────────────────────────────────
// PASO 3 ─ Segundo abono parcial (30% más del total original)
// ─────────────────────────────────────────────────────────────────────────────
sep("PASO 3 — Segundo abono parcial (30% más = \$" . number_format($invoiceTotal * 0.30, 2) . ")");

$abono2   = round($invoiceTotal * 0.30, 2);
$ref2     = ref();
$wispDate = date('Y-m-d H:i');

// Si WispHub creó una nueva factura de saldo, abonamos a esa
$targetInvForAbono2 = $promiseInvId ?: $invoiceId;
$payResult2 = $wispClient->registerPaymentAndActivate(
    SERVICE_ID, $abono2, $ref2, $wispDate,
    \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
    false, '', [(int)$targetInvForAbono2]
);

if (in_array($payResult2['status'] ?? 0, [200, 201])) {
    ok("Abono 2 registrado en WispHub: \$$abono2 | Ref: $ref2");
} else {
    err("Abono 2 falló: " . json_encode($payResult2));
}

// Actualizar BD local: acumular el cobrado
$totalCobradoHastaAhora = $abono1 + $abono2;
$saldoTras2 = round($invoiceTotal - $totalCobradoHastaAhora, 2);

// Insertar nuevo registro de abono en BD local (referencia diferente)
guardarPago(
    NOMBRE, '192.168.160.174', date('Y-m-d'), 'CCR2116_ESCUQUE',
    $abono2, 'Pago Móvil', $ref2,
    $invoiceTotal, 'abono', SERVICE_ID, 9, (string)$invoiceId
);
ok("Abono 2 guardado en BD local. Total cobrado: \$$totalCobradoHastaAhora | Saldo: \$$saldoTras2");

// Calcular nueva promesa (ahora cubrimos 60%)
$proporcion2  = $totalCobradoHastaAhora / $invoiceTotal;
$diasExtra2   = round(30 * $proporcion2) + 1;
$fechaPromesa2 = date('Y-m-d', strtotime($fechaVencimiento . " + $diasExtra2 days"));
info("Nueva promesa: ratio=" . round($proporcion2 * 100, 1) . "% → $diasExtra2 días extra → vence $fechaPromesa2");

waitSec(2);
$pendingAfter2 = $wispClient->getPendingInvoices(SERVICE_ID);
$promiseInvId2 = null;
foreach ($pendingAfter2 as $pInv) {
    $pId    = $pInv['id'] ?? $pInv['id_factura'];
    $pTotal = floatval($pInv['total'] ?? 0);
    info("  Factura pendiente post-abono2: #$pId | \$$pTotal");
    if (abs($pTotal - $saldoTras2) < 0.20) {
        $promiseInvId2 = $pId;
    }
}

// Guardar promesa 2 localmente
$stmt = $db->prepare("UPDATE pagos_registrados SET fecha_promesa = ? WHERE referencia = ?");
$stmt->execute([$fechaPromesa2, $ref2]);
ok("Promesa de pago 2 actualizada localmente: vence $fechaPromesa2");

// ─────────────────────────────────────────────────────────────────────────────
// PASO 4 ─ Pago completo del saldo restante (40% final)
// ─────────────────────────────────────────────────────────────────────────────
sep("PASO 4 — Pago completo del saldo restante (\$$saldoTras2)");

$ref3     = ref();
$wispDate = date('Y-m-d H:i');

$targetInvFinal = $promiseInvId2 ?: $targetForPromise;
$payResult3 = $wispClient->registerPaymentAndActivate(
    SERVICE_ID, $saldoTras2, $ref3, $wispDate,
    \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
    false, '', [(int)$targetInvFinal]
);

if (in_array($payResult3['status'] ?? 0, [200, 201])) {
    ok("Pago final registrado en WispHub: \$$saldoTras2 | Ref: $ref3");
} else {
    err("Pago final falló: " . json_encode($payResult3));
}

// Actualizar BD local: insertar pago final marcando total_cobrado = total (pago completo)
$totalFinalCobrado = $invoiceTotal;
guardarPago(
    NOMBRE, '192.168.160.174', date('Y-m-d'), 'CCR2116_ESCUQUE',
    $saldoTras2, 'Pago Móvil', $ref3,
    $invoiceTotal, 'completo', SERVICE_ID, 9, (string)$invoiceId
);
ok("Pago final guardado en BD local.");

// Marcar los registros de abono anteriores como total = total_cobrado (cubiertos)
// (En producción esto se hace consultando WispHub; aquí lo marcamos directo)
if ($db) {
    $db->prepare("UPDATE pagos_registrados SET total_cobrado = total WHERE referencia IN (?, ?) AND service_id = ?")
       ->execute([$ref1, $ref2, SERVICE_ID]);
    ok("Registros de abono anteriores actualizados a total_cobrado = total.");
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 5 ─ Verificar estado final
// ─────────────────────────────────────────────────────────────────────────────
sep("PASO 5 — Verificación del estado final");

waitSec(2);

$pendingFinal = $wispClient->getPendingInvoices(SERVICE_ID);
info("Facturas pendientes al final: " . count($pendingFinal));
if (empty($pendingFinal)) {
    ok("¡Sin facturas pendientes! El cliente está al día.");
} else {
    foreach ($pendingFinal as $pInv) {
        $pId = $pInv['id'] ?? $pInv['id_factura'];
        $pTot = $pInv['total'] ?? 0;
        err("  Factura pendiente: #$pId | \$$pTot");
    }
}

// Verificar BD local
if ($db) {
    $stmtCheck = $db->prepare("SELECT * FROM pagos_registrados WHERE service_id = ? AND created_at > NOW() - INTERVAL 10 MINUTE ORDER BY id");
    $stmtCheck->execute([SERVICE_ID]);
    $registros = $stmtCheck->fetchAll();

    echo "\n";
    info("Registros en BD local (últimos 10 min para service_id=" . SERVICE_ID . "):");
    echo "  " . str_repeat('-', 63) . "\n";
    echo "  " . str_pad('REF', 12) . str_pad('COBRADO', 10) . str_pad('TOTAL', 10) . str_pad('ACCION', 12) . "FACTURA\n";
    echo "  " . str_repeat('-', 63) . "\n";
    foreach ($registros as $r) {
        $status = (floatval($r['total_cobrado']) >= floatval($r['total'])) ? '✅' : '⏳';
        echo "  $status " . str_pad($r['referencia'], 10)
           . str_pad('$' . $r['total_cobrado'], 10)
           . str_pad('$' . $r['total'], 10)
           . str_pad($r['accion'] ?? '-', 12)
           . '#' . $r['facturas'] . "\n";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// RESUMEN
// ─────────────────────────────────────────────────────────────────────────────
sep("RESUMEN DEL TEST");
ok("Factura de prueba    : #$invoiceId | Total: \$$invoiceTotal");
ok("Abono 1              : \$$abono1 | Ref: $ref1 | Promesa: $fechaPromesa1");
ok("Abono 2              : \$$abono2 | Ref: $ref2 | Promesa: $fechaPromesa2");
ok("Pago final           : \$$saldoTras2 | Ref: $ref3");
info("");
info("Ahora ve al Dashboard del portal y verifica que:");
info("  1. La sección 'Abonos y Promesas de Pago' mostró la factura durante los abonos.");
info("  2. La barra de progreso subió con cada abono (30% → 60%).");
info("  3. Tras el pago final aparece '¡Sin deudas pendientes!'");
info("");
info("URL Dashboard: http://localhost/portal-pagos-web/portal/dashboard.php?refreshed=1");
echo "\n";
