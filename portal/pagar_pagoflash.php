<?php
session_start();
if (!isset($_SESSION['cliente_cedula'])) {
    header('Location: index.php');
    exit;
}

require '../paginas/conexion.php';
require 'config_pagoflash.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_contrato'])) {
    die("Petición inválida.");
}

$id_contrato = intval($_POST['id_contrato']);
$deuda_base = isset($_POST['deuda_base']) ? floatval($_POST['deuda_base']) : 0;
$monto_plan = isset($_POST['monto_plan']) ? floatval($_POST['monto_plan']) : 0;
$meses_adelanto = isset($_POST['meses_adelanto']) ? intval($_POST['meses_adelanto']) : 0;

$monto = $deuda_base + ($monto_plan * $meses_adelanto);
$cedula = $_SESSION['cliente_cedula'];

if ($monto <= 0) {
    die("El monto a pagar debe ser mayor a cero.");
}

// Generar un ID de orden único interno (puedes guardarlo en BD si deseas trackear intentos)
$order_id = 'ORD-' . $id_contrato . '-' . time();

// Preparar payload para PagoFlash
$payload = [
    'p_key_public' => PAGOFLASH_KEY_PUBLIC,
    'p_key_secret' => PAGOFLASH_KEY_SECRET,
    'p_order_id'   => $order_id,
    'p_order_amount' => number_format($monto, 2, '.', ''), // Asegurar formato 123.45
    'p_order_description' => "Pago de Mensualidad - Contrato #$id_contrato - $cedula",
    'p_order_currency' => "USD",
    'p_url_callback' => PAGOFLASH_WEBHOOK_URL
];

// URLs Base según el entorno
$pf_base_url = PAGOFLASH_SANDBOX ? 'https://qa.pagoflash.com/payment-gateway-commerce' : 'https://pagoflash.com/payment-gateway-commerce';


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Procesando Pago...</title>
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .loader { border: 4px solid rgba(255,255,255,0.1); border-left-color: #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div style="text-align: center;">
        <div class="loader"></div>
        <h3>Conectando con pasarela segura...</h3>
        <p>Por favor, no cierres esta ventana.</p>
    </div>

    <form id="pagoflashForm" action="<?php echo $pf_base_url; ?>" method="POST" style="display: none;">
        <input type="hidden" name="p_key_public" value="<?php echo PAGOFLASH_KEY_PUBLIC; ?>">
        <input type="hidden" name="p_key_secret" value="<?php echo PAGOFLASH_KEY_SECRET; ?>">
        <input type="hidden" name="p_order_id" value="<?php echo htmlspecialchars($order_id); ?>">
        <input type="hidden" name="p_order_amount" value="<?php echo number_format($monto, 2, '.', ''); ?>">
        <input type="hidden" name="p_order_description" value="<?php echo htmlspecialchars("Pago de Mensualidad - Contrato #$id_contrato - $cedula"); ?>">
        <input type="hidden" name="p_order_currency" value="USD">
        <input type="hidden" name="p_url_callback" value="<?php echo PAGOFLASH_WEBHOOK_URL; ?>">
    </form>

    <script>
        document.getElementById('pagoflashForm').submit();
    </script>
</body>
</html>
