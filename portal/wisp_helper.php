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

    // Solo usamos las facturas pendientes que devuelve WispHub.
    // NO mezclamos con facturas "pagadas" (estado=2) porque WispHub, cuando
    // registra un abono parcial, crea una factura nueva de "Saldo Pendiente"
    // y marca la original como pagada (estado=2). Si mezcláramos ambas,
    // el portal mostraría la factura original (pagada parcialmente) Y la nueva
    // de saldo pendiente, duplicando facturas y confundiendo al cliente.
    $pendingIds = [];
    $byId = [];
    foreach ($invoicesPending as $inv) {
        $id = $inv['id'] ?? $inv['id_factura'] ?? 0;
        if ($id) {
            $pendingIds[$id] = true;
            $byId[$id] = $inv;
        }
    }
    $allInvoices = array_values($byId);

    $enriched = [];
    foreach ($allInvoices as $inv) {
        $invId = $inv['id'] ?? 0;
        if ($invId) {
            $full = $wispClient->getInvoiceDetail((string)$invId);
            if (!empty($full)) {
                // Si la factura viene del listado PENDIENTE, el campo "total" de ese
                // endpoint es el saldo real a pagar. El detalle (/facturas/{id}/) puede
                // devolver total_cobrado desactualizado. Corregimos:
                $fromPending = isset($pendingIds[$invId]);
                if ($fromPending) {
                    $pendingTotal = floatval($inv['total'] ?? 0);
                    $detailTotal  = floatval($full['total'] ?? $pendingTotal);
                    // pendingTotal = saldo pendiente real
                    // total_cobrado = detailTotal - pendingTotal (si detailTotal > pendingTotal, hay abono)
                    $full['total'] = $detailTotal; // el total original de la factura
                    $full['total_cobrado'] = max(0, $detailTotal - $pendingTotal);
                    $full['saldo_nuevo'] = $pendingTotal;
                    $full['saldo'] = $pendingTotal;
                }
                $enriched[] = $full;
                continue;
            }
        }
        $enriched[] = $inv;
    }
    $invoices = $enriched;

    $usuario_ws = $c_perfil['usuario'] ?? '';
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
