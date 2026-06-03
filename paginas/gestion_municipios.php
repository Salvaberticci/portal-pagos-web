<?php
// Incluye el archivo de conexión.
session_start();
require_once 'conexion.php';

// El mensaje flash se manejará via JS ahora.
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_class = $_SESSION['flash_class'];
    unset($_SESSION['flash_message'], $_SESSION['flash_class']);
}

// Ya no necesitamos las consultas SQL manuales aquí, se harán vía AJAX.
$conn->close();

$path_to_root = "../";
$page_title = "Gestión de Ubicaciones";
$breadcrumb = ["Técnica"];
$back_url = "menu.php";
require_once 'includes/layout_head.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
    <?php include 'includes/header.php'; ?>

    <div class="page-content">

        <div class="row g-4">
            <!-- TABLA DE MUNICIPIOS -->
            <div class="col-xl-5 col-lg-6">
                <div class="card glass-panel h-100 border-0 shadow-sm overflow-hidden">
                    <div
                        class="card-header bg-transparent py-3 border-bottom border-white border-opacity-10 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-primary mb-1">Municipios</h5>
                            <p class="text-muted small mb-0">Gestión de áreas geográficas</p>
                        </div>
                        <button onclick="addMunicipioPrompt()" class="btn btn-premium btn-sm rounded-circle shadow-sm"
                            title="Agregar Municipio">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body px-4 py-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaMunicipios">
                                <thead class="bg-white bg-opacity-10">
                                    <tr>
                                        <th class="ps-4">Municipio</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="2" class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            </div>
                                            <span class="ms-2">Cargando municipios...</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLA DE PARROQUIAS -->
            <div class="col-xl-7 col-lg-6">
                <div class="card glass-panel h-100 border-0 shadow-sm overflow-hidden">
                    <div
                        class="card-header bg-transparent py-3 border-bottom border-white border-opacity-10 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-primary mb-1">Parroquias</h5>
                            <p class="text-muted small mb-0">Subdivisiones municipales</p>
                        </div>
                        <button onclick="addParroquiaPrompt()" class="btn btn-premium btn-sm rounded-circle shadow-sm"
                            title="Agregar Parroquia">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body px-4 py-4">
                        <div class="mb-3">
                            <div class="input-group glass-input-group input-group-sm">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-filter"></i></span>
                                <select id="filterMunicipio" class="form-select border-start-0 ps-0 text-muted"
                                    onchange="renderParroquias()">
                                    <option value="">Todos los Municipios</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaParroquias">
                                <thead class="bg-white bg-opacity-10">
                                    <tr>
                                        <th class="ps-4">Parroquia</th>
                                        <th>Municipio</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            </div>
                                            <span class="ms-2">Cargando parroquias...</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="menu.php" class="btn btn-outline-secondary px-4">
                <i class="fa-solid fa-arrow-left me-2"></i>Volver al Menú
            </a>
        </div>
    </div>
</main>

