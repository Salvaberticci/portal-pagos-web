<?php
/**
 * test_banco_api.php
 * Endpoint AJAX de prueba de conectividad para el modal de configuración de API bancaria.
 *
 * Recibe via POST JSON: { tipo, api_key, cuenta, endpoint }
 * Hace una consulta de prueba con la fecha de hoy y retorna si la API responde.
 */

require_once '../conexion.php';
require_once 'bdv_api_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$tipo     = trim($input['tipo']     ?? '');
$api_key  = trim($input['api_key']  ?? '');
$cuenta   = trim($input['cuenta']   ?? '');
$endpoint = trim($input['endpoint'] ?? '');

if (empty($tipo) || empty($api_key) || empty($cuenta)) {
    echo json_encode(['success' => false, 'message' => 'Tipo, API Key y Cuenta son requeridos.']);
    exit;
}

$hoy      = date('Y-m-d');
$ayer     = date('Y-m-d', strtotime('-1 day'));

switch ($tipo) {
    case 'bdv':
        if (empty($endpoint)) {
            $endpoint = 'https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos';
        }
        $resultado = consultar_movimientos_bdv($cuenta, $ayer, $hoy, '', $api_key, $endpoint);

        if ($resultado['success']) {
            $total = count($resultado['movs']);
            echo json_encode([
                'success' => true,
                'message' => "✅ Conexión exitosa. Se encontraron $total movimiento(s) recientes.",
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '❌ ' . ($resultado['message'] ?? 'Sin respuesta de la API.'),
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => "Tipo '$tipo' no soportado en prueba."]);
        break;
}
