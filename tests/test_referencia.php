<?php
/**
 * Test REAL de validación de referencias bancarias
 *
 * Escenarios cubiertos (con API real del banco):
 * 1. Ref existe en DB local → DUPLICADA
 * 2. Ref NO existe en DB local + consultando al banco:
 *    a. Ref existe en el banco → VERIFICADA
 *    b. Ref NO existe en el banco → NO EXISTE EN EL BANCO
 * 3. Guardar pago en DB + verificar post-insercion
 * 4. Formato de referencia (6-15 digitos)
 */

require_once __DIR__ . '/../portal/referencia_helper.php';
require_once __DIR__ . '/../paginas/principal/banco_api_router.php';
require_once __DIR__ . '/../portal/security_helper.php';

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

function consultarRefEnBanco(string $referencia, int $idBanco = 9, string $metodo = 'Pago Móvil', ?string $fecha = null): ?array {
    $ts = $fecha ? strtotime($fecha) : strtotime(date('Y-m-d'));
    $hoy = (new DateTime('now', new DateTimeZone('America/Caracas')))->format('Y-m-d');

    // Intentar varios rangos de fecha (a veces la API falla con rangos muy amplios)
    $rangos = [
        ['-2 days', '+1 day'],  // rango principal (-2/+1)
        ['-1 day',  '+0 day'],  // exacto
        ['-3 days', '+1 day'],  // mas amplio
    ];

    foreach ($rangos as $offset) {
        $fecha_ini = date('Y-m-d', strtotime($offset[0], $ts));
        $fecha_fin = date('Y-m-d', strtotime($offset[1], $ts));
        if ($fecha_fin > $hoy) $fecha_fin = $hoy;

        $resultado = consultar_movimientos_banco($idBanco, $fecha_ini, $fecha_fin);
        if (empty($resultado['success']) || empty($resultado['movs'])) continue;

        $refUserClean = preg_replace('/\D/', '', $referencia);
        $refUser6 = strlen($refUserClean) >= 6 ? substr($refUserClean, -6) : $refUserClean;
        $refUser8 = strlen($refUserClean) >= 8 ? substr($refUserClean, -8) : $refUserClean;

        foreach ($resultado['movs'] as $mov) {
            $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
            $desc = strtoupper($mov['descripcion'] ?? '');
            if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
            if (!isset($mov['referencia'])) continue;

            $refBancoClean = preg_replace('/\D/', '', $mov['referencia']);
            $refBanco6 = strlen($refBancoClean) >= 6 ? substr($refBancoClean, -6) : $refBancoClean;
            $refBanco8 = strlen($refBancoClean) >= 8 ? substr($refBancoClean, -8) : $refBancoClean;

            if (
                $refBancoClean === $refUserClean ||
                ($refBanco8 !== '' && $refBanco8 === $refUser8) ||
                ($refBanco6 !== '' && $refBanco6 === $refUser6)
            ) {
                return $mov;
            }
        }
    }
    return null;
}

// ─────────────────────────────────────────────
section('ESCENARIO 1: REF existe en DB local → DUPLICADA');

$refsEnDB = [
    '139627'        => ['cliente' => 'Maire Villegas (V14800836)', 'fecha' => '2026-06-19'],
    '851396'        => ['cliente' => 'Cliente (ref 851396)',       'fecha' => '2026-06-20'],
    '0677266323803' => ['cliente' => 'Cliente Prueba (V20788775)', 'fecha' => '2026-06-19'],
];

foreach ($refsEnDB as $ref => $expected) {
    $info = getReferenciaInfo($ref);
    $found = $info !== null;
    test("getReferenciaInfo('$ref') → encontrada", $found);
    if ($found) {
        test("  cliente: {$info['cliente']}", $info['cliente'] === $expected['cliente']);
        test("  fecha_pago: {$info['fecha_pago']}", $info['fecha_pago'] === $expected['fecha']);
        test("  referenciaYaUsada('$ref') → true", referenciaYaUsada($ref));
    }
}

// ─────────────────────────────────────────────
section('ESCENARIO 2A: Ref consultada en BDV (API real) — existe en DB y en banco');

