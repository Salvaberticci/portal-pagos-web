<?php
// paginas/principal/consultar_bdv_api.php
// Endpoint AJAX para el panel admin de conciliación.
// Recibe: { fecha_inicio, fecha_fin, cuenta? }  via POST JSON
// Devuelve: JSON con movimientos normalizados listos para el grid de conciliación.

require_once '../conexion.php';
require_once 'bdv_api_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$fecha_inicio = trim($input['fecha_inicio'] ?? '');
$fecha_fin    = trim($input['fecha_fin']    ?? '');
$cuenta       = trim($input['cuenta']       ?? BDV_CUENTA_DEFECTO);

if (empty($fecha_inicio) || empty($fecha_fin)) {
    echo json_encode(['success' => false, 'message' => 'Se requieren fecha_inicio y fecha_fin.']);
    exit;
}

// Llamar a la API
$resultado = consultar_movimientos_bdv($cuenta, $fecha_inicio, $fecha_fin);

if (!$resultado['success']) {
    echo json_encode([
        'success' => false,
        'message' => $resultado['message'],
        'movs'    => [],
    ]);
    exit;
}

$movs = $resultado['movs'];

// Normalizar los movimientos al formato que espera el grid de conciliacion.php
// (keys: Fecha, Referencia, Descripción, Tipo, Importe, Observación)
$movs_norm = array_map(function($m) {
    return [
        'Fecha'        => $m['fecha']       ?? '',
        'Referencia'   => $m['referencia']  ?? '',
        'Descripción'  => $m['descripcion'] ?? '',
        'Tipo'         => $m['mov']         ?? '',
        'Importe'      => $m['importe']     ?? '0',
        'Saldo'        => $m['saldo']       ?? '0',
        'Observación'  => $m['observacion'] ?? '',
        'nroMov'       => $m['nroMov']      ?? '',
    ];
}, $movs);

echo json_encode([
    'success' => true,
    'message' => $resultado['message'],
    'total'   => count($movs_norm),
    'movs'    => $movs_norm,
]);
