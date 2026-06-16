import os
import re

file_path = "c:\\xampp\\htdocs\\sistemas-administrativo-tecnico-wireless\\paginas\\soporte\\gestion_fallas.php"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. HTML Replacement
html_start_marker = "<!-- ☠️ Caídas Críticas Activas -->"
html_end_marker = "<!-- =============== MODAL: VER DETALLES NIVEL 3 =============== -->"

parts = content.split(html_end_marker)
if len(parts) == 2:
    before_html = parts[0].split(html_start_marker)[0]
    after_html = "\n<!-- =============== MODAL: VER DETALLES NIVEL 3 =============== -->" + parts[1]
    
    nuevo_html = """
        <!-- PESTAÑAS DE TABLAS POR NIVEL -->
        <?php
        $sql_criticas = "SELECT s.id_soporte, DATE_FORMAT(s.fecha_soporte, '%d/%m/%Y') as fecha, c.nombre_completo, s.tipo_falla, s.tecnico_asignado, s.clientes_afectados, s.zona_afectada, s.solucion_completada, s.prioridad
            FROM soportes s
            INNER JOIN contratos c ON s.id_contrato = c.id
            WHERE s.prioridad = 'NIVEL 3'
            ORDER BY s.id_soporte DESC LIMIT 100";
        $res_criticas = $conn->query($sql_criticas);

        $sWhere = "s.fecha_soporte BETWEEN '$filtro_desde' AND '$filtro_hasta' AND s.prioridad != 'NIVEL 3'";
        if (!empty($filtro_tipo)) $sWhere .= " AND s.tipo_falla = '$filtro_tipo'";
        if (!empty($filtro_tecnico)) $sWhere .= " AND s.tecnico_asignado LIKE '%$filtro_tecnico%'";
        if (!empty($filtro_pago)) {
            if ($filtro_pago == 'PAGADO')
                $sWhere .= " AND (s.monto_total - s.monto_pagado) <= 0.01";
            else if ($filtro_pago == 'PENDIENTE')
                $sWhere .= " AND (s.monto_total - s.monto_pagado) > 0.01";
        }

        $sql_reportes = "SELECT s.id_soporte, DATE_FORMAT(s.fecha_soporte, '%d/%m/%Y') as fecha_formateada,
                                           s.hora_solucion, s.tiempo_transcurrido,
                                           c.nombre_completo, COALESCE(s.tipo_falla, 'No especificado') as tipo_falla,
                                           COALESCE(s.tecnico_asignado, 'Sin asignar') as tecnico, s.prioridad,
                                           s.monto_total, s.monto_pagado, (s.monto_total - s.monto_pagado) as saldo_pendiente,
                                           s.solucion_completada, s.es_caida_critica
                                    FROM soportes s
                                    INNER JOIN contratos c ON s.id_contrato = c.id
                                    WHERE $sWhere
                                    ORDER BY s.id_soporte DESC";
        $res_reportes = $conn->query($sql_reportes);

        $rowsNivel1 = [];
        $rowsNivel2 = [];

        if (!function_exists('fix_utf8')) {
            function fix_utf8($str) {
                if (empty($str)) return '';
                if (!mb_check_encoding($str, 'UTF-8')) $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
                return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        if ($res_reportes && $res_reportes->num_rows > 0) {
            while ($row = $res_reportes->fetch_assoc()) {
                if ($row['prioridad'] == 'NIVEL 1') $rowsNivel1[] = $row;
                elseif ($row['prioridad'] == 'NIVEL 2') $rowsNivel2[] = $row;
            }
        }
        
        function renderRow($row, $isNivel3 = false) {
            $id = $isNivel3 ? $row['id_soporte'] : $row['id_soporte'];
            $fecha = $isNivel3 ? htmlspecialchars($row['fecha']) : $row['fecha_formateada'];
            $cliente = fix_utf8($row['nombre_completo']);
            $falla = fix_utf8($row['tipo_falla'] ?? 'N/A');
            $tecnico = fix_utf8($isNivel3 ? $row['tecnico_asignado'] : $row['tecnico']);
            $prioridad = $row['prioridad'] ?? 'NIVEL 3';
            $solucion_completada = $row['solucion_completada'];

            if ($isNivel3) {
                echo "<tr style='cursor:pointer;' ondblclick='verDetallesCritica({$id})'>";
                echo "<td><span class='badge bg-danger'>#{$id}</span></td>";
                echo "<td>{$fecha}</td>";
                echo "<td>{$cliente}</td>";
                echo "<td>{$falla}</td>";
                echo "<td>{$tecnico}</td>";
                echo "<td class='text-center fw-bold text-danger'>" . intval($row['clientes_afectados'] ?? 0) . "</td>";
                echo "<td>" . fix_utf8($row['zona_afectada'] ?? '—') . "</td>";
                echo "<td>" . ($solucion_completada ? '<span class="badge bg-success">Solucionada</span>' : '<span class="badge bg-warning text-dark">Activa</span>') . "</td>";
                echo "<td class='text-nowrap'>";
                echo "<button class='btn btn-sm btn-outline-primary' onclick='verDetallesCritica({$id})' title='Ver Detalles'><i class='fa-solid fa-eye'></i></button> ";
                echo "<button class='btn btn-sm btn-warning me-1' onclick='editarCritica({$id})' title='Editar'><i class='fa-solid fa-pen'></i></button> ";
                echo "<a href='generar_pdf_reporte.php?id={$id}' target='_blank' class='btn btn-sm btn-danger me-1' title='Exportar PDF'><i class='fa-solid fa-file-pdf'></i></a> ";
                $btnClass = $solucion_completada ? 'btn-secondary' : 'btn-success';
                $btnIcon = $solucion_completada ? 'rotate-left' : 'check';
                $btnTitle = $solucion_completada ? 'Marcar Activa' : 'Marcar Solucionada';
                $nuevoStatus = $solucion_completada ? 0 : 1;
                echo "<button class='btn btn-sm {$btnClass}' onclick='toggleEstado({$id}, {$nuevoStatus}, \\"NIVEL 3\\")' title='{$btnTitle}'><i class='fa-solid fa-{$btnIcon}'></i></button>";
                echo "</td></tr>";
            } else {
                $saldo = floatval($row['saldo_pendiente'] ?? 0);
                $badgePago = $saldo <= 0.01 ? '<span class="badge bg-success">Pagado</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>';
                $badgePrioridad = ($prioridad == 'NIVEL 1') ? '<span class="badge" style="background-color: #ffff00; color: #000;">Nivel 1</span>' : '<span class="badge bg-warning text-dark">Nivel 2</span>';
                $rowClass = !empty($row['es_caida_critica']) ? 'table-warning' : '';
                echo "<tr class='{$rowClass}' style='cursor:pointer;' ondblclick='verDetalles({$id})'>";
                echo "<td>{$id}</td>";
                echo "<td>{$fecha}</td>";
                echo "<td>" . (substr($row['hora_solucion'] ?? '', 0, 5) ?: '—') . "</td>";
                echo "<td>" . fix_utf8($row['tiempo_transcurrido'] ?: '—') . "</td>";
                echo "<td>{$cliente}</td>";
                echo "<td>{$falla}</td>";
                echo "<td>{$tecnico}</td>";
                echo "<td>{$badgePrioridad}</td>";
                echo "<td>{$badgePago}</td>";
                $estadoBadge = $solucion_completada ? '<span class="badge bg-success">Solucionada</span>' : '<span class="badge bg-warning text-dark">Activa</span>';
                echo "<td>{$estadoBadge}</td>";
                echo "<td class='text-nowrap'>";
                echo "<button class='btn btn-sm btn-outline-info me-1' onclick='verDetalles({$id})' title='Ver Detalles'><i class='fa-solid fa-eye'></i></button> ";
                echo "<button class='btn btn-sm btn-warning me-1' onclick='abrirEditar({$id})' title='Editar'><i class='fa-solid fa-pen'></i></button> ";
                echo "<a href='generar_pdf_reporte.php?id={$id}' target='_blank' class='btn btn-sm btn-danger me-1' title='PDF'><i class='fa-solid fa-file-pdf'></i></a> ";
                $btnClass = $solucion_completada ? 'btn-secondary' : 'btn-success';
                $btnIcon = $solucion_completada ? 'rotate-left' : 'check';
                $btnTitle = $solucion_completada ? 'Marcar Activa' : 'Marcar Solucionada';
                $nuevoStatus = $solucion_completada ? 0 : 1;
                echo "<button class='btn btn-sm {$btnClass}' onclick='toggleEstado({$id}, {$nuevoStatus}, \\"{$prioridad}\\")' title='{$btnTitle}'><i class='fa-solid fa-{$btnIcon}'></i></button>";
                echo "</td></tr>";
            }
        }
        ?>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs border-0" id="tablesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-dark px-4 py-3 border-0 rounded-0" id="nav-nivel1-tab" data-bs-toggle="tab" data-bs-target="#nav-nivel1" type="button" role="tab" style="background-color: transparent;">
                            <i class="fa-solid fa-screwdriver-wrench text-warning me-1"></i>Fallas Nivel 1 (<?php echo count($rowsNivel1); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-dark px-4 py-3 border-0 rounded-0" id="nav-nivel2-tab" data-bs-toggle="tab" data-bs-target="#nav-nivel2" type="button" role="tab" style="background-color: transparent;">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>Fallas Nivel 2 (<?php echo count($rowsNivel2); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-danger px-4 py-3 border-0 rounded-0" id="nav-criticas-tab" data-bs-toggle="tab" data-bs-target="#nav-criticas" type="button" role="tab" style="background-color: transparent;">
                            <i class="fa-solid fa-fire fa-shake me-1"></i>Caídas Críticas Nivel 3 (<?php echo $res_criticas ? $res_criticas->num_rows : 0; ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="nav-tabContent">
                    
                    <!-- TAB NIVEL 1 -->
                    <div class="tab-pane fade show active" id="nav-nivel1" role="tabpanel">
                        <div class="bg-light border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="fw-bold text-muted"><i class="fa-solid fa-info-circle me-1"></i>Fallas de Configuración o Red Interna</span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-dark border-dark text-white"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="buscadorNivel1" class="form-control" placeholder="Buscar Nivel 1...">
                                </div>
                                <button class="btn btn-sm btn-outline-danger fw-bold bg-white" onclick="exportarPDF(false, 'NIVEL 1')">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Listado
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="display table table-striped table-bordered w-100 mb-0" id="tablaNivel1">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Fecha</th><th>Hora</th><th>Tiempo</th><th>Cliente</th><th>Tipo Falla</th><th>Técnico</th><th>Nivel</th><th>Pagado</th><th>Estado</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (count($rowsNivel1) > 0) {
                                        foreach ($rowsNivel1 as $r) renderRow($r);
                                    } else {
                                        echo "<tr><td colspan='11' class='text-center text-muted py-4'>No se encontraron reportes de Nivel 1.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                            <span id="infoPaginacionNivel1" class="text-muted small">Mostrando registros</span>
                            <nav><ul class="pagination pagination-sm mb-0" id="paginacionNivel1"></ul></nav>
                        </div>
                    </div>

                    <!-- TAB NIVEL 2 -->
                    <div class="tab-pane fade" id="nav-nivel2" role="tabpanel">
                        <div class="bg-light border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="fw-bold text-muted"><i class="fa-solid fa-info-circle me-1"></i>Averías Físicas (Corte Fibra/Equipos)</span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-dark border-dark text-white"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="buscadorNivel2" class="form-control" placeholder="Buscar Nivel 2...">
                                </div>
                                <button class="btn btn-sm btn-outline-danger fw-bold bg-white" onclick="exportarPDF(false, 'NIVEL 2')">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Listado
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="display table table-striped table-bordered w-100 mb-0" id="tablaNivel2">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Fecha</th><th>Hora</th><th>Tiempo</th><th>Cliente</th><th>Tipo Falla</th><th>Técnico</th><th>Nivel</th><th>Pagado</th><th>Estado</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (count($rowsNivel2) > 0) {
                                        foreach ($rowsNivel2 as $r) renderRow($r);
                                    } else {
                                        echo "<tr><td colspan='11' class='text-center text-muted py-4'>No se encontraron reportes de Nivel 2.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                            <span id="infoPaginacionNivel2" class="text-muted small">Mostrando registros</span>
                            <nav><ul class="pagination pagination-sm mb-0" id="paginacionNivel2"></ul></nav>
                        </div>
                    </div>

                    <!-- TAB NIVEL 3 (Caídas Críticas) -->
                    <div class="tab-pane fade" id="nav-criticas" role="tabpanel">
                        <div class="bg-danger text-white p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="fa-solid fa-fire fa-shake"></i>
                                <span class="fw-bold ms-1">Fallas Masivas de Infraestructura</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-dark border-dark text-white"><i class="fa-solid fa-search"></i></span>
                                    <input type="text" id="buscadorNivel3" class="form-control" placeholder="Buscar Nivel 3...">
                                </div>
                                <button class="btn btn-sm btn-light fw-bold text-danger" onclick="exportarPDFCriticas()">
                                    <i class="fa-solid fa-file-pdf me-1"></i>Listado
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="table table-bordered mb-0" id="tablaNivel3">
                                <thead class="table-danger">
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Cliente / Referencia</th>
                                        <th>Tipo Falla</th>
                                        <th>Técnico</th>
                                        <th>Client. Afectados</th>
                                        <th>Zona</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($res_criticas && $res_criticas->num_rows > 0) {
                                        mysqli_data_seek($res_criticas, 0);
                                        while ($r = $res_criticas->fetch_assoc()) {
                                            renderRow($r, true);
                                        }
                                    } else {
                                        echo "<tr><td colspan='9' class='text-center text-muted py-4'>No se encontraron caídas críticas activas.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3">
                            <span id="infoPaginacionNivel3" class="text-muted small">Mostrando registros</span>
                            <nav><ul class="pagination pagination-sm mb-0" id="paginacionNivel3"></ul></nav>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <style>
            #tablesTabs .nav-link { color: #555; border-bottom: 2px solid transparent; }
            #tablesTabs .nav-link.active { border-bottom: 3px solid #0d6efd; color: #0d6efd !important; background-color: #f8f9fa !important; }
            #tablesTabs #nav-criticas-tab.active { border-bottom: 3px solid #dc3545; color: #dc3545 !important; }
        </style>
"""
    content = before_html + nuevo_html + after_html

