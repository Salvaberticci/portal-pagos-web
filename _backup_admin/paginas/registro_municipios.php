<?php
require_once 'conexion.php';

// Los municipios y parroquias se cargarán vía AJAX o inicialmente para mayor rapidez
$municipios = $conn->query("SELECT id_municipio, nombre_municipio FROM `municipio` ORDER BY nombre_municipio ASC")->fetch_all(MYSQLI_ASSOC);
$parroquias = $conn->query("SELECT id_parroquia, nombre_parroquia FROM `parroquia` ORDER BY nombre_parroquia ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$path_to_root = "../";
$page_title = "Registro Geográfico";
$breadcrumb = ["Técnica", "Municipios"];
$back_url = "gestion_municipios.php";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">
        <div class="row g-4 justify-content-center">
            <!-- Formulario Municipio -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="mb-0 fw-bold">Registrar Municipio</h6>
                    </div>
                    <div class="card-body p-4">
                        <form id="form-municipio">
                            <div class="mb-3">
                                <label for="nombre_municipio"
                                    class="form-label small text-muted fw-bold text-uppercase">Nombre del
                                    Municipio</label>
                                <input type="text" class="form-control" id="nombre_municipio" name="nombre_municipio"
                                    required autofocus placeholder="Ej: Heres">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Registrar
                                Municipio</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Formulario Parroquia -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-info text-white py-3">
                        <h6 class="mb-0 fw-bold">Registrar Parroquia</h6>
                    </div>
                    <div class="card-body p-4">
                        <form id="form-parroquia">
                            <div class="mb-3">
                                <label for="id_municipio"
                                    class="form-label small text-muted fw-bold text-uppercase">Seleccionar
                                    Municipio</label>
                                <select class="form-select select-municipios" id="id_municipio" name="id_municipio"
                                    required>
                                    <option value="">Seleccione un municipio</option>
                                    <?php foreach ($municipios as $municipio): ?>
                                        <option value="<?php echo htmlspecialchars($municipio['id_municipio']); ?>">
                                            <?php echo htmlspecialchars($municipio['nombre_municipio']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="nombre_parroquia"
                                    class="form-label small text-muted fw-bold text-uppercase">Nombre de la
                                    Parroquia</label>
                                <input type="text" class="form-control" id="nombre_parroquia" name="nombre_parroquia"
                                    required placeholder="Ej: Catedral">
                            </div>
                            <button type="submit" class="btn btn-info w-100 py-2 fw-bold text-white">Registrar
                                Parroquia</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php require_once 'includes/layout_foot.php'; ?>

<script src="../js/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const API_URL = 'principal/api_municipios.php';

    $(document).ready(function () {
        // Registro de Municipio
        $('#form-municipio').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const resp = await fetch(API_URL + '?action=add_municipio', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Municipio registrado correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    this.reset();
                    actualizarMunicipios();
                } else {
                    Swal.fire('Error', res.message || 'Error al guardar', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });

        // Registro de Parroquia
        $('#form-parroquia').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const resp = await fetch(API_URL + '?action=add_parroquia', { method: 'POST', body: formData });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Parroquia registrada correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    this.reset();
                    actualizarParroquias();
                } else {
                    Swal.fire('Error', res.message || 'Error al guardar', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            }
        });
    });

    async function actualizarMunicipios() {
        try {
            const resp = await fetch(API_URL + '?action=get_municipios');
            const res = await resp.json();
            if (res.success) {
                const select = $('.select-municipios');
                select.each(function () {
                    const currentVal = $(this).val();
                    $(this).html('<option value="">Seleccione un municipio</option>');
                    res.data.forEach(m => {
                        $(this).append(`<option value="${m.id_municipio}">${m.nombre_municipio}</option>`);
                    });
                    $(this).val(currentVal);
                });
            }
        } catch (e) { console.error('Error actualizando municipios', e); }
    }

    async function actualizarParroquias() {
        try {
            const resp = await fetch(API_URL + '?action=get_parroquias');
            const res = await resp.json();
            if (res.success) {
                const select = $('.select-parroquias');
                select.each(function () {
                    const currentVal = $(this).val();
                    $(this).html('<option value="">Seleccione una parroquia</option>');
                    res.data.forEach(p => {
                        $(this).append(`<option value="${p.id_parroquia}">${p.nombre_parroquia}</option>`);
                    });
                    $(this).val(currentVal);
                });
            }
        } catch (e) { console.error('Error actualizando parroquias', e); }
    }
</script>