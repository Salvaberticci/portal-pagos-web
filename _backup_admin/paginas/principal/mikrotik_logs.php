<?php
$page_title = "Consola MikroTik API";
$breadcrumb = ["Red Técnica", "MikroTik", "Logs"];
$path_to_root = '../../';
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';
require_once '../includes/auth.php';
require_once '../conexion.php';

// ─── Filtros ──────────────────────────────────────────────────────────────
$f_accion = $_GET['accion'] ?? '';
$f_estado = $_GET['estado'] ?? '';
$f_fecha  = $_GET['fecha']  ?? date('Y-m-d');
$pagina   = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 50;

// Construir WHERE
$where_parts = [];
$where_parts[] = "DATE(ml.created_at) = '$f_fecha'";
if ($f_accion) $where_parts[] = "ml.accion = '$f_accion'";
if ($f_estado) $where_parts[] = "ml.estado = '$f_estado'";
$where = implode(' AND ', $where_parts);

// Total
$total_rows = $conn->query("SELECT COUNT(*) as c FROM mikrotik_logs ml WHERE $where")->fetch_assoc()['c'];
$total_paginas = ceil($total_rows / $por_pagina);
$offset = ($pagina - 1) * $por_pagina;

// Logs
$logs = $conn->query("
    SELECT ml.*, mr.nombre AS nombre_router, mr.ip AS ip_router
    FROM mikrotik_logs ml
    LEFT JOIN mikrotik_routers mr ON ml.id_router = mr.id
    WHERE $where
    ORDER BY ml.id DESC
    LIMIT $por_pagina OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

// Stats del día
$stats_hoy = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(estado = 'EXITO') as exitosos,
        SUM(estado = 'ERROR') as errores,
        SUM(estado = 'DRY_RUN') as dry_runs,
        SUM(accion = 'CORTE') as cortes,
        SUM(accion = 'RECONEXION') as reconexiones
    FROM mikrotik_logs
    WHERE DATE(created_at) = '$f_fecha'
")->fetch_assoc();
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>

    <div class="page-content">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
                    <span class="rounded-3 p-2 d-inline-flex" style="background:linear-gradient(135deg,#00274422,#00474f33)">
                        <i class="fa-solid fa-terminal" style="color:#00b96b;font-size:1.3rem;"></i>
                    </span>
                    Consola de Logs MikroTik
                </h1>
                <p class="text-muted mb-0 small">Registro de todos los comandos enviados (o simulados) al MikroTik API</p>
            </div>
            <a href="gestion_mikrotik.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-router me-1"></i> Gestión de Routers
            </a>
        </div>

        <!-- Stats Hoy -->
        <div class="row g-3 mb-4">
            <div class="col-4 col-md-2">
                <div class="card border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold fs-4 text-secondary"><?= $stats_hoy['total'] ?? 0 ?></div>
                    <div class="tiny text-muted mt-1">Total</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="card border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold fs-4 text-success"><?= $stats_hoy['exitosos'] ?? 0 ?></div>
                    <div class="tiny text-muted mt-1">Éxito</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="card border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold fs-4 text-danger"><?= $stats_hoy['errores'] ?? 0 ?></div>
                    <div class="tiny text-muted mt-1">Errores</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="card border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold fs-4 text-warning"><?= $stats_hoy['dry_runs'] ?? 0 ?></div>
                    <div class="tiny text-muted mt-1">Dry Run</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="card border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold fs-4 text-danger"><?= $stats_hoy['cortes'] ?? 0 ?></div>
                    <div class="tiny text-muted mt-1">Cortes</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="card border-0 shadow-sm text-center p-3 h-100">
                    <div class="fw-bold fs-4 text-success"><?= $stats_hoy['reconexiones'] ?? 0 ?></div>
                    <div class="tiny text-muted mt-1">Reconex.</div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold mb-1">Fecha</label>
                        <input type="date" name="fecha" class="form-control form-control-sm" value="<?= $f_fecha ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Acción</label>
                        <select name="accion" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option value="CORTE"      <?= $f_accion === 'CORTE'      ? 'selected' : '' ?>>Corte</option>
                            <option value="RECONEXION" <?= $f_accion === 'RECONEXION' ? 'selected' : '' ?>>Reconexión</option>
                            <option value="TEST"       <?= $f_accion === 'TEST'       ? 'selected' : '' ?>>Test</option>
                            <option value="CONSULTA"   <?= $f_accion === 'CONSULTA'   ? 'selected' : '' ?>>Consulta</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold mb-1">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="EXITO"   <?= $f_estado === 'EXITO'   ? 'selected' : '' ?>>Éxito</option>
                            <option value="ERROR"   <?= $f_estado === 'ERROR'   ? 'selected' : '' ?>>Error</option>
                            <option value="DRY_RUN" <?= $f_estado === 'DRY_RUN' ? 'selected' : '' ?>>Dry Run</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-6 col-md-2">
                        <a href="mikrotik_logs.php" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="fa-solid fa-xmark me-1"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Logs (estilo terminal) -->
        <div class="card border-0 shadow-sm">
            <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between"
                 style="background:#1e1e2e;border-radius:8px 8px 0 0;">
                <div class="d-flex align-items-center gap-2">
                    <span style="width:12px;height:12px;border-radius:50%;background:#ff5f57;display:inline-block;"></span>
                    <span style="width:12px;height:12px;border-radius:50%;background:#febc2e;display:inline-block;"></span>
                    <span style="width:12px;height:12px;border-radius:50%;background:#28c840;display:inline-block;"></span>
                    <code class="small ms-2" style="color:#cdd6f4;">mikrotik_api_logs — <?= $f_fecha ?></code>
                </div>
                <span class="badge bg-secondary"><?= $total_rows ?> registros</span>
            </div>
            <div class="card-body p-0" style="background:#1e1e2e;border-radius:0 0 8px 8px;">
                <?php if (empty($logs)): ?>
                <div class="text-center py-5" style="color:#6c7086;">
                    <i class="fa-solid fa-terminal fa-3x mb-3 opacity-25"></i>
                    <p>No hay registros para los filtros seleccionados.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="color:#cdd6f4;background:transparent;">
                        <thead>
                            <tr style="border-bottom:1px solid #313244;">
                                <th style="color:#585b70;font-size:.7rem;padding:10px 16px;" class="text-nowrap">HORA</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">ACCIÓN</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">ESTADO</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">CLIENTE</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">IP</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">ROUTER</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">COMANDO</th>
                                <th style="color:#585b70;font-size:.7rem;padding:10px 8px;">POR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr style="border-bottom:1px solid #31324488;" class="log-row">
                                <td style="padding:8px 16px;" class="text-nowrap">
                                    <span style="color:#a6e3a1;font-size:.75rem;font-family:monospace;">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </span>
                                </td>
                                <td style="padding:8px;">
                                    <?php
                                    $iconAccion = ['CORTE' => ['fa-scissors','#f38ba8'], 'RECONEXION' => ['fa-plug','#a6e3a1'], 'TEST' => ['fa-plug-circle-check','#89b4fa'], 'CONSULTA' => ['fa-magnifying-glass','#cba6f7']];
                                    $ia = $iconAccion[$log['accion']] ?? ['fa-circle','#cdd6f4'];
                                    ?>
                                    <span style="color:<?= $ia[1] ?>;font-size:.8rem;" class="text-nowrap">
                                        <i class="fa-solid <?= $ia[0] ?> me-1"></i> <?= $log['accion'] ?>
                                    </span>
                                </td>
                                <td style="padding:8px;">
                                    <?php if ($log['estado'] === 'EXITO'): ?>
                                    <span style="color:#a6e3a1;font-size:.75rem;"><i class="fa-solid fa-check me-1"></i>ÉXITO</span>
                                    <?php elseif ($log['estado'] === 'ERROR'): ?>
                                    <span style="color:#f38ba8;font-size:.75rem;" title="<?= htmlspecialchars($log['mensaje_error']) ?>">
                                        <i class="fa-solid fa-xmark me-1"></i>ERROR <i class="fa-solid fa-circle-info ms-1" style="cursor:pointer;"></i>
                                    </span>
                                    <?php else: ?>
                                    <span style="color:#f9e2af;font-size:.75rem;"><i class="fa-solid fa-flask me-1"></i>DRY RUN</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <span style="font-size:.8rem;color:#cdd6f4;"><?= htmlspecialchars($log['nombre_cliente'] ?? '—') ?></span>
                                    <?php if ($log['id_contrato']): ?>
                                    <div style="color:#6c7086;font-size:.7rem;">#<?= $log['id_contrato'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <?php if ($log['ip_cliente']): ?>
                                    <code style="color:#89dceb;font-size:.75rem;background:#181825;padding:2px 6px;border-radius:4px;">
                                        <?= htmlspecialchars($log['ip_cliente']) ?>
                                    </code>
                                    <?php else: ?>
                                    <span style="color:#585b70;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <span style="font-size:.75rem;color:#b4befe;">
                                        <?= htmlspecialchars($log['nombre_router'] ?? '—') ?>
                                    </span>
                                </td>
                                <td style="padding:8px;max-width:280px;">
                                    <?php if ($log['comando']): ?>
                                    <code style="color:#cba6f7;font-size:.7rem;word-break:break-all;display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:280px;"
                                          title="<?= htmlspecialchars($log['comando']) ?>">
                                        <?= htmlspecialchars($log['comando']) ?>
                                    </code>
                                    <?php else: ?>
                                    <span style="color:#585b70;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <span style="font-size:.75rem;color:#a6adc8;"><?= htmlspecialchars($log['ejecutado_por'] ?? 'SISTEMA') ?></span>
                                    <?php if ($log['origen']): ?>
                                    <div style="color:#585b70;font-size:.65rem;"><?= htmlspecialchars($log['origen']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($log['estado'] === 'ERROR' && $log['mensaje_error']): ?>
                            <tr style="background:#1a0000;border-bottom:1px solid #31324488;">
                                <td colspan="8" style="padding:6px 16px;">
                                    <code style="color:#f38ba8;font-size:.7rem;">
                                        ↳ Error: <?= htmlspecialchars($log['mensaje_error']) ?>
                                    </code>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="d-flex justify-content-center py-3" style="border-top:1px solid #313244;">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                <a class="page-link" style="background:<?= $i === $pagina ? '#89b4fa' : '#313244' ?>;border-color:#45475a;color:<?= $i === $pagina ? '#1e1e2e' : '#cdd6f4' ?>;"
                                   href="?fecha=<?= $f_fecha ?>&accion=<?= $f_accion ?>&estado=<?= $f_estado ?>&pagina=<?= $i ?>">
                                   <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

    </div><!-- /page-content -->
</main>

<style>
.log-row:hover td { background: #31324488 !important; }
.tiny { font-size: .72rem; }
</style>

<?php require_once '../includes/layout_foot.php'; ?>
