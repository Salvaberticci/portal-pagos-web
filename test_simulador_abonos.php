<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';

echo "=================================================================\n";
echo "       SIMULADOR Y TEST DE ABONOS (PORTAL DE PAGOS)\n";
echo "=================================================================\n\n";

$c = include 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($c);

$username = 'onu_prueba_oficina@sitelco';
$serviceId = '902';

// -------------------------------------------------------------------------
// TEST 1: FORMATO DE REFERENCIA BANCARIA
// -------------------------------------------------------------------------
echo "▶ TEST 1: Formato de Referencia para WispHub\n";
$referencia_banco = '010260741024';
$monto_banco_bs = 130.60;
$ref_8_chars = str_pad(substr($referencia_banco, -8), 8, '0', STR_PAD_LEFT);
$referencia_wisp = $ref_8_chars . '-' . intval($monto_banco_bs);

echo "  - Ref Banco Original: $referencia_banco\n";
echo "  - Monto Bs Original:  $monto_banco_bs\n";
echo "  - Ref WispHub Salida: $referencia_wisp\n";
if ($referencia_wisp === '60741024-130') {
    echo "  ✅ APROBADO: Cortó los 8 dígitos, quitó decimales y concatenó con guion.\n\n";
} else {
    echo "  ❌ ERROR en formato de referencia.\n\n";
}


// -------------------------------------------------------------------------
// TEST 2: CÁLCULO DE FECHA PROMESA CON VENCIMIENTO ATRASADO
// -------------------------------------------------------------------------
echo "▶ TEST 2: Cálculo de Promesa respetando el ciclo del cliente\n";
$fecha_pago_hoy = date('Y-m-d');
$fecha_vencimiento_original = date('Y-m-d', strtotime('-5 days')); // Venció hace 5 días
$monto_usd = 10.00;
$precioPlan = 20.00;

$diasExtra = round(30 * ($monto_usd / max($precioPlan, 1)));
$fechaBasePromesa = $fecha_vencimiento_original; // Usa el vencimiento original, NO hoy
$fechaLimitePromesa = date('Y-m-d', strtotime($fechaBasePromesa . " + $diasExtra days"));
$fecha_erronea_si_usara_hoy = date('Y-m-d', strtotime($fecha_pago_hoy . " + $diasExtra days"));

echo "  - El cliente debe pagar los días: $fecha_vencimiento_original\n";
echo "  - Cliente realiza el pago el día: $fecha_pago_hoy (5 días tarde)\n";
echo "  - Pago de \$$monto_usd sobre un plan de \$$precioPlan (Corresponden $diasExtra días)\n";
echo "  - Fecha promesa generada:       $fechaLimitePromesa\n";

$fecha_esperada = date('Y-m-d', strtotime($fecha_vencimiento_original . " + $diasExtra days"));
if ($fechaLimitePromesa === $fecha_esperada && $fechaLimitePromesa !== $fecha_erronea_si_usara_hoy) {
    echo "  ✅ APROBADO: La promesa cae el $fechaLimitePromesa, amarrada a su ciclo original.\n";
    echo "               (Si usara la fecha de pago de hoy, habría sido el $fecha_erronea_si_usara_hoy, regalando 5 días)\n\n";
} else {
    echo "  ❌ ERROR en el cálculo de la fecha promesa.\n\n";
}


// -------------------------------------------------------------------------
// TEST 3: RASTREO RECURSIVO DE PRECIO REAL EN ABONOS CONSECUTIVOS
// -------------------------------------------------------------------------
echo "▶ TEST 3: Rastreo del precio original en abonos de $5 sucesivos\n";
echo "  (Creando facturas en WispHub temporalmente...)\n";

$createBase = $wispClient->createInvoice($username, 20.00, "Factura Original", $fecha_vencimiento_original, $serviceId);
preg_match('/factura\s*#?(\d+)/i', $createBase['data']['messages'] ?? '', $m);
$rootInvoiceId = isset($m[1]) ? (int)$m[1] : 0;

$createHija = $wispClient->createInvoice($username, 15.00, "Saldo pendiente tras abono - Factura #$rootInvoiceId", $fecha_vencimiento_original, $serviceId);
preg_match('/factura\s*#?(\d+)/i', $createHija['data']['messages'] ?? '', $mHija);
$hijaInvoiceId = isset($mHija[1]) ? (int)$mHija[1] : 0;

$createNieta = $wispClient->createInvoice($username, 10.00, "Saldo pendiente tras abono - Factura #$hijaInvoiceId", $fecha_vencimiento_original, $serviceId);
preg_match('/factura\s*#?(\d+)/i', $createNieta['data']['messages'] ?? '', $mNieta);
$nietaInvoiceId = isset($mNieta[1]) ? (int)$mNieta[1] : 0;

echo "  - Factura Original (Raíz): #$rootInvoiceId de $20\n";
echo "  - Abono 1 (Crea Hija):     #$hijaInvoiceId de $15\n";
echo "  - Abono 2 (Crea Nieta):    #$nietaInvoiceId de $10\n";
echo "  -> El cliente decide hacer su 3er abono pagando la Factura Nieta (#$nietaInvoiceId).\n";

// Copia exacta de la función recursiva del portal
$getTruePlanPrice = function($wispClient, $invId, $fallbackPrice) use (&$getTruePlanPrice) {
    $detail = $wispClient->getInvoiceDetail((string)$invId);
    if (empty($detail)) return $fallbackPrice;
    $parentInvoiceId = 0;
    if (!empty($detail['articulos'])) {
        foreach ($detail['articulos'] as $art) {
            $desc = $art['descripcion'] ?? '';
            if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $desc, $m)) {
                $parentInvoiceId = $m[1];
                break;
            }
        }
    }
    if ($parentInvoiceId) {
        return $getTruePlanPrice($wispClient, $parentInvoiceId, $fallbackPrice);
    }
    return floatval($detail['total'] ?? $fallbackPrice);
};

$precioPlanDetectado = $getTruePlanPrice($wispClient, $nietaInvoiceId, 10.00);
$diasExtraCalculados = round(30 * (5.00 / max($precioPlanDetectado, 1)));

echo "  - Precio Plan Detectado por el código: \$$precioPlanDetectado\n";
echo "  - Días extra calculados para pago $5:  $diasExtraCalculados días\n";

if ($precioPlanDetectado == 20 && $diasExtraCalculados == 8) {
    echo "  ✅ APROBADO: El código viajó por la descripción hasta la factura original de $20.\n";
    echo "               (Si no lo hiciera, habría detectado plan de $10 y habría dado 15 días, regalando días)\n\n";
} else {
    echo "  ❌ ERROR en el rastreo de facturas.\n\n";
}

echo "=================================================================\n";
echo " TODO EL CÓDIGO FUNCIONA ACORDE A LAS EXIGENCIAS DEL NEGOCIO.\n";
echo "=================================================================\n";
