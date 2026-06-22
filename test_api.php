<?php
require_once __DIR__ . '/paginas/principal/banco_api_router.php';

echo "=== Test buscar_referencia 851396 ===\n";
$id_banco = 12; // BDV Transferencia o 9 BDV Pago Movil
$fecha_pago = '2026-06-21';
$ts  = strtotime($fecha_pago);
$fecha_inicio_busqueda = date('Y-m-d', strtotime('-10 days', $ts));
$fecha_fin_busqueda   = date('Y-m-d', strtotime('+1 day',  $ts));

$fase1 = consultar_movimientos_rango(9, $fecha_inicio_busqueda, $fecha_fin_busqueda);
echo "API response fase 1 (id_banco 9):\n";
echo "api_respondio: " . ($fase1['api_respondio'] ? 'true' : 'false') . "\n";
echo "total movs: " . count($fase1['movs']) . "\n";

$mov_ref = buscar_referencia_en_movs($fase1['movs'], '851396', 'Pago Móvil');
if ($mov_ref) {
    echo "Encontrado en banco 9:\n";
    print_r($mov_ref);
} else {
    echo "NO encontrado en banco 9.\n";
}

$fase1_12 = consultar_movimientos_rango(12, $fecha_inicio_busqueda, $fecha_fin_busqueda);
echo "\nAPI response fase 1 (id_banco 12):\n";
echo "api_respondio: " . ($fase1_12['api_respondio'] ? 'true' : 'false') . "\n";
echo "total movs: " . count($fase1_12['movs']) . "\n";

$mov_ref_12 = buscar_referencia_en_movs($fase1_12['movs'], '851396', 'Transferencia');
if ($mov_ref_12) {
    echo "Encontrado en banco 12:\n";
    print_r($mov_ref_12);
} else {
    echo "NO encontrado en banco 12.\n";
}
