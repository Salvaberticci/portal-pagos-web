<?php
/**
 * Migración: Agregar columnas para gestión avanzada de fallas
 * Fecha: 2026-02-09
 * 
 * Nuevas columnas:
 * - Tiempos: fecha_reporte, fecha_atencion, fecha_resolucion
 * - Clasificación: prioridad, es_caida_critica, clientes_afectados
 * - Ubicación: zona_afectada
 * - Notas: notas_internas
 */

require_once '../conexion.php';

echo "<h2>Migración: Gestión Avanzada de Fallas</h2>";
echo "<hr>";

// Array de columnas a agregar
$columnas = [
    [
        'nombre' => 'fecha_reporte',
        'sql' => "ALTER TABLE soportes ADD COLUMN fecha_reporte DATETIME DEFAULT CURRENT_TIMESTAMP AFTER fecha_soporte",
        'descripcion' => 'Fecha/hora cuando se reporta la falla'
    ],
    [
        'nombre' => 'fecha_atencion',
        'sql' => "ALTER TABLE soportes ADD COLUMN fecha_atencion DATETIME NULL AFTER fecha_reporte",
        'descripcion' => 'Fecha/hora cuando el técnico inicia atención'
    ],
    [
        'nombre' => 'fecha_resolucion',
        'sql' => "ALTER TABLE soportes ADD COLUMN fecha_resolucion DATETIME NULL AFTER fecha_atencion",
        'descripcion' => 'Fecha/hora cuando se resuelve la falla'
    ],
    [
        'nombre' => 'prioridad',
        'sql' => "ALTER TABLE soportes ADD COLUMN prioridad ENUM('BAJA', 'MEDIA', 'ALTA', 'CRITICA') DEFAULT 'MEDIA' AFTER tipo_falla",
        'descripcion' => 'Nivel de prioridad de la falla'
    ],
    [
        'nombre' => 'es_caida_critica',
        'sql' => "ALTER TABLE soportes ADD COLUMN es_caida_critica BOOLEAN DEFAULT FALSE AFTER prioridad",
        'descripcion' => 'Indica si es una caída crítica que afecta múltiples clientes'
    ],
    [
        'nombre' => 'clientes_afectados',
        'sql' => "ALTER TABLE soportes ADD COLUMN clientes_afectados INT DEFAULT 1 AFTER es_caida_critica",
        'descripcion' => 'Número de clientes afectados por la falla'
    ],
    [
        'nombre' => 'zona_afectada',
        'sql' => "ALTER TABLE soportes ADD COLUMN zona_afectada VARCHAR(100) NULL AFTER sector",
        'descripcion' => 'Zona geográfica específica afectada'
    ],
    [
        'nombre' => 'notas_internas',
        'sql' => "ALTER TABLE soportes ADD COLUMN notas_internas TEXT NULL AFTER observaciones",
        'descripcion' => 'Notas internas del operador (no visibles en reportes al cliente)'
    ]
];

// Verificar y agregar columnas
$columnas_agregadas = 0;
$columnas_existentes = 0;
$errores = 0;

foreach ($columnas as $columna) {
    echo "<p><strong>Procesando: {$columna['nombre']}</strong><br>";
    echo "<em>{$columna['descripcion']}</em><br>";

    // Verificar si la columna ya existe
    $check_sql = "SHOW COLUMNS FROM soportes LIKE '{$columna['nombre']}'";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        echo "<span style='color: orange;'>⚠️ La columna ya existe, saltando...</span></p>";
        $columnas_existentes++;
    } else {
        // Agregar la columna
        if ($conn->query($columna['sql'])) {
            echo "<span style='color: green;'>✅ Columna agregada exitosamente</span></p>";
            $columnas_agregadas++;
        } else {
            echo "<span style='color: red;'>❌ Error: " . $conn->error . "</span></p>";
            $errores++;
        }
    }
}

echo "<hr>";
echo "<h3>Creando Índices para Optimización</h3>";

