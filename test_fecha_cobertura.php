<?php
/**
 * TEST: Verificar cálculo de fechas de cobertura para pagos parciales
 * Caso real: Ana Roymar Villegas (V26094384)
 * 
 * Factura original #9852:
 *   - Plan: $20 USD
 *   - Período: 1/Jul./2026 al 31/Jul./2026
 *   - fecha_emision: 2026-06-25
 *   - Pago parcial: $10.12 USD el 2026-07-07
 * 
 * Factura saldo pendiente #10259:
 *   - Saldo: $9.88 USD
 *   - Descripción: "Saldo pendiente tras abono - Factura #9852"
 *   - No tiene "Periodo del..." en la descripción
 * 
 * Esperado para primer abono ($10.12 de $20):
 *   días = round(30 * 10.12/20) = round(15.18) = 15
 *   fechaBase = 2026-07-01 (inicio del período, NO fecha_emision 2026-06-25)
 *   cobertura = 2026-07-01 + 15 días = 2026-07-16 ✓
 */
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($config);

echo "=== TEST: Cálculo de fechas de cobertura para pagos parciales ===\n\n";

// --- Función recursiva (copiada del código de producción) ---
$getTruePeriodStart = function($wispClient, $invId, $fallbackDate) use (&$getTruePeriodStart) {
    $detail = $wispClient->getInvoiceDetail((string)$invId);
    if (empty($detail)) return $fallbackDate;
    
    $meses = ['Ene'=>'01','Feb'=>'02','Mar'=>'03','Abr'=>'04','May'=>'05','Jun'=>'06',
              'Jul'=>'07','Ago'=>'08','Sep'=>'09','Oct'=>'10','Nov'=>'11','Dic'=>'12'];
    
    $parentInvoiceId = 0;
    if (!empty($detail['articulos'])) {
        foreach ($detail['articulos'] as $art) {
            $desc = $art['descripcion'] ?? '';
            if (preg_match('/Periodo del\s+(\d{1,2})\/([A-Za-z.]+)\/(\d{4})/i', $desc, $m)) {
                $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                $monthStr = ucfirst(strtolower(str_replace('.', '', $m[2])));
                if (isset($meses[$monthStr])) {
                    return $m[3] . '-' . $meses[$monthStr] . '-' . $day;
                }
            }
            if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $desc, $m2)) {
                $parentInvoiceId = $m2[1];
            } elseif (preg_match('/Saldo Pendiente de Pago de la Factura (\d+)/i', $desc, $m2)) {
                $parentInvoiceId = $m2[1];
            }
        }
    }
    
    if ($parentInvoiceId) {
        return $getTruePeriodStart($wispClient, $parentInvoiceId, $fallbackDate);
    }
    return $detail['fecha_pago'] ? substr($detail['fecha_pago'], 0, 10) : $fallbackDate;
};

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

// ===== CASO 1: Primer abono contra factura original #9852 =====
echo "--- CASO 1: Pago parcial directo contra factura original #9852 ---\n";
$invId1 = 9852;
$montoAbono1 = 10.12;
$inv1 = $wispClient->getInvoiceDetail((string)$invId1);
echo "Factura #$invId1:\n";
echo "  fecha_emision:     " . ($inv1['fecha_emision'] ?? 'N/A') . "\n";
echo "  fecha_vencimiento: " . ($inv1['fecha_vencimiento'] ?? 'N/A') . "\n";
echo "  fecha_pago:        " . ($inv1['fecha_pago'] ?? 'N/A') . "\n";
echo "  total:             $" . ($inv1['total'] ?? 'N/A') . "\n";
$desc1 = $inv1['articulos'][0]['descripcion'] ?? 'N/A';
echo "  descripcion:       " . trim(preg_replace('/\s+/', ' ', $desc1)) . "\n";

