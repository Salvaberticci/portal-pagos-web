<?php
require_once '../conexion.php';

$queries = [];

$queries['mikrotik_routers'] = "CREATE TABLE IF NOT EXISTS `mikrotik_routers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo del router',
  `ip` VARCHAR(45) NOT NULL COMMENT 'IP o hostname del MikroTik',
  `puerto` INT(5) NOT NULL DEFAULT 8728 COMMENT 'Puerto API (8728 normal, 8729 SSL)',
  `usuario` VARCHAR(100) NOT NULL COMMENT 'Usuario con permisos api,read,write',
  `contrasena` VARCHAR(255) NOT NULL COMMENT 'Contraseña del usuario API',
  `descripcion` TEXT NULL COMMENT 'Notas adicionales (sector, nodo, etc.)',
  `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `dry_run` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Modo prueba (no ejecuta), 0=Real',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$queries['mikrotik_logs'] = "CREATE TABLE IF NOT EXISTS `mikrotik_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_router` INT(11) NULL COMMENT 'FK a mikrotik_routers',
  `id_contrato` INT(11) NULL COMMENT 'FK a contratos',
  `nombre_cliente` VARCHAR(150) NULL,
  `accion` ENUM('CORTE','RECONEXION','CONSULTA','TEST') NOT NULL,
  `ip_cliente` VARCHAR(45) NULL COMMENT 'IP enviada al MikroTik',
  `mac_cliente` VARCHAR(50) NULL COMMENT 'MAC enviada al MikroTik (opcional)',
  `comando` TEXT NULL COMMENT 'Comando RouterOS generado',
  `estado` ENUM('EXITO','ERROR','DRY_RUN') NOT NULL DEFAULT 'DRY_RUN',
  `mensaje_error` TEXT NULL COMMENT 'Detalle del error si falló',
  `ejecutado_por` VARCHAR(100) NULL COMMENT 'Usuario del sistema que disparó la acción',
  `origen` VARCHAR(100) NULL COMMENT 'Archivo/módulo que originó la acción',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_id_contrato` (`id_contrato`),
  KEY `idx_accion` (`accion`),
  KEY `idx_estado` (`estado`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

echo "<pre style='font-family:monospace;background:#1e1e2e;color:#cdd6f4;padding:20px;border-radius:8px;'>";
echo "<b style='color:#89b4fa;font-size:1.2em;'>🔧 Setup Tablas MikroTik API</b>\n";
echo str_repeat("─", 50) . "\n\n";

foreach ($queries as $tabla => $sql) {
    if ($conn->query($sql)) {
        echo "✅ <span style='color:#a6e3a1;'>Tabla <b>$tabla</b>: CREADA / YA EXISTÍA</span>\n";
    } else {
        echo "❌ <span style='color:#f38ba8;'>Error en <b>$tabla</b>: " . $conn->error . "</span>\n";
    }
}

echo "\n" . str_repeat("─", 50) . "\n";
echo "<b style='color:#89b4fa;'>✔ Setup completado.</b>\n";
echo "<small style='color:#6c7086;'>Puedes eliminar este archivo: setup_mikrotik_tables.php</small>";
echo "</pre>";
