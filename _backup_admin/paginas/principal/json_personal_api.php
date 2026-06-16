<?php
header('Content-Type: application/json; charset=utf-8');
require '../conexion.php';

// Archivos JSON
$fileInstaladores = 'data/instaladores.json';
$fileVendedores = 'data/vendedores.json';

// Ensure data dir exists
if (!is_dir('data'))
    mkdir('data', 0755, true);

// Función helper para leer
function leerJson($file)
{
    if (!file_exists($file))
        return [];
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

// Función helper para guardar
function guardarJson($file, $data)
{
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// REQUEST METHOD
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'get_instaladores') {
        echo json_encode(leerJson($fileInstaladores));
    } elseif ($action === 'get_vendedores') {
        echo json_encode(leerJson($fileVendedores));
    } elseif ($action === 'get_planes_prorrateo') {
        echo json_encode(leerJson('data/planes_prorrateo.json'));
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'save_instaladores') {
        if (is_array($input)) {
            // --- LÓGICA DE ACTUALIZACIÓN EN CASCADA ---
            if (file_exists($fileInstaladores)) {
                $old_insts = json_decode(file_get_contents($fileInstaladores), true) ?: [];
                foreach ($old_insts as $idx => $old_name_raw) {
                    if (isset($input[$idx])) {
                        $new_name_raw = $input[$idx];
                        $old_name = $conn->real_escape_string($old_name_raw);
                        $new_name = $conn->real_escape_string($new_name_raw);

                        if ($old_name !== $new_name && !empty($old_name) && !empty($new_name)) {
                            // Actualizar en el campo 'instalador' de la tabla contratos
                            $sql_name = "UPDATE contratos SET instalador = '$new_name' WHERE instalador = '$old_name'";
                            $conn->query($sql_name);
                        }
                    }
                }
            }

            guardarJson($fileInstaladores, $input);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        }
    } elseif ($action === 'save_vendedores') {
        if (is_array($input)) {
            // --- LÓGICA DE ACTUALIZACIÓN EN CASCADA ---
            if (file_exists($fileVendedores)) {
                $old_vends = json_decode(file_get_contents($fileVendedores), true) ?: [];
                foreach ($old_vends as $idx => $old_name_raw) {
                    if (isset($input[$idx])) {
                        $new_name_raw = $input[$idx];
                        $old_name = $conn->real_escape_string($old_name_raw);
                        $new_name = $conn->real_escape_string($new_name_raw);

                        if ($old_name !== $new_name && !empty($old_name) && !empty($new_name)) {
                            $sql_name = "UPDATE contratos SET vendedor_texto = '$new_name' WHERE vendedor_texto = '$old_name'";
                            $conn->query($sql_name);
                        }
                    }
                }
            }

            guardarJson($fileVendedores, $input);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        }
    } elseif ($action === 'save_planes_prorrateo') {
        if (is_array($input)) {
            $file = 'data/planes_prorrateo.json';

            // --- LÓGICA DE ACTUALIZACIÓN EN CASCADA ---
            if (file_exists($file)) {
                $old_planes = json_decode(file_get_contents($file), true) ?: [];
                foreach ($old_planes as $idx => $old) {
                    if (isset($input[$idx])) {
                        $new = $input[$idx];
                        $old_name = $conn->real_escape_string($old['nombre']);
                        $new_name = $conn->real_escape_string($new['nombre']);
                        $new_price = floatval($new['precio']);
                        $old_price = floatval($old['precio']);

                        // 1. Si cambió el nombre, actualizarlo en los contratos
                        if ($old_name !== $new_name) {
                            $sql_name = "UPDATE contratos SET plan_prorrateo_nombre = '$new_name' WHERE plan_prorrateo_nombre = '$old_name'";
                            $conn->query($sql_name);
                        }

                        // 2. Si cambió el precio, recalcular montos en los contratos
                        if ($old_price !== $new_price) {
                            $sql_price = "UPDATE contratos SET monto_prorrateo_usd = ($new_price / 30) * dias_prorrateo WHERE plan_prorrateo_nombre = '$new_name'";
                            $conn->query($sql_price);
                        }
                    }
                }
            }

            guardarJson($file, $input);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        }
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Method not allowed']);
}
