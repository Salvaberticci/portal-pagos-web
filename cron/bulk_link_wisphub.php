<?php
/**
 * cron/bulk_link_wisphub.php
 *
 * Herramienta CLI para mapear contratos locales a servicios WispHub.
 *
 * Uso:
 *   php cron/bulk_link_wisphub.php list           - Lista contratos sin link
 *   php cron/bulk_link_wisphub.php list-unlinked   - Misma funcionalidad, más detalle
 *   php cron/bulk_link_wisphub.php export-csv      - Genera CSV para llenar wisp_account_id
 *   php cron/bulk_link_wisphub.php import-csv <ruta> - Importa CSV con columnas: contract_id,wisp_account_id
 *   php cron/bulk_link_wisphub.php create-skeleton  - Crea links vacíos (PENDING) para todos los contratos ACTIVOS sin link
 *   php cron/bulk_link_wisphub.php stats            - Muestra estadísticas de integración
 *
 * Formato CSV:
 *   contract_id,wisp_account_id
 *   123,WH-ABC-123
 *   456,WH-DEF-456
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../paginas/conexion.php';

$action = $argv[1] ?? 'list';

switch ($action) {
    case 'list':
    case 'list-unlinked':
        cmdListUnlinked($conn, $action === 'list-unlinked');
        break;
    case 'export-csv':
        cmdExportCsv($conn);
        break;
    case 'import-csv':
        if (empty($argv[2])) {
            echo "Uso: php cron/bulk_link_wisphub.php import-csv <ruta.csv>\n";
            exit(1);
        }
        cmdImportCsv($conn, $argv[2]);
        break;
    case 'create-skeleton':
        cmdCreateSkeleton($conn);
        break;
    case 'stats':
        cmdStats($conn);
        break;
    default:
        echo "Acción no reconocida: $action\n";
        echo "Acciones: list, list-unlinked, export-csv, import-csv, create-skeleton, stats\n";
        exit(1);
}

$conn->close();

// ─── Funciones ────────────────────────────────────────────────────────────────

function cmdListUnlinked(mysqli $conn, bool $detailed): void
{
    $result = $conn->query("
        SELECT c.id, c.cedula, c.nombre_completo, c.email, c.estado
        FROM contratos c
        LEFT JOIN wisp_hub_links wl ON wl.contract_id = c.id
        WHERE wl.id IS NULL
          AND c.estado = 'ACTIVO'
        ORDER BY c.id
    ");
    if (!$result) {
        die("Error: " . $conn->error . "\n");
    }

    $total = $result->num_rows;
    echo "Contratos ACTIVOS sin link WispHub: $total\n\n";

    if ($detailed) {
        printf("%-6s %-14s %-30s %-30s %s\n", 'ID', 'Cédula', 'Nombre', 'Email', 'Estado');
        echo str_repeat('-', 100) . "\n";
        while ($row = $result->fetch_assoc()) {
            printf("%-6d %-14s %-30s %-30s %s\n",
                $row['id'], $row['cedula'], substr($row['nombre_completo'] ?? '', 0, 28),
                substr($row['email'] ?? '', 0, 28), $row['estado']);
        }
    }

    echo "\nPara exportar como CSV: php cron/bulk_link_wisphub.php export-csv\n";
}

function cmdExportCsv(mysqli $conn): void
{
    $result = $conn->query("
        SELECT c.id, c.cedula, c.nombre_completo, c.email
        FROM contratos c
        LEFT JOIN wisp_hub_links wl ON wl.contract_id = c.id
        WHERE wl.id IS NULL
          AND c.estado = 'ACTIVO'
        ORDER BY c.id
    ");
    if (!$result) {
        die("Error: " . $conn->error . "\n");
    }

    $filename = 'wisphub_unlinked_' . date('Ymd_His') . '.csv';
    $fh = fopen($filename, 'w');
    if (!$fh) {
        die("No se pudo crear $filename\n");
    }

    fputcsv($fh, ['contract_id', 'cedula', 'nombre', 'email', 'wisp_account_id']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($fh, [$row['id'], $row['cedula'], $row['nombre_completo'], $row['email'], '']);
    }
    fclose($fh);

    echo "Exportados " . $result->num_rows . " contratos a $filename\n";
    echo "Llena la columna wisp_account_id y ejecuta:\n";
    echo "  php cron/bulk_link_wisphub.php import-csv $filename\n";
}

function cmdImportCsv(mysqli $conn, string $path): void
{
    if (!file_exists($path)) {
        die("Archivo no encontrado: $path\n");
    }

    $fh = fopen($path, 'r');
    if (!$fh) {
        die("No se pudo abrir $path\n");
    }

    $header = fgetcsv($fh);
    if ($header === false || count($header) < 2) {
        die("CSV inválido. Requiere columnas: contract_id,wisp_account_id\n");
    }

    // Normalizar header a lowercase
    $header = array_map('strtolower', $header);
    $colContractId = array_search('contract_id', $header);
    $colAccountId = array_search('wisp_account_id', $header);

    if ($colContractId === false || $colAccountId === false) {
        die("CSV debe tener columnas 'contract_id' y 'wisp_account_id'\n");
    }

    $insertados = 0;
    $errores = 0;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO wisp_hub_links (contract_id, wisp_account_id, status, last_event, created_at) VALUES (?, ?, 'ACTIVE', 'bulk_import', NOW())");
        if (!$stmt) {
            throw new Exception("Prepare: " . $conn->error);
        }

        while (($row = fgetcsv($fh)) !== false) {
            $contractId = intval(trim($row[$colContractId]));
            $accountId = trim($row[$colAccountId]);

            if ($contractId <= 0 || empty($accountId)) {
                $errores++;
                continue;
            }

            // Verificar que el contrato existe
            $check = $conn->query("SELECT id FROM contratos WHERE id = $contractId");
            if (!$check || $check->num_rows === 0) {
                echo "  [SKIP] Contrato #$contractId no existe\n";
                $errores++;
                continue;
            }

            // Verificar duplicado
            $dup = $conn->query("SELECT id FROM wisp_hub_links WHERE wisp_account_id = '$accountId'");
            if ($dup && $dup->num_rows > 0) {
                echo "  [SKIP] wisp_account_id '$accountId' ya existe\n";
                $errores++;
                continue;
            }

            $stmt->bind_param("is", $contractId, $accountId);
            if ($stmt->execute()) {
                $insertados++;
            } else {
                echo "  [ERROR] Contrato #$contractId: " . $stmt->error . "\n";
                $errores++;
            }
        }

        $conn->commit();
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error en importación: " . $e->getMessage() . "\n");
    }

    fclose($fh);
    echo "\nImportación completada:\n";
    echo "  Insertados: $insertados\n";
    echo "  Errores/saltados: $errores\n";
}

function cmdCreateSkeleton(mysqli $conn): void
{
    $result = $conn->query("
        SELECT c.id
        FROM contratos c
        LEFT JOIN wisp_hub_links wl ON wl.contract_id = c.id
        WHERE wl.id IS NULL
          AND c.estado = 'ACTIVO'
        ORDER BY c.id
    ");
    if (!$result) {
        die("Error: " . $conn->error . "\n");
    }

    $total = $result->num_rows;
    echo "Creando links esqueleto (PENDING) para $total contratos...\n";

    $creados = 0;
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO wisp_hub_links (contract_id, wisp_account_id, status, last_event, created_at) VALUES (?, '', 'PENDING', 'skeleton_created', NOW())");
        if (!$stmt) {
            throw new Exception("Prepare: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $stmt->bind_param("i", $row['id']);
            if ($stmt->execute()) {
                $creados++;
            }
        }

        $conn->commit();
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage() . "\n");
    }

    echo "Creados $creados links esqueletos (status=PENDING, wisp_account_id='')\n";
    echo "Los links deben completarse con wisp_account_id mediante import-csv o el panel admin.\n";
}

function cmdStats(mysqli $conn): void
{
    echo "=== Estadísticas de Integración WispHub ===\n\n";

    $total = $conn->query("SELECT COUNT(*) AS n FROM contratos WHERE estado = 'ACTIVO'");
    $conLink = $conn->query("SELECT COUNT(DISTINCT wl.contract_id) AS n FROM wisp_hub_links wl INNER JOIN contratos c ON c.id = wl.contract_id WHERE c.estado = 'ACTIVO' AND wl.wisp_account_id != ''");
    $sinLink = $conn->query("SELECT COUNT(*) AS n FROM contratos c LEFT JOIN wisp_hub_links wl ON wl.contract_id = c.id WHERE wl.id IS NULL AND c.estado = 'ACTIVO'");
    $linkVacio = $conn->query("SELECT COUNT(DISTINCT wl.contract_id) AS n FROM wisp_hub_links wl INNER JOIN contratos c ON c.id = wl.contract_id WHERE c.estado = 'ACTIVO' AND wl.wisp_account_id = ''");
    $suspendidos = $conn->query("SELECT COUNT(DISTINCT wl.contract_id) AS n FROM wisp_hub_links wl INNER JOIN contratos c ON c.id = wl.contract_id WHERE c.estado = 'SUSPENDIDO'");

    $t = $total->fetch_assoc()['n'] ?? 0;
    $cl = $conLink->fetch_assoc()['n'] ?? 0;
    $sl = $sinLink->fetch_assoc()['n'] ?? 0;
    $lv = $linkVacio->fetch_assoc()['n'] ?? 0;
    $sp = $suspendidos->fetch_assoc()['n'] ?? 0;

    printf("  Contratos ACTIVOS:          %5d\n", $t);
    printf("  Con WispHub link activo:    %5d (%.1f%%)\n", $cl, $t > 0 ? $cl / $t * 100 : 0);
    printf("  Sin link (sin integrar):    %5d\n", $sl);
    printf("  Link vacío (PENDING):       %5d\n", $lv);
    printf("  Suspendidos en WispHub:     %5d\n", $sp);
    echo "\n";
    echo "Logs de WispHub registrados:\n";
    $logCount = $conn->query("SELECT DATE(created_at) AS fecha, COUNT(*) AS n FROM wisp_hub_logs GROUP BY DATE(created_at) ORDER BY fecha DESC LIMIT 10");
    if ($logCount && $logCount->num_rows > 0) {
        printf("  %-12s %s\n", 'Fecha', 'Cantidad');
        while ($r = $logCount->fetch_assoc()) {
            printf("  %-12s %d\n", $r['fecha'], $r['n']);
        }
    } else {
        echo "  (sin logs)\n";
    }
}
