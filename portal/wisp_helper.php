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

/**
 * Normaliza una factura de WispHub al formato que usa el dashboard.
 * Devuelve null si la factura no tiene id (se omite del listado).
 *
 * monto_pendiente: la deuda real según WispHub.
 * 1. Base: total - cobrado (abono parcial con cobrado < total).
 * 2. saldo_nuevo solo si es MAYOR a la base y hay abono parcial real
 *    (nunca reducir la deuda por debajo de total - cobrado).
 * 3. Si la factura está pendiente pero cobrado >= total, WispHub deja
 *    total_cobrado = total como residuo y los campos saldo/saldo_nuevo
 *    NO son confiables (ej. #10873: total=30, cobrado=30, saldo=10):
 *    la deuda real es el total de la factura.
 */
function wisp_normalize_invoice(array $inv): ?array
{
    $id = $inv['id_factura'] ?? $inv['id'] ?? 0;
    if (!$id) return null;

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
    $invTotal = floatval($inv['total'] ?? $inv['sub_total'] ?? $inv['monto'] ?? 0);
    $invSubTotal = floatval($inv['sub_total'] ?? $invTotal);
    $invCobrado = floatval($inv['total_cobrado'] ?? 0);
    $invSaldoNuevo = floatval($inv['saldo_nuevo'] ?? 0);
    $invSaldo = floatval($inv['saldo'] ?? 0);
    $invPendiente = (float)max(0, $invTotal - $invCobrado);
    if ($invSaldoNuevo > $invPendiente && $invCobrado < $invTotal) {
        $invPendiente = $invSaldoNuevo;
    }
    if ($invPendiente <= 0.005 && in_array($invEstado, ['Pendiente de Pago', 'Vencida', 'Pendiente', 'Vencido'])) {
        $invPendiente = (float)max(0, $invTotal > 0 ? $invTotal : $invSubTotal);
    }

    return [
        'id'                => $id,
        'id_factura'        => $id,
        'fecha_emision'     => $inv['fecha_emision'] ?? '',
        'fecha_vencimiento' => $inv['fecha_vencimiento'] ?? '',
        'total'             => $invTotal,
        'sub_total'         => $invSubTotal,
        'saldo_nuevo'       => $invSaldoNuevo,
        'saldo'             => $invSaldo,
        'total_cobrado'     => $invCobrado,
        'estado'            => $invEstado,
        'monto_pendiente'   => $invPendiente,
        'articulos'         => $articulos
    ];
}

/**
 * Elimina de la lista las facturas padre que tienen una factura hija
 * "Saldo pendiente tras abono - Factura #X" (abono parcial). Solo se
 * muestra la factura hija con el saldo real pendiente.
 */
function wisp_filter_saldo_pendiente(array $invoices): array
{
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
    return $invoicesFiltradas;
}

