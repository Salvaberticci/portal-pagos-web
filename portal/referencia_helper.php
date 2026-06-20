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
        $pdo->exec("CREATE TABLE IF NOT EXISTS referencias_usadas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referencia VARCHAR(10) NOT NULL,
            service_id VARCHAR(50) NOT NULL,
            monto_usd DECIMAL(10,2) NOT NULL DEFAULT 0,
            metodo_pago VARCHAR(30) DEFAULT NULL,
            id_banco INT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'registrado',
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
        $stmt = $pdo->prepare("SELECT 1 FROM referencias_usadas WHERE referencia = ? AND status IN ('registrado','pendiente') LIMIT 1");
        $stmt->execute([$referencia]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[referencia_helper] check error: ' . $e->getMessage());
        return false;
    }
}

function guardarReferencia(string $referencia, string $serviceId, float $montoUsd, ?string $metodoPago = null, ?int $idBanco = null, string $status = 'registrado'): bool {
    $pdo = getDb();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("INSERT INTO referencias_usadas (referencia, service_id, monto_usd, metodo_pago, id_banco, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$referencia, $serviceId, $montoUsd, $metodoPago, $idBanco, $status]);
        return true;
    } catch (PDOException $e) {
        error_log('[referencia_helper] insert error: ' . $e->getMessage());
        return false;
    }
}
