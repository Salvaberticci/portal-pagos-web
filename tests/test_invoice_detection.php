<?php
/**
 * Test: Verificar la lógica de detección de facturas mensuales.
 * Simula el comportamiento de wisp_helper.php para asegurar que 
 * no se generen facturas duplicadas si ya existe una factura 
 * válida emitida a finales del mes anterior, pero que vence 
 * en el mes actual.
 */

require_once __DIR__ . '/../portal/wisp_helper.php';

echo "=== Test de Detección de Facturas Mensuales ===\n\n";

$hoy_str = date('Y-m-d');
$primer_dia_mes = date('Y-m-01');
$ultimo_dia_mes = date('Y-m-t');
$ventana_inicio = $primer_dia_mes;
$ventana_fin = date('Y-m-d', strtotime($ultimo_dia_mes . ' +5 days'));

echo "Ventana de detección (fecha_vencimiento): $ventana_inicio al $ventana_fin\n\n";

// Helper interno (el mismo de wisp_helper.php)
$es_hija = function(array $inv): bool {
    $desc = '';
    foreach ($inv['articulos'] ?? [] as $art) {
        $desc .= ($art['descripcion'] ?? '');
    }
    return stripos($desc, 'Saldo pendiente') !== false
        || stripos($desc, 'Saldo a favor') !== false;
};

// Caso 1: Factura emitida el mes pasado, pero vence este mes. (Caso Yosner)
// DEBE DETECTAR: Sí (tiene_mensualidad = true)
$caso1_invoices = [
    [
        'id' => 1,
        'fecha_emision' => date('Y-m-d', strtotime('-10 days')), // Mes pasado
        'fecha_vencimiento' => date('Y-m-03'), // Vence el 3 de este mes
        'estado' => 'Pendiente de Pago',
        'articulos' => [['descripcion' => 'Renta de Internet']]
    ]
];

// Caso 2: Factura emitida este mes, vence este mes.
// DEBE DETECTAR: Sí
$caso2_invoices = [
    [
        'id' => 2,
        'fecha_emision' => date('Y-m-02'),
        'fecha_vencimiento' => date('Y-m-25'),
        'estado' => 'Pendiente de Pago',
        'articulos' => [['descripcion' => 'Renta de Internet']]
    ]
];

// Caso 3: Solo tiene una factura hija de saldo pendiente (emitida y vence este mes).
// DEBE DETECTAR: No (debe ignorarla)
$caso3_invoices = [
    [
        'id' => 3,
        'fecha_emision' => date('Y-m-02'),
        'fecha_vencimiento' => date('Y-m-15'),
        'estado' => 'Pendiente de Pago',
        'articulos' => [['descripcion' => 'Saldo pendiente tras abono - Factura #123']]
    ]
];

// Caso 4: Factura del mes pasado que ya venció el mes pasado. No tiene de este mes.
// DEBE DETECTAR: No (necesita generar)
$caso4_invoices = [
    [
        'id' => 4,
        'fecha_emision' => date('Y-m-d', strtotime('-40 days')),
        'fecha_vencimiento' => date('Y-m-d', strtotime('-10 days')), // Venció el mes pasado
        'estado' => 'Pagada',
        'articulos' => [['descripcion' => 'Renta de Internet']]
    ]
];

// Función para testear un caso
function probarCaso($nombre, $invoices, $resultadoEsperado, $es_hija, $ventana_inicio, $ventana_fin) {
    $tiene_mensualidad = false;
    foreach ($invoices as $inv) {
        if ($es_hija($inv)) continue;
        $fvenc = substr($inv['fecha_vencimiento'] ?? '', 0, 10);
        if ($fvenc >= $ventana_inicio && $fvenc <= $ventana_fin) {
            $tiene_mensualidad = true;
            break;
        }
    }
    $resultado = $tiene_mensualidad ? "SÍ" : "NO";
    $esperadoStr = $resultadoEsperado ? "SÍ" : "NO";
    $pass = ($tiene_mensualidad === $resultadoEsperado);
    
    echo "[$nombre] Esperado: $esperadoStr | Obtenido: $resultado -> " . ($pass ? "✅ PASS" : "❌ FAIL") . "\n";
}

probarCaso("Caso 1 (Yosner - Emitida mes anterior, vence este mes)", $caso1_invoices, true, $es_hija, $ventana_inicio, $ventana_fin);
probarCaso("Caso 2 (Normal - Emitida este mes, vence este mes)", $caso2_invoices, true, $es_hija, $ventana_inicio, $ventana_fin);
probarCaso("Caso 3 (Ignorar hija - Solo tiene saldo pendiente)", $caso3_invoices, false, $es_hija, $ventana_inicio, $ventana_fin);
probarCaso("Caso 4 (Faltante - Vencimiento fue el mes pasado)", $caso4_invoices, false, $es_hija, $ventana_inicio, $ventana_fin);

echo "\nTest finalizado.\n";
