<?php
require_once 'vendor/autoload.php';
require_once 'src/Services/WispHubClient.php';
$config = require 'config/wisp_hub.php';
$c = new \Services\WispHubClient($config);

$url = rtrim($config['base_url'], '/') . '/clientes/657/';
$options = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
    'http' => [
        'header'  => "Authorization: Token " . $config['api_key'] . "\r\n",
        'method'  => 'GET',
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
print_r(json_decode($result, true));
