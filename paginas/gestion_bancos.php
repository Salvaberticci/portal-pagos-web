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
                                    <th class="text-center">Habilitado para el Portal</th>
                                    <th class="text-center">API</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="lista_bancos_api">
                                <tr>
                                    <td colspan="7" class="text-center p-4"><i
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
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" id="reg_activo" checked value="1">
                            <label class="form-check-label small fw-bold text-muted text-uppercase" for="reg_activo">Habilitado en Portal de Clientes</label>
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
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="activo" id="edit_activo" checked value="1">
                            <label class="form-check-label small fw-bold text-muted text-uppercase" for="edit_activo">Habilitado en Portal de Clientes</label>
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


<!-- Modal Configuración de API -->
<div class="modal fade" id="modalApiConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg,#7c3aed,#4f46e5);">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-plug me-2"></i>Configurar API del Banco</h5>
                    <small id="api_config_banco_nombre" class="opacity-75"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-api-config">
                <input type="hidden" id="api_config_id_banco" name="id_banco">
                <div class="modal-body p-4">

                    <!-- Switch habilitar -->
                    <div class="p-3 rounded-3 mb-4 d-flex align-items-center justify-content-between"
                         style="background:var(--bg-card);border:1px solid var(--border-glass);">
                        <div>
                            <div class="fw-bold">Habilitar auto-verificación por API</div>
                            <small class="text-muted">Al habilitar, los pagos de este banco se verificarán automáticamente</small>
                        </div>
                        <div class="form-check form-switch mb-0 ms-3">
                            <input class="form-check-input" type="checkbox" id="api_habilitada" name="api_habilitada"
                                   role="switch" style="width:3rem;height:1.5rem;" onchange="toggleApiFields()">
                        </div>
                    </div>

                    <!-- Campos (se ocultan si deshabilitado) -->
                    <div id="api_fields_wrapper">
                        <!-- Tipo de API -->
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">Tipo de API / Banco</label>
                            <select class="form-select" id="api_tipo" name="api_tipo" onchange="autoFillEndpoint()">
                                <option value="">-- Selecciona un tipo --</option>
                                <option value="bdv">🏦 Banco de Venezuela (BDV)</option>
                                <!-- Futuras opciones:
                                <option value="banesco">🏦 Banesco</option>
                                <option value="mercantil">🏦 Mercantil</option>
                                -->
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label small text-muted fw-bold text-uppercase">API Key</label>
                                <div class="input-group">
                                    <input type="password" class="form-control font-monospace" id="api_key" name="api_key"
                                           placeholder="Clave secreta de la API" autocomplete="new-password">
                                    <button type="button" class="btn btn-outline-secondary" id="btn_toggle_apikey"
                                            onclick="toggleApiKeyVisibility()" title="Mostrar/Ocultar">
                                        <i class="fa-solid fa-eye" id="icon_apikey"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted fw-bold text-uppercase">Titular de la cuenta</label>
                                <input type="text" class="form-control" id="api_titular" name="api_titular"
                                       placeholder="EMPRESA C.A.">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">Número de Cuenta (20 dígitos)</label>
                            <input type="text" class="form-control font-monospace" id="api_cuenta" name="api_cuenta"
                                   placeholder="01020000000000000000" maxlength="20">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold text-uppercase">URL del Endpoint</label>
                            <div class="input-group">
                                <input type="url" class="form-control font-monospace" id="api_endpoint" name="api_endpoint"
                                       placeholder="https://...">
                                <button type="button" class="btn btn-outline-secondary" onclick="autoFillEndpoint()" title="Restaurar URL por defecto">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                            </div>
                            <div class="form-text">Se auto-rellena al seleccionar el tipo de API.</div>
                        </div>

                        <!-- Resultado de prueba -->
                        <div id="api_test_result" class="rounded-3 p-3 d-none" style="border:1px solid var(--border-glass);"></div>
                    </div>

                </div>
                <div class="modal-footer border-0 py-3" style="background:var(--bg-card);">
                    <button type="button" class="btn btn-outline-secondary me-auto" id="btn_test_api" onclick="testApiConnection()">
                        <i class="fa-solid fa-satellite-dish me-2"></i>Probar Conexión
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn px-4 text-white fw-semibold"
                            style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border:none;">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Configuración
                    </button>
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
            formData.set('activo', this.activo.checked ? '1' : '0');
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
                tbody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-muted">No hay bancos registrados.</td></tr>';
                renderPagination(result);
                return;
            }

            data.forEach(b => {
                const tr = document.createElement('tr');
                const metodos = b.metodos_pago || [];
                const metodosHtml = metodos.map(m => `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 me-1" style="font-size: 0.65rem;">${m}</span>`).join('');

                // Badge de estado de API
                const apiCfg = b.api_config || null;
                let apiBadge;
                if (apiCfg && apiCfg.habilitada) {
                    const tipoLabel = (apiCfg.tipo || '').toUpperCase();
                    apiBadge = `<span class="badge d-inline-flex align-items-center gap-1" style="background:rgba(34,197,94,.12);color:#16a34a;border:1px solid rgba(34,197,94,.25);font-size:.68rem;"><i class="fa-solid fa-plug"></i>${tipoLabel}</span>`;
                } else if (apiCfg && !apiCfg.habilitada) {
                    apiBadge = `<span class="badge d-inline-flex align-items-center gap-1" style="background:rgba(148,163,184,.1);color:#94a3b8;border:1px solid rgba(148,163,184,.25);font-size:.68rem;"><i class="fa-solid fa-plug-circle-xmark"></i>Inactiva</span>`;
                } else {
                    apiBadge = `<span class="badge d-inline-flex align-items-center gap-1" style="background:rgba(251,191,36,.1);color:#d97706;border:1px solid rgba(251,191,36,.25);font-size:.68rem;"><i class="fa-solid fa-triangle-exclamation"></i>Sin API</span>`;
                }

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
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input" type="checkbox" role="switch" id="status_switch_${b.id_banco}" ${b.activo !== false ? 'checked' : ''} onchange="toggleBancoStatus('${b.id_banco}', this.checked)">
                        </div>
                    </td>
                    <td class="text-center">${apiBadge}</td>
                    <td class="text-end pe-4">
                        <div class="btn-group gap-2">
                            <button class="btn btn-sm btn-glass text-primary" onclick='prepareEdit(${JSON.stringify(b)})' title="Editar">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="btn btn-sm btn-glass" style="color:#8b5cf6" onclick='prepareApiConfig(${JSON.stringify(b)})' title="Configurar API">
                                <i class="fa-solid fa-plug"></i>
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
            document.getElementById('lista_bancos_api').innerHTML = '<tr><td colspan="6" class="text-center text-danger p-4">Error al cargar datos.</td></tr>';
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
        document.getElementById('edit_activo').checked = (banco.activo !== false);

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
        formData.set('activo', this.activo.checked ? '1' : '0');
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

    window.toggleBancoStatus = async function (id, isChecked) {
        const formData = new FormData();
        formData.append('id_banco', id);
        formData.append('activo', isChecked ? '1' : '0');
        try {
            const resp = await fetch(API_URL + '?action=toggle_status', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                // Toast de éxito muy sutil
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: isChecked ? 'Banco habilitado en el portal' : 'Banco deshabilitado en el portal'
                });
            } else {
                Swal.fire('Error', res.message || 'Error al actualizar estado', 'error');
                // Revertir checkbox
                document.getElementById('status_switch_' + id).checked = !isChecked;
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            document.getElementById('status_switch_' + id).checked = !isChecked;
        }
    };

    // ── Configuración de API ──────────────────────────────────────────────────

    const ENDPOINT_PRESETS = {
        'bdv': 'https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos',
        // 'banesco': 'https://...',
        // 'mercantil': 'https://...',
    };

    window.prepareApiConfig = function (banco) {
        const cfg = banco.api_config || {};
        document.getElementById('api_config_id_banco').value   = banco.id_banco;
        document.getElementById('api_config_banco_nombre').textContent = banco.nombre_banco;
        document.getElementById('api_habilitada').checked      = !!cfg.habilitada;
        document.getElementById('api_tipo').value              = cfg.tipo     || '';
        document.getElementById('api_key').value               = cfg.api_key  || '';
        document.getElementById('api_cuenta').value            = cfg.cuenta   || '';
        document.getElementById('api_titular').value           = cfg.titular  || '';
        document.getElementById('api_endpoint').value          = cfg.endpoint || '';

        // Resetear resultado de prueba
        const testDiv = document.getElementById('api_test_result');
        testDiv.className = 'rounded-3 p-3 d-none';
        testDiv.innerHTML = '';

        toggleApiFields();
        new bootstrap.Modal(document.getElementById('modalApiConfig')).show();
    };

    window.toggleApiFields = function () {
        const habilitada = document.getElementById('api_habilitada').checked;
        document.getElementById('api_fields_wrapper').style.opacity  = habilitada ? '1' : '0.45';
        document.getElementById('api_fields_wrapper').style.pointerEvents = habilitada ? '' : 'none';
    };

    window.autoFillEndpoint = function () {
        const tipo = document.getElementById('api_tipo').value;
        const preset = ENDPOINT_PRESETS[tipo] || '';
        if (preset) document.getElementById('api_endpoint').value = preset;
    };

    window.toggleApiKeyVisibility = function () {
        const input = document.getElementById('api_key');
        const icon  = document.getElementById('icon_apikey');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    };

    window.testApiConnection = async function () {
        const tipo     = document.getElementById('api_tipo').value;
        const api_key  = document.getElementById('api_key').value.trim();
        const cuenta   = document.getElementById('api_cuenta').value.trim();
        const endpoint = document.getElementById('api_endpoint').value.trim();
        const testDiv  = document.getElementById('api_test_result');

        if (!tipo || !api_key || !cuenta) {
            testDiv.className = 'rounded-3 p-3';
            testDiv.style.cssText = 'background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.35);color:#d97706;';
            testDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i>Completa Tipo, API Key y Cuenta antes de probar.';
            return;
        }

        const btn = document.getElementById('btn_test_api');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Probando...';
        testDiv.className = 'rounded-3 p-3';
        testDiv.style.cssText = 'background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);color:var(--text-main);';
        testDiv.innerHTML = '<i class="fa-solid fa-satellite-dish me-2"></i>Conectando con la API...';

        try {
            const resp = await fetch('principal/test_banco_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ tipo, api_key, cuenta, endpoint })
            });
            const res = await resp.json();
            if (res.success) {
                testDiv.style.cssText = 'background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#16a34a;';
                testDiv.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i>${res.message}`;
            } else {
                testDiv.style.cssText = 'background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#dc2626;';
                testDiv.innerHTML = `<i class="fa-solid fa-circle-xmark me-2"></i>${res.message}`;
            }
        } catch (e) {
            testDiv.style.cssText = 'background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#dc2626;';
            testDiv.innerHTML = '<i class="fa-solid fa-circle-xmark me-2"></i>Error de red al contactar el servidor.';
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-satellite-dish me-2"></i>Probar Conexión';
    };

    $('#form-api-config').on('submit', async function (e) {
        e.preventDefault();
        const habilitada = document.getElementById('api_habilitada').checked;

        if (habilitada) {
            const tipo   = document.getElementById('api_tipo').value;
            const apiKey = document.getElementById('api_key').value.trim();
            const cuenta = document.getElementById('api_cuenta').value.trim();
            if (!tipo || !apiKey || !cuenta) {
                Swal.fire('Campos requeridos', 'Tipo, API Key y Cuenta son obligatorios cuando la API está habilitada.', 'warning');
                return;
            }
        }

        const proceeds = await solicitarClaveAdmin('Guardar Configuración de API');
        if (!proceeds) return;

        const formData = new FormData(this);
        formData.set('api_habilitada', habilitada ? '1' : '0');

        try {
            const resp = await fetch(API_URL + '?action=save_api_config', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Configuración guardada!',
                    text: habilitada
                        ? 'La API ha sido habilitada. Los pagos de este banco se verificarán automáticamente.'
                        : 'La API ha sido deshabilitada para este banco.',
                    timer: 3500,
                    showConfirmButton: false,
                });
                bootstrap.Modal.getInstance(document.getElementById('modalApiConfig')).hide();
                cargarBancos(currentPage);
            } else {
                Swal.fire('Error', res.message || 'Error al guardar', 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    });

    // ─────────────────────────────────────────────────────────────────────────

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