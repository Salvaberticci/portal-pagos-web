<?php
// Iniciar buffer de salida al principio absoluto
ob_start();

/**
 * Script para insertar nuevos datos de registro
 *
 * Este script recibe los nuevos datos del registro a través del método POST
 * y realiza la inserción en la base de datos. También permite la carga de archivos adjuntos.
 *
 *
 *
 */
// Conexión a la base de datos
require '../conexion.php';

// Manejo de errores para respuestas JSON
function jsonErrorHandler($errno, $errstr, $errfile, $errline)
{
    // Limpiar buffer
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => "PHP Error: $errstr en $errfile:$errline"]);
    exit;
}
set_error_handler("jsonErrorHandler");

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        if (ob_get_length())
            ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => "Fatal Error: {$error['message']} en {$error['file']}:{$error['line']}"]);
    }
});


// 1. CAPTURA Y SANEO DE DATOS
$cedula = trim($conn->real_escape_string($_POST['cedula'] ?? ''));
$nombre_completo = trim($conn->real_escape_string($_POST['nombre_completo'] ?? ''));
$telefono = trim($conn->real_escape_string($_POST['telefono'] ?? ''));
$correo = trim($conn->real_escape_string($_POST['correo'] ?? ''));
// Para claves foráneas, si vienen vacías deben ser NULL, no string vacío ''
$municipio_texto = !empty($_POST['id_municipio']) ? $conn->real_escape_string($_POST['id_municipio']) : '';
$parroquia_texto = !empty($_POST['id_parroquia']) ? $conn->real_escape_string($_POST['id_parroquia']) : '';
$id_plan = !empty($_POST['id_plan']) ? $conn->real_escape_string($_POST['id_plan']) : null;
$vendedor_texto = $conn->real_escape_string($_POST['vendedor_texto'] ?? '');
$direccion = $conn->real_escape_string($_POST['direccion'] ?? '');
$fecha_instalacion = $conn->real_escape_string($_POST['fecha_instalacion'] ?? '');
$ident_caja_nap = $conn->real_escape_string($_POST['ident_caja_nap'] ?? '');
$puerto_nap = $conn->real_escape_string($_POST['puerto_nap'] ?? '');
$num_presinto_odn = $conn->real_escape_string($_POST['num_presinto_odn'] ?? '');
$id_olt = !empty($_POST['id_olt']) ? $conn->real_escape_string($_POST['id_olt']) : null;
$id_pon = !empty($_POST['id_pon']) ? $conn->real_escape_string($_POST['id_pon']) : null;
$estado = 'ACTIVO'; // Estado inicial por defecto
$monto_plan = floatval($_POST['monto_plan'] ?? 0);

// NUEVOS CAMPOS ADMINISTRATIVOS Y TÉCNICOS
$telefono_secundario = trim($conn->real_escape_string($_POST['telefono_secundario'] ?? ''));
$correo_adicional = trim($conn->real_escape_string($_POST['correo_adicional'] ?? ''));
$observaciones = $conn->real_escape_string($_POST['observaciones'] ?? '');

$tipo_instalacion = $conn->real_escape_string($_POST['tipo_instalacion'] ?? '');
$monto_instalacion = floatval($_POST['monto_instalacion'] ?? 0);
$gastos_adicionales = floatval($_POST['gastos_adicionales'] ?? 0);
$monto_pagar = floatval($_POST['monto_pagar'] ?? 0);
$monto_pagado = floatval($_POST['monto_pagado'] ?? 0);

$medio_pago = $conn->real_escape_string($_POST['medio_pago'] ?? '');
$moneda_pago = $conn->real_escape_string($_POST['moneda_pago'] ?? 'USD');

// PRORRATEO LOGIC
$incluye_prorrateo = isset($_POST['incluye_prorrateo']) && $_POST['incluye_prorrateo'] === 'SI';

