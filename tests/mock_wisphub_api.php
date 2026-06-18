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
$fullUri = $_SERVER['REQUEST_URI'];
$uri = parse_url($fullUri, PHP_URL_PATH);
$query = parse_url($fullUri, PHP_URL_QUERY) ?? '';
parse_str($query, $queryParams);

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

// Helper: responder JSON
function mock_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Datos mock del cliente de prueba V20788775
$mockUsuario = 'v20788775@wirelesssupply.com';
$mockClienteData = [
    'usuario'       => $mockUsuario,
    'nombre'        => 'CLIENTE DE PRUEBA WIRELESS',
    'email'         => 'cliente@test.com',
    'cedula'        => 'V20788775',
    'direccion'     => 'Av. Principal, Edif. Test, Piso 1',
    'localidad'     => 'Barquisimeto',
    'telefono'      => '04241234567',
    'rfc'           => '',
];

// Facturas mock pendientes con IDs realistas
$mockInvoices = [
    [
        'id'              => 9681,
        'folio'           => 9681,
        'fecha_emision'   => '2026-06-15',
        'fecha_vencimiento'=> '2026-06-17',
        'fecha_pago'      => null,
        'estado'          => 'Pendiente de Pago',
        'tipo'            => 1,
        'total'           => 20.00,
        'monto_pendiente' => 20.00,
        'sub_total'       => 20.00,
        'saldo'           => 20.00,
        'total_cobrado'   => 0,
        'articulos'       => [
            [
                'id'          => 1,
                'descripcion' => 'Renta y mantenimiento de la red: ZONA KM23  Plan de Internet: Plan Basico KM23 FTTH 20.00 $  Periodo del 15/Jun/2026 al 15/Jul/2026',
                'precio'      => '20.00',
                'cantidad'    => 1,
            ],
        ],
    ],
    [
        'id'              => 9656,
        'folio'           => 9656,
        'fecha_emision'   => '2026-06-11',
        'fecha_vencimiento'=> '2026-06-11',
        'fecha_pago'      => null,
        'estado'          => 'Pendiente de Pago',
        'tipo'            => 1,
        'total'           => 35.00,
        'monto_pendiente' => 35.00,
        'sub_total'       => 35.00,
        'saldo'           => 35.00,
        'total_cobrado'   => 0,
        'articulos'       => [
            [
                'id'          => 2,
                'descripcion' => 'Instalacion Equipo en COMODATO (Prestado) Vsol AX1500. Monto Total: 35$',
                'precio'      => '35.00',
                'cantidad'    => 1,
            ],
        ],
    ],
];

// Facturas pagadas mock
$mockPaidInvoices = [
    [
        'id_factura'      => 9001,
        'folio'           => 9001,
        'fecha_emision'   => '2026-04-01',
        'fecha_vencimiento'=> '2026-04-05',
        'fecha_pago'      => '2026-04-02T14:30:00Z',
        'estado'          => 'Pagada',
        'tipo'            => 1,
        'total'           => 17.50,
        'total_cobrado'   => 17.50,
        'sub_total'       => 17.50,
        'referencia'      => 'ABC123',
        'cliente'         => $mockClienteData,
    ],
    [
        'id_factura'      => 9002,
        'folio'           => 9002,
        'fecha_emision'   => '2026-03-01',
        'fecha_vencimiento'=> '2026-03-05',
        'fecha_pago'      => '2026-03-03T10:15:00Z',
        'estado'          => 'Pagada',
        'tipo'            => 1,
        'total'           => 17.50,
        'total_cobrado'   => 17.50,
        'sub_total'       => 17.50,
        'referencia'      => 'XYZ789',
        'cliente'         => $mockClienteData,
    ],
    [
        'id_factura'      => 9003,
        'folio'           => 9003,
        'fecha_emision'   => '2026-02-01',
        'fecha_vencimiento'=> '2026-02-05',
        'fecha_pago'      => '2026-02-10T16:45:00Z',
        'estado'          => 'Pagada',
        'tipo'            => 1,
        'total'           => 17.50,
        'total_cobrado'   => 17.50,
        'sub_total'       => 17.50,
        'referencia'      => 'DEF456',
        'cliente'         => $mockClienteData,
    ],
];

