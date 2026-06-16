<?php
require_once '../../vendor/autoload.php';
require_once '../conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Optimización de recursos para archivos grandes
ini_set('memory_limit', '512M');
set_time_limit(600);

header('Content-Type: application/json');

// Función para normalizar encabezados
function normalizeHeader($h) {
    if (!$h) return "";
    $h = trim($h);
    $h = mb_strtoupper($h, 'UTF-8');
    // Eliminar acentos para robustez
    $search = ['Á','É','Í','Ó','Ú','Ñ'];
    $replace = ['A','E','I','O','U','N'];
    return str_replace($search, $replace, $h);
}

// Función para formatear IP (12 dígitos a 4 octetos)
function formatIP($ip) {
    if (!$ip) return "";
    $ip = trim($ip);
    if (preg_match('/^\d{12}$/', $ip)) {
        return (int)substr($ip, 0, 3) . "." . (int)substr($ip, 3, 3) . "." . (int)substr($ip, 6, 3) . "." . (int)substr($ip, 9, 3);
    }
    return $ip;
}

// Verificar archivo
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
    exit;
}

$inputFileName = $_FILES['archivo_excel']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray(null, true, true, true);

    if (empty($rows)) {
        throw new Exception("El archivo está vacío.");
    }

    $raw_headers = array_shift($rows);
    $headers = [];
    foreach ($raw_headers as $colKey => $h) {
        $headers[$colKey] = normalizeHeader($h);
    }

    // Mapa de Columnas Extendido (Nombre Normalizado del Excel => Columna BD)
    // Coincide exactamente con los encabezados de exportExcel() en gestion_contratos.php
    $colMap = [
        'ID' => 'id',
        'SAR' => 'fecha_registro',
        'CEDULA' => 'cedula',
        'CLIENTE' => 'nombre_completo',
        'PLAN ($)' => 'monto_plan',
        'MUNICIPIO' => 'municipio_texto',
        'PARROQUIA' => 'parroquia_texto',
        'DIRECCION' => 'direccion',
        'TELF. 1' => 'telefono',
        'TELF. 2' => 'telefono_secundario',
        'CORREO' => 'correo',
        'CORREO (ALT)' => 'correo_adicional',
        'F. INSTALACION' => 'fecha_instalacion',
        'MEDIO PAGO' => 'medio_pago',
        'MONTO PAGAR' => 'monto_pagar',
        'MONTO PAGADO' => 'monto_pagado',
        'DIAS PRORRATEO' => 'dias_prorrateo',
        'PLAN PRORRATEO' => 'plan_prorrateo_nombre',
        'MONTO PRORR. ($)' => 'monto_prorrateo_usd',
        'OBSERV.' => 'observaciones',
        'TIPO CONEX.' => 'tipo_conexion',
        'TIPO INSTAL.' => 'tipo_instalacion',
        'MAC/SERIAL' => 'mac_onu',
        'IP ONU' => 'ip_onu',
        'CAJA NAP' => 'ident_caja_nap',
        'PUERTO NAP' => 'puerto_nap',
        'NAP TX (DBM)' => 'nap_tx_power',
        'ONU RX (DBM)' => 'onu_rx_power',
        'DIST. DROP (M)' => 'distancia_drop',
        'INSTALADOR' => 'instalador',
        'EVIDENCIA FIBRA' => 'evidencia_fibra',
        'PUNTO ACCESO' => 'punto_acceso',
        'VAL. CONEX. (DBM)' => 'valor_conexion_dbm',
        'PRECINTO ODN' => 'num_presinto_odn',
        'VENDEDOR (EDIT)' => 'vendedor_texto',
        'SAE PLUS (EDIT)' => 'sae_plus',
        'ESTADO' => 'estado',
        'OLT' => 'nombre_olt',
        'PON' => 'nombre_pon'
    ];

    // Mapas de FK para resolución dinámica
    $municipiosMap = [];
    $res = $conn->query("SELECT id_municipio, nombre_municipio FROM municipio");
    while ($r = $res->fetch_assoc()) $municipiosMap[normalizeHeader($r['nombre_municipio'])] = $r['id_municipio'];

    $parroquiasMap = [];
    $res = $conn->query("SELECT id_parroquia, nombre_parroquia FROM parroquia");
    while ($r = $res->fetch_assoc()) $parroquiasMap[normalizeHeader($r['nombre_parroquia'])] = $r['id_parroquia'];

    $oltMap = [];
    $res = $conn->query("SELECT id_olt, nombre_olt FROM olt");
    while ($r = $res->fetch_assoc()) $oltMap[normalizeHeader($r['nombre_olt'])] = $r['id_olt'];

    $ponMap = [];
    $res = $conn->query("SELECT id_pon, nombre_pon FROM pon");
    while ($r = $res->fetch_assoc()) $ponMap[normalizeHeader($r['nombre_pon'])] = $r['id_pon'];

    $planesPrices = [];
    $res = $conn->query("SELECT id_plan, monto FROM planes");
    while ($r = $res->fetch_assoc()) $planesPrices[] = ['id' => $r['id_plan'], 'monto' => (float)$r['monto']];

    $stats = ['updated' => 0, 'inserted' => 0, 'errors' => 0, 'skipped' => 0];

    // Helper para fechas de Excel
    function excelToDate($val) {
        if (!$val) return null;
        if (is_numeric($val)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
        }
        return date('Y-m-d', strtotime($val));
    }

    foreach ($rows as $index => $row) {
        $rowData = array_fill_keys(array_values($colMap), '');
        foreach ($headers as $colKey => $normHeader) {
            if (isset($colMap[$normHeader])) {
                $val = $row[$colKey] ?? '';
                $rowData[$colMap[$normHeader]] = ($val === null) ? '' : trim($val);
            }
        }

        if (empty($rowData['cedula']) || empty($rowData['nombre_completo'])) {
            $stats['skipped']++;
            continue;
        }

        // 1. Resolver Plan por Precio (Mejorado)
        $id_plan = 8; // Default
        $price = (float)$rowData['monto_plan'];
        foreach ($planesPrices as $p) {
            if (abs($p['monto'] - $price) < 0.05) {
                $id_plan = $p['id'];
                if (abs($price - 23.20) < 0.05) {
                    $tipo = strtoupper($rowData['tipo_conexion'] ?? '');
                    $id_plan = ($tipo == 'RADIO') ? 2 : 3;
                }
                break;
            }
        }

        // 2. Normalizar e Identificar FKs
        $id_mun = $municipiosMap[normalizeHeader($rowData['municipio_texto'])] ?? null;
        $id_par = $parroquiasMap[normalizeHeader($rowData['parroquia_texto'])] ?? null;
        $id_olt = $oltMap[normalizeHeader($rowData['nombre_olt'])] ?? null;
        $id_pon = $ponMap[normalizeHeader($rowData['nombre_pon'])] ?? null;
        
        $ip_onu = formatIP($rowData['ip_onu']);
        $f_inst = excelToDate($rowData['fecha_instalacion']);
        $f_reg  = excelToDate($rowData['fecha_registro']) ?: date('Y-m-d H:i:s');

        // 3. SQL de Inserción/Sincronización Total
        $sql = "INSERT INTO contratos (
            id, cedula, nombre_completo, id_municipio, municipio_texto, id_parroquia, parroquia_texto,
            id_plan, monto_plan, vendedor_texto, sae_plus, direccion, telefono, telefono_secundario,
            correo, correo_adicional, fecha_instalacion, ident_caja_nap, puerto_nap, num_presinto_odn,
            id_olt, id_pon, estado, medio_pago, monto_pagar, monto_pagado, dias_prorrateo,
            monto_prorrateo_usd, plan_prorrateo_nombre, observaciones, tipo_conexion, tipo_instalacion,
            mac_onu, ip_onu, nap_tx_power, onu_rx_power, distancia_drop, punto_acceso, valor_conexion_dbm,
            fecha_registro
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?,
            ?
        ) ON DUPLICATE KEY UPDATE
            cedula = VALUES(cedula),
            nombre_completo = VALUES(nombre_completo),
            id_municipio = VALUES(id_municipio),
            municipio_texto = VALUES(municipio_texto),
            id_parroquia = VALUES(id_parroquia),
            parroquia_texto = VALUES(parroquia_texto),
            id_plan = VALUES(id_plan),
            monto_plan = VALUES(monto_plan),
            vendedor_texto = VALUES(vendedor_texto),
            sae_plus = VALUES(sae_plus),
            direccion = VALUES(direccion),
            telefono = VALUES(telefono),
            telefono_secundario = VALUES(telefono_secundario),
            correo = VALUES(correo),
            correo_adicional = VALUES(correo_adicional),
            fecha_instalacion = VALUES(fecha_instalacion),
            ident_caja_nap = VALUES(ident_caja_nap),
            puerto_nap = VALUES(puerto_nap),
            num_presinto_odn = VALUES(num_presinto_odn),
            id_olt = VALUES(id_olt),
            id_pon = VALUES(id_pon),
            estado = VALUES(estado),
            medio_pago = VALUES(medio_pago),
            monto_pagar = VALUES(monto_pagar),
            monto_pagado = VALUES(monto_pagado),
            dias_prorrateo = VALUES(dias_prorrateo),
            monto_prorrateo_usd = VALUES(monto_prorrateo_usd),
            plan_prorrateo_nombre = VALUES(plan_prorrateo_nombre),
            observaciones = VALUES(observaciones),
            tipo_conexion = VALUES(tipo_conexion),
            tipo_instalacion = VALUES(tipo_instalacion),
            mac_onu = VALUES(mac_onu),
            ip_onu = VALUES(ip_onu),
            nap_tx_power = VALUES(nap_tx_power),
            onu_rx_power = VALUES(onu_rx_power),
            distancia_drop = VALUES(distancia_drop),
            punto_acceso = VALUES(punto_acceso),
            valor_conexion_dbm = VALUES(valor_conexion_dbm)";

        $stmt = $conn->prepare($sql);
        
        // El ID lo tomamos del Excel si existe, si no NULL para generar nuevo
        $id_val = !empty($rowData['id']) ? intval($rowData['id']) : null;
        
        $stmt->bind_param("issisisidsssssssssssiissddidssssssssssss", 
            $id_val, $rowData['cedula'], $rowData['nombre_completo'], $id_mun, $rowData['municipio_texto'], $id_par, $rowData['parroquia_texto'],
            $id_plan, $rowData['monto_plan'], $rowData['vendedor_texto'], $rowData['sae_plus'], $rowData['direccion'], $rowData['telefono'], $rowData['telefono_secundario'],
            $rowData['correo'], $rowData['correo_adicional'], $f_inst, $rowData['ident_caja_nap'], $rowData['puerto_nap'], $rowData['num_presinto_odn'],
            $id_olt, $id_pon, $rowData['estado'], $rowData['medio_pago'], $rowData['monto_pagar'], $rowData['monto_pagado'], $rowData['dias_prorrateo'],
            $rowData['monto_prorrateo_usd'], $rowData['plan_prorrateo_nombre'], $rowData['observaciones'], $rowData['tipo_conexion'], $rowData['tipo_instalacion'],
            $rowData['mac_onu'], $ip_onu, $rowData['nap_tx_power'], $rowData['onu_rx_power'], $rowData['distancia_drop'], $rowData['punto_acceso'], $rowData['valor_conexion_dbm'],
            $f_reg
        );

        if ($stmt->execute()) {
            if ($stmt->affected_rows == 1) $stats['inserted']++;
            elseif ($stmt->affected_rows == 2) $stats['updated']++;
            else $stats['skipped']++;
        } else {
            $stats['errors']++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Proceso completado. Insertados: {$stats['inserted']}, Actualizados: {$stats['updated']}, Errores: {$stats['errors']}, Ignorados: {$stats['skipped']}",
        'stats' => $stats
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>