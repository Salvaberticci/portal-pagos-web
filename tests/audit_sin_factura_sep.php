<?php
/**
 * Auditoría: Clientes activos SIN factura de Septiembre 2026
 * en Jalisco y Sitelco.
 * Solo lectura — no modifica nada.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/wisphub_credentials.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$hoy_str      = date('Y-m-d');
$ventana_ini  = date('Y-m-01');
$ventana_fin  = date('Y-m-d', strtotime(date('Y-m-t') . ' +5 days'));
$nodos        = ['jalisco', 'sitelco'];

$es_hija = function(array $inv): bool {
    $desc = '';
    foreach ($inv['articulos'] ?? [] as $art) {
        $desc .= ($art['descripcion'] ?? '');
    }
    return stripos($desc, 'Saldo pendiente') !== false
        || stripos($desc, 'Saldo a favor') !== false
        || stripos($desc, 'Saldo Pendiente') !== false;
};

foreach ($nodos as $nodo) {
    $creds = $WISPHUB_ACCOUNTS[$nodo];
    $wispClient = new \Services\WispHubClient([
        'base_url'   => $creds['base_url'],
        'api_key'    => $creds['api_key'],
        'verify_ssl' => false,
    ]);

    echo "\n==============================\n";
    echo "  NODO: " . strtoupper($nodo) . "\n";
    echo "==============================\n";

    $page     = 1;
    $sin_fac  = [];
    $total    = 0;

    do {
        try {
            $resp = $wispClient->listClients([
                'limit'  => 100,
                'offset' => ($page - 1) * 100,
            ]);
        } catch (\Throwable $e) {
            echo "Error listando clientes: " . $e->getMessage() . "\n";
            break;
        }

        // listClients devuelve {status, data: {count, results, next, previous}}
        $count_total = $resp['data']['count'] ?? 0;
        $raw_results = $resp['data']['results'] ?? [];
        if (empty($raw_results)) break;
        $clientes    = array_values(array_filter($raw_results, fn($c) => ($c['estado'] ?? '') === 'Activo'));

        foreach ($clientes as $c) {
            $total++;
            $username   = $c['usuario'] ?? null;
            $svc_id     = $c['id_servicio'] ?? null;
            $nombre     = $c['nombre'] ?? '?';
            $cedula     = $c['cedula'] ?? '?';

            if (!$svc_id) continue;

            // Buscar facturas pendientes del cliente por id_servicio (más fiable)
            try {
                $pendientes = $wispClient->getInvoices([
                    'id_servicio' => $svc_id,
                    'estado'      => 1,
                    'limit'       => 30,
                ]);
            } catch (\Throwable $e) {
                continue;
            }

            // Buscar si tiene cobertura para este mes por fecha_vencimiento
            $tiene_mensualidad = false;
            foreach ($pendientes as $inv) {
                if ($es_hija($inv)) continue;
                $fvenc = substr($inv['fecha_vencimiento'] ?? '', 0, 10);
                if ($fvenc >= $ventana_ini && $fvenc <= $ventana_fin) {
                    $tiene_mensualidad = true;
                    break;
                }
            }

            // Si no encontró en pendientes, buscar en todas (últimos 45 días)
            if (!$tiene_mensualidad) {
                try {
                    $buscar_desde = date('Y-m-d', strtotime('-45 days'));
                    $todas_rango  = $wispClient->getInvoices([
                        'id_servicio'            => $svc_id,
                        'fecha_emision__range_0' => $buscar_desde,
                        'fecha_emision__range_1' => $hoy_str,
                        'limit'                  => 20,
                    ]);
                    foreach ($todas_rango as $inv) {
                        if ($es_hija($inv)) continue;
                        $fvenc = substr($inv['fecha_vencimiento'] ?? '', 0, 10);
                        if ($fvenc >= $ventana_ini && $fvenc <= $ventana_fin) {
                            $tiene_mensualidad = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {}
            }

            if (!$tiene_mensualidad) {
                $sin_fac[] = [
                    'nombre'  => $nombre,
                    'cedula'  => $cedula,
                    'svc_id'  => $svc_id,
                    'usuario' => $username,
                ];
                echo "⚠️  Sin factura Sep: $nombre | Cedula: $cedula | svc=$svc_id\n";
            }
        }

        // ¿Hay más páginas?
        $page++;
    } while (count($clientes) === 100 && ($page - 1) * 100 < $count_total);

    echo "\nTotal activos revisados: $total\n";
    echo "Sin factura Septiembre: " . count($sin_fac) . "\n";
}

echo "\n=== AUDITORÍA COMPLETA ===\n";
