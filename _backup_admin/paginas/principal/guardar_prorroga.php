<?php
require_once '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cedula = $_POST['cedula_titular'] ?? '';
    $nombre = $_POST['nombre_titular'] ?? '';
    $fecha_corte = !empty($_POST['fecha_corte']) ? $_POST['fecha_corte'] : null;
    $tipo = $_POST['tipo_solicitud'] ?? 'PRORROGA';

    // Prórroga Interna
    $existe_saeplus = $_POST['existe_saeplus'] ?? 'NO';
    $prorroga_regular = $_POST['prorroga_regular'] ?? 'SI';
    $estado_venta = $_POST['estado_venta'] ?? 'PENDIENTE';
    $codigo_sae_plus = isset($_POST['codigo_sae_plus']) ? $conn->real_escape_string(trim($_POST['codigo_sae_plus'])) : null;
    $id_contrato_asociado = $_POST['id_contrato_asociado'] ?? null;
    $id_plan = $_POST['id_plan'] ?? null;

    // Inicializar variables que pueden no venir en el form de prórroga pero están en el bind_param
    $telefono = $_POST['telefono'] ?? null;
    $telefono_extra = $_POST['telefono_extra'] ?? null;
    $email = $_POST['email'] ?? null;
    $id_municipio = $_POST['id_municipio'] ?? null;
    $id_parroquia = $_POST['id_parroquia'] ?? null;
    $direccion = $_POST['direccion'] ?? null;
    $fecha_firma = $_POST['fecha_firma'] ?? null;
    $prorateo = $_POST['prorateo'] ?? null;
    $metodo_pago = $_POST['metodo_pago'] ?? null;
    $fecha_instalacion = $_POST['fecha_instalacion'] ?? null;

    $path_contrato = null;

    // Manejo de Archivo (Foto del Contrato)
    if (isset($_FILES['foto_contrato']) && $_FILES['foto_contrato']['error'] == 0) {
        $upload_dir = '../../uploads/contratos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = pathinfo($_FILES['foto_contrato']['name'], PATHINFO_EXTENSION);
        $filename = 'contrato_' . time() . '_' . $cedula . '.' . $ext;
        if (move_uploaded_file($_FILES['foto_contrato']['tmp_name'], $upload_dir . $filename)) {
            $path_contrato = $filename;
        }
    }

    $sql = "INSERT INTO prorrogas (
        tipo_solicitud, cedula_titular, nombre_titular, fecha_corte, existe_saeplus, prorroga_regular,
        telefono, telefono_extra, email, id_municipio, id_parroquia, direccion, id_plan, fecha_firma,
        path_contrato, prorateo, metodo_pago, fecha_instalacion, estado_venta, codigo_sae_plus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssiisisssssss",
        $tipo,
        $cedula,
        $nombre,
        $fecha_corte,
        $existe_saeplus,
        $prorroga_regular,
        $telefono,
        $telefono_extra,
        $email,
        $id_municipio,
        $id_parroquia,
        $direccion,
        $id_plan,
        $fecha_firma,
        $path_contrato,
        $prorateo,
        $metodo_pago,
        $fecha_instalacion,
        $estado_venta,
        $codigo_sae_plus
    );

    if ($stmt->execute()) {
        header("Location: gestion_prorrogas.php?message=Solicitud registrada con éxito&class=success");
    } else {
        header("Location: gestion_prorrogas.php?message=Error al registrar: " . $stmt->error . "&class=danger");
    }
}
?>