// Índices para mejorar rendimiento
$indices = [
    [
        'nombre' => 'idx_fecha_reporte',
        'sql' => "CREATE INDEX idx_fecha_reporte ON soportes(fecha_reporte)",
        'descripcion' => 'Índice para búsquedas por fecha de reporte'
    ],
    [
        'nombre' => 'idx_prioridad',
        'sql' => "CREATE INDEX idx_prioridad ON soportes(prioridad)",
        'descripcion' => 'Índice para filtros por prioridad'
    ],
    [
        'nombre' => 'idx_zona_afectada',
        'sql' => "CREATE INDEX idx_zona_afectada ON soportes(zona_afectada)",
        'descripcion' => 'Índice para análisis por zona'
    ],
    [
        'nombre' => 'idx_caida_critica',
        'sql' => "CREATE INDEX idx_caida_critica ON soportes(es_caida_critica)",
        'descripcion' => 'Índice para filtrar caídas críticas'
    ]
];

$indices_creados = 0;
$indices_existentes = 0;

foreach ($indices as $indice) {
    echo "<p><strong>Procesando: {$indice['nombre']}</strong><br>";
    echo "<em>{$indice['descripcion']}</em><br>";

    // Verificar si el índice ya existe
    $check_sql = "SHOW INDEX FROM soportes WHERE Key_name = '{$indice['nombre']}'";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        echo "<span style='color: orange;'>⚠️ El índice ya existe, saltando...</span></p>";
        $indices_existentes++;
    } else {
        // Crear el índice
        if ($conn->query($indice['sql'])) {
            echo "<span style='color: green;'>✅ Índice creado exitosamente</span></p>";
            $indices_creados++;
        } else {
            echo "<span style='color: red;'>❌ Error: " . $conn->error . "</span></p>";
            $errores++;
        }
    }
}

echo "<hr>";
echo "<h3>Actualizando Datos Existentes</h3>";

// Actualizar registros existentes: copiar fecha_soporte a fecha_reporte si es NULL
$update_sql = "UPDATE soportes SET fecha_reporte = fecha_soporte WHERE fecha_reporte IS NULL";
if ($conn->query($update_sql)) {
    $affected = $conn->affected_rows;
    echo "<p><span style='color: green;'>✅ Se actualizaron {$affected} registros existentes con fecha_reporte</span></p>";
} else {
    echo "<p><span style='color: red;'>❌ Error al actualizar registros: " . $conn->error . "</span></p>";
}

// Si solucion_completada = 1, marcar fecha_resolucion = fecha_soporte
$update_resueltos = "UPDATE soportes 
                     SET fecha_resolucion = fecha_soporte 
                     WHERE solucion_completada = 1 
                     AND fecha_resolucion IS NULL";
if ($conn->query($update_resueltos)) {
    $affected = $conn->affected_rows;
    echo "<p><span style='color: green;'>✅ Se marcaron {$affected} reportes resueltos con fecha_resolucion</span></p>";
} else {
    echo "<p><span style='color: red;'>❌ Error: " . $conn->error . "</span></p>";
}

$conn->close();

echo "<hr>";
echo "<h2>Resumen de Migración</h2>";
echo "<ul>";
echo "<li><strong>Columnas agregadas:</strong> {$columnas_agregadas}</li>";
echo "<li><strong>Columnas existentes:</strong> {$columnas_existentes}</li>";
echo "<li><strong>Índices creados:</strong> {$indices_creados}</li>";
echo "<li><strong>Índices existentes:</strong> {$indices_existentes}</li>";
echo "<li><strong>Errores:</strong> {$errores}</li>";
echo "</ul>";

if ($errores == 0) {
    echo "<h3 style='color: green;'>✅ Migración completada exitosamente</h3>";
} else {
    echo "<h3 style='color: red;'>⚠️ Migración completada con {$errores} errores</h3>";
}

echo "<p><a href='../soporte/gestion_fallas.php'>← Volver a Gestión de Fallas</a></p>";
?>