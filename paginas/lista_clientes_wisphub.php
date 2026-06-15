<?php
$page_title = "WispHub — Lista de Clientes";
require_once 'conexion.php';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/WispHubClient.php';

$wispConfig = @include __DIR__ . '/../config/wisp_hub.php';
$wispClient = null;
$wispConnected = false;
$errorMsg = '';

if (!is_array($wispConfig) || empty($wispConfig['api_key'])) {
    $errorMsg = 'API Key no configurada. Ve a <a href="admin_wisphub.php#credenciales" class="text-info">Panel WispHub → Credenciales API</a>.';
} else {
    try {
        $wispClient = new \Services\WispHubClient($wispConfig);
        $wispConnected = true;
    } catch (\Throwable $e) {
        $errorMsg = 'Error al inicializar cliente WispHub: ' . htmlspecialchars($e->getMessage());
    }
}

// AJAX handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (!$wispConnected) {
        echo json_encode(['success' => false, 'message' => 'WispHub no configurado']);
        exit;
    }

    $response = ['success' => false, 'message' => 'Acción no válida'];
    $action = $_POST['ajax_action'];

    try {
        if ($action === 'get_profile') {
            $serviceId = trim($_POST['service_id'] ?? '');
            if ($serviceId) {
                $res = $wispClient->getServiceProfile($serviceId);
                $response['data'] = $res;
                $response['success'] = $res['status'] === 200;
                $response['message'] = $res['status'] === 200 ? 'Perfil obtenido' : 'Error HTTP ' . $res['status'];
            } else {
                $response['message'] = 'Service ID requerido';
            }
        } elseif ($action === 'get_balance') {
            $serviceId = trim($_POST['service_id'] ?? '');
            if ($serviceId) {
                $res = $wispClient->getServiceBalance($serviceId);
                $response['data'] = $res;
                $response['success'] = $res['status'] === 200;
                $response['message'] = $res['status'] === 200 ? 'Saldo obtenido' : 'Error HTTP ' . $res['status'];
            } else {
                $response['message'] = 'Service ID requerido';
            }
        }
    } catch (\Throwable $e) {
        $response['message'] = 'Excepción: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$search = trim($_GET['search'] ?? '');

$clients = [];
$totalClients = 0;

if ($wispConnected) {
    $filters = ['page' => $page, 'limit' => $perPage];
    $result = $wispClient->listClients($filters);
    if ($result['status'] === 200) {
        $clients = $result['data']['results'] ?? [];
        $totalClients = $result['data']['count'] ?? 0;
    } else {
        $errorMsg = 'Error al consultar WispHub: HTTP ' . $result['status'] . ' — ' . htmlspecialchars($result['data']['message'] ?? $result['error'] ?? '');
    }
}

// Filter clients by search term client-side if API doesn't support it
if ($search !== '' && !empty($clients)) {
    $clients = array_filter($clients, function($c) use ($search) {
        $search = mb_strtolower($search);
        return mb_strpos(mb_strtolower($c['nombre'] ?? ''), $search) !== false
            || mb_strpos(mb_strtolower($c['apellidos'] ?? ''), $search) !== false
            || mb_strpos(mb_strtolower($c['cedula'] ?? ''), $search) !== false
            || mb_strpos(mb_strtolower($c['id_servicio'] ?? ''), $search) !== false;
    });
}

$totalPages = max(1, (int)ceil($totalClients / $perPage));

require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>
<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 animate-fade">
            <div>
                <h1 class="fw-bold mb-1" style="font-size:1.6rem;">
                    <span class="badge rounded-pill me-2" style="background:linear-gradient(135deg,#22c55e,#16a34a);font-size:.7rem;vertical-align:middle;">API</span>
                    Lista de Clientes WispHub
                </h1>
                <p class="text-muted small mb-0">Clientes registrados en la plataforma WispHub</p>
            </div>
            <a href="admin_wisphub.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="fa-solid fa-gears me-1"></i> Panel WispHub
            </a>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-warning rounded-3 animate-fade">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= $errorMsg ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="glass-panel p-4 text-center hover-lift animate-fade">
                    <div class="mb-2">
                        <i class="fa-solid fa-users fa-2x" style="color:#6366f1;"></i>
                    </div>
                    <div class="fs-2 fw-bold"><?= number_format($totalClients) ?></div>
                    <div class="text-muted small">Total clientes</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="glass-panel p-4 text-center hover-lift animate-fade" style="animation-delay:.08s">
                    <div class="mb-2">
                        <i class="fa-solid fa-circle-check fa-2x text-success"></i>
                    </div>
                    <div class="fs-2 fw-bold text-success" id="statActive">-</div>
                    <div class="text-muted small">Servicios activos</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="glass-panel p-4 text-center hover-lift animate-fade" style="animation-delay:.16s">
                    <div class="mb-2">
                        <i class="fa-solid fa-circle-pause fa-2x text-warning"></i>
                    </div>
                    <div class="fs-2 fw-bold text-warning" id="statSuspended">-</div>
                    <div class="text-muted small">Servicios suspendidos</div>
                </div>
            </div>
        </div>

        <!-- Search & Pagination bar -->
        <div class="glass-panel p-3 mb-4 animate-fade" style="animation-delay:.1s">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <form method="GET" class="d-flex gap-2 flex-grow-1" style="max-width:400px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text border-0" style="background:var(--bg-card);">
                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control form-control-sm border-0" placeholder="Buscar por nombre, cédula o ID..." value="<?= htmlspecialchars($search) ?>" style="background:var(--bg-card);">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Buscar</button>
                        <?php if ($search): ?>
                            <a href="lista_clientes_wisphub.php" class="btn btn-sm btn-outline-danger">✕</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="text-muted small">
                    Mostrando <?= count($clients) ?> de <?= number_format($totalClients) ?> clientes
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="glass-panel p-0 animate-fade" style="animation-delay:.15s">
            <?php if (empty($clients)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">No se encontraron clientes<?= $search ? ' para "' . htmlspecialchars($search) . '"' : '' ?>.</p>
                    <?php if ($wispConnected && !$errorMsg): ?>
                        <p class="small text-muted mt-2">La API de WispHub respondió correctamente pero no hay clientes registrados.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="tblClientes">
                        <thead>
                            <tr class="text-muted small" style="font-size:.8rem;">
                                <th>ID Servicio</th>
                                <th>Nombre</th>
                                <th>Cédula / RIF</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $c):
                            $estado = strtolower($c['estado'] ?? 'desconocido');
                            $isActive = $estado === 'activo' || $estado === 'active';
                            $nombreCompleto = trim(($c['nombre'] ?? '') . ' ' . ($c['apellidos'] ?? ''));
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-info bg-opacity-25 text-info rounded-pill font-monospace">
                                        #<?= htmlspecialchars($c['id_servicio'] ?? $c['id'] ?? '?') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($nombreCompleto ?: '—') ?></span>
                                </td>
                                <td class="font-monospace small"><?= htmlspecialchars($c['cedula'] ?? $c['documento'] ?? '—') ?></td>
                                <td class="small"><?= htmlspecialchars($c['telefono'] ?? $c['celular'] ?? '—') ?></td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge rounded-pill" style="background:#16a34a22;color:#16a34a;">
                                            <i class="fa-solid fa-circle-check me-1"></i> Activo
                                        </span>
                                    <?php elseif ($estado === 'suspendido' || $estado === 'suspended'): ?>
                                        <span class="badge rounded-pill" style="background:#eab30822;color:#eab308;">
                                            <i class="fa-solid fa-circle-pause me-1"></i> Suspendido
                                        </span>
                                    <?php elseif ($estado === 'inactivo' || $estado === 'inactive' || $estado === 'desconectado'): ?>
                                        <span class="badge rounded-pill" style="background:#ef444422;color:#ef4444;">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Inactivo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill" style="background:#6b728022;color:#6b7280;">
                                            <?= htmlspecialchars(ucfirst($estado)) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2" onclick="viewProfile('<?= htmlspecialchars($c['id_servicio'] ?? $c['id'] ?? '') ?>', this)" title="Ver perfil">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2" onclick="viewBalance('<?= htmlspecialchars($c['id_servicio'] ?? $c['id'] ?? '') ?>', this)" title="Ver saldo">
                                            <i class="fa-solid fa-wallet"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="p-3 d-flex justify-content-center" aria-label="Paginación">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link rounded-2 mx-1" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            if ($start > 1): ?>
                                <li class="page-item">
                                    <a class="page-link rounded-2 mx-1" href="?page=1&search=<?= urlencode($search) ?>">1</a>
                                </li>
                                <?php if ($start > 2): ?>
                                    <li class="page-item disabled"><span class="page-link rounded-2 mx-1">…</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link rounded-2 mx-1" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($end < $totalPages): ?>
                                <?php if ($end < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link rounded-2 mx-1">…</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link rounded-2 mx-1" href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>"><?= $totalPages ?></a>
                                </li>
                            <?php endif; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link rounded-2 mx-1" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- Modal for detail view -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content glass-panel" style="background:rgba(15,23,42,0.95);border:1px solid rgba(255,255,255,0.1);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-gradient" id="modalTitle">Detalle del Cliente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4">
                    <i class="fa-solid fa-spinner fa-spin fa-2x text-info"></i>
                    <p class="text-muted mt-2">Cargando...</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Compute stats from table
(function() {
    const rows = document.querySelectorAll('#tblClientes tbody tr');
    let active = 0, suspended = 0;
    rows.forEach(row => {
        const cell = row.querySelector('td:nth-child(5)');
        if (cell) {
            const txt = cell.textContent.toLowerCase();
            if (txt.includes('activo')) active++;
            else if (txt.includes('suspendido')) suspended++;
        }
    });
    const elActive = document.getElementById('statActive');
    const elSuspended = document.getElementById('statSuspended');
    if (elActive) elActive.textContent = active;
    if (elSuspended) elSuspended.textContent = suspended;
})();

function viewProfile(serviceId, btn) {
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    document.getElementById('modalTitle').textContent = 'Perfil — Servicio #' + serviceId;
    document.getElementById('modalBody').innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-info"></i><p class="text-muted mt-2">Cargando perfil...</p></div>';
    modal.show();

    const formData = new FormData();
    formData.append('ajax_action', 'get_profile');
    formData.append('service_id', serviceId);

    fetch('lista_clientes_wisphub.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const body = document.getElementById('modalBody');
            if (data.success && data.data && data.data.data) {
                const p = data.data.data;
                body.innerHTML = renderProfile(p);
            } else {
                body.innerHTML = `<div class="alert alert-danger rounded-3">Error: ${data.message || 'No se pudo obtener el perfil'}</div>
                    <pre class="small bg-black bg-opacity-50 p-3 rounded-3 text-info font-monospace" style="max-height:300px;overflow:auto;">${JSON.stringify(data.data, null, 2)}</pre>`;
            }
        })
        .catch(err => {
            document.getElementById('modalBody').innerHTML = `<div class="alert alert-danger rounded-3">Error de conexión: ${err.message}</div>`;
        });
}

function viewBalance(serviceId, btn) {
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    document.getElementById('modalTitle').textContent = 'Saldo — Servicio #' + serviceId;
    document.getElementById('modalBody').innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-info"></i><p class="text-muted mt-2">Cargando saldo...</p></div>';
    modal.show();

    const formData = new FormData();
    formData.append('ajax_action', 'get_balance');
    formData.append('service_id', serviceId);

    fetch('lista_clientes_wisphub.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const body = document.getElementById('modalBody');
            if (data.success && data.data && data.data.data) {
                const b = data.data.data;
                body.innerHTML = renderBalance(b);
            } else {
                body.innerHTML = `<div class="alert alert-danger rounded-3">Error: ${data.message || 'No se pudo obtener el saldo'}</div>
                    <pre class="small bg-black bg-opacity-50 p-3 rounded-3 text-info font-monospace" style="max-height:300px;overflow:auto;">${JSON.stringify(data.data, null, 2)}</pre>`;
            }
        })
        .catch(err => {
            document.getElementById('modalBody').innerHTML = `<div class="alert alert-danger rounded-3">Error de conexión: ${err.message}</div>`;
        });
}

