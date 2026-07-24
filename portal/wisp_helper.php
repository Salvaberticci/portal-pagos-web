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

    // Si getServiceProfile falló (timeout), intentar con findClientByDocument
    // usando la cédula de la sesión como fallback
    if (empty($c_perfil) && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['cliente_cedula'])) {
        try {
            $cedula = $_SESSION['cliente_cedula'];
            $fallbackRes = $wispClient->findClientByDocument($cedula);
            if ($fallbackRes['status'] === 200 && !empty($fallbackRes['data']['data'])) {
                $c_perfil = $fallbackRes['data']['data'];
                error_log('[wisp_helper] Fallback findClientByDocument OK para cédula ' . $cedula);
            }
        } catch (\Throwable $e2) {
            error_log('[wisp_helper] Fallback findClientByDocument también falló: ' . $e2->getMessage());
        }
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

        $invEstado = $inv['estado'] ?? 'Pendiente de Pago';
        $invTotal = floatval($inv['total'] ?? 0);
        $invCobrado = floatval($inv['total_cobrado'] ?? 0);
        $invSaldoNuevo = floatval($inv['saldo_nuevo'] ?? 0);
        // monto_pendiente: usar saldo_nuevo si está disponible y es > 0,
        // si no, total - cobrado; si ambos son 0 pero estado es pendiente, usar total
        $invPendiente = max(0, $invTotal - $invCobrado);
        if ($invSaldoNuevo > 0) {
            $invPendiente = $invSaldoNuevo;
        } elseif ($invPendiente === 0.0 && in_array($invEstado, ['Pendiente de Pago', 'Vencida', 'Pendiente', 'Vencido'])) {
            $invPendiente = $invTotal;
        }

        $invoices[] = [
            'id'                => $id,
            'id_factura'        => $id,
            'fecha_emision'     => $inv['fecha_emision'] ?? '',
            'fecha_vencimiento' => $inv['fecha_vencimiento'] ?? '',
            'total'             => $invTotal,
            'saldo_nuevo'       => $invSaldoNuevo,
            'saldo'             => floatval($inv['saldo'] ?? $invTotal),
            'total_cobrado'     => $invCobrado,
            'estado'            => $invEstado,
            'monto_pendiente'   => $invPendiente,
            'articulos'         => $articulos
        ];
    }

    // ── Filtrar facturas de saldo pendiente ──────────────────────────────
    // Cuando se hace un abono parcial, WispHub crea una factura "Saldo pendiente tras abono - Factura #X".
    // La factura padre #X NO debe mostrarse; solo la factura hija (saldo real pendiente).
    $idsConHijo = [];
    foreach ($invoices as $inv) {
        $artList = $inv['articulos'] ?? [];
        foreach ($artList as $art) {
            $d = $art['descripcion'] ?? '';
            if (preg_match('/Saldo pendiente tras abono - Factura #(\d+)/i', $d, $m)) {
                $idsConHijo[(int)$m[1]] = true;
            }
        }
    }
    $invoicesFiltradas = [];
    foreach ($invoices as $inv) {
        $idInv = $inv['id'] ?? $inv['id_factura'] ?? 0;
        if (isset($idsConHijo[$idInv])) continue;
        $invoicesFiltradas[] = $inv;
    }
    $invoices = $invoicesFiltradas;

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
