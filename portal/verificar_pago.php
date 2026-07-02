<?php
// Verificación del pago fraccionado - app.marateltru.com/portal/verificar_pago.php
// Solo accesible con token secreto para evitar uso público
$token_valido = 'm4r4t3lt2026';
if (!isset($_GET['token']) || $_GET['token'] !== $token_valido) {
    http_response_code(403);
    die('Acceso denegado');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
$cfg = include __DIR__ . '/../config/wisp_hub.php';
$wisp = new \Services\WispHubClient($cfg);

$serviceId = $_GET['id'] ?? '902';
$username = $_GET['user'] ?? 'onu_prueba_oficina@sitelco';

echo "<!DOCTYPE html><html><head><title>Verificar Pago Fraccionado</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#0f0}";
echo "h2{color:#0ff}.ok{color:#0f0}.fail{color:#f00}.info{color:#ff0}";
echo "table{border-collapse:collapse;width:100%}td,th{border:1px solid #333;padding:6px;text-align:left}</style></head><body>";
echo "<h2>🔍 Verificación - Pago Fraccionado (WispHub)</h2>";

echo "<h3>1. Facturas pendientes de $username</h3>";
$pending = $wisp->getPendingInvoices($serviceId);
if (empty($pending)) {
    echo "<p class='info'>No hay facturas pendientes</p>";
} else {
    echo "<table><tr><th>ID</th><th>Total</th><th>Vence</th><th>Descripción</th></tr>";
    foreach ($pending as $inv) {
        $id = $inv['id'] ?? $inv['id_factura'] ?? '?';
        $total = $inv['total'] ?? 0;
        $venc = $inv['fecha_vencimiento'] ?? '';
        $desc = $inv['concepto'] ?? $inv['descripcion'] ?? '';
        if (empty($desc) && !empty($inv['articulos'])) {
            $desc = $inv['articulos'][0]['descripcion'] ?? '';
        }
        $esSaldo = strpos($desc, 'Saldo pendiente tras abono') === 0;
        $tag = $esSaldo ? "<span class='ok'>✅ SALDO PENDIENTE</span>" : '';
        echo "<tr><td>#$id</td><td>\$$total</td><td>$venc</td><td>" . htmlspecialchars($desc) . " $tag</td></tr>";
    }
    echo "</table>";
}

echo "<h3>2. Últimas facturas creadas (estado pendiente)</h3>";
$invoices = $wisp->getInvoices(['cliente' => $username, 'estado' => 1, 'limit' => 20, 'ordering' => '-id']);
if (empty($invoices)) {
    echo "<p class='info'>No hay facturas pendientes en getInvoices</p>";
} else {
    echo "<table><tr><th>ID</th><th>Total</th><th>Emisión</th><th>Vence</th><th>Descripción</th></tr>";
    foreach ($invoices as $inv) {
        $id = $inv['id'] ?? $inv['id_factura'] ?? '?';
        $total = $inv['total'] ?? 0;
        $emi = $inv['fecha_emision'] ?? '';
        $venc = $inv['fecha_vencimiento'] ?? '';
        $desc = $inv['concepto'] ?? $inv['descripcion'] ?? '';
        if (empty($desc) && !empty($inv['articulos'])) {
            $desc = $inv['articulos'][0]['descripcion'] ?? '';
        }
        $esSaldo = strpos($desc, 'Saldo pendiente tras abono') === 0;
        $tag = $esSaldo ? "<span class='ok'>✅</span>" : '';
        echo "<tr><td>#$id</td><td>\$$total</td><td>$emi</td><td>$venc</td><td>" . htmlspecialchars($desc) . " $tag</td></tr>";
    }
    echo "</table>";
}

echo "<h3>3. Promesas de pago activas</h3>";
$promesas = $wisp->getInvoices(['cliente' => $username, 'estado' => 1, 'limit' => 50, 'ordering' => '-id']);
$conPromesa = 0;
foreach ($promesas as $inv) {
    $id = $inv['id'] ?? $inv['id_factura'] ?? 0;
    if ($id) {
        $detail = $wisp->getInvoiceDetail((string)$id);
        if (!empty($detail['promesa_fecha']) || !empty($detail['fecha_promesa'])) {
            $pf = $detail['promesa_fecha'] ?? $detail['fecha_promesa'] ?? '';
            echo "<p class='ok'>✅ Factura #$id - Promesa: $pf</p>";
            $conPromesa++;
        }
    }
}
if ($conPromesa === 0) echo "<p class='info'>No hay promesas de pago activas detectadas</p>";

echo "<h3>✅ Resumen</h3>";
echo "<p>Si ves facturas con descripción <strong>'Saldo pendiente tras abono - Factura #...'</strong> y monto = saldo restante, el sistema funciona correctamente.</p>";

// Mostrar cálculo de fechas para depuración
echo "<h3>4. Cálculo de fechas (abono)</h3>";
$detalle = $wisp->getServiceDetail($serviceId);
$precioPlan = floatval($detalle['data']['precio_plan'] ?? 0);
$fechaCorte = $detalle['data']['fecha_corte'] ?? 'N/A';
echo "<table><tr><th>Campo</th><th>Valor</th></tr>";
echo "<tr><td>precio_plan (plan del cliente)</td><td>\$$precioPlan USD</td></tr>";
echo "<tr><td>fecha_corte (DIA PAGO)</td><td>$fechaCorte</td></tr>";
echo "<tr><td>30 días =</td><td>\$$precioPlan USD</td></tr>";
echo "<tr><td>Si paga \$\$10 → días</td><td>" . ($precioPlan > 0 ? round(30 * (10/$precioPlan)) : 'N/A') . " días</td></tr>";
echo "<tr><td>WispHub promesa (+1 día)</td><td>portal + 1 día</td></tr>";
echo "</table>";
echo "<p class='info'>ℹ️ El portal muestra la fecha calculada (sin +1). WispHub recibe la fecha +1 día.</p>";

$totalSaldo = 0;
foreach ($pending as $inv) {
    $desc = $inv['concepto'] ?? $inv['descripcion'] ?? '';
    if (empty($desc) && !empty($inv['articulos'])) {
        $desc = $inv['articulos'][0]['descripcion'] ?? '';
    }
    if (strpos($desc, 'Saldo pendiente tras abono') === 0) {
        $totalSaldo += floatval($inv['total'] ?? 0);
    }
}
if ($totalSaldo > 0) {
    echo "<p class='ok'>✅ Total en facturas de saldo pendiente: <strong>\$$totalSaldo</strong></p>";
} else {
    echo "<p class='info'>ℹ️ No hay facturas de saldo pendiente activas. Hacé un pago fraccionado primero.</p>";
}

echo "<hr><p class='info'>URL de ejemplo: <code>https://app.marateltru.com/portal/verificar_pago.php?token=m4r4t3lt2026&id=902</code></p>";
echo "</body></html>";