// Rutas
switch (true) {
    // GET /v1/clients/by-document/{doc}
    case preg_match('#^v1/clients/by-document/(.+)$#', $uri) === 1 && $method === 'GET':
        preg_match('#^v1/clients/by-document/(.+)$#', $uri, $m);
        $doc = $m[1];
        $docClean = preg_replace('/^[A-Z]/i', '', $doc);
        if ($docClean === '20788775') {
            mock_json([
                'status' => 200,
                'data' => [
                    'service_id' => '902',
                    'cedula'     => 'V20788775',
                    'nombre'     => $mockClienteData['nombre'],
                    'email'      => $mockClienteData['email'],
                    'telefono'   => $mockClienteData['telefono'],
                    'usuario'    => $mockUsuario,
                ],
            ]);
        }
        mock_json(['status' => 404, 'data' => ['message' => 'Cliente no encontrado']], 404);

    // GET /clientes/{id}/perfil/
    case preg_match('#^clientes/([^/]+)/perfil/?$#', $uri) === 1 && $method === 'GET':
        preg_match('#^clientes/([^/]+)/perfil/?$#', $uri, $m);
        $serviceId = $m[1];
        mock_json([
            'status' => 200,
            'data' => [
                'id'                  => $serviceId,
                'nombre'              => $mockClienteData['nombre'],
                'correo'              => $mockClienteData['email'],
                'telefono'            => $mockClienteData['telefono'],
                'direccion'           => $mockClienteData['direccion'],
                'localidad'           => $mockClienteData['localidad'],
                'estado'              => 'activo',
                'plan_internet_nombre' => 'Plan Básico 20MB',
                'plan_internet_precio' => 17.50,
                'usuario'             => $mockUsuario,
                'zona'                => ['id' => 1, 'nombre' => 'Zona Norte'],
                'moneda'              => 'USD',
                'fecha_creacion'      => '2025-01-01',
            ],
        ]);

    // GET /clientes/{id}/saldo/  (facturas pendientes + saldo a favor)
    case preg_match('#^clientes/([^/]+)/saldo/?$#', $uri) === 1 && $method === 'GET':
        preg_match('#^clientes/([^/]+)/saldo/?$#', $uri, $m);
        $serviceId = $m[1];
        // Calcular saldo a favor: sobrepago en facturas pendientes
        $saldo_favor_calc = 0;
        foreach ($mockInvoices as $inv) {
            $cobrado = floatval($inv['total_cobrado'] ?? 0);
            $total   = floatval($inv['total'] ?? 0);
            if ($cobrado > $total) {
                $saldo_favor_calc += ($cobrado - $total);
            }
        }
        mock_json([
            'status' => 200,
            'data' => [
                'facturas'    => $mockInvoices,
                'total_deuda' => 55.00,
                'saldo'       => 0.00,
                'saldo_favor' => $saldo_favor_calc,
            ],
        ]);

    // GET /clientes/
    case preg_match('#^clientes/?$#', $uri) === 1 && $method === 'GET':
        mock_json([
            'status' => 200,
            'data' => [
                'results' => [
                    [
                        'id'       => '902',
                        'nombre'   => $mockClienteData['nombre'],
                        'cedula'   => 'V20788775',
                        'estado'   => 'activo',
                        'usuario'  => $mockUsuario,
                    ],
                ],
                'current_page' => 1,
                'last_page'    => 1,
                'total'        => 1,
            ],
        ]);

    // GET /facturas/  (nuevo: listado de facturas con filtros)
    case preg_match('#^facturas/?$#', $uri) === 1 && $method === 'GET':
        $estado  = $queryParams['estado'] ?? null;
        $cliente = $queryParams['cliente'] ?? null;
        $limit   = intval($queryParams['limit'] ?? 10);
        $offset  = intval($queryParams['offset'] ?? 0);

        if ($estado == 2) {
            // Facturas pagadas
            $results = $mockPaidInvoices;
        } elseif ($estado == 1) {
            // Facturas pendientes - devolver las mismas del saldo pero con formato facturas/
            $results = [];
            foreach ($mockInvoices as $inv) {
                $results[] = [
                    'id_factura'       => $inv['id'],
                    'folio'            => $inv['folio'],
                    'fecha_emision'    => $inv['fecha_emision'],
                    'fecha_vencimiento'=> $inv['fecha_vencimiento'],
                    'fecha_pago'       => $inv['fecha_pago'],
                    'estado'           => 'Pendiente de Pago',
                    'tipo'             => $inv['tipo'],
                    'total'            => $inv['total'],
                    'total_cobrado'    => $inv['total_cobrado'],
                    'sub_total'        => $inv['sub_total'],
                    'articulos'        => $inv['articulos'] ?? [],
                    'cliente'          => $mockClienteData,
                ];
            }
        } else {
            $results = $mockPaidInvoices;
        }

        mock_json([
            'count'    => count($results),
            'next'     => null,
            'previous' => null,
            'results'  => array_slice($results, $offset, $limit),
        ]);

    // GET /facturas/{id}/  (detalle de factura)
    case preg_match('#^facturas/(\d+)/?$#', $uri) === 1 && $method === 'GET' && !preg_match('#registrar-pago#', $uri):
        preg_match('#^facturas/(\d+)/?$#', $uri, $m);
        $invoiceId = intval($m[1]);
        // Buscar en pendientes o pagadas
        $found = null;
        foreach ($mockInvoices as $inv) {
            if ($inv['id'] == $invoiceId) {
                $found = $inv;
                $found['id_factura'] = $inv['id'];
                $found['cliente'] = $mockClienteData;
                $found['zona'] = ['id' => 1, 'nombre' => 'ZONA KM23'];
                break;
            }
        }
        if (!$found) {
            foreach ($mockPaidInvoices as $inv) {
                if (($inv['id_factura'] ?? 0) == $invoiceId) {
                    $found = $inv;
                    break;
                }
            }
        }
        if ($found) {
            mock_json($found);
        }
        mock_json(['status' => 404, 'message' => 'Factura no encontrada'], 404);

    // POST /facturas/{id}/registrar-pago/
    case preg_match('#^facturas/(\d+)/registrar-pago/?$#', $uri) === 1 && $method === 'POST':
        preg_match('#^facturas/(\d+)/registrar-pago/?$#', $uri, $m);
        $invoiceId = $m[1];
        mock_json([
            'status'  => 200,
            'message' => 'Pago registrado correctamente',
            'task_id' => 'mock-task-' . uniqid(),
            'data'    => [
                'invoice_id' => $invoiceId,
                'total_cobrado' => $bodyData['total_cobrado'] ?? 0,
                'referencia'    => $bodyData['referencia'] ?? '',
            ],
        ]);

    // POST /clientes/activar/
    case preg_match('#^clientes/activar/?$#', $uri) === 1 && $method === 'POST':
        $serviceIds = $bodyData['servicios'] ?? $bodyData['id_servicios'] ?? [];
        if (empty($serviceIds)) {
            mock_json(['status' => 400, 'message' => 'servicios requerido'], 400);
        }
        mock_json([
            'status'  => 200,
            'message' => 'Servicio(s) activado(s) correctamente',
            'data'    => [
                'activated' => $serviceIds,
                'timestamp' => date('c'),
            ],
        ]);

    // POST /clientes/desactivar/
    case preg_match('#^clientes/desactivar/?$#', $uri) === 1 && $method === 'POST':
        $serviceIds = $bodyData['servicios'] ?? $bodyData['id_servicios'] ?? [];
        if (empty($serviceIds)) {
            mock_json(['status' => 400, 'message' => 'servicios requerido'], 400);
        }
        mock_json([
            'status'  => 200,
            'message' => 'Servicio(s) desactivado(s) correctamente',
            'data'    => [
                'suspended' => $serviceIds,
                'reason'    => $bodyData['motivo'] ?? '',
                'timestamp' => date('c'),
            ],
        ]);

    // Cualquier otra ruta → 404
    default:
        mock_json([
            'status' => 404,
            'message' => 'Endpoint no encontrado en mock WispHub',
            'uri' => $uri,
            'method' => $method,
        ], 404);
}
