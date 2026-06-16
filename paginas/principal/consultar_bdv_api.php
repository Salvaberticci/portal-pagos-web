<?php
/**
 * consultar_bdv_api.php
 * Endpoint AJAX para el panel admin de conciliación.
 *
 * Recibe via POST JSON: { fecha_inicio, fecha_fin, id_banco? }
 * Devuelve: JSON con movimientos normalizados listos para el grid de conciliación.
 *
 * Si no se pasa id_banco, usa el primer banco BDV habilitado en bancos.json.
 */

require_once '../conexion.php';
require_once 'banco_api_router.php'; // router genérico (incluye bdv_api_helper)

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$fecha_inicio = trim($input['fecha_inicio'] ?? '');
$fecha_fin    = trim($input['fecha_fin']    ?? '');
$id_banco     = $input['id_banco'] ?? null;

if (empty($fecha_inicio) || empty($fecha_fin)) {
    echo json_encode(['success' => false, 'message' => 'Se requieren fecha_inicio y fecha_fin.']);
    exit;
}

// Si no se especificó banco, usar el primer BDV habilitado
if (empty($id_banco)) {
    $ids_bdv = obtener_ids_banco_con_api('bdv');
    $id_banco = $ids_bdv[0] ?? null;
}

if (empty($id_banco)) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay ningún banco con API BDV habilitada configurada. Por favor configure la API en Gestión de Bancos.',
    ]);
    exit;
}

// Consultar vía router genérico
$resultado = consultar_movimientos_banco($id_banco, $fecha_inicio, $fecha_fin);

if (!$resultado['success']) {
    echo json_encode([
        'success' => false,
        'message' => $resultado['message'],
        'movs'    => [],
        'raw'     => $resultado['raw'] ?? null,
    ]);
    exit;
}

$movs = $resultado['movs'];

// Normalizar los movimientos al formato que espera el grid de conciliacion.php
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