if ($incluye_prorrateo) {
    $plan_prorrateo_nombre = $conn->real_escape_string($_POST['plan_prorrateo_nombre'] ?? '');
    $dias_prorrateo = intval($_POST['dias_prorrateo'] ?? 0);
    // Recalcular backend prorrateo just in case or trust frontend mapping
    $monto_prorrateo_usd = floatval($_POST['monto_prorrateo_usd'] ?? 0);
} else {
    // Force to 0 if switch is off
    $plan_prorrateo_nombre = null;
    $dias_prorrateo = 0;
    $monto_prorrateo_usd = 0;
}

// DATOS TÉCNICOS ESPECÍFICOS
$tipo_conexion = $conn->real_escape_string($_POST['tipo_conexion'] ?? '');
$mac_onu = strtoupper(trim($conn->real_escape_string($_POST['mac_onu'] ?? '')));
$ip_onu = trim($conn->real_escape_string($_POST['ip_onu'] ?? ''));
$ip_servicio = trim($conn->real_escape_string($_POST['ip_servicio'] ?? ''));
$punto_acceso = $conn->real_escape_string($_POST['punto_acceso'] ?? '');
$valor_conexion_dbm = $conn->real_escape_string($_POST['valor_conexion_dbm'] ?? '');

$nap_tx_power = isset($_POST['nap_tx_power']) ? $conn->real_escape_string($_POST['nap_tx_power']) : '';
$onu_rx_power = isset($_POST['onu_rx_power']) ? $conn->real_escape_string($_POST['onu_rx_power']) : '';
$distancia_drop = isset($_POST['distancia_drop']) ? $conn->real_escape_string($_POST['distancia_drop']) : '';
$evidencia_fibra = isset($_POST['evidencia_fibra']) ? $conn->real_escape_string($_POST['evidencia_fibra']) : '';
$evidencia_fibra = isset($_POST['evidencia_fibra']) ? $conn->real_escape_string($_POST['evidencia_fibra']) : '';

// PROCESAR FOTO EVIDENCIA
$evidencia_foto = null;
if (isset($_FILES['evidencia_foto']) && $_FILES['evidencia_foto']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['evidencia_foto']['name'];
    $filetype = $_FILES['evidencia_foto']['type'];
    $filesize = $_FILES['evidencia_foto']['size'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $new_filename = 'evidencia_' . uniqid() . '.' . $ext;
        $upload_dir = '../../uploads/contratos/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (move_uploaded_file($_FILES['evidencia_foto']['tmp_name'], $upload_dir . $new_filename)) {
            $evidencia_foto = 'uploads/contratos/' . $new_filename;
        }
    }
}

// Manejar Instaladores por tipo
$instalador_ftth = isset($_POST['instalador_ftth']) ? $conn->real_escape_string($_POST['instalador_ftth']) : '';
$instalador_radio = isset($_POST['instalador_radio']) ? $conn->real_escape_string($_POST['instalador_radio']) : '';

// Para compatibilidad con la columna 'instalador' existente (FTTH)
$instalador_val = ($tipo_conexion === 'FTTH') ? $instalador_ftth : '';
// Nueva columna para Radio
$instalador_c = ($tipo_conexion === 'RADIO') ? $instalador_radio : '';

// --- FUNCIÓN PARA GUARDAR FIRMAS (Base64 a PNG) ---
function saveSignature($base64_string, $prefix)
{
    if (empty($base64_string))
        return null;
    $data = explode(',', $base64_string);
    if (count($data) < 2)
        return null;
    $imgData = base64_decode($data[1]);
    $fileName = $prefix . '_' . uniqid() . '.png';
    $uploadDir = '../../uploads/firmas/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (file_put_contents($uploadDir . $fileName, $imgData)) {
        return $fileName;
    }
    return null;
}

