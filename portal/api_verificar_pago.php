<?php
// portal/api_verificar_pago.php
session_start();
require_once 'security_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cliente_cedula'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$cedula = $_SESSION['cliente_cedula'];

// CSRF Verification
$csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    echo json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

// Rate limiting
if (!check_rate_limit('api_verificar_pago', 10, 60)) {
    echo json_encode(['status' => 'error', 'message' => 'Demasiadas solicitudes. Espera un momento.']);
    exit;
}

// Inputs
$id_banco = isset($_REQUEST['id_banco']) ? intval($_REQUEST['id_banco']) : 0;
$referencia = isset($_REQUEST['referencia']) ? trim($_REQUEST['referencia']) : '';
$fecha_pago = isset($_REQUEST['fecha_pago']) ? trim($_REQUEST['fecha_pago']) : '';
$metodo_pago = isset($_REQUEST['metodo_pago']) ? trim($_REQUEST['metodo_pago']) : '';
$tasa_dolar = obtener_tasa_bcv();
$wisp_service_id = isset($_REQUEST['id_contrato']) ? trim($_REQUEST['id_contrato']) : '';
$invoice_ids = isset($_REQUEST['invoice_ids']) ? $_REQUEST['invoice_ids'] : [];

if (empty($referencia) || empty($fecha_pago) || empty($id_banco)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

// Clean reference
$referencia_clean = preg_replace('/[^a-zA-Z0-9]/', '', $referencia);
if (empty($referencia_clean) || strlen($referencia_clean) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'La referencia debe tener al menos 6 caracteres alfanuméricos.']);
    exit;
}
$referencia = $referencia_clean;

// Conectar a Banco API Router
require_once __DIR__ . '/../paginas/principal/banco_api_router.php';
@include_once __DIR__ . '/../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

$api_cfg = obtener_config_api_banco($id_banco);
if ($api_cfg === null) {
    echo json_encode([
        'status' => 'manual',
        'message' => 'Este método requiere verificación manual por administración.'
    ]);
    exit;
}

// Consultar la API del banco
$ts         = strtotime($fecha_pago);
$fecha_ini  = date('Y-m-d', strtotime('-1 day', $ts));
$fecha_fin  = date('Y-m-d', strtotime('+1 day', $ts));
$hoy        = date('Y-m-d');
if ($fecha_fin > $hoy) $fecha_fin = $hoy;

$resultado = consultar_movimientos_banco($id_banco, $fecha_ini, $fecha_fin);

if (!$resultado['success'] || empty($resultado['movs'])) {
    echo json_encode(['status' => 'error', 'message' => 'No pudimos consultar los movimientos del banco en este momento. Inténtalo más tarde o reporta para verificación manual.']);
    exit;
}

// Buscar movimiento
$mov_ref = null;
$ref_user_clean = preg_replace('/\D/', '', $referencia);

if ($metodo_pago === 'Transferencia') {
    foreach ($resultado['movs'] as $mov) {
        $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
        $desc = strtoupper($mov['descripcion'] ?? '');
        if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
        if (!isset($mov['referencia'])) continue;
        if (preg_replace('/\D/', '', $mov['referencia']) === $ref_user_clean) {
            $mov_ref = $mov;
            break;
        }
    }
} else {
    if (strlen($ref_user_clean) > 8) {
        $ref_user_clean = substr($ref_user_clean, -8);
    }
    $ref_user_6 = strlen($ref_user_clean) >= 6 ? substr($ref_user_clean, -6) : $ref_user_clean;
    $ref_user_8 = strlen($ref_user_clean) >= 8 ? substr($ref_user_clean, -8) : $ref_user_clean;

        foreach ($resultado['movs'] as $mov) {
            $tipo = strtoupper($mov['Tipo'] ?? $mov['mov'] ?? '');
            $desc = strtoupper($mov['descripcion'] ?? '');
            if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
            if (!isset($mov['referencia'])) continue;

        $ref_banco_clean = preg_replace('/\D/', '', $mov['referencia']);
        $ref_banco_6 = strlen($ref_banco_clean) >= 6 ? substr($ref_banco_clean, -6) : $ref_banco_clean;
        $ref_banco_8 = strlen($ref_banco_clean) >= 8 ? substr($ref_banco_clean, -8) : $ref_banco_clean;

        if (
            $ref_banco_clean === $ref_user_clean ||
            ($ref_banco_8 !== '' && $ref_banco_8 === $ref_user_8) ||
            ($ref_banco_6 !== '' && $ref_banco_6 === $ref_user_6)
        ) {
            $mov_ref = $mov;
            break;
        }
    }
}

