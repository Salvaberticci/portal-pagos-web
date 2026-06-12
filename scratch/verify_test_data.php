<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
require_once __DIR__ . '/../paginas/conexion.php';

$config = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($config);

$cedula_test = 'V99999999';
$wispServiceId = '902';

echo "=== Verificación de datos de prueba ===\n\n";

// Check local contract
$r = $conn->query("SELECT id, estado, nombre_completo FROM contratos WHERE cedula = '$cedula_test'");
if ($r && $row = $r->fetch_assoc()) {
    echo "✅ Contrato local: #{$row['id']} [{$row['estado']}] - {$row['nombre_completo']}\n";
    $cid = $row['id'];
    
    // Check wisp_hub_links
    $r2 = $conn->query("SELECT id, wisp_account_id, status FROM wisp_hub_links WHERE contract_id = $cid ORDER BY id DESC LIMIT 1");
    if ($r2 && $row2 = $r2->fetch_assoc()) {
        echo "✅ WispHub link: ID={$row2['wisp_account_id']} [{$row2['status']}]\n";
        if ($row2['wisp_account_id'] === $wispServiceId) {
            echo "   → Coincide con el servicio real 902 en WispHub\n";
        }
    } else {
        echo "❌ No hay wisp_hub_links para este contrato\n";
    }
    
    // Check CxC
    $r3 = $conn->query("SELECT id_cobro, monto_total, estado, fecha_vencimiento FROM cuentas_por_cobrar WHERE id_contrato = $cid ORDER BY id_cobro DESC LIMIT 1");
    if ($r3 && $row3 = $r3->fetch_assoc()) {
        echo "✅ CxC: #{$row3['id_cobro']} \${$row3['monto_total']} [{$row3['estado']}] vence: {$row3['fecha_vencimiento']}\n";
    } else {
        echo "⚠️  No hay cuentas_por_cobrar\n";
    }
} else {
    echo "❌ No hay contrato para V99999999\n";
}

echo "\n=== Estado actual del servicio 902 en WispHub ===\n\n";
$res = $client->getServiceBalance($wispServiceId);
if ($res['status'] === 200) {
    echo "📡 Servicio: {$res['data']['nombre']}\n";
    echo "📊 Estado: {$res['data']['estado']}\n";
    echo "💰 Saldo: \${$res['data']['saldo']}\n";
    if (!empty($res['data']['facturas'])) {
        foreach ($res['data']['facturas'] as $f) {
            echo "📄 Factura #{$f['id']}: \${$f['total']} vence {$f['fecha_vencimiento']}\n";
        }
    }
    echo "🔗 Router: {$res['data']['router']['nombre']} ({$res['data']['router']['ip']})\n";
}

echo "\n=== PRÓXIMOS PASOS ===\n";
echo "Todo está listo para probar desde el navegador:\n";
echo "1. Ir a: http://localhost/sistemas-administrativo-tecnico-wireless/portal/index.php\n";
echo "2. Ingresar con cédula: V99999999\n";
echo "3. Reportar pago con ref: 999222, monto: 1.00\n";
echo "4. BDV auto-aprobará → WispHub activará servicio 902\n\n";
echo "O ejecutar prueba automatizada:\n";
echo "php tests/test_complete_payment_flow.php\n";

$conn->close();
