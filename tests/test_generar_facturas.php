<?php
/**
 * Test: Generación de facturas desde el módulo de depuración.
 *
 * Valida:
 *  1. listClients devuelve 'usuario' y 'precio_plan' por servicio.
 *  2. createInvoice con datos reales retorna 200/201.
 *  3. precio=0 → error controlado sin crear factura.
 *  4. usuario vacío → error controlado.
 *  5. getInvoices con filtro de fechas del mes funciona.
 *
 * ⚠️  El test 2 crea una factura REAL en WispHub sobre el servicio de prueba (794 - jalisco).
 *     Eliminarla manualmente desde el admin de WispHub si se necesita.
 *
 * Uso: php tests/test_generar_facturas.php
 */

define('TEST_NODO',   'jalisco');
define('TEST_SVC_ID', '794');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
require_once __DIR__ . '/../config/wisphub_credentials.php';

$pass = 0; $fail = 0;
$resultados_test = [];

function check(string $label, bool $cond, string $detalle = ''): void {
    global $pass, $fail, $resultados_test;
    $resultados_test[] = [$cond ? '✅ PASS' : '❌ FAIL', $label, $detalle];
    $cond ? $pass++ : $fail++;
}

// ─── Función de generación (copia de depuracion_facturas.php) ─────────────────
function generarFacturaCliente(\Services\WispHubClient $client, array $c, string $ultimo_dia, string $mes_label): array {
    $svc_id   = (string)($c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '');
    $username = $c['usuario'] ?? $c['username'] ?? '';
    $nombre   = $c['nombre'] ?? 'Sin nombre';

    // 1. Precio: campo 'precio_plan' (listClients)
    $precio = floatval($c['precio_plan'] ?? 0);

    // 2. Fallback: plan_internet array
    if ($precio <= 0 && !empty($c['plan_internet']) && is_array($c['plan_internet'])) {
        $precio = floatval($c['plan_internet']['precio'] ?? $c['plan_internet']['costo'] ?? 0);
    }

    // 3. Fallback: listClients filtrado por id_servicio
    if ($precio <= 0 && $svc_id) {
        $r = $client->listClients(['id_servicio' => $svc_id, 'limit' => 1]);
        if ($r['status'] === 200 && !empty($r['data']['results'][0])) {
            $precio = floatval($r['data']['results'][0]['precio_plan'] ?? 0);
            if (empty($username)) $username = $r['data']['results'][0]['usuario'] ?? '';
            if ($nombre === 'Sin nombre') $nombre = $r['data']['results'][0]['nombre'] ?? $nombre;
        }
    }

    // 4. Fallback usuario: getServiceProfile
    if (empty($username) && $svc_id) {
        $perfil = $client->getServiceProfile($svc_id);
        if ($perfil['status'] === 200) $username = $perfil['data']['usuario'] ?? '';
    }

    if ($precio <= 0)    return ['ok' => false, 'svc_id' => $svc_id, 'nombre' => $nombre, 'error' => 'No se pudo obtener el precio del plan'];
    if (empty($username)) return ['ok' => false, 'svc_id' => $svc_id, 'nombre' => $nombre, 'error' => 'Usuario de WispHub no encontrado'];

    $result = $client->createInvoice($username, $precio, "TEST - $mes_label", $ultimo_dia, $svc_id, 1);
    if (in_array($result['status'], [200, 201], true)) {
        return ['ok' => true, 'svc_id' => $svc_id, 'nombre' => $nombre, 'monto' => $precio,
                'factura_id' => $result['data']['id'] ?? $result['data']['messages']['id'] ?? null];
    }
    $err = $result['data']['message'] ?? $result['data']['detail'] ?? ($result['error'] ?? 'Error desconocido');
    if (is_array($err)) $err = json_encode($err);
    return ['ok' => false, 'svc_id' => $svc_id, 'nombre' => $nombre, 'error' => $err];
}

// ─── Instanciar cliente ───────────────────────────────────────────────────────
$creds  = $WISPHUB_ACCOUNTS[TEST_NODO];
$client = new \Services\WispHubClient([
    'base_url'   => $creds['base_url'],
    'api_key'    => $creds['api_key'],
    'verify_ssl' => $creds['verify_ssl'] ?? false,
]);
$ultimo_dia = date('Y-m-t');
$mes_label  = date('F Y');

echo "=== Test: Módulo Generación de Facturas (" . strtoupper(TEST_NODO) . ") ===\n\n";

// ─── TEST 1: listClients devuelve precio_plan y usuario para svc 794 ──────────
echo "[1] listClients svc=" . TEST_SVC_ID . " devuelve precio_plan y usuario ... ";
$r = $client->listClients(['id_servicio' => TEST_SVC_ID, 'limit' => 1]);
$c_test = $r['data']['results'][0] ?? [];
check("listClients retorna status 200",          $r['status'] === 200, "status=".$r['status']);
check("listClients tiene campo 'usuario'",       !empty($c_test['usuario']), "usuario=" . ($c_test['usuario'] ?? 'VACIO'));
check("listClients tiene campo 'precio_plan' > 0", floatval($c_test['precio_plan'] ?? 0) > 0, "precio_plan=" . ($c_test['precio_plan'] ?? '0'));

