<?php
/**
 * scratch/test_bdv_api_live.php
 * Prueba de conectividad con la API del Banco de Venezuela en producción.
 * Ejecutar desde el navegador: http://localhost/sistemas-administrativo-tecnico-wireless/scratch/test_bdv_api_live.php
 */
require_once __DIR__ . '/../paginas/principal/bdv_api_helper.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
      <title>Test API BDV</title>
      <link href='../css/bootstrap.min.css' rel='stylesheet'>
      </head><body class='p-4 bg-dark text-white'>";

echo "<h3 class='text-warning mb-4'><i class='fas fa-bolt me-2'></i>Test: API Banco de Venezuela</h3>";
echo "<pre class='bg-secondary p-3 rounded'>";

// ── Test 1: Conectividad básica ──────────────────────────────────────────────
$hoy      = date('Y-m-d');
$hace_7   = date('Y-m-d', strtotime('-7 days'));

echo "=== Test 1: Consulta de movimientos (últimos 7 días) ===\n";
echo "Cuenta  : " . BDV_CUENTA_DEFECTO . "\n";
echo "Desde   : $hace_7\n";
echo "Hasta   : $hoy\n\n";

$resultado = consultar_movimientos_bdv(BDV_CUENTA_DEFECTO, $hace_7, $hoy);

echo "Success : " . ($resultado['success'] ? '✅ SÍ' : '❌ NO') . "\n";
echo "Mensaje : " . $resultado['message'] . "\n";
echo "Movs    : " . count($resultado['movs']) . " movimiento(s)\n\n";

if (!empty($resultado['movs'])) {
    echo "Primeros 3 movimientos:\n";
    $muestra = array_slice($resultado['movs'], 0, 3);
    foreach ($muestra as $i => $m) {
        echo "  [" . ($i+1) . "] Fecha=" . ($m['fecha'] ?? '-') .
             "  Ref=" . ($m['referencia'] ?? '-') .
             "  Tipo=" . ($m['mov'] ?? '-') .
             "  Importe=Bs " . ($m['importe'] ?? '-') . "\n";
    }
}

// ── Test 2: Búsqueda de un movimiento ficticio ───────────────────────────────
echo "\n=== Test 2: buscar_movimiento_bdv (referencia ficticia) ===\n";
$encontrado = buscar_movimiento_bdv($resultado['movs'], '999999', 100.00);
echo "Resultado búsqueda ref '999999' / Bs 100: " . ($encontrado ? '¡Encontrado!' : 'No encontrado (esperado)') . "\n";

// ── Test 3: Respuesta raw de la API ─────────────────────────────────────────
echo "\n=== Test 3: Respuesta RAW de la API ===\n";
if ($resultado['raw']) {
    echo json_encode($resultado['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "(Sin respuesta — error de conexión o credenciales)\n";
}

echo "</pre>";
echo "<a href='../portal/dashboard.php' class='btn btn-secondary mt-3'>← Volver al Portal</a>";
echo "</body></html>";
