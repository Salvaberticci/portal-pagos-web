<?php
/**
 * Gestión de Planes - Migración a AJAX con Seguridad
 */
require_once 'conexion.php';

$path_to_root = "../";
$page_title = "Gestión de Planes";
$breadcrumb = ["Admin"];
$back_url = "menu.php";
include $path_to_root . 'paginas/includes/layout_head.php';
include $path_to_root . 'paginas/includes/sidebar.php';
include $path_to_root . 'paginas/includes/header.php';

$message = isset($_GET['message']) ? $_GET['message'] : '';
$message_class = isset($_GET['class']) ? $_GET['class'] : '';
?>


<main class="main-content">
    <div class="page-content">
        <div class="container-fluid">
            <!-- Alertas -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_class === 'success' ? 'success' : ($message_class === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show shadow-sm"
                    role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i
                            class="fa-solid <?php echo $message_class === 'success' ? 'fa-circle-check' : ($message_class === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark'); ?>"></i>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Contenedor Principal -->
            <div class="card glass-panel border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Listado de Planes</h5>
                    <button type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoPlan"
                        class="btn btn-premium btn-sm d-flex align-items-center gap-2">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nuevo Plan</span>
                    </button>
                </div>
                <div class="card-body p-0">
                    <!-- Buscador -->
                    <div class="p-4 bg-white bg-opacity-5 border-bottom border-white border-opacity-10">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <div class="input-group glass-input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-muted">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0"
                                        placeholder="Buscar por nombre de plan...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-white bg-opacity-10">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Nombre</th>
                                    <th>Monto (USD)</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Clientes</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="lista_planes_api">
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-spinner fa-spin me-2"></i> Cargando planes...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <div id="pagination-container" class="d-flex justify-content-between align-items-center px-3">
                        <small class="text-muted" id="pagination-info">Mostrando 0 de 0 planes</small>
                        <nav aria-label="Navegación de planes">
                            <ul class="pagination pagination-sm mb-0" id="pagination-list"></ul>
                        </nav>
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


</main>


<!-- Modal Modificación -->
<div class="modal fade" id="modalModificacionPlan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalModificacionPlanLabel">
                    <i class="fa-solid fa-pen-to-square me-2 opacity-75"></i>Modificar Plan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="form-modificacion-plan" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_plan" id="id_plan_modal" value="">

                    <div class="mb-3">
                        <label for="nombre_plan_modal"
                            class="form-label fw-semibold text-muted small text-uppercase">Nombre del Plan</label>
                        <input type="text" id="nombre_plan_modal" name="nombre_plan" class="form-control" required
                            placeholder="Ej: Fibra 100MB">
                    </div>

                    <div class="mb-3">
                        <label for="monto_modal"
                            class="form-label fw-semibold text-muted small text-uppercase">Monto (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="monto_modal" name="monto" step="0.01" min="0" class="form-control"
                                required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion_modal"
                            class="form-label fw-semibold text-muted small text-uppercase">Descripción</label>
                        <textarea id="descripcion_modal" name="descripcion" class="form-control" rows="3"
                            placeholder="Detalles del servicio..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Migración -->
<div class="modal fade" id="modalMigrarClientes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-shuffle me-2"></i>Migrar Clientes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-migrar-clientes">
                <input type="hidden" name="id_old" id="migrate_id_old">
                <div class="modal-body p-4">
                    <div class="alert alert-warning small mb-4">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Este plan tiene <strong id="migrate_client_count">0</strong> clientes. Para eliminarlo, primero
                        debes migrarlos a otro plan.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small text-uppercase">Seleccionar Plan de
                            Destino</label>
                        <select name="id_new" id="migrate_id_new" class="form-select" required>
                            <option value="">Cargando planes...</option>
                        </select>
                    </div>
                    <p class="text-muted small">
                        Nota: Los contratos de estos clientes se actualizarán automáticamente con el nuevo plan y su
                        respectivo monto mensual.
                    </p>
                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold">Migrar y Continuar</button>
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
                <h5 class="mb-2 fw-bold text-main">¿Eliminar registro?</h5>
                <p class="text-muted small mb-4">Esta acción no se puede deshacer.</p>
                <input type="hidden" id="id_to_delete">
                <div class="d-grid gap-2">
                    <button type="button" onclick="confirmDelete()" class="btn btn-danger fw-medium">Eliminar</button>
                    <button type="button" class="btn btn-light text-secondary fw-medium"
                        data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal Nuevo Plan -->
<div class="modal fade" id="modalNuevoPlan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus-circle me-2 opacity-75"></i>Registrar Nuevo Plan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="form-nuevo-plan" novalidate>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="nombre_plan_nuevo"
                            class="form-label fw-semibold text-muted small text-uppercase">Nombre del Plan</label>
                        <input type="text" id="nombre_plan_nuevo" name="nombre_plan" class="form-control" required
                            placeholder="Ej: Fibra 100MB">
                    </div>

                    <div class="mb-3">
                        <label for="monto_nuevo"
                            class="form-label fw-semibold text-muted small text-uppercase">Monto (USD)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="monto_nuevo" name="monto" step="0.01" min="0" class="form-control"
                                required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion_nuevo"
                            class="form-label fw-semibold text-muted small text-uppercase">Descripción</label>
                        <textarea id="descripcion_nuevo" name="descripcion" class="form-control" rows="3"
                            placeholder="Detalles del servicio..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4">Registrar Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Scripts Dependencies -->
<script src="../js/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const API_URL = 'principal/api_planes.php';
    let currentPage = 1;
    const itemsPerPage = 10;

    $(document).ready(function () {
        cargarPlanes(currentPage);

        // Búsqueda con delay (debounce)
        let searchTimer;
        $('#searchInput').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                cargarPlanes(1);
            }, 500);
        });

        // Registro de Plan
        $('#form-nuevo-plan').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const resp = await fetch(API_URL + '?action=add', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire('¡Éxito!', 'Plan registrado correctamente.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevoPlan')).hide();
                    this.reset();
                    cargarPlanes(1);
                } else {
                    Swal.fire('Error', res.message || 'Error al guardar', 'error');
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });

        // Edición de Plan
        $('#form-modificacion-plan').on('submit', async function (e) {
            e.preventDefault();
            const monto = $('#monto_modal').val();

            const result = await Swal.fire({
                title: '¿Confirmar cambios?',
                text: "Si cambias el precio, se actualizará el monto mensual de TODOS los clientes vinculados a este plan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            const formData = new FormData(this);
            try {
                const resp = await fetch(API_URL + '?action=update', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire('¡Éxito!', 'Plan y contratos actualizados correctamente.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalModificacionPlan')).hide();
                    cargarPlanes(currentPage);
                } else {
                    Swal.fire('Error', res.message || 'Error al actualizar', 'error');
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });

        // Migración de Clientes
        $('#form-migrar-clientes').on('submit', async function (e) {
            e.preventDefault();
            const id_new = $('#migrate_id_new').val();
            if (!id_new) {
                Swal.fire('Atención', 'Selecciona un plan de destino', 'warning');
                return;
            }

            const formData = new FormData(this);
            try {
                const resp = await fetch(API_URL + '?action=migrate', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire('¡Éxito!', `Se han migrado ${res.migrated} clientes correctamente. Ahora puedes eliminar el plan.`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalMigrarClientes')).hide();
                    cargarPlanes(currentPage);
                } else {
                    Swal.fire('Error', res.message || 'Error en la migración', 'error');
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });
    });

    async function cargarPlanes(page = 1) {
        currentPage = page;
        const search = $('#searchInput').val();
        try {
            const resp = await fetch(`${API_URL}?action=get&page=${page}&limit=${itemsPerPage}&search=${encodeURIComponent(search)}`);
            const result = await resp.json();
            const data = result.data;
            const tbody = document.getElementById('lista_planes_api');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron planes.</td></tr>';
                renderPagination(result);
                return;
            }

            data.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-4 fw-medium text-muted">#${p.id_plan}</td>
                    <td class="fw-bold text-main">${p.nombre_plan}</td>
                    <td>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3">
                            $${parseFloat(p.monto).toFixed(2)}
                        </span>
                    </td>
                    <td class="text-muted small text-truncate" style="max-width: 300px;">
                        ${p.descripcion || ''}
                    </td>
                    <td class="text-center">
                        <span class="badge ${p.clientes_activos > 0 ? 'bg-primary shadow-sm' : 'bg-secondary bg-opacity-25 text-muted'} rounded-pill">
                            ${p.clientes_activos}
                        </span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group gap-2">
                            <button class="btn btn-sm btn-glass text-primary rounded-2" onclick='prepareEdit(${JSON.stringify(p)})' title="Modificar">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="btn btn-sm btn-glass text-danger rounded-2" onclick="openDeleteModal(${p.id_plan}, ${p.clientes_activos})" title="Eliminar">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            renderPagination(result);
        } catch (e) {
            console.error(e);
            document.getElementById('lista_planes_api').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Error al cargar datos.</td></tr>';
        }
    }

    function renderPagination(info) {
        const infoText = document.getElementById('pagination-info');
        const start = info.total > 0 ? (info.page - 1) * info.limit + 1 : 0;
        const end = Math.min(info.page * info.limit, info.total);
        infoText.innerText = `Mostrando ${start} a ${end} de ${info.total} planes`;

        const list = document.getElementById('pagination-list');
        list.innerHTML = '';

        if (info.pages <= 1) return;

        // Previous
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${info.page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); cargarPlanes(${info.page - 1})"><i class="fas fa-chevron-left"></i></a>`;
        list.appendChild(prevLi);

        // Pages
        for (let i = 1; i <= info.pages; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${info.page === i ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); cargarPlanes(${i})">${i}</a>`;
            list.appendChild(li);
        }

        // Next
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${info.page === info.pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); cargarPlanes(${info.page + 1})"><i class="fas fa-chevron-right"></i></a>`;
        list.appendChild(nextLi);
    }

    window.prepareEdit = function (p) {
        document.getElementById('id_plan_modal').value = p.id_plan;
        document.getElementById('nombre_plan_modal').value = p.nombre_plan;
        document.getElementById('monto_modal').value = parseFloat(p.monto).toFixed(2);
        document.getElementById('descripcion_modal').value = p.descripcion || '';

        const modal = new bootstrap.Modal(document.getElementById('modalModificacionPlan'));
        modal.show();
    }

    window.openDeleteModal = function (id, count) {
        document.getElementById('id_to_delete').value = id;
        document.getElementById('id_to_delete').dataset.count = count;

        const modal = new bootstrap.Modal(document.getElementById('eliminaModal'));
        modal.show();
    }

    window.confirmDelete = async function () {
        const id = document.getElementById('id_to_delete').value;
        const count = parseInt(document.getElementById('id_to_delete').dataset.count);

        if (count > 0) {
            bootstrap.Modal.getInstance(document.getElementById('eliminaModal')).hide();
            openMigrationModal(id, count);
            return;
        }

        const formData = new FormData();
        formData.append('id_plan', id);

        try {
            const resp = await fetch(API_URL + '?action=delete', { method: 'POST', body: formData });
            const res = await resp.json();
            if (res.success) {
                Swal.fire('Eliminado', 'El plan ha sido eliminado con éxito.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('eliminaModal')).hide();
                cargarPlanes(currentPage);
            } else {
                Swal.fire('Error', res.message || 'Error al eliminar', 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        }
    }

    async function openMigrationModal(id, count) {
        document.getElementById('migrate_id_old').value = id;
        document.getElementById('migrate_client_count').innerText = count;

        const select = document.getElementById('migrate_id_new');
        select.innerHTML = '<option value="">Cargando planes...</option>';

        try {
            const resp = await fetch(API_URL + '?action=get&limit=100'); // Cargar todos para migración
            const result = await resp.json();
            select.innerHTML = '<option value="">Seleccione un plan...</option>';
            result.data.forEach(p => {
                if (p.id_plan != id) {
                    const opt = document.createElement('option');
                    opt.value = p.id_plan;
                    opt.innerText = `${p.nombre_plan} ($${parseFloat(p.monto).toFixed(2)})`;
                    select.appendChild(opt);
                }
            });

            const modal = new bootstrap.Modal(document.getElementById('modalMigrarClientes'));
            modal.show();
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudieron cargar los planes para migración', 'error');
        }
    }

</script>

<?php include $path_to_root . 'paginas/includes/layout_foot.php'; ?>