<?php
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Diagnóstico de Conexión</title>
<style>
  body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;max-width:800px;margin:auto}
  h1{color:#3b82f6;text-align:center}
  table{width:100%;border-collapse:collapse;margin:10px 0}
  th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #334155}
  th{background:#1e293b;color:#94a3b8;font-size:0.85rem}
  .ok{color:#10b981;font-weight:700}
  .slow{color:#eab308;font-weight:700}
  .fail{color:#ef4444;font-weight:700}
  .summary{background:#1e293b;border-radius:8px;padding:15px;margin:15px 0}
  button{background:#3b82f6;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:1rem;margin:10px 0}
  button:hover{background:#2563eb}
  pre{background:#0f172a;border:1px solid #334155;padding:10px;border-radius:4px;overflow-x:auto;font-size:0.85rem}
</style>
</head>
<body>
<h1>Diagnostico del Servidor</h1>
<p style="text-align:center;color:#94a3b8">Tiempos en segundos · <?php echo date('Y-m-d H:i:s'); ?></p>

<?php
$results = [];
$totalFail = 0; $totalSlow = 0;

function test(string $label, callable $fn): void {
    global $results, $totalFail, $totalSlow;
    $start = microtime(true);
    try {
        $info = $fn();
        $elapsed = round((microtime(true) - $start) * 1000) / 1000;
        $status = $elapsed > 4 ? 'fail' : ($elapsed > 2 ? 'slow' : 'ok');
        if ($status === 'fail') $totalFail++;
        if ($status === 'slow') $totalSlow++;
        $results[] = ['label' => $label, 'tiempo' => $elapsed, 'status' => $status, 'info' => $info];
    } catch (\Throwable $e) {
        $elapsed = round((microtime(true) - $start) * 1000) / 1000;
        $results[] = ['label' => $label, 'tiempo' => $elapsed, 'status' => 'fail', 'info' => $e->getMessage()];
        $totalFail++;
    }
}

function tcp(string $host, int $port = 443, int $timeout = 5): string {
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $elapsed = round((microtime(true) - $start) * 1000) / 1000;
    if ($fp) { fclose($fp); return "{$elapsed}s";
    } else { return "FALLÓ ({$errstr})"; }
}

function ssl(string $host, int $port = 443, int $timeout = 5): string {
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
    $start = microtime(true);
    $fp = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    $elapsed = round((microtime(true) - $start) * 1000) / 1000;
    if ($fp) { fclose($fp); return "{$elapsed}s";
    } else { return "FALLÓ ({$errstr})"; }
}

// 1. Servidor
test('PHP version', fn() => phpversion());
test('Hostname', fn() => gethostname());
test('IP del servidor', fn() => $_SERVER['SERVER_ADDR'] ?? $_SERVER['SERVER_NAME'] ?? '?');
test('max_execution_time', fn() => ini_get('max_execution_time'));
test('PHP memory_limit', fn() => ini_get('memory_limit'));

// 2. DNS
test('DNS: api.wisphub.net', fn() => gethostbyname('api.wisphub.net'));
test('DNS: app.marateltru.com', fn() => gethostbyname('app.marateltru.com'));
test('DNS: google.com', fn() => gethostbyname('google.com'));

// 3. TCP
test('TCP: api.wisphub.net:443', fn() => tcp('api.wisphub.net'));
test('TCP: app.marateltru.com:443', fn() => tcp('app.marateltru.com'));
test('TCP: google.com:443', fn() => tcp('google.com'));
test('TCP: localhost:80', fn() => tcp('localhost', 80, 2));

// 4. SSL
test('SSL: api.wisphub.net', fn() => ssl('api.wisphub.net'));
test('SSL: app.marateltru.com', fn() => ssl('app.marateltru.com'));
test('SSL: google.com', fn() => ssl('google.com'));

// 5. WispHub API — probar TODAS las cuentas configuradas
@include_once __DIR__ . '/../config/wisphub_credentials.php';
$allAccounts = $WISPHUB_ACCOUNTS ?? [];
if (empty($allAccounts)) {
    $wispConfig = @include __DIR__ . '/../config/wisp_hub.php';
    if ($wispConfig) {
        $allAccounts = ['activa' => ['label' => 'Cuenta activa', 'api_key' => $wispConfig['api_key'], 'base_url' => $wispConfig['base_url'], 'verify_ssl' => $wispConfig['verify_ssl'] ?? false]];
    }
}
if (!empty($allAccounts)) {
    $testedEndpoints = ['clientes/?limit=1', 'clientes/902/perfil/', 'clientes/902/saldo/', 'clientes/902/', 'facturas/?estado=1&limit=1'];
    // Endpoints de escritura (POST) — probamos con GET para verificar que la ruta existe
    $writeEndpoints = ['facturas/999999/registrar-pago/', 'facturas/', 'promesa-pago/', 'clientes/activar/'];
    foreach ($allAccounts as $ref => $acct) {
        $label = $acct['label'] ?? $ref;
        $apiKey = $acct['api_key'] ?? '';
        $baseUrl = $acct['base_url'] ?? 'https://api.wisphub.net/api';
        $verifySsl = $acct['verify_ssl'] ?? false;
        $activeRef = defined('WISP_HUB_ACTIVE_ACCOUNT') ? WISP_HUB_ACTIVE_ACCOUNT : 'sitelco';
        $isActive = ($ref === $activeRef);
        $prefix = $isActive ? '★ ' : '  ';
        $shortKey = substr($apiKey, 0, 10) . '...';

        // GET endpoints
        foreach ($testedEndpoints as $ep) {
            test("WispHub [{$label}] GET /{$ep}", function() use ($baseUrl, $apiKey, $verifySsl, $ep) {
                $ch = curl_init($baseUrl . '/' . $ep);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => $verifySsl,
                    CURLOPT_HTTPHEADER => ["Authorization: Api-Key {$apiKey}"],
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                $detalle = $error ? "FALLÓ: {$error}" : "HTTP {$http} " . strlen($resp) . 'b';
                if (!$error && $http === 200 && strpos($ep, 'perfil') !== false) {
                    $data = json_decode($resp, true);
                    if (isset($data['data']['nombre'])) $detalle .= ' - ' . $data['data']['nombre'];
                }
                if (!$error && $http === 200 && strpos($ep, 'saldo') !== false) {
                    $data = json_decode($resp, true);
                    if (isset($data['data']['saldo'])) $detalle .= ' - Saldo: ' . $data['data']['saldo'];
                }
                return $detalle;
            });
        }

        // POST endpoints — probar con GET para verificar que la ruta existe (debe dar 405)
        foreach ($writeEndpoints as $ep) {
            test("WispHub [{$label}] PATH /{$ep}", function() use ($baseUrl, $apiKey, $verifySsl, $ep) {
                $ch = curl_init($baseUrl . '/' . $ep);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_NOBODY => true,
                    CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => $verifySsl,
                    CURLOPT_HTTPHEADER => ["Authorization: Api-Key {$apiKey}"],
                ]);
                $resp = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                curl_close($ch);
                // 405 = Method Not Allowed (esperado — la ruta existe pero requiere POST)
                // 404 = Not Found (la ruta NO existe en este WispHub)
                // 200 = aceptó GET (ruta funcional)
                if ($error) return "FALLÓ: {$error}";
                if ($http === 405) return "HTTP {$http} (OK — existe, requiere POST)";
                if ($http === 404) return "HTTP {$http} (⚠️ RUTA NO EXISTE en este WispHub)";
                if ($http === 200) return "HTTP {$http} (ruta funcional vía GET)";
                return "HTTP {$http}";
            });
        }
    }
} else {
    $results[] = ['label' => 'WispHub config', 'tiempo' => 0, 'status' => 'fail', 'info' => 'No se encontraron cuentas WispHub configuradas'];
}

// 6. Base de datos MySQL
test('MySQL: conexion', function() {
    require_once __DIR__ . '/referencia_helper.php';
    $pdo = getDb();
    if (!$pdo) return 'sin conexion';
    $stmt = $pdo->query('SELECT 1');
    return 'ok';
});

test('MySQL: SELECT NOW()', function() {
    require_once __DIR__ . '/referencia_helper.php';
    $pdo = getDb();
    if (!$pdo) return 'sin conexion';
    $stmt = $pdo->query('SELECT NOW() as t');
    return $stmt->fetch()['t'];
});

test('MySQL: pagos_registrados COUNT', function() {
    require_once __DIR__ . '/referencia_helper.php';
    $pdo = getDb();
    if (!$pdo) return 'sin conexion';
    $stmt = $pdo->query('SELECT COUNT(*) as c FROM pagos_registrados');
    return $stmt->fetch()['c'] . ' registros';
});

// 7. Archivos
test('File: composer.json', fn() => strlen(file_get_contents(__DIR__ . '/../composer.json')) . ' bytes');
test('File: cache write/delete', function() {
    $f = __DIR__ . '/../cache/_diag_' . time() . '.tmp';
    file_put_contents($f, str_repeat('x', 10000));
    $r = filesize($f) . ' bytes';
    unlink($f);
    return $r;
});

$total = round(array_sum(array_column($results, 'tiempo')), 3);
$max = round(max(array_column($results, 'tiempo')), 3);
?>

<div class="summary">
  <strong>Total pruebas:</strong> <?php echo $total; ?>s &nbsp;|&nbsp;
  <strong>Mas lenta:</strong> <?php echo $max; ?>s &nbsp;|&nbsp;
  <strong style="color:#eab308"><?php echo $totalSlow; ?> lentas</strong> &nbsp;|&nbsp;
  <strong style="color:#ef4444"><?php echo $totalFail; ?> fallos</strong>
</div>

<table>
  <tr><th>#</th><th>Prueba</th><th>Tiempo (s)</th><th>Estado</th><th>Detalle</th></tr>
  <?php foreach ($results as $i => $r): ?>
  <tr>
    <td><?php echo $i + 1; ?></td>
    <td><?php echo htmlspecialchars($r['label']); ?></td>
    <td><?php echo $r['tiempo']; ?></td>
    <td class="<?php echo $r['status']; ?>">
      <?php echo ['ok' => '✅', 'slow' => '⚠️', 'fail' => '❌'][$r['status']] ?? '?'; ?>
    </td>
    <td style="font-size:0.85rem;max-width:300px;word-break:break-all">
      <?php echo htmlspecialchars(substr((string)$r['info'], 0, 150)); ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h3>Interpretacion rapida</h3>
<ul style="color:#94a3b8;font-size:0.9rem">
  <li>⚠️ <strong>DNS</strong> &gt;1s → resolutor de HostGator lento</li>
  <li>⚠️ <strong>TCP/SSL</strong> &gt;2s → latencia de red entre HostGator y el destino</li>
  <li>⚠️ <strong>WispHub</strong> &gt;3s → API de WispHub lenta o saturada</li>
  <li>⚠️ <strong>MySQL</strong> &gt;1s → base de datos remota lenta (HostGator)</li>
  <li>❌ <strong>Fallos</strong> → servicio caido o bloqueado</li>
</ul>

<p style="text-align:center;margin-top:30px">
  <button onclick="location.reload()">Ejecutar de nuevo</button>
</p>
</body>
</html>
