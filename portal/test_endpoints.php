<?php
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Test Endpoints WispHub</title>
<style>
  body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;max-width:900px;margin:auto}
  h1{color:#3b82f6;text-align:center}
  table{width:100%;border-collapse:collapse;margin:10px 0;font-family:monospace;font-size:0.85rem}
  th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #334155;vertical-align:top}
  th{background:#1e293b;color:#94a3b8;position:sticky;top:0}
  .ok{color:#10b981;font-weight:700}
  .fail{color:#ef4444;font-weight:700}
  pre{background:#0f172a;border:1px solid #334155;padding:8px;border-radius:4px;overflow-x:auto;font-size:0.78rem;margin:2px 0;max-height:200px;overflow-y:auto}
  .summary{background:#1e293b;border-radius:8px;padding:15px;margin:15px 0;text-align:center}
  details{margin:4px 0}
  summary{cursor:pointer;font-weight:600}
</style>
</head>
<body>
<h1>🔍 Test de Endpoints WispHub (POST)</h1>
<p style="text-align:center;color:#94a3b8">
  Prueba los endpoints de escritura para cada cuenta WispHub · 
  <?php echo date('Y-m-d H:i:s'); ?>
</p>

<?php
@include_once __DIR__ . '/../config/wisphub_credentials.php';

function testEndpoint(string $label, string $baseUrl, string $apiKey, string $method, string $endpoint, array $body = []): array {
    $start = microtime(true);
    $ch = curl_init($baseUrl . '/' . $endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            "Authorization: Api-Key {$apiKey}",
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    $elapsed = round((microtime(true) - $start) * 1000) / 1000;
    return [
        'label' => $label,
        'method' => $method,
        'url' => $url,
        'http' => $http,
        'error' => $error,
        'body' => $resp,
        'elapsed' => $elapsed,
    ];
}

$results = [];
$endpointsToTest = [
    ['GET', 'facturas/999999/registrar-pago/', [], 'Verificar si la ruta existe (debe dar 405 o 404)'],
    ['POST', 'facturas/999999/registrar-pago/', ['forma_pago' => 45181, 'accion' => 1, 'fecha_pago' => date('Y-m-d'), 'referencia' => 'TEST', 'total_cobrado' => 0], 'Probar POST con invoice inexistente (debe dar 400 o 404)'],
    ['POST', 'facturas/', ['tipo_factura' => 1, 'cliente' => 'test', 'fecha_emision' => date('Y-m-d'), 'fecha_pago' => date('Y-m-d'), 'fecha_vencimiento' => date('Y-m-d'), 'articulos' => [['cantidad' => 1, 'descripcion' => 'test', 'precio' => 0]]], 'Probar POST crear factura (debe dar 400 por cliente inválido)'],
];

foreach ($WISPHUB_ACCOUNTS as $ref => $acct) {
    $label = $acct['label'] ?? $ref;
    $baseUrl = $acct['base_url'] ?? 'N/A';
    $apiKey = $acct['api_key'] ?? '';
    $shortKey = substr($apiKey, 0, 8) . '...';
    foreach ($endpointsToTest as $ep) {
        $result = testEndpoint("{$ref}: {$label}", $baseUrl, $apiKey, $ep[0], $ep[1], $ep[2]);
        $result['desc'] = $ep[3];
        $results[] = $result;
    }
}
?>

<table>
<thead><tr><th>Cuenta</th><th>Endpoint</th><th>HTTP</th><th>Tiempo</th><th>Respuesta</th></tr></thead>
<tbody>
<?php foreach ($results as $r):
    $isOk = ($r['http'] === 405 || $r['http'] === 400 || $r['http'] === 422 || $r['http'] === 200 || $r['http'] === 201);
    $status = $r['error'] ? 'fail' : ($isOk ? 'ok' : 'fail');
    $parsed = json_decode($r['body'], true);
    $prettyBody = $parsed ? json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($r['body'] ?: '(vacío)');
    $badgeCode = $r['http'] ?: ($r['error'] ? 'ERR' : '???');
?>
<tr>
  <td><strong><?php echo htmlspecialchars($r['label']); ?></strong><br><small style="color:#64748b"><?php echo htmlspecialchars($r['method']); ?></small></td>
  <td><code><?php echo htmlspecialchars($r['url']); ?></code><br><small style="color:#94a3b8"><?php echo htmlspecialchars($r['desc']); ?></small></td>
  <td class="<?php echo $status; ?>"><?php echo $badgeCode; ?></td>
  <td><?php echo $r['elapsed']; ?>s</td>
  <td>
    <?php if ($r['error']): ?>
      <span class="fail">FALLÓ: <?php echo htmlspecialchars($r['error']); ?></span>
    <?php else: ?>
      <details>
        <summary style="color:#94a3b8"><?php echo htmlspecialchars(substr($prettyBody, 0, 80)) . (strlen($prettyBody) > 80 ? '...' : ''); ?></summary>
        <pre><?php echo htmlspecialchars($prettyBody); ?></pre>
      </details>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="summary">
  <p style="margin:0;color:#94a3b8">
    ✅ 405/400/422 = esperado (endpoint existe, datos inválidos) · 
    ❌ 404 = endpoint NO existe · 
    ❌ ERR = error de conexión
  </p>
</div>

</body>
</html>
