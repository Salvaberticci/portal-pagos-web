<?php
/**
 * portal/test_setup.php
 *
 * Script de configuración rápida para pruebas manuales.
 * Vincula el usuario de prueba local con un servicio real de WispHub (ID 902).
 *
 * Acciones:
 *   ?accion=setup    - Crear contrato local vinculado a WispHub ID 902
 *   ?accion=status   - Ver estado actual
 *   ?accion=expire   - Vencer deudas (para probar corte)
 *   ?accion=pago     - Simular pago aprobado (para probar reactivación)
 *   ?accion=clean    - Limpiar datos de prueba
 */

require_once dirname(__DIR__) . '/paginas/conexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Services/WispHubClient.php';

// ── Crear tablas si no existen ────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `request_payload` TEXT,
    `response_payload` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `wisp_hub_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `contract_id` INT DEFAULT NULL,
    `wisp_account_id` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'PENDING',
    `last_event` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contract_id` (`contract_id`),
    INDEX `idx_wisp_account_id` (`wisp_account_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "<pre>";
echo "=== Portal Test Setup — WispHub Real (ID 902) ===\n\n";

// Configuración de WispHub - USAR PRODUCCIÓN
$wispConfig = include dirname(__DIR__) . '/config/wisp_hub.php';
echo "API URL: " . $wispConfig['base_url'] . "\n";
echo "API Key: " . substr($wispConfig['api_key'] ?? '', 0, 15) . "...\n\n";

$wispClient = new \Services\WispHubClient($wispConfig);

$accion = $_GET['accion'] ?? 'setup';
$cedula_test = 'V20788775';
$wisp_service_id = '902';
$wisp_username = 'ONU_PRUEBA_OFICINA';

switch ($accion) {
    case 'setup':
        echo "Configurando entorno de pruebas con servicio real ID $wisp_service_id...\n\n";

        // Limpiar datos previos
        $old = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test'");
        while ($row = $old->fetch_assoc()) {
            $cid = $row['id'];
            $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IN (SELECT id_reporte FROM pagos_reportados WHERE cedula_titular = '$cedula_test')");
            $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $cid");
            $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $cid)");
            $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $cid");
            $conn->query("DELETE FROM pagos_reportados WHERE id_contrato_asociado = $cid");
            $conn->query("DELETE FROM clientes_deudores WHERE id_contrato = $cid");
            $conn->query("DELETE FROM contratos WHERE id = $cid");
        }
        echo "Datos previos de V20788775 eliminados.\n";

        // Crear contrato
        $sql = "INSERT INTO contratos (
            cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan,
            vendedor_texto, direccion, fecha_instalacion, estado, monto_instalacion,
            monto_pagar, monto_pagado, instalador, tipo_conexion, mac_onu
        ) VALUES ('$cedula_test', 'CLIENTE OFICINA PRUEBA', 1, 1, 4, 650.00,
                  'SISTEMA', 'DIRECCION DE PRUEBA', NOW(), 'ACTIVO', 0, 0, 0,
                  'SISTEMA', 'FTTH', 'ONU_PRUEBA_OFICINA')";

        if ($conn->query($sql)) {
            $id_contrato = $conn->insert_id;
            echo "Contrato creado: ID #$id_contrato (ACTIVO)\n";
        } else {
            die("Error creando contrato: " . $conn->error . "\n");
        }

        // Crear wisp_hub_links con el ID real de WispHub
        $conn->query("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, created_at)
                      VALUES (NULL, $id_contrato, '$wisp_service_id', 'SUSPENDED', NOW())");
        echo "wisp_hub_links creado: wisp_account_id=$wisp_service_id (SUSPENDED)\n";

        // Crear deuda PENDIENTE
        $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen)
                      VALUES ($id_contrato, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 17.50, 'PENDIENTE', 'SISTEMA')");
        echo "Cuenta por cobrar PENDIENTE: 17.50 USD\n";

        echo "\n=== SETUP COMPLETADO ===\n";
        echo "  Cedula: $cedula_test\n";
        echo "  Contrato ID: #$id_contrato\n";
        echo "  WispHub Service ID: $wisp_service_id\n";
        echo "  WispHub Username: $wisp_username\n";
        echo "\nProximo paso:\n";
        echo "  1. Ve al portal: http://localhost/sistemas-administrativo-tecnico-wireless/portal/index.php\n";
        echo "  2. Ingresa con cedula: $cedula_test\n";
        echo "  3. Reporta un pago con referencia 999222, monto 1.00 Bs\n";
        break;

    case 'status':
        echo "Estado actual del usuario de prueba:\n\n";

        $contratos = $conn->query("SELECT id, estado, nombre_completo FROM contratos WHERE cedula = '$cedula_test'");
        if ($contratos && $contratos->num_rows > 0) {
            while ($c = $contratos->fetch_assoc()) {
                echo "Contrato #{$c['id']}: {$c['estado']} ({$c['nombre_completo']})\n";

                $links = $conn->query("SELECT wisp_account_id, status, last_event, updated_at FROM wisp_hub_links WHERE contract_id = {$c['id']}");
                while ($l = $links->fetch_assoc()) {
                    echo "  WispHub: ID={$l['wisp_account_id']} [{$l['status']}] evento: {$l['last_event']} actualizado: {$l['updated_at']}\n";
                }

                $cxcs = $conn->query("SELECT id_cobro, monto_total, estado, fecha_vencimiento FROM cuentas_por_cobrar WHERE id_contrato = {$c['id']} ORDER BY id_cobro DESC");
                while ($cx = $cxcs->fetch_assoc()) {
                    echo "  CxC #{$cx['id_cobro']}: \${$cx['monto_total']} [{$cx['estado']}] vence: {$cx['fecha_vencimiento']}\n";
                }

                $logs = $conn->query("SELECT id, response_payload, created_at FROM wisp_hub_logs WHERE payment_id IS NULL AND request_payload LIKE '%902%' ORDER BY id DESC LIMIT 5");
                if ($logs && $logs->num_rows > 0) {
                    echo "  Logs recientes:\n";
                    while ($l = $logs->fetch_assoc()) {
                        echo "    #{$l['id']} [{$l['created_at']}]: " . substr($l['response_payload'], 0, 100) . "\n";
                    }
                }
            }
        } else {
            echo "No hay contratos para V20788775. Ejecuta ?accion=setup primero.\n";
        }
        break;

    case 'expire':
        echo "Venciendo deudas...\n\n";

        $contratos = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test' AND estado = 'ACTIVO'");
        if ($contratos && $r = $contratos->fetch_assoc()) {
            $cid = $r['id'];
            $fecha = date('Y-m-d', strtotime('-30 days'));
            $conn->query("UPDATE cuentas_por_cobrar SET fecha_vencimiento = '$fecha' WHERE id_contrato = $cid AND estado = 'PENDIENTE'");
            echo "Cuentas por cobrar del contrato #$cid vencidas al $fecha.\n";
            echo "\nAhora ejecuta el cron:\n";
            echo "  php cron/cortar_servicios_vencidos.php 0\n";
            echo "\nO desde el navegador, usa ?accion=cron para ejecutarlo directamente.\n";
        } else {
            echo "No hay contratos ACTIVOS para V20788775.\n";
        }
        break;

    case 'cron':
        echo "Ejecutando cron de corte de servicios...\n\n";

        $cronScript = dirname(__DIR__) . '/cron/cortar_servicios_vencidos.php';
        if (file_exists($cronScript)) {
            $output = [];
            $exitCode = 0;
            exec("php " . escapeshellarg($cronScript) . " 0 2>&1", $output, $exitCode);
            foreach ($output as $line) {
                echo $line . "\n";
            }
            echo "\nExit code: $exitCode\n";
        } else {
            echo "Script cron no encontrado: $cronScript\n";
        }
        break;

    case 'pago':
        echo "Simulando pago aprobado y activacion en WispHub...\n\n";

        $contratos = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test'");
        if (!$contratos || $contratos->num_rows === 0) {
            echo "No hay contrato. Ejecuta ?accion=setup primero.\n";
            break;
        }
        $cid = $contratos->fetch_assoc()['id'];

        $links = $conn->query("SELECT wisp_account_id FROM wisp_hub_links WHERE contract_id = $cid ORDER BY id DESC LIMIT 1");
        if (!$links || $links->num_rows === 0) {
            echo "No hay wisp_hub_links para este contrato.\n";
            break;
        }
        $accountId = $links->fetch_assoc()['wisp_account_id'];

        echo "Servicio: ID $accountId\n\n";

        $amount = 17.50;
        $reference = 'TEST-' . time();
        $paymentDate = date('Y-m-d H:i');

        // 1. Mostrar estado antes
        echo "1. Estado antes:\n";
        $bal = $wispClient->getServiceBalance($accountId);
        echo "   Estado: " . ($bal['data']['estado'] ?? 'N/A') . "\n";
        echo "   Facturas pendientes: " . count($bal['data']['facturas'] ?? []) . "\n";
        echo "   Saldo: " . ($bal['data']['saldo'] ?? 'N/A') . "\n\n";

        // 2. Flujo completo: registrar pago + activar
        echo "2. registerPaymentAndActivate(amount=$$amount, ref=$reference)...\n";
        $result = $wispClient->registerPaymentAndActivate($accountId, $amount, $reference, $paymentDate);
        echo "   Facturas encontradas: " . ($result['invoices_found'] ?? 0) . "\n";
        foreach ($result['payments_registered'] ?? [] as $p) {
            echo "   Pago factura #{$p['invoice_id']}: HTTP {$p['status']}\n";
        }
        echo "   Activacion: HTTP " . ($result['activation']['status'] ?? 'error');
        if (!empty($result['activation']['data']['message'])) {
            echo " ({$result['activation']['data']['message']})";
        }
        echo "\n\n";

        // 3. Estado después
        echo "3. Estado después:\n";
        $bal2 = $wispClient->getServiceBalance($accountId);
        echo "   Estado: " . ($bal2['data']['estado'] ?? 'N/A') . "\n";
        echo "   Facturas pendientes: " . count($bal2['data']['facturas'] ?? []) . "\n";
        echo "   Saldo: " . ($bal2['data']['saldo'] ?? 'N/A') . "\n\n";

        // 4. Actualizar estado local
        $ok = ($result['activation']['status'] ?? 0) === 200;
        if ($ok) {
            $newStatus = 'ACTIVE';
            $newContractStatus = 'ACTIVO';
            if (!empty($result['activation']['data']['message']) && $result['activation']['data']['message'] === 'Servicio ya activo') {
                $newStatus = 'ACTIVE';
                $newContractStatus = 'ACTIVO';
            }
            $conn->query("UPDATE wisp_hub_links SET status = '$newStatus', last_event = 'registerPaymentAndActivate', updated_at = NOW() WHERE contract_id = $cid AND wisp_account_id = '$accountId'");
            $conn->query("UPDATE contratos SET estado = '$newContractStatus' WHERE id = $cid");
        }

        echo "4. Resultado local:\n";
        echo "   WispHub: " . ($ok ? "OK" : "ERROR") . "\n";
        $newLink = $conn->query("SELECT status FROM wisp_hub_links WHERE contract_id = $cid ORDER BY id DESC LIMIT 1")->fetch_assoc();
        echo "   Estado local: " . ($newLink['status'] ?? 'N/A') . "\n";
        $ctr = $conn->query("SELECT estado FROM contratos WHERE id = $cid")->fetch_assoc();
        echo "   Contrato: " . ($ctr['estado'] ?? 'N/A') . "\n";

        echo "\n=== PRUEBA COMPLETADA ===\n";
        break;

    case 'cortar':
        echo "Cortando servicio en WispHub...\n\n";

        $contratos = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test'");
        if (!$contratos || $contratos->num_rows === 0) {
            echo "No hay contrato. Ejecuta ?accion=setup primero.\n";
            break;
        }
        $cid = $contratos->fetch_assoc()['id'];

        $links = $conn->query("SELECT wisp_account_id FROM wisp_hub_links WHERE contract_id = $cid ORDER BY id DESC LIMIT 1");
        if (!$links || $links->num_rows === 0) {
            echo "No hay wisp_hub_links.\n";
            break;
        }
        $accountId = $links->fetch_assoc()['wisp_account_id'];

        echo "Servicio a cortar: ID $accountId\n\n";

        echo "suspendService($accountId)...\n";
        $suspendRes = $wispClient->suspendService($accountId, 'Corte por vencimiento - prueba');
        echo "HTTP " . ($suspendRes['status'] ?? 'error') . "\n";
        echo json_encode($suspendRes['data'] ?? $suspendRes['error'] ?? '', JSON_UNESCAPED_UNICODE) . "\n\n";

        $ok = (($suspendRes['status'] ?? 0) === 200 || ($suspendRes['status'] ?? 0) === 201);
        if ($ok) {
            $conn->query("UPDATE wisp_hub_links SET status = 'SUSPENDED', last_event = 'manual.suspend', updated_at = NOW() WHERE contract_id = $cid AND wisp_account_id = '$accountId'");
            $conn->query("UPDATE contratos SET estado = 'SUSPENDIDO' WHERE id = $cid");

            $logPayload = json_encode(['action' => 'manual_suspend', 'service_id' => $accountId]);
            $logResponse = json_encode($suspendRes);
            $stmt = $conn->prepare("INSERT INTO wisp_hub_logs (payment_id, request_payload, response_payload, created_at) VALUES (NULL, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ss", $logPayload, $logResponse);
                $stmt->execute();
                $stmt->close();
            }
        }

        echo "Resultado: " . ($ok ? "CORTADO OK" : "ERROR") . "\n";
        echo "Verifica en WispHub: Clientes → ID 902 → Estado debe ser 'Suspendido'\n";
        break;

    case 'clean':
        $old = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test'");
        while ($row = $old->fetch_assoc()) {
            $cid = $row['id'];
            $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IN (SELECT id_reporte FROM pagos_reportados WHERE cedula_titular = '$cedula_test')");
            $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IS NULL");
            $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $cid");
            $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $cid)");
            $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $cid");
            $conn->query("DELETE FROM pagos_reportados WHERE cedula_titular = '$cedula_test'");
            $conn->query("DELETE FROM contratos WHERE id = $cid");
        }
        echo "Todos los datos de V20788775 eliminados.\n";
        break;

    case 'test_corte':
        echo "═══════════════════════════════════════════════════════════\n";
        echo "  PRUEBA COMPLETA: Corte por vencimiento\n";
        echo "═══════════════════════════════════════════════════════════\n\n";

        // 1. Estado actual del servicio en WispHub
        echo "─── 1. Estado del servicio WispHub ID 902 ────────────\n";
        $balance = $wispClient->getServiceBalance($wisp_service_id);
        $estado_wh = $balance['data']['estado'] ?? 'desconocido';
        $facturas_wh = $balance['data']['facturas'] ?? [];
        echo "   Estado: {$estado_wh}\n";
        echo "   Facturas pendientes: " . count($facturas_wh) . "\n";
        echo "   Saldo: \$" . ($balance['data']['saldo'] ?? 'N/A') . "\n\n";

        // 2. Preparar datos locales
        echo "─── 2. Preparar datos locales ────────────────────────\n";

        // Limpiar datos previos
        $old = $conn->query("SELECT id FROM contratos WHERE cedula = '$cedula_test'");
        while ($row = $old->fetch_assoc()) {
            $cid = $row['id'];
            $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IN (SELECT id_reporte FROM pagos_reportados WHERE cedula_titular = '$cedula_test')");
            $conn->query("DELETE FROM wisp_hub_logs WHERE payment_id IS NULL");
            $conn->query("DELETE FROM wisp_hub_links WHERE contract_id = $cid");
            $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN (SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $cid)");
            $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_contrato = $cid");
            $conn->query("DELETE FROM pagos_reportados WHERE cedula_titular = '$cedula_test'");
            $conn->query("DELETE FROM contratos WHERE id = $cid");
        }
        echo "   Datos previos eliminados.\n";

        $sql = "INSERT INTO contratos (
            cedula, nombre_completo, id_municipio, id_parroquia, id_plan, monto_plan,
            vendedor_texto, direccion, fecha_instalacion, estado, monto_instalacion,
            monto_pagar, monto_pagado, instalador, tipo_conexion, mac_onu
        ) VALUES ('$cedula_test', 'CLIENTE OFICINA PRUEBA', 1, 1, 4, 650.00,
                  'SISTEMA', 'DIRECCION DE PRUEBA', NOW(), 'ACTIVO', 0, 0, 0,
                  'SISTEMA', 'FTTH', 'ONU_PRUEBA_OFICINA')";
        $conn->query($sql);
        $id_contrato = $conn->insert_id;
        echo "   ✅ Contrato #{$id_contrato} creado (ACTIVO)\n";

        $conn->query("INSERT INTO wisp_hub_links (payment_id, contract_id, wisp_account_id, status, created_at)
                      VALUES (NULL, $id_contrato, '$wisp_service_id', 'ACTIVE', NOW())");
        echo "   ✅ wisp_hub_links: account_id={$wisp_service_id} (ACTIVE)\n";

        $cxc_fecha = date('Y-m-d', strtotime('-30 days'));
        $conn->query("INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen)
                      VALUES ($id_contrato, '$cxc_fecha', '$cxc_fecha', 17.50, 'PENDIENTE', 'SISTEMA')");
        echo "   ✅ CxC vencida desde {$cxc_fecha}: \$17.50 (PENDIENTE)\n\n";

        // 3. Ejecutar el cron de corte
        echo "─── 3. Ejecutar cron de corte ────────────────────────\n";
        $cronScript = dirname(__DIR__) . '/cron/cortar_servicios_vencidos.php';
        $output = [];
        $exitCode = 0;
        exec("php " . escapeshellarg($cronScript) . " 0 2>&1", $output, $exitCode);
        foreach ($output as $line) {
            echo "   {$line}\n";
        }
        echo "\n";

        // 4. Estado después
        echo "─── 4. Estado después del corte ─────────────────────\n";

        // Local
        $ctr = $conn->query("SELECT estado FROM contratos WHERE id = $id_contrato")->fetch_assoc();
        echo "   Contrato #{$id_contrato}: " . ($ctr['estado'] ?? 'N/A') . "\n";

        $lk = $conn->query("SELECT status, last_event FROM wisp_hub_links WHERE contract_id = $id_contrato ORDER BY id DESC LIMIT 1")->fetch_assoc();
        echo "   wisp_hub_links: [{$lk['status']}] evento: {$lk['last_event']}\n";

        $cxc = $conn->query("SELECT id_cobro, estado FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato ORDER BY id_cobro DESC LIMIT 1")->fetch_assoc();
        echo "   CxC #{$cxc['id_cobro']}: [{$cxc['estado']}]\n";

        // WispHub
        $balance2 = $wispClient->getServiceBalance($wisp_service_id);
        echo "   WispHub ID {$wisp_service_id}: {$balance2['data']['estado']}\n\n";

        // 5. Conclusión
        echo "─── 5. Resultado ─────────────────────────────────────\n";
        $localOk = ($ctr['estado'] ?? '') === 'SUSPENDIDO';
        $whOk = ($balance2['data']['estado'] ?? '') === 'Suspendido' || ($balance2['data']['estado'] ?? '') === 'Suspendido (Corte)';

        if ($localOk && $whOk) {
            echo "   ✅ CORTE COMPLETO: Servicio suspendido en WispHub y BD local\n";
        } elseif ($whOk && !$localOk) {
            echo "   ⚠️  WispHub suspendido, pero BD local no se actualizó\n";
        } elseif (!$whOk && $localOk) {
            echo "   ⚠️  BD local actualizada, pero WispHub no suspendió\n";
        } else {
            echo "   ❌ No se produjo el corte. Revisa logs arriba.\n";
        }

        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "  Para reactivar: ?accion=pago  o  tests/test_full_flow.php\n";
        echo "═══════════════════════════════════════════════════════════\n";
        break;

    default:
        echo "Acciones disponibles:\n";
        echo "  ?accion=setup       - Crear contrato vinculado a WispHub ID 902\n";
        echo "  ?accion=status      - Ver estado actual\n";
        echo "  ?accion=expire      - Vencer deudas (para probar corte)\n";
        echo "  ?accion=pago        - Simular pago y activar en WispHub\n";
        echo "  ?accion=cortar      - Cortar servicio en WispHub\n";
        echo "  ?accion=cron        - Ejecutar cron de corte\n";
        echo "  ?accion=test_corte  - Prueba completa: setup→expire→cron→verificar\n";
        echo "  ?accion=clean       - Limpiar todo\n";
        break;
}

$conn->close();
echo "</pre>";
