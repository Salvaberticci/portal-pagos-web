<?php
// Incluye el archivo de conexión. La variable $conn estará disponible aquí.
require_once 'conexion.php';

$message = '';
$message_class = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$edit_data = null;
$stmt = null;

// Variables para la búsqueda
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// ----------------------------------------------------------------------------------
// LÓGICA DE GESTIÓN DE FORMULARIO (POST: INSERTAR O ACTUALIZAR)
// ----------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recolección y saneamiento de datos
    // Usamos real_escape_string para prevenir inyección SQL.
    $user_input = $conn->real_escape_string($_POST['usuario']);
    $nombre_completo = $conn->real_escape_string($_POST['nombre_completo'] ?? '');
    $rol = $conn->real_escape_string($_POST['rol'] ?? 'Vendedor'); // Asume un valor por defecto si falta

    $id_usuario = isset($_POST['id_usuario']) ? $conn->real_escape_string($_POST['id_usuario']) : null;
    // La clave puede ser nula/vacía en la modificación, pero no en la creación.
    $pass_input = $conn->real_escape_string($_POST['clave'] ?? '');

    if ($id_usuario) {
        // --- MODIFICAR (UPDATE) ---
        $set_clause = "nombre_completo = ?, usuario = ?, rol = ?";
        $types = "sss";
        $params = [$nombre_completo, $user_input, $rol];

        if (!empty($pass_input)) {
            // Si se ingresó una nueva clave, la hasheamos y la incluimos
            $hashed_pass = password_hash($pass_input, PASSWORD_DEFAULT);
            $set_clause .= ", clave = ?";
            $types .= "s";
            $params[] = $hashed_pass;
        }

        // Añadir el ID al final para la cláusula WHERE
        $types .= "i";
        $params[] = $id_usuario;

        $sql_update = "UPDATE usuarios SET $set_clause WHERE id_usuario = ?";
        $stmt = $conn->prepare($sql_update);

        // El método call_user_func_array es útil para bind_param con arrays dinámicos
        if (call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params))) {
            if ($stmt->execute()) {
                $message = "Usuario modificado con éxito.";
                $message_class = 'success';
            } else {
                $message = "Error al modificar el usuario: " . $stmt->error;
                $message_class = 'error';
            }
        } else {
            $message = "Error al enlazar parámetros para la modificación.";
            $message_class = 'error';
        }

    } else {
        // --- CREAR (INSERT) ---
        if (empty($pass_input)) {
            $message = "La clave no puede estar vacía al crear un nuevo usuario.";
            $message_class = 'error';
        } else {
            $hashed_pass = password_hash($pass_input, PASSWORD_DEFAULT);
            $sql_insert = "INSERT INTO usuarios (usuario, clave, nombre_completo, rol) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("ssss", $user_input, $hashed_pass, $nombre_completo, $rol);
            if ($stmt->execute()) {
                $message = "Usuario creado con éxito.";
                $message_class = 'success';
            } else {
                $message = "Error al crear el usuario: " . $stmt->error;
                // Manejo de error específico (ej: clave duplicada, si aplica)
                if ($conn->errno == 1062) {
                    $message = "Error: El usuario '{$user_input}' ya existe.";
                }
                $message_class = 'error';
            }
        }
    }

    if ($stmt) {
        $stmt->close();
    }

    // Redirección POST-a-GET para evitar reenvío de formulario y mostrar el mensaje
    header("Location: gestion_usuarios.php?message=" . urlencode($message) . "&class=" . urlencode($message_class));
    exit;
}

// ----------------------------------------------------------------------------------
// LÓGICA DE GESTIÓN (ELIMINAR)
// ----------------------------------------------------------------------------------
if ($action === 'delete_user' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM `usuarios` WHERE `id_usuario` = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $message = "Usuario eliminado con éxito.";
        $message_class = 'success';
    } else {
        $message = "Error al eliminar el usuario: " . $stmt->error;
        $message_class = 'error';
    }
    $stmt->close();

    // Redirección POST-a-GET
    header("Location: gestion_usuarios.php?message=" . urlencode($message) . "&class=" . urlencode($message_class));
    exit;
}