// Procesar Firmas y Documento
$firma_cliente_b64 = $_POST['firma_cliente_data'] ?? '';
$firma_tecnico_b64 = $_POST['firma_tecnico_data'] ?? '';
$firma_cliente = saveSignature($firma_cliente_b64, 'cliente');
$firma_tecnico = saveSignature($firma_tecnico_b64, 'tecnico');

// PROCESAR FOTO DOCUMENTO
$evidencia_documento = null;
if (isset($_FILES['evidencia_documento_file']) && $_FILES['evidencia_documento_file']['error'] == 0) {
    if (in_array(strtolower(pathinfo($_FILES['evidencia_documento_file']['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
        $new_filename_doc = 'doc_' . uniqid() . '.' . strtolower(pathinfo($_FILES['evidencia_documento_file']['name'], PATHINFO_EXTENSION));
        $upload_dir = '../../uploads/contratos/';
        if (!file_exists($upload_dir))
            mkdir($upload_dir, 0755, true);
        if (move_uploaded_file($_FILES['evidencia_documento_file']['tmp_name'], $upload_dir . $new_filename_doc)) {
            $evidencia_documento = 'uploads/contratos/' . $new_filename_doc;
        }
    }
}

$generate_link = isset($_POST['generate_link']) && $_POST['generate_link'] === '1';

if ($generate_link) {
    $token_firma = bin2hex(random_bytes(32));
    $estado_firma = 'PENDIENTE';
}

// Variable para manejar mensajes de error específicos
$error_mensaje = null;
$resultado = false; // Inicializamos a falso por si hay errores de validación

// =========================================================================
// 1.2 VALIDACIÓN DE CAMPOS OBLIGATORIOS (Backend Enforcement)
// =========================================================================
// La IP solo es obligatoria para conexiones de tipo RADIO. Para FTTH se usa ip_onu o se asigna despues.
if (empty($cedula))
    $error_mensaje = "La Cédula / RIF es obligatoria.";
if (empty($nombre_completo))
    $error_mensaje = "El Nombre Completo es obligatorio.";
if (empty($monto_instalacion) && $monto_instalacion !== 0.0)
    $error_mensaje = "El Monto de Instalación es obligatorio.";

// Validar formato de Cédula (V/J/E/G/P + números)
if (!empty($cedula) && !preg_match('/^[VJEGP]\d+$/i', $cedula)) {
    $error_mensaje = "Formato de Cédula inválido. Debe empezar con V, J, E, G o P seguido de números.";
}

// Validar formato de IP
if (!empty($ip_onu) && $ip_onu !== '192.168.' && !preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $ip_onu)) {
    $error_mensaje = "Formato de IP ONU inválido.";
}

// Validar Teléfonos
if (!empty($telefono) && !preg_match('/^[0-9-+\s]{7,15}$/', $telefono)) {
    $error_mensaje = "Formato de Teléfono principal inválido.";
}
if (!empty($telefono_secundario) && !preg_match('/^[0-9-+\s]{7,15}$/', $telefono_secundario)) {
    $error_mensaje = "Formato de Teléfono secundario inválido.";
}

// Validar Correos
if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $error_mensaje = "Formato de Correo principal inválido.";
}
if (!empty($correo_adicional) && !filter_var($correo_adicional, FILTER_VALIDATE_EMAIL)) {
    $error_mensaje = "Formato de Correo adicional inválido.";
}

// Validar MAC / Serial
if (!empty($mac_onu) && !preg_match('/^[A-Za-z0-9:.-]{8,20}$/', $mac_onu)) {
    $error_mensaje = "Formato de MAC o Serial ONU inválido.";
}

// Validar Potencias (dBm)
if (!empty($nap_tx_power) && !preg_match('/^-?\d+(\.\d+)?$/', $nap_tx_power)) {
    $error_mensaje = "El valor NAP TX Power debe ser numérico.";
}
if (!empty($onu_rx_power) && !preg_match('/^-?\d+(\.\d+)?$/', $onu_rx_power)) {
    $error_mensaje = "El valor ONU RX Power debe ser numérico.";
}
if (!empty($valor_conexion_dbm) && !preg_match('/^-?\d+(\.\d+)?$/', $valor_conexion_dbm)) {
    $error_mensaje = "El valor de Conexión (dBm) debe ser numérico.";
}

if ($error_mensaje) {
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'msg' => $error_mensaje]);
    exit;
} else {
    // Continuar si no hay errores previos

    // Función auxiliar para validar existencia genérica
    function validarFK($conn, $tabla, $columna, $valor)
    {
        if (empty($valor))
            return null;
        $sql = "SELECT $columna FROM $tabla WHERE $columna = '$valor' LIMIT 1";
        $res = $conn->query($sql);
        return ($res && $res->num_rows > 0) ? $valor : null;
    }

    function getMunicipioId($conn, $nombre)
    {
        $nombre = trim($conn->real_escape_string($nombre));
        if (empty($nombre))
            return null;
        $sql = "SELECT id_municipio FROM municipio WHERE nombre_municipio = '$nombre' LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0)
            return $res->fetch_assoc()['id_municipio'];
        if ($conn->query("INSERT INTO municipio (nombre_municipio) VALUES ('$nombre')"))
            return $conn->insert_id;
        return null;
    }

    function getParroquiaId($conn, $nombre, $id_municipio)
    {
        $nombre = trim($conn->real_escape_string($nombre));
        if (empty($nombre) || empty($id_municipio))
            return null;
        $sql = "SELECT id_parroquia FROM parroquia WHERE nombre_parroquia = '$nombre' LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0)
            return $res->fetch_assoc()['id_parroquia'];
        if ($conn->query("INSERT INTO parroquia (nombre_parroquia, id_municipio) VALUES ('$nombre', $id_municipio)"))
            return $conn->insert_id;
        return null;
    }



    // Resolviendo IDs de Ubicaciones por Nombre para mantener integridad en tablas antiguas si se desea
    $id_municipio = getMunicipioId($conn, $municipio_texto);
    $id_parroquia = getParroquiaId($conn, $parroquia_texto, $id_municipio);

    // Validar Plan
    $id_plan = validarFK($conn, 'planes', 'id_plan', $id_plan);
    // Validar OLT
    $id_olt = validarFK($conn, 'olt', 'id_olt', $id_olt);
    // Validar PON
    $id_pon = validarFK($conn, 'pon', 'id_pon', $id_pon);


    // =========================================================================
// 2. <<<< VALIDACIÓN AGREGADA >>>>: Verificar si la IP ya existe 
// =========================================================================
    // También validamos ip_onu si no esta vacía
    if (!$error_mensaje && !empty($ip_onu)) {
        $sql_check_ip_onu = "SELECT id FROM contratos WHERE ip_onu = ? LIMIT 1";
        $stmt_check_ip_onu = $conn->prepare($sql_check_ip_onu);
        $stmt_check_ip_onu->bind_param("s", $ip_onu);
        $stmt_check_ip_onu->execute();
        $stmt_check_ip_onu->store_result();

        if ($stmt_check_ip_onu->num_rows > 0) {
            $error_mensaje = "Error de Validación: La dirección IP de la ONU <strong>'{$ip_onu}'</strong> ya se encuentra registrada en otro contrato.";
        }
        $stmt_check_ip_onu->close();
    }

    if ($error_mensaje) {
        if (ob_get_length())
            ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'msg' => strip_tags($error_mensaje)]);
        exit;
    } else {
        // La IP es única, podemos continuar con la inserción del contrato.

        // 3. INSERCIÓN EN LA TABLA DE CONTRATOS
        $sql = "INSERT INTO contratos (
        cedula, nombre_completo, telefono, correo, 
        id_municipio, id_parroquia, municipio_texto, parroquia_texto, id_plan, monto_plan, vendedor_texto,
        direccion, fecha_instalacion, estado, ident_caja_nap, puerto_nap, 
        num_presinto_odn, id_olt, id_pon, tipo_instalacion, monto_instalacion, 
        gastos_adicionales, monto_pagar, monto_pagado, instalador, instalador_c,
        telefono_secundario, correo_adicional, medio_pago, moneda_pago, plan_prorrateo_nombre, dias_prorrateo,
        monto_prorrateo_usd, observaciones, tipo_conexion, mac_onu, ip_onu,
        nap_tx_power, onu_rx_power, distancia_drop, punto_acceso, valor_conexion_dbm,
        evidencia_fibra, evidencia_foto, evidencia_documento, firma_cliente, firma_tecnico,
        token_firma, estado_firma
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, ?, ?, ?
    )";

        $stmt = $conn->prepare($sql);

        // 49 params: ssss=cedula..correo | ii=id_mun,id_par | ss=texto,texto | i=id_plan | d=monto_plan | s=vendedor
        // sssssss=dir..presinto | ii=id_olt,id_pon | s=tipo_instal | dddd=montos | sssssss=instalador..plan_prorra
        // i=dias | d=monto_prorra | sssssssssssssssss=observ..estado_firma (17 s from pos 33 to 49)
        $types = "ssssiissidsssssssiisddddssssssidsssssssssssssssss";

        $ip_val = (!empty($ip_onu) ? $ip_onu : $ip_servicio);
        $stmt->bind_param(
            $types,
            $cedula,
            $nombre_completo,
            $telefono,
            $correo,
            $id_municipio,
            $id_parroquia,
            $municipio_texto,
            $parroquia_texto,
            $id_plan,
            $monto_plan,
            $vendedor_texto,
            $direccion,
            $fecha_instalacion,
            $estado,
            $ident_caja_nap,
            $puerto_nap,
            $num_presinto_odn,
            $id_olt,
            $id_pon,
            $tipo_instalacion,
            $monto_instalacion,
            $gastos_adicionales,
            $monto_pagar,
            $monto_pagado,
            $instalador_val,
            $instalador_c,
            $telefono_secundario,
            $correo_adicional,
            $medio_pago,
            $moneda_pago,
            $plan_prorrateo_nombre,
            $dias_prorrateo,
            $monto_prorrateo_usd,
            $observaciones,
            $tipo_conexion,
            $mac_onu,
            $ip_val,
            $nap_tx_power,
            $onu_rx_power,
            $distancia_drop,
            $punto_acceso,
            $valor_conexion_dbm,
            $evidencia_fibra,
            $evidencia_foto,
            $evidencia_documento,
            $firma_cliente,
            $firma_tecnico,
            $token_firma,
            $estado_firma
        );

        $resultado = $stmt->execute();
        $id_contrato = $conn->insert_id; // Obtiene el ID del contrato recién insertado
        $stmt->close();

        // 4. RETORNO JSON SI ES EXITOSO
        if (ob_get_length())
            ob_clean();
        header('Content-Type: application/json');

        if (!$resultado) {
            echo json_encode(['status' => 'error', 'msg' => 'Error al guardar en BD: ' . $conn->error]);
            exit;
        }

        // ⚠️ REDIRECCIÓN A GENERAR EL PDF
        /* if ($resultado) { // Si el contrato se guardó correctamente
             $pdf_url = "../reportes_pdf/generar_contrato_pdf.php?id_contrato=" . $id_contrato;
             $conn->close(); // Cerrar conexión antes de la redirección
             header("Location: " . $pdf_url); // Envía la cabecera de redirección
             exit(); // Detiene el script para asegurar la redirección
         }*/

        // 5. GENERACIÓN DE LA PRIMERA CUENTA POR COBRAR (Solo si el contrato fue exitoso)
        if ($resultado && $id_contrato > 0) {

            // Obtener el monto total del plan
            $sql_monto = "SELECT monto FROM planes WHERE id_plan = ? LIMIT 1";
            $stmt_monto = $conn->prepare($sql_monto);
            $stmt_monto->bind_param("i", $id_plan);
            $stmt_monto->execute();
            $result_monto = $stmt_monto->get_result();

            if ($result_monto->num_rows > 0) {
                $row_monto = $result_monto->fetch_assoc();
                $monto_total = $row_monto['monto'];

                // Define fechas para la factura
                $fecha_emision = $fecha_instalacion; // La fecha de instalación es la de emisión
                $fecha_vencimiento = date('Y-m-d', strtotime($fecha_emision . ' + 30 days'));

                // Inserción en la tabla de cuentas por cobrar (cxc)
                $sql_cobro = "INSERT INTO cuentas_por_cobrar (id_contrato, fecha_emision, fecha_vencimiento, monto_total)
            VALUES (?, ?, ?, ?)";

                $stmt_cobro = $conn->prepare($sql_cobro);
                $stmt_cobro->bind_param(
                    "issd",
                    $id_contrato,
                    $fecha_emision,
                    $fecha_vencimiento,
                    $monto_total
                );

                if (!$stmt_cobro->execute()) {
                    // El contrato se guardó ($resultado sigue siendo true), pero la factura falló.
                    $error_mensaje = "ADVERTENCIA: Contrato guardado, pero falló la generación de la primera factura. (No se encontró el plan o error interno)";
                } else {
                    // Insertar en historial con concepto amigable para que NO aparezca como "Mensualidad" genérica
                    $id_cobro_nuevo = $conn->insert_id;
                    $justif_contrato = "[REGISTRO_CONTRATO] Primera mensualidad del contrato";
                    $stmt_hist = $conn->prepare("INSERT INTO cobros_manuales_historial (id_cobro_cxc, id_contrato, autorizado_por, justificacion, monto_cargado) VALUES (?, ?, 'SISTEMA', ?, ?)");
                    $stmt_hist->bind_param("iisd", $id_cobro_nuevo, $id_contrato, $justif_contrato, $monto_total);
                    $stmt_hist->execute();
                    $stmt_hist->close();
                }
                $stmt_cobro->close();
            } else {
                // El contrato se guardó ($resultado sigue siendo true), pero no se encontró el plan.
                $error_mensaje = "ADVERTENCIA: Contrato guardado, pero no se pudo generar la factura: Plan de servicio no encontrado.";
            }

            $stmt_monto->close();
        }

        if ($resultado && $id_contrato > 0) {
            $saldo_pendiente = round($monto_pagar - $monto_pagado, 2);

            if ($saldo_pendiente > 0) {
                $sql_deudor = "INSERT INTO clientes_deudores (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado) 
                          VALUES (?, ?, ?, ?, 'PENDIENTE')";
                $stmt_deudor = $conn->prepare($sql_deudor);
                $stmt_deudor->bind_param("iddd", $id_contrato, $monto_pagar, $monto_pagado, $saldo_pendiente);
                $stmt_deudor->execute();
                $stmt_deudor->close();
            }
        }
    }
}

// Ya hemos respondido JSON arriba si es exitoso o error critico. 
// Por seguridad si algo se escapó, respondemos JSON aquí también.
if (ob_get_length())
    ob_clean();
if (!headers_sent())
    header('Content-Type: application/json');
if ($resultado) {
    $pdf_url = "../reportes_pdf/generar_contrato_pdf.php?id_contrato=" . $id_contrato;
    echo json_encode([
        'status' => 'success',
        'msg' => 'Contrato registrado correctamente.',
        'id' => $id_contrato,
        'pdf_url' => $pdf_url
    ]);
} else if ($error_mensaje) {
    echo json_encode(['status' => 'error', 'msg' => $error_mensaje]);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Error desconocido al procesar.']);
}

$conn->close();
?>