<?php
/**
 * config/wisphub_credentials.sample.php
 *
 * COPIA este archivo como wisphub_credentials.php (sin "sample") y completa
 * tus credenciales reales de WispHub.
 *
 * Este archivo NO se sube al repositorio (config/wisphub_credentials.php
 * está en .gitignore).
 *
 * Instrucciones:
 *   1. Copiar: cp wisphub_credentials.sample.php wisphub_credentials.php
 *   2. Completar los valores abajo
 *   3. NUNCA subir wisphub_credentials.php al repositorio
 */

// ── API de WispHub ────────────────────────────────────────────────────────────
define('WISP_HUB_API_KEY',    'AQUI_TU_API_KEY');
define('WISP_HUB_API_SECRET', 'AQUI_TU_API_SECRET'); // Clave HMAC para webhooks
define('WISP_HUB_BASE_URL',   'https://api.wisphub.net/api');
// Para sandbox (pruebas): define('WISP_HUB_BASE_URL', 'https://sandbox-api.wisphub.net/api');

// ── Seguridad del cron ────────────────────────────────────────────────────────
// Clave secreta que debe pasarse como ?key=... en la URL del cron.
// Cambiar por una clave única y compleja.
define('WISP_HUB_CRON_SECRET', 'cambia_esta_clave_por_una_unica');
