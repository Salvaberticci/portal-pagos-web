<?php
require '../conexion.php';

echo "=== Creando tablas y columnas para el sistema mejorado ===\n\n";

// 1. Crear tabla de instaladores
$sql = "CREATE TABLE IF NOT EXISTS instaladores (
    id_instalador INT PRIMARY KEY AUTO_INCREMENT,
    nombre_instalador VARCHAR(100) NOT NULL,
    telefono VARCHAR(50),
    activo TINYINT DEFAULT 1,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✓ Tabla 'instaladores' creada\n";
} else {
    echo "✗ Error en instaladores: " . $conn->error . "\n";
}

// 2. Crear tabla de tipos de instalación
$sql = "CREATE TABLE IF NOT EXISTS tipos_instalacion (
    id_tipo INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50) NOT NULL UNIQUE,
    monto DECIMAL(10,2) NOT NULL,
    descripcion TEXT
)";

if ($conn->query($sql)) {
    echo "✓ Tabla 'tipos_instalacion' creada\n";
} else {
    echo "✗ Error en tipos_instalacion: " . $conn->error . "\n";
}

// 3. Insertar tipos de instalación por defecto
$sql = "INSERT IGNORE INTO tipos_instalacion (tipo, monto, descripcion) VALUES
    ('NUEVO', 50.00, 'Instalación nueva'),
    ('MIGRACION', 30.00, 'Migración de servicio'),
    ('MUDANZA', 40.00, 'Mudanza de equipo')";

if ($conn->query($sql)) {
    echo "✓ Tipos de instalación insertados\n";
} else {
    echo "✗ Error insertando tipos: " . $conn->error . "\n";
}

// 4. Crear tabla de clientes deudores
$sql = "CREATE TABLE IF NOT EXISTS clientes_deudores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_contrato INT NOT NULL,
    monto_total DECIMAL(10,2),
    monto_pagado DECIMAL(10,2),
    saldo_pendiente DECIMAL(10,2),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    notas TEXT,
    FOREIGN KEY (id_contrato) REFERENCES contratos(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "✓ Tabla 'clientes_deudores' creada\n";
} else {
    echo "✗ Error en clientes_deudores: " . $conn->error . "\n";
}

// 5. Agregar columnas a contratos
$columns = [
    "tipo_instalacion VARCHAR(50)",
    "monto_instalacion DECIMAL(10,2) DEFAULT 0",
    "gastos_adicionales DECIMAL(10,2) DEFAULT 0",
    "instaladores TEXT COMMENT 'IDs separados por comas'"
];

foreach ($columns as $col) {
    $parts = explode(' ', $col);
    $colName = $parts[0];
    
    $check = $conn->query("SHOW COLUMNS FROM contratos LIKE '$colName'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE contratos ADD COLUMN $col";
        if ($conn->query($sql)) {
            echo "✓ Columna '$colName' agregada a contratos\n";
        } else {
            echo "✗ Error agregando $colName: " . $conn->error . "\n";
        }
    } else {
        echo "- Columna '$colName' ya existe\n";
    }
}

echo "\n=== Optimizando Base de Datos (Índices) ===\n";
$indexQueries = [
    "idx_cxc_fecha_pago" => "CREATE INDEX idx_cxc_fecha_pago ON cuentas_por_cobrar(fecha_pago)",
    "idx_cxc_fecha_emision" => "CREATE INDEX idx_cxc_fecha_emision ON cuentas_por_cobrar(fecha_emision)",
    "idx_cxc_id_banco" => "CREATE INDEX idx_cxc_id_banco ON cuentas_por_cobrar(id_banco)",
    "idx_cxc_estado" => "CREATE INDEX idx_cxc_estado ON cuentas_por_cobrar(estado)",
    "idx_cxc_estado_sae_plus" => "CREATE INDEX idx_cxc_estado_sae_plus ON cuentas_por_cobrar(estado_sae_plus)",
    "idx_cxc_referencia_pago" => "CREATE INDEX idx_cxc_referencia_pago ON cuentas_por_cobrar(referencia_pago)"
];

foreach ($indexQueries as $idxName => $sql) {
    // Verificar si el índice ya existe (Compatible con MySQL antiguo y nuevo)
    $checkIndex = $conn->query("SHOW INDEX FROM cuentas_por_cobrar WHERE Key_name = '$idxName'");
    if ($checkIndex && $checkIndex->num_rows == 0) {
        if ($conn->query($sql)) {
            echo "✓ Índice '$idxName' creado con éxito.\n";
        } else {
            echo "✗ Error creando índice '$idxName': " . $conn->error . "\n";
        }
    } else {
        echo "- Índice '$idxName' ya existe.\n";
    }
}

echo "\n=== Proceso completado ===\n";
$conn->close();
?>
