<?php
/**
 * Gestión de Bancos - Migración a JSON API con Seguridad
 */
require_once 'conexion.php';

$message = isset($_GET['message']) ? $_GET['message'] : '';
$message_class = isset($_GET['class']) ? $_GET['class'] : '';

$page_title = "Gestión de Bancos";
$breadcrumb = ["Admin"];
$back_url = "menu.php";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">
        <div class="container-fluid">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_class == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show"
                    role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card glass-panel border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Listado de Bancos (API JSON)</h5>
                    <button type="button" class="btn btn-premium btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal"
                        data-bs-target="#modalRegistroBanco">
                        <i class="fa-solid fa-plus"></i>
                        <span>Nuevo Banco</span>
                    </button>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tabla_bancos_json">
                            <thead class="bg-white bg-opacity-10">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Nombre del Banco</th>
                                    <th>Número de Cuenta</th>
                                    <th>Propietario</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="lista_bancos_api">
                                <tr>
                                    <td colspan="5" class="text-center p-4"><i
                                            class="fas fa-spinner fa-spin me-2"></i>Cargando datos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <div id="pagination-container" class="d-flex justify-content-between align-items-center px-3">
                        <small class="text-muted" id="pagination-info">Mostrando 0 de 0 bancos</small>
                        <nav aria-label="Navegación de bancos">
                            <ul class="pagination pagination-sm mb-0" id="pagination-list">
                                <!-- Pagination items will be injected here -->
                            </ul>
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


<!-- Modal Registro/Nuevo -->
<div class="modal fade" id="modalRegistroBanco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-gradient border-0 text-white">
                <h5 class="modal-title fw-bold">Registrar Nuevo Banco</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-registro-banco">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Nombre del Banco</label>
                        <input type="text" name="nombre_banco" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Número de Cuenta</label>
                        <input type="text" name="numero_cuenta" class="form-control font-monospace"
                            placeholder="0000-0000-00-0000000000" required>
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">Cédula</label>
                            <input type="text" name="cedula_propietario" class="form-control" placeholder="V-12345678"
                                required>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">Titular</label>
                            <input type="text" name="titular_cuenta" class="form-control" placeholder="Nombre completo"
                                required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Métodos de Pago Soportados</label>
                        <div class="d-flex flex-wrap gap-3 p-2 border border-white border-opacity-10 rounded bg-white bg-opacity-10">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Pago Móvil" id="reg_pago_movil">
                                <label class="form-check-label small" for="reg_pago_movil">Pago Móvil</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Transferencia" id="reg_transferencia">
                                <label class="form-check-label small" for="reg_transferencia">Transferencia</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Zelle" id="reg_zelle">
                                <label class="form-check-label small" for="reg_zelle">Zelle</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Efectivo" id="reg_efectivo">
                                <label class="form-check-label small" for="reg_efectivo">Efectivo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Divisas" id="reg_divisas">
                                <label class="form-check-label small" for="reg_divisas">Divisas</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Guardar Banco</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edición -->
<div class="modal fade" id="modalEditBanco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-gradient border-0 text-white">
                <h5 class="modal-title fw-bold">Editar Banco</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-edit-banco">
                <input type="hidden" name="id_banco" id="edit_id_banco">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Nombre del Banco</label>
                        <input type="text" name="nombre_banco" id="edit_nombre_banco" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Número de Cuenta</label>
                        <input type="text" name="numero_cuenta" id="edit_numero_cuenta"
                            class="form-control font-monospace" placeholder="0000-0000-00-0000000000" required>
                    </div>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">Cédula</label>
                            <input type="text" name="cedula_propietario" id="edit_cedula_propietario"
                                class="form-control" placeholder="V-12345678" required>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">Titular</label>
                            <input type="text" name="titular_cuenta" id="edit_titular_cuenta" class="form-control"
                                placeholder="Nombre completo" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold text-uppercase">Métodos de Pago Soportados</label>
                        <div class="d-flex flex-wrap gap-3 p-2 border border-white border-opacity-10 rounded bg-white bg-opacity-10">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Pago Móvil" id="edit_pago_movil">
                                <label class="form-check-label small" for="edit_pago_movil">Pago Móvil</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Transferencia" id="edit_transferencia">
                                <label class="form-check-label small" for="edit_transferencia">Transferencia</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Zelle" id="edit_zelle">
                                <label class="form-check-label small" for="edit_zelle">Zelle</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Efectivo" id="edit_efectivo">
                                <label class="form-check-label small" for="edit_efectivo">Efectivo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Divisas" id="edit_divisas">
                                <label class="form-check-label small" for="edit_divisas">Divisas</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Actualizar Banco</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/jquery.min.js"></script>

