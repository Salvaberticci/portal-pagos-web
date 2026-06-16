<?php
/**
 * Ajax endpoint for updating a contract. Returns JSON {success, message}.
 */
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();
require '../conexion.php';

// --- Capture & sanitize ---
$id = intval($_POST['id'] ?? 0);
$cedula = $conn->real_escape_string($_POST['cedula'] ?? '');
$nombre_completo = $conn->real_escape_string($_POST['nombre_completo'] ?? '');
$telefono = $conn->real_escape_string($_POST['telefono'] ?? '');
$correo = $conn->real_escape_string($_POST['correo'] ?? '');
$telefono_sec = $conn->real_escape_string($_POST['telefono_secundario'] ?? '');
$correo_adicional = $conn->real_escape_string($_POST['correo_adicional'] ?? '');
$municipio_texto = !empty($_POST['id_municipio']) ? $conn->real_escape_string($_POST['id_municipio']) : '';
$parroquia_texto = !empty($_POST['id_parroquia']) ? $conn->real_escape_string($_POST['id_parroquia']) : '';
$comunidad_texto = !empty($_POST['id_comunidad']) ? $conn->real_escape_string($_POST['id_comunidad']) : '';

$id_plan = intval($_POST['id_plan'] ?? 0) ?: 'NULL';
$vendedor_texto = $conn->real_escape_string($_POST['vendedor_texto'] ?? '');
$sae_plus = $conn->real_escape_string($_POST['sae_plus'] ?? '');
$id_olt = intval($_POST['id_olt'] ?? 0) ?: 'NULL';
$id_pon = intval($_POST['id_pon'] ?? 0) ?: 'NULL';

// Financials and others... (kept from before)
$direccion = $conn->real_escape_string($_POST['direccion'] ?? '');
$fecha_inst = $conn->real_escape_string($_POST['fecha_instalacion'] ?? '');
$estado = $conn->real_escape_string($_POST['estado'] ?? '');
$ident_caja_nap = trim($conn->real_escape_string($_POST['ident_caja_nap'] ?? ''));
$puerto_nap = trim($conn->real_escape_string($_POST['puerto_nap'] ?? ''));
$num_presinto_odn = trim($conn->real_escape_string($_POST['num_presinto_odn'] ?? ''));
$tipo_conexion = trim($conn->real_escape_string($_POST['tipo_conexion'] ?? ''));
$mac_onu = strtoupper(trim($conn->real_escape_string($_POST['mac_onu'] ?? '')));
$ip_onu = trim($conn->real_escape_string($_POST['ip_onu'] ?? ''));
$nap_tx_power = trim($conn->real_escape_string($_POST['nap_tx_power'] ?? ''));
$onu_rx_power = trim($conn->real_escape_string($_POST['onu_rx_power'] ?? ''));
$distancia_drop = trim($conn->real_escape_string($_POST['distancia_drop'] ?? ''));
$punto_acceso = trim($conn->real_escape_string($_POST['punto_acceso'] ?? ''));
$ip_radio = trim($conn->real_escape_string($_POST['ip_servicio'] ?? ''));
$valor_conexion = trim($conn->real_escape_string($_POST['valor_conexion_dbm'] ?? ''));
$observaciones = $conn->real_escape_string($_POST['observaciones'] ?? '');
$plan_prorrateo_nombre = $conn->real_escape_string($_POST['plan_prorrateo_nombre'] ?? '');
$dias_prorrateo = intval($_POST['dias_prorrateo'] ?? 0);
$monto_prorrateo_usd = floatval($_POST['monto_prorrateo_usd'] ?? 0);
$monto_plan = floatval($_POST['monto_plan'] ?? 0);

// FINANCIAL FIELDS
$tipo_instalacion = $conn->real_escape_string($_POST['tipo_instalacion'] ?? '');
$monto_instalacion = floatval($_POST['monto_instalacion'] ?? 0);
$gastos_adicionales = floatval($_POST['gastos_adicionales'] ?? 0);
$monto_pagar = floatval($_POST['monto_pagar'] ?? 0);
$monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
$medio_pago = $conn->real_escape_string($_POST['medio_pago'] ?? '');
$moneda_pago = $conn->real_escape_string($_POST['moneda_pago'] ?? 'USD');

