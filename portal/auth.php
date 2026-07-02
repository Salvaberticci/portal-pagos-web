<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');
echo "PHP: " . phpversion() . "\n";
echo "START\n";

require_once 'security_helper.php';
echo "OK\n";

@include_once '../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

echo "STEP 1: basic init OK\n";
echo "DEV_MODE: " . (DEV_MODE ? 'true' : 'false') . "\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "STEP 2: POST detected\n";
    exit;
} else {
    echo "STEP 2: GET - redirect\n";
    if (isset($_GET['logout'])) {
        if (isset($_SESSION['cliente_cedula'])) {
            log_security_event('LOGOUT', 'Cierre de sesión', $_SESSION['cliente_cedula']);
        }
        session_destroy();
    }
    echo "Redirect to index.php\n";
    exit;
}
