<?php
/**
 * config/wisphub_credentials.php
 *
 * Configuración multi-cuenta de WispHub.
 * Cada entrada del arreglo $WISPHUB_ACCOUNTS corresponde a una cuenta (nodo o grupo de nodos).
 * La clave del arreglo ('sitelco', 'jalisco', etc.) es el "account_ref" que se usa internamente.
 *
 * Para agregar un nuevo nodo:
 *   1. Agrega una entrada en $WISPHUB_ACCOUNTS con la API Key que te dio el cliente.
 *   2. Agrega el alias en el switch de WISP_HUB_ACTIVE_ACCOUNT más abajo.
 *   3. En cPanel, apunta el nuevo subdominio/path a la misma carpeta del proyecto.
 */

// ─── CUENTAS DE WISPHUB ──────────────────────────────────────────────────────
$WISPHUB_ACCOUNTS = [
    'sitelco' => [
        'label'      => 'SITELCO / Galanet (Principal)',
        'api_key'    => 'ubxyK8jE.BoTLrjCN8zRDaaybVL6E3X270cojY15W',
        'api_secret' => '',
        'base_url'   => 'https://api.wisphub.net/api',
        'verify_ssl' => false,
        // Nodos que pertenecen a esta cuenta (para referencia, no usados en código)
        'nodos'      => ['km23', 'bosque', 'escuque', 'cumbres'],
    ],
    'jalisco' => [
        'label'      => 'Wiven - Nodo Jalisco',
        'api_key'    => 'krxbkpsX.y06PZ1NdvMk1PPoI4Wc2vCWLa0gDSJqO',
        'api_secret' => '',
        'base_url'   => 'https://api.wisphub.io/api',
        'verify_ssl' => false,
        'nodos'      => ['jalisco'],
    ],
    // ── Agrega nuevas cuentas aquí siguiendo el mismo formato ──
    // 'merida' => [
    //     'label'      => 'Nodo Mérida',
    //     'api_key'    => 'TU_API_KEY_AQUI',
    //     'api_secret' => '',
    //     'base_url'   => 'https://api.wisphub.net/api',
    //     'verify_ssl' => true,
    //     'nodos'      => ['merida'],
    // ],
];

// ─── DETECCIÓN AUTOMÁTICA DEL NODO ACTIVO ────────────────────────────────────
// Lee la URL para detectar el nodo. Funciona con:
//   - Parámetro GET: ?nodo=jalisco (toma prioridad)
//   - Subdominios: jalisco.midominio.com → detecta 'jalisco'
//   - Rutas limpias: /portal/jalisco → detecta 'jalisco' (via htaccess rewrite)
function _wisp_detect_nodo(): string {
    // 1. Intentar desde parámetro GET (prioridad máxima, evita que regex capte index.php)
    if (!empty($_GET['nodo'])) {
        return strtolower(preg_replace('/[^a-z0-9_-]/i', '', $_GET['nodo']));
    }
    // 2. Intentar desde el subdominio
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $subdomains = explode('.', $host);
    if (count($subdomains) >= 3) {
        return $subdomains[0];
    }
    // 3. Intentar desde PATH_INFO o REQUEST_URI (rutas limpias /portal/jalisco)
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/portal/([a-z0-9_-]+)#i', $uri, $m)) {
        $captured = strtolower($m[1]);
        // Ignorar nombres de archivos PHP conocidos
        $skip = ['index', 'dashboard', 'pago', 'diagnostico', 'clear_cache'];
        if (!in_array($captured, $skip, true)) {
            return $captured;
        }
    }
    // 4. Intentar desde la sesión (el cliente ya inició sesión y guardamos su nodo)
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['wisp_account_ref'])) {
        return $_SESSION['wisp_account_ref'];
    }
    return 'sitelco'; // Cuenta por defecto
}

// ─── MAPEO ALIAS → ACCOUNT_REF ───────────────────────────────────────────────
// Mapea las palabras clave de la URL al account_ref correcto.
// Útil si un nodo tiene varios nombres posibles en la URL.
$_nodo_detectado = _wisp_detect_nodo();
$_account_ref    = 'sitelco'; // Valor por defecto

switch ($_nodo_detectado) {
    case 'jalisco':
    case 'wiven':
        $_account_ref = 'jalisco';
        break;
    case 'km23':
    case 'bosque':
    case 'escuque':
    case 'cumbres':
    case 'sitelco':
    default:
        $_account_ref = 'sitelco';
        break;
    // Agrega nuevos casos aquí cuando tengas más cuentas:
    // case 'merida':
    //     $_account_ref = 'merida';
    //     break;
}

// ─── EXPORTAR CONSTANTES ACTIVAS ─────────────────────────────────────────────
$_creds = $WISPHUB_ACCOUNTS[$_account_ref] ?? $WISPHUB_ACCOUNTS['sitelco'];

define('WISP_HUB_ACTIVE_ACCOUNT', $_account_ref);
define('WISP_HUB_API_KEY',        $_creds['api_key']);
define('WISP_HUB_API_SECRET',     $_creds['api_secret'] ?? '');
define('WISP_HUB_BASE_URL',       $_creds['base_url'] ?? 'https://api.wisphub.net/api');
define('WISP_HUB_VERIFY_SSL',     $_creds['verify_ssl'] ?? false);
define('WISP_HUB_CRON_SECRET',    'cambia_esta_clave_por_una_unica'); // Para los crons

unset($_nodo_detectado, $_account_ref, $_creds);
