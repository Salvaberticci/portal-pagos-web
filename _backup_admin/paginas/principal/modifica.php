<?php

/**
 * Formulario para editar un registro existente
 *
 * Este formulario permite editar los datos de un registro existente en la base de datos.
 */

require '../conexion.php';

// Obtener el ID del registro a editar desde la URL
$id = $conn->real_escape_string($_GET['id']);

$sql = "SELECT * FROM contratos WHERE id = '$id'";
$resultado = $conn->query($sql);
$row = $resultado->fetch_assoc();

// Configuración del Layout
$path_to_root = "../../";
$page_title = "Modificar Registro";
$breadcrumb = ["Admin", "Gestión de Contratos"];
$back_url = "gestion_contratos.php";
require_once '../includes/layout_head.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
	<?php include '../includes/header.php'; ?>

	<div class="page-content">
		<div class="card">
			<div
				class="card-header d-flex justify-content-between align-items-center bg-white border-bottom-0 pt-4 px-4">
				<div>
					<h5 class="fw-bold text-primary mb-1">Modificar Registro</h5>
					<p class="text-muted small mb-0">Wireless Supply, C.A.</p>
				</div>
			</div>

			<div class="card-body px-4">
				<form class="row g-3" method="POST" action="actualiza.php" enctype="multipart/form-data"
					autocomplete="off">

					<input type="hidden" id="id" name="id" value="<?php echo $row['id']; ?>" />

					<div class="col-md-6">
						<label for="ip" class="form-label">IP</label>
						<input type="text" class="form-control" id="ip" name="ip" value="<?php echo $row['ip']; ?>"
							required autofocus>
					</div>

					<div class="col-md-6">
						<label for="cedula" class="form-label">Cédula / RIF <span class="text-danger">*</span></label>
						<div class="input-group">
							<?php
							$raw_cedula = $row['cedula'];
							$tipo_cedula_val = preg_match('/^[VEJ]/i', $raw_cedula) ? strtoupper($raw_cedula[0]) : 'V';
							// Asegurarnos que el input siempre tenga el prefijo para la lógica JS
							$cedula_full = preg_match('/^[VEJ]/i', $raw_cedula) ? $raw_cedula : 'V' . $raw_cedula;
							?>
							<select class="form-select" name="tipo_cedula" id="tipo_cedula" style="max-width: 80px;"
								required>
								<option value="V" <?php echo $tipo_cedula_val == 'V' ? 'selected' : ''; ?>>V</option>
								<option value="E" <?php echo $tipo_cedula_val == 'E' ? 'selected' : ''; ?>>E</option>
								<option value="J" <?php echo $tipo_cedula_val == 'J' ? 'selected' : ''; ?>>J</option>
							</select>
							<input type="text" class="form-control" id="cedula" name="cedula"
								value="<?php echo htmlspecialchars($cedula_full); ?>" required pattern="^[VEJ][0-9]+">
						</div>
					</div>

					<div class="col-md-6">
						<label for="nombre_completo" class="form-label">Nombre Completo</label>
						<input type="text" class="form-control" id="nombre_completo" name="nombre_completo"
							value="<?php echo $row['nombre_completo']; ?>">
					</div>

					<div class="col-md-6">
						<label for="telefono" class="form-label">Teléfono</label>
						<input type="text" class="form-control" id="telefono" name="telefono"
							value="<?php echo $row['telefono']; ?>">
					</div>

					<div class="col-md-6">
						<label for="correo" class="form-label">Correo</label>
						<input type="text" class="form-control" id="correo" name="correo"
							value="<?php echo $row['correo']; ?>">
					</div>

					<?php

					// ⚠️ 1. OBTENER EL ID DEL CONTRATO A EDITAR 
					$idContrato = $row['id'];

					// 2. CONSULTA PARA OBTENER DATOS ACTUALES (INCLUYENDO MUNICIPIO, PARROQUIA y COMUNIDAD)
					$sql_contrato = "SELECT id_municipio, id_parroquia, municipio_texto, parroquia_texto 
									 FROM contratos 
									 WHERE id = $idContrato";
					$resultado_contrato = $conn->query($sql_contrato);

					if ($resultado_contrato->num_rows === 0) {
						die("Contrato no encontrado.");
					}

					$datos_actuales = $resultado_contrato->fetch_assoc();
					$municipio_seleccionado = $datos_actuales['municipio_texto'] ?? ''; // Ahora usamos texto
					$parroquia_seleccionada = $datos_actuales['parroquia_texto'] ?? ''; // Ahora usamos texto
					
					// 3. CONSULTA PARA OBTENER TODOS LOS MUNICIPIOS, PLANES Y VENDEDORES (para llenar la lista)
					$sql_municipios = "SELECT id_municipio, nombre_municipio FROM municipio ORDER BY nombre_municipio ASC";
					$resultado_municipios = $conn->query($sql_municipios);

					// OBTENER VALORES SELECCIONADOS DEL CONTRATO (RED)
					// ----------------------------------------------------
					$vendedor_seleccionado = $row['vendedor_texto'] ?? '';
					$plan_seleccionado = $row['id_plan'];
					$pon_seleccionado = $row['id_pon'];
					$olt_seleccionado = $row['id_olt'];
					// ----------------------------------------------------
					
					// CONSULTAS PARA LLENAR LAS LISTAS (STATIC)
					// ----------------------------------------------------
					
					// Vendedores desde JSON
					$vend_json = 'data/vendedores.json';
					$resultado_vendedores = file_exists($vend_json) ? (json_decode(file_get_contents($vend_json), true) ?: []) : [];

					// Consulta para Planes
					$sql_planes = "SELECT id_plan, nombre_plan FROM planes ORDER BY nombre_plan ASC";
					$resultado_planes = $conn->query($sql_planes);

					$estado_actual = $row['estado'];

					// Consulta para OLTs
					$sql_olt = "SELECT id_olt, nombre_olt FROM olt ORDER BY nombre_olt ASC";
					$resultado_olt = $conn->query($sql_olt);

					// 🔑 NUEVO: Consulta rápida para obtener el nombre del PON seleccionado (Para el label inicial)
					$nombre_pon_actual = 'Cargando...';
					if (!empty($pon_seleccionado)) {
						$sql_nombre_pon = "SELECT nombre_pon FROM pon WHERE id_pon = $pon_seleccionado";
						$res_nombre_pon = $conn->query($sql_nombre_pon);
						if ($res_nombre_pon && $res_nombre_pon->num_rows > 0) {
							$nombre_pon_actual = $res_nombre_pon->fetch_assoc()['nombre_pon'];
						}
					}
					// 🔑 NUEVO: Consulta rápida para obtener el nombre de la OLT seleccionada (Para el label inicial, aunque el select lo hará)
					$nombre_olt_actual = 'Cargando...';
					if (!empty($olt_seleccionado)) {
						$sql_nombre_olt = "SELECT nombre_olt FROM olt WHERE id_olt = $olt_seleccionado";
						$res_nombre_olt = $conn->query($sql_nombre_olt);
						if ($res_nombre_olt && $res_nombre_olt->num_rows > 0) {
							$nombre_olt_actual = $res_nombre_olt->fetch_assoc()['nombre_olt'];
						}
					}

					// CONVERSIÓN DE FECHA PARA VISUALIZACIÓN
					// ----------------------------------------------------
					$fecha_sql = $row['fecha_instalacion'];

					// Convertir YYYY-MM-DD (SQL) a DD/MM/YYYY (Venezolano) para mostrar
					if (!empty($fecha_sql) && $fecha_sql != '0000-00-00') {
						$fecha_venezolana = date('d/m/Y', strtotime($fecha_sql));
					} else {
						$fecha_venezolana = "No definida";
					}


					?>
					<div class="col-md-6">
						<label for="municipio" class="form-label">Municipio</label>
						<select name="id_municipio" id="municipio" class="form-select" required>
							<option value="">-- Cargando Municipios --</option>
						</select>
					</div>

					<br>

					<div class="col-md-6">
						<label for="parroquia" class="form-label">Parroquia</label>
						<select name="id_parroquia" id="parroquia" class="form-select" required disabled>
							<option value="">-- Seleccione un Municipio primero --</option>
						</select>
					</div>




					<div class="col-md-6">
						<label for="id_plan" class="form-label">Plan</label>
						<select name="id_plan" id="id_plan" class="form-select" required>
							<option value="">-- Seleccione un Plan --</option>

							<?php
							if ($resultado_planes->num_rows > 0) {
								while ($fila = $resultado_planes->fetch_assoc()) {
									$id = htmlspecialchars($fila["id_plan"]);
									$nombre = htmlspecialchars($fila["nombre_plan"]);

									// Lógica CLAVE: Marca la opción guardada
									$selected = ($id == $plan_seleccionado) ? 'selected' : '';

									echo "<option value=\"$id\" $selected>$nombre</option>";
								}
							}
							?>
						</select>
					</div>

					<div class="col-md-6">
						<label for="vendedor_texto" class="form-label">Vendedor</label>
						<select name="vendedor_texto" id="vendedor_texto" class="form-select" required>
							<option value="">-- Seleccione un Vendedor --</option>
							<?php
							foreach ($resultado_vendedores as $nombre) {
								$selected = ($nombre == $vendedor_seleccionado) ? 'selected' : '';
								echo '<option value="' . htmlspecialchars($nombre) . '" ' . $selected . '>' . htmlspecialchars($nombre) . '</option>';
							}
							?>
						</select>
					</div>

					<div class="col-md-6">
						<label for="direccion" class="form-label">Direccion</label>
						<textarea class="form-control" id="direccion" name="direccion"
							rows="3"><?php echo htmlspecialchars($row['direccion']); ?></textarea>
					</div>

					<div class="col-md-6">
						<label for="fecha_instalacion" class="form-label">Fecha de Instalación</label>

						<input type="date" class="form-control" id="fecha_instalacion" name="fecha_instalacion"
							value="<?php echo $row['fecha_instalacion']; ?>">

						<?php if ($row['fecha_instalacion']): ?>
							<small class="form-text text-muted">
								<?php //echo $fecha_venezolana; ?>
							</small>
						<?php endif; ?>
					</div>

					<div class="col-md-6">
						<label for="estado" class="form-label">Estado del Contrato</label>
						<select class="form-select" id="estado" name="estado" required>
							<option value="">-- Seleccione el Estado --</option>

							<option value="ACTIVO" <?php if ($estado_actual == 'ACTIVO')
								echo 'selected'; ?>>
								ACTIVO
							</option>

							<option value="INACTIVO" <?php if ($estado_actual == 'INACTIVO')
								echo 'selected'; ?>>
								INACTIVO
							</option>

							<option value="SUSPENDIDO" <?php if ($estado_actual == 'SUSPENDIDO')
								echo 'selected'; ?>>
								SUSPENDIDO
							</option>

						</select>
					</div>


					<div class="col-md-6">
						<label for="num_presinto_odn" class="form-label">Numero_Presinto_ODN</label>
						<input type="text" class="form-control" id="num_presinto_odn" name="num_presinto_odn"
							value="<?php echo $row['num_presinto_odn']; ?>">
					</div>

					<div class="col-md-6">
						<label for="id_olt" class="form-label">OLT</label>
						<select name="id_olt" id="id_olt" class="form-select" required>
							<option value="">-- Seleccione una OLT --</option>

							<?php
							if ($resultado_olt->num_rows > 0) {
								while ($fila = $resultado_olt->fetch_assoc()) {
									$id = htmlspecialchars($fila["id_olt"]);
									$nombre = htmlspecialchars($fila["nombre_olt"]);

									// Lógica CLAVE: Marca la opción guardada
									$selected = ($id == $olt_seleccionado) ? 'selected' : '';

									echo "<option value=\"$id\" $selected>$nombre</option>";
								}
							}
							?>
						</select>
					</div>

					<div class="col-md-6">
						<label for="id_pon" class="form-label">PON</label>
						<select name="id_pon" id="id_pon" class="form-select" required disabled>
							<option value="<?php echo $pon_seleccionado; ?>" selected>
								<?php echo htmlspecialchars($nombre_pon_actual); ?> (Actual)
							</option>
						</select>
					</div>

					<!-- NUEVOS CAMPOS ADMINISTRATIVOS Y TÉCNICOS -->
					<div class="section-title bg-light p-2 mt-4 fw-bold border-start border-primary border-4">
						Información de Conexión</div>

					<div class="col-md-6">
						<label for="tipo_conexion" class="form-label">Tipo de Conexión</label>
						<select name="tipo_conexion" id="tipo_conexion" class="form-select" required>
							<option value="">-- Seleccione Conexión --</option>
							<?php
							// Cargar Tipos desde JSON
							$jsonFileTypes = 'data/tipos_conexion.json';
							$current_tipo_conexion = $row['tipo_conexion'] ?? '';
							if (file_exists($jsonFileTypes)) {
								$typesData = json_decode(file_get_contents($jsonFileTypes), true);
								if (is_array($typesData)) {
									foreach ($typesData as $type) {
										$selected = ($type == $current_tipo_conexion) ? 'selected' : '';
										echo '<option value="' . $type . '" ' . $selected . '>' . $type . '</option>';
									}
								}
							} else {
								// Fallback
								echo '<option value="FTTH" ' . ($current_tipo_conexion == 'FTTH' ? 'selected' : '') . '>FTTH</option>';
								echo '<option value="RADIO" ' . ($current_tipo_conexion == 'RADIO' ? 'selected' : '') . '>RADIO</option>';
							}
							?>
						</select>
					</div>

					<div class="col-md-6">
						<label for="tipo_instalacion" class="form-label">Tipo de Instalación <span
								class="text-danger">*</span></label>
						<select class="form-select" id="tipo_instalacion" name="tipo_instalacion" required>
							<option value="">-- Seleccione --</option>
							<?php
							$jsonTiposInst = 'data/tipos_instalacion.json';
							$current_tipo_instalacion = $row['tipo_instalacion'] ?? $current_tipo_conexion;
							if (file_exists($jsonTiposInst)) {
								$tiposInst = json_decode(file_get_contents($jsonTiposInst), true);
								foreach ($tiposInst as $t) {
									$selected = ($t == $current_tipo_instalacion) ? 'selected' : '';
									echo '<option value="' . $t . '" ' . $selected . '>' . $t . '</option>';
								}
							} else {
								$defaultsInst = ["Nivel 1", "Nivel 2", "Nivel 3", "Mudanza", "Migración", "Onu", "Reactivación"];
								foreach ($defaultsInst as $t) {
									$selected = ($t == $current_tipo_instalacion) ? 'selected' : '';
									echo '<option value="' . $t . '" ' . $selected . '>' . $t . '</option>';
								}
							}
							?>
						</select>
					</div>

					<div class="section-title bg-light p-2 mt-4 fw-bold border-start border-primary border-4">Detalles
						Técnicos</div>

					<!-- CAMPOS FTTH -->
					<div class="col-md-6 campo-ftth">
						<label for="mac_onu" class="form-label">MAC/Serial ONU</label>
						<input type="text" class="form-control" id="mac_onu" name="mac_onu"
							value="<?php echo $row['mac_onu']; ?>">
					</div>
					<div class="col-md-6 campo-ftth">
						<label for="ip_onu" class="form-label">IP ONU</label>
						<input type="text" class="form-control" id="ip_onu" name="ip_onu"
							value="<?php echo $row['ip_onu']; ?>">
					</div>
					<div class="col-md-6 campo-ftth">
						<label for="ident_caja_nap" class="form-label">Identificación Caja NAP</label>
						<input type="text" class="form-control" id="ident_caja_nap" name="ident_caja_nap"
							value="<?php echo htmlspecialchars($row['ident_caja_nap'] ?? ''); ?>">
					</div>
					<div class="col-md-6 campo-ftth">
						<label for="puerto_nap" class="form-label">Puerto NAP</label>
						<input type="text" class="form-control" id="puerto_nap" name="puerto_nap"
							value="<?php echo htmlspecialchars($row['puerto_nap'] ?? ''); ?>">
					</div>
					<div class="col-md-6 campo-ftth">
						<label for="nap_tx_power" class="form-label">NAP TX Power</label>
						<input type="text" class="form-control" id="nap_tx_power" name="nap_tx_power"
							value="<?php echo $row['nap_tx_power']; ?>">
					</div>
					<div class="col-md-6 campo-ftth">
						<label for="onu_rx_power" class="form-label">ONU RX Power</label>
						<input type="text" class="form-control" id="onu_rx_power" name="onu_rx_power"
							value="<?php echo $row['onu_rx_power']; ?>">
					</div>
					<div class="col-md-6 campo-ftth">
						<label for="distancia_drop" class="form-label">Distancia Drop (m)</label>
						<input type="text" class="form-control" id="distancia_drop" name="distancia_drop"
							value="<?php echo $row['distancia_drop']; ?>">
					</div>

					<!-- CAMPOS RADIO -->
					<div class="col-md-6 campo-radio">
						<label for="punto_acceso" class="form-label">Punto de Acceso</label>
						<input type="text" class="form-control" id="punto_acceso" name="punto_acceso"
							value="<?php echo $row['punto_acceso']; ?>">
					</div>
					<div class="col-md-6 campo-radio">
						<label for="valor_conexion_dbm" class="form-label">Valor Conexión (dBm)</label>
						<input type="text" class="form-control" id="valor_conexion_dbm" name="valor_conexion_dbm"
							value="<?php echo $row['valor_conexion_dbm']; ?>">
					</div>

					<!-- Instaladores -->
					<?php
					$jsonInstaladores = 'data/instaladores.json';
					$instaladoresList = [];
					if (file_exists($jsonInstaladores)) {
						$instaladoresList = json_decode(file_get_contents($jsonInstaladores), true) ?: [];
					}
					// Si el JSON está vacío, intentamos cargar de la DB
					if (empty($instaladoresList)) {
						$sql_inst_fb = "SELECT nombre_instalador FROM instaladores WHERE activo = 1 ORDER BY nombre_instalador ASC";
						$res_inst_fb = $conn->query($sql_inst_fb);
						while ($inst = $res_inst_fb->fetch_assoc()) {
							$instaladoresList[] = $inst['nombre_instalador'];
						}
					}
					?>

					<div class="col-md-6 campo-ftth">
						<label for="instalador_ftth" class="form-label fw-bold">Instalador FTTH</label>
						<select name="instalador_ftth" id="instalador_ftth" class="form-select">
							<option value="">-- Seleccione un Instalador --</option>
							<?php
							foreach ($instaladoresList as $inst) {
								$selected = ($inst == $row['instalador']) ? 'selected' : '';
								echo '<option value="' . htmlspecialchars($inst) . '" ' . $selected . '>' . htmlspecialchars($inst) . '</option>';
							}
							?>
						</select>
					</div>

					<div class="col-md-6 campo-radio">
						<label for="instalador_radio" class="form-label fw-bold">Instalador Radio</label>
						<select name="instalador_radio" id="instalador_radio" class="form-select">
							<option value="">-- Seleccione un Instalador --</option>
							<?php
							foreach ($instaladoresList as $inst) {
								$selected = ($inst == $row['instalador_c']) ? 'selected' : '';
								echo '<option value="' . htmlspecialchars($inst) . '" ' . $selected . '>' . htmlspecialchars($inst) . '</option>';
							}
							?>
						</select>
					</div>



					<div class="col-md-12">
						<label for="evidencia_foto" class="form-label">Evidencia Foto (Opcional)</label>
						<?php if (!empty($row['evidencia_foto'])): ?>
							<div class="mb-2">
								<img src="../../<?php echo $row['evidencia_foto']; ?>" class="img-thumbnail"
									style="max-width: 200px;">
								<p class="small text-muted">Imagen actual</p>
							</div>
						<?php endif; ?>
						<input type="file" class="form-control" id="evidencia_foto" name="evidencia_foto"
							accept="image/*">
					</div>

					<div class="col-12 mt-4 text-end">
						<a href="gestion_contratos.php" class="btn btn-secondary me-2">Regresar</a>
						<button type="submit" class="btn btn-primary">Guardar Cambios</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</main>

