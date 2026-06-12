<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$config = include __DIR__ . '/../config/wisp_hub.php';
$client = new \Services\WispHubClient($config);

$taskId = $argv[1] ?? '37604162-c52d-4c13-9b26-f1740eafa9b7';

echo "Consultando tarea: $taskId\n";
$res = $client->getTaskStatus($taskId);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n\nEstado del servicio 902:\n";
$saldo = $client->getServiceBalance('902');
echo "Estado: " . ($saldo['data']['estado'] ?? 'N/A') . "\n";
echo "Saldo: \$" . ($saldo['data']['saldo'] ?? 'N/A') . "\n";
