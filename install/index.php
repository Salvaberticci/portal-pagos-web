<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación — Portal de Pagos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a2e;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #1e2a4a;
            border: 1px solid #2a3a5c;
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .subtitle {
            color: #a0a0b0;
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        .step {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .step.ok { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .step.err { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .step.pending { background: rgba(255, 255, 255, 0.05); color: #a0a0b0; }
        .step .icon { font-size: 1.2rem; width: 24px; text-align: center; }
        .step .msg { flex: 1; }
        .step .detail { font-size: 0.8rem; color: #a0a0b0; }
        .footer {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #2a3a5c;
            font-size: 0.85rem;
            color: #a0a0b0;
        }
        .footer a { color: #667eea; }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #e0e0e0;
        }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Instalación</h1>
        <p class="subtitle">Configuración de la base de datos local del Portal de Pagos</p>

        <?php
        $cfg = @include __DIR__ . '/../config/database.php';
        $allOk = true;

        if (!$cfg || empty($cfg['host']) || empty($cfg['user'])) {
            echo '<div class="step err"><span class="icon">✗</span><span class="msg">No se encontró config/database.php. Crea el archivo con tus credenciales MySQL.</span></div>';
            $allOk = false;
        } else {
            $host     = $cfg['host'];
            $port     = $cfg['port'] ?? 3306;
            $dbname   = $cfg['dbname'];
            $user     = $cfg['user'];
            $password = $cfg['password'] ?? '';
            $charset  = $cfg['charset'] ?? 'utf8mb4';

            // Paso 1: Conectar a MySQL
            try {
                $pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                echo '<div class="step ok"><span class="icon">✓</span><span class="msg">Conexión a MySQL exitosa <span class="detail">(' . htmlspecialchars($host) . ':' . $port . ')</span></span></div>';
            } catch (PDOException $e) {
                echo '<div class="step err"><span class="icon">✗</span><span class="msg">Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</span></div>';
                $allOk = false;
            }

            // Paso 2: Crear BD
            if ($allOk) {
                try {
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
                    echo '<div class="step ok"><span class="icon">✓</span><span class="msg">Base de datos <strong>' . htmlspecialchars($dbname) . '</strong> creada o ya existe</span></div>';
                } catch (PDOException $e) {
                    echo '<div class="step err"><span class="icon">✗</span><span class="msg">Error al crear BD: ' . htmlspecialchars($e->getMessage()) . '</span></div>';
                    $allOk = false;
                }
            }

            // Paso 3: Crear tablas
            if ($allOk) {
                try {
                    $pdo->exec("USE `{$dbname}`");
                    $pdo->exec("CREATE TABLE IF NOT EXISTS pagos_registrados (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        cliente VARCHAR(100) NOT NULL,
                        ip_servicio VARCHAR(45) NOT NULL DEFAULT '',
                        fecha_pago DATE NOT NULL,
                        estado VARCHAR(30) NOT NULL DEFAULT 'Pagada',
                        zona VARCHAR(100) NOT NULL DEFAULT '',
                        total_cobrado DECIMAL(10,2) NOT NULL DEFAULT 0,
                        forma_pago VARCHAR(30) DEFAULT NULL,
                        referencia VARCHAR(15) NOT NULL,
                        facturas VARCHAR(100) NOT NULL DEFAULT '',
                        total DECIMAL(10,2) NOT NULL DEFAULT 0,
                        accion VARCHAR(30) DEFAULT NULL,
                        service_id VARCHAR(50) NOT NULL,
                        id_banco INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uk_referencia (referencia)
                    ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$charset}_unicode_ci");
                    echo '<div class="step ok"><span class="icon">✓</span><span class="msg">Tabla <strong>pagos_registrados</strong> creada o ya existe</span></div>';
                } catch (PDOException $e) {
                    echo '<div class="step err"><span class="icon">✗</span><span class="msg">Error al crear tabla: ' . htmlspecialchars($e->getMessage()) . '</span></div>';
                    $allOk = false;
                }
            }
        }
        ?>

        <?php if ($allOk): ?>
        <div class="step ok" style="margin-top:16px;">
            <span class="icon">✅</span>
            <span class="msg"><strong>Instalación completada exitosamente</strong></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="../portal/" class="btn btn-primary">Ir al Portal</a>
            <a href="." class="btn btn-secondary">Re-verificar</a>
        </div>
        <?php else: ?>
        <div style="margin-top:16px;">
            <p style="color:#a0a0b0;margin-bottom:8px;">Verifica que:</p>
            <ul style="color:#a0a0b0;font-size:0.85rem;padding-left:20px;">
                <li>MySQL esté corriendo en el servidor</li>
                <li>Los datos en <code>config/database.php</code> sean correctos</li>
                <li>El usuario MySQL tenga permisos para crear bases de datos</li>
            </ul>
            <a href="." class="btn btn-secondary" style="margin-top:12px;">Reintentar</a>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Base de datos: <strong>portal_pagos</strong></p>
            <p>Configuración: <code>config/database.php</code></p>
            <p style="margin-top:8px;">⚠️ Por seguridad, elimina o protege esta carpeta después de la instalación.</p>
        </div>
    </div>
</body>
</html>
