<?php
/**
 * CRON JOBS: Verificación de Promesas de Pago Vencidas
 * 
 * Este script debe ejecutarse mediante una tarea programada (Cron Job) todos los días.
 * Preferiblemente a primera hora (ej. 00:05 AM).
 * 
 * Lógica:
 * Busca en la base de datos local los abonos parciales cuyas promesas de pago
 * ya vencieron (fecha_promesa < HOY) y el cliente aún no ha completado el pago
 * (total_cobrado < total). Luego, envía la orden a WispHub para suspender el servicio.
 */

// Permite ejecución sin límite de tiempo
set_time_limit(0);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/WispHubClient.php';
require_once __DIR__ . '/portal/referencia_helper.php';

// Configurar log
$logFile = __DIR__ . '/logs/cron_cortes.log';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

function logger($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    $line = "[$date] $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

logger("Iniciando cron de cortes por promesas vencidas...");

$db = getDb();
if (!$db) {
    logger("ERROR: No se pudo conectar a la base de datos.");
    exit(1);
}

try {
    // Buscar promesas vencidas donde el pago no se ha completado
    // (Asegurarnos de que fecha_promesa sea estricto menor a hoy)
    $stmt = $db->prepare("
        SELECT * FROM pagos_registrados 
        WHERE fecha_promesa IS NOT NULL 
          AND fecha_promesa < CURDATE() 
          AND CAST(total_cobrado AS DECIMAL(10,2)) < CAST(total AS DECIMAL(10,2))
          AND accion IN ('abono', 'pago parcial')
        GROUP BY service_id
    ");
    $stmt->execute();
    $morosos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($morosos)) {
        logger("No hay promesas vencidas para procesar hoy.");
        exit(0);
    }

    $wispConfig = include __DIR__ . '/config/wisp_hub.php';
    $wispClient = new \Services\WispHubClient($wispConfig);

    logger("Encontrados " . count($morosos) . " clientes con promesa vencida.");

    foreach ($morosos as $row) {
        $serviceId = $row['service_id'];
        $fechaProm = $row['fecha_promesa'];
        $cobrado = $row['total_cobrado'];
        $total = $row['total'];

        logger("Evaluando Service ID: $serviceId | Promesa: $fechaProm | Pagado: $cobrado / $total");

        // 1. Verificar el estado actual del servicio en WispHub
        $detalle = $wispClient->getServiceDetail($serviceId);
        $estadoActual = $detalle['estado'] ?? 'Desconocido';

        if (strtolower($estadoActual) === 'activo') {
            logger("  El servicio está activo. Procediendo a suspender...");
            
            // 2. Enviar orden de suspensión
            $res = $wispClient->deactivateService($serviceId, "Promesa de pago vencida");
            
            if (($res['status'] ?? 0) === 200 || ($res['status'] ?? 0) === 201) {
                logger("  ÉXITO: Servicio $serviceId suspendido por WispHub API.");
            } else {
                $err = $res['error'] ?? json_encode($res['data'] ?? 'Error desconocido');
                logger("  ERROR: No se pudo suspender el servicio $serviceId. Detalles: $err");
            }
        } else {
            logger("  El servicio ya está en estado '$estadoActual'. No se requiere acción.");
        }
        
        // Pausa de 1 segundo para no saturar la API de WispHub
        sleep(1);
    }

    logger("Cron finalizado correctamente.");

} catch (Exception $e) {
    logger("ERROR CRÍTICO: " . $e->getMessage());
}