// INSTALADOR(ES)
$instalador_ftth = $conn->real_escape_string($_POST['instalador_ftth'] ?? '');
$instalador_radio = $conn->real_escape_string($_POST['instalador_radio'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID de contrato inválido.']);
    exit;
}

// 2. <<<< VALIDACIÓN DE IP DUPLICADA >>>>
$ip_to_check = !empty($ip_onu) ? $ip_onu : $ip_radio;
if (!empty($ip_to_check)) {
    $stmt_ip_onu = $conn->prepare("SELECT id FROM contratos WHERE ip_onu = ? AND id != ? LIMIT 1");
    $stmt_ip_onu->bind_param("si", $ip_to_check, $id);
    $stmt_ip_onu->execute();
    if ($stmt_ip_onu->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => "La dirección IP '{$ip_to_check}' ya está registrada en otro contrato."]);
        exit;
    }
    $stmt_ip_onu->close();
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

function getComunidadId($conn, $nombre, $id_parroquia)
{
    $nombre = trim($conn->real_escape_string($nombre));
    if (empty($nombre) || empty($id_parroquia))
        return null;
    $sql = "SELECT id_comunidad FROM comunidad WHERE nombre_comunidad = '$nombre' AND id_parroquia = $id_parroquia LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0)
        return $res->fetch_assoc()['id_comunidad'];
    if ($conn->query("INSERT INTO comunidad (nombre_comunidad, id_parroquia) VALUES ('$nombre', $id_parroquia)"))
        return $conn->insert_id;
    return null;
}

$id_municipio = getMunicipioId($conn, $municipio_texto);
$id_parroquia = getParroquiaId($conn, $parroquia_texto, $id_municipio);
$id_comunidad = getComunidadId($conn, $comunidad_texto, $id_parroquia);

$mun_val = $id_municipio ?: 'NULL';
$par_val = $id_parroquia ?: 'NULL';
$com_val = $id_comunidad ?: 'NULL';

// --- PHOTO ---
function savePhoto($file, $prefix = 'evidencia') {
    if (empty($file) || $file['error'] != 0) return null;
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = $prefix . '_' . uniqid() . '.' . $ext;
    $uploadDir = '../../uploads/contratos/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        return 'uploads/contratos/' . $fileName;
    }
    return null;
}

$evidencia_foto_path = savePhoto($_FILES['evidencia_foto'] ?? null, 'evidencia');
$sql_foto = $evidencia_foto_path ? ", evidencia_foto = '$evidencia_foto_path'" : "";

$evidencia_documento_path = savePhoto($_FILES['evidencia_documento_file'] ?? null, 'doc');
$sql_documento = $evidencia_documento_path ? ", evidencia_documento = '$evidencia_documento_path'" : "";

$evidencia_fibra_path = savePhoto($_FILES['evidencia_fibra_file'] ?? null, 'fibra');
$sql_fibra_file = $evidencia_fibra_path ? ", evidencia_fibra = '$evidencia_fibra_path'" : "";

// Si no se subió archivo de fibra, pero se envió texto manual
$sql_fibra_text = "";
if (!$evidencia_fibra_path && isset($_POST['evidencia_fibra'])) {
    $evid_fibra_text = $conn->real_escape_string($_POST['evidencia_fibra']);
    $sql_fibra_text = ", evidencia_fibra = '$evid_fibra_text'";
}

// --- FIRMAS ---
$firma_cliente_data = $_POST['firma_cliente_data'] ?? '';
$firma_tecnico_data = $_POST['firma_tecnico_data'] ?? '';

$sql_firma_cliente = "";
$sql_firma_tecnico = "";

$upload_dir = "../../uploads/firmas/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!empty($firma_cliente_data) && strpos($firma_cliente_data, 'base64') !== false) {
    if (strpos($firma_cliente_data, ',') !== false) {
        $data_part = explode(',', $firma_cliente_data)[1];
    } else {
        $data_part = $firma_cliente_data;
    }
    $decoded = base64_decode($data_part);
    $filename = "firma_cli_" . $id . "_" . time() . ".png";
    $filepath = $upload_dir . $filename;
    if (file_put_contents($filepath, $decoded)) {
        $sql_firma_cliente = ", firma_cliente = '$filename'";
    }
}