# 2. JS replacement setup
js_search_pdf = r"function exportarPDF\(soloReportes = false\) \{(.*?)\}"
js_replace_pdf = """function exportarPDF(excluirNivel3 = false, prioridadEspecifica = null) {
        let params = new URLSearchParams();
        params.append('fecha_desde', $('#fecha_desde').val());
        params.append('fecha_hasta', $('#fecha_hasta').val());
        if ($('#filtro_tipo').val()) params.append('filtro_tipo', $('#filtro_tipo').val());
        if ($('#filtro_tecnico').val()) params.append('filtro_tecnico', $('#filtro_tecnico').val());
        if ($('#filtro_pago').val()) params.append('filtro_pago', $('#filtro_pago').val());
        if (excluirNivel3) params.append('excluir_nivel_3', '1');
        if (prioridadEspecifica) params.append('filtro_prioridad', prioridadEspecifica);
        
        window.open('generar_pdf_consolidado.php?' + params.toString(), '_blank');
    }"""
content = re.sub(js_search_pdf, js_replace_pdf, content, flags=re.DOTALL)

# Paginacion JS replacement
js_pag_start = "// --- Paginación y Búsqueda Nativa ---"
js_pag_end = "// --- Funciones para Gestión de Opciones JSON ---"
parts_js = content.split(js_pag_end)

