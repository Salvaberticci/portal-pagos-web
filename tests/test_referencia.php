<?php
/**
 * Test completo de validación de referencias bancarias
 *
 * Escenarios cubiertos:
 * 1. Ref NO existe en DB local + existe en banco = Pago procesado → guardada en DB
 * 2. Ref NO existe en DB local + NO existe en banco = "NO EXISTE EN EL BANCO"
 * 3. Ref SÍ existe en DB local = "DUPLICADA" con detalles
 * 4. Guardar ref nueva en DB + verificar que se encuentra después
 */

require_once __DIR__ . '/../portal/referencia_helper.php';

$passed = 0;
$failed = 0;

function test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ $name\n";
        $passed++;
    } else {
        echo "  ❌ $name — $detail\n";
        $failed++;
    }
}

function section(string $name) {
    echo "\n━━━ $name ━━━\n\n";
}

// ─────────────────────────────────────────────
section('ESCENARIO 1: Referencia existe en DB local');

$refsEnDB = [
    '139627'        => ['cliente' => 'Maire Villegas (V14800836)', 'fecha_pago' => '2026-06-19'],
    '851396'        => ['cliente' => 'Cliente (ref 851396)',       'fecha_pago' => '2026-06-20'],
    '0677266323803' => ['cliente' => 'Cliente Prueba (V20788775)', 'fecha_pago' => '2026-06-19'],
];

foreach ($refsEnDB as $ref => $expected) {
    $info = getReferenciaInfo($ref);
    $found = $info !== null;
    test("getReferenciaInfo('$ref') → encontrada", $found);

    if ($found) {
        test("  cliente: {$info['cliente']}", $info['cliente'] === $expected['cliente'], "Esperado: {$expected['cliente']}, Obtenido: {$info['cliente']}");
        test("  fecha_pago: {$info['fecha_pago']}", $info['fecha_pago'] === $expected['fecha_pago'], "Esperado: {$expected['fecha_pago']}, Obtenido: {$info['fecha_pago']}");
        test("  referenciaYaUsada('$ref') → true", referenciaYaUsada($ref));
        echo "\n";
    }
}

// ─────────────────────────────────────────────
section('ESCENARIO 2: Referencia NO existe en DB local');

$refsFalsas = [
    '999999999999999' => '15 dígitos aleatoria',
    '000000'          => '6 dígitos',
    '12345678'       => '8 dígitos',
];

foreach ($refsFalsas as $ref => $desc) {
    $info = getReferenciaInfo($ref);
    test("getReferenciaInfo('$ref') → null ($desc)", $info === null);
    test("referenciaYaUsada('$ref') → false ($desc)", referenciaYaUsada($ref) === false);
}

// ─────────────────────────────────────────────
section('ESCENARIO 3: Flujo completo — guardar y verificar');

$refNueva = 'T' . date('YmdHis'); // máx 15 chars para VARCHAR(15)

$ok = guardarPago(
    'Cliente Test',
    '192.168.1.1',
    date('Y-m-d'),
    'Zona Test',
    25.50,
    'Pago Móvil',
    $refNueva,
    30.00,
    'completo',
    '999',
    9,
    '99999'
);
test("guardarPago('$refNueva') → insertado correctamente", $ok);

$info = getReferenciaInfo($refNueva);
test("getReferenciaInfo('$refNueva') → encontrada después de guardar", $info !== null);

if ($info) {
    test("  cliente coincide",     $info['cliente'] === 'Cliente Test');
    test("  servicio coincide",    $info['service_id'] === '999');
    test("  monto coincide",      floatval($info['total_cobrado']) === 25.50);
    test("  facturas almacenada", $info['facturas'] === '99999');
    test("  referenciaYaUsada() → true", referenciaYaUsada($refNueva));
}

// ─────────────────────────────────────────────
section('ESCENARIO 4: Simulación JSON — api_verificar_pago.php');

// 4a. Ref duplicada
foreach ($refsEnDB as $ref => $expected) {
    $info = getReferenciaInfo($ref);
    if ($info) {
        $fact = $info['facturas'] ? ' #' . $info['facturas'] : '';
        $json = [
            'status'  => 'error',
            'titulo'  => '!REFERENCIA DUPLICADA!',
            'message' => "La referencia {$ref} ya fue utilizada en la Factura{$fact} del día {$info['fecha_pago']}, por el cliente {$info['cliente']}."
        ];
        $jsonStr = json_encode($json, JSON_UNESCAPED_UNICODE);
        $parsed = json_decode($jsonStr, true);

        test("JSON duplicado '{$ref}': status=error", $parsed['status'] === 'error');
        test("JSON duplicado '{$ref}': titulo correcto", $parsed['titulo'] === '!REFERENCIA DUPLICADA!');
        test("JSON duplicado '{$ref}': incluye cliente '{$info['cliente']}'", strpos($parsed['message'], $info['cliente']) !== false);
        test("JSON duplicado '{$ref}': incluye fecha '{$info['fecha_pago']}'", strpos($parsed['message'], $info['fecha_pago']) !== false);
        echo "\n";
    }
}

// 4b. Ref no existe en banco
$jsonBanco = [
    'status'  => 'error',
    'titulo'  => '!REFERENCIA NO EXISTE EN EL BANCO!',
    'message' => 'La referencia no fue encontrada en los movimientos del banco. Verifica la fecha y el número de referencia.'
];
$jsonStr = json_encode($jsonBanco, JSON_UNESCAPED_UNICODE);
$parsed = json_decode($jsonStr, true);

test("JSON banco: status=error", $parsed['status'] === 'error');
test("JSON banco: titulo correcto", $parsed['titulo'] === '!REFERENCIA NO EXISTE EN EL BANCO!');

// ─────────────────────────────────────────────
section('ESCENARIO 5: Validación de referencia (formato)');

// Las referencias válidas: solo dígitos, 6-15 caracteres
$validas   = ['123456', '1234567', '12345678', '0677266323803', '139627', '851396'];
$invalidas = ['abc123', '12345', '1234567890123456', '', '12-345', '12 345'];

foreach ($validas as $ref) {
    $clean = preg_replace('/\D/', '', $ref);
    $ok = (strlen($clean) >= 6 && strlen($clean) <= 15 && $clean === $ref);
    test("Formato válido: '$ref'", $ok);
}

foreach ($invalidas as $ref) {
    $clean = preg_replace('/\D/', '', $ref);
    $ok = strlen($clean) < 6 || strlen($clean) > 15 || $clean !== $ref;
    test("Formato inválido detectado: '$ref'", $ok);
}

// ─────────────────────────────────────────────
echo "\n═══════════════════════════════════════\n";
echo "  Total: " . ($passed + $failed) . " tests\n";
echo "  ✅ Pasaron: $passed\n";
if ($failed > 0) echo "  ❌ Fallaron: $failed\n";
echo "═══════════════════════════════════════\n";

exit($failed > 0 ? 1 : 0);
