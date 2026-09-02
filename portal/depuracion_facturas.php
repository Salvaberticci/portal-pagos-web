<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
@include_once __DIR__ . '/../config/wisphub_credentials.php';

// ─── Manejo de acciones AJAX ──────────────────────────────────────────────────
$accion = $_POST['accion'] ?? '';

// ── Escanear errores de abono ──────────────────────────────────────────────────
if ($accion === 'escanear_errores') {
    header('Content-Type: application/json; charset=utf-8');
    $nodo_ajax = $_POST['nodo'] ?? '';
    $accountMap = ['jalisco' => 'jalisco', 'sitelco' => 'sitelco', 'pampanito' => 'pampanito'];
    $ref = $accountMap[$nodo_ajax] ?? '';
    if (!$ref || !isset($WISPHUB_ACCOUNTS[$ref])) {
        echo json_encode(['ok' => false, 'error' => 'Nodo inválido']);
        exit;
    }
    $creds = $WISPHUB_ACCOUNTS[$ref];
    $client = new \Services\WispHubClient([
        'base_url'   => $creds['base_url'],
        'api_key'    => $creds['api_key'],
        'verify_ssl' => $creds['verify_ssl'] ?? false,
    ]);

    $duplicados = []; // Patron 1: padre + hija ambas pendientes
    $fantasmas   = []; // Patron 2: cobrado >= total pero estado Pendiente
    $sin_promesa = []; // Patron 3: factura hija sin promesa de pago

    // Obtener TODAS las facturas pendientes del mes actual paginando
    $todas_pendientes = [];
    $offset = 0; $limit = 100;
    while (true) {
        $page = $client->getInvoices(['estado' => 1, 'limit' => $limit, 'offset' => $offset]);
        foreach ($page as $inv) { $todas_pendientes[] = $inv; }
        if (count($page) < $limit) break;
        $offset += $limit;
    }

    // Indexar por id y construir mapa de "padre con hija"
    $por_id = [];
    $ids_con_hija = []; // id_padre => id_hija
    foreach ($todas_pendientes as $inv) {
        $id = $inv['id_factura'] ?? $inv['id'] ?? 0;
        if ($id) $por_id[$id] = $inv;
    }
    foreach ($todas_pendientes as $inv) {
        $id_hija = $inv['id_factura'] ?? $inv['id'] ?? 0;
        $arts = $inv['articulos'] ?? [];
        foreach ($arts as $art) {
            $desc = $art['descripcion'] ?? '';
            if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $desc, $m)) {
                $id_padre = (int)$m[1];
                $ids_con_hija[$id_padre] = $id_hija;
            }
        }
    }

    foreach ($todas_pendientes as $inv) {
        $id      = $inv['id_factura'] ?? $inv['id'] ?? 0;
        $total   = floatval($inv['total'] ?? 0);
        $cobrado = floatval($inv['total_cobrado'] ?? 0);
        $estado  = strtolower($inv['estado'] ?? '');
        $ref_inv = trim($inv['referencia'] ?? '');
        $cliente = $inv['cliente'] ?? '';
        if (is_array($cliente)) $cliente = $cliente['usuario'] ?? $cliente['nombre'] ?? '';
        $nombre_cliente = $inv['nombre'] ?? $inv['cliente']['nombre'] ?? '';
        $arts    = $inv['articulos'] ?? [];
        $es_hija = false;
        $padre_id = 0;
        $desc_art = '';
        foreach ($arts as $art) {
            $d = $art['descripcion'] ?? '';
            if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $d, $m2)) {
                $es_hija  = true;
                $padre_id = (int)$m2[1];
                $desc_art = $d;
                break;
            }
            if (!empty($d)) $desc_art = $d;
        }

        // Patron 1: Deuda Duplicada — padre e hija ambas pendientes
        if (!$es_hija && isset($ids_con_hija[$id]) && isset($por_id[$ids_con_hija[$id]])) {
            $id_hija_dup = $ids_con_hija[$id];
            $inv_hija = $por_id[$id_hija_dup];
            $duplicados[] = [
                'id_padre'  => $id,
                'id_hija'   => $id_hija_dup,
                'cliente'   => $cliente,
                'nombre'    => $nombre_cliente ?: ($inv_hija['nombre'] ?? ''),
                'total_padre' => $total,
                'total_hija'  => floatval($inv_hija['total'] ?? 0),
                'desc_padre'  => $desc_art ?: ("Factura #$id"),
                'desc_hija'   => implode('; ', array_column($inv_hija['articulos'] ?? [], 'descripcion')),
            ];
        }

        // Patron 2: Cobro Fantasma — cobrado >= total, estado Pendiente, referencia vacía
        if ($cobrado >= $total && $total > 0 && empty($ref_inv)
            && in_array($estado, ['pendiente de pago', 'pendiente', 'vencida', 'vencido'])) {
            $fantasmas[] = [
                'id'       => $id,
                'cliente'  => $cliente,
                'nombre'   => $nombre_cliente,
                'total'    => $total,
                'cobrado'  => $cobrado,
                'desc'     => $desc_art ?: ("Factura #$id"),
            ];
        }

        // Patron 3: Abono sin promesa — factura hija sin fecha de promesa
        if ($es_hija) {
            $tiene_promesa = !empty($inv['fecha_promesa']) || !empty($inv['promesa_pago']);
            // Si WispHub no expone el campo, consultamos detalle
            if (!$tiene_promesa) {
                $detalle = $client->getInvoiceDetail((string)$id);
                $tiene_promesa = !empty($detalle['fecha_promesa']) || !empty($detalle['promesa_pago'])
                    || !empty($detalle['promesas_pago']);
            }
            if (!$tiene_promesa) {
                $sin_promesa[] = [
                    'id'         => $id,
                    'id_padre'   => $padre_id,
                    'cliente'    => $cliente,
                    'nombre'     => $nombre_cliente,
                    'total'      => $total,
                    'cobrado'    => $cobrado,
                    'saldo'      => round($total - $cobrado, 2),
                    'desc'       => $desc_art,
                    'fecha_venc' => $inv['fecha_vencimiento'] ?? '',
                ];
            }
        }
    }

    echo json_encode([
        'ok'          => true,
        'duplicados'  => $duplicados,
        'fantasmas'   => $fantasmas,
        'sin_promesa' => $sin_promesa,
        'total_escaneadas' => count($todas_pendientes),
    ]);
    exit;
}

