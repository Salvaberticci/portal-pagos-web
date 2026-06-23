<?php
require_once __DIR__ . '/security_helper.php';
@include_once __DIR__ . '/../config/test_mode.php';
if (!defined('TEST_USER_CEDULA')) define('TEST_USER_CEDULA', '');

session_start();

// Solo test user o admin puede ejecutar esto
$cedula = $_SESSION['cliente_cedula'] ?? '';
if ($cedula !== TEST_USER_CEDULA) {
    http_response_code(403);
    exit('Acceso denegado');
}

$excelFile = __DIR__ . '/../Lista de Facturas - SITELCO c.a. aliado comercial de MARATEL_Historico_20Marzo-20Junio.xlsx';

$result = ['procesados' => 0, 'importados' => 0, 'saltados_ref_vacia' => 0, 'saltados_ref_invalida' => 0, 'saltados_duplicado' => 0, 'errores' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $result['errores'][] = 'CSRF inválido';
    } elseif (!file_exists($excelFile)) {
        $result['errores'][] = 'Archivo Excel no encontrado: ' . basename($excelFile);
    } else {
        require __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/referencia_helper.php';

        try {
            $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($excelFile);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            for ($row = 2; $row <= $highestRow; $row++) {
                $result['procesados']++;

                $referencia_raw = (string)$sheet->getCell('M' . $row)->getCalculatedValue();
                $referencia = preg_replace('/\D/', '', $referencia_raw);

                if (empty($referencia_raw)) {
                    $result['saltados_ref_vacia']++;
                    continue;
                }

                if (strlen($referencia) < 6 || strlen($referencia) > 15) {
                    $result['saltados_ref_invalida']++;
                    continue;
                }

                if (getReferenciaInfo($referencia)) {
                    $result['saltados_duplicado']++;
                    continue;
                }

                $fecha_raw = (string)$sheet->getCell('H' . $row)->getCalculatedValue();
                $fecha_dt = DateTime::createFromFormat('d/m/Y H:i', $fecha_raw) ?: DateTime::createFromFormat('d/m/Y', $fecha_raw);
                if (!$fecha_dt) {
                    $result['errores'][] = "Fila $row: fecha inválida '$fecha_raw'";
                    continue;
                }

                $total_cobrado = floatval($sheet->getCell('K' . $row)->getCalculatedValue() ?? 0);
                $total = floatval($sheet->getCell('N' . $row)->getCalculatedValue() ?? 0);

                $accion_val = (string)$sheet->getCell('O' . $row)->getCalculatedValue();
                $accion = $accion_val !== '' ? $accion_val : null;

                $pdo = getDb();
                if (!$pdo) {
                    $result['errores'][] = "Fila $row: error de conexión BD";
                    continue;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO pagos_registrados
                        (cliente, ip_servicio, fecha_pago, estado, zona, total_cobrado, forma_pago, referencia, facturas, total, accion, service_id)
                        VALUES (?, ?, ?, 'Pagada', ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        (string)$sheet->getCell('F' . $row)->getCalculatedValue(),
                        (string)$sheet->getCell('G' . $row)->getCalculatedValue(),
                        $fecha_dt->format('Y-m-d'),
                        (string)$sheet->getCell('J' . $row)->getCalculatedValue(),
                        $total_cobrado,
                        (string)$sheet->getCell('L' . $row)->getCalculatedValue(),
                        $referencia,
                        (string)$sheet->getCell('C' . $row)->getCalculatedValue(),
                        $total,
                        $accion,
                        (string)$sheet->getCell('E' . $row)->getCalculatedValue(),
                    ]);
                    $result['importados']++;
                } catch (PDOException $e) {
                    $errCode = $e->getCode();
                    if ($errCode === '23000' || strpos($e->getMessage(), 'Duplicate') !== false) {
                        $result['saltados_duplicado']++;
                    } else {
                        $result['errores'][] = "Fila $row: " . $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $result['errores'][] = 'Error al leer Excel: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Importar Pagos desde Excel</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; padding: 40px 20px; }
        .card { background: rgba(30,41,59,0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; max-width: 720px; margin: 0 auto; padding: 32px; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        .stat { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .stat:last-child { border-bottom: none; }
        .badge-success { background: #10b981; padding: 2px 10px; border-radius: 999px; font-size: 0.875rem; }
        .badge-warning { background: #f59e0b; padding: 2px 10px; border-radius: 999px; font-size: 0.875rem; }
        .badge-danger { background: #ef4444; padding: 2px 10px; border-radius: 999px; font-size: 0.875rem; }
        .btn-primary { background: linear-gradient(135deg,#2563eb,#1e40af); border: none; padding: 12px 28px; border-radius: 12px; color: #fff; font-weight: 600; }
        .btn-primary:hover { background: linear-gradient(135deg,#3b82f6,#2563eb); }
        .errores-list { margin-top: 16px; max-height: 300px; overflow-y: auto; font-size: 0.85rem; color: #fca5a5; }
        .errores-list li { padding: 2px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1><i class="fas fa-file-import me-2"></i>Importar Pagos desde Excel</h1>
        <p style="color:#94a3b8;font-size:0.9rem;">
            Archivo: <code><?php echo htmlspecialchars(basename($excelFile)); ?></code>
        </p>

        <?php if (!empty($result['errores']) || $result['procesados'] > 0): ?>
            <div style="margin: 20px 0; padding: 16px; background: rgba(255,255,255,0.03); border-radius: 12px;">
                <div class="stat"><span>Procesados</span><span class="badge-warning"><?php echo $result['procesados']; ?></span></div>
                <div class="stat"><span>Importados</span><span class="badge-success"><?php echo $result['importados']; ?></span></div>
                <div class="stat"><span>Saltados (ref vacía)</span><span><?php echo $result['saltados_ref_vacia']; ?></span></div>
                <div class="stat"><span>Saltados (ref inválida)</span><span><?php echo $result['saltados_ref_invalida']; ?></span></div>
                <div class="stat"><span>Saltados (duplicado)</span><span><?php echo $result['saltados_duplicado']; ?></span></div>
                <?php if (!empty($result['errores'])): ?>
                    <div class="stat"><span>Errores</span><span class="badge-danger"><?php echo count($result['errores']); ?></span></div>
                    <ul class="errores-list">
                        <?php foreach (array_slice($result['errores'], 0, 50) as $e): ?>
                            <li><?php echo htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($result['errores']) > 50): ?>
                            <li>... y <?php echo count($result['errores']) - 50; ?> más</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <button type="submit" class="btn-primary w-100">
                <?php if ($result['procesados'] > 0): ?>
                    <i class="fas fa-redo me-2"></i>Re-importar
                <?php else: ?>
                    <i class="fas fa-play me-2"></i>Iniciar Importación
                <?php endif; ?>
            </button>
        </form>

        <p style="margin-top:16px;font-size:0.8rem;color:#64748b;">
            Las referencias se limpian a solo dígitos. Duplicados se omiten automáticamente.
        </p>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>