<?php require_once '../includes/layout_foot.php'; ?>

<script src="<?php echo $path_to_root; ?>js/jquery.min.js"></script>

<script type="text/javascript">
	// (Scripts previos sin cambios)
	let deleteButtons = document.querySelectorAll('.delete');

	for (let i = 0; i < deleteButtons.length; i++) {
		deleteButtons[i].addEventListener('click', function () {
			var file = this.getAttribute('data');
			var dataString = 'file=' + file;

			fetch('del_file.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				body: dataString
			})
				.then(function (response) {
					if (response.ok) {
						location.reload();
					}
				})
				.catch(function (error) {
					console.error('Error:', error);
				});
		});
	}
</script>

<script>
	$(document).ready(function () {

		// 🔑 OBTENER LOS VALORES INICIALES DE PHP (UBICACIÓN)
		var parroquiaSeleccionadaId = "<?php echo $parroquia_seleccionada; ?>";
		var municipioInicialId = "<?php echo $municipio_seleccionado; ?>";

		// 🔑 NUEVOS: OBTENER LOS VALORES INICIALES DE PHP (RED)
		var oltInicialId = "<?php echo $olt_seleccionado; ?>";
		var ponSeleccionadoId = "<?php echo $pon_seleccionado; ?>";

		// =========================================================================
		// 2. FUNCIONES DE CARGA EN CASCADA
		// =========================================================================

		let ubicacionesData = [];
		const municipioSeleccionado = "<?php echo $municipio_seleccionado; ?>";
		const parroquiaSeleccionada = "<?php echo $parroquia_seleccionada; ?>";

		// 1. Cargar JSON completo al inicio
		$.get('api_ubicaciones.php', function (data) {
			ubicacionesData = data;
			let mOptions = '<option value="">-- Seleccione un Municipio --</option>';

			ubicacionesData.forEach(function (item) {
				const isSelected = (item.municipio === municipioSeleccionado) ? 'selected' : '';
				mOptions += `<option value="${item.municipio}" ${isSelected}>${item.municipio}</option>`;
			});
			$('#municipio').html(mOptions);

			// Si hay municipio seleccionado, cargar sus parroquias
			if (municipioSeleccionado) {
				actualizarParroquias(municipioSeleccionado, parroquiaSeleccionada);
			}
		});

		// Manejar cambio de Municipio
		$('#municipio').on('change', function () {
			actualizarParroquias($(this).val());
		});

		function actualizarParroquias(municipioNombre, parroquiaNombreToSelect = '') {
			const $pSelect = $('#parroquia');
			if (!municipioNombre) {
				$pSelect.html('<option value="">-- Seleccione un Municipio primero --</option>').prop('disabled', true);
				return;
			}

			const muni = ubicacionesData.find(m => m.municipio === municipioNombre);
			if (muni && muni.parroquias) {
				let pOptions = '<option value="">-- Seleccione una Parroquia --</option>';
				muni.parroquias.forEach(function (p) {
					const pNombre = typeof p === 'object' ? p.nombre : p;
					const isSelected = (pNombre === parroquiaNombreToSelect) ? 'selected' : '';
					pOptions += `<option value="${pNombre}" ${isSelected}>${pNombre}</option>`;
				});
				$pSelect.html(pOptions).prop('disabled', false);
			} else {
				$pSelect.html('<option value="">Sin parroquias registradas</option>').prop('disabled', true);
			}
		}

		// 🔑 NUEVA: Función para cargar PONs (OLT -> PON)
		function cargarPonsIniciales(oltID, ponIDaSeleccionar) {
			var $ponSelect = $('#id_pon');

			$ponSelect.html('<option value="">Cargando PONs...</option>').prop('disabled', true);

			if (oltID) {
				// Realizar la llamada AJAX al endpoint que filtra
				$.ajax({
					url: 'gets_pon_by_olt.php', // *** IMPORTANTE: NECESITAS ESTE ARCHIVO ***
					type: 'GET',
					data: { id_olt: oltID },
					dataType: 'json',
					success: function (response) {
						$ponSelect.empty();

						if (!response.error && response.pons && response.pons.length > 0) {
							$ponSelect.append('<option value="">-- Seleccione un PON --</option>');

							$.each(response.pons, function (index, pon) {
								// Lógica de pre-selección
								var selectedAttr = (pon.id_pon == ponIDaSeleccionar) ? 'selected' : '';
								$ponSelect.append('<option value="' + pon.id_pon + '" ' + selectedAttr + '>' + pon.nombre_pon + '</option>');
							});
							$ponSelect.prop('disabled', false);
						} else {
							var msg = response.message || 'No se encontraron PONs.';
							$ponSelect.append('<option value="" disabled>' + msg + '</option>');
							$ponSelect.prop('disabled', true);
						}
					},
					error: function () {
						$ponSelect.html('<option value="" disabled>Error de comunicación al cargar PONs.</option>');
						$ponSelect.prop('disabled', true);
					}
				});
			} else {
				$ponSelect.html('<option value="">-- Seleccione una OLT primero --</option>');
			}
		}


		// =========================================================================
		// 3. EVENTOS y LLAMADAS INICIALES
		// =========================================================================

		// Al cargar la página, iniciar la carga de PONs pre-seleccionados
		if (oltInicialId) {
			cargarPonsIniciales(oltInicialId, ponSeleccionadoId);
		}

		// (Lógica de municipios manejada arriba en el bloque GET inicial)

		// 🔑 NUEVO: Manejar cambio de OLT
		$('#id_olt').on('change', function () {
			var idOlt = $(this).val();
			// Al cambiar OLT, no hay PON pre-seleccionado, pasamos 'null'
			cargarPonsIniciales(idOlt, null);
		});

		// 🔑 NUEVO: Manejar visibilidad de campos técnicos
		$('#tipo_conexion').on('change', function () {
			var tipo = $(this).val();
			// Ocultar todos primero y desactivar para evitar errores de validación en campos ocultos
			$('.campo-ftth, .campo-radio').hide();
			$('.campo-ftth :input, .campo-radio :input').prop('disabled', true);

			if (tipo && tipo.includes('FTTH')) {
				$('.campo-ftth').show();
				$('.campo-ftth :input').prop('disabled', false);
				// Si la IP está vacía, restaurar el prefijo por defecto
				if ($('#ip_onu').val() === '') {
					$('#ip_onu').val('192.168.');
				}
			} else if (tipo && tipo.includes('RADIO')) {
				$('.campo-radio').show();
				$('.campo-radio :input').prop('disabled', false);
				// Limpiar IP de la ONU si solo tiene el prefijo por defecto para evitar errores de pattern
				if ($('#ip_onu').val() === '192.168.') {
					$('#ip_onu').val('');
				}
			}
		}).trigger('change');

		// ======================================================
		// LÓGICA DE PREFIJO DE CÉDULA/RIF
		// ======================================================

		const $tipoCedula = $('#tipo_cedula');
		const $cedulaInput = $('#cedula');

		function actualizarPrefijo() {
			const prefijo = $tipoCedula.val();
			let currentVal = $cedulaInput.val();

			if (currentVal === '') {
				$cedulaInput.val(prefijo);
				return;
			}

			const soloNumeros = currentVal.replace(/[^0-9]/g, '');
			$cedulaInput.val(prefijo + soloNumeros);
		}

		$tipoCedula.on('change', function() {
			actualizarPrefijo();
		});

		$cedulaInput.on('input', function() {
			const prefijo = $tipoCedula.val();
			let val = $(this).val();

			if (!val.startsWith(prefijo)) {
				const soloNumeros = val.replace(/[^0-9]/g, '');
				$(this).val(prefijo + soloNumeros);
			} else {
				const rest = val.substring(prefijo.length).replace(/[^0-9]/g, '');
				$(this).val(prefijo + rest);
			}
		});

		// Inicialización ya manejada por PHP en el valor del input y select

		// VERIFICACIÓN DE CÉDULA DUPLICADA (CON EXCLUSIÓN DE ID ACTUAL)
		$cedulaInput.on('blur', function () {
			const val = $(this).val().trim();
			const prefijo = $tipoCedula.val();
			const currentId = $('#id').val();
			// Extraer solo los números
			const cedulaNum = val.startsWith(prefijo) ? val.substring(prefijo.length) : val.replace(/[^0-9]/g, '');

			if (cedulaNum.length > 0) {
				$.ajax({
					url: 'check_cedula_api.php',
					type: 'GET',
					data: {
						cedula: cedulaNum,
						tipo_cedula: prefijo,
						exclude_id: currentId
					},
					dataType: 'json',
					success: function (response) {
						if (response.exists) {
							Swal.fire({
								title: '¡Cédula Detectada!',
								html: `Ya existe <b>otro</b> contrato registrado con esta cédula (<b>${prefijo}-${cedulaNum}</b>) a nombre de:<br><b>${response.nombre_completo}</b>`,
								icon: 'warning',
								confirmButtonColor: '#3085d6',
								confirmButtonText: 'Entendido'
							});
						}
					},
					error: function (xhr, status, error) {
						console.error("Error al verificar cédula:", error);
					}
				});
			}
		});

	});
</script>