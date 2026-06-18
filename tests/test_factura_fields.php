<?php
require 'vendor/autoload.php';
require 'src/Services/WispHubClient.php';
$wispConfig = include 'config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$refObj = new ReflectionObject($wispClient);
$refMethod = $refObj->getMethod('request');
$refMethod->setAccessible(true);

// Consultar facturas pasándole id_servicio en la URL
$res1 = $refMethod->invoke($wispClient, 'GET', 'facturas/', ['id_servicio' => 902, 'limit' => 1]);
if ($res1['status'] === 200 && !empty($res1['data']['results'])) {
    echo "CAMPOS DE FACTURA:\n";
    print_r($res1['data']['results'][0]);
} else {
    print_r($res1);
}