<script>
    const API_URL = 'principal/json_bancos_api.php';
    let currentPage = 1;
    const itemsPerPage = 10;

    $(document).ready(function () {
        cargarBancos(currentPage);

        $('#form-edit-banco').on('submit', async function (e) {
            e.preventDefault();
            const proceeds = await solicitarClaveAdmin('Actualizar Banco');
            if (!proceeds) return;

            const formData = new FormData(this);
            try {
                const resp = await fetch(API_URL + '?action=update', {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire('¡Éxito!', 'Banco actualizado correctamente.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalEditBanco')).hide();
                    cargarBancos(currentPage);
                } else {
                    Swal.fire('Error', res.message || 'Error al actualizar', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });
    });

    async function cargarBancos(page = 1) {
        currentPage = page;
        try {
            const resp = await fetch(`${API_URL}?action=get&page=${page}&limit=${itemsPerPage}`);
            const result = await resp.json();
            const data = result.data;
            const tbody = document.getElementById('lista_bancos_api');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-muted">No hay bancos registrados.</td></tr>';
                renderPagination(result);
                return;
            }

            data.forEach(b => {
                const tr = document.createElement('tr');
                const metodos = b.metodos_pago || [];
                const metodosHtml = metodos.map(m => `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-1" style="font-size: 0.65rem;">${m}</span>`).join('');

                tr.innerHTML = `
                    <td class="ps-4 text-muted">#${b.id_banco}</td>
                    <td>
                        <div class="fw-bold text-main">${b.nombre_banco}</div>
                        <div class="mt-1">${metodosHtml || '<small class="text-muted italic">Sin métodos</small>'}</div>
                    </td>
                    <td class="font-monospace text-muted">${b.numero_cuenta || 'N/A'}</td>
                    <td>
                        <div class="fw-semibold text-main">${b.nombre_propietario || 'Sin titular'}</div>
                        <small class="text-muted">${b.cedula_propietario || ''}</small>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group gap-2">
                            <button class="btn btn-sm btn-glass text-primary" onclick='prepareEdit(${JSON.stringify(b)})' title="Editar">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="btn btn-sm btn-glass text-danger" onclick="eliminarBanco('${b.id_banco}')" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            renderPagination(result);
        } catch (e) {
            console.error(e);
            document.getElementById('lista_bancos_api').innerHTML = '<tr><td colspan="5" class="text-center text-danger p-4">Error al cargar datos.</td></tr>';
        }
    }

    function renderPagination(info) {
        const infoText = document.getElementById('pagination-info');
        const start = (info.page - 1) * info.limit + 1;
        const end = Math.min(info.page * info.limit, info.total);
        infoText.innerText = `Mostrando ${info.total > 0 ? start : 0} a ${end} de ${info.total} bancos`;

        const list = document.getElementById('pagination-list');
        list.innerHTML = '';

        if (info.pages <= 1) return;

        // Previous
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${info.page === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); cargarBancos(${info.page - 1})"><i class="fas fa-chevron-left"></i></a>`;
        list.appendChild(prevLi);

        // Pages
        for (let i = 1; i <= info.pages; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${info.page === i ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); cargarBancos(${i})">${i}</a>`;
            list.appendChild(li);
        }

        // Next
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${info.page === info.pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); cargarBancos(${info.page + 1})"><i class="fas fa-chevron-right"></i></a>`;
        list.appendChild(nextLi);
    }

    window.prepareEdit = function (banco) {
        document.getElementById('edit_id_banco').value = banco.id_banco;
        document.getElementById('edit_nombre_banco').value = banco.nombre_banco;
        document.getElementById('edit_numero_cuenta').value = banco.numero_cuenta || '';
        document.getElementById('edit_cedula_propietario').value = banco.cedula_propietario || '';
        document.getElementById('edit_titular_cuenta').value = banco.nombre_propietario || '';

        // Limpiar y marcar métodos de pago
        const metodos = banco.metodos_pago || [];
        $('#modalEditBanco input[name="metodos_pago[]"]').prop('checked', false);
        metodos.forEach(m => {
            $(`#modalEditBanco input[name="metodos_pago[]"][value="${m}"]`).prop('checked', true);
        });

        const modal = new bootstrap.Modal(document.getElementById('modalEditBanco'));
        modal.show();
    }

    async function solicitarClaveAdmin(titulo = 'Confirmar Acción') {
        const focusHandler = (e) => {
            if (e.target.closest(".swal2-container")) {
                e.stopImmediatePropagation();
            }
        };
        document.addEventListener('focusin', focusHandler, true);

        const { value: password } = await Swal.fire({
            title: titulo,
            input: 'password',
            inputLabel: 'Ingrese la clave de administrador para proceder',
            inputPlaceholder: 'Clave de seguridad',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            didClose: () => {
                document.removeEventListener('focusin', focusHandler, true);
            }
        });

        if (password) {
            const resp = await fetch('principal/verificar_clave.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'clave=' + encodeURIComponent(password)
            });
            const data = await resp.json();
            if (data.success) return true;
            Swal.fire('Error', 'Clave incorrecta', 'error');
        }
        return false;
    }

    $('#form-registro-banco').on('submit', async function (e) {
        e.preventDefault();

        const proceeds = await solicitarClaveAdmin('Registrar Nuevo Banco');
        if (!proceeds) return;

        const formData = new FormData(this);
        try {
            const resp = await fetch(API_URL + '?action=add', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                Swal.fire('¡Éxito!', 'Banco registrado correctamente.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalRegistroBanco')).hide();
                this.reset();
                cargarBancos(1);
            } else {
                Swal.fire('Error', res.message || 'Error al guardar', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        }
    });

    window.eliminarBanco = async function (id) {
        const proceeds = await solicitarClaveAdmin('Eliminar Banco');
        if (!proceeds) return;

        const formData = new FormData();
        formData.append('id', id);
        try {
            const resp = await fetch(API_URL + '?action=delete', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                Swal.fire('Eliminado', 'El banco ha sido eliminado con éxito.', 'success');
                cargarBancos(currentPage);
            } else {
                Swal.fire('Error', res.message || 'Error al eliminar', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        }
    };

</script>

<?php require_once 'includes/layout_foot.php'; ?>