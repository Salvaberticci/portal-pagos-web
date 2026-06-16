<?php
ini_set('display_errors', 0); // Disable display errors for JSON response
header('Content-Type: application/json');

require '../conexion.php';
session_start();

// 1. Verify Authentication
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada o expirada.']);
    exit;
}

// 2. Validate Input
$id = isset($_POST['id']) ? $conn->real_escape_string($_POST['id']) : null;
$clave = isset($_POST['clave']) ? $_POST['clave'] : null;

if (!$id || !$clave) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes para procesar la eliminación.']);
    exit;
}

// 3. Verify Password
$usuario_id = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT clave FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res_user = $stmt->get_result();

if ($res_user->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'Error al verificar el usuario.']);
    exit;
}

$user_db = $res_user->fetch_assoc();
if (!password_verify($clave, $user_db['clave'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta. No se ha eliminado el registro.']);
    exit;
}

// 4. Proceed with Deletion
$conn->begin_transaction();

try {
    // A. Clean up history
    $sql_cobros_ids = "SELECT id_cobro FROM cuentas_por_cobrar WHERE id_contrato = $id";
    $result_cobros_ids = $conn->query($sql_cobros_ids);

    if ($result_cobros_ids && $result_cobros_ids->num_rows > 0) {
        $cobro_ids = [];
        while ($row = $result_cobros_ids->fetch_assoc()) {
            $cobro_ids[] = $row['id_cobro'];
        }
        $cobro_ids_list = implode(',', $cobro_ids);

        $sql_historial = "DELETE FROM cobros_manuales_historial WHERE id_cobro_cxc IN ($cobro_ids_list)";
        $conn->query($sql_historial);
    }

    // B. Delete associated supports (added cleanup for FK constraint)
    $sql_soportes = "DELETE FROM soportes WHERE id_contrato = $id";
    $conn->query($sql_soportes);

    // C. Delete accounts receivable
    $sql_cxc = "DELETE FROM cuentas_por_cobrar WHERE id_contrato = $id";
    $conn->query($sql_cxc);

    // D. Delete parent record (contract)
    $sql_contrato = "DELETE FROM contratos WHERE id = $id";
    $conn->query($sql_contrato);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'El contrato y todos sus registros asociados han sido eliminados correctamente.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>