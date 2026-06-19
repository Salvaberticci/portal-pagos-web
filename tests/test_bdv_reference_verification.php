<?php
require_once __DIR__ . '/../paginas/principal/bdv_api_helper.php';

$test_ref = $argv[1] ?? '250316';
$cuenta   = '01020589150000001371';
$api_key  = '650D973744E70DFD936382F9B734405A';
$endpoint = 'https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos';

$ref_clean = preg_replace('/\D/', '', $test_ref);
$ref_6 = strlen($ref_clean) >= 6 ? substr($ref_clean, -6) : $ref_clean;
$ref_8 = strlen($ref_clean) >= 8 ? substr($ref_clean, -8) : $ref_clean;

$pasaron = 0;
$fallaron = 0;

echo "=== Test: Verificaci\u00f3n de referencia bancaria BDV ===\n\n";
echo "Cuenta: $cuenta\n";
echo "Ref a buscar: $test_ref (clean=$ref_clean, last6=$ref_6, last8=$ref_8)\n\n";

$movs_cache = [];
for ($paso = 1; $paso <= 3; $paso++) {
    switch ($paso) {
        case 1:
            $label = "HOY (-2/+1 d\u00edas)";
            $f_ini = date('Y-m-d', strtotime('-2 days'));
            $f_fin = min(date('Y-m-d', strtotime('+1 day')), date('Y-m-d'));
            break;
        case 2:
            $label = "AYER (-3/-1 d\u00edas)";
            $ayer = date('Y-m-d', strtotime('-1 day'));
            $f_ini = date('Y-m-d', strtotime('-3 days'));
            $f_fin = min(date('Y-m-d', strtotime('+0 days', strtotime($ayer))), date('Y-m-d'));
            break;
        case 3:
            $label = "\u00daltima semana (-7 d\u00edas)";
            $f_ini = date('Y-m-d', strtotime('-7 days'));
            $f_fin = date('Y-m-d');
            break;
    }
    echo "--- Paso $paso: $label ($f_ini -> $f_fin) ---\n";
    $result = consultar_movimientos_bdv($cuenta, $f_ini, $f_fin, '', $api_key, $endpoint);
    if (!$result['success']) {
        echo "  ERROR: {$result['message']}\n";
        $fallaron++;
        continue;
    }
    echo "  Movimientos: " . count($result['movs']) . "\n";
    $found = false;
    foreach ($result['movs'] as $m) {
        $tipo = strtoupper($m['mov'] ?? $m['Tipo'] ?? '');
        $desc = strtoupper($m['descripcion'] ?? '');
        if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
        $ref_banco = $m['referencia'] ?? '';
        if (empty($ref_banco)) continue;
        $ref_banco_clean = preg_replace('/\D/', '', $ref_banco);
        $ref_banco_6 = strlen($ref_banco_clean) >= 6 ? substr($ref_banco_clean, -6) : $ref_banco_clean;
        $ref_banco_8 = strlen($ref_banco_clean) >= 8 ? substr($ref_banco_clean, -8) : $ref_banco_clean;
        if (
            $ref_banco_clean === $ref_clean ||
            ($ref_banco_8 !== '' && $ref_banco_8 === $ref_8) ||
            ($ref_banco_6 !== '' && $ref_banco_6 === $ref_6)
        ) {
            echo "    >>> COINCIDENCIA: Ref banco=$ref_banco Bs.{$m['importe']}\n";
            $found = true;
        }
    }
    if ($found) {
        echo "  \u2705 ENCONTRADA\n";
        $pasaron++;
    } else {
        echo "  \u274c NO ENCONTRADA\n";
        $fallaron++;
    }
    if ($paso === 1) $movs_cache = $result['movs'] ?? [];
}

// Paso 4: buscar_movimiento_bdv oficial
echo "\n--- Paso 4: buscar_movimiento_bdv() oficial ---\n";
if (!empty($movs_cache)) {
    $mov = buscar_movimiento_bdv($movs_cache, $test_ref, 0, 999999);
    if ($mov) {
        echo "  \u2705 ENCONTRADO: Ref={$mov['referencia']} Bs={$mov['importe']}\n";
        $pasaron++;
    } else {
        echo "  \u274c NO ENCONTRADO\n";
        $fallaron++;
    }
} else {
    echo "  \u26a0\u200d Sin datos del paso 1 para buscar_movimiento_bdv\n";
}

echo "\n=== RESUMEN ===\n";
echo "\u2705 Pasaron: $pasaron\n";
echo "\u274c Fallaron: $fallaron\n";
echo "Fecha servidor: " . date('Y-m-d H:i:s') . "\n";
