<?php

function wisp_get_cache($serviceId) {
    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/wisp_' . preg_replace('/[^a-zA-Z0-9_]/', '', $serviceId) . '.json';
    $ttl = 10;
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    return null;
}

function wisp_set_cache($serviceId, $data) {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheFile = $cacheDir . '/wisp_' . preg_replace('/[^a-zA-Z0-9_]/', '', $serviceId) . '.json';
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}

function wisp_clear_cache($serviceId) {
    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/wisp_' . preg_replace('/[^a-zA-Z0-9_]/', '', $serviceId) . '.json';
    if (file_exists($cacheFile)) @unlink($cacheFile);
}

function wisp_extract_desc($inv, $id) {
    $desc = '';
    $articulosKeys = ['articulos', 'items'];
    foreach ($articulosKeys as $artKey) {
        if (!empty($inv[$artKey]) && is_array($inv[$artKey])) {
            $parts = [];
            foreach ($inv[$artKey] as $art) {
                foreach (['descripcion', 'concepto', 'nombre', 'detalle'] as $field) {
                    if (!empty($art[$field])) {
                        $parts[] = trim($art[$field]);
                        break;
                    }
                }
            }
            if (!empty($parts)) {
                $desc = implode('; ', $parts);
                break;
            }
        }
    }
    if (empty($desc)) {
        foreach (['concepto', 'descripcion', 'observacion', 'detalle', 'nota'] as $key) {
            if (!empty($inv[$key])) {
                $desc = trim($inv[$key]);
                break;
            }
        }
    }
    if (empty($desc)) $desc = 'Recibo N° ' . $id;
    return $desc;
}

function wisp_get_cached_data($wispClient, $serviceId) {
    $forceRefresh = isset($_GET['refreshed']);
    if (!$forceRefresh) {
        $cached = wisp_get_cache($serviceId);
        if ($cached !== null) return $cached;
    }

    $profileRes = $wispClient->getServiceProfile($serviceId);
    $c_perfil = $profileRes['data'] ?? [];

    $detailRes = $wispClient->getServiceDetail($serviceId);
    if ($detailRes['status'] === 200 && !empty($detailRes['data'])) {
        $c_perfil = array_merge($c_perfil, $detailRes['data']);
    }

    $clientId = $c_perfil['usuario'] ?? null;

    // Usar GET /facturas/ con filtro por cliente y estado pendiente para obtener articulos con descripcion
    $invoicesPendingAPI = [];
    if ($clientId) {
        $invoicesPendingAPI = $wispClient->getInvoices([
            'cliente' => $clientId,
            'estado'  => 1,
            'limit'   => 50,
        ]);
    }
    $balance = $wispClient->getClientBalance($serviceId);

    // Normalizar y estructurar la respuesta para el dashboard
    $invoices = [];
    foreach ($invoicesPendingAPI as $inv) {
        $id = $inv['id_factura'] ?? $inv['id'] ?? 0;
        if (!$id) continue;
        
        $articulos = $inv['articulos'] ?? [];
        if (empty($articulos)) {
            $desc_fallback = '';
            foreach (['descripcion', 'concepto', 'observacion'] as $k) {
                if (!empty($inv[$k])) { $desc_fallback = trim($inv[$k]); break; }
            }
            if (empty($desc_fallback)) {
                $desc_fallback = 'Recibo N° ' . $id;
            }
            $articulos = [['descripcion' => $desc_fallback]];
        }

        $invoices[] = [
            'id'                => $id,
            'id_factura'        => $id,
            'fecha_emision'     => $inv['fecha_emision'] ?? '',
            'fecha_vencimiento' => $inv['fecha_vencimiento'] ?? '',
            'total'             => floatval($inv['total'] ?? 0),
            'saldo_nuevo'       => floatval($inv['total'] ?? 0),
            'saldo'             => floatval($inv['total'] ?? 0),
            'total_cobrado'     => 0,
            'estado'            => 1,
            'articulos'         => $articulos
        ];
    }

    $ultimo_pago = null;
    if (!empty($clientId)) {
        $ultimo_pago = $wispClient->getLastPaidInvoice($clientId);
    }

    // Sumar saldo a favor guardado en BD local (pagos con exceso) al balance de WispHub
    // Usamos require_once para evitar doble declaración si ya fue incluido
    $saldo_favor_local = 0.0;
    $refHelper = __DIR__ . '/referencia_helper.php';
    if (file_exists($refHelper)) {
        require_once $refHelper;
        $saldo_favor_local = getSaldoFavor($serviceId);
    }
    $balance_total = round($balance + $saldo_favor_local, 2);

    $data = [
        'profile'           => $c_perfil,
        'invoices'          => $invoices,
        'balance'           => $balance_total,          // WispHub + BD local
        'balance_wisphub'   => $balance,                // Solo WispHub (para debug)
        'saldo_favor_local' => $saldo_favor_local,      // Solo BD local
        'ultimo_pago'       => $ultimo_pago,
    ];

    wisp_set_cache($serviceId, $data);
    return $data;
}
