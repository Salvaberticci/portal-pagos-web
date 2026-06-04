<?php
require dirname(__DIR__) . '/paginas/conexion.php';

$tasa_bcv = 39.50;
$cedula = 'V99999999';

echo "--- Simulación de procesar_pago_cliente.php ---\n";
// Caso 1: Deuda actual (Bs 1.00)
$monto_usd_input = 1.00 / $tasa_bcv; 
$tasa_dolar = $tasa_bcv;
$monto_bs = round($monto_usd_input * $tasa_dolar, 2);

echo "Antes del override: USD: $monto_usd_input | Bs: $monto_bs\n";

if ($cedula === 'V99999999') {
    $monto_bs_int = round($monto_bs);
    if (abs($monto_bs - $monto_bs_int) < 0.2) {
        $monto_bs = $monto_bs_int;
    }
    if ($monto_bs <= 0) {
        $monto_bs = 1.00;
    }
    $monto_usd_final = $monto_bs / ($tasa_dolar > 0 ? $tasa_dolar : 1);
}

echo "Después del override: USD: $monto_usd_final | Bs: $monto_bs\n\n";

// Caso 2: Deuda + 1 Mes (Bs 2.00)
$monto_usd_input = 2.00 / $tasa_bcv; 
$monto_bs = round($monto_usd_input * $tasa_dolar, 2);

echo "Antes del override (Caso 2): USD: $monto_usd_input | Bs: $monto_bs\n";

if ($cedula === 'V99999999') {
    $monto_bs_int = round($monto_bs);
    if (abs($monto_bs - $monto_bs_int) < 0.2) {
        $monto_bs = $monto_bs_int;
    }
    if ($monto_bs <= 0) {
        $monto_bs = 1.00;
    }
    $monto_usd_final = $monto_bs / ($tasa_dolar > 0 ? $tasa_dolar : 1);
}

echo "Después del override (Caso 2): USD: $monto_usd_final | Bs: $monto_bs\n";
