<?php
/**
 * Limpia todos los datos de prueba del cliente onu_prueba_oficina@sitelco (service_id 902)
 * Elimina: facturas en WispHub, registros en pagos_registrados, y caché local
 */

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/portal/referencia_helper.php';

$wispConfig = include __DIR__ . '/config/wisp_hub.php';
$cfg = include __DIR__ . '/config/database.php';

$usuario = 'onu_prueba_oficina@sitelco';
$serviceId = '902';

echo "=== LIMPIEZA DE DATOS DE PRUEBA ===\n\n";

// ============================================================
// 1. Conectar a BD local y eliminar registros
// ============================================================
echo "1. Eliminando registros locales en pagos_registrados...\n";
try {
    $pdo = getDb();
    if ($pdo) {
        $stmt = $pdo->prepare("DELETE FROM pagos_registrados WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        $count = $stmt->rowCount();
        echo "   Eliminados $count registros con service_id = '$serviceId'\n";
    }
} catch (Exception $e) {
    echo "   Error BD: " . $e->getMessage() . "\n";
}

// ============================================================
// 2. Obtener y eliminar facturas del cliente en WispHub
// ============================================================
echo "\n2. Eliminando facturas del cliente en WispHub...\n";

$client = new \GuzzleHttp\Client([
    'base_uri' => $wispConfig['base_url'],
    'verify'   => $wispConfig['verify_ssl'] ?? false,
    'headers'  => [
        'Authorization' => 'Api-Key ' . $wispConfig['api_key'],
        'Content-Type'  => 'application/json',
    ],
]);

// Obtener facturas pendientes del usuario
try {
    $resp = $client->get('facturas/', [
        'query' => ['cliente' => $usuario, 'estado' => 1, 'limit' => 100],
    ]);
    $data = json_decode($resp->getBody(), true);
    $facturas = $data['results'] ?? [];

    if (empty($facturas)) {
        echo "   No hay facturas pendientes para '$usuario'\n";
    } else {
        echo "   Encontradas " . count($facturas) . " facturas pendientes:\n";
        foreach ($facturas as $f) {
            $id = $f['id_factura'] ?? $f['id'] ?? '';
            $monto = $f['total'] ?? $f['monto'] ?? 0;
            echo "   - Eliminando #$id (\${$monto})... ";
            try {
                $client->delete("facturas/{$id}/");
                echo "OK\n";
            } catch (\Exception $e) {
                $code = $e->getCode();
                echo "Error (HTTP $code): " . $e->getMessage() . "\n";
            }
            usleep(300000); // 300ms entre peticiones
        }
    }
} catch (\Exception $e) {
    echo "   Error al obtener facturas: " . $e->getMessage() . "\n";
}

// También buscar facturas pagadas o en otros estados
foreach ([2, 3, 4, 5] as $estado) {
    try {
        $resp = $client->get('facturas/', [
            'query' => ['cliente' => $usuario, 'estado' => $estado, 'limit' => 100],
        ]);
        $data = json_decode($resp->getBody(), true);
        $facturas = $data['results'] ?? [];
        if (!empty($facturas)) {
            echo "\n   Encontradas " . count($facturas) . " facturas en estado $estado:\n";
            foreach ($facturas as $f) {
                $id = $f['id_factura'] ?? $f['id'] ?? '';
                $monto = $f['total'] ?? $f['monto'] ?? 0;
                echo "   - Eliminando #$id (\${$monto})... ";
                try {
                    $client->delete("facturas/{$id}/");
                    echo "OK\n";
                } catch (\Exception $e) {
                    echo "Error (HTTP {$e->getCode()}): no se pudo eliminar\n";
                }
                usleep(300000);
            }
        }
    } catch (\Exception $e) {
        // Ignorar errores de consulta
    }
}

// ============================================================
// 3. Limpiar caché local
// ============================================================
echo "\n3. Limpiando caché local...\n";
$cacheFile = __DIR__ . '/cache/wisp_' . $serviceId . '.json';
if (file_exists($cacheFile)) {
    unlink($cacheFile);
    echo "   Eliminado $cacheFile\n";
} else {
    echo "   No existe archivo de caché\n";
}

echo "\n=== LIMPIEZA COMPLETADA ===\n";
