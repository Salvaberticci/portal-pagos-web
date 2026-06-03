<?php
// Header include with Top Navigation Bar
// Assumes $page_title is set before including
$page_title = isset($page_title) ? $page_title : 'Panel de Control';

// Asegurar que $path_fix esté definido (normalmente viene de layout_head.php, pero por seguridad)
if (!isset($path_fix)) {
    $path_fix = isset($path_to_root) ? $path_to_root : '../';
}

// --- SEGURIDAD: CONTROL DE ACCESO ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión iniciada, redirigir al login
    header("Location: " . $path_fix . "index.html");
    exit;
}

// Obtener datos del usuario desde la sesión
$user_name = isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : 'Usuario';
$user_role = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'Sin Rol';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>

<nav class="navbar navbar-expand-lg glass-header py-1 shadow-sm">
    <div class="container-fluid px-4">
        <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-main" href="<?php echo $path_fix; ?>paginas/menu.php">
            <img src="<?php echo $path_fix; ?>images/logo.jpg" alt="Logo" style="height: 35px; border-radius: 50%;">
            <span class="d-none d-sm-inline font-heading tracking-wide" style="color: var(--text-main);">Wireless Supply</span>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
            aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">

                <!-- Administración Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropAdmin" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fa-solid fa-briefcase me-1 text-primary-light"></i> Admin
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropAdmin">
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/gestion_contratos.php"><i
                                    class="fa-solid fa-file-contract w-20 me-2 text-muted"></i> Contratos</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/gestion_planes.php"><i
                                    class="fa-solid fa-wifi w-20 me-2 text-muted"></i> Planes</a></li>
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/gestion_bancos.php"><i
                                    class="fa-solid fa-building-columns w-20 me-2 text-muted"></i> Bancos</a></li>

                    </ul>
                </li>

                <!-- Red Técnica Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropTech" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fa-solid fa-network-wired me-1 text-info"></i> Técnica
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropTech">
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/gestion_olt.php"><i
                                    class="fa-solid fa-server w-20 me-2 text-muted"></i> OLTs</a></li>
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/gestion_pon.php"><i
                                    class="fa-solid fa-network-wired w-20 me-2 text-muted"></i> PONs</a></li>
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/gestion_municipios.php"><i
                                    class="fa-solid fa-map-location-dot w-20 me-2 text-muted"></i> Ubicaciones</a></li>
                    </ul>
                </li>

                <!-- MikroTik Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropMikrotik" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fa-solid fa-tower-broadcast me-1" style="color:#fa541c;"></i> MikroTik
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropMikrotik">
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/gestion_mikrotik.php"><i
                                    class="fa-solid fa-tower-broadcast w-20 me-2" style="color:#fa541c;"></i> Gestión de Routers</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/mikrotik_logs.php"><i
                                    class="fa-solid fa-terminal w-20 me-2" style="color:#00b96b;"></i> Consola de Logs</a></li>
                    </ul>
                </li>

                <!-- Cobranzas Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropCobranzas" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-money-bill-wave me-1 text-success"></i> Cobranzas
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropCobranzas">
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/gestion_mensualidades.php"><i
                                    class="fa-solid fa-file-invoice-dollar w-20 me-2 text-muted"></i> Gestión de
                                Mensualidades y Pagos</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/gestion_deudores.php"><i
                                    class="fa-solid fa-triangle-exclamation w-20 me-2 text-danger"></i> Clientes
                                Deudores</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/aprobar_pagos.php"><i
                                    class="fa-solid fa-check-double w-20 me-2 text-warning"></i> Aprobar Pagos
                                Web</a></li>

                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/historial_pagos_reportados.php"><i
                                    class="fa-solid fa-clock-rotate-left w-20 me-2 text-primary"></i> Historial
                                Reportes Web</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/gestion_prorrogas.php"><i
                                    class="fa-solid fa-calendar-plus w-20 me-2 text-primary"></i> Gestión de
                                Prórrogas</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/principal/conciliacion.php"><i
                                    class="fa-solid fa-scale-balanced w-20 me-2 text-primary"></i> Conciliación
                                (OCR)</a></li>
                    </ul>
                </li>

                <!-- Enlaces Externos Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropEnlaces" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-link me-1 text-info"></i> Enlaces
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropEnlaces">
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>portal/index.php" target="_blank"><i
                                    class="fa-solid fa-user-tag w-20 me-2 text-success"></i> Reporte de Pago (Portal)</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/soporte/registro_contrato_instalador.php"
                                target="_blank"><i class="fa-solid fa-file-signature w-20 me-2 text-primary"></i>
                                Registro
                                Instalador</a></li>
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/soporte/reporte_tecnico.php"
                                target="_blank"><i class="fa-solid fa-pen-to-square w-20 me-2 text-danger"></i> Nuevo
                                Reporte Técnico</a></li>
                    </ul>
                </li>

                <!-- Soporte Técnico Link -->
                <!-- Soporte Técnico Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropSupport" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-screwdriver-wrench me-1 text-danger"></i> Soporte
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropSupport">
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/soporte/gestion_fallas.php"><i
                                    class="fa-solid fa-chart-line w-20 me-2 text-muted"></i> Gestión de Fallas</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/soporte/registro_falla.php"><i
                                    class="fa-solid fa-triangle-exclamation w-20 me-2 text-danger"></i> Registrar Falla Masiva</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/soporte/historial_soportes.php"><i
                                    class="fa-solid fa-list-check w-20 me-2 text-muted"></i> Historial</a></li>
                    </ul>
                </li>

                <!-- Reportes Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropReports" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-chart-pie me-1 text-warning"></i> Reportes
                    </a>
                    <ul class="dropdown-menu shadow-lg border-0" aria-labelledby="dropReports">
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/reportes_pdf/reporte_cobranza.php"><i
                                    class="fa-solid fa-chart-line w-20 me-2 text-muted"></i> Reporte Cobranzas</a></li>
                        <li><a class="dropdown-item"
                                href="<?php echo $path_fix; ?>paginas/reportes_pdf/reporte_clientes.php"><i
                                    class="fa-solid fa-users-viewfinder w-20 me-2 text-muted"></i> Reporte Clientes</a>
                        </li>
                    </ul>
                </li>
            </ul>

            <!-- Right Side: User Profile & Actions -->
            <div class="d-flex align-items-center gap-3">
                <!-- Theme Toggle -->
                <button class="btn btn-sm btn-glass rounded-circle d-flex align-items-center justify-content-center border-0 shadow-sm" id="themeToggleBtn" style="width: 38px; height: 38px;" title="Cambiar Tema">
                    <i class="fa-solid fa-moon text-warning"></i>
                </button>
                
                <!-- User Dropdown -->
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle"
                        id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="text-end me-2 d-none d-md-block">
                            <span class="d-block small text-uppercase fw-bold"
                                style="font-size: 0.65rem; color: rgba(255, 255, 255, 0.7);">Bienvenido</span>
                            <span class="d-block fw-semibold text-white"
                                style="font-size: 0.9rem;"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="badge shadow-sm"
                                style="font-size: 0.65rem; background: rgba(255, 255, 255, 0.15); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.1);"><?php echo htmlspecialchars($user_role); ?></span>
                        </div>
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                            style="width: 38px; height: 38px; background: linear-gradient(135deg, var(--primary), var(--accent)) !important; border: 1px solid var(--border-glass);"><?php echo $user_initial; ?></div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 text-small"
                        aria-labelledby="dropdownUser1">
                        <li><a class="dropdown-item" href="<?php echo $path_fix; ?>paginas/gestion_usuarios.php"><i
                                    class="fa-solid fa-user-shield me-2"></i> Gestión Usuarios</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="<?php echo $path_fix; ?>index.html"><i
                                    class="fa-solid fa-right-from-bracket me-2"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Sub-header for Page Title (Optional, keeps the context) -->
