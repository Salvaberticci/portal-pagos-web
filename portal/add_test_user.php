<?php
// add_test_user.php - Endpoint web para crear el usuario de prueba V20788775
// Acceso mediante: http://your-domain/portal/add_test_user.php

require_once dirname(__DIR__) . '/paginas/conexion.php';

// *** Seguridad básica ***


echo "Iniciando creación de usuario de prueba...\n";

// 1. Verificar si existe el contrato con la cédula V20788775
$sql_check = "SELECT id FROM contratos WHERE cedula = 'V20788775'";
$res_check = $conn->query($sql_check);

if ($res_check && $res_check->num_rows > 0) {
    $row = $res_check->fetch_assoc();
    $id_contrato = $row['id'];
    echo "El contrato de prueba con Cédula V20788775 ya existe (ID: $id_contrato).\n";
} else {
    // Insertar contrato de prueba
    $sql_insert = "INSERT INTO contratos (
        cedula, nombre_completo, id_plan, monto_plan, 
        direccion, telefono, estado, fecha_instalacion
    ) VALUES (
        'V20788775', 'USUARIO DE PRUEBA (1 BS)', 4, 17.50, 
        'DIRECCION DE PRUEBA - SOLO PARA TEST DE API', '04120000000', 'ACTIVO', CURRENT_DATE
    )";
    if ($conn->query($sql_insert)) {
        $id_contrato = $conn->insert_id;
        echo "Contrato de prueba creado exitosamente (ID: $id_contrato).\n";
    } else {
        http_response_code(500);
        die("Error al crear contrato de prueba: " . $conn->error . "\n");
    }
}

// 2. Verificar o crear cuenta por cobrar PENDIENTE
$sql_cxc_check = "SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $id_contrato AND estado = 'PENDIENTE'";
$res_cxc_check = $conn->query($sql_cxc_check);
if ($res_cxc_check && $res_cxc_check->num_rows > 0) {
    echo "El contrato de prueba ya tiene cuentas por cobrar PENDIENTE.\n";
} else {
    $sql_cxc_insert = "INSERT INTO cuentas_por_cobrar (
        id_contrato, fecha_emision, fecha_vencimiento, monto_total, estado, origen
    ) VALUES (
        $id_contrato, CURRENT_DATE, CURRENT_DATE, 17.50, 'PENDIENTE', 'SISTEMA'
    )";
    if ($conn->query($sql_cxc_insert)) {
        echo "Cuenta por cobrar PENDIENTE creada exitosamente para el contrato de prueba.\n";
    } else {
        echo "Error al crear cuenta por cobrar de prueba: " . $conn->error . "\n";
    }
}

echo "Proceso completado.\n";
?>
