<?php
/**
 * install/setup.php
 *
 * Script de instalación para la base de datos local.
 * Uso: php install/setup.php
 *      o abrirlo en el navegador desde el servidor web.
 *
 * Crea la base de datos y las tablas necesarias.
 * Antes de ejecutarlo, configura tus credenciales en config/database.php
 */

$cfg = @include __DIR__ . '/../config/database.php';

if (!$cfg || empty($cfg['host']) || empty($cfg['user'])) {
    echo "ERROR: No se pudo leer config/database.php. Verifica que el archivo exista y tenga las credenciales correctas.\n";
    exit(1);
}

$host     = $cfg['host'];
$port     = $cfg['port'] ?? 3306;
$dbname   = $cfg['dbname'];
$user     = $cfg['user'];
$password = $cfg['password'];
$charset  = $cfg['charset'] ?? 'utf8mb4';

echo "============================================\n";
echo "  Instalación BD Portal de Pagos\n";
echo "============================================\n\n";

// 1. Conectar a MySQL (sin BD)
try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "[OK] Conexión a MySQL establecida.\n";
} catch (PDOException $e) {
    echo "ERROR: No se pudo conectar a MySQL: " . $e->getMessage() . "\n";
    echo "Verifica que:\n";
    echo "  - MySQL esté corriendo (servicio 'mysql' o 'mysqld')\n";
    echo "  - Los datos en config/database.php sean correctos\n";
    echo "  - Host: {$host}:{$port}\n";
    echo "  - Usuario: {$user}\n";
    exit(1);
}

// 2. Crear base de datos
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    echo "[OK] Base de datos '{$dbname}' creada o ya existe.\n";
} catch (PDOException $e) {
    echo "ERROR: No se pudo crear la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Seleccionar y crear tablas
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

    echo "[OK] Tabla 'pagos_registrados' creada o ya existe.\n";

} catch (PDOException $e) {
    echo "ERROR: No se pudieron crear las tablas: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n============================================\n";
echo "  Instalación completada exitosamente.\n";
echo "============================================\n";
echo "\nBase de datos: {$dbname}\n";
echo "\nPara usar la BD desde el portal, no necesitas hacer nada más.\n";
echo "El portal se conecta automáticamente usando config/database.php.\n";
echo "\n";
