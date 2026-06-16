<?php
// elimina_cobro.php - Elimina una cuenta por cobrar después de verificar la clave
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_cobro = isset($_POST['id']) ? intval($_POST['id']) : 0;
$clave = isset($_POST['clave']) ? $_POST['clave'] : '';

if ($id_cobro <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cobro no válido.']);
    exit;
}

// 1. Verificar sesión y clave
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$stmt_user = $conn->prepare("SELECT clave FROM usuarios WHERE id_usuario = ?");
$stmt_user->bind_param("i", $usuario_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user = $res_user->fetch_assoc();

if (!$user || !password_verify($clave, $user['clave'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña administrativa incorrecta.']);
    exit;
}

// 2. Obtener el estado y Referencia del cobro antes de eliminar
$stmt_check = $conn->prepare("SELECT cxc.estado, cxc.referencia_pago, co.estado AS contrato_estado, co.nombre_completo 
                             FROM cuentas_por_cobrar cxc 
                             LEFT JOIN contratos co ON cxc.id_contrato = co.id 
                             WHERE cxc.id_cobro = ?");
$stmt_check->bind_param("i", $id_cobro);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$cobro = $result_check->fetch_assoc();
$stmt_check->close();

if (!$cobro) {
    echo json_encode(['success' => false, 'message' => 'El cobro no existe.']);
    exit;
}

$referencia = $cobro['referencia_pago'];

// 3. Eliminar registros
$conn->begin_transaction();
try {
    if (!empty($referencia)) {
        // ES UN PAGO GLOBAL (Capture desglosado)
        $stmt_ids = $conn->prepare("SELECT id_cobro FROM cuentas_por_cobrar WHERE referencia_pago = ?");
        $stmt_ids->bind_param("s", $referencia);
        $stmt_ids->execute();
        $res_ids = $stmt_ids->get_result();
        
        $ids_a_borrar = [];
        while($fila = $res_ids->fetch_assoc()) {
            $ids_a_borrar[] = $fila['id_cobro'];
        }
        $stmt_ids->close();

        if (count($ids_a_borrar) > 0) {
            $in_clause = implode(',', $ids_a_borrar);
            $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN ($in_clause)");
            $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_cobro IN ($in_clause)");
        }

        $msg = "Se eliminó el capture completo (Referencia $referencia) correctamente.";

    } else {
        // ES UN COBRO AISLADO
        if ($cobro['estado'] == 'PAGADO') {
            throw new Exception("No se puede eliminar un cobro PAGADO antiguo sin referencia vinculada.");
        }
        $conn->query("DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc = $id_cobro");
        $conn->query("DELETE FROM cuentas_por_cobrar WHERE id_cobro = $id_cobro");

        $msg = "Eliminado correctamente.";
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}

$conn->close();
exit;
?>