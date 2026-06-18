<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$wispConfig = include 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$refObj = new ReflectionObject($wispClient);
$refMethod = $refObj->getMethod('request');
$refMethod->setAccessible(true);

echo "--- Probando filtros en facturas/ ---\n";

$filtros = [
    'cliente' => ['cliente' => 902],
    'id_cliente' => ['id_cliente' => 902],
    'cliente_id' => ['cliente_id' => 902],
    'usuario' => ['usuario' => 'onu_prueba_oficina@sitelco'],
    'cedula' => ['cedula' => 'V20788775']
];

foreach ($filtros as $label => $param) {
    $res = $refMethod->invoke($wispClient, 'GET', 'facturas/', array_merge($param, ['limit' => 1]));
    if ($res['status'] === 200 && !empty($res['data']['results'])) {
        $f = $res['data']['results'][0];
        echo "Filtro '$label' -> ESTATUS: 200, CLIENTE ENCONTRADO: " . $f['cliente']['nombre'] . " (" . $f['cliente']['cedula'] . ")\n";
    } else {
        echo "Filtro '$label' -> Falló o no trajo resultados.\n";
    }
}