<script src="../js/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let ubicacionesData = [];

    $(document).ready(function () {
        loadUbicaciones();
    });

    function loadUbicaciones() {
        $.get('principal/api_ubicaciones.php', function (data) {
            ubicacionesData = data;
            renderMunicipios();
            renderParroquias();
            updateFilterOptions();
        });
    }

    function renderMunicipios() {
        let html = '';
        ubicacionesData.forEach((m, index) => {
            html += `
            <tr>
                <td class="fw-bold text-main ps-4">${m.municipio}</td>
                <td class="text-end pe-4">
                    <div class="btn-group gap-2">
                        <button class="btn btn-sm btn-glass text-primary rounded-2" title="Modificar" onclick="editMunicipioPrompt('${m.municipio}')">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-glass text-danger rounded-2" title="Eliminar" onclick="deleteMunicipio('${m.municipio}')">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        });
        $('#tablaMunicipios tbody').html(html || '<tr><td colspan="2" class="text-center text-muted">No hay municipios</td></tr>');
    }

    function renderParroquias() {
        const filter = $('#filterMunicipio').val();
        let html = '';
        ubicacionesData.forEach(m => {
            if (!filter || m.municipio === filter) {
                if (m.parroquias && Array.isArray(m.parroquias)) {
                    m.parroquias.forEach(p => {
                        const pNombre = typeof p === 'object' ? p.nombre : p;
                        html += `
                        <tr>
                            <td class="fw-bold text-main ps-4">${pNombre}</td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3">${m.municipio}</span></td>
                            <td class="text-end pe-4">
                                <div class="btn-group gap-2">
                                    <button class="btn btn-sm btn-glass text-primary rounded-2" title="Modificar" onclick="editParroquiaPrompt('${m.municipio}', '${pNombre}')">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-glass text-danger rounded-2" title="Eliminar" onclick="deleteParroquia('${m.municipio}', '${pNombre}')">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                    });
                }
            }
        });
        $('#tablaParroquias tbody').html(html || '<tr><td colspan="3" class="text-center text-muted">No hay parroquias</td></tr>');
    }

    function updateFilterOptions() {
        let options = '<option value="">Todos los Municipios</option>';
        ubicacionesData.forEach(m => {
            options += `<option value="${m.municipio}">${m.municipio}</option>`;
        });
        $('#filterMunicipio').html(options);
    }

    // --- SEGURIDAD ---
    async function verificarClave(nombreAccion) {
        const { value: password } = await Swal.fire({
            title: 'Verificación de Seguridad',
            text: `Ingrese la clave de administrador para ${nombreAccion}`,
            input: 'password',
            inputPlaceholder: 'Clave de administrador',
            showCancelButton: true,
            confirmButtonText: 'Verificar',
            cancelButtonText: 'Cancelar'
        });

        if (!password) return false;

        const response = await fetch('principal/verificar_clave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ clave: password })
        });
        const data = await response.json();
        if (!data.success) {
            Swal.fire('Error', data.message || 'Clave incorrecta', 'error');
            return false;
        }
        return true;
    }

    // --- ACCIONES ---
    async function addMunicipioPrompt() {
        if (!await verificarClave('agregar municipio')) return;

        const { value: nombre } = await Swal.fire({
            title: 'Nuevo Municipio',
            input: 'text',
            inputLabel: 'Nombre del municipio',
            showCancelButton: true,
            inputValidator: (value) => !value && 'Debe ingresar un nombre'
        });

        if (nombre) {
            if (ubicacionesData.some(m => m.municipio.toLowerCase() === nombre.toLowerCase())) {
                Swal.fire('Error', 'El municipio ya existe', 'warning');
                return;
            }
            ubicacionesData.push({ municipio: nombre, parroquias: [] });
            saveData();
        }
    }

    async function editMunicipioPrompt(oldName) {
        if (!await verificarClave('editar municipio')) return;

        const { value: newName } = await Swal.fire({
            title: 'Editar Municipio',
            input: 'text',
            inputLabel: 'Nuevo nombre',
            inputValue: oldName,
            showCancelButton: true,
            inputValidator: (value) => !value && 'Debe ingresar un nombre'
        });

        if (newName && newName !== oldName) {
            const mIndex = ubicacionesData.findIndex(m => m.municipio === oldName);
            if (mIndex > -1) {
                ubicacionesData[mIndex].municipio = newName;
                saveData();
            }
        }
    }

    async function deleteMunicipio(nombre) {
        const inUse = await checkUsage('municipio', nombre);
        if (inUse) {
            Swal.fire('Acción Bloqueada', 'Este municipio está siendo usado en contratos activos y no puede eliminarse.', 'warning');
            return;
        }

        if (!await verificarClave('eliminar municipio')) return;

        const result = await Swal.fire({
            title: '¿Eliminar Municipio?',
            text: `Se eliminarán todas las parroquias asociadas a ${nombre}. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar'
        });

        if (result.isConfirmed) {
            const mIndex = ubicacionesData.findIndex(m => m.municipio === nombre);
            if (mIndex > -1) {
                ubicacionesData.splice(mIndex, 1);
                saveData();
            }
        }
    }

    async function addParroquiaPrompt() {
        if (!await verificarClave('agregar parroquia')) return;

        const inputOptions = {};
        ubicacionesData.forEach(m => inputOptions[m.municipio] = m.municipio);

        const { value: municipio } = await Swal.fire({
            title: 'Seleccione Municipio',
            input: 'select',
            inputOptions: inputOptions,
            showCancelButton: true
        });

        if (municipio) {
            const { value: parroquia } = await Swal.fire({
                title: 'Nueva Parroquia',
                input: 'text',
                inputLabel: `Nombre de la parroquia para ${municipio}`,
                showCancelButton: true,
                inputValidator: (value) => !value && 'Debe ingresar un nombre'
            });

            if (parroquia) {
                const mIndex = ubicacionesData.findIndex(m => m.municipio === municipio);
                if (mIndex > -1) {
                    if (ubicacionesData[mIndex].parroquias.some(p => (typeof p === 'object' ? p.nombre : p).toLowerCase() === parroquia.toLowerCase())) {
                        Swal.fire('Error', 'La parroquia ya existe', 'warning');
                        return;
                    }
                    ubicacionesData[mIndex].parroquias.push({ nombre: parroquia, comunidades: [] });
                    saveData();
                }
            }
        }
    }

    async function editParroquiaPrompt(municipio, oldParroquia) {
        if (!await verificarClave('editar parroquia')) return;

        const { value: newParroquia } = await Swal.fire({
            title: 'Editar Parroquia',
            input: 'text',
            inputLabel: 'Nuevo nombre',
            inputValue: oldParroquia,
            showCancelButton: true,
            inputValidator: (value) => !value && 'Debe ingresar un nombre'
        });

        if (newParroquia && newParroquia !== oldParroquia) {
            const mIndex = ubicacionesData.findIndex(m => m.municipio === municipio);
            if (mIndex > -1) {
                const pIndex = ubicacionesData[mIndex].parroquias.findIndex(p => (typeof p === 'object' ? p.nombre : p) === oldParroquia);
                if (pIndex > -1) {
                    if (typeof ubicacionesData[mIndex].parroquias[pIndex] === 'object') {
                        ubicacionesData[mIndex].parroquias[pIndex].nombre = newParroquia;
                    } else {
                        ubicacionesData[mIndex].parroquias[pIndex] = { nombre: newParroquia, comunidades: [] };
                    }
                    saveData();
                }
            }
        }
    }

    async function deleteParroquia(municipio, parroquia) {
        const inUse = await checkUsage('parroquia', parroquia);
        if (inUse) {
            Swal.fire('Acción Bloqueada', 'Esta parroquia está siendo usada en contratos activos y no puede eliminarse.', 'warning');
            return;
        }

        if (!await verificarClave('eliminar parroquia')) return;

        const result = await Swal.fire({
            title: '¿Eliminar Parroquia?',
            text: `¿Seguro que desea eliminar la parroquia ${parroquia}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar'
        });

        if (result.isConfirmed) {
            const mIndex = ubicacionesData.findIndex(m => m.municipio === municipio);
            if (mIndex > -1) {
                const pIndex = ubicacionesData[mIndex].parroquias.findIndex(p => (typeof p === 'object' ? p.nombre : p) === parroquia);
                if (pIndex > -1) {
                    ubicacionesData[mIndex].parroquias.splice(pIndex, 1);
                    saveData();
                }
            }
        }
    }

    async function checkUsage(type, name) {
        try {
            const response = await fetch('principal/verificar_uso_ubicacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ tipo: type, nombre: name })
            });
            const data = await response.json();
            return data.in_use;
        } catch (e) {
            console.error(e);
            return false;
        }
    }

    function saveData() {
        Swal.fire({
            title: 'Guardando...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'principal/api_ubicaciones.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(ubicacionesData),
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    Swal.fire('¡Éxito!', response.message || 'Cambios guardados correctamente', 'success').then(() => {
                        loadUbicaciones();
                    });
                } else {
                    Swal.fire('Error', response.message || 'No se pudieron guardar los cambios', 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    }
</script>



<?php require_once 'includes/layout_foot.php'; ?>