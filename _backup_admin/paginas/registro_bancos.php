<?php
/**
 * Registro de Bancos - Migración a JSON API con Seguridad
 */
require_once 'conexion.php';

$page_title = "Registro de Bancos";
$breadcrumb = ["Admin", "Gestión de Bancos"];
$back_url = "gestion_bancos.php";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">
        <div class="card">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                <h5 class="fw-bold text-primary mb-1">Registro de Bancos</h5>
                <p class="text-muted small mb-0">Wireless Supply, C.A.</p>
            </div>

            <div class="card-body px-4">
                <form id="form-registro-banco-standalone" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Banco</label>
                        <input type="text" class="form-control" name="nombre_banco" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Número de Cuenta</label>
                        <input type="text" class="form-control font-monospace" name="numero_cuenta"
                            placeholder="0000-0000-00-0000000000" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Cédula del Propietario</label>
                        <input type="text" class="form-control" name="cedula_propietario" placeholder="V-12345678"
                            required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nombre del Propietario</label>
                        <input type="text" class="form-control" name="titular_cuenta" placeholder="Titular completo"
                            required>
                    </div>

                    <div class="col-12 mt-4 text-end">
                        <a href="gestion_bancos.php" class="btn btn-secondary me-2 text-white">Volver</a>
                        <button type="submit" class="btn btn-primary px-4">Registrar Banco</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/jquery.min.js"></script>

<script>
    $('#form-registro-banco-standalone').on('submit', async function (e) {
        e.preventDefault();

        // 1. Confirmación de Clave
        const { value: password } = await Swal.fire({
            title: 'Verificar Identidad',
            input: 'password',
            inputLabel: 'Ingrese su clave para autorizar el registro',
            inputPlaceholder: 'Contraseña de administrador',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        });

        if (!password) return;

        // 2. Validar Clave
        const verifyResp = await fetch('principal/verificar_clave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'clave=' + encodeURIComponent(password)
        });
        const verifyData = await verifyResp.json();

        if (!verifyData.success) {
            Swal.fire('Error', 'Clave incorrecta. Acción cancelada.', 'error');
            return;
        }

        // 3. Proceder con el Registro en JSON
        const formData = new FormData(this);
        const saveResp = await fetch('principal/json_bancos_api.php?action=add', {
            method: 'POST',
            body: formData
        });
        const saveData = await saveResp.json();

        if (saveData.success) {
            Swal.fire({
                title: '¡Éxito!',
                text: 'Banco registrado correctamente en el sistema.',
                icon: 'success'
            }).then(() => {
                window.location.href = 'gestion_bancos.php';
            });
        } else {
            Swal.fire('Error', saveData.message || 'No se pudo guardar el registro', 'error');
        }
    });
</script>

<?php require_once 'includes/layout_foot.php'; ?>