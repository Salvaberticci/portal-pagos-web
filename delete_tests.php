<?php
require_once __DIR__ . '/vendor/autoload.php';
$wispConfig = include __DIR__ . '/config/wisp_hub.php';

$ids = [9818, 9817, 9816, 9815];

foreach ($ids as $id) {
    echo "Borrando/Anulando factura de prueba: $id...\n";
    $ch = curl_init("https://wisphub.net/api/facturas/$id/");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Api-Key " . $wispConfig['api_key']
    ]);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "DELETE HTTP code: $httpcode\n";
    
    // Si delete falla (ej: method not allowed), intentalo marcando como anulada:
    if ($httpcode !== 204 && $httpcode !== 200) {
        $ch = curl_init("https://wisphub.net/api/facturas/$id/");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Api-Key " . $wispConfig['api_key'],
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['estado' => 2])); // 2 = anulada ? 
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "PUT HTTP code: $httpcode\n";
    }
}
