<?php
// portal/referencia_helper.php

function getDb(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS portal_pagos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE portal_pagos");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pagos_registrados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente VARCHAR(100) NOT NULL,
            ip_servicio VARCHAR(45) NOT NULL DEFAULT '',
            fecha_pago DATE NOT NULL,
            estado VARCHAR(30) NOT NULL DEFAULT 'Pagada',
            zona VARCHAR(100) NOT NULL DEFAULT '',
            total_cobrado DECIMAL(10,2) NOT NULL DEFAULT 0,
            forma_pago VARCHAR(30) DEFAULT NULL,
            referencia VARCHAR(10) NOT NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            accion VARCHAR(30) DEFAULT NULL,
            service_id VARCHAR(50) NOT NULL,
            id_banco INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_referencia (referencia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $pdo;
    } catch (PDOException $e) {
        error_log('[referencia_helper] DB connection failed: ' . $e->getMessage());
        return null;
    }
}

function referenciaYaUsada(string $referencia): bool {
    $pdo = getDb();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM pagos_registrados WHERE referencia = ? LIMIT 1");
        $stmt->execute([$referencia]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[referencia_helper] check error: ' . $e->getMessage());
        return false;
    }
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
    ?int $idBanco = null
): bool {
    $pdo = getDb();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO pagos_registrados
            (cliente, ip_servicio, fecha_pago, estado, zona, total_cobrado, forma_pago, referencia, total, accion, service_id, id_banco)
            VALUES (?, ?, ?, 'Pagada', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cliente, $ipServicio, $fechaPago, $zona, $totalCobrado, $formaPago, $referencia, $total, $accion, $serviceId, $idBanco]);
        return true;
    } catch (PDOException $e) {
        error_log('[referencia_helper] insert error: ' . $e->getMessage());
        return false;
    }
}
