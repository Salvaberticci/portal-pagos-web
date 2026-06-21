<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$config = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($config);

$abonos = [
    ['V23838767', 'JAIME TERAN', 907, 9654],
    ['V20788775', 'Cliente OFICINA Prueba', 902, 9805],
    ['V12907714', 'YHONY PENA', 899, 9287],
    ['V26094384', 'Ana Roymar Villegas', 884, 9304],
    ['V23254261', 'ANDREINA GIL', 880, 9686],
];

foreach ($abonos as $a) {
    $ced = $a[0]; $nom = $a[1]; $svc = $a[2]; $fact = $a[3];
    echo "=== $ced ($nom) svc #$svc ===\n";
    
    $detail = $client->getInvoiceDetail((string)$fact);
    $femi = $detail['fecha_emision'] ?? '';
    $fven = $detail['fecha_vencimiento'] ?? '';
    $total = $detail['total'] ?? 0;
    $cob = $detail['total_cobrado'] ?? 0;
    $periodo = ($femi && $fven) ? round((strtotime($fven)-strtotime($femi))/86400) : 0;
    $tipo = $periodo > 1 ? 'RECURRENTE' : 'PAGO_UNICO';
    $resta = $total - $cob;
    echo "  Factura #$fact: \${$total} pagado \${$cob} resta \${$resta}\n";
    echo "  emi:{$femi} venc:{$fven} periodo:{$periodo}d - $tipo\n";
    
    // Profile for plan
    $profileRes = $client->getServiceProfile((string)$svc);
    $profile = $profileRes['data'] ?? [];
    $plan = $profile['plan_internet']['nombre'] ?? '?';
    $estado = $profile['estado'] ?? '?';
    echo "  Estado: $estado | Plan: $plan\n\n";
}
