<?php
require_once 'conexion.php';

$message = '';
$message_class = '';
$nombre_pon_post = isset($_POST['nombre_pon']) ? $_POST['nombre_pon'] : '';
$id_olt_post = isset($_POST['id_olt']) ? $_POST['id_olt'] : '';
$descripcion_post = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';

// Consulta para obtener las OLTs disponibles
$olts = [];
$sql_olts = "SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt ASC";
$result_olts = $conn->query($sql_olts);

if ($result_olts && $result_olts->num_rows > 0) {
    while ($row = $result_olts->fetch_assoc()) {
        $olts[] = $row;
    }
}

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($nombre_pon_post) || empty($id_olt_post)) {
            throw new Exception("Los campos Nombre PON y OLT son obligatorios.");
        }
        
        $stmt_pon = $conn->prepare("INSERT INTO pon (nombre_pon, id_olt, descripcion) VALUES (?, ?, ?)");
        $stmt_pon->bind_param("sis", $nombre_pon_post, $id_olt_post, $descripcion_post); 
        
        if (!$stmt_pon->execute()) {
            // Check for duplicate entry error (error code 1062)
            if ($conn->errno === 1062) {
                throw new Exception("Este nombre de PON ya está registrado para la OLT seleccionada.");
            } else {
                throw new Exception("Error al insertar el PON: " . $stmt_pon->error);
            }
        }
        
        $id_referencia = $conn->insert_id; 
        $stmt_pon->close();
        
        $message = '¡PON registrado con éxito! (ID Asignado: ' . $id_referencia . ')';
        $message_class = 'success';
        
        $nombre_pon_post = $id_olt_post = $descripcion_post = '';

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_class = 'error';
    }
}

$path_to_root = "../";
$page_title = "Registro de PON";
$breadcrumb = ["Técnica", "Gestión de PON"];
$back_url = "gestion_pon.php";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">
        <div class="container-fluid">
            <div class="card glass-panel border-0 shadow-sm overflow-hidden mb-4">
                <div class="card-header bg-transparent py-4 border-bottom border-white border-opacity-10 px-4">
                    <h5 class="mb-1 fw-bold">Registro de PON</h5>
                    <p class="text-muted small mb-0">Crear nuevo PON asociado a una OLT</p>
                </div>

                <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_class === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="row g-4">
                    <div class="col-md-6">
                        <label for="nombre_pon" class="form-label text-secondary small fw-bold text-uppercase">Nombre PON</label>
                        <input type="text" class="form-control" id="nombre_pon" name="nombre_pon" 
                               value="<?php echo htmlspecialchars($nombre_pon_post); ?>" required autofocus>
                    </div>

                    <div class="col-md-6">
                        <label for="id_olt" class="form-label text-secondary small fw-bold text-uppercase">OLT a la que pertenece</label>
                        <select class="form-select" id="id_olt" name="id_olt" required>
                            <option value="">-- Seleccione una OLT --</option>
                            <?php foreach ($olts as $olt): ?>
                                <option value="<?php echo htmlspecialchars($olt['id_olt']); ?>" 
                                    <?php echo ($id_olt_post == $olt['id_olt']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($olt['nombre_olt']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mt-4">
                        <label for="descripcion" class="form-label text-secondary small fw-bold text-uppercase">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($descripcion_post); ?></textarea>
                    </div>
                    
                    <div class="col-12 pt-3 border-top border-white border-opacity-10 mt-4 d-flex justify-content-end gap-3">
                        <a href="gestion_pon.php" class="btn btn-outline-secondary px-4">Cancelar</a>
                        <button type="submit" class="btn btn-premium px-4">
                            <i class="fa-solid fa-save me-2"></i>Registrar PON
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center mb-4">
            <a href="gestion_pon.php" class="btn btn-outline-secondary px-4">
                <i class="fa-solid fa-arrow-left me-2"></i>Volver
            </a>
        </div>
    </div>
</main>

<?php require_once 'includes/layout_foot.php'; ?>