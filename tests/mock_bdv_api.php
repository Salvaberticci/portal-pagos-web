<?php
// tests/mock_bdv_api.php
header('Content-Type: application/json');

// Leer cuerpo de la petición (para depuración si fuese necesario)
$request_body = file_get_contents('php://input');
$request_data = json_decode($request_body, true);

// Retornar transacciones de prueba
echo json_encode([
    'code' => '1000',
    'status' => 200,
    'message' => 'consulta exitosa',
    'data' => [
        'movs' => [
            [
                'mov' => 'CREDITO',
                'referencia' => '999111',
                'importe' => '100,00',
                'concepto' => 'PAGO MOVIL PRUEBA'
            ],
            [
                'mov' => 'CREDITO',
                'referencia' => '999222',
                'importe' => '1,00',
                'concepto' => 'PAGO MOVIL PRUEBA'
            ],
            [
                'mov' => 'CREDITO',
                'referencia' => '999333',
                'importe' => '5,50',
                'concepto' => 'PAGO MOVIL PRUEBA'
            ],
            [
                'mov' => 'DEBITO',
                'referencia' => '2026060400999444',
                'importe' => '2,00',
                'concepto' => 'COMISION PAGO MOVIL'
            ],
            [
                'mov' => 'CREDITO',
                'referencia' => '2026060400999444',
                'importe' => '10,00',
                'concepto' => 'PAGO MOVIL PRUEBA'
            ]
        ]
    ]
]);
