<?php
/**
 * Auditoría read-only: facturas con patrón "residuo sin pago real".
 *
 * Escanea TODAS las facturas pendientes de las 3 cuentas (sitelco, jalisco,
 * pampanito) y reporta las que cumplen:
 *   - estado Pendiente/Vencida
 *   - total_cobrado >= total   (residuo: WispHub registró un cobro fantasma,
 *     ej. #10873 con referencia vacía, importado por cajero)
 *   - saldo > 0
 *   - OPCIONAL flag: total > sub_total (se sumó monto extra al plan)
 *
 * Solo hace GETs a la API (no modifica nada).
 *
 * Uso: php tests/audit_facturas_residuo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';
require_once __DIR__ . '/../config/wisphub_credentials.php';

function client_for(array $account): \Services\WispHubClient {
    return new \Services\WispHubClient([
        'base_url'   => $account['base_url'],
        'api_key'    => $account['api_key'],
        'verify_ssl' => $account['verify_ssl'] ?? false,
    ]);
}

function es_patron_residuo(array $inv): bool {
    $estado  = $inv['estado'] ?? '';
    $total   = floatval($inv['total'] ?? 0);
    $cobrado = floatval($inv['total_cobrado'] ?? 0);
    $saldo   = floatval($inv['saldo'] ?? 0);
    $pendiente = in_array($estado, ['Pendiente de Pago', 'Vencida', 'Pendiente', 'Vencido'], true);
    return $pendiente && $cobrado >= $total && $saldo > 0;
}

function extraer_servicio(array $inv): string {
    $svc = $inv['id_servicio'] ?? '';
    if (empty($svc) && !empty($inv['articulos']) && is_array($inv['articulos'])) {
        foreach ($inv['articulos'] as $art) {
            $s = $art['servicio']['id_servicio'] ?? '';
            if (!empty($s)) { $svc = $s; break; }
        }
    }
    return (string)$svc;
}

function nombre_cliente(array $inv): string {
    if (!empty($inv['cliente']) && is_array($inv['cliente'])) {
        return ($inv['cliente']['nombre'] ?? '') . ' (' . ($inv['cliente']['cedula'] ?? $inv['cliente']['usuario'] ?? '') . ')';
    }
    return (string)($inv['cliente'] ?? '');
}

$grandes_total = 0;
$grandes_afectados = 0;

foreach ($WISPHUB_ACCOUNTS as $ref => $account) {
    echo "\n=== Cuenta: {$ref} ({$account['label']}) ===\n";
    $client = client_for($account);

    $afectados = [];
    $offset = 0;
    $limit = 500;
    $revisadas = 0;

    while (true) {
        $result = $client->getInvoices(['estado' => 1, 'limit' => $limit, 'offset' => $offset]);
        $pageCount = count($result);
        if ($pageCount === 0) break;
        $revisadas += $pageCount;

        foreach ($result as $inv) {
            if (es_patron_residuo($inv)) {
                $sub_total = floatval($inv['sub_total'] ?? 0);
                $total     = floatval($inv['total'] ?? 0);
                $afectados[] = [
                    'id'       => $inv['id_factura'] ?? $inv['id'] ?? 0,
                    'svc'      => extraer_servicio($inv),
                    'cliente'  => nombre_cliente($inv),
                    'zona'     => is_array($inv['zona'] ?? null) ? ($inv['zona']['nombre'] ?? '') : ($inv['zona'] ?? ''),
                    'subtotal' => $sub_total,
                    'total'    => $total,
                    'cobrado'  => floatval($inv['total_cobrado'] ?? 0),
                    'saldo'    => floatval($inv['saldo'] ?? 0),
                    'snuevo'   => floatval($inv['saldo_nuevo'] ?? 0),
                    'ref'      => $inv['referencia'] ?? '',
                    'cajero'   => is_array($inv['cajero'] ?? null) ? ($inv['cajero']['nombre'] ?? '') : ($inv['cajero'] ?? ''),
                    'emis'     => $inv['fecha_emision'] ?? '',
                    'extra'    => $total > $sub_total + 0.01,
                ];
            }
        }

        if ($pageCount < $limit) break;
        $offset += $limit;
    }

    echo "  Facturas pendientes revisadas: {$revisadas}\n";
    echo "  Afectadas (patrón residuo): " . count($afectados) . "\n";

    if (count($afectados) > 0) {
        echo "  id | svc | cliente | zona | sub_total | total | cobrado | saldo | snuevo | ref | cajero | emision | total>subtotal\n";
        foreach ($afectados as $a) {
            echo "  {$a['id']} | {$a['svc']} | {$a['cliente']} | {$a['zona']} | {$a['subtotal']} | {$a['total']} | {$a['cobrado']} | {$a['saldo']} | {$a['snuevo']} | {$a['ref']} | {$a['cajero']} | {$a['emis']} | " . ($a['extra'] ? 'SÍ' : 'no') . "\n";
        }
    }

    $grandes_total += $revisadas;
    $grandes_afectados += count($afectados);
}

echo "\n=== RESUMEN GLOBAL ===\n";
echo "  Facturas pendientes revisadas: {$grandes_total}\n";
echo "  Facturas con patrón residuo: {$grandes_afectados}\n";
echo "  (Solo lectura: la API no fue modificada)\n";