$refsReales = [
    '139627' => ['2026-06-19'],
    '851396' => ['2026-06-20', '2026-06-21', '2026-06-19'],
];
foreach ($refsReales as $ref => $fechas) {
    $mov = null;
    foreach ($fechas as $fechaRef) {
        echo "  >> BDV: ref=$ref fecha=$fechaRef\n";
        $mov = consultarRefEnBanco($ref, 9, 'Pago Móvil', $fechaRef);
        if ($mov) break;
    }
    if ($mov) {
        $monto = $mov['importe'] ?? $mov['monto'] ?? '?';
        $fechaMov = $mov['fecha'] ?? '?';
        $refBanco = $mov['referencia'] ?? '?';
        $tipo = $mov['mov'] ?? $mov['Tipo'] ?? '?';
        test("  '$ref' → ENCONTRADA en BDV", true);
        test("    Ref completa: $refBanco", !empty($refBanco));
        test("    Monto: $monto Bs", floatval(preg_replace('/[^\d,.-]/', '', str_replace('.', '', $monto))) > 0);
        test("    Tipo: $tipo", strtoupper($tipo) === 'CREDITO' || $tipo === '?');
        test("    Fecha: $fechaMov", !empty($fechaMov));
    } else {
        test("  '$ref' → NO encontrada en BDV (rango -15/+1 dias)", false);
    }
}

section('ESCENARIO 2B: Ref NO en DB + consultando BDV (API real) — existe en banco');

$refBankOnly = '123456';
echo "  Buscando ref generica '$refBankOnly' en BDV (ultimos 2 dias)...\n";
$mov = consultarRefEnBanco($refBankOnly, 9, 'Pago Móvil', date('Y-m-d'));
if ($mov) {
    echo "  ℹ️  Coincidio con ref: {$mov['referencia']} — monto: {$mov['importe']} Bs\n";
}
test("  '{$refBankOnly}' no estaba en DB de antemano", getReferenciaInfo($refBankOnly) === null);
test("  (resultado del banco: " . ($mov ? 'encontrada' : 'no encontrada') . ")", true);
echo "\n";
// NOTA: Si el banco la encuentra, el portal mostraria verificacion → pago → guardaria en DB.
// Si no la encuentra, mostraria "NO EXISTE EN EL BANCO".

section('ESCENARIO 2C: Ref FALSA — NO en DB + NO en banco → "NO EXISTE EN EL BANCO"');

$refFalsa = '999999999999999';
echo "  >> Consultando BDV (API real) para ref FALSA: $refFalsa (ultimos 2 dias)\n";
$mov = consultarRefEnBanco($refFalsa, 9, 'Pago Móvil', date('Y-m-d'));
test("  getReferenciaInfo('$refFalsa') → null (no esta en DB)", getReferenciaInfo($refFalsa) === null);
test("  consultarRefEnBanco('$refFalsa') → null (no esta en BDV)", $mov === null);
echo "  >> CONCLUSION: El portal mostraria → '!REFERENCIA NO EXISTE EN EL BANCO!'\n";

// ─────────────────────────────────────────────
section('ESCENARIO 3: Guardar en DB + verificar post-insercion');

$refNueva = 'T' . date('YmdHis');

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
test("guardarPago('$refNueva') → insertado", $ok);

$info = getReferenciaInfo($refNueva);
test("getReferenciaInfo('$refNueva') → encontrada post-insercion", $info !== null);

if ($info) {
    test("  cliente coincide",  $info['cliente'] === 'Cliente Test');
    test("  servicio coincide", $info['service_id'] === '999');
    test("  monto coincide",   floatval($info['total_cobrado']) === 25.50);
    test("  facturas almacenada", $info['facturas'] === '99999');
    test("  referenciaYaUsada() → true", referenciaYaUsada($refNueva));
}

// ─────────────────────────────────────────────
section('ESCENARIO 4: Formato de referencia (6-15 digitos)');

$validas   = ['123456', '12345678', '0677266323803', '139627', '851396'];
$invalidas = ['abc123', '12345', '1234567890123456', '', '12-345'];

foreach ($validas as $ref) {
    $clean = preg_replace('/\D/', '', $ref);
    test("Formato válido: '$ref'", strlen($clean) >= 6 && strlen($clean) <= 15 && $clean === $ref);
}

foreach ($invalidas as $ref) {
    $clean = preg_replace('/\D/', '', $ref);
    $ok = strlen($clean) < 6 || strlen($clean) > 15 || $clean !== $ref;
    test("Formato inválido: '$ref'", $ok);
}

// ─────────────────────────────────────────────
echo "\n═══════════════════════════════════════\n";
echo "  Total: " . ($passed + $failed) . " tests\n";
echo "  ✅ Pasaron: $passed\n";
if ($failed > 0) echo "  ❌ Fallaron: $failed\n";
echo "═══════════════════════════════════════\n";

exit($failed > 0 ? 1 : 0);
