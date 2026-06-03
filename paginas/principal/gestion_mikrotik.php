<?php
$page_title = "Integración MikroTik";
$breadcrumb = ["Red Técnica", "MikroTik"];
$path_to_root = '../../';
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';
require_once '../includes/auth.php';
require_once '../conexion.php';
require_once '../includes/MikrotikController.php';

// ─── Acciones POST ────────────────────────────────────────────────────────
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // Guardar / Editar Router
    if ($accion === 'guardar') {
        $id       = intval($_POST['id'] ?? 0);
        $nombre   = trim($_POST['nombre'] ?? '');
        $ip       = trim($_POST['ip'] ?? '');
        $puerto   = intval($_POST['puerto'] ?? 8728);
        $usuario  = trim($_POST['usuario'] ?? '');
        $pass     = trim($_POST['contrasena'] ?? '');
        $desc     = trim($_POST['descripcion'] ?? '');
        $activo   = isset($_POST['activo']) ? 1 : 0;
        $dry_run  = isset($_POST['dry_run']) ? 1 : 0;

        if (empty($nombre) || empty($ip) || empty($usuario)) {
            $mensaje = 'Nombre, IP y Usuario son obligatorios.';
            $tipo_mensaje = 'danger';
        } else {
            if ($id > 0) {
                // Editar
                if (!empty($pass)) {
                    $stmt = $conn->prepare("UPDATE mikrotik_routers SET nombre=?, ip=?, puerto=?, usuario=?, contrasena=?, descripcion=?, activo=?, dry_run=? WHERE id=?");
                    $stmt->bind_param("ssisssiii", $nombre, $ip, $puerto, $usuario, $pass, $desc, $activo, $dry_run, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE mikrotik_routers SET nombre=?, ip=?, puerto=?, usuario=?, descripcion=?, activo=?, dry_run=? WHERE id=?");
                    $stmt->bind_param("ssisssii", $nombre, $ip, $puerto, $usuario, $desc, $activo, $dry_run, $id);
                }
                $stmt->execute();
                $mensaje = 'Router actualizado correctamente.';
            } else {
                // Nuevo
                $stmt = $conn->prepare("INSERT INTO mikrotik_routers (nombre, ip, puerto, usuario, contrasena, descripcion, activo, dry_run) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssisssii", $nombre, $ip, $puerto, $usuario, $pass, $desc, $activo, $dry_run);
                $stmt->execute();
                $mensaje = 'Router registrado correctamente.';
            }
            $tipo_mensaje = 'success';
        }
    }

    // Eliminar
    if ($accion === 'eliminar') {
        $id = intval($_POST['id'] ?? 0);
        $conn->query("DELETE FROM mikrotik_routers WHERE id = $id");
        $mensaje = 'Router eliminado.';
        $tipo_mensaje = 'warning';
    }

    // Test de conexión (AJAX)
    if ($accion === 'test_conexion') {
        $id = intval($_POST['id'] ?? 0);
        $resultado = MikrotikController::testConexion($id);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
}

// Obtener routers
$routers = $conn->query("SELECT * FROM mikrotik_routers ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Obtener stats de logs
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(estado = 'EXITO') as exitosos,
        SUM(estado = 'ERROR') as errores,
        SUM(estado = 'DRY_RUN') as dry_runs
    FROM mikrotik_logs
")->fetch_assoc();
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2 text-primary">
                    <span class="rounded-3 p-2 d-inline-flex" style="background:linear-gradient(135deg,#d4380d22,#fa541c22)">
                        <i class="fa-solid fa-router" style="color:#fa541c;font-size:1.3rem;"></i>
                    </span>
                    Integración MikroTik API
                </h1>
                <p class="text-muted mb-0 small">Gestión de enrutadores y automatización de cortes/reconexiones</p>
            </div>
            <div class="d-flex gap-2">
                <a href="mikrotik_logs.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                    <i class="fa-solid fa-terminal"></i> <span>Ver Logs</span>
                </a>
                <button class="btn btn-premium btn-sm d-flex align-items-center gap-2"
                    data-bs-toggle="modal" data-bs-target="#modalRouter" onclick="limpiarForm()">
                    <i class="fa-solid fa-plus"></i> <span>Agregar Router</span>
                </button>
            </div>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'danger' ? 'times-circle' : 'exclamation-triangle') ?> me-2"></i>
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card glass-panel border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold display-6 text-primary"><?= count($routers) ?></div>
                    <div class="small text-muted mt-1"><i class="fa-solid fa-router me-1"></i> Routers</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card glass-panel border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold display-6 text-success"><?= $stats['exitosos'] ?? 0 ?></div>
                    <div class="small text-muted mt-1"><i class="fa-solid fa-check-circle me-1"></i> Exitosos</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card glass-panel border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold display-6 text-danger"><?= $stats['errores'] ?? 0 ?></div>
                    <div class="small text-muted mt-1"><i class="fa-solid fa-times-circle me-1"></i> Errores</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card glass-panel border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold display-6 text-warning"><?= $stats['dry_runs'] ?? 0 ?></div>
                    <div class="small text-muted mt-1"><i class="fa-solid fa-flask me-1"></i> Dry Runs</div>
                </div>
            </div>
        </div>

        <!-- Modo Dry Run Banner -->
        <?php $hayDryRun = array_filter($routers, fn($r) => $r['dry_run'] == 1); ?>
        <?php if (!empty($hayDryRun) || empty($routers)): ?>
        <div class="alert border border-warning border-opacity-25 bg-warning bg-opacity-10 shadow-sm mb-4 d-flex align-items-start gap-3"
             style="border-left:4px solid #fa8c16 !important;">
            <i class="fa-solid fa-flask fa-lg mt-1 text-warning"></i>
            <div>
                <strong class="text-warning">Modo Dry Run Activo</strong>
                <p class="mb-0 text-main small mt-1">
                    Los routers en modo <strong>Dry Run</strong> no enviarán comandos reales al MikroTik.
                    Solo registrarán en el log lo que <em>habrían</em> ejecutado. 
                    Ideal para verificar que el sistema está generando los comandos correctos antes de ir a producción.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabla de Routers -->
        <div class="card glass-panel border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-transparent border-bottom border-white border-opacity-10 py-3">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-list me-2 opacity-50"></i> Routers Configurados</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($routers)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-router fa-3x mb-3 opacity-25"></i>
                    <p class="mt-2">No hay routers configurados aún.</p>
                    <button class="btn btn-premium btn-sm d-flex align-items-center gap-2 mx-auto mt-2"
                        data-bs-toggle="modal" data-bs-target="#modalRouter" onclick="limpiarForm()">
                        <i class="fa-solid fa-plus"></i> <span>Agregar primer router</span>
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-white bg-opacity-10">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Nombre / Descripción</th>
                                <th>Conexión</th>
                                <th>Modo</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routers as $r): ?>
                            <tr>
                                <td class="ps-4"><span class="text-muted small">#<?= $r['id'] ?></span></td>
                                <td>
                                    <div class="fw-bold text-main"><?= htmlspecialchars($r['nombre']) ?></div>
                                    <?php if ($r['descripcion']): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($r['descripcion']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="small bg-white bg-opacity-5 px-2 py-1 rounded border border-white border-opacity-10 text-main">
                                        <?= htmlspecialchars($r['ip']) ?>:<?= $r['puerto'] ?>
                                    </code>
                                    <div class="text-muted small mt-1">
                                        <i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($r['usuario']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['dry_run'] == 1): ?>
                                    <span class="badge rounded-pill px-3 py-1" style="background:#fff3cd;color:#d46b08;border:1px solid #fa8c1655;">
                                        <i class="fa-solid fa-flask me-1"></i> Dry Run
                                    </span>
                                    <?php else: ?>
                                    <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger px-3 py-1 border border-danger border-opacity-25">
                                        <i class="fa-solid fa-bolt me-1"></i> PRODUCCIÓN
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['activo']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="fa-solid fa-circle me-1"></i> Activo</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25"><i class="fa-solid fa-circle me-1"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <!-- Test -->
                                        <button class="btn btn-sm btn-glass text-info rounded-2" title="Probar conexión"
                                            onclick="testConexion(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>')">
                                            <i class="fa-solid fa-plug-circle-check"></i>
                                        </button>
                                        <!-- Editar -->
                                        <button class="btn btn-sm btn-glass text-primary rounded-2" title="Editar"
                                            onclick="editarRouter(<?= htmlspecialchars(json_encode($r)) ?>)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Eliminar -->
                                        <button class="btn btn-sm btn-glass text-danger rounded-2" title="Eliminar"
                                            onclick="eliminarRouter(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>')">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección Info Técnica -->
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <div class="card glass-panel border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2 text-info"></i> ¿Cómo configurar el MikroTik?</h6>
                        <ol class="small text-muted ps-3 mb-0" style="line-height:1.9">
                            <li>En Winbox ir a <strong>IP → Services</strong></li>
                            <li>Habilitar el servicio <strong>"api"</strong> (puerto 8728)</li>
                            <li>Ir a <strong>System → Users → Groups</strong></li>
                            <li>Crear grupo con políticas: <code>api, read, write</code></li>
                            <li>Crear un usuario dedicado y asignar el grupo</li>
                            <li>Registrar las credenciales aquí</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card glass-panel border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved me-2 text-warning"></i> Seguridad recomendada</h6>
                        <ul class="small text-muted ps-3 mb-0" style="line-height:1.9">
                            <li>Crear usuario API dedicado (no usar <code>admin</code>)</li>
                            <li>Permitir conexión API <strong>solo</strong> desde IP de este servidor</li>
                            <li>Usar puerto SSL 8729 cuando sea posible</li>
                            <li>Nunca usar permisos de <code>full</code> o <code>reboot</code></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card glass-panel border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-flask me-2 text-warning"></i> Dry Run vs Producción</h6>
                        <ul class="small text-muted ps-3 mb-0" style="line-height:1.9">
                            <li><strong>Dry Run:</strong> Registra el comando en logs pero <em>no lo envía</em> al router</li>
                            <li><strong>Producción:</strong> Envía el comando real al MikroTik</li>
                            <li>Verifica los logs primero antes de activar producción</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /page-content -->
</main>

<!-- Modal Agregar/Editar Router -->
<div class="modal fade" id="modalRouter" tabindex="-1" aria-labelledby="modalRouterLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-gradient text-white border-0">
                <h5 class="modal-title fw-bold" id="modalRouterLabel">
                    <i class="fa-solid fa-router me-2 opacity-75"></i> <span id="modalRouterTitulo">Nuevo Router MikroTik</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formRouter">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="routerId" value="0">
                <div class="modal-body p-4">
                    <div class="row g-4">

                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-bold text-uppercase">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="rNombre" class="form-control"
                                placeholder="Ej: Router Central, Nodo Norte..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-bold text-uppercase">IP / Hostname <span class="text-danger">*</span></label>
                            <input type="text" name="ip" id="rIp" class="form-control"
                                placeholder="192.168.1.1" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-bold text-uppercase">Puerto API</label>
                            <input type="number" name="puerto" id="rPuerto" class="form-control" value="8728"
                                min="1" max="65535">
                            <div class="form-text small opacity-75">8728 normal · 8729 SSL</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-bold text-uppercase">Usuario API <span class="text-danger">*</span></label>
                            <input type="text" name="usuario" id="rUsuario" class="form-control"
                                placeholder="api_sistema" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-bold text-uppercase" id="labelPass">Contraseña API <span class="text-danger">*</span></label>
                            <div class="input-group glass-input-group">
                                <input type="password" name="contrasena" id="rContrasena" class="form-control border-end-0"
                                    placeholder="••••••••">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePass">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text small opacity-75" id="passHelp">Dejar vacío para no cambiar</div>
                        </div>

                        <div class="col-12 mt-4">
                            <label class="form-label text-secondary small fw-bold text-uppercase">Descripción / Notas</label>
                            <textarea name="descripcion" id="rDesc" class="form-control" rows="2"
                                placeholder="Nodo que controla, sector, observaciones..."></textarea>
                        </div>

                        <div class="col-md-6">
                            <div class="card border border-warning border-opacity-25 bg-warning bg-opacity-10 p-3 h-100">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="dry_run" id="rDryRun" checked>
                                    <label class="form-check-label fw-bold text-main" for="rDryRun">
                                        <i class="fa-solid fa-flask me-1 text-warning"></i> Modo Dry Run
                                    </label>
                                </div>
                                <small class="text-muted mt-1 d-block">Activar para pruebas: no enviará comandos reales.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 p-3 h-100">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="activo" id="rActivo" checked>
                                    <label class="form-check-label fw-bold text-main" for="rActivo">
                                        <i class="fa-solid fa-circle me-1 text-success"></i> Router Activo
                                    </label>
                                </div>
                                <small class="text-muted mt-1 d-block">El sistema usará este router para las operaciones.</small>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-transparent border-top border-white border-opacity-10 py-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-premium px-4">
                        <i class="fa-solid fa-save me-2"></i> Guardar Router
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-danger">
                    <i class="fa-solid fa-circle-exclamation fa-3x"></i>
                </div>
                <h5 class="mb-2 fw-bold">¿Eliminar registro?</h5>
                <p class="mb-1 text-main">¿Eliminar el router <strong id="nombreEliminar"></strong>?</p>
                <p class="text-muted small mb-4">Esta acción no se puede deshacer.</p>
                <form method="POST" id="formEliminar" class="w-100">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="idEliminar">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-ok fw-medium">Eliminar</button>
                        <button type="button" class="btn btn-outline-secondary fw-medium"
                            data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast notificaciones -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toastMikrotik" class="toast align-items-center border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="toastBody">Mensaje</div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
function limpiarForm() {
    document.getElementById('routerId').value = '0';
    document.getElementById('rNombre').value = '';
    document.getElementById('rIp').value = '';
    document.getElementById('rPuerto').value = '8728';
    document.getElementById('rUsuario').value = '';
    document.getElementById('rContrasena').value = '';
    document.getElementById('rDesc').value = '';
    document.getElementById('rDryRun').checked = true;
    document.getElementById('rActivo').checked = true;
    document.getElementById('modalRouterTitulo').textContent = 'Nuevo Router MikroTik';
    document.getElementById('labelPass').innerHTML = 'Contraseña API <span class="text-danger">*</span>';
    document.getElementById('passHelp').style.display = 'none';
}

function editarRouter(r) {
    document.getElementById('routerId').value = r.id;
    document.getElementById('rNombre').value = r.nombre;
    document.getElementById('rIp').value = r.ip;
    document.getElementById('rPuerto').value = r.puerto;
    document.getElementById('rUsuario').value = r.usuario;
    document.getElementById('rContrasena').value = '';
    document.getElementById('rDesc').value = r.descripcion || '';
    document.getElementById('rDryRun').checked = r.dry_run == 1;
    document.getElementById('rActivo').checked = r.activo == 1;
    document.getElementById('modalRouterTitulo').textContent = 'Editar Router: ' + r.nombre;
    document.getElementById('labelPass').innerHTML = 'Contraseña API';
    document.getElementById('passHelp').style.display = 'block';
    new bootstrap.Modal(document.getElementById('modalRouter')).show();
}

function eliminarRouter(id, nombre) {
    document.getElementById('idEliminar').value = id;
    document.getElementById('nombreEliminar').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}

function testConexion(id, nombre) {
    mostrarToast('info', `<i class="fa-solid fa-spinner fa-spin me-2"></i>Probando conexión con <b>${nombre}</b>...`);

    fetch('gestion_mikrotik.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `accion=test_conexion&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = data.dry_run ? '🧪' : '✅';
            mostrarToast('success', `${icon} <b>${nombre}:</b> ${data.mensaje}`);
        } else {
            mostrarToast('danger', `❌ <b>${nombre}:</b> ${data.mensaje}`);
        }
    })
    .catch(e => mostrarToast('danger', `Error de red: ${e.message}`));
}

function mostrarToast(tipo, html) {
    const toast = document.getElementById('toastMikrotik');
    const body = document.getElementById('toastBody');
    body.innerHTML = html;
    toast.className = `toast align-items-center border-0 shadow-lg text-white bg-${tipo === 'info' ? 'secondary' : tipo}`;
    new bootstrap.Toast(toast, {delay: 5000}).show();
}

// Toggle password visible
document.getElementById('togglePass').addEventListener('click', function() {
    const input = document.getElementById('rContrasena');
    const icon = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
});
</script>

<?php require_once '../includes/layout_foot.php'; ?>
