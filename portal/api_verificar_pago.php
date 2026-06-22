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

// Clean reference (solo d├¡gitos, 6-10)
$referencia_clean = preg_replace('/\D/', '', $referencia);
if (empty($referencia_clean) || strlen($referencia_clean) < 6 || strlen($referencia_clean) > 15) {
    echo json_encode(['status' => 'error', 'message' => 'La referencia debe tener entre 6 y 15 d\u00edgitos.']);
    exit;
}
$referencia = $referencia_clean;

// Verificar referencia duplicada en BD local
require_once __DIR__ . '/referencia_helper.php';
$refInfo = getReferenciaInfo($referencia);
if ($refInfo) {
    $facturas = $refInfo['facturas'] ? ' #' . $refInfo['facturas'] : '';
    echo json_encode(['status' => 'error', 'titulo' => '!REFERENCIA DUPLICADA!', 'message' => "La referencia {$referencia} ya fue utilizada en la Factura{$facturas} del día {$refInfo['fecha_pago']}, por el cliente {$refInfo['cliente']}."]);
    exit;
}

// Conectar a Banco API Router
require_once __DIR__ . '/../paginas/principal/banco_api_router.php';
@include_once __DIR__ . '/../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');
if (!defined('DEV_MODE')) define('DEV_MODE', false);

// Obtener deuda de WispHub
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
$wispConfig = include __DIR__ . '/../config/wisp_hub.php';
if (DEV_MODE && $cedula === TEST_USER_CEDULA) {
    require_once __DIR__ . '/../src/Services/WispHubDevModeClient.php';
    $wispClient = new \Services\WispHubDevModeClient($wispConfig);
} else {
    $wispClient = new \Services\WispHubClient($wispConfig);
}

$deuda_usd = 0.00;
$selected_deuda_usd = 0.00;
if (!empty($wisp_service_id)) {
    $invoices = $wispClient->getPendingInvoices($wisp_service_id);
    foreach ($invoices as $inv) {
        $inv_monto = floatval($inv['monto'] ?? $inv['monto_pendiente'] ?? $inv['total'] ?? 0);
        $deuda_usd += $inv_monto;
        if (!empty($invoice_ids) && in_array(strval($inv['id'] ?? ''), $invoice_ids, true)) {
            $selected_deuda_usd += $inv_monto;
        }
    }
}
$deuda_referencia = !empty($invoice_ids) ? $selected_deuda_usd : $deuda_usd;

// === MODO PRUEBA: saltar API del banco real ===
if (DEV_MODE && $cedula === TEST_USER_CEDULA) {
    $monto_mov = $deuda_referencia * $tasa_dolar;
    if ($tasa_dolar <= 0) $tasa_dolar = 1.0;
    $monto_usd = $deuda_referencia;
    $diferencia = round($monto_usd - $deuda_referencia, 2);
    if ($diferencia < 0) {
        $tipo_pago = 'abono';
        $descripcion = "Modo PRUEBA — Estás realizando un ABONO de $" . number_format($monto_usd, 2) . " USD. Saldo pendiente: $" . number_format(abs($diferencia), 2) . " USD.";
    } elseif ($diferencia == 0) {
        $tipo_pago = 'completo';
        $descripcion = "Modo PRUEBA — Estás PAGANDO POR COMPLETO el recibo seleccionado por $" . number_format($deuda_referencia, 2) . " USD.";
    } else {
        $tipo_pago = 'saldo_favor';
        $descripcion = "Modo PRUEBA — Pagas el recibo seleccionado ($" . number_format($deuda_referencia, 2) . " USD) y queda un SALDO A FAVOR de $" . number_format($diferencia, 2) . " USD.";
    }
    $cobertura_hasta = '';
    if ($tipo_pago === 'abono') {
        $days = round(30 * ($monto_usd / max(0.01, $deuda_referencia)));
        $cobertura_hasta = date('d/m/Y', strtotime('+' . ($days + 1) . ' days'));
    }
    echo json_encode([
        'status'     => 'verified',
        'movimiento' => [
            'referencia_banco' => $referencia,
            'importe_bs'       => round($monto_mov, 2),
            'importe_usd'      => $monto_usd,
            'tipo_movimiento'  => 'CREDITO',
            'observacion'      => 'Pago de prueba',
            'fecha'            => $fecha_pago,
        ],
        'monto_bs'    => round($monto_mov, 2),
        'monto_usd'   => $monto_usd,
        'deuda_usd'   => $deuda_usd,
        'deuda_seleccionada_usd' => $deuda_referencia,
        'tipo_pago'   => $tipo_pago,
        'cobertura_hasta' => $cobertura_hasta,
        'descripcion' => $descripcion,
        'referencia'  => $referencia,
        'invoice_ids' => $invoice_ids,
    ]);
    exit;
}

