<?php
// portal/referencia_helper.php

function getDb(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $cfg = @include __DIR__ . '/../config/database.php';
    if (!$cfg) {
        error_log('[referencia_helper] config/database.php no encontrado');
        return null;
    }
    $host     = $cfg['host'] ?? 'localhost';
    $port     = $cfg['port'] ?? 3306;
    $dbname   = $cfg['dbname'] ?? 'portal_pagos';
    $user     = $cfg['user'] ?? 'root';
    $password = $cfg['password'] ?? '';
    $charset  = $cfg['charset'] ?? 'utf8mb4';
    try {
        try {
            // Forma recomendada: conectar directo a la base de datos (ideal para cPanel/producción)
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}", $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            // Si falla (por ej. en local donde no existe), conectamos sin BD e intentamos crearla
            $pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
            $pdo->exec("USE `{$dbname}`");
        }
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
        // Migraciones para tablas existentes
        try {
            $pdo->exec("ALTER TABLE pagos_registrados MODIFY COLUMN referencia VARCHAR(15) NOT NULL");
        } catch (PDOException $e) {
            // Ya migrado
        }
        try {
            $pdo->exec("ALTER TABLE pagos_registrados ADD COLUMN facturas VARCHAR(100) NOT NULL DEFAULT '' AFTER referencia");
        } catch (PDOException $e) {
            // Ya migrado
        }
        try {
            $pdo->exec("ALTER TABLE pagos_registrados ADD COLUMN monto_banco_bs DECIMAL(15,2) DEFAULT NULL AFTER facturas");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE pagos_registrados ADD COLUMN fecha_banco VARCHAR(20) DEFAULT NULL AFTER monto_banco_bs");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE pagos_registrados ADD COLUMN banco_descripcion VARCHAR(255) DEFAULT NULL AFTER fecha_banco");
        } catch (PDOException $e) {}
        try {
            $pdo->exec("ALTER TABLE pagos_registrados ADD COLUMN fecha_promesa DATE DEFAULT NULL AFTER banco_descripcion");
        } catch (PDOException $e) {}
        return $pdo;
    } catch (PDOException $e) {
        error_log('[referencia_helper] DB connection failed: ' . $e->getMessage());
        return null;
    }
}

function getReferenciaInfo(string $referencia): ?array {
    $pdo = getDb();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM pagos_registrados WHERE referencia = ? LIMIT 1");
        $stmt->execute([$referencia]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('[referencia_helper] getReferenciaInfo error: ' . $e->getMessage());
        return null;
    }
}

function referenciaYaUsada(string $referencia): bool {
    return getReferenciaInfo($referencia) !== null;
}

function guardarPago(
    string $cliente,
    string $ipServicio,
    string $fechaPago,
    string $zona,
    float  $totalCobrado,
    string $formaPago,
    string $referencia,
    float  $total,
    ?string $accion,
    string $serviceId,
    ?int $idBanco = null,
    string $facturas = '',
    ?float $montoBancoBs = null,
    ?string $fechaBanco = null,
    ?string $bancoDescripcion = null,
    ?string $fechaPromesa = null
): bool {
    $pdo = getDb();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO pagos_registrados
            (cliente, ip_servicio, fecha_pago, estado, zona, total_cobrado, forma_pago, referencia, facturas, monto_banco_bs, fecha_banco, banco_descripcion, fecha_promesa, total, accion, service_id, id_banco)
            VALUES (?, ?, ?, 'Pagada', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            cliente = VALUES(cliente), ip_servicio = VALUES(ip_servicio), fecha_pago = VALUES(fecha_pago),
            estado = 'Pagada', zona = VALUES(zona), total_cobrado = VALUES(total_cobrado),
            forma_pago = VALUES(forma_pago), facturas = VALUES(facturas),
            monto_banco_bs = VALUES(monto_banco_bs), fecha_banco = VALUES(fecha_banco),
            banco_descripcion = VALUES(banco_descripcion), fecha_promesa = VALUES(fecha_promesa),
            total = VALUES(total), accion = VALUES(accion), service_id = VALUES(service_id),
            id_banco = VALUES(id_banco)");
        $stmt->execute([$cliente, $ipServicio, $fechaPago, $zona, $totalCobrado, $formaPago, $referencia, $facturas, $montoBancoBs, $fechaBanco, $bancoDescripcion, $fechaPromesa, $total, $accion, $serviceId, $idBanco]);
        return true;
    } catch (PDOException $e) {
        error_log('[referencia_helper] insert/upsert error: ' . $e->getMessage());
        return false;
    }
}
