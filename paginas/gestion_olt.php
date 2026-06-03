<?php
// Incluye el archivo de conexión.
require_once 'conexion.php';

$path_to_root = "../";
$page_title = "Gestión de OLTs";
$breadcrumb = ["Técnica"];
$back_url = "menu.php";
include $path_to_root . 'paginas/includes/layout_head.php';
include $path_to_root . 'paginas/includes/sidebar.php';
include $path_to_root . 'paginas/includes/header.php';

$message = '';
$message_class = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$stmt = null;
$parroquias_disponibles = [];

// Variables para la búsqueda
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Consulta base para obtener la lista de OLTs, agrupando las parroquias
$sql_base = "SELECT 
                o.id_olt, 
                o.nombre_olt, 
                o.marca,
                o.modelo,
                o.descripcion,
                GROUP_CONCAT(pa.nombre_parroquia ORDER BY pa.nombre_parroquia SEPARATOR ', ') AS parroquias_atendidas
             FROM olt o
             LEFT JOIN olt_parroquia op ON o.id_olt = op.olt_id
             LEFT JOIN parroquia pa ON op.parroquia_id = pa.id_parroquia";

// --- CONSULTA PARA OBTENER TODAS LAS PARROQUIAS (para el MODAL de modificación) ---
$sql_parroquias = "SELECT id_parroquia, nombre_parroquia FROM parroquia ORDER BY nombre_parroquia ASC";
$result_parroquias = $conn->query($sql_parroquias);

if ($result_parroquias && $result_parroquias->num_rows > 0) {
    while ($row = $result_parroquias->fetch_assoc()) {
        $parroquias_disponibles[] = $row;
    }
}

// --- CONSULTA PARA OBTENER TODAS LAS ASIGNACIONES OLT-PARROQUIA (para el JS del modal) ---
$assigned_parroquias = [];
$sql_assignments = "SELECT olt_id, parroquia_id FROM olt_parroquia";
$result_assignments = $conn->query($sql_assignments);
if ($result_assignments && $result_assignments->num_rows > 0) {
    while ($row = $result_assignments->fetch_assoc()) {
        $olt_id = $row['olt_id'];
        $parroquia_id = $row['parroquia_id'];
        if (!isset($assigned_parroquias[$olt_id])) {
            $assigned_parroquias[$olt_id] = [];
        }
        // Guardamos el ID como INT para la comparación en JS
        $assigned_parroquias[$olt_id][] = (int) $parroquia_id;
    }
}


// --- LÓGICA DE GESTIÓN (ELIMINAR) ---
if ($action === 'delete_olt' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];

    // Check if the OLT has any associated PONs
    $stmt_check_pons = $conn->prepare("SELECT COUNT(*) AS total_pons FROM pon WHERE id_olt = ?");
    $stmt_check_pons->bind_param("i", $id_to_delete);
    $stmt_check_pons->execute();
    $result_check = $stmt_check_pons->get_result();
    $row_check = $result_check->fetch_assoc();
    $stmt_check_pons->close();

    if ($row_check['total_pons'] > 0) {
        $message = "No se puede eliminar la OLT porque tiene " . $row_check['total_pons'] . " PONs asociados. Por favor, elimine o reasigne los PONs primero.";
        $message_class = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM olt WHERE id_olt = ?");
        $stmt->bind_param("i", $id_to_delete);
        if ($stmt->execute()) {
            $message = "OLT eliminada con éxito.";
            $message_class = 'success';
        } else {
            $message = "Error al eliminar la OLT: " . $stmt->error;
            $message_class = 'error';
        }
        $stmt->close();
    }

    echo "<script>window.location.href = 'gestion_olt.php?message=" . urlencode($message) . "&class=" . $message_class . "';</script>";
    exit;
}