function renderProfile(p) {
    const plan = p.plan || {};
    const router = p.router || {};
    return `
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <h6 class="small fw-semibold text-muted mb-2"><i class="fa-solid fa-user me-1"></i> Datos del Cliente</h6>
                        <div class="mb-1 small"><span class="text-muted">Nombre:</span> <span class="fw-semibold">${esc(p.nombre || '')} ${esc(p.apellidos || '')}</span></div>
                        <div class="mb-1 small"><span class="text-muted">Cédula:</span> <span class="font-monospace">${esc(p.cedula || '—')}</span></div>
                        <div class="mb-1 small"><span class="text-muted">Teléfono:</span> ${esc(p.telefono || '—')}</div>
                        <div class="mb-1 small"><span class="text-muted">Correo:</span> ${esc(p.correo || p.email || '—')}</div>
                        <div class="mb-1 small"><span class="text-muted">Dirección:</span> ${esc(p.direccion || '—')}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <h6 class="small fw-semibold text-muted mb-2"><i class="fa-solid fa-wifi me-1"></i> Datos del Servicio</h6>
                        <div class="mb-1 small"><span class="text-muted">ID Servicio:</span> <span class="font-monospace fw-bold">#${esc(p.id_servicio || p.id || '?')}</span></div>
                        <div class="mb-1 small"><span class="text-muted">Plan:</span> ${esc(plan.nombre || plan.name || '—')}</div>
                        <div class="mb-1 small"><span class="text-muted">Velocidad:</span> ${esc(plan.velocidad || plan.speed || '—')}</div>
                        <div class="mb-1 small"><span class="text-muted">Precio:</span> ${p.precio || plan.precio || plan.price || '—'} USD</div>
                        <div class="mb-1 small"><span class="text-muted">Estado:</span> ${statusBadge(p.estado || '')}</div>
                        <div class="mb-1 small"><span class="text-muted">Router:</span> ${esc(router.nombre || router.name || '—')}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <h6 class="small fw-semibold text-muted mb-2"><i class="fa-solid fa-calendar me-1"></i> Fechas</h6>
                    <div class="row small">
                        <div class="col-md-4"><span class="text-muted">Creación:</span> ${esc(p.fecha_creacion || p.created_at || '—')}</div>
                        <div class="col-md-4"><span class="text-muted">Corte:</span> ${esc(p.fecha_corte || p.cutoff_date || '—')}</div>
                        <div class="col-md-4"><span class="text-muted">Últ. pago:</span> ${esc(p.ultimo_pago || p.last_payment || '—')}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <pre class="small bg-black bg-opacity-50 p-3 rounded-3 text-info font-monospace mb-0" style="max-height:200px;overflow:auto;font-size:0.7rem;">${JSON.stringify(p, null, 2)}</pre>
        </div>
    `;
}