// ----------------------------------------------------------------------------------
// LÓGICA DE CONSULTA (LISTADO Y BÚSQUEDA)
// ----------------------------------------------------------------------------------
$sql = "SELECT id_usuario, usuario, nombre_completo, rol FROM usuarios";

if (!empty($search_term)) {
    // Usamos la variable saneada $search_term
    $sql .= " WHERE nombre_completo LIKE ? OR usuario LIKE ? OR rol LIKE ?";
}
$sql .= " ORDER BY id_usuario ASC";

$result = null;

if (!empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Manejo de mensajes de redirección
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_class = $_GET['class'];
}

$conn->close();

?>
<?php
$page_title = "Gestión de Usuarios";
$breadcrumb = ["Admin"];
$back_url = "menu.php";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<style>
    :root {
        --primary: #0d6efd;
        --primary-rgb: 13, 110, 253;
        --bg-card: #ffffff;
        --text-main: #212529;
        --border-glass: rgba(0, 0, 0, 0.08);
    }
    [data-theme="dark"] {
        --bg-card: #1a2234;
        --text-main: #e2e8f0;
        --border-glass: rgba(255, 255, 255, 0.1);
    }
    .text-gradient {
        background: linear-gradient(45deg, var(--primary), #00c6ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .glass-panel {
        background: var(--bg-card) !important;
        backdrop-filter: blur(10px);
        border: 1px solid var(--border-glass) !important;
        border-radius: 20px !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05) !important;
        transition: all 0.3s ease;
    }
    .table-premium th,
    .table-premium td {
        text-align: center !important;
        vertical-align: middle !important;
        padding: 15px 12px !important;
        background: transparent !important;
    }
    .table-premium thead th {
        background: rgba(var(--primary-rgb), 0.03) !important;
        color: var(--text-main) !important;
        border: none !important;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    [data-theme="dark"] .table-premium thead th {
        background: rgba(255, 255, 255, 0.05) !important;
    }
    .btn-glass {
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        border: 1px solid rgba(var(--primary-rgb), 0.2);
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
    }
    .btn-glass:hover {
        background: var(--primary);
        color: white;
    }
    .form-control, .form-select {
        background-color: var(--bg-card) !important;
        color: var(--text-main) !important;
        border: 1px solid var(--border-glass) !important;
    }
    [data-theme="dark"] .modal-content {
        background-color: #1a2234 !important;
        color: #e2e8f0 !important;
    }
    [data-theme="dark"] .modal-header, [data-theme="dark"] .modal-footer {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">
        <!-- Header -->
        <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="h3 fw-bold mb-1 text-gradient">Gestión de Usuarios</h2>
                <p class="text-muted mb-0"><i class="fa-solid fa-user-shield me-2"></i>Control de accesos y perfiles del sistema</p>
            </div>
            <button type="button" class="btn btn-primary px-4 py-2 shadow-sm rounded-3" data-bs-toggle="modal"
                data-bs-target="#modalModificacionUsuario" data-id="" data-nombre="" data-usuario=""
                data-rol="">
                <i class="fa-solid fa-plus me-2"></i>Nuevo Usuario
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_class == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show"
                role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filtros y Búsqueda -->
        <div class="glass-panel mb-4">
            <div class="p-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="small fw-bold text-muted text-uppercase">Usuarios Registrados:</span>
                    <span class="badge bg-primary rounded-pill"><?php echo count($data); ?></span>
                </div>
                <form action="gestion_usuarios.php" method="GET" class="d-flex gap-2" style="max-width: 400px; flex-grow: 1;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0 text-muted">
                            <i class="fa-solid fa-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Buscar por nombre, usuario o rol..."
                            value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="btn btn-primary px-3">Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla Resultados -->
        <div class="glass-panel overflow-hidden mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-premium">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Nombre Completo</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data)): ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td class="ps-4 text-muted small">#<?php echo htmlspecialchars($row['id_usuario']); ?></td>
                                    <td>
                                        <span class="fw-bold text-main"><?php echo htmlspecialchars($row['nombre_completo']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex align-items-center px-3 py-1 rounded-pill bg-light text-dark border small">
                                            <i class="fa-solid fa-user-circle me-2 opacity-50"></i>
                                            <?php echo htmlspecialchars($row['usuario']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = 'primary';
                                        switch($row['rol']) {
                                            case 'Administrador': $badgeClass = 'danger'; break;
                                            case 'Operador': $badgeClass = 'info'; break;
                                            case 'Vendedor': $badgeClass = 'success'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass; ?> bg-opacity-10 text-<?php echo $badgeClass; ?> border border-<?php echo $badgeClass; ?> border-opacity-25 px-3 py-1 rounded-pill" style="font-size: 0.7rem;">
                                            <i class="fa-solid fa-shield-halved me-1"></i>
                                            <?php echo htmlspecialchars($row['rol']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" data-bs-toggle="modal" data-bs-target="#modalModificacionUsuario"
                                                data-id="<?php echo htmlspecialchars($row['id_usuario']); ?>"
                                                data-nombre="<?php echo htmlspecialchars($row['nombre_completo']); ?>"
                                                data-usuario="<?php echo htmlspecialchars($row['usuario']); ?>"
                                                data-rol="<?php echo htmlspecialchars($row['rol']); ?>"
                                                class="btn btn-sm btn-glass rounded-pill px-3 shadow-none" title="Editar">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                                            </button>
                                            <button type="button"
                                                data-bs-href="gestion_usuarios.php?action=delete_user&id=<?php echo urlencode($row['id_usuario']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#eliminaModal"
                                                class="btn btn-sm btn-light text-danger rounded-pill px-3 shadow-none" title="Eliminar">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-slash fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">No se encontraron usuarios registrados.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Footer Informativo -->
            <div class="p-3 px-4 border-top border-light-subtle d-flex justify-content-center align-items-center" style="background: rgba(0,0,0,0.01);">
                <div class="small text-muted fw-medium">
                    Mostrando <?php echo count($data); ?> registros encontrados
                </div>
            </div>
        </div>

        <div class="text-center mt-4 mb-4">
            <a href="menu.php" class="btn btn-glass px-5 py-2">
                <i class="fa-solid fa-arrow-left me-2"></i>Volver al Menú
            </a>
        </div>
    </div>
</main>

<!-- Modal Modificar/Crear -->
<div class="modal fade" id="modalModificacionUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary" id="modalModificacionUsuarioLabel">Gestión de Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-modificacion-usuario" method="POST" action="gestion_usuarios.php" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="id_usuario" id="id_usuario_modal" value="">

                    <div class="mb-3">
                        <label for="nombre_completo_modal"
                            class="form-label small text-muted fw-bold text-uppercase">Nombre Completo</label>
                        <input type="text" id="nombre_completo_modal" name="nombre_completo" class="form-control"
                            placeholder="Ej: Juan Pérez" required>
                        <div class="invalid-feedback">Ingrese el nombre completo.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="usuario_modal"
                                class="form-label small text-muted fw-bold text-uppercase">Usuario</label>
                            <input type="text" id="usuario_modal" name="usuario" class="form-control"
                                placeholder="Ej: jperez" required>
                            <div class="invalid-feedback">Ingrese el usuario.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="rol_modal"
                                class="form-label small text-muted fw-bold text-uppercase">Rol</label>
                            <select id="rol_modal" name="rol" class="form-select" required>
                                <option value="Administrador">Administrador</option>
                                <option value="Operador">Operador</option>
                                <option value="Vendedor">Vendedor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="clave_modal"
                            class="form-label small text-muted fw-bold text-uppercase">Contraseña</label>
                        <input type="password" id="clave_modal" name="clave" class="form-control" placeholder="••••••"
                            minlength="4">
                        <div class="form-text small" id="clave_hint">Déjelo en blanco para mantener la actual.</div>
                        <div class="invalid-feedback">La contraseña es requerida para nuevos usuarios (min 4
                            caracteres).</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-actualizar-usuario" class="btn btn-primary px-4">Guardar
                        Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="eliminaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <div class="mb-3 text-danger">
                    <i class="fa-solid fa-circle-exclamation fa-3x"></i>
                </div>
                <h5 class="fw-bold mb-2">¿Eliminar Usuario?</h5>
                <p class="text-muted small mb-4">Esta acción no se puede deshacer.</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" class="btn btn-danger btn-ok px-4">Eliminar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_foot.php'; ?>

<script>
    // Logic for User Modal
    const modalModificacionUsuario = document.getElementById('modalModificacionUsuario');
    const formModificacionUsuario = document.getElementById('form-modificacion-usuario');

    if (modalModificacionUsuario) {
        modalModificacionUsuario.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const usuario = button.getAttribute('data-usuario');
            const rol = button.getAttribute('data-rol');

            const modalTitle = document.getElementById('modalModificacionUsuarioLabel');
            const claveInput = document.getElementById('clave_modal');
            const claveHint = document.getElementById('clave_hint');
            const btnSubmit = document.getElementById('btn-actualizar-usuario');

            claveInput.value = ''; // Reset password field

            if (id) {
                // Edit Mode
                modalTitle.textContent = 'Editar Usuario';
                document.getElementById('id_usuario_modal').value = id;
                document.getElementById('nombre_completo_modal').value = nombre;
                document.getElementById('usuario_modal').value = usuario;
                document.getElementById('rol_modal').value = rol;

                claveInput.required = false;
                claveHint.textContent = "Ingrese una nueva contraseña solo si desea cambiarla.";
            } else {
                // Create Mode
                modalTitle.textContent = 'Nuevo Usuario';
                document.getElementById('id_usuario_modal').value = '';
                document.getElementById('nombre_completo_modal').value = '';
                document.getElementById('usuario_modal').value = '';
                document.getElementById('rol_modal').value = 'Vendedor';

                claveInput.required = true;
                claveHint.textContent = "La contraseña es obligatoria para nuevos usuarios.";
            }
            formModificacionUsuario.classList.remove('was-validated');
        });

        // Submit Logic
        const btnActualizarUsuario = document.getElementById('btn-actualizar-usuario');
        if (btnActualizarUsuario && formModificacionUsuario) {
            btnActualizarUsuario.addEventListener('click', function () {
                const claveInput = document.getElementById('clave_modal');
                const isCreation = document.getElementById('id_usuario_modal').value === '';

                if (isCreation) {
                    claveInput.required = true;
                } else {
                    claveInput.required = claveInput.value.length > 0;
                    if (claveInput.value.length > 0 && claveInput.value.length < 4) {
                        claveInput.setCustomValidity("La clave debe tener al menos 4 caracteres.");
                    } else {
                        claveInput.setCustomValidity("");
                    }
                }

                if (formModificacionUsuario.checkValidity()) {
                    formModificacionUsuario.submit();
                } else {
                    formModificacionUsuario.classList.add('was-validated');
                }
            });
        }
    }

    // Delete Modal
    const eliminaModal = document.getElementById('eliminaModal');
    if (eliminaModal) {
        eliminaModal.addEventListener('shown.bs.modal', event => {
            const button = event.relatedTarget;
            const url = button.getAttribute('data-bs-href');
            const btnOk = eliminaModal.querySelector('.btn-ok');
            if (btnOk) btnOk.href = url;
        });
    }
</script>