// --- LÓGICA DE MODIFICACIÓN (PROCESAR FORMULARIO POST DEL MODAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_olt'])) {
    $id = $_POST['id_olt'];
    $nombre = $_POST['nombre_olt'];
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $descripcion = $_POST['descripcion'];
    $parroquias_seleccionadas = isset($_POST['parroquias_id']) ? $_POST['parroquias_id'] : [];

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // 1. Actualizar datos básicos de la OLT
        $stmt_update_olt = $conn->prepare("UPDATE olt SET nombre_olt = ?, marca = ?, modelo = ?, descripcion = ? WHERE id_olt = ?");
        $stmt_update_olt->bind_param("ssssi", $nombre, $marca, $modelo, $descripcion, $id);
        if (!$stmt_update_olt->execute()) {
            if ($conn->errno === 1062) {
                throw new Exception("El nombre de la OLT ya está en uso. Por favor, elija otro.");
            } else {
                throw new Exception("Error al actualizar OLT: " . $stmt_update_olt->error);
            }
        }
        $stmt_update_olt->close();

        // 2. Eliminar todas las relaciones viejas
        $stmt_delete_relaciones = $conn->prepare("DELETE FROM olt_parroquia WHERE olt_id = ?");
        $stmt_delete_relaciones->bind_param("i", $id);
        if (!$stmt_delete_relaciones->execute()) {
            throw new Exception("Error al limpiar relaciones: " . $stmt_delete_relaciones->error);
        }
        $stmt_delete_relaciones->close();

        // 3. Insertar las nuevas relaciones
        if (!empty($parroquias_seleccionadas)) {
            $stmt_insert_relacion = $conn->prepare("INSERT INTO olt_parroquia (olt_id, parroquia_id) VALUES (?, ?)");
            foreach ($parroquias_seleccionadas as $parroquia_id) {
                $parroquia_id_int = (int) $parroquia_id;
                $stmt_insert_relacion->bind_param("ii", $id, $parroquia_id_int);
                if (!$stmt_insert_relacion->execute()) {
                    throw new Exception("Error al insertar nueva relación: " . $stmt_insert_relacion->error);
                }
            }
            $stmt_insert_relacion->close();
        }

        // Si todo va bien
        $conn->commit();
        $message = "¡OLT y Parroquias actualizadas con éxito!";
        $message_class = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error al actualizar: " . $e->getMessage();
        $message_class = 'error';
    }

    // Redirigir para limpiar los parámetros GET y mostrar el mensaje
    echo "<script>window.location.href = 'gestion_olt.php?message=" . urlencode($message) . "&class=" . $message_class . "';</script>";
    exit;
}

// --- MANEJO DE MENSAJES DE REDIRECCIÓN ---
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_class = $_GET['class'];
}


// --- CONSULTA FINAL PARA MOSTRAR LOS DATOS (INCLUYENDO BÚSQUEDA) ---
$sql_final = $sql_base;
if (!empty($search_term)) {
    // Buscar en el nombre de la OLT o en el nombre de la parroquia
    $sql_final .= " WHERE o.nombre_olt LIKE ? OR pa.nombre_parroquia LIKE ?";
}
$sql_final .= " GROUP BY o.id_olt ORDER BY o.nombre_olt ASC";


if (!empty($search_term)) {
    $search_param = "%" . $search_term . "%";
    $stmt = $conn->prepare($sql_final);
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql_final);
}

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

$conn->close();

?>

