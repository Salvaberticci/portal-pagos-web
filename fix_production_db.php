<?php
// fix_production_db.php
require 'paginas/conexion.php';

echo "<pre>";
echo "Iniciando migración de base de datos...\n";

$queries = [
    // Columnas para reportes de pago
    "ALTER TABLE pagos_reportados ADD COLUMN IF NOT EXISTS motivo_rechazo TEXT AFTER concepto",
    "ALTER TABLE pagos_reportados ADD COLUMN IF NOT EXISTS visto_por_cliente TINYINT(1) DEFAULT 0 AFTER motivo_rechazo",
    
    // Índices para optimización
    "ALTER TABLE contratos ADD INDEX IF NOT EXISTS idx_cedula (cedula)",
    "ALTER TABLE cuentas_por_cobrar ADD INDEX IF NOT EXISTS idx_id_contrato (id_contrato)",
    "ALTER TABLE cuentas_por_cobrar ADD INDEX IF NOT EXISTS idx_estado (estado)",
    "ALTER TABLE pagos_reportados ADD INDEX IF NOT EXISTS idx_cedula_titular (cedula_titular)",
    "ALTER TABLE pagos_reportados ADD INDEX IF NOT EXISTS idx_estado (estado)",
    "ALTER TABLE pagos_reportados ADD INDEX IF NOT EXISTS idx_fecha_registro (fecha_registro)"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "[OK] Ejecutado: $sql\n";
    } else {
        echo "[ERROR] En $sql: " . $conn->error . "\n";
    }
}

echo "\nMigración finalizada. Intenta cargar el dashboard ahora.";
echo "</pre>";
?>
