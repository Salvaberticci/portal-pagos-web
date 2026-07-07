<?php
// Test script to simulate the reference parsing logic

// 1. Datos simulados del escenario real
$referencia_tecleada_por_cliente = "8998874"; // El cliente omitió un dígito
$referencia_api_banco = "059138998874";       // Lo que responde la API de Banco de Venezuela

$verificacion_data = [
    'movimiento' => [
        'referencia_banco' => $referencia_api_banco,
        'importe_bs' => 6750.00
    ]
];

$monto_banco_bs = 6750.00;

echo "--- DATOS ORIGINALES ---\n";
echo "Ref tecleada por cliente : " . $referencia_tecleada_por_cliente . "\n";
echo "Ref reportada por banco  : " . $referencia_api_banco . "\n\n";

echo "--- SIMULANDO LÓGICA DE PROCESAR PAGO ---\n";

// A. Lógica para guardar en BD Local (Líneas 156-163 en procesar_pago_cliente.php)
$referencia_bd = $referencia_tecleada_por_cliente;

if (!empty($verificacion_data['movimiento']['referencia_banco'])) {
    $ref_banco_raw = preg_replace('/\D/', '', $verificacion_data['movimiento']['referencia_banco']);
    if (strlen($ref_banco_raw) >= 8) {
        $referencia_bd = substr($ref_banco_raw, -8);
    }
}
echo "[1] Ref a guardar en BD Local : " . $referencia_bd . "\n";


// B. Lógica para enviar a WispHub (Líneas 311-326 en procesar_pago_cliente.php)
$ref_fuente = $referencia_bd; // Se usa como base la de la BD
if (!empty($verificacion_data['movimiento']['referencia_banco'])) {
    $ref_banco = preg_replace('/\D/', '', $verificacion_data['movimiento']['referencia_banco']);
    if (strlen($ref_banco) >= 8) {
        $ref_fuente = $ref_banco;
    }
}
$ref_8_chars = substr($ref_fuente, -8);
$monto_plano = intval($monto_banco_bs);
$referencia_wisp = $ref_8_chars . '-' . $monto_plano;

echo "[2] Ref a enviar a WispHub  : " . $referencia_wisp . " (Formato: ultimos_8_digitos-montobs)\n\n";

echo "--- SIMULANDO LÓGICA DEL FRONTEND (UI) ---\n";
// En pago.php, línea 828, hace esto en Javascript:
// data.movimiento.referencia_banco.slice(-8)
$ref_pantalla = substr($referencia_api_banco, -8);
echo "[3] Ref a mostrar en Pantalla : " . $ref_pantalla . "\n";

echo "\n--- RESULTADO FINAL ---\n";
if ($referencia_bd === '38998874' && $ref_8_chars === '38998874' && $ref_pantalla === '38998874') {
    echo "¡TEST PASADO! El sistema usa '38998874' consistentemente en todas partes.\n";
} else {
    echo "ERROR: Las referencias no coinciden.\n";
}
?>
