<?php

function wisp_get_cache($serviceId) {
    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/wisp_' . preg_replace('/[^a-zA-Z0-9_]/', '', $serviceId) . '.json';
    $ttl = 300; // 5 minutos para reducir llamadas a la API
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

    // Perfil del servicio
    $c_perfil = [];
    try {
        $profileRes = $wispClient->getServiceProfile($serviceId);
        $c_perfil = $profileRes['data'] ?? [];
    } catch (\Throwable $e) {
        error_log('[wisp_helper] getServiceProfile falló: ' . $e->getMessage());
    }

    try {
        $detailRes = $wispClient->getServiceDetail($serviceId);
        if (!empty($detailRes['data'])) {
            $c_perfil = array_merge($c_perfil, $detailRes['data']);
        }
    } catch (\Throwable $e) {
        error_log('[wisp_helper] getServiceDetail falló: ' . $e->getMessage());
    }

    $clientId = $c_perfil['usuario'] ?? null;

    // Facturas pendientes
    $invoicesPendingAPI = [];
    if ($clientId) {
        try {
            $invoicesPendingAPI = $wispClient->getInvoices([
                'cliente' => $clientId,
                'estado'  => 1,
                'limit'   => 50,
            ]);
        } catch (\Throwable $e) {
            error_log('[wisp_helper] getInvoices falló: ' . $e->getMessage());
        }
    }

    // Saldo a favor en WispHub
    $balance = 0.0;
    try {
        $balance = $wispClient->getClientBalance($serviceId);
    } catch (\Throwable $e) {
        error_log('[wisp_helper] getClientBalance falló: ' . $e->getMessage());
    }

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

    // Último pago
    $ultimo_pago = null;
    if (!empty($clientId)) {
        try {
            $ultimo_pago = $wispClient->getLastPaidInvoice($clientId);
        } catch (\Throwable $e) {
            error_log('[wisp_helper] getLastPaidInvoice falló: ' . $e->getMessage());
        }
    }

    // Sumar saldo a favor guardado en BD local (pagos con exceso) al balance de WispHub
    $saldo_favor_local = 0.0;
    $refHelper = __DIR__ . '/referencia_helper.php';
    if (file_exists($refHelper)) {
        require_once $refHelper;
        try {
            $saldo_favor_local = getSaldoFavor($serviceId);
        } catch (\Throwable $e) {
            error_log('[wisp_helper] getSaldoFavor falló: ' . $e->getMessage());
        }
    }
    $balance_total = round($balance + $saldo_favor_local, 2);

    $data = [
        'profile'           => $c_perfil,
        'invoices'          => $invoices,
        'balance'           => $balance_total,
        'balance_wisphub'   => $balance,
        'saldo_favor_local' => $saldo_favor_local,
        'ultimo_pago'       => $ultimo_pago,
    ];

    // Solo cachear si obtuvimos datos del perfil, para no cachear una respuesta vacía por fallo de red
    if (!empty($c_perfil)) {
        wisp_set_cache($serviceId, $data);
    }
    return $data;
}