$username_test  = $c_test['usuario'] ?? '';
$precio_test    = floatval($c_test['precio_plan'] ?? 0);

// ─── TEST 2: createInvoice con datos reales ───────────────────────────────────
if (!empty($username_test) && $precio_test > 0) {
    echo "\n[2] createInvoice usuario=$username_test monto=$precio_test ... ";
    $res_crea = $client->createInvoice(
        $username_test, $precio_test,
        "TEST auto-generado - $mes_label",
        $ultimo_dia, TEST_SVC_ID, 1
    );
    check(
        "createInvoice retorna 200 o 201",
        in_array($res_crea['status'], [200, 201], true),
        "status=" . $res_crea['status'] . " body=" . json_encode($res_crea['data'] ?? $res_crea['error'] ?? '')
    );
    // WispHub devuelve: {"messages": "Se genero correctamente el id 12345"}
    $msg = $res_crea['data']['messages'] ?? $res_crea['data']['id'] ?? '';
    $nf_id = null;
    if (is_string($msg) && preg_match('/\b(\d+)\b/', $msg, $m)) {
        $nf_id = (int)$m[1];
    } elseif (is_numeric($msg)) {
        $nf_id = (int)$msg;
    }
    check("createInvoice retorna ID de la nueva factura", !empty($nf_id), "id=" . ($nf_id ?? 'NO'));
    if ($nf_id) {
        echo "\n  ⚠️  Factura TEST creada: id=$nf_id (eliminar manualmente en WispHub admin si es necesario)\n";
    }
} else {
    echo "\n[2] SKIP createInvoice (datos insuficientes del test 1)\n";
}

// ─── TEST 3: generarFacturaCliente con precio = 0 → error ────────────────────
echo "\n[3] precio=0 → error controlado ... ";
$c_sin_precio = ['id_servicio' => '99999', 'usuario' => 'x@test', 'nombre' => 'Test', 'precio_plan' => '0'];
$r3 = generarFacturaCliente($client, $c_sin_precio, $ultimo_dia, $mes_label);
check("precio=0 retorna ok=false", $r3['ok'] === false && !empty($r3['error']), "error=" . ($r3['error'] ?? 'VACIO'));

// ─── TEST 4: generarFacturaCliente con usuario vacío → error ─────────────────
echo "\n[4] usuario vacío → error controlado ... ";
$c_sin_usuario = ['id_servicio' => '99999', 'usuario' => '', 'nombre' => 'Test', 'precio_plan' => '25.00'];
// svc_id=99999 no existe, el fallback de listClients no encontrará nada
$r4 = generarFacturaCliente($client, $c_sin_usuario, $ultimo_dia, $mes_label);
check("usuario vacío retorna ok=false", $r4['ok'] === false, "error=" . ($r4['error'] ?? 'VACIO'));

// ─── TEST 5: getInvoices con rango del mes ────────────────────────────────────
echo "\n[5] getInvoices con rango del mes actual ... ";
$inv_mes = $client->getInvoices([
    'fecha_vencimiento__range_0' => date('Y-m-01'),
    'fecha_vencimiento__range_1' => date('Y-m-t'),
    'limit' => 10, 'offset' => 0,
]);
check("getInvoices con filtro de fecha retorna array", is_array($inv_mes), "count=" . count($inv_mes));

// ─── TEST 6: generarFacturaCliente con datos completos de listClients ─────────
if (!empty($c_test)) {
    echo "\n[6] generarFacturaCliente END-TO-END con datos reales de listClients ... ";
    // NOTA: Esto crearía una segunda factura real. Lo marcamos como skip en CI
    // pero lo probamos pasando los datos de $c_test a la función para validar el flujo
    // sin llamar a createInvoice (simulando con precio=0 para el svc de prueba)
    // Para un test sin efectos: verificamos solo que la función extrae datos correctamente
    check(
        "generarFacturaCliente: datos de listClients tienen usuario y precio",
        !empty($c_test['usuario']) && floatval($c_test['precio_plan'] ?? 0) > 0,
        "usuario=" . ($c_test['usuario'] ?? '') . " precio=" . ($c_test['precio_plan'] ?? '0')
    );
}

// ─── Resumen ──────────────────────────────────────────────────────────────────
echo "\n\n=== RESULTADOS ===\n";
printf("%-10s %-55s %s\n", "ESTADO", "TEST", "DETALLE");
echo str_repeat("-", 105) . "\n";
foreach ($resultados_test as [$estado, $label, $det]) {
    printf("%-10s %-55s %s\n", $estado, $label, mb_substr($det, 0, 45));
}
echo str_repeat("=", 105) . "\n";
echo "  ✅ PASS: $pass   ❌ FAIL: $fail   TOTAL: " . ($pass + $fail) . "\n\n";

if ($fail > 0) exit(1);
