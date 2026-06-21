<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migrar referencias históricas</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #e0e0e0; display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px; }
        .card { background:#1e2a4a;border:1px solid #2a3a5c;border-radius:16px;padding:40px;max-width:600px;width:100%; }
        .ok { color:#28a745; } .err { color:#dc3545; }
        code { background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;font-size:0.9em; }
    </style>
</head>
<body>
<div class="card">
    <h1>Migrar referencias históricas</h1>
    <p style="color:#a0a0b0;">Inserta en la BD local las referencias de pagos realizados antes de crear la base de datos, para evitar que se reutilicen.</p>

    <?php
    $cfg = @include __DIR__ . '/../config/database.php';
    if (!$cfg) { echo '<p class="err">ERROR: No se encontró config/database.php</p>'; exit; }

    try {
        $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}", $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("USE `{$cfg['dbname']}`");

        $inserted = 0;
        $historico = [];

        // ─── Agrega aquí las referencias históricas conocidas ───
        // Formato: [cliente, ip_servicio, fecha_pago, zona, total_cobrado, forma_pago, referencia, total, accion, service_id, id_banco]
        // Referencias de pagos reales existentes en WispHub antes del portal
        $historico[] = ['Cliente Prueba (V20788775)', '', '2026-06-19', '', 0.17, 'Pago Móvil', '0677266323803', 0.17, 'completo', '902', 9];
        $historico[] = ['Maire Villegas (V14800836)',  '', '2026-06-19', '', 20.00, 'Pago Móvil', '139627',        20.00, 'completo', '870', 9];
        $historico[] = ['Cliente (referencia 851396)',  '', '2026-06-20', '', 12.147, 'Pago Móvil', '851396',      12.147, 'completo', '0', 9];
        // ─────────────────────────────────────────────────────────

        $stmt = $pdo->prepare("INSERT IGNORE INTO pagos_registrados
            (cliente, ip_servicio, fecha_pago, estado, zona, total_cobrado, forma_pago, referencia, total, accion, service_id, id_banco)
            VALUES (?, ?, ?, 'Pagada', ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($historico as $row) {
            $stmt->execute($row);
            if ($stmt->rowCount() > 0) $inserted++;
        }

        echo "<p class=\"ok\">✓ $inserted referencia(s) histórica(s) insertada(s) en la base de datos.</p>";
        echo "<p style=\"color:#a0a0b0;\">Ya no se podrán reutilizar para nuevos pagos.</p>";

    } catch (PDOException $e) {
        echo '<p class="err">ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>

    <p style="margin-top:24px;border-top:1px solid #2a3a5c;padding-top:16px;color:#a0a0b0;font-size:0.85rem;">
        Para agregar más referencias, edita <code>install/migrate_refs.php</code> y agrega filas al array <code>$historico</code>.
    </p>
</div>
</body>
</html>
