<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Services/WispHubClient.php';

$cfg = include __DIR__ . '/config/wisp_hub.php';
$wisp = new \Services\WispHubClient($cfg);

$invId = $argv[1] ?? 10164;

echo "=== Detalle completo de Factura #$invId ===\n";
$detail = $wisp->getInvoiceDetail((string)$invId);

if (empty($detail)) {
    echo "No se pudo obtener detalle\n";
    exit(1);
}

echo "Todos los campos:\n";
foreach ($detail as $key => $val) {
    if (is_array($val)) {
        echo "  $key: " . json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "  $key: $val\n";
    }
}

echo "\n=== Articulos ===\n";
$articulos = $detail['articulos'] ?? $detail['items'] ?? [];
foreach ($articulos as $i => $art) {
    echo "  Artículo $i:\n";
    foreach ($art as $k => $v) {
        echo "    $k: $v\n";
    }
}

echo "\n=== Servicio/Cliente ===\n";
echo "  service_id: " . ($detail['id_servicio'] ?? $detail['service_id'] ?? 'N/A') . "\n";
echo "  cliente usuario: " . (is_array($detail['cliente'] ?? null) ? ($detail['cliente']['usuario'] ?? json_encode($detail['cliente'])) : ($detail['cliente'] ?? 'N/A')) . "\n";

echo "\n=== Total breakdown ===\n";
echo "  total: " . ($detail['total'] ?? 'N/A') . "\n";
echo "  subtotal: " . ($detail['subtotal'] ?? 'N/A') . "\n";
echo "  impuesto: " . ($detail['impuesto'] ?? 'N/A') . "\n";
echo "  descuento: " . ($detail['descuento'] ?? 'N/A') . "\n";

echo "\n=== Fechas ===\n";
echo "  fecha_emision: " . ($detail['fecha_emision'] ?? 'N/A') . "\n";
echo "  fecha_vencimiento: " . ($detail['fecha_vencimiento'] ?? 'N/A') . "\n";
echo "  fecha_pago: " . ($detail['fecha_pago'] ?? 'N/A') . "\n";
