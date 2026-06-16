<?php
/**
 * Backend para gestionar opciones de soporte (Solo Fallas)
 * CRUD sobre archivo JSON
 */

header('Content-Type: application/json');

// Ruta al archivo JSON
$json_file = 'data/opciones_soporte.json';

// Verificar permisos de escritura en el directorio si el archivo no existe
if (!file_exists($json_file)) {
    if (!is_writable(dirname($json_file))) {
        echo json_encode(['success' => false, 'message' => 'No hay permisos de escritura en el directorio data/']);
        exit;
    }
    // Crear archivo por defecto si no existe
    $defaults = [
        'NIVEL 1' => ['Mala señal WiFi'],
        'NIVEL 2' => ['Cable roto'],
        'NIVEL 3' => ['Caída de nodo']
    ];
    file_put_contents($json_file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Leer datos actuales
$json_content = file_get_contents($json_file);
$data = json_decode($json_content, true);

if (!$data) {
    $data = ['tipos_falla' => []];
}

// Acción solicitada
$action = $_POST['action'] ?? $_GET['action'] ?? 'read';

try {
    switch ($action) {
        case 'read':
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'add':
            $level = $_POST['level'] ?? '';
            $value = trim($_POST['value'] ?? '');

            if (empty($level) || !isset($data[$level])) {
                throw new Exception("Nivel no válido: " . $level);
            }
            if (empty($value)) {
                throw new Exception("El valor no puede estar vacío");
            }
            if (in_array($value, $data[$level])) {
                throw new Exception("Esta opción ya existe en $level");
            }

            $data[$level][] = $value;
            saveData($json_file, $data);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'delete':
            $level = $_POST['level'] ?? '';
            $value = $_POST['value'] ?? '';

            if (empty($level) || !isset($data[$level])) {
                throw new Exception("Nivel no válido");
            }

            $key = array_search($value, $data[$level]);
            if ($key !== false) {
                array_splice($data[$level], $key, 1);
                saveData($json_file, $data);
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                throw new Exception("Opción no encontrada");
            }
            break;

        case 'edit':
            $level = $_POST['level'] ?? '';
            $old_value = $_POST['old_value'] ?? '';
            $new_value = trim($_POST['new_value'] ?? '');

            if (empty($level) || !isset($data[$level])) {
                throw new Exception("Nivel no válido");
            }
            if (empty($new_value)) {
                throw new Exception("El nuevo valor no puede estar vacío");
            }
            if ($old_value === $new_value) {
                echo json_encode(['success' => true, 'data' => $data]);
                break;
            }
            if (in_array($new_value, $data[$level])) {
                throw new Exception("El nuevo valor ya existe en $level");
            }

            $key = array_search($old_value, $data[$level]);
            if ($key !== false) {
                $data[$level][$key] = $new_value;
                saveData($json_file, $data);
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                throw new Exception("Opción original no encontrada");
            }
            break;

        default:
            throw new Exception("Acción no reconocida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function saveData($file, $data)
{
    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        throw new Exception("Error al guardar archivo");
    }
}
?>