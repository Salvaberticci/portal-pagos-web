<?php
$apiKey = 'ubxyK8jE.BoTLrjCN8zRDaaybVL6E3X270cojY15W';
$base = 'https://api.wisphub.net/api/';

echo "Consultando API de PRODUCCION: $base\n";

$ch = curl_init($base . 'clientes/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['page' => 1]),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$r = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP $code\n";
if ($err) echo "Error: $err\n";

$json = json_decode($r, true);
if ($json) {
    if (isset($json['results']) && is_array($json['results'])) {
        echo "Total clientes: " . count($json['results']) . "\n";
        foreach ($json['results'] as $c) {
            $id = $c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '?';
            $name = $c['nombre'] ?? $c['name'] ?? $c['cliente'] ?? $c['nombre_completo'] ?? '?';
            $cedula = $c['cedula'] ?? $c['documento'] ?? $c['identificacion'] ?? '?';
            $status = $c['estado'] ?? $c['status'] ?? '?';
            echo "  ID: $id | Nombre: $name | Cedula: $cedula | Estado: $status\n";
        }
    } elseif (isset($json['data']) && is_array($json['data'])) {
        echo "Total clientes: " . count($json['data']) . "\n";
        foreach ($json['data'] as $c) {
            $id = $c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '?';
            $name = $c['nombre'] ?? $c['name'] ?? $c['cliente'] ?? '?';
            $cedula = $c['cedula'] ?? $c['documento'] ?? '?';
            $status = $c['estado'] ?? $c['status'] ?? '?';
            echo "  ID: $id | Nombre: $name | Cedula: $cedula | Estado: $status\n";
        }
    } else {
        echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Raw: " . substr($r, 0, 1000) . "\n";
}
