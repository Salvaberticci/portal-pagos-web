<?php
// paginas/soporte/procesar_abono.php
// Procesa un abono a un soporte y actualiza la deuda asociada.

require_once '../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_soporte = isset($_POST['id_soporte']) ? intval($_POST['id_soporte']) : 0;
    $monto_abono = isset($_POST['monto_abono']) ? floatval($_POST['monto_abono']) : 0.0;
    $fecha_pago = date('Y-m-d H:i:s');

    if ($id_soporte > 0 && $monto_abono > 0) {
        $conn->begin_transaction();
        try {
            // 1. Obtener datos actuales del soporte
            $stmt = $conn->prepare("SELECT monto_total, monto_pagado, id_cobro, id_contrato FROM soportes WHERE id_soporte = ? FOR UPDATE");
            $stmt->bind_param("i", $id_soporte);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows == 0) throw new Exception("Soporte no encontrado.");
            $row = $res->fetch_assoc();
            
            $nuevo_pagado = $row['monto_pagado'] + $monto_abono;
            $pendiente = $row['monto_total'] - $nuevo_pagado;
            $id_cobro = $row['id_cobro'];

            // Validación básica
            if ($nuevo_pagado > $row['monto_total'] + 0.01) { // Tolerancia pequeña
                throw new Exception("El abono excede el monto pendiente.");
            }

            // 2. Actualizar Soporte
            $stmt_update = $conn->prepare("UPDATE soportes SET monto_pagado = ? WHERE id_soporte = ?");
            $stmt_update->bind_param("di", $nuevo_pagado, $id_soporte);
            if (!$stmt_update->execute()) throw new Exception("Error al actualizar soporte.");

            // 3. Actualizar Cuenta por Cobrar (si existe)
            if ($id_cobro) {
                // Obtener estado actual de la deuda
                $stmt_cxc = $conn->prepare("SELECT monto_total FROM cuentas_por_cobrar WHERE id_cobro = ?");
                $stmt_cxc->bind_param("i", $id_cobro);
                $stmt_cxc->execute();
                $res_cxc = $stmt_cxc->get_result();
                
                if ($res_cxc->num_rows > 0) {
                    // No necesitamos el row para el cálculo directo, ya tenemos el pendiente del soporte
                    
                    // La lógica aquí es: El 'monto_total' en CxC para soportes suele ser el SALDO INICIAL pendiente.
                    // Pero si usamos el sistema estándar, 'monto_total' es la deuda original y 'monto_pagado' va subiendo.
                    // Sin embargo, mi implementación inicial de guardar_soporte puso 'monto_total' = 'deuda_pendiente'.
                    // ESTO ES IMPORTANTE: Si soporte costo 100, pagó 20, deuda inicial fue 80.
                    // Si ahora abono 30 más:
                    // Soporte: Pagado 50. Pendiente 50.
                    // Deuda (CxC): Monto 80. Deberíamos registrar un 'pago parcial' o reducir el monto?
                    // Estrategia más limpia para integración: Actualizar el 'monto_total' de la deuda para reflejar el saldo actual O marcar como pagado si es total.
                    // Dado que el sistema de cobros usa 'monto_total' como deuda, reducirlo es una opción, o usar un registro de pagos.
                    // VOY A REDUCIR EL MONTO TOTAL DE LA DEUDA PENDIENTE para simplificar, ya que es un "saldo pendiente".
                    
                    $nuevo_monto_deuda = $pendiente; // El nuevo saldo pendiente del soporte es la nueva deuda.
                    
                    if ($nuevo_monto_deuda <= 0.01) {
                         // Se pagó todo
                        $conn->query("UPDATE cuentas_por_cobrar SET monto_total = 0, estado = 'PAGADO', fecha_pago = '$fecha_pago' WHERE id_cobro = '$id_cobro'");
                    } else {
                        // Aún hay deuda, actualizamos el monto
                        $conn->query("UPDATE cuentas_por_cobrar SET monto_total = '$nuevo_monto_deuda' WHERE id_cobro = '$id_cobro'");
                    }
                }
            }

            $conn->commit();
            header("Location: historial_soportes.php?status=success&msg=Abono registrado correctamente.");

        } catch (Exception $e) {
            $conn->rollback();
            header("Location: historial_soportes.php?status=error&msg=" . urlencode($e->getMessage()));
        }
    } else {
        header("Location: historial_soportes.php?status=error&msg=Datos inválidos.");
    }
}
?>
