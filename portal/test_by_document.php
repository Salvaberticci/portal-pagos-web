<?php
require_once __DIR__ . '/../paginas/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($wispConfig);

$cedula = $_GET['cedula'] ?? 'V99999999';
echo "<h2>Prueba: getClientByDocument('$cedula')</h2>";
echo "<pre>";
$result = $client->getClientByDocument($cedula);
echo "Status: " . $result['status'] . "\n";
echo "Response:\n";
print_r($result['data']);
echo "</pre>";

if ($result['status'] === 200 && !empty($result['data']['data']['service_id'])) {
    $serviceId = $result['data']['data']['service_id'];
    echo "<p style='color:green'>✓ Cliente encontrado. Service ID: <strong>$serviceId</strong></p>";

    // Probar registerPaymentAndActivate con la cédula
    echo "<h3>Prueba: registerPaymentAndActivate con cédula</h3>";
    echo "<pre>";
    $payResult = $client->registerPaymentAndActivate(
        '',
        1.00,
        'TEST_CEDULA_' . date('YmdHis'),
        date('Y-m-d H:i'),
        \Services\WispHubClient::FORMA_PAGO_OPERACION_BANCARIA,
        false,
        $cedula
    );
    print_r($payResult);
    echo "</pre>";
} else {
    $msg = $result['data']['message'] ?? 'Cliente no encontrado';
    echo "<p style='color:red'>✗ $msg</p>";
}
