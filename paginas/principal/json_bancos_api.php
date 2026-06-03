<?php
/**
 * API para Gestión de Bancos (JSON)
 * Acciones: get, add, delete
 */

header('Content-Type: application/json; charset=utf-8');

$file_path = 'bancos.json';

// Cargar Datos
function loadPartidas($path)
{
    if (!file_exists($path))
        return [];
    $content = file_get_contents($path);
    return json_decode($content, true) ?: [];
}

// Guardar Datos
function savePartidas($path, $data)
{
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = isset($_GET['action']) ? $_GET['action'] : 'get';

$bancos = loadPartidas($file_path);

if ($action === 'get') {
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    $total = count($bancos);
    $pagedData = array_slice($bancos, $offset, $limit);

    echo json_encode([
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit),
        'data' => $pagedData
    ]);
    exit;
}

if ($action === 'add') {
    $nombre = isset($_POST['nombre_banco']) ? trim($_POST['nombre_banco']) : '';
    $numero = isset($_POST['numero_cuenta']) ? trim($_POST['numero_cuenta']) : '';
    $titular = isset($_POST['titular_cuenta']) ? trim($_POST['titular_cuenta']) : '';
    $cedula = isset($_POST['cedula_propietario']) ? trim($_POST['cedula_propietario']) : '';
    $metodos = isset($_POST['metodos_pago']) ? $_POST['metodos_pago'] : []; // Esperamos un array
    $activo = isset($_POST['activo']) ? ($_POST['activo'] == '1' || $_POST['activo'] === 'true') : true;

    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
        exit;
    }

    // Generar ID
    $maxId = 0;
    foreach ($bancos as $b) {
        if (isset($b['id_banco']) && intval($b['id_banco']) > $maxId) {
            $maxId = intval($b['id_banco']);
        }
    }
    $newId = $maxId + 1;

    $nuevo = [
        'id_banco' => (string) $newId,
        'nombre_banco' => $nombre,
        'numero_cuenta' => $numero,
        'cedula_propietario' => $cedula,
        'nombre_propietario' => $titular,
        'metodos_pago' => $metodos,
        'activo' => $activo
    ];

    $bancos[] = $nuevo;
    savePartidas($file_path, $bancos);
    echo json_encode(['success' => true, 'data' => $nuevo]);
    exit;
}

if ($action === 'update') {
    $id = isset($_POST['id_banco']) ? $_POST['id_banco'] : '';
    $nombre = isset($_POST['nombre_banco']) ? trim($_POST['nombre_banco']) : '';
    $numero = isset($_POST['numero_cuenta']) ? trim($_POST['numero_cuenta']) : '';
    $titular = isset($_POST['titular_cuenta']) ? trim($_POST['titular_cuenta']) : '';
    $cedula = isset($_POST['cedula_propietario']) ? trim($_POST['cedula_propietario']) : '';
    $metodos = isset($_POST['metodos_pago']) ? $_POST['metodos_pago'] : []; // Esperamos un array
    $activo = isset($_POST['activo']) ? ($_POST['activo'] == '1' || $_POST['activo'] === 'true') : false;

    if (empty($id) || empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'ID y Nombre requeridos']);
        exit;
    }

    $found = false;
    foreach ($bancos as &$b) {
        if ($b['id_banco'] == $id) {
            $b['nombre_banco'] = $nombre;
            $b['numero_cuenta'] = $numero;
            $b['cedula_propietario'] = $cedula;
            $b['nombre_propietario'] = $titular;
            $b['metodos_pago'] = $metodos;
            $b['activo'] = $activo;
            $found = true;
            break;
        }
    }

    if ($found) {
        savePartidas($file_path, $bancos);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Banco no encontrado']);
    }
    exit;
}

if ($action === 'toggle_status') {
    $id = isset($_POST['id_banco']) ? $_POST['id_banco'] : '';
    $activo = isset($_POST['activo']) ? ($_POST['activo'] == '1' || $_POST['activo'] === 'true') : false;

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        exit;
    }

    $found = false;
    foreach ($bancos as &$b) {
        if ($b['id_banco'] == $id) {
            $b['activo'] = $activo;
            $found = true;
            break;
        }
    }

    if ($found) {
        savePartidas($file_path, $bancos);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Banco no encontrado']);
    }
    exit;
}


if ($action === 'delete') {
    $id = isset($_POST['id']) ? $_POST['id'] : '';

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        exit;
    }

    $bancos = array_filter($bancos, function ($b) use ($id) {
        return $b['id_banco'] != $id;
    });

    savePartidas($file_path, array_values($bancos)); // Re-index array
    echo json_encode(['success' => true]);
    exit;
}
?>