<main class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <!-- Contenedor Principal -->
            <div class="card glass-panel border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Gestión de OLTs</h5>
                    <a href="registro_olt.php" class="btn btn-premium btn-sm d-flex align-items-center gap-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nueva OLT</span>
                    </a>
                </div>
                <div class="card-body p-0">
                    <!-- Buscador -->
                    <div class="p-4 bg-white bg-opacity-5 border-bottom border-white border-opacity-10">
                        <form action="gestion_olt.php" method="GET" class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <div class="input-group glass-input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-muted">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-start-0 ps-0"
                                        placeholder="Buscar por nombre de OLT o Parroquia..."
                                        value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-premium w-100">Buscar</button>
                            </div>
                            <?php if (!empty($search_term)): ?>
                                <div class="col-md-2">
                                    <a href="gestion_olt.php" class="btn btn-outline-secondary w-100">Limpiar</a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Tabla -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-white bg-opacity-10">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Nombre</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Parroquias Atendidas</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($data)): ?>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td class="ps-4 fw-medium text-muted">
                                                #<?php echo htmlspecialchars($row['id_olt']); ?></td>
                                            <td class="fw-bold text-main"><?php echo htmlspecialchars($row['nombre_olt']); ?>
                                            </td>
                                            <td class="text-muted"><?php echo htmlspecialchars($row['marca']); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars($row['modelo']); ?></td>
                                            <td>
                                                <?php
                                                $parroquias = $row['parroquias_atendidas'] ? explode(', ', $row['parroquias_atendidas']) : [];
                                                if (!empty($parroquias)): ?>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <?php foreach (array_slice($parroquias, 0, 3) as $p): ?>
                                                            <span
                                                                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?php echo htmlspecialchars($p); ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($parroquias) > 3): ?>
                                                            <span
                                                                class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 small">+<?php echo count($parroquias) - 3; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group gap-2">
                                                    <?php
                                                    $olt_id = $row['id_olt'];
                                                    $assigned_ids = isset($assigned_parroquias[$olt_id]) ? json_encode($assigned_parroquias[$olt_id]) : '[]';
                                                    ?>
                                                    <button type="button" data-bs-toggle="modal"
                                                        data-bs-target="#modalModificacionOLT"
                                                        data-id="<?php echo htmlspecialchars($olt_id); ?>"
                                                        data-nombre="<?php echo htmlspecialchars($row['nombre_olt']); ?>"
                                                        data-marca="<?php echo htmlspecialchars($row['marca']); ?>"
                                                        data-modelo="<?php echo htmlspecialchars($row['modelo']); ?>"
                                                        data-descripcion="<?php echo htmlspecialchars($row['descripcion']); ?>"
                                                        data-assigned-parroquias='<?php echo htmlspecialchars($assigned_ids, ENT_QUOTES, 'UTF-8'); ?>'
                                                        class="btn btn-sm btn-glass text-primary rounded-2" title="Modificar">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button type="button"
                                                        data-bs-href="gestion_olt.php?action=delete_olt&id=<?php echo urlencode($olt_id); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#eliminaModal"
                                                        class="btn btn-sm btn-glass text-danger rounded-2" title="Eliminar">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <div class="d-flex flex-column align-items-center gap-2">
                                                <i class="fa-solid fa-server fa-2x opacity-25"></i>
                                                <p class="mb-0">No se encontraron OLTs registradas</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="menu.php" class="btn btn-outline-secondary px-4">
                    <i class="fa-solid fa-arrow-left me-2"></i>Volver al Menú
                </a>
            </div>
        </div>
    </div>

    <?php include $path_to_root . 'paginas/includes/layout_foot.php'; ?>
</main>

