<?php
/**
 * config/database.php
 *
 * Configuración de la base de datos local MySQL.
 * Esta BD se usa únicamente para:
 *   - Validar referencias duplicadas
 *   - Almacenar historial de pagos registrados
 *
 * En XAMPP los valores por defecto son:
 *   host     = localhost
 *   port     = 3306
 *   user     = root
 *   password = '' (vacío)
 *   dbname   = portal_pagos
 */

return [
    'host'     => 'localhost',
    'port'     => 3306,
    'dbname'   => 'darwinra_bdmarateltru',
    'user'     => 'darwinra_bdmarateltruadmin',
    'password' => 'Adminbdmarateltru2026',
    'charset'  => 'utf8mb4',
];
