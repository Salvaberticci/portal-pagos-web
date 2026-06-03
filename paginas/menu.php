<?php
$page_title = "Panel de Control";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">
        <!-- Welcome Hero Section -->
        <div class="glass-panel overflow-hidden position-relative mb-5 animate-fade">
            <div class="card-body p-5 position-relative text-center text-md-start">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-4 fw-bold mb-3 text-gradient">Bienvenido a Wireless Supply</h1>
                        <p class="lead text-muted mb-4">Sistema de Gestión Administrativa y Técnica de Nueva Generación</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="p-3 bg-white bg-opacity-5 rounded-4 d-inline-block shadow-lg border border-white border-opacity-10">
                            <img src="../images/logo.jpg" alt="Wireless Supply" class="img-fluid rounded-3 shadow-sm"
                                style="max-height: 160px; filter: drop-shadow(0 0 20px rgba(59, 130, 246, 0.2));">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Grid -->
        <h4 class="mb-4 text-muted fw-bold px-2" style="letter-spacing: 0.1em; font-size: 0.9rem; text-transform: uppercase;">Accesos Directos</h4>
        <div class="row g-4">
            <!-- Contratos -->
            <div class="col-md-6 col-lg-3">
                <a href="principal/gestion_contratos.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-file-contract fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Contratos</h5>
                            <p class="card-text text-muted small">Administrar contratos de clientes</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Cobranzas -->
            <div class="col-md-6 col-lg-3">
                <a href="principal/gestion_mensualidades.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-money-bill-wave fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title">Cobranzas</h5>
                            <p class="card-text text-muted small">Gestión de cuentas por cobrar</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Usuarios -->
            <div class="col-md-6 col-lg-3">
                <a href="gestion_usuarios.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-users fa-2x text-info"></i>
                            </div>
                            <h5 class="card-title">Usuarios</h5>
                            <p class="card-text text-muted small">Gestión de usuarios del sistema</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Gestión de Fallas -->
            <div class="col-md-6 col-lg-3">
                <a href="soporte/gestion_fallas.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-chart-line fa-2x text-danger"></i>
                            </div>
                            <h5 class="card-title">Gestión de Fallas</h5>
                            <p class="card-text text-muted small">Estadísticas y reportes técnicos</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Reportes -->
            <div class="col-md-6 col-lg-3">
                <a href="reportes_pdf/reporte_cobranza.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-chart-pie fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title">Reportes</h5>
                            <p class="card-text text-muted small">Ver métricas y reportes</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Aprobar Pagos -->
            <div class="col-md-6 col-lg-3">
                <a href="principal/aprobar_pagos.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-check-double fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title">Aprobar Pagos</h5>
                            <p class="card-text text-muted small">Revisar reportes de clientes</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Historial Pagos -->
            <div class="col-md-6 col-lg-3">
                <a href="principal/historial_pagos_reportados.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-clock-rotate-left fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Historial Pagos</h5>
                            <p class="card-text text-muted small">Ver reportes procesados</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Prórrogas -->
            <div class="col-md-6 col-lg-3">
                <a href="principal/gestion_prorrogas.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-calendar-plus fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Prórrogas</h5>
                            <p class="card-text text-muted small">Gestión de solicitudes de pago</p>
                        </div>
                    </div>
                </a>
            </div>
            <!-- Conciliación -->
            <div class="col-md-6 col-lg-3">
                <a href="principal/conciliacion.php" class="text-decoration-none">
                    <div class="card h-100 hover-lift border-white border-opacity-5">
                        <div class="card-body text-center p-4">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-3 p-3 bg-white bg-opacity-5 shadow-sm">
                                <i class="fa-solid fa-scale-balanced fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title">Conciliación</h5>
                            <p class="card-text text-muted small">Validar pagos con extracto (OCR)</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <footer class="mt-5 text-center text-muted small">
            &copy; <span id="current-year"></span> Wireless Supply, C.A. Todos los derechos reservados.
        </footer>
    </div>
</main>

<script>
    document.getElementById("current-year").textContent = new Date().getFullYear();
</script>

<?php require_once 'includes/layout_foot.php'; ?>