if (!empty($firma_tecnico_data) && strpos($firma_tecnico_data, 'base64') !== false) {
    if (strpos($firma_tecnico_data, ',') !== false) {
        $data_part = explode(',', $firma_tecnico_data)[1];
    } else {
        $data_part = $firma_tecnico_data;
    }
    $decoded = base64_decode($data_part);
    $filename = "firma_tec_" . $id . "_" . time() . ".png";
    $filepath = $upload_dir . $filename;
    if (file_put_contents($filepath, $decoded)) {
        $sql_firma_tecnico = ", firma_tecnico = '$filename'";
    }
}

$vnd = empty($vendedor_texto) ? 'NULL' : "'$vendedor_texto'";
$olt = ($id_olt === 'NULL') ? 'NULL' : $id_olt;
$pon = ($id_pon === 'NULL') ? 'NULL' : $id_pon;

$sql = "UPDATE contratos SET
    cedula='$cedula', nombre_completo='$nombre_completo',
    telefono='$telefono', correo='$correo',
    telefono_secundario='$telefono_sec', correo_adicional='$correo_adicional',
    id_municipio=$mun_val, id_parroquia=$par_val, id_comunidad=$com_val,
    municipio_texto='$municipio_texto', parroquia_texto='$parroquia_texto',
    id_plan=$id_plan, monto_plan=$monto_plan, vendedor_texto=$vnd, sae_plus='$sae_plus',
    direccion='$direccion', fecha_instalacion='$fecha_inst', estado='$estado',
    ident_caja_nap='$ident_caja_nap', puerto_nap='$puerto_nap', num_presinto_odn='$num_presinto_odn',
    id_olt=$olt, id_pon=$pon,
    tipo_conexion='$tipo_conexion', tipo_instalacion='$tipo_instalacion', mac_onu='$mac_onu', ip_onu='" . (!empty($ip_onu) ? $ip_onu : $ip_radio) . "', 
    nap_tx_power='$nap_tx_power', onu_rx_power='$onu_rx_power', distancia_drop='$distancia_drop',
    punto_acceso='$punto_acceso', valor_conexion_dbm='$valor_conexion',
    observaciones='$observaciones',
    plan_prorrateo_nombre='$plan_prorrateo_nombre',
    dias_prorrateo=$dias_prorrateo,
    monto_prorrateo_usd=$monto_prorrateo_usd,
    monto_pagar=$monto_pagar,
    monto_pagado=$monto_pagado,
    medio_pago='$medio_pago',
    instalador='$instalador_ftth',
    instalador_c='$instalador_radio'
    $sql_firma_cliente
    $sql_firma_tecnico
    $sql_foto
    $sql_documento
    $sql_fibra_file
    $sql_fibra_text
    WHERE id=$id";

if ($conn->query($sql)) {
    // --- Debt Syncing ---
    $saldo_pendiente = round($monto_pagar - $monto_pagado, 2);
    if ($saldo_pendiente > 0) {
        // Check if exists
        $check_deudor = $conn->query("SELECT id FROM clientes_deudores WHERE id_contrato = $id AND estado = 'PENDIENTE' LIMIT 1");
        if ($check_deudor && $check_deudor->num_rows > 0) {
            $deudor_row = $check_deudor->fetch_assoc();
            $id_deudor = $deudor_row['id'];
            $conn->query("UPDATE clientes_deudores SET monto_total = $monto_pagar, monto_pagado = $monto_pagado, saldo_pendiente = $saldo_pendiente WHERE id = $id_deudor");
        } else {
            $conn->query("INSERT INTO clientes_deudores (id_contrato, monto_total, monto_pagado, saldo_pendiente, estado) VALUES ($id, $monto_pagar, $monto_pagado, $saldo_pendiente, 'PENDIENTE')");
        }
    } else {
        // Mark as paid if exists
        $conn->query("UPDATE clientes_deudores SET estado = 'PAGADO', saldo_pendiente = 0 WHERE id_contrato = $id AND estado = 'PENDIENTE'");
    }

    echo json_encode(['success' => true, 'message' => 'Contrato actualizado correctamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $conn->error]);
}

$conn->close();
?>