<!-- Modal Modificación -->
<div class="modal fade" id="modalModificacionOLT" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-gradient border-0 text-white">
                <h5 class="modal-title fw-bold" id="modalModificacionOLTLabel">
                    <i class="fa-solid fa-pen-to-square me-2 opacity-75"></i>Modificar OLT
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="form-modificacion-olt" action="gestion_olt.php" method="POST" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="update_olt" value="1">
                    <input type="hidden" name="id_olt" id="id_olt_modal" value="">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_olt_modal"
                                class="form-label fw-semibold text-secondary small text-uppercase">Nombre OLT</label>
                            <input type="text" id="nombre_olt_modal" name="nombre_olt" class="form-control" required
                                placeholder="Ej: Huawei MA5608T">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="marca_modal"
                                class="form-label fw-semibold text-secondary small text-uppercase">Marca</label>
                            <input type="text" id="marca_modal" name="marca" class="form-control" required
                                placeholder="Ej: Huawei">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modelo_modal"
                                class="form-label fw-semibold text-secondary small text-uppercase">Modelo</label>
                            <input type="text" id="modelo_modal" name="modelo" class="form-control" required
                                placeholder="Modelo específico">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-secondary small text-uppercase mb-2">Parroquias
                                que Atiende</label>
                            <div class="border border-white border-opacity-10 rounded bg-white bg-opacity-10 p-3" style="max-height: 200px; overflow-y: auto;">
                                <div id="parroquias-checkbox-container">
                                    <?php if (!empty($parroquias_disponibles)): ?>
                                        <?php foreach ($parroquias_disponibles as $parroquia): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    id="modal_parroquia_<?php echo htmlspecialchars($parroquia['id_parroquia']); ?>"
                                                    name="parroquias_id[]"
                                                    value="<?php echo htmlspecialchars($parroquia['id_parroquia']); ?>">
                                                <label class="form-check-label"
                                                    for="modal_parroquia_<?php echo htmlspecialchars($parroquia['id_parroquia']); ?>">
                                                    <?php echo htmlspecialchars($parroquia['nombre_parroquia']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted small mb-0">No hay parroquias disponibles.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion_modal"
                            class="form-label fw-semibold text-secondary small text-uppercase">Descripción</label>
                        <textarea id="descripcion_modal" name="descripcion" class="form-control" rows="3"
                            placeholder="Información técnica adicional..."></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btn-actualizar-olt" class="btn btn-primary px-4">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminación -->
<div class="modal fade" id="eliminaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-danger">
                    <i class="fa-solid fa-circle-exclamation fa-3x"></i>
                </div>
                <h5 class="mb-2 fw-bold">¿Eliminar registro?</h5>
                <p class="text-muted small mb-4">Esta acción no se puede deshacer.</p>
                <div class="d-grid gap-2">
                    <a href="#" class="btn btn-danger btn-ok fw-medium">Eliminar</a>
                    <button type="button" class="btn btn-outline-secondary fw-medium"
                        data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // -----------------------------------------------------
    // 1. LÓGICA DEL MODAL DE MODIFICACIÓN
    // -----------------------------------------------------
    const modalModificacionOLT = document.getElementById('modalModificacionOLT');
    const formModificacionOLT = document.getElementById('form-modificacion-olt');

    if (modalModificacionOLT) {
        modalModificacionOLT.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;

            // 1. Obtener los datos (incluyendo el JSON de parroquias asignadas)
            const id = button.getAttribute('data-id');
            const nombre = button.getAttribute('data-nombre');
            const marca = button.getAttribute('data-marca');
            const modelo = button.getAttribute('data-modelo');
            const descripcion = button.getAttribute('data-descripcion');
            const assignedParroquiasJson = button.getAttribute('data-assigned-parroquias');

            let assignedParroquiasIds = [];
            try {
                // El atributo está codificado como string HTML, lo parseamos a JSON
                assignedParroquiasIds = JSON.parse(assignedParroquiasJson);
            } catch (e) {
                console.error("Error al parsear parroquias asignadas:", e);
            }

            // 2. Asignar valores a los campos simples del modal
            document.getElementById('modalModificacionOLTLabel').innerHTML = `<i class="fa-solid fa-pen-to-square me-2 opacity-75"></i>Modificar OLT: ${nombre}`;
            document.getElementById('id_olt_modal').value = id;
            document.getElementById('nombre_olt_modal').value = nombre;
            document.getElementById('marca_modal').value = marca;
            document.getElementById('modelo_modal').value = modelo;
            document.getElementById('descripcion_modal').value = descripcion;

            // 3. Limpiar y Chequear Checkboxes de Parroquias
            const checkboxes = document.querySelectorAll('#parroquias-checkbox-container input[type="checkbox"]');

            checkboxes.forEach(checkbox => {
                // Primero, desmarcar todos los checkboxes
                checkbox.checked = false;

                // Convertir el valor del checkbox (string) a número para la comparación
                const parroquiaId = parseInt(checkbox.value);

                // Si el ID de la parroquia está en el array de IDs asignados, chequearlo
                if (assignedParroquiasIds.includes(parroquiaId)) {
                    checkbox.checked = true;
                }
            });

            // 4. Reiniciar la validación de Bootstrap
            formModificacionOLT.classList.remove('was-validated');
        });

        // 5. Lógica para el botón de Actualizar y validación manual
        const btnActualizarOLT = document.getElementById('btn-actualizar-olt');

        if (btnActualizarOLT && formModificacionOLT) {
            btnActualizarOLT.addEventListener('click', function (event) {
                // Evita el envío por defecto si la validación falla
                if (!formModificacionOLT.checkValidity()) {
                    event.preventDefault();
                    formModificacionOLT.classList.add('was-validated');
                } else {
                    formModificacionOLT.submit(); // Enviar el formulario
                }
            });
        }
    }

    // -----------------------------------------------------
    // 2. LÓGICA DEL MODAL DE ELIMINACIÓN
    // -----------------------------------------------------
    let eliminaModal = document.getElementById('eliminaModal');

    if (eliminaModal) {
        eliminaModal.addEventListener('shown.bs.modal', event => {
            let button = event.relatedTarget;
            // Obtenemos la URL del atributo data-bs-href (que es lo que se envía al PHP)
            let url = button.getAttribute('data-bs-href');

            // Asignamos la URL al botón de confirmación 'Eliminar' (clase .btn-ok)
            let btnOk = eliminaModal.querySelector('.modal-footer .btn-ok');
            if (btnOk) {
                btnOk.href = url;
            }
        });
    }

    // --- LOGICA DE AUTORIZACION (CONTRASEÑA) ---
    let isAuthVerified = false;
    let currentAuthorizedButton = null;

    // modalModificacionOLT es el id del modal, pero ojo, en el boton de OLT
    // podria estar llamandose solo modalModificacion. Dejamos ambos para asegurar.
    const modalsToProtect = ['modalModificacionOLT', 'eliminaModal'];

    modalsToProtect.forEach(modalId => {
        const modalEl = document.getElementById(modalId);
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function (e) {
                if (isAuthVerified && currentAuthorizedButton === e.relatedTarget) {
                    return; // Permitir que el modal se muestre
                }

                // Prevenir que el modal se muestre
                e.preventDefault();
                const btn = e.relatedTarget;

                Swal.fire({
                    title: 'Verificación requerida',
                    text: 'Ingrese su contraseña de administrador para continuar',
                    input: 'password',
                    inputAttributes: {
                        autocapitalize: 'off',
                        autocorrect: 'off'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Verificar',
                    cancelButtonText: 'Cancelar',
                    showLoaderOnConfirm: true,
                    preConfirm: (clave) => {
                        if (!clave) {
                            Swal.showValidationMessage('La contraseña es requerida');
                            return false;
                        }
                        return fetch('principal/verificar_clave.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({ clave: clave })
                        })
                            .then(response => {
                                if (!response.ok) throw new Error('Error en la red');
                                return response.json();
                            })
                            .then(data => {
                                if (!data.success) throw new Error(data.message || 'Contraseña incorrecta');
                                return true;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(`Error: ${error.message}`);
                            });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        isAuthVerified = true;
                        currentAuthorizedButton = btn;

                        Swal.fire({
                            icon: 'success',
                            title: 'Acceso autorizado',
                            timer: 1000,
                            showConfirmButton: false
                        }).then(() => {
                            // Transferir el href manualmente por si bsModal pierde el relatedTarget
                            if (modalId === 'eliminaModal') {
                                const btnOk = modalEl.querySelector('.btn-ok');
                                const url = btn.getAttribute('data-bs-href');
                                if (btnOk && url) {
                                    btnOk.href = url;
                                }
                            }

                            // Volver a disparar el modal programaticamente
                            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                            bsModal.show(btn);

                            // Resetear el flag despues de que el modal se muestre
                            setTimeout(() => {
                                isAuthVerified = false;
                                currentAuthorizedButton = null;
                            }, 500);
                        });
                    }
                });
            });
        }
    });
    // --- FIN LOGICA AUTORIZACION ---
</script>
</body>

</html>