if (!$mov_ref) {
    echo json_encode(['status' => 'error', 'message' => 'La referencia no fue encontrada en los movimientos del banco. Verifica la fecha y el número de referencia.']);
    exit;
}

// Obtener monto real del banco
$monto_mov_raw = $mov_ref['importe'] ?? $mov_ref['monto'] ?? '0';
$monto_mov = floatval(str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,.]/', '', $monto_mov_raw))));

if ($monto_mov <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'El monto del movimiento bancario es inválido.']);
    exit;
}

// Convertir a USD
if ($tasa_dolar <= 0) $tasa_dolar = 1.0;
$monto_usd = round($monto_mov / $tasa_dolar, 2);

// Obtener deuda de WispHub
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
$wispClient = new \Services\WispHubClient($wispConfig);

$deuda_usd = 0.00;
$selected_deuda_usd = 0.00;
if (!empty($wisp_service_id)) {
    $invoices = $wispClient->getPendingInvoices($wisp_service_id);
    foreach ($invoices as $inv) {
        $inv_monto = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
        $deuda_usd += $inv_monto;
        // Si hay invoice_ids seleccionados, sumar solo esas
        if (!empty($invoice_ids) && in_array(strval($inv['id'] ?? ''), $invoice_ids, true)) {
            $selected_deuda_usd += $inv_monto;
        }
    }
}

// Si se seleccionaron facturas especificas, usar esa deuda
$deuda_referencia = !empty($invoice_ids) ? $selected_deuda_usd : $deuda_usd;

// Si es el usuario de prueba, usar montos de prueba
if ($cedula === TEST_USER_CEDULA) {
    $deuda_usd = 1.00 / $tasa_dolar;
    $deuda_referencia = $deuda_usd;
}

// Comparar montos para determinar acción
$diferencia = round($monto_usd - $deuda_referencia, 2);
if ($diferencia < 0) {
    $tipo_pago = 'abono';
    $descripcion = "Estás realizando un ABONO de $" . number_format($monto_usd, 2) . " USD. El saldo pendiente de los recibos seleccionados quedará en $" . number_format(abs($diferencia), 2) . " USD.";
} elseif ($diferencia == 0) {
    $tipo_pago = 'completo';
    $descripcion = "Estás PAGANDO POR COMPLETO los recibos seleccionados por $" . number_format($deuda_referencia, 2) . " USD.";
} else {
    $tipo_pago = 'saldo_favor';
    $descripcion = "Pagas los recibos seleccionados ($" . number_format($deuda_referencia, 2) . " USD) y quedará un SALDO A FAVOR de $" . number_format($diferencia, 2) . " USD.";
}

// Enviar respuesta con detalles del movimiento
echo json_encode([
    'status'     => 'verified',
    'movimiento' => [
        'referencia_banco' => $mov_ref['referencia'] ?? '',
        'importe_bs'       => $monto_mov,
        'importe_usd'      => $monto_usd,
        'tipo_movimiento'  => $mov_ref['mov'] ?? $mov_ref['Tipo'] ?? '',
        'observacion'      => $mov_ref['concepto'] ?? '',
        'fecha'            => $fecha_pago,
    ],
    'monto_bs'    => $monto_mov,
    'monto_usd'   => $monto_usd,
    'deuda_usd'   => $deuda_usd,
    'deuda_seleccionada_usd' => $deuda_referencia,
    'tipo_pago'   => $tipo_pago,
    'descripcion' => $descripcion,
    'referencia'  => $referencia,
    'invoice_ids' => $invoice_ids,
]);
exit;
