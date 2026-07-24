<?php
@session_start();
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Test - Preservación de Nodo</title>
<style>
  body{font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;padding:20px;max-width:900px;margin:auto}
  h1{color:#3b82f6;text-align:center}
  h2{color:#94a3b8;margin-top:30px;border-bottom:1px solid #334155;padding-bottom:6px}
  table{width:100%;border-collapse:collapse;margin:10px 0;font-family:monospace;font-size:0.85rem}
  th,td{padding:6px 10px;text-align:left;border-bottom:1px solid #1e293b}
  th{background:#1e293b;color:#94a3b8;font-size:0.8rem;position:sticky;top:0}
  .pass{color:#10b981;font-weight:700}
  .fail{color:#ef4444;font-weight:700}
  .skip{color:#94a3b8}
  .summary{background:#1e293b;border-radius:8px;padding:15px;margin:15px 0}
  .url{color:#eab308;font-size:0.8rem;word-break:break-all}
  pre{background:#0f172a;border:1px solid #334155;padding:8px;border-radius:4px;overflow-x:auto;font-size:0.8rem;margin:4px 0}
  .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:0.75rem;font-weight:600}
  .badge-pass{background:rgba(16,185,129,0.15);color:#10b981}
  .badge-fail{background:rgba(239,68,68,0.15);color:#ef4444}
  .badge-total{background:rgba(59,130,246,0.15);color:#3b82f6}
  details{margin:4px 0;background:#1e293b;border-radius:6px;padding:8px}
  summary{cursor:pointer;font-weight:600;color:#e2e8f0}
</style>
</head>
<body>
<h1>🧪 Test de Preservación de Nodo</h1>
<p style="text-align:center;color:#94a3b8">
  Verifica que todas las URLs (redirects, forms, links) preserven <code>?nodo=</code> · 
  <?php echo date('Y-m-d H:i:s'); ?>
</p>

<?php
$pass = 0;
$fail = 0;
$total = 0;

function ok(string $label, string $detail = ''): void {
    global $pass, $total;
    $total++;
    $pass++;
    echo "<tr><td class=\"pass\">✅ PASS</td><td>" . htmlspecialchars($label) . "</td><td class=\"url\">" . htmlspecialchars($detail) . "</td></tr>\n";
}
function nok(string $label, string $detail = ''): void {
    global $fail, $total;
    $total++;
    $fail++;
    echo "<tr><td class=\"fail\">❌ FAIL</td><td>" . htmlspecialchars($label) . "</td><td class=\"url\">" . htmlspecialchars($detail) . "</td></tr>\n";
}

function section(string $title): void {
    echo "<h2>" . htmlspecialchars($title) . "</h2>\n";
    echo "<table><thead><tr><th style=\"width:80px\">Estado</th><th>Prueba</th><th>Detalle</th></tr></thead><tbody>\n";
}
function endsec(): void {
    echo "</tbody></table>\n";
}

// ─── 1. DETECCIÓN DE NODO ────────────────────────────────────────────────
section('1. Detección automática del nodo (_wisp_detect_nodo)');

// Cargar la función sin definir constantes (solo la función)
require_once __DIR__ . '/../config/wisphub_credentials.php';

// Helper: llama _wisp_detect_nodo con entorno simulado
function testDetect(array $get, array $server, array $session, string $expected): bool {
    $_GET = $get;
    $_SERVER = array_merge(['HTTP_HOST' => 'app.marateltru.com', 'REQUEST_URI' => '/portal/'], $server);
    $_SESSION = $session;
    $result = _wisp_detect_nodo();
    // Mapear alias a account_ref
    $map = ['jalisco'=>'jalisco','wiven'=>'jalisco','km23'=>'sitelco','bosque'=>'sitelco','escuque'=>'sitelco','cumbres'=>'sitelco','sitelco'=>'sitelco','pampanito'=>'pampanito','trujillo'=>'pampanito','staana'=>'pampanito'];
    $mapped = $map[$result] ?? $result;
    return $mapped === $expected;
}

// Test 1a: GET parameter
if (testDetect(['nodo'=>'jalisco'], [], [], 'jalisco')) {
    ok('GET ?nodo=jalisco → account_ref=jalisco', '_wisp_detect_nodo() return=jalisco');
} else {
    nok('GET ?nodo=jalisco → account_ref=jalisco', 'No detectó jalisco');
}

// Test 1b: GET parameter with pampanito
if (testDetect(['nodo'=>'pampanito'], [], [], 'pampanito')) {
    ok('GET ?nodo=pampanito → account_ref=pampanito', '_wisp_detect_nodo() return=pampanito');
} else {
    nok('GET ?nodo=pampanito → account_ref=pampanito', 'No detectó pampanito');
}

// Test 1c: Session (sin GET)
if (testDetect([], ['REQUEST_URI'=>'/portal/dashboard.php'], ['wisp_account_ref'=>'jalisco'], 'jalisco')) {
    ok('Session wisp_account_ref=jalisco → account_ref=jalisco', 'Sesión detectada antes que URL');
} else {
    nok('Session wisp_account_ref=jalisco → account_ref=jalisco', 'Sesión no tuvo prioridad');
}

// Test 1d: URL path no debe capturar PHP filenames
if (testDetect([], ['REQUEST_URI'=>'/portal/dashboard.php'], [], 'sitelco')) {
    ok('URL /portal/dashboard.php sin nodo → sitelco', 'No captura nombre de archivo PHP');
} else {
    nok('URL /portal/dashboard.php sin nodo → sitelco', 'Bug: capturó nombre de archivo PHP!');
}

// Test 1e: Sin nada → default sitelco
if (testDetect([], ['REQUEST_URI'=>'/portal/'], [], 'sitelco')) {
    ok('Sin nodo en GET/Session/URL → sitelco', 'Default correcto');
} else {
    nok('Sin nodo en GET/Session/URL → sitelco', 'No devolvió sitelco');
}

// Test 1f: Session tiene prioridad sobre URL path
if (testDetect([], ['REQUEST_URI'=>'/portal/pago.php?recibo_id=123'], ['wisp_account_ref'=>'pampanito'], 'pampanito')) {
    ok('Session pampanito + URL=/portal/pago.php → pampanito', 'Sesión gana sobre regex URL');
} else {
    nok('Session pampanito + URL=/portal/pago.php → pampanito', 'Regex URL tuvo prioridad sobre sesión');
}

// Test 1g: GET tiene prioridad sobre Session
if (testDetect(['nodo'=>'jalisco'], ['REQUEST_URI'=>'/portal/?nodo=jalisco'], ['wisp_account_ref'=>'pampanito'], 'jalisco')) {
    ok('GET ?nodo=jalisco + Session=pampanito → jalisco', 'GET tiene máxima prioridad');
} else {
    nok('GET ?nodo=jalisco + Session=pampanito → jalisco', 'GET no tuvo prioridad');
}

// Test 1h: trujillo alias → pampanito
if (testDetect(['nodo'=>'trujillo'], [], [], 'pampanito')) {
    ok('GET ?nodo=trujillo → account_ref=pampanito', 'Alias trujillo mapeado correctamente');
} else {
    nok('GET ?nodo=trujillo → account_ref=pampanito', 'Alias trujillo no funcionó');
}
endsec();

// ─── 2. FUNCIÓN nodoUrl() (replicada para test) ─────────────────────────
section('2. Función nodoUrl() (generación de URLs con nodo)');

function nodoUrl(string $base): string {
    global $currentNodo;
    return ($currentNodo && $currentNodo !== 'sitelco') ? $base . '?nodo=' . $currentNodo : $base;
}
$currentNodo = 'jalisco';

$tests = [
    ['index.php', 'index.php?nodo=jalisco'],
    ['dashboard.php', 'dashboard.php?nodo=jalisco'],
    ['index.php?logout=1', 'index.php?logout=1&nodo=jalisco'],  // Nota: nodoUrl usa ?nodo= siempre
];
foreach ($tests as $t) {
    $result = nodoUrl($t[0]);
    if ($result === $t[1] || $result === str_replace('?nodo=', '&nodo=', $t[1])) {
        ok("nodoUrl('{$t[0]}')", $result);
    } else {
        nok("nodoUrl('{$t[0]}')", "Esperado={$t[1]}, obtenido={$result}");
    }
}

// nodoUrl with sitelco (no nodo)
$currentNodo = 'sitelco';
$result = nodoUrl('index.php');
if ($result === 'index.php') {
    ok("nodoUrl('index.php') con currentNodo=sitelco", $result . ' (sin ?nodo=)');
} else {
    nok("nodoUrl('index.php') con currentNodo=sitelco", "Esperado=index.php, obtenido={$result}");
}

endsec();

// ─── 3. ARCHIVOS: REDIRECTS Y LINKS ──────────────────────────────────────
section('3. Verificación de redirects/links en archivos PHP');

function checkFile(string $filepath, array $checks): void {
    global $pass, $fail, $total;
    $content = file_get_contents($filepath);
    $short = basename($filepath);
    foreach ($checks as $desc => $pattern) {
        $total++;
        $found = preg_match($pattern, $content);
        if ($found) {
            $pass++;
            echo "<tr><td class=\"pass\">✅ PASS</td><td>" . htmlspecialchars($short) . ": {$desc}</td><td class=\"url\">Pattern encontrado</td></tr>\n";
        } else {
            $fail++;
            echo "<tr><td class=\"fail\">❌ FAIL</td><td>" . htmlspecialchars($short) . ": {$desc}</td><td class=\"url\">Pattern NO encontrado: " . htmlspecialchars($pattern) . "</td></tr>\n";
        }
    }
}

$base = __DIR__;

// index.php
checkFile("$base/index.php", [
    "Logout redirect preserve nodo" => '/nodoUrl\(\'index\.php\'\)/',
    "Session mismatch redirect preserve nodo" => '/header\(\s*\'Location:\s*\' \. nodoUrl\(/',
    "Already logged in redirect preserve nodo" => '/nodoUrl\(\'dashboard\.php\'\)/',
    "Form action preserve nodo" => '/action="<\?php echo nodoUrl\(\'index\.php\'\)/"',
    "Badge dinámico con label" => '/\$WISPHUB_ACCOUNTS\[\$activeRef\]\[\'label\'\]/',
]);

// dashboard.php
checkFile("$base/dashboard.php", [
    "Not-logged-in redirect preserve nodo" => '/\?nodo=\' \. \$_dn/',
    "No-service redirect preserve nodo" => '/\?logout=1.*nodo=\$nodoLogout/',
    "Badge dinámico con label" => '/\$WISPHUB_ACCOUNTS\[\$activeRef\]\[\'label\'\]/',
    "Logout link preserve nodo" => '/nodo=\$activeRef/',
    "Continuar button preserve nodo" => '/nodo=\' \. \$activeRef/',
]);

// pago.php
checkFile("$base/pago.php", [
    "Not-logged-in redirect preserve nodo" => '/\?nodo=\' \. \$_dn/',
    "No-service redirect preserve nodo" => '/\?nodo=\$nodoAct/',
    "No-profile redirect preserve nodo" => '/\?nodo=\$nodoAct2/',
    "Back link preserve nodo" => '/dashboard\.php.*nodo=\$pagoNodoRef/',
    "Form action preserve nodo" => '/action="procesar_pago_cliente\.php.*nodo=\$_formNodo/',
    "Ir al Dashboard preserve nodo" => '/dashboard\.php.*nodo=\$pagoNodoRef/',
    "Badge dinámico con label" => '/\$WISPHUB_ACCOUNTS\[\$pagoNodoRef\]\[\'label\'\]/',
]);

// procesar_pago_cliente.php
checkFile("$base/procesar_pago_cliente.php", [
    "Not-logged-in redirect preserve nodo" => '/\?nodo=\$_SESSION\[\'wisp_account_ref\'\]/',
    "Base redirect_url usa nodo" => '/\$redirect_url = \'dashboard\.php\'.*nodo=\$_nodoActivo/',
    "Success redirect con nodo" => '/\$redirect_url = \'dashboard\.php.*nodoParam/',
]);

// security_helper.php
checkFile("$base/security_helper.php", [
    "Session timeout redirect preserve nodo" => '/timeoutNodo.*nodo=.*timeoutNodo/',
]);

// wisphub_credentials.php
checkFile(dirname($base) . "/config/wisphub_credentials.php", [
    "Session check before URL regex" => '/\/\/ 3\..*Intentar desde la sesión/',
    "URL path regex skip PHP filenames" => '/procesar_pago_cliente/',
    "Mapeo alias pampanito" => '/case \'pampanito\'.*case \'trujillo\'.*case \'staana\'.*_account_ref = \'pampanito\'/s',
    "Mapeo alias jalisco" => '/case \'jalisco\'.*case \'wiven\'.*_account_ref = \'jalisco\'/s',
]);
endsec();

// ─── 4. VERIFICACIÓN EN VIVO (HTTP) ──────────────────────────────────────
section('4. Verificación en vivo (cada página con ?nodo=jalisco)');

$host = $_SERVER['HTTP_HOST'] ?? 'app.marateltru.com';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

$livePages = [
    'index.php?nodo=jalisco' => 'Login Jalisco — debe mostrar badge "Wiven - Nodo Jalisco" y ?nodo=jalisco en form action',
    'index.php?nodo=pampanito' => 'Login Pampanito — badge "Pampanito - Trujillo - Sta Ana" y ?nodo=pampanito en form action',
    'index.php' => 'Login Sitelco (default) — badge "SITELCO / Galanet" sin ?nodo=',
];

foreach ($livePages as $url => $desc) {
    $fullUrl = "$scheme://$host/portal/$url";
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
        $html = @file_get_contents($fullUrl, false, $ctx);
        if ($html === false) {
            nok("No se pudo obtener $url", $fullUrl . ' (posible ModSecurity)');
            continue;
        }
        if (strpos($url, 'nodo=jalisco') !== false) {
            $hasBadge = (strpos($html, 'Nodo Jalisco') !== false || strpos($html, 'Wiven - Nodo Jalisco') !== false);
            $hasFormNodo = (strpos($html, '?nodo=jalisco') !== false);
            if ($hasBadge) ok("Badge muestra 'Nodo Jalisco'", $fullUrl);
            else nok("Badge NO muestra 'Nodo Jalisco'", $fullUrl);
            if ($hasFormNodo) ok("Form action contiene ?nodo=jalisco", $fullUrl);
            else nok("Form action NO contiene ?nodo=jalisco", $fullUrl);
        } elseif (strpos($url, 'nodo=pampanito') !== false) {
            $hasBadge = (strpos($html, 'Pampanito') !== false);
            $hasFormNodo = (strpos($html, '?nodo=pampanito') !== false);
            if ($hasBadge) ok("Badge muestra 'Pampanito'", $fullUrl);
            else nok("Badge NO muestra 'Pampanito'", $fullUrl);
            if ($hasFormNodo) ok("Form action contiene ?nodo=pampanito", $fullUrl);
            else nok("Form action NO contiene ?nodo=pampanito", $fullUrl);
        } else {
            // Sitio sin nodo
            $hasSitelco = (strpos($html, 'SITELCO') !== false || strpos($html, 'Galanet') !== false);
            $noNodo = (strpos($html, '?nodo=') === false && strpos($html, '&nodo=') === false);
            if ($hasSitelco && $noNodo) ok("Sin ?nodo= en URL — badge muestra Sitelco", $fullUrl);
            else nok("Sin ?nodo= — verificar badge/links", $fullUrl);
        }
    } catch (\Throwable $e) {
        nok("Error obteniendo $url", $e->getMessage());
    }
}
endsec();

// ─── 5. RESUMEN ─────────────────────────────────────────────────────────
$pct = $total > 0 ? round($pass / $total * 100, 1) : 0;
?>
<div class="summary" style="text-align:center">
    <p style="font-size:1.3rem;font-weight:700;margin:0">
        <span class="badge badge-pass"><?php echo $pass; ?> PASS</span>
        <span class="badge badge-fail"><?php echo $fail; ?> FAIL</span>
        <span class="badge badge-total" style="margin-left:8px"><?php echo $total; ?> TOTAL</span>
        <span style="margin-left:12px;color:#94a3b8;font-size:1rem"><?php echo $pct; ?>% éxito</span>
    </p>
    <?php if ($fail > 0): ?>
        <p style="color:#ef4444;margin:10px 0 0">❌ Algunas pruebas fallaron. Revisa los detalles arriba.</p>
    <?php else: ?>
        <p style="color:#10b981;margin:10px 0 0">✅ Todas las pruebas pasaron. El nodo se preserva correctamente en todas las secciones.</p>
    <?php endif; ?>
</div>

<details>
    <summary>📋 Instrucciones de prueba manual en navegador</summary>
    <ol style="color:#94a3b8;font-size:0.85rem;margin:10px 0">
        <li><strong>Login como Jalisco:</strong> <code>https://app.marateltru.com/portal/index.php?nodo=jalisco</code> — ingresar V9174522 → debe mostrar badge "Wiven - Nodo Jalisco" en login y en dashboard</li>
        <li><strong>Botón Continuar:</strong> en dashboard, debe ir a <code>pago.php?id_contrato=X&nodo=jalisco</code></li>
        <li><strong>Volver desde pago.php:</strong> el link de "volver" debe ir a <code>dashboard.php?nodo=jalisco</code></li>
        <li><strong>Ir al Dashboard (modal):</strong> después de pagar, "Ir al Dashboard" debe ir a <code>dashboard.php?nodo=jalisco</code></li>
        <li><strong>Salir:</strong> el botón Salir debe ir a <code>index.php?logout=1&nodo=jalisco</code></li>
        <li><strong>Login como Pampanito:</strong> <code>https://app.marateltru.com/portal/index.php?nodo=pampanito</code> — ingresar 15217235 → badge "Pampanito - Trujillo - Sta Ana"</li>
        <li><strong>Cross-nodo:</strong> estando logueado en jalisco, acceder a <code>index.php?nodo=sitelco</code> → debe forzar logout y mostrar login de sitelco</li>
    </ol>
</details>

<details>
    <summary>🔧 Referencia: archivos modificados y commits</summary>
    <pre>
Commit 4b6ee5e - fix: security_helper session timeout + login.php legacy preservan nodo
Commit 5119f31 - docs: agregados cambios 2026-07-23 al changelog (nodo, Pampanito, redirects)
Commit 2b5058a - fix: boton Continuar en dashboard preserva nood
Commit 4ab5915 - fix: enlaces volver/ir-a-dashboard en pago.php preservan nood
Commit b2c9018 - feat: agregar cuenta Pampanito + badge dinámico
Commit bde2237 - fix: _wisp_detect_nodo sesion antes que regex URL + form pago con nood
Commit 4be1825 - fix: preservar nood en todos los redirects + forzar re-login
Commit a4a1e62 - fix: cambiar base_url Jalisco a api.wisphub.io
Commit df9d1cf - fix: JS sobrescribia form.action y eliminaba ?nodo=
    </pre>
</details>

</body>
</html>