if len(parts_js) == 2:
    before_js = parts_js[0].split(js_pag_start)[0]
    after_js = "\n    // --- Funciones para Gestión de Opciones JSON ---" + parts_js[1]
    
    nuevo_js = """// --- Paginación Tabulada (Nivel 1, Nivel 2, Nivel 3) ---
    function initPaginacion(tablaId, buscadorId, infoId, pagId) {
        const filasPorPagina = 10;
        let paginaActual = 1;

        function actualizarTabla() {
            const buscador = $(`#${buscadorId}`).val().toLowerCase();
            const todasLasFilas = $(`#${tablaId} tbody tr`);
            let filasFiltradas = [];

            if (todasLasFilas.length === 1 && todasLasFilas.find('.text-center').length > 0) return;

            todasLasFilas.each(function () {
                let fila = $(this);
                let textoFila = fila.text().toLowerCase();
                if (textoFila.includes(buscador)) {
                    filasFiltradas.push(fila);
                    fila.removeClass('d-none');
                } else {
                    fila.addClass('d-none');
                }
            });

            const totalFiltradas = filasFiltradas.length;
            const totalPaginas = Math.ceil(totalFiltradas / filasPorPagina);

            if (paginaActual > totalPaginas && totalPaginas > 0) paginaActual = totalPaginas;
            else if (paginaActual < 1) paginaActual = 1;

            const inicio = (paginaActual - 1) * filasPorPagina;
            const fin = inicio + filasPorPagina;

            for (let i = 0; i < totalFiltradas; i++) {
                if (i >= inicio && i < fin) filasFiltradas[i].removeClass('d-none');
                else filasFiltradas[i].addClass('d-none');
            }

            const numInicioDisp = totalFiltradas === 0 ? 0 : inicio + 1;
            const numFinDisp = (fin > totalFiltradas) ? totalFiltradas : fin;
            $(`#${infoId}`).text(`Mostrando ${numInicioDisp} a ${numFinDisp} de ${totalFiltradas} registros`);
            
            renderizarControles(totalPaginas);
        }

        function renderizarControles(totalPaginas) {
            const contenedor = $(`#${pagId}`);
            contenedor.empty();
            if (totalPaginas <= 1) return;

            const disAnt = paginaActual === 1 ? 'disabled' : '';
            contenedor.append(`<li class="page-item ${disAnt}"><a class="page-link" href="javascript:void(0)" data-page="${paginaActual - 1}">Anterior</a></li>`);

            let startPage = Math.max(1, paginaActual - 2);
            let endPage = startPage + 4;
            if (endPage > totalPaginas) {
                endPage = totalPaginas;
                startPage = Math.max(1, endPage - 4);
            }

            for (let i = startPage; i <= endPage; i++) {
                const activo = i === paginaActual ? 'active' : '';
                contenedor.append(`<li class="page-item ${activo}"><a class="page-link" href="javascript:void(0)" data-page="${i}">${i}</a></li>`);
            }

            const disSig = paginaActual === totalPaginas ? 'disabled' : '';
            contenedor.append(`<li class="page-item ${disSig}"><a class="page-link" href="javascript:void(0)" data-page="${paginaActual + 1}">Siguiente</a></li>`);

            contenedor.find('.page-link').on('click', function() {
                const page = $(this).data('page');
                if(page) {
                    paginaActual = parseInt(page);
                    actualizarTabla();
                }
            });
        }

        $(`#${buscadorId}`).on('keyup', function () {
            paginaActual = 1;
            actualizarTabla();
        });

        // Init
        if ($(`#${tablaId} tbody tr`).length > 0 && !$(`#${tablaId} tbody tr td`).hasClass('text-center')) {
            actualizarTabla();
        }
    }

    $(document).ready(function () {
        // Validaciones de fechas
        const inputDesde = document.getElementById('fecha_desde');
        const inputHasta = document.getElementById('fecha_hasta');
        if (inputDesde && inputHasta) {
            inputDesde.addEventListener('change', function () {
                if (this.value && inputHasta.value && this.value > inputHasta.value) inputHasta.value = this.value;
            });
            inputHasta.addEventListener('change', function () {
                if (this.value && inputDesde.value && this.value < inputDesde.value) inputDesde.value = this.value;
            });
        }

        // Init tres tablas
        initPaginacion('tablaNivel1', 'buscadorNivel1', 'infoPaginacionNivel1', 'paginacionNivel1');
        initPaginacion('tablaNivel2', 'buscadorNivel2', 'infoPaginacionNivel2', 'paginacionNivel2');
        initPaginacion('tablaNivel3', 'buscadorNivel3', 'infoPaginacionNivel3', 'paginacionNivel3');
    });
"""
    content = before_js + nuevo_js + after_js

content = re.sub(r"// === CAÍDAS CRÍTICAS: Búsqueda, Paginación, Funciones ====(.*?)// --- Ver Detalles Crítica ---", r"// --- Ver Detalles Crítica ---", content, flags=re.DOTALL)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)
print("Updated gestion_fallas.php successfully.")