<div class="border-bottom py-2 shadow-sm d-none d-lg-block" style="background: var(--bg-card); border-color: var(--border-glass) !important;">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="m-0 text-secondary fw-bold d-flex align-items-center gap-2 flex-wrap">
                <?php if (isset($back_url)): ?>
                    <a href="<?php echo $back_url; ?>"
                        class="btn btn-sm btn-light border shadow-sm py-1 px-2 text-dark me-2" title="Volver">
                        <i class="fa-solid fa-arrow-left me-1"></i> Volver
                    </a>
                <?php endif; ?>

                <div class="d-flex align-items-center gap-2 breadcrumb-path">
                    <?php if (isset($breadcrumb) && is_array($breadcrumb)): ?>
                        <?php foreach ($breadcrumb as $step): ?>
                            <span class="text-muted opacity-75 fw-normal small"><?php echo htmlspecialchars($step); ?></span>
                            <i class="fa-solid fa-chevron-right opacity-25 small" style="font-size: 0.7rem;"></i>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <span class="text-primary"><?php echo htmlspecialchars($page_title); ?></span>
                </div>
            </h5>
            <small class="text-muted d-none d-xl-block"><?php echo date('d/m/Y'); ?></small>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeBtn = document.getElementById('themeToggleBtn');
    if (!themeBtn) return;
    
    const html = document.documentElement;
    const icon = themeBtn.querySelector('i');

    function updateIcon(theme) {
        if (theme === 'dark') {
            icon.className = 'fa-solid fa-sun text-warning';
        } else {
            icon.className = 'fa-solid fa-moon text-primary';
        }
    }

    // Inicializar icono
    const currentTheme = html.getAttribute('data-theme') || 'light';
    updateIcon(currentTheme);

    themeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        let theme = html.getAttribute('data-theme');
        let newTheme = theme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateIcon(newTheme);
    });
});
</script>