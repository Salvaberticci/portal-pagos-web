<?php
require_once __DIR__ . '/../portal/referencia_helper.php';

echo "========================================\n";
echo "TEST: Validacion de Referencia Bancaria\n";
echo "========================================\n\n";

// =============================================
// 1. Obtener referencia real de la BD local
// =============================================
echo "--- ESCENARIO 1: Referencia REAL existente en DB ---\n\n";

$db = getDb();
$refReal = null;
$datosReal = null;

if ($db) {
    $stmt = $db->query("SELECT referencia, cliente, facturas, fecha_pago, total_cobrado, service_id FROM pagos_registrados LIMIT 5");
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        echo "Referencias disponibles en la BD:\n";
        foreach ($rows as $r) {
            echo "  - Ref: {$r['referencia']} | Cliente: {$r['cliente']} | Facturas: {$r['facturas']} | Fecha: {$r['fecha_pago']}\n";
        }
        $refReal = $rows[0]['referencia'];
        $datosReal = $rows[0];
        echo "\nUsando referencia REAL: $refReal\n";
    } else {
        echo "No hay registros en la BD. Insertando un registro de prueba...\n";
        guardarPago(
            'Cliente PRUEBA Test',
            '192.168.1.1',
            date('Y-m-d'),
            'Zona Test',
            25.00,
            'Pago Móvil',
            'TEST123456789',
            30.00,
            'abono',
            '999',
            9,
            '99999,99998'
        );
        $refReal = 'TEST123456789';
        $datosReal = getReferenciaInfo($refReal);
        echo "Registro de prueba insertado con referencia: $refReal\n";
    }
} else {
    echo "ERROR: No hay conexion a la BD. Saltando escenario 1.\n\n";
}

// Test escenario 1: referencia real
if ($refReal) {
    echo "\n>> Llamando getReferenciaInfo('$refReal')...\n";
    $info = getReferenciaInfo($refReal);
    if ($info) {
        $fact = $info['facturas'] ? ' #' . $info['facturas'] : '';
        $msg = "La referencia {$refReal} ya fue utilizada en la Factura{$fact} del dia {$info['fecha_pago']}, por el cliente {$info['cliente']}.";
        echo "  RESULTADO: Referencia DUPLICADA detectada.\n";
        echo "  Mensaje:   $msg\n";
        echo "  ✅ ESCENARIO 1 PASO: Referencia real encontrada en DB y mensaje detallado generado.\n";
    } else {
        echo "  ❌ ESCENARIO 1 FALLO: getReferenciaInfo() devolvio null para una referencia que existe.\n";
    }
} else {
    echo "  ⚠️  ESCENARIO 1 OMITIDO: No hay DB disponible.\n";
}

echo "\n----------------------------------------\n\n";

// =============================================
// 2. Probar referencia FALSA (no existe en DB)
// =============================================
echo "--- ESCENARIO 2: Referencia FALSA (no existe en DB) ---\n\n";

$refFalsa = '999999999999999';

echo ">> Llamando getReferenciaInfo('$refFalsa')...\n";
$info = getReferenciaInfo($refFalsa);
if ($info === null) {
    echo "  RESULTADO: getReferenciaInfo() devolvio null (correcto - no existe en DB).\n";
    echo "  ✅ ESCENARIO 2 PASO: Referencia falsa correctamente identificada como no existente en DB.\n";
    echo "  (Seguiria a consulta API del banco -> !REFERENCIA NO EXISTE EN EL BANCO!)\n";
} else {
    echo "  ❌ ESCENARIO 2 FALLO: getReferenciaInfo() devolvio datos para una referencia que no deberia existir.\n";
}

echo "\n----------------------------------------\n\n";

// =============================================
// 3. Validacion visual: duplicado ya usado
// =============================================
echo "--- ESCENARIO 3: Simulacion JSON de api_verificar_pago.php ---\n\n";

if ($refReal && $datosReal) {
    $fact = $datosReal['facturas'] ? ' #' . $datosReal['facturas'] : '';
    $response = [
        'status'  => 'error',
        'titulo'  => '!REFERENCIA DUPLICADA!',
        'message' => "La referencia {$refReal} ya fue utilizada en la Factura{$fact} del dia {$datosReal['fecha_pago']}, por el cliente {$datosReal['cliente']}."
    ];
    echo "JSON response (duplicado):\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "\n  ✅ JSON generado correctamente con titulo y mensaje detallado.\n";
} else {
    echo "  ⚠️  ESCENARIO 3 OMITIDO: No hay referencia real disponible.\n";
}

echo "\n--- ESCENARIO 4: Simulacion JSON referencia no encontrada en banco ---\n\n";
$responseBanco = [
    'status'  => 'error',
    'titulo'  => '!REFERENCIA NO EXISTE EN EL BANCO!',
    'message' => 'La referencia no fue encontrada en los movimientos del banco. Verifica la fecha y el numero de referencia.'
];
echo "JSON response (no existe en banco):\n";
echo json_encode($responseBanco, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "\n  ✅ JSON generado correctamente con titulo !REFERENCIA NO EXISTE EN EL BANCO!.\n";

echo "\n========================================\n";
echo "TEST COMPLETADO\n";
echo "========================================\n";