function wisp_get_cached_data($wispClient, $serviceId) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $forceRefresh = isset($_GET['refreshed']) || !empty($_SESSION['wisp_force_refresh']);
    if ($forceRefresh) {
        unset($_SESSION['wisp_force_refresh']);
    }

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
        $norm = wisp_normalize_invoice($inv);
        if ($norm !== null) $invoices[] = $norm;
    }

    // ── Filtrar facturas de saldo pendiente ──────────────────────────────
    // Cuando se hace un abono parcial, WispHub crea una factura "Saldo pendiente tras abono - Factura #X".
    // La factura padre #X NO debe mostrarse; solo la factura hija (saldo real pendiente).
    $invoices = wisp_filter_saldo_pendiente($invoices);

    // ── Auto-generación de mensualidad faltante ───────────────────────────
    // Si el cliente NO tiene ninguna factura mensual vigente para el mes ACTUAL,
    // generamos la nueva mensualidad silenciosamente.
    //
    // DETECCIÓN POR fecha_vencimiento (no por fecha_emision):
    //   WispHub suele emitir la factura de Septiembre a finales de Agosto.
    //   Si buscamos por fecha_emision en Sep, no la encontramos y duplicamos.
    //   La solución correcta: buscar si existe alguna factura cuyo vencimiento
    //   caiga dentro del mes actual (no importa cuándo fue emitida).
    //
    // Problemas que corrige esta versión:
    //  1. precio_plan no viene de getServiceProfile en Jalisco → fallback a listClients
    //  2. tipo_factura no existe en la API de Jalisco → no usamos ese filtro
    //  3. Detección por fecha_vencimiento, no fecha_emision (evita duplicados)
    $factura_mensual_generada = false;
    if ($clientId && !empty($c_perfil)) {
        try {
            $estado_cliente = strtolower($c_perfil['estado'] ?? '');
            $precio_plan    = floatval($c_perfil['precio_plan'] ?? 0);

            // Si falta el estado o el precio del plan (común en Jalisco/.io),
            // hacer un fallback a listClients que sí devuelve estos datos.
            if (empty($estado_cliente) || $precio_plan <= 0) {
                try {
                    $cliRes = $wispClient->listClients(['id_servicio' => $serviceId, 'limit' => 1]);
                    if ($cliRes['status'] === 200 && !empty($cliRes['data']['results'][0])) {
                        if (empty($estado_cliente)) {
                            $estado_cliente = strtolower($cliRes['data']['results'][0]['estado'] ?? '');
                        }
                        if ($precio_plan <= 0) {
                            $precio_plan = floatval($cliRes['data']['results'][0]['precio_plan'] ?? 0);
                        }
                    }
                } catch (\Throwable $e2) {
                    error_log('[wisp_helper] listClients fallback falló: ' . $e2->getMessage());
                }
            }

            // Último recurso: precio desde plan_internet embebido en el perfil
            if ($precio_plan <= 0 && !empty($c_perfil['plan_internet'])) {
                $precio_plan = floatval($c_perfil['plan_internet']['precio'] ?? $c_perfil['plan_internet']['costo'] ?? 0);
            }

            // Solo generar si el cliente está Activo
            if (in_array($estado_cliente, ['activo', 'active'])) {

                if ($precio_plan > 0) {
                    $primer_dia_mes = date('Y-m-01');
                    $ultimo_dia_mes = date('Y-m-t');
                    $hoy_str        = date('Y-m-d');

                    // ──────────────────────────────────────────────────────────────
                    // DETECCIÓN: ¿Ya existe una factura que cubre el mes actual?
                    // Criterio: fecha_vencimiento dentro del mes actual Y que no
                    // sea una factura hija de saldo parcial.
                    // También se acepta si la fecha_vencimiento cae 1-5 días en
                    // el mes siguiente (ciclos que arrancan el 1 del mes próximo).
                    // ──────────────────────────────────────────────────────────────
                    $tiene_mensualidad = false;
                    $ventana_inicio    = $primer_dia_mes;
                    // Buscar facturas cuyo vencimiento esté desde el día 1 del mes
                    // actual hasta 5 días dentro del mes siguiente
                    $ventana_fin       = date('Y-m-d', strtotime($ultimo_dia_mes . ' +5 days'));

                    // Helper interno para detectar factura hija de saldo
                    $es_hija = function(array $inv): bool {
                        $desc = '';
                        foreach ($inv['articulos'] ?? [] as $art) {
                            $desc .= ($art['descripcion'] ?? '');
                        }
                        return stripos($desc, 'Saldo pendiente') !== false
                            || stripos($desc, 'Saldo a favor') !== false;
                    };

                    // 1. Revisar pendientes ya cargadas en memoria
                    foreach ($invoicesPendingAPI as $inv) {
                        if ($es_hija($inv)) continue;
                        $fvenc = substr($inv['fecha_vencimiento'] ?? '', 0, 10);
                        if ($fvenc >= $ventana_inicio && $fvenc <= $ventana_fin) {
                            $tiene_mensualidad = true;
                            break;
                        }
                    }

                    // 2. Si no encontró en pendientes, buscar en TODAS
                    //    (incluyendo pagadas) usando ventana de vencimiento.
                    //    Buscamos facturas emitidas en los últimos 45 días para
                    //    capturar las que WispHub genera a finales del mes anterior.
                    if (!$tiene_mensualidad) {
                        $buscar_desde = date('Y-m-d', strtotime('-45 days'));
                        $todas_rango  = $wispClient->getInvoices([
                            'cliente'                => $clientId,
                            'fecha_emision__range_0' => $buscar_desde,
                            'fecha_emision__range_1' => $hoy_str,
                            'limit'                  => 30,
                        ]);
                        foreach ($todas_rango as $inv) {
                            if ($es_hija($inv)) continue;
                            $fvenc = substr($inv['fecha_vencimiento'] ?? '', 0, 10);
                            if ($fvenc >= $ventana_inicio && $fvenc <= $ventana_fin) {
                                $tiene_mensualidad = true;
                                break;
                            }
                        }
                    }

                    // 3. Generar la mensualidad si no existe cobertura para este mes
                    if (!$tiene_mensualidad) {
                        // Construir la descripción imitando el formato estándar de WispHub
                        $plan_nombre  = $c_perfil['plan_internet']['nombre'] ?? $c_perfil['plan'] ?? 'Plan de Internet';
                        $rb_name      = $c_perfil['torre'] ?? $c_perfil['sectorial'] ?? '';
                        if (!empty($rb_name)) {
                            $desc_factura = "Renta y mantenimiento de la red: {$rb_name}\nPlan de Internet: {$plan_nombre} {$precio_plan}\nPeriodo del 1/" . date('M./Y') . " al " . date('t/M./Y');
                        } else {
                            $desc_factura = "Renta y mantenimiento de la red\nPlan de Internet: {$plan_nombre} {$precio_plan}\nPeriodo del 1/" . date('M./Y') . " al " . date('t/M./Y');
                        }

                        $createResult = $wispClient->createInvoice(
                            $clientId,
                            $precio_plan,
                            $desc_factura,
                            $ultimo_dia_mes,  // vence el último día del mes
                            $serviceId,
                            1                 // tipo_factura=1 (Recurrente/Mensual)
                        );

                        if (in_array($createResult['status'] ?? 0, [200, 201])) {
                            $factura_mensual_generada = true;
                            error_log("[wisp_helper] Mensualidad auto-generada para svc={$serviceId} usuario={$clientId} precio={$precio_plan}");

                            // Recargar facturas pendientes para que aparezca la nueva
                            $invoicesPendingAPI = $wispClient->getInvoices([
                                'cliente' => $clientId,
                                'estado'  => 1,
                                'limit'   => 50,
                            ]);
                            $invoices = [];
                            foreach ($invoicesPendingAPI as $inv) {
                                $norm = wisp_normalize_invoice($inv);
                                if ($norm !== null) $invoices[] = $norm;
                            }
                            $invoices = wisp_filter_saldo_pendiente($invoices);
                        } else {
                            error_log("[wisp_helper] Fallo auto-generación mensualidad svc={$serviceId}: " . json_encode($createResult));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[wisp_helper] auto-generar mensualidad falló: ' . $e->getMessage());
        }
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
