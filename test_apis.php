<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
require_once __DIR__ . '/config/wisphub_credentials.php';

global $WISPHUB_ACCOUNTS;

echo "==============================================\n";
echo "PRUEBA DE CONEXION MULTI-CUENTA WISPHUB\n";
echo "==============================================\n\n";

if (empty($WISPHUB_ACCOUNTS)) {
    die("ERROR: No se encontraron cuentas configuradas en WISPHUB_ACCOUNTS.\n");
}

foreach ($WISPHUB_ACCOUNTS as $ref => $config) {
    echo "Testeando cuenta: [{$ref}] - {$config['label']}\n";
    echo "API Key: " . substr($config['api_key'], 0, 8) . "...\n";
    
    try {
        $wispClient = new \Services\WispHubClient([
            'api_key'    => $config['api_key'],
            'base_url'   => $config['base_url'],
            'verify_ssl' => $config['verify_ssl']
        ]);
        
        // Consultamos facturas para ver si tiene permisos
        $start = microtime(true);
        $resFacturas = $wispClient->getInvoices(['estado' => 1, 'limit' => 1]);
        $resServicios = $wispClient->getServiceProfile('999999');
        $time = round(microtime(true) - $start, 3);        
        
        if (isset($resFacturas['status']) && $resFacturas['status'] !== 403 && isset($resServicios['status']) && $resServicios['status'] !== 403) {
            echo "✅ CONEXION EXITOSA! Permisos de Dashboard correctos ({$time}s)\n";
        } else {
            echo "❌ ERROR DE PERMISOS\n";
            echo "   -> Facturas Status: " . ($resFacturas['status'] ?? 'N/A') . "\n";
            echo "   -> Servicios Status: " . ($resServicios['status'] ?? 'N/A') . "\n";
        }
    } catch (\Throwable $e) {
        echo "❌ EXCEPCION: " . $e->getMessage() . "\n";
    }
    echo "----------------------------------------------\n";
}
echo "Prueba finalizada.\n";