$precioPlan1 = $getTruePlanPrice($wispClient, $invId1, floatval($inv1['total']));
$fechaBase1 = $getTruePeriodStart($wispClient, $invId1, $inv1['fecha_emision'] ?? date('Y-m-d'));
$diasExtra1 = round(30 * ($montoAbono1 / max($precioPlan1, 1)));
$cobertura1 = date('Y-m-d', strtotime($fechaBase1 . " + $diasExtra1 days"));

echo "\n  Precio plan real:    $" . number_format($precioPlan1, 2) . "\n";
echo "  Fecha base período: $fechaBase1\n";
echo "  Abono:               $" . number_format($montoAbono1, 2) . "\n";
echo "  Días ganados:        $diasExtra1\n";
echo "  Cobertura hasta:     $cobertura1\n";
echo "  Esperado:            2026-07-16\n";
echo "  " . ($cobertura1 === '2026-07-16' ? '✅ CORRECTO' : '❌ INCORRECTO') . "\n\n";

// ===== CASO 2: Segundo abono contra factura de saldo #10259 =====
echo "--- CASO 2: Pago parcial contra factura de saldo pendiente #10259 ---\n";
$invId2 = 10259;
$montoAbono2 = 5.00; // supongamos $5 de abono
$inv2 = $wispClient->getInvoiceDetail((string)$invId2);
if (!empty($inv2)) {
    echo "Factura #$invId2:\n";
    echo "  fecha_emision:     " . ($inv2['fecha_emision'] ?? 'N/A') . "\n";
    echo "  total:             $" . ($inv2['total'] ?? 'N/A') . "\n";
    $desc2 = $inv2['articulos'][0]['descripcion'] ?? 'N/A';
    echo "  descripcion:       " . trim(preg_replace('/\s+/', ' ', $desc2)) . "\n";
    
    $precioPlan2 = $getTruePlanPrice($wispClient, $invId2, floatval($inv2['total']));
    $fechaBase2 = $getTruePeriodStart($wispClient, $invId2, $inv2['fecha_emision'] ?? date('Y-m-d'));
    $diasExtra2 = round(30 * ($montoAbono2 / max($precioPlan2, 1)));
    $cobertura2 = date('Y-m-d', strtotime($fechaBase2 . " + $diasExtra2 days"));
    
    echo "\n  Precio plan real (recursivo): $" . number_format($precioPlan2, 2) . "\n";
    echo "  Fecha base período (recursivo): $fechaBase2\n";
    echo "  Abono:               $" . number_format($montoAbono2, 2) . "\n";
    echo "  Días ganados:        $diasExtra2\n";
    echo "  Cobertura hasta:     $cobertura2\n";
    echo "  Esperado fechaBase:  2026-07-01 (del período original, NO de fecha_emision)\n";
    echo "  " . ($fechaBase2 === '2026-07-01' ? '✅ Fecha base CORRECTA' : '❌ Fecha base INCORRECTA') . "\n";
} else {
    echo "  Factura #$invId2 no encontrada (posiblemente ya no existe)\n";
}

// ===== TEST de referencia bancaria =====
echo "\n--- TEST: Referencia bancaria (últimos 8 dígitos) ---\n";
$refBanco = '059138998874';
$refCliente = '8998874';

$refBancoClean = preg_replace('/\D/', '', $refBanco);
$ref8banco = substr($refBancoClean, -8);

$ref8cliente = str_pad(substr($refCliente, -8), 8, '0', STR_PAD_LEFT);

echo "  Referencia banco completa: $refBanco\n";
echo "  Últimos 8 del banco:       $ref8banco\n";
echo "  Lo que tecleó el cliente:   $refCliente\n";
echo "  Antes (str_pad cliente):    $ref8cliente\n";
echo "  Ahora (substr banco -8):   $ref8banco\n";
echo "  Esperado:                   38998874\n";
echo "  " . ($ref8banco === '38998874' ? '✅ CORRECTO' : '❌ INCORRECTO') . "\n";
