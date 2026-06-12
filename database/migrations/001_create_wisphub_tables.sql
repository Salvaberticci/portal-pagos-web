-- SQL para crear las tablas de integración WispHub
-- Ejecutar en phpMyAdmin o desde consola MySQL

CREATE TABLE IF NOT EXISTS `wisp_hub_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `request_payload` TEXT,
    `response_payload` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wisp_hub_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT DEFAULT NULL,
    `contract_id` INT NOT NULL,
    `wisp_account_id` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'PENDING',
    `last_event` VARCHAR(100) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_contract_id` (`contract_id`),
    INDEX `idx_wisp_account_id` (`wisp_account_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
