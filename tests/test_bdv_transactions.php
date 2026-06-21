<?php
/**
 * Test: Consultar todas las transacciones BDV de hoy y por rango de fechas
 * Uso: php tests/test_bdv_transactions.php [fecha_ini] [fecha_fin]
 * Ej:  php tests/test_bdv_transactions.php 2026-06-17 2026-06-20
 */

require_once __DIR__ . '/../paginas/principal/banco_api_router.php';

$hoy = (new DateTime('now', new DateTimeZone('America/Caracas')))->format('Y-m-d');
$fecha_ini = $argv[1] ?? $hoy;
$fecha_fin = $argv[2] ?? $hoy;

echo "========================================\n";
echo "  BDV API - Consulta de Transacciones\n";
echo "========================================\n";
echo "Hora servidor: " . date('Y-m-d H:i:s') . "\n";
echo "Hora Caracas:  $hoy\n";
echo "Rango: $fecha_ini a $fecha_fin\n\n";

$start = microtime(true);
$resultado = consultar_movimientos_banco(9, $fecha_ini, $fecha_fin);
$elapsed = round((microtime(true) - $start) * 1000);

echo "--- RESULTADO ---\n";
echo "success: " . ($resultado['success'] ? 'true' : 'false') . "\n";
echo "tiempo: {$elapsed}ms\n";
echo "message: " . ($resultado['message'] ?? 'N/A') . "\n";
echo "total movs: " . count($resultado['movs'] ?? []) . "\n\n";

if (!empty($resultado['error'])) {
    echo "error: " . $resultado['error'] . "\n\n";
}

if (!empty($resultado['raw'])) {
    echo "--- RAW (metadata) ---\n";
    $raw = $resultado['raw'];
    foreach (['code','message','status'] as $k) {
        if (isset($raw[$k])) echo "  $k: " . var_export($raw[$k], true) . "\n";
    }
    if (isset($raw['data']['totalOfMovements'])) {
        echo "  totalOfMovements: " . $raw['data']['totalOfMovements'] . "\n";
    }
    echo "\n";
}

$movs = $resultado['movs'] ?? [];
if (empty($movs)) {
    echo "!! No se encontraron movimientos en el rango.\n";
    echo "   Posibles causas:\n";
    echo "   - El rango incluye domingo (BDV no devuelve datos)\n";
    echo "   - No hubo transacciones en esas fechas\n";
    echo "   - La API del banco no está disponible\n\n";
    exit(0);
}

// Resumen por tipo
$creditos = 0;
$debitos = 0;
foreach ($movs as $m) {
    $tipo = strtoupper($m['mov'] ?? $m['Tipo'] ?? '');
    if ($tipo === 'CREDITO') $creditos++;
    elseif (strpos($tipo, 'DEBITO') !== false || $tipo === 'DEBITO') $debitos++;
}
echo "--- RESUMEN ---\n";
echo "Total movs: " . count($movs) . "\n";
echo "Créditos: $creditos\n";
echo "Débitos: $debitos\n\n";

echo "--- TRANSACCIONES (últimas 20) ---\n";
$show = array_slice($movs, -20);
foreach (array_reverse($show) as $i => $m) {
    $tipo = $m['mov'] ?? $m['Tipo'] ?? '?';
    $fecha = $m['fecha'] ?? '?';
    $hora = $m['hora'] ?? '';
    $ref = $m['referencia'] ?? '?';
    $importe = $m['importe'] ?? $m['monto'] ?? '?';
    $desc = $m['descripcion'] ?? '';
    $obs = $m['observacion'] ?? '';

    $icon = strtoupper($tipo) === 'CREDITO' ? '💰' : '💳';
    echo "$icon #" . (count($show)-$i) . " $fecha $hora | $tipo | Ref: $ref | Bs $importe\n";
    if ($desc) echo "     Desc: $desc\n";
    if ($obs) echo "     Obs: $obs\n";
    echo "\n";
}

echo "--- MOVIMIENTOS CRÉDITO (referencias disponibles) ---\n";
$creditosMovs = array_filter($movs, function($m) {
    $tipo = strtoupper($m['mov'] ?? $m['Tipo'] ?? '');
    $desc = strtoupper($m['descripcion'] ?? '');
    return $tipo === 'CREDITO' && strpos($desc, 'DEBITO') === false;
});
foreach ($creditosMovs as $m) {
    $ref = preg_replace('/\D/', '', $m['referencia'] ?? '');
    $ref6 = strlen($ref) >= 6 ? substr($ref, -6) : $ref;
    $importe = $m['importe'] ?? $m['monto'] ?? '?';
    $fecha = $m['fecha'] ?? '?';
    echo "  Ref: $ref ({$ref6}) | Bs $importe | $fecha\n";
}
echo "Total créditos: " . count($creditosMovs) . "\n";

echo "\n========================================\n";
echo "  FIN\n";
echo "========================================\n";