// === Fin modo prueba — flujo normal ===

// Forzar la consulta a la API del BDV independientemente del banco origen
$id_banco_api = $id_banco;
if (strpos(strtolower($metodo_pago), 'pago m') !== false) {
    $id_banco_api = 9; // BDV Pago Movil
} else if ($metodo_pago === 'Transferencia') {
    $id_banco_api = 12; // BDV Transferencia
}

$api_cfg = obtener_config_api_banco($id_banco_api);
if ($api_cfg === null) {
    echo json_encode([
        'status' => 'manual',
        'message' => 'El sistema de verificación automática no está configurado.'
    ]);
    exit;
}

// === FASE 1: Buscar referencia en rango ±10 días del pago ===
$ts  = strtotime($fecha_pago);
$fecha_inicio_busqueda = date('Y-m-d', strtotime('-10 days', $ts));
$fecha_fin_busqueda   = date('Y-m-d', strtotime('+1 day',  $ts));

$fase1 = consultar_movimientos_rango($id_banco_api, $fecha_inicio_busqueda, $fecha_fin_busqueda);
$mov_ref = buscar_referencia_en_movs($fase1['movs'], $referencia, $metodo_pago);

// === FASE 2 (fallback): Si no se encontró, buscar en todos los créditos de los últimos 30 días ===
$fase2 = null;
if (!$mov_ref) {
    $fase2 = obtener_creditos_recientes($id_banco_api);
    if (!empty($fase2['movs'])) {
        $mov_ref = buscar_referencia_en_movs($fase2['movs'], $referencia, $metodo_pago);
    }
}

if (!$mov_ref) {
    $api_respondio = !empty($fase1['api_respondio']) || !empty($fase2['api_respondio'] ?? false);
    if ($api_respondio) {
        $titulo = '!REFERENCIA NO EXISTE EN EL BANCO!';
        $message = 'La referencia no fue encontrada en los movimientos del banco. Verifica la fecha y el número de referencia. O ponte en contacto con tu número de Soporte local.';
    } else {
        $titulo = '!ERROR DE CONEXION BANCARIA!';
        $message = 'No pudimos consultar los movimientos del banco en este momento. Inténtalo más tarde o reporta para verificación manual.';
    }
    echo json_encode(['status' => 'error', 'titulo' => $titulo, 'message' => $message]);
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

// Comparar montos para determinar acción
$diferencia = round($monto_usd - $deuda_referencia, 2);
if ($diferencia < 0) {
    $tipo_pago = 'abono';
    $descripcion = "Usted hizo un abono de Bs " . number_format($monto_mov, 2, ',', '.') . " (equivalente a $" . number_format($monto_usd, 2) . ").";
} elseif ($diferencia == 0) {
    $tipo_pago = 'completo';
    $descripcion = "Usted hizo un pago de $" . number_format($monto_usd, 2) . ". Su servicio está al día.";
} else {
    $tipo_pago = 'saldo_favor';
    $descripcion = "Pagas el recibo seleccionado ($" . number_format($deuda_referencia, 2) . " USD) y quedará un SALDO A FAVOR de $" . number_format($diferencia, 2) . " USD.";
}

// Calcular cobertura para abonos (solo facturas recurrentes con período)
$cobertura_hasta = '';
if ($tipo_pago === 'abono' && !empty($invoice_ids) && $deuda_referencia > 0) {
    $firstId = (int)$invoice_ids[0];
    $invDetail = $wispClient->getInvoiceDetail((string)$firstId);
    $fechaEmi = $invDetail['fecha_emision'] ?? '';
    $fechaVenc = $invDetail['fecha_vencimiento'] ?? '';
    $totalInv = floatval($invDetail['total'] ?? 0);
    if ($totalInv > 0 && $fechaEmi && $fechaVenc) {
        $proporcion = $monto_usd / $deuda_referencia;
        $diasExtra = round(30 * $proporcion);
        $cobertura_hasta = date('d/m/Y', strtotime($fechaVenc . ' + ' . ($diasExtra + 1) . ' days'));
        $descripcion .= " Su servicio estará vigente hasta el {$cobertura_hasta}.";
    }
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
        'fecha'            => $mov_ref['fecha'] ?? $fecha_pago,
    ],
    'monto_bs'    => $monto_mov,
    'monto_usd'   => $monto_usd,
    'fecha'       => $mov_ref['fecha'] ?? $fecha_pago,
    'deuda_usd'   => $deuda_usd,
    'deuda_seleccionada_usd' => $deuda_referencia,
    'tipo_pago'   => $tipo_pago,
    'cobertura_hasta' => $cobertura_hasta,
    'descripcion' => $descripcion,
    'referencia'  => $referencia,
    'invoice_ids' => $invoice_ids,
]);