// ── Reparar error de abono ────────────────────────────────────────────────────
if ($accion === 'reparar_error') {
    header('Content-Type: application/json; charset=utf-8');
    $nodo_ajax = $_POST['nodo'] ?? '';
    $tipo      = $_POST['tipo'] ?? '';  // 'duplicado' | 'fantasma' | 'promesa'
    $accountMap = ['jalisco' => 'jalisco', 'sitelco' => 'sitelco', 'pampanito' => 'pampanito'];
    $ref = $accountMap[$nodo_ajax] ?? '';
    if (!$ref || !isset($WISPHUB_ACCOUNTS[$ref])) {
        echo json_encode(['ok' => false, 'error' => 'Nodo inválido']);
        exit;
    }
    $creds = $WISPHUB_ACCOUNTS[$ref];
    $client = new \Services\WispHubClient([
        'base_url'   => $creds['base_url'],
        'api_key'    => $creds['api_key'],
        'verify_ssl' => $creds['verify_ssl'] ?? false,
    ]);

    if ($tipo === 'duplicado') {
        // Anular la factura hija registrando un cobro igual a su total (la "cierra" como pagada en WispHub)
        $id_hija = (int)($_POST['id_hija'] ?? 0);
        if (!$id_hija) { echo json_encode(['ok' => false, 'error' => 'id_hija requerido']); exit; }
        $detalle = $client->getInvoiceDetail((string)$id_hija);
        $monto   = floatval($detalle['total'] ?? 0);
        if ($monto <= 0) { echo json_encode(['ok' => false, 'error' => 'No se pudo obtener el monto de la factura hija']); exit; }
        $result = $client->notifyPayment([
            'invoice_id'    => $id_hija,
            'total_cobrado' => $monto,
            'referencia'    => 'ANULACION-DUP-' . $id_hija,
            'fecha_pago'    => date('Y-m-d H:i'),
        ]);
        $ok = in_array($result['status'] ?? 0, [200, 201]);
        echo json_encode(['ok' => $ok, 'detalle' => $result['data'] ?? $result['error'] ?? '']);
        exit;
    }

    if ($tipo === 'fantasma') {
        // Registrar el pago fantasma en WispHub para cerrar la factura
        $id_factura = (int)($_POST['id_factura'] ?? 0);
        if (!$id_factura) { echo json_encode(['ok' => false, 'error' => 'id_factura requerido']); exit; }
        $detalle = $client->getInvoiceDetail((string)$id_factura);
        $monto   = floatval($detalle['total'] ?? 0);
        if ($monto <= 0) { echo json_encode(['ok' => false, 'error' => 'No se pudo obtener el monto']); exit; }
        $result = $client->notifyPayment([
            'invoice_id'    => $id_factura,
            'total_cobrado' => $monto,
            'referencia'    => 'CORREC-FANTASMA-' . $id_factura,
            'fecha_pago'    => date('Y-m-d H:i'),
        ]);
        $ok = in_array($result['status'] ?? 0, [200, 201]);
        echo json_encode(['ok' => $ok, 'detalle' => $result['data'] ?? $result['error'] ?? '']);
        exit;
    }

    if ($tipo === 'promesa') {
        // Agregar promesa de pago a la factura hija
        $id_factura  = (int)($_POST['id_factura'] ?? 0);
        $fecha_limite = trim($_POST['fecha_limite'] ?? '');
        if (!$id_factura || !$fecha_limite) {
            echo json_encode(['ok' => false, 'error' => 'id_factura y fecha_limite son requeridos']);
            exit;
        }
        $detalle = $client->getInvoiceDetail((string)$id_factura);
        $monto   = round(floatval($detalle['total'] ?? 0) - floatval($detalle['total_cobrado'] ?? 0), 2);
        if ($monto <= 0) $monto = floatval($detalle['total'] ?? 1);
        // WispHub acepta fecha en formato YYYY/MM/DD
        $fecha_wisp = str_replace('-', '/', $fecha_limite);
        $result = $client->addPaymentPromise($id_factura, $fecha_wisp, $monto, 1);
        $ok = in_array($result['status'] ?? 0, [200, 201]);
        echo json_encode(['ok' => $ok, 'detalle' => $result['data'] ?? $result['error'] ?? '']);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Tipo de reparación desconocido']);
    exit;
}

if ($accion === 'generar_factura' || $accion === 'generar_masiva') {
    header('Content-Type: application/json; charset=utf-8');

    $nodo_ajax = $_POST['nodo'] ?? '';
    $accountMap = ['jalisco' => 'jalisco', 'sitelco' => 'sitelco', 'pampanito' => 'pampanito'];
    $ref = $accountMap[$nodo_ajax] ?? '';

    if (!$ref || !isset($WISPHUB_ACCOUNTS[$ref])) {
        echo json_encode(['ok' => false, 'error' => 'Nodo inválido']);
        exit;
    }

    $creds  = $WISPHUB_ACCOUNTS[$ref];
    $client = new \Services\WispHubClient([
        'base_url'   => $creds['base_url'],
        'api_key'    => $creds['api_key'],
        'verify_ssl' => $creds['verify_ssl'] ?? false,
    ]);

    // Fecha vencimiento: último día del mes actual
    $ultimo_dia = date('Y-m-t');
    $mes_label  = date('F Y');

    /**
     * Genera una factura para un cliente dado sus datos de la API de WispHub.
     *
     * La API de WispHub (listClients) devuelve:
     *   - 'usuario'     → username para crear factura (ej. "user@empresa")
     *   - 'precio_plan' → precio del plan como string (ej. "20.00")
     *   - 'nombre'      → nombre visible del cliente
     *
     * Si esos campos no vienen en $c (ej. al llamar desde generar_factura con solo el svc_id),
     * se obtienen usando getServiceProfile (que sí tiene 'usuario') y getServiceDetail.
     */
    function generarFacturaCliente(\Services\WispHubClient $client, array $c, string $ultimo_dia, string $mes_label): array {
        $svc_id   = (string)($c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '');
        $username = $c['usuario'] ?? $c['username'] ?? '';
        $nombre   = $c['nombre'] ?? 'Sin nombre';

        // 1. Precio del plan: listClients lo expone como 'precio_plan' (string)
        $precio = floatval($c['precio_plan'] ?? 0);

        // 2. Fallback: plan_internet array con precio/costo (algunos endpoints)
        if ($precio <= 0 && !empty($c['plan_internet']) && is_array($c['plan_internet'])) {
            $precio = floatval($c['plan_internet']['precio'] ?? $c['plan_internet']['costo'] ?? 0);
        }

        // 3. Si aún no tenemos precio, consultar perfil del servicio
        if ($precio <= 0 && $svc_id) {
            $perfil = $client->getServiceProfile($svc_id);
            // getServiceProfile devuelve los datos directamente en ['data']
            // (no en ['data']['data'])
            if ($perfil['status'] === 200 && !empty($perfil['data'])) {
                // El perfil no tiene precio_plan directamente; buscamos en detail
                $det = $client->getServiceDetail($svc_id);
                if ($det['status'] === 200) {
                    // getServiceDetail para jalisco no expone precio; intentamos listClients filtrado
                    $r = $client->listClients(['id_servicio' => $svc_id, 'limit' => 1]);
                    if ($r['status'] === 200 && !empty($r['data']['results'][0])) {
                        $precio = floatval($r['data']['results'][0]['precio_plan'] ?? 0);
                        if (empty($username)) {
                            $username = $r['data']['results'][0]['usuario'] ?? '';
                        }
                        if ($nombre === 'Sin nombre') {
                            $nombre = $r['data']['results'][0]['nombre'] ?? $nombre;
                        }
                    }
                }
            }
        }

        // 4. Si el usuario no vino en $c, obtenerlo del perfil
        if (empty($username) && $svc_id) {
            $perfil = $client->getServiceProfile($svc_id);
            if ($perfil['status'] === 200) {
                $username = $perfil['data']['usuario'] ?? '';
            }
        }

        if ($precio <= 0) {
            return ['ok' => false, 'svc_id' => $svc_id, 'nombre' => $nombre, 'error' => 'No se pudo obtener el precio del plan'];
        }
        if (empty($username)) {
            return ['ok' => false, 'svc_id' => $svc_id, 'nombre' => $nombre, 'error' => 'Usuario de WispHub no encontrado'];
        }

        $descripcion = "Servicio de Internet - $mes_label";
        $result = $client->createInvoice(
            $username,
            $precio,
            $descripcion,
            $ultimo_dia,  // fecha_vencimiento
            $svc_id,
            1             // tipo_factura = 1 (Recurrente/Internet)
        );

        if (in_array($result['status'], [200, 201], true)) {
            // WispHub devuelve: {"messages": "Se genero correctamente el id 12345"}
            $msg = $result['data']['messages'] ?? $result['data']['id'] ?? '';
            $factura_id = null;
            if (is_string($msg) && preg_match('/\b(\d+)\b/', $msg, $m)) {
                $factura_id = (int)$m[1];
            } elseif (is_int($msg) || is_numeric($msg)) {
                $factura_id = (int)$msg;
            }
            return ['ok' => true, 'svc_id' => $svc_id, 'nombre' => $nombre, 'monto' => $precio, 'factura_id' => $factura_id];
        }

        $err_msg = $result['data']['message'] ?? $result['data']['detail'] ?? ($result['error'] ?? 'Error desconocido');
        if (is_array($err_msg)) $err_msg = json_encode($err_msg);
        return ['ok' => false, 'svc_id' => $svc_id, 'nombre' => $nombre, 'error' => $err_msg];
    }

    // ── Generar una sola factura ─────────────────────────────
    if ($accion === 'generar_factura') {
        $svc_id_target = (string)($_POST['svc_id'] ?? '');
        if (!$svc_id_target) {
            echo json_encode(['ok' => false, 'error' => 'svc_id requerido']);
            exit;
        }
        // Obtener datos del cliente usando listClients (contiene precio_plan y usuario)
        $r = $client->listClients(['id_servicio' => $svc_id_target, 'limit' => 1]);
        if ($r['status'] !== 200 || empty($r['data']['results'][0])) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo obtener datos del servicio ' . $svc_id_target]);
            exit;
        }
        $res = generarFacturaCliente($client, $r['data']['results'][0], $ultimo_dia, $mes_label);
        echo json_encode($res);
        exit;
    }

    // ── Generar facturas masivamente ─────────────────────────
    if ($accion === 'generar_masiva') {
        $svc_ids_raw = $_POST['svc_ids'] ?? '';
        $svc_ids = json_decode($svc_ids_raw, true);
        if (empty($svc_ids) || !is_array($svc_ids)) {
            echo json_encode(['ok' => false, 'error' => 'Lista de svc_ids vacía o inválida']);
            exit;
        }

        $resultados_masivo = [];
        foreach ($svc_ids as $svc_id) {
            $r = $client->listClients(['id_servicio' => (string)$svc_id, 'limit' => 1]);
            if ($r['status'] !== 200 || empty($r['data']['results'][0])) {
                $resultados_masivo[] = ['ok' => false, 'svc_id' => $svc_id, 'nombre' => '?', 'error' => 'No se pudo obtener datos del servicio'];
                continue;
            }
            $resultados_masivo[] = generarFacturaCliente($client, $r['data']['results'][0], $ultimo_dia, $mes_label);
            usleep(200000); // 200ms entre requests para no saturar la API
        }

        $ok_count  = count(array_filter($resultados_masivo, fn($r) => $r['ok']));
        $err_count = count($resultados_masivo) - $ok_count;
        echo json_encode([
            'ok'        => $err_count === 0,
            'total'     => count($resultados_masivo),
            'ok_count'  => $ok_count,
            'err_count' => $err_count,
            'detalles'  => $resultados_masivo,
        ]);
        exit;
    }
}

