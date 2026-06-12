<?php
// tests/mock_wisphub_api.php
// Mock de la API de WispHub para pruebas automatizadas.
// Implementa los endpoints clave usados por WispHubClient.
//
// Uso: Incluir en servidor PHP embebido:
//   php -S 127.0.0.1:8544 -t /ruta/proyecto
//   Luego configurar wisphub_credentials.php apuntando a:
//   http://127.0.0.1:8544/tests/mock_wisphub_api.php

header('Content-Type: application/json');

// Obtener ruta de la URI (sin query string)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalizar: eliminar /api si existe (el WispHubClient usa base_uri con /api/)
$uri = preg_replace('#^/tests/mock_wisphub_api\.php#', '', $uri);
$uri = preg_replace('#^/api#', '', $uri);
$uri = trim($uri, '/');

// Obtener método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Leer body para payloads POST
$rawBody = file_get_contents('php://input');
$bodyData = json_decode($rawBody, true) ?? [];

// Log para depuración
$logFile = __DIR__ . '/../logs/mock_wisphub.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] $method $uri\n" . ($rawBody ? "Body: $rawBody\n" : '') . "\n", FILE_APPEND);

// Rutas
switch (true) {
    // POST /clientes/activar/
    case preg_match('#^clientes/activar/?$#', $uri) === 1 && $method === 'POST':
        $serviceIds = $bodyData['id_servicios'] ?? [];
        if (empty($serviceIds)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id_servicios requerido']);
            exit;
        }
        echo json_encode([
            'status' => 200,
            'message' => 'Servicio(s) activado(s) correctamente',
            'data' => [
                'activated' => $serviceIds,
                'timestamp' => date('c'),
            ],
        ]);
        exit;

    // POST /clientes/desactivar/
    case preg_match('#^clientes/desactivar/?$#', $uri) === 1 && $method === 'POST':
        $serviceIds = $bodyData['id_servicios'] ?? [];
        if (empty($serviceIds)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id_servicios requerido']);
            exit;
        }
        echo json_encode([
            'status' => 200,
            'message' => 'Servicio(s) desactivado(s) correctamente',
            'data' => [
                'suspended' => $serviceIds,
                'reason' => $bodyData['motivo'] ?? '',
                'timestamp' => date('c'),
            ],
        ]);
        exit;

    // POST /payments/notify/  o  /facturas/{id}/registrar-pago/
    case (preg_match('#^payments/notify/?$#', $uri) === 1 || preg_match('#^facturas/\d+/registrar-pago/?$#', $uri) === 1) && $method === 'POST':
        $accountId = $bodyData['customer_cedula'] ?? 'test-' . rand(1000, 9999);
        echo json_encode([
            'status' => 200,
            'message' => 'Pago registrado correctamente',
            'data' => [
                'account_id' => $accountId,
                'payment_id' => $bodyData['payment_id'] ?? null,
                'invoice_id' => 'INV-' . rand(10000, 99999),
                'timestamp' => date('c'),
            ],
        ]);
        exit;

    // GET /clientes/{id}/perfil/
    case preg_match('#^clientes/([^/]+)/perfil/?$#', $uri) === 1 && $method === 'GET':
        preg_match('#^clientes/([^/]+)/perfil/?$#', $uri, $m);
        $serviceId = $m[1];
        echo json_encode([
            'status' => 200,
            'data' => [
                'id' => $serviceId,
                'nombre' => 'Cliente de Prueba',
                'estado' => 'activo',
                'plan' => 'Plan Básico 20MB',
                'monto' => 17.50,
                'moneda' => 'USD',
                'fecha_creacion' => '2026-01-01',
                'ultimo_pago' => date('Y-m-d'),
            ],
        ]);
        exit;

    // GET /clientes/
    case preg_match('#^clientes/?$#', $uri) === 1 && $method === 'GET':
        echo json_encode([
            'status' => 200,
            'data' => [
                'clientes' => [
                    ['id' => 'test-001', 'nombre' => 'Cliente Test 1', 'estado' => 'activo'],
                    ['id' => 'test-002', 'nombre' => 'Cliente Test 2', 'estado' => 'suspendido'],
                ],
                'total' => 2,
            ],
        ]);
        exit;

    // PATCH /clientes/{id}/
    case preg_match('#^clientes/([^/]+)/?$#', $uri) === 1 && $method === 'PATCH':
        preg_match('#^clientes/([^/]+)/?$#', $uri, $m);
        $serviceId = $m[1];
        echo json_encode([
            'status' => 200,
            'message' => 'Servicio actualizado',
            'data' => [
                'id' => $serviceId,
                'updated_fields' => array_keys($bodyData),
            ],
        ]);
        exit;

    // Cualquier otra ruta → 404
    default:
        http_response_code(404);
        echo json_encode([
            'status' => 404,
            'message' => 'Endpoint no encontrado en mock WispHub',
            'uri' => $uri,
            'method' => $method,
        ]);
        exit;
}
