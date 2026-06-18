<?php
/**
 * config/test_mode.sample.php
 *
 * COPIA este archivo como test_mode.php (sin "sample") y completa.
 *
 * En producción, dejar TEST_USER_CEDULA vacío.
 * En desarrollo, poner la cédula del usuario de prueba.
 */

define('TEST_USER_CEDULA', 'V20788775'); // '' para deshabilitar modo pruebas
define('DEV_MODE', true); // true = usa datos mock para el cliente de prueba; false = API real siempre