function renderBalance(b) {
    const facturas = b.facturas || b.invoices || [];
    const totalDeuda = b.total_deuda || b.total_debt || b.saldo || 0;
    return `
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <div class="text-muted small">Saldo pendiente</div>
                    <div class="fs-4 fw-bold ${parseFloat(totalDeuda) > 0 ? 'text-danger' : 'text-success'}">
                        ${parseFloat(totalDeuda).toFixed(2)} USD
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <div class="text-muted small">Facturas pendientes</div>
                    <div class="fs-4 fw-bold">${facturas.length}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="p-3 rounded-3 text-center" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <div class="text-muted small">Estado del servicio</div>
                    <div class="fs-4">${statusBadge(b.estado || b.status || 'desconocido')}</div>
                </div>
            </div>
        </div>
        ${facturas.length > 0 ? `
        <h6 class="fw-semibold mb-2"><i class="fa-solid fa-file-invoice me-1"></i> Facturas pendientes</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.85rem;">
                <thead>
                    <tr class="text-muted small">
                        <th># Factura</th>
                        <th>Periodo</th>
                        <th>Vencimiento</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    ${facturas.map(f => `
                        <tr>
                            <td class="font-monospace">#${f.id || f.numero || '?'}</td>
                            <td>${esc(f.periodo || f.mes || '—')}</td>
                            <td class="small">${esc(f.fecha_vencimiento || f.due_date || '—')}</td>
                            <td class="fw-semibold ${parseFloat(f.total || f.monto || 0) > 0 ? 'text-danger' : ''}">
                                ${parseFloat(f.total || f.monto || 0).toFixed(2)} USD
                            </td>
                            <td>${statusBadge(f.estado || f.status || 'pendiente')}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>` : '<p class="text-muted text-center py-3"><i class="fa-solid fa-check-circle text-success me-1"></i> No hay facturas pendientes.</p>'}
        <div class="mt-3">
            <pre class="small bg-black bg-opacity-50 p-3 rounded-3 text-info font-monospace mb-0" style="max-height:200px;overflow:auto;font-size:0.7rem;">${JSON.stringify(b, null, 2)}</pre>
        </div>
    `;
}

function statusBadge(estado) {
    const e = String(estado).toLowerCase();
    if (e === 'activo' || e === 'active' || e === 'activo') {
        return '<span class="badge rounded-pill" style="background:#16a34a22;color:#16a34a;"><i class="fa-solid fa-circle-check me-1"></i> Activo</span>';
    } else if (e === 'suspendido' || e === 'suspended') {
        return '<span class="badge rounded-pill" style="background:#eab30822;color:#eab308;"><i class="fa-solid fa-circle-pause me-1"></i> Suspendido</span>';
    } else if (e === 'inactivo' || e === 'inactive') {
        return '<span class="badge rounded-pill" style="background:#ef444422;color:#ef4444;"><i class="fa-solid fa-circle-xmark me-1"></i> Inactivo</span>';
    } else if (e === 'pendiente' || e === 'pending') {
        return '<span class="badge rounded-pill" style="background:#f59e0b22;color:#f59e0b;">Pendiente</span>';
    } else if (e === 'pagada' || e === 'paid') {
        return '<span class="badge rounded-pill" style="background:#16a34a22;color:#16a34a;">Pagada</span>';
    } else if (e === 'vencida' || e === 'overdue') {
        return '<span class="badge rounded-pill" style="background:#ef444422;color:#ef4444;">Vencida</span>';
    }
    return `<span class="badge rounded-pill" style="background:#6b728022;color:#6b7280;">${esc(estado)}</span>`;
}

function esc(s) {
    if (s === null || s === undefined) return '—';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>

<?php require_once 'includes/layout_foot.php'; ?>
