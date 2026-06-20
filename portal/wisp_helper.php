<?php

function wisp_get_cache($serviceId) {
    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/wisp_' . preg_replace('/[^a-zA-Z0-9_]/', '', $serviceId) . '.json';
    $ttl = 60;
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

    $invoicesPending = $wispClient->getPendingInvoices($serviceId);
    $balance = $wispClient->getClientBalance($serviceId);

    // También obtener facturas pagadas recientes
    $usuario_ws = $c_perfil['usuario'] ?? '';
    $invoicesPaid = [];
    if (!empty($usuario_ws)) {
        $paidRes = $wispClient->getInvoices([
            'cliente'  => $usuario_ws,
            'estado'   => 2,
            'limit'    => 50,
            'ordering' => '-id',
        ]);
        $invoicesPaid = $paidRes;
    }

    // Fusionar: pendientes + pagadas
    $allInvoices = array_merge($invoicesPending, $invoicesPaid);

    $enriched = [];
    foreach ($allInvoices as $inv) {
        $invId = $inv['id'] ?? 0;
        if ($invId) {
            $full = $wispClient->getInvoiceDetail((string)$invId);
            if (!empty($full)) {
                $enriched[] = $full;
                continue;
            }
        }
        $enriched[] = $inv;
    }
    $invoices = $enriched;

    $ultimo_pago = null;
    if (!empty($usuario_ws)) {
        $ultimo_pago = $wispClient->getLastPaidInvoice($usuario_ws);
    }

    $data = [
        'profile' => $c_perfil,
        'invoices' => $invoices,
        'balance' => $balance,
        'ultimo_pago' => $ultimo_pago,
    ];

    wisp_set_cache($serviceId, $data);
    return $data;
}
