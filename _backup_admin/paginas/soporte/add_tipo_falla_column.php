<?php
/**
 * Migration: Add tipo_falla column to soportes table
 * Run this file once to add the new column
 */
require_once __DIR__ . '/../conexion.php';

echo "=== Agregando columna tipo_falla a la tabla soportes ===\n\n";

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM soportes LIKE 'tipo_falla'");

if ($check->num_rows == 0) {
    $sql = "ALTER TABLE soportes ADD COLUMN tipo_falla VARCHAR(100) AFTER observaciones";

    if ($conn->query($sql)) {
        echo "✓ Columna 'tipo_falla' agregada exitosamente a la tabla 'soportes'\n";
    } else {
        echo "✗ Error al agregar columna: " . $conn->error . "\n";
    }
} else {
    echo "- La columna 'tipo_falla' ya existe en la tabla 'soportes'\n";
}

echo "\n=== Proceso completado ===\n";
$conn->close();
?>