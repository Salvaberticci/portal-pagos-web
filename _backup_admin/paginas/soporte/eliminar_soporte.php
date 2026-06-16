<?php
// paginas/soporte/eliminar_soporte.php
// Elimina un soporte y su deuda asociada en cascada.

require_once '../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_soporte = isset($_POST['id_soporte_eliminar']) ? intval($_POST['id_soporte_eliminar']) : 0;
    $is_json = isset($_POST['json']) && $_POST['json'] == 1;

    if ($id_soporte > 0) {
        $conn->begin_transaction();
        try {
            // 1. Obtener ID del cobro asociado antes de eliminar
            $stmt = $conn->prepare("SELECT id_cobro FROM soportes WHERE id_soporte = ? FOR UPDATE");
            $stmt->bind_param("i", $id_soporte);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows == 0) throw new Exception("Soporte no encontrado.");
            $row = $res->fetch_assoc();
            $id_cobro = $row['id_cobro'];

            // 2. Eliminar de tabla Soportes
            $stmt_del = $conn->prepare("DELETE FROM soportes WHERE id_soporte = ?");
            $stmt_del->bind_param("i", $id_soporte);
            if (!$stmt_del->execute()) throw new Exception("Error al eliminar soporte.");

            // 3. Eliminar Deuda asociada (Si existe)
            if ($id_cobro) {
                // Primero eliminar historial de cobros manuales (FK constraint posible)
                $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc = '$id_cobro'");
                
                // Eliminar la cuenta por cobrar
                if (!$conn->query("DELETE FROM cuentas_por_cobrar WHERE id_cobro = '$id_cobro'")) {
                    throw new Exception("Error al eliminar la deuda asociada.");
                }
            }

            $conn->commit();
            
            if ($is_json) {
                echo json_encode(["success" => true, "message" => "Registro eliminado correctamente."]);
                exit;
            }
            header("Location: historial_soportes.php?status=success&msg=Registro eliminado correctamente.");

        } catch (Exception $e) {
            $conn->rollback();
            if ($is_json) {
                echo json_encode(["success" => false, "message" => $e->getMessage()]);
                exit;
            }
            header("Location: historial_soportes.php?status=error&msg=Error: " . urlencode($e->getMessage()));
        }
    } else {
        if ($is_json) {
            echo json_encode(["success" => false, "message" => "ID inválido."]);
            exit;
        }
        header("Location: historial_soportes.php?status=error&msg=ID inválido.");
    }
}
?>
