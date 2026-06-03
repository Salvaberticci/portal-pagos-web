<?php
// Configuración de PagoFlash
// IMPORTANTE: Sustituir estas llaves por las proporcionadas en tu panel de comercio en qa.pagoflash.com
define('PAGOFLASH_KEY_PUBLIC', 'PON_TU_KEY_PUBLIC_AQUI');
define('PAGOFLASH_KEY_SECRET', 'PON_TU_KEY_SECRET_AQUI');

// false para PRODUCCIÓN, true para SANDBOX (Pruebas)
define('PAGOFLASH_SANDBOX', true);

// URLs Base según el entorno
$pf_base_url = PAGOFLASH_SANDBOX ? 'https://qa.pagoflash.com/api/v1' : 'https://api.pagoflash.com/api/v1';
define('PAGOFLASH_API_URL', $pf_base_url . '/order');

// URL donde PagoFlash devolverá la notificación silenciosa
// Asegúrate de que este dominio sea accesible desde internet (no localhost en producción)
$dominio_actual = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$carpeta_proyecto = dirname($_SERVER['PHP_SELF']); // e.g. /sistemas-administrativo.../portal
define('PAGOFLASH_WEBHOOK_URL', $dominio_actual . $carpeta_proyecto . '/webhook_pagoflash.php');