// ─── Lógica de conciliación (GET / POST normal) ───────────────────────────────
$nodo     = $_POST['nodo'] ?? '';
$resultados = null;
$error      = '';
$mes_actual = date('Y-m');
$primer_dia = $mes_actual . '-01';
$ultimo_dia = date('Y-m-t', strtotime($primer_dia));

function extraer_servicio_id(array $inv): string {
    $svc = $inv['id_servicio'] ?? '';
    if (empty($svc) && !empty($inv['articulos']) && is_array($inv['articulos'])) {
        foreach ($inv['articulos'] as $art) {
            $s = $art['servicio']['id_servicio'] ?? '';
            if (!empty($s)) { $svc = $s; break; }
        }
    }
    return (string)$svc;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $nodo) {
    $accountMap = ['jalisco' => 'jalisco', 'sitelco' => 'sitelco', 'pampanito' => 'pampanito'];
    $ref = $accountMap[$nodo] ?? '';

    if ($ref && isset($WISPHUB_ACCOUNTS[$ref])) {
        $creds  = $WISPHUB_ACCOUNTS[$ref];
        $client = new \Services\WispHubClient([
            'base_url'   => $creds['base_url'],
            'api_key'    => $creds['api_key'],
            'verify_ssl' => $creds['verify_ssl'] ?? false,
        ]);

        try {
            // 1. Servicios que YA tienen factura con vencimiento este mes
            $facturados = [];
            $offset = 0; $limit = 100;
            while (true) {
                $page_inv = $client->getInvoices([
                    'fecha_vencimiento__range_0' => $primer_dia,
                    'fecha_vencimiento__range_1' => $ultimo_dia,
                    'limit' => $limit, 'offset' => $offset,
                ]);
                foreach ($page_inv as $inv) {
                    $s = extraer_servicio_id($inv);
                    if ($s) {
                        // Guardamos el estado de la factura (ej: "Pagada", "Pendiente de Pago")
                        $estado = $inv['estado'] ?? 'Desconocido';
                        // Normalizar estado
                        if (strtolower($estado) === 'pagada' || strtolower($estado) === 'pagado') {
                            $facturados[$s] = 'pagado';
                        } else {
                            $facturados[$s] = 'pendiente';
                        }
                    }
                }
                if (count($page_inv) < $limit) break;
                $offset += $limit;
            }

            // 2. Todos los clientes activos y su clasificación
            $todos_activos = [];
            $page = 1;
            while (true) {
                $res = $client->listClients(['estado' => 1, 'limit' => 100, 'page' => $page]);
                if ($res['status'] !== 200) {
                    $error = "Error al obtener clientes: " . ($res['error'] ?? 'Desconocido');
                    break;
                }
                $clients = $res['data']['results'] ?? [];
                if (empty($clients)) break;
                foreach ($clients as $c) {
                    $svc = (string)($c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '');
                    if ($svc) {
                        if (isset($facturados[$svc])) {
                            $c['_estado_local'] = $facturados[$svc]; // 'pagado' o 'pendiente'
                        } else {
                            $c['_estado_local'] = 'sin_factura';
                        }
                        $todos_activos[] = $c;
                    }
                }
                $cp = $res['data']['current_page'] ?? 1;
                $lp = $res['data']['last_page']    ?? 1;
                if ($cp >= $lp) break;
                $page++;
            }

            if (!$error) $resultados = $todos_activos;

        } catch (\Exception $e) {
            $error = "Excepción: " . $e->getMessage();
        }
    } else {
        $error = "Nodo inválido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Depuración: Clientes Activos sin Factura</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
        .glass-panel { background: rgba(30,41,59,0.7); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; }
        .table-dark { --bs-table-bg: rgba(15,23,42,0.6); --bs-table-border-color: #334155; color: #e2e8f0; }
        .table-dark th { background: rgba(30,41,59,0.9); color: #94a3b8; }
        .table-dark td { vertical-align: middle; }
        .table-dark tr:hover td { background: rgba(59,130,246,0.07); }
        .btn-premium { background: linear-gradient(135deg,#3b82f6,#6366f1); color:#fff; border:none; }
        .btn-premium:hover { background: linear-gradient(135deg,#2563eb,#4f46e5); color:#fff; }
        .btn-generar { background: linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; font-size:.78rem; padding:4px 12px; border-radius:8px; }
        .btn-generar:hover { background: linear-gradient(135deg,#059669,#047857); color:#fff; }
        .btn-masiva  { background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; }
        .btn-masiva:hover  { background: linear-gradient(135deg,#d97706,#b45309); color:#fff; }
        select.form-select { background:#1e293b; color:#fff; border:1px solid #334155; }
        .estado-cel { min-width: 130px; }
        .badge-ok   { background: rgba(16,185,129,.15); color:#10b981; border:1px solid rgba(16,185,129,.3); }
        .badge-err  { background: rgba(239,68,68,.15);  color:#ef4444; border:1px solid rgba(239,68,68,.3); }
        #resumenMasivo { display:none; }
        
        /* Estilos para los tabs/filtros */
        .tab-btn { background: rgba(30,41,59,0.8); border: 1px solid rgba(255,255,255,0.05); color: #94a3b8; transition: all 0.2s; border-radius: 8px; font-weight: 500; }
        .tab-btn:hover { background: rgba(51,65,85,0.8); color: #fff; }
        .tab-btn.active-sin { background: rgba(239,68,68,0.2); color: #ef4444; border-color: rgba(239,68,68,0.4); }
        .tab-btn.active-pag { background: rgba(16,185,129,0.2); color: #10b981; border-color: rgba(16,185,129,0.4); }
        .tab-btn.active-pen { background: rgba(245,158,11,0.2); color: #f59e0b; border-color: rgba(245,158,11,0.4); }
        /* Escáner de errores de abono */
        .scanner-section { border: 1px solid rgba(245,158,11,0.25); border-radius: 14px; background: rgba(20,30,50,0.7); margin-bottom: 2rem; padding: 24px; }
        .scanner-section h5 { color: #f59e0b; }
        .btn-scan { background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; border-radius:10px; font-weight:600; padding:10px 28px; }
        .btn-scan:hover { background: linear-gradient(135deg,#d97706,#b45309); color:#fff; }
        .error-tabs .etab { background: rgba(30,41,59,0.9); border: 1px solid rgba(255,255,255,0.06); color: #94a3b8; border-radius: 8px; padding: 6px 16px; cursor:pointer; transition: all .2s; font-size:.9rem; }
        .error-tabs .etab:hover { background: rgba(51,65,85,0.9); color:#fff; }
        .error-tabs .etab.active-dup { background: rgba(239,68,68,0.18); color:#ef4444; border-color:rgba(239,68,68,0.35); }
        .error-tabs .etab.active-fan { background: rgba(245,158,11,0.18); color:#f59e0b; border-color:rgba(245,158,11,0.35); }
        .error-tabs .etab.active-pro { background: rgba(99,102,241,0.18); color:#a5b4fc; border-color:rgba(99,102,241,0.35); }
        .btn-fix { font-size:.78rem; border:none; border-radius:7px; padding:4px 12px; font-weight:600; cursor:pointer; transition:all .2s; }
        .btn-fix-dup { background:rgba(239,68,68,.15); color:#ef4444; border:1px solid rgba(239,68,68,.3); }
        .btn-fix-dup:hover { background:rgba(239,68,68,.3); color:#fff; }
        .btn-fix-fan { background:rgba(245,158,11,.15); color:#f59e0b; border:1px solid rgba(245,158,11,.3); }
        .btn-fix-fan:hover { background:rgba(245,158,11,.3); color:#fff; }
        .btn-fix-pro { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }
        .btn-fix-pro:hover { background:rgba(99,102,241,.3); color:#fff; }
        .scanner-empty { text-align:center; padding: 2rem; color: #10b981; font-size:1.05rem; }
    </style>
</head>
<body>
<div class="container my-4">
    <h2 class="fw-bold mb-4 text-center text-primary"><i class="fas fa-search-dollar me-2"></i> Conciliación de Facturación</h2>

    <!-- Formulario de búsqueda -->
    <div class="glass-panel mb-4 mx-auto" style="max-width:600px;">
        <p class="text-muted small text-center mb-4">
            Detecta clientes <strong>Activos</strong> sin factura en el mes en curso (<strong><?php echo date('F Y'); ?></strong>)
            y genera las facturas faltantes desde aquí.
        </p>
        <form method="POST" id="depuracionForm">
            <div class="mb-3">
                <label class="form-label text-light fw-bold">Seleccionar Nodo</label>
                <select name="nodo" id="selectNodo" class="form-select" required>
                    <option value="">-- Elija un nodo --</option>
                    <option value="sitelco"   <?php echo $nodo==='sitelco'   ? 'selected':''; ?>>Sitelco</option>
                    <option value="jalisco"   <?php echo $nodo==='jalisco'   ? 'selected':''; ?>>Jalisco</option>
                    <option value="pampanito" <?php echo $nodo==='pampanito' ? 'selected':''; ?>>Pampanito</option>
                </select>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-premium py-2 fw-bold" id="btnBuscar">
                    <i class="fas fa-search me-2"></i> Conciliar Activos sin Factura
                </button>
            </div>
        </form>
    </div>

    <!-- ═══ ESCÁNER DE ERRORES DE ABONO ═══ -->
    <div class="scanner-section mx-auto" style="max-width:900px;" id="scannerSection">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0"><i class="fas fa-heartbeat me-2"></i> Errores de Abono</h5>
                <small class="text-muted">Detecta y repara desincronizaciones por pagos parciales</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select id="scannerNodo" class="form-select form-select-sm" style="width:auto;background:#1e293b;color:#fff;border:1px solid #334155;">
                    <option value="">-- Nodo --</option>
                    <option value="sitelco">Sitelco</option>
                    <option value="jalisco">Jalisco</option>
                    <option value="pampanito">Pampanito</option>
                </select>
                <button class="btn btn-scan" id="btnScanear" onclick="escanearErrores()">
                    <i class="fas fa-radar me-2"></i> Escanear
                </button>
            </div>
        </div>

        <div id="scannerStatus" class="text-muted small mb-2" style="display:none;"></div>

        <!-- Sub-tabs de error -->
        <div class="error-tabs d-flex gap-2 mb-3" id="errorTabs" style="display:none !important;">
            <button class="etab active-dup" onclick="mostrarErrorTab('duplicados', this)" id="tabDup">
                <i class="fas fa-copy me-1"></i> Deuda Duplicada <span class="badge bg-danger ms-1" id="cntDup">0</span>
            </button>
            <button class="etab active-fan" onclick="mostrarErrorTab('fantasmas', this)" id="tabFan">
                <i class="fas fa-ghost me-1"></i> Cobro Fantasma <span class="badge bg-warning text-dark ms-1" id="cntFan">0</span>
            </button>
            <button class="etab active-pro" onclick="mostrarErrorTab('sin_promesa', this)" id="tabPro">
                <i class="fas fa-clock me-1"></i> Sin Promesa <span class="badge ms-1" style="background:#6366f1;" id="cntPro">0</span>
            </button>
        </div>

        <!-- Panel de resultados de errores -->
        <div id="scannerResultados"></div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger mx-auto" style="max-width:600px;">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($resultados !== null): ?>
    <?php
        $count_sin = 0; $count_pag = 0; $count_pen = 0;
        foreach($resultados as $c) {
            $e = $c['_estado_local'];
            if ($e === 'sin_factura') $count_sin++;
            elseif ($e === 'pagado') $count_pag++;
            elseif ($e === 'pendiente') $count_pen++;
        }
    ?>
    <div class="glass-panel">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-0 text-white">Nodo: <?php echo ucfirst($nodo); ?></h5>
                <small class="text-muted">Estado de facturación (<?php echo date('F Y'); ?>)</small>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button class="btn tab-btn active-sin px-3" onclick="filtrarTabla('sin_factura', this)">
                    <i class="fas fa-exclamation-circle me-1"></i> Sin Factura <span class="badge bg-danger ms-1"><?php echo $count_sin; ?></span>
                </button>
                <button class="btn tab-btn px-3" onclick="filtrarTabla('pendiente', this)">
                    <i class="fas fa-clock me-1"></i> Pendientes <span class="badge bg-warning text-dark ms-1"><?php echo $count_pen; ?></span>
                </button>
                <button class="btn tab-btn px-3" onclick="filtrarTabla('pagado', this)">
                    <i class="fas fa-check-circle me-1"></i> Pagados <span class="badge bg-success ms-1"><?php echo $count_pag; ?></span>
                </button>
                
                <div class="ms-3" id="generarMasivaContainer" style="display: <?php echo $count_sin > 0 ? 'block' : 'none'; ?>;">
                    <button id="btnGenerarTodas" class="btn btn-masiva fw-bold px-4"
                            onclick="generarMasiva()">
                        <i class="fas fa-bolt me-2"></i> Generar Faltantes
                    </button>
                </div>
            </div>
        </div>

        <!-- Resumen masivo -->
        <div id="resumenMasivo" class="alert mb-3" role="alert"></div>

        <!-- Buscador -->
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fas fa-search"></i></span>
                <input type="text" id="buscador-tabla" class="form-control bg-dark text-white border-secondary" placeholder="Buscar por cliente, cédula, servicio, plan o IP...">
            </div>
        </div>

        <?php if (count($resultados) > 0): ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th># Servicio</th>
                        <th>Cliente</th>
                        <th>Cédula / Usuario</th>
                        <th>Plan / Precio</th>
                        <th>IP / Router</th>
                        <th class="text-center">Acción</th>
                        <th class="text-center estado-cel">Estado</th>
                    </tr>
                </thead>
                <tbody id="tablaResultados">
                    <?php foreach ($resultados as $c):
                        $svc    = $c['id_servicio'] ?? $c['id'] ?? $c['service_id'] ?? '-';
                        $nombre = $c['nombre'] ?? '-';
                        $ident  = $c['cedula'] ?? $c['usuario'] ?? '-';
                        $plan   = $c['plan_internet'] ?? '-';
                        if (is_array($plan)) {
                            $precio_plan = floatval($plan['precio'] ?? $plan['costo'] ?? 0);
                            $nombre_plan = $plan['nombre'] ?? '-';
                            $plan_str    = $nombre_plan . ($precio_plan > 0 ? " ($" . number_format($precio_plan,2) . ")" : '');
                        } else {
                            $plan_str = is_string($plan) ? $plan : '-';
                        }
                        $ip     = $c['ip'] ?? '-';
                        $router = $c['router'] ?? '-';
                        if (is_array($router)) $router = $router['nombre'] ?? '-';
                        $estado_local = $c['_estado_local']; // 'sin_factura', 'pagado', 'pendiente'
                    ?>
                    <tr id="fila-<?php echo htmlspecialchars($svc); ?>" class="fila-cliente" data-estado="<?php echo $estado_local; ?>" style="<?php echo $estado_local !== 'sin_factura' ? 'display:none;' : ''; ?>">
                        <td class="fw-bold text-primary"><?php echo htmlspecialchars($svc); ?></td>
                        <td style="color:#e2e8f0;"><?php echo htmlspecialchars($nombre); ?></td>
                        <td style="color:#e2e8f0;"><?php echo htmlspecialchars($ident); ?></td>
                        <td style="color:#e2e8f0;"><small><?php echo htmlspecialchars($plan_str); ?></small></td>
                        <td style="color:#e2e8f0;">
                            <small><?php echo htmlspecialchars($ip); ?><br>
                            <span style="color:#94a3b8;"><?php echo htmlspecialchars($router); ?></span></small>
                        </td>
                        <td class="text-center">
                            <?php if ($estado_local === 'sin_factura'): ?>
                            <button class="btn btn-generar"
                                    onclick="abrirModalConfirm(
                                        '<?php echo htmlspecialchars($svc); ?>',
                                        '<?php echo htmlspecialchars(addslashes($nombre)); ?>',
                                        '<?php echo htmlspecialchars(addslashes($plan_str)); ?>',
                                        this
                                    )">
                                <i class="fas fa-file-invoice me-1"></i> Generar
                            </button>
                            <?php else: ?>
                                <span class="text-muted"><i class="fas fa-ban"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center estado-cel" id="estado-<?php echo htmlspecialchars($svc); ?>">
                            <?php if ($estado_local === 'pagado'): ?>
                                <span class="badge" style="background:rgba(16,185,129,.15);color:#10b981;"><i class="fas fa-check-circle me-1"></i>Pagada</span>
                            <?php elseif ($estado_local === 'pendiente'): ?>
                                <span class="badge" style="background:rgba(245,158,11,.15);color:#f59e0b;"><i class="fas fa-clock me-1"></i>Pendiente</span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(100,116,139,.2);color:#94a3b8;">Sin Factura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <div style="font-size:3rem;">🎉</div>
            <h4 class="text-white mt-2">¡Todo en orden!</h4>
            <p class="text-muted">Todos los clientes activos tienen su factura de <?php echo date('F Y'); ?> generada.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmación individual -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,23,42,0.97);border:1.5px solid rgba(59,130,246,0.35);border-radius:16px;color:#e2e8f0;">
            <div class="modal-header" style="border-bottom:1px solid #334155;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:42px;height:42px;border-radius:12px;background:rgba(59,130,246,0.12);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#3b82f6;">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Confirmar Generación</h5>
                        <small style="color:#94a3b8;">Esta acción creará la factura en WispHub</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-1" style="color:#94a3b8;font-size:.85rem;">CLIENTE</p>
                <p class="fw-bold fs-6 mb-3" id="modalNombre" style="color:#e2e8f0;">—</p>
                <p class="mb-1" style="color:#94a3b8;font-size:.85rem;">PLAN / PRECIO</p>
                <p class="fw-bold mb-3" id="modalPlan" style="color:#3b82f6;">—</p>
                <p class="mb-1" style="color:#94a3b8;font-size:.85rem;"># SERVICIO</p>
                <p class="fw-bold mb-0" id="modalSvc" style="color:#e2e8f0;">—</p>
            </div>
            <div class="modal-footer" style="border-top:1px solid #334155;gap:10px;">
                <button type="button" class="btn px-4" data-bs-dismiss="modal"
                        style="background:rgba(100,116,139,.15);color:#94a3b8;border:1px solid #334155;border-radius:10px;">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="button" id="btnConfirmarGenerar" class="btn fw-bold px-4"
                        style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:10px;">
                    <i class="fas fa-file-invoice me-2"></i> Confirmar y Generar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
const NODO   = <?php echo json_encode($nodo); ?>;
const ULTIMO = <?php echo json_encode($ultimo_dia ?? date('Y-m-t')); ?>;

// IDs de todos los servicios de la tabla (solo los que NO tienen factura)
const todosLosSvcIds = Array.from(document.querySelectorAll('tr.fila-cliente[data-estado="sin_factura"]')).map(tr => tr.id.replace('fila-', ''));

let estadoActivo = 'sin_factura';

function filtrarTabla(estado, btn) {
    estadoActivo = estado;
    // 1. Quitar la clase active de todos los botones
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active-sin', 'active-pag', 'active-pen');
    });
    
    // 2. Agregar la clase active al botón clickeado
    let activeClass = 'active-sin';
    if (estado === 'pagado') activeClass = 'active-pag';
    if (estado === 'pendiente') activeClass = 'active-pen';
    btn.classList.add(activeClass);

    // 3. Mostrar/ocultar filas (aplicando también el buscador si hay texto)
    let count = 0;
    const term = document.getElementById('buscador-tabla')?.value.toLowerCase() || '';
    
    document.querySelectorAll('tr.fila-cliente').forEach(tr => {
        if (tr.dataset.estado === estado) {
            if (term && !tr.textContent.toLowerCase().includes(term)) {
                tr.style.display = 'none';
            } else {
                tr.style.display = '';
                count++;
            }
        } else {
            tr.style.display = 'none';
        }
    });
    
    // 4. Mostrar/ocultar el botón masivo y mensajes de vacío
    const contenedorMasiva = document.getElementById('generarMasivaContainer');
    if (estado === 'sin_factura' && count > 0) {
        contenedorMasiva.style.display = 'block';
    } else {
        contenedorMasiva.style.display = 'none';
    }
}

function setEstado(svcId, html) {
    const el = document.getElementById('estado-' + svcId);
    if (el) el.innerHTML = html;
}

// ── Estado pendiente del modal ────────────────────────────────────────────────
let _pendingSvcId  = null;
let _pendingBtn    = null;

function abrirModalConfirm(svcId, nombre, plan, btn) {
    _pendingSvcId = svcId;
    _pendingBtn   = btn;
    document.getElementById('modalNombre').textContent = nombre;
    document.getElementById('modalPlan').textContent   = plan || '—';
    document.getElementById('modalSvc').textContent    = '# ' + svcId;
    const modal = new bootstrap.Modal(document.getElementById('modalConfirm'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btnConfirmarGenerar').addEventListener('click', function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirm'));
        if (modal) modal.hide();
        if (_pendingSvcId && _pendingBtn) {
            generarUna(_pendingSvcId, _pendingBtn);
            _pendingSvcId = null;
            _pendingBtn   = null;
        }
    });

    const buscador = document.getElementById('buscador-tabla');
    if (buscador) {
        buscador.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('tr.fila-cliente').forEach(row => {
                if (row.dataset.estado === estadoActivo) {
                    if (term && !row.textContent.toLowerCase().includes(term)) {
                        row.style.display = 'none';
                    } else {
                        row.style.display = '';
                    }
                }
            });
        });
    }
});

// ── Generar una sola factura ──────────────────────────────────────────────────
async function generarUna(svcId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
    setEstado(svcId, '<span class="badge bg-secondary">Generando...</span>');

    try {
        const fd = new FormData();
        fd.append('accion',  'generar_factura');
        fd.append('nodo',    NODO);
        fd.append('svc_id',  svcId);

        const r   = await fetch('', { method: 'POST', body: fd });
        const res = await r.json();

        if (res.ok) {
            btn.closest('tr').style.opacity = '0.5';
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Generada';
            btn.style.background = 'rgba(16,185,129,.2)';
            btn.style.color      = '#10b981';
            setEstado(svcId,
                `<span class="badge badge-ok"><i class="fas fa-check-circle me-1"></i>OK $${parseFloat(res.monto||0).toFixed(2)}</span>`);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-invoice me-1"></i> Generar';
            setEstado(svcId,
                `<span class="badge badge-err" title="${esc(res.error)}"><i class="fas fa-times-circle me-1"></i>Error</span>`);
        }
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-invoice me-1"></i> Generar';
        setEstado(svcId, '<span class="badge badge-err">Red error</span>');
    }
}

// ── Generar todas masivamente ─────────────────────────────────────────────────
async function generarMasiva() {
    const btn = document.getElementById('btnGenerarTodas');
    if (!confirm(`¿Generar facturas para los ${todosLosSvcIds.length} clientes listados?`)) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Generando...';

    const resumenEl = document.getElementById('resumenMasivo');
    resumenEl.style.display = 'block';
    resumenEl.className     = 'alert alert-secondary mb-3';
    resumenEl.textContent   = 'Enviando solicitudes a WispHub, espere...';

    // Marcar todo como "generando"
    todosLosSvcIds.forEach(id => {
        setEstado(id, '<span class="badge bg-secondary">Generando...</span>');
        const filaBtn = document.querySelector(`#fila-${id} .btn-generar`);
        if (filaBtn) { filaBtn.disabled = true; filaBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    });

    try {
        const fd = new FormData();
        fd.append('accion',   'generar_masiva');
        fd.append('nodo',     NODO);
        fd.append('svc_ids',  JSON.stringify(todosLosSvcIds));

        const r   = await fetch('', { method: 'POST', body: fd });
        const res = await r.json();

        // Actualizar estado por fila
        (res.detalles || []).forEach(d => {
            const id = String(d.svc_id);
            const filaBtn = document.querySelector(`#fila-${id} .btn-generar`);
            if (d.ok) {
                if (filaBtn) {
                    filaBtn.innerHTML = '<i class="fas fa-check me-1"></i>Generada';
                    filaBtn.style.background = 'rgba(16,185,129,.2)';
                    filaBtn.style.color      = '#10b981';
                }
                const fila = document.getElementById('fila-' + id);
                if (fila) fila.style.opacity = '0.5';
                setEstado(id, `<span class="badge badge-ok"><i class="fas fa-check-circle me-1"></i>OK $${parseFloat(d.monto||0).toFixed(2)}</span>`);
            } else {
                if (filaBtn) { filaBtn.disabled = false; filaBtn.innerHTML = '<i class="fas fa-file-invoice me-1"></i> Reintentar'; }
                setEstado(id, `<span class="badge badge-err" title="${esc(d.error)}"><i class="fas fa-times-circle me-1"></i>Error</span>`);
            }
        });

        const ok  = res.ok_count  || 0;
        const err = res.err_count || 0;
        resumenEl.className = err === 0 ? 'alert alert-success mb-3' : 'alert alert-warning mb-3';
        resumenEl.innerHTML = `<strong>${ok} facturas generadas correctamente.</strong>` +
            (err > 0 ? ` <span class="text-danger">${err} con error (revisa la columna Estado).</span>` : '');

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-bolt me-2"></i> Generar Todas las Facturas';

    } catch(e) {
        resumenEl.className   = 'alert alert-danger mb-3';
        resumenEl.textContent = 'Error de red: ' + e.message;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-bolt me-2"></i> Generar Todas las Facturas';
    }
}

function esc(s) { return String(s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

// Spinner al buscar
document.getElementById('depuracionForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnBuscar');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Procesando...';
    btn.disabled  = true;
});

// ── Escáner de Errores de Abono ───────────────────────────────────────────────
let _scanData = null;
let _scanNodo = '';
let _tabActiva = 'duplicados';

async function escanearErrores() {
    const nodo = document.getElementById('scannerNodo').value;
    if (!nodo) { alert('Selecciona un nodo primero.'); return; }
    _scanNodo = nodo;

    const btn = document.getElementById('btnScanear');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Escaneando...';

    const status = document.getElementById('scannerStatus');
    status.style.display = 'block';
    status.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Consultando facturas pendientes en WispHub...';

    document.getElementById('scannerResultados').innerHTML = '';
    document.getElementById('errorTabs').style.setProperty('display', 'none', 'important');

    try {
        const fd = new FormData();
        fd.append('accion', 'escanear_errores');
        fd.append('nodo', nodo);
        const r = await fetch('', { method: 'POST', body: fd });
        const res = await r.json();

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-radar me-2"></i> Escanear';

        if (!res.ok) {
            status.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>${esc(res.error)}</span>`;
            return;
        }

        _scanData = res;
        const total = (res.duplicados||[]).length + (res.fantasmas||[]).length + (res.sin_promesa||[]).length;
        status.innerHTML = `<i class="fas fa-check-circle me-1 text-success"></i> Escaneadas <strong>${res.total_escaneadas}</strong> facturas pendientes. <strong>${total}</strong> error(es) detectado(s).`;

        // Actualizar contadores tabs
        document.getElementById('cntDup').textContent = (res.duplicados||[]).length;
        document.getElementById('cntFan').textContent = (res.fantasmas||[]).length;
        document.getElementById('cntPro').textContent = (res.sin_promesa||[]).length;

        if (total === 0) {
            document.getElementById('scannerResultados').innerHTML =
                '<div class="scanner-empty"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i>¡Sin errores detectados! Todo en orden.</div>';
            return;
        }

        document.getElementById('errorTabs').style.removeProperty('display');

        // Mostrar primera tab con errores
        if ((res.duplicados||[]).length > 0) mostrarErrorTab('duplicados', document.getElementById('tabDup'));
        else if ((res.fantasmas||[]).length > 0) mostrarErrorTab('fantasmas', document.getElementById('tabFan'));
        else mostrarErrorTab('sin_promesa', document.getElementById('tabPro'));

    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-radar me-2"></i> Escanear';
        status.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Error de red: ${esc(e.message)}</span>`;
    }
}

function mostrarErrorTab(tipo, btn) {
    _tabActiva = tipo;
    document.querySelectorAll('.error-tabs .etab').forEach(b => {
        b.classList.remove('active-dup','active-fan','active-pro');
    });
    if (tipo === 'duplicados')  btn.classList.add('active-dup');
    if (tipo === 'fantasmas')   btn.classList.add('active-fan');
    if (tipo === 'sin_promesa') btn.classList.add('active-pro');

    const contenedor = document.getElementById('scannerResultados');
    if (!_scanData) { contenedor.innerHTML = ''; return; }

    const items = _scanData[tipo] || [];
    if (items.length === 0) {
        contenedor.innerHTML = '<div class="scanner-empty"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i>Sin errores en esta categoría.</div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-dark table-hover mb-0"><thead><tr>';
    if (tipo === 'duplicados') {
        html += '<th>Cliente</th><th>Factura Original</th><th>Factura Duplicada</th><th>Monto Hija</th><th class="text-center">Acción</th>';
        html += '</tr></thead><tbody>';
        items.forEach((d, i) => {
            html += `<tr>
                <td><span class="fw-bold text-primary">${esc(d.nombre||d.cliente)}</span><br><small class="text-muted">${esc(d.cliente)}</small></td>
                <td><span class="badge bg-secondary">#${d.id_padre}</span><br><small>${esc(d.desc_padre)}</small></td>
                <td><span class="badge bg-danger">#${d.id_hija}</span><br><small>${esc(d.desc_hija)}</small></td>
                <td class="text-warning fw-bold">$${parseFloat(d.total_hija||0).toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn-fix btn-fix-dup" id="btnfix-dup-${i}" onclick="repararError('duplicado',{id_hija:${d.id_hija}},this)">
                        <i class="fas fa-trash-alt me-1"></i> Anular Duplicado
                    </button>
                </td>
            </tr>`;
        });
    } else if (tipo === 'fantasmas') {
        html += '<th>Cliente</th><th>Factura</th><th>Total</th><th>Ya Cobrado</th><th class="text-center">Acción</th>';
        html += '</tr></thead><tbody>';
        items.forEach((d, i) => {
            html += `<tr>
                <td><span class="fw-bold text-primary">${esc(d.nombre||d.cliente)}</span><br><small class="text-muted">${esc(d.cliente)}</small></td>
                <td><span class="badge bg-secondary">#${d.id}</span><br><small>${esc(d.desc)}</small></td>
                <td class="text-white fw-bold">$${parseFloat(d.total||0).toFixed(2)}</td>
                <td class="text-success">$${parseFloat(d.cobrado||0).toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn-fix btn-fix-fan" id="btnfix-fan-${i}" onclick="repararError('fantasma',{id_factura:${d.id}},this)">
                        <i class="fas fa-check me-1"></i> Marcar Pagada
                    </button>
                </td>
            </tr>`;
        });
    } else {
        html += '<th>Cliente</th><th>Factura Hija</th><th>Saldo Pendiente</th><th>Vence</th><th class="text-center">Acción</th>';
        html += '</tr></thead><tbody>';
        items.forEach((d, i) => {
            const hoy = new Date(); hoy.setDate(hoy.getDate() + 7);
            const defFecha = hoy.toISOString().slice(0,10);
            html += `<tr>
                <td><span class="fw-bold text-primary">${esc(d.nombre||d.cliente)}</span><br><small class="text-muted">${esc(d.cliente)}</small></td>
                <td><span class="badge" style="background:#6366f1;">#${d.id}</span><br><small>${esc(d.desc)}</small></td>
                <td class="text-warning fw-bold">$${parseFloat(d.saldo||d.total||0).toFixed(2)}</td>
                <td><small>${esc(d.fecha_venc||'—')}</small></td>
                <td class="text-center">
                    <div class="d-flex gap-1 align-items-center justify-content-center">
                        <input type="date" id="fecha-pro-${i}" class="form-control form-control-sm bg-dark text-white border-secondary" style="width:140px;" value="${defFecha}">
                        <button class="btn-fix btn-fix-pro" id="btnfix-pro-${i}" onclick="repararError('promesa',{id_factura:${d.id},fecha_el:'fecha-pro-${i}'},this)">
                            <i class="fas fa-calendar-check me-1"></i> Aplicar Promesa
                        </button>
                    </div>
                </td>
            </tr>`;
        });
    }
    html += '</tbody></table></div>';
    contenedor.innerHTML = html;
}

async function repararError(tipo, data, btn) {
    if (!confirm('¿Confirmas esta acción de reparación en WispHub?')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append('accion', 'reparar_error');
    fd.append('nodo', _scanNodo);
    fd.append('tipo', tipo);

    if (tipo === 'duplicado') fd.append('id_hija', data.id_hija);
    if (tipo === 'fantasma')  fd.append('id_factura', data.id_factura);
    if (tipo === 'promesa') {
        fd.append('id_factura', data.id_factura);
        fd.append('fecha_limite', document.getElementById(data.fecha_el).value);
    }

    try {
        const r = await fetch('', { method: 'POST', body: fd });
        const res = await r.json();
        if (res.ok) {
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Reparado';
            btn.style.background = 'rgba(16,185,129,.25)';
            btn.style.color = '#10b981';
            btn.closest('tr').style.opacity = '0.5';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times me-1"></i> Error';
            btn.title = JSON.stringify(res.detalle || res.error);
        }
    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = 'Red error';
    }
}
</script>
</body>
</html>
