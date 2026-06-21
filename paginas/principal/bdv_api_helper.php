<?php
/**
 * bdv_api_helper.php
 * Módulo de integración con la API Consulta de Movimientos del Banco de Venezuela (Producción).
 *
 * Las credenciales ya NO están hardcodeadas aquí. Se leen desde bancos.json via
 * obtener_config_api_banco() o se pasan directamente a consultar_movimientos_bdv().
 */

/**
 * Lee el bloque api_config de un banco desde bancos.json.
 *
 * @param  int|string $id_banco  ID del banco a consultar.
 * @return array|null            El bloque api_config o null si no existe / no está habilitado.
 */
function obtener_config_api_banco($id_banco): ?array
{
    $json_path = __DIR__ . '/bancos.json';
    if (!file_exists($json_path)) {
        return null;
    }
    $bancos = json_decode(file_get_contents($json_path), true) ?: [];
    foreach ($bancos as $banco) {
        if ((string)$banco['id_banco'] === (string)$id_banco) {
            $cfg = $banco['api_config'] ?? null;
            if ($cfg && !empty($cfg['habilitada'])) {
                return $cfg;
            }
            return null;
        }
    }
    return null;
}

/**
 * Retorna todos los IDs de banco que tengan api_config habilitada y tipo dado.
 *
 * @param  string $tipo  Tipo de API, p.ej. 'bdv'.
 * @return int[]
 */
function obtener_ids_banco_con_api(string $tipo = 'bdv'): array
{
    $json_path = __DIR__ . '/bancos.json';
    if (!file_exists($json_path)) {
        return [];
    }
    $bancos = json_decode(file_get_contents($json_path), true) ?: [];
    $ids = [];
    foreach ($bancos as $banco) {
        $cfg = $banco['api_config'] ?? null;
        if ($cfg && !empty($cfg['habilitada']) && ($cfg['tipo'] ?? '') === $tipo) {
            $ids[] = intval($banco['id_banco']);
        }
    }
    return $ids;
}

/**
 * Consulta los movimientos bancarios del Banco de Venezuela para un rango de fechas.
 *
 * @param string $cuenta         Número de cuenta a consultar (20 dígitos).
 * @param string $fechaIni       Fecha inicio en formato Y-m-d o d/m/Y.
 * @param string $fechaFin       Fecha fin en formato Y-m-d o d/m/Y.
 * @param string $nroMovimiento  Opcional: número de movimiento específico.
 * @param string $api_key        API Key. Si se omite, se busca en bancos.json.
 * @param string $endpoint       URL del endpoint. Si se omite, usa el default BDV.
 *
 * @return array  [
 *   'success'   => bool,
 *   'message'   => string,
 *   'movs'      => array,
 *   'raw'       => mixed,
 * ]
 */
function consultar_movimientos_bdv(
    string $cuenta,
    string $fechaIni,
    string $fechaFin,
    string $nroMovimiento = '',
    string $api_key = '',
    string $endpoint = ''
): array {
    $verifySsl = true;
    if (empty($api_key)) {
        // Buscar en el primer banco BDV habilitado
        $ids = obtener_ids_banco_con_api('bdv');
        if (!empty($ids)) {
            $cfg = obtener_config_api_banco($ids[0]);
            $api_key   = $cfg['api_key']   ?? '';
            $endpoint  = $cfg['endpoint']  ?? '';
            $verifySsl = $cfg['verify_ssl'] ?? true;
        }
    }
    if (empty($endpoint)) {
        $endpoint = 'https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos';
    }

    if (empty($api_key)) {
        return [
            'success' => false,
            'message' => 'No se encontró una API Key configurada para BDV.',
            'movs'    => [],
            'raw'     => null,
        ];
    }

    // Normalizar fechas al formato que espera la API: DD/MM/YYYY
    $fechaIni = _bdv_normalizar_fecha($fechaIni);
    $fechaFin  = _bdv_normalizar_fecha($fechaFin);

    if (!$fechaIni || !$fechaFin) {
        return [
            'success' => false,
            'message' => 'Fechas inválidas para consulta BDV.',
            'movs'    => [],
            'raw'     => null,
        ];
    }

    $payload_arr = [
        'cuenta'     => $cuenta,
        'fechaIni'   => $fechaIni,
        'fechaFin'   => $fechaFin,
        'tipoMoneda' => 'VES',
    ];
    if ($nroMovimiento !== '') {
        $payload_arr['nroMovimiento'] = $nroMovimiento;
    }
    $payload = json_encode($payload_arr, JSON_UNESCAPED_SLASHES);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $api_key,
        ],
    ]);

    $respuesta   = curl_exec($ch);
    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error  = curl_error($ch);
    curl_close($ch);

    // Log debug
    $log_dir = __DIR__ . '/../../logs';
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0777, true); }
    @file_put_contents($log_dir . '/bdv_api.log', date('Y-m-d H:i:s') . " REQ: $payload\nRES: $respuesta\nERR: $curl_error\n===\n", FILE_APPEND);

    if ($respuesta === false || !empty($curl_error)) {
        return [
            'success' => false,
            'message' => 'Error de conexión con la API BDV: ' . $curl_error,
            'movs'    => [],
            'raw'     => null,
        ];
    }

    $data = json_decode($respuesta, true);

    if ($http_code < 200 || $http_code >= 300) {
        return [
            'success' => false,
            'message' => "HTTP $http_code desde API BDV. Respuesta: " . $respuesta,
            'movs'    => [],
            'raw'     => $data,
        ];
    }

    $code = $data['code'] ?? $data['status'] ?? null;
    if ($code != '1000' && $code != '1001' && $code != 200) {
        $raw_preview = mb_substr(json_encode($data), 0, 2000);
        return [
            'success' => false,
            'message' => 'API BDV respondió con código: ' . ($data['message'] ?? $code) . '. Respuesta: ' . $raw_preview,
            'movs'    => [],
            'raw'     => $data,
        ];
    }

    $movs = $data['data']['movs'] ?? [];

    return [
        'success' => true,
        'message' => $data['message'] ?? ($code === '1001' ? 'No existen movimientos' : 'consulta exitosa'),
        'movs'    => $movs,
        'raw'     => $data,
    ];
}

/**
 * Busca dentro de un array de movimientos BDV si existe una transacción que
 * coincida con la referencia y el monto proporcionados.
 *
 * @param array  $movimientos  Array de movimientos devuelto por consultar_movimientos_bdv().
 * @param string $referencia   Número de referencia reportado por el cliente.
 * @param float  $monto_bs     Monto en Bolívares a buscar.
 * @param float  $tolerancia   Tolerancia de diferencia de monto aceptada en Bs.
 *
 * @return array|null  El movimiento encontrado o null si no hay match.
 */
function buscar_movimiento_bdv(array $movimientos, string $referencia, float $monto_bs, float $tolerancia = 0.10): ?array
{
    $ref_limpia = preg_replace('/\D/', '', $referencia);
    $ref_6 = strlen($ref_limpia) >= 6 ? substr($ref_limpia, -6) : $ref_limpia;

    foreach ($movimientos as $mov) {
        $tipo = strtoupper($mov['mov'] ?? '');
        $desc = strtoupper($mov['descripcion'] ?? '');
        if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) {
            continue;
        }

        $ref_banco_limpia = preg_replace('/\D/', '', $mov['referencia'] ?? '');
        $ref_banco_6      = strlen($ref_banco_limpia) >= 6 ? substr($ref_banco_limpia, -6) : $ref_banco_limpia;

        $ref_match = (
            $ref_banco_limpia === $ref_limpia ||
            $ref_banco_6      === $ref_6
        );

        if (!$ref_match) {
            continue;
        }

        $importe_banco = floatval(str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,.]/', '', $mov['importe'] ?? '0'))));
        if (abs($importe_banco - $monto_bs) <= $tolerancia) {
            return $mov;
        }
    }

    return null;
}

/**
 * Convierte una fecha en formato Y-m-d a DD/MM/YYYY.
 * Si ya viene en DD/MM/YYYY, la retorna tal cual.
 */
function _bdv_normalizar_fecha(string $fecha): ?string
{
    $fecha = trim($fecha);

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
        return $fecha;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $parts = explode('-', $fecha);
        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }

    return null;
}

/**
 * Consulta movimientos bancarios BDV para un rango de fechas, dividiendo
 * automáticamente en bloques Lunes→Sábado para evitar el problema del
 * domingo (la API BDV no retorna datos cuando el rango incluye domingos).
 *
 * @param  int|string $id_banco   ID del banco en bancos.json.
 * @param  string     $fechaIni   Fecha inicio (Y-m-d).
 * @param  string     $fechaFin   Fecha fin (Y-m-d).
 *
 * @return array [
 *   'success'        => bool,
 *   'movs'           => array,  // Todos los movimientos encontrados
 *   'api_respondio'  => bool,   // Si la API respondió al menos una vez
 *   'total_api_calls' => int,   // Número de llamadas API realizadas
 * ]
 */
function consultar_movimientos_rango(int $id_banco, string $fechaIni, string $fechaFin): array
{
    $hoy = (new \DateTime('now', new \DateTimeZone('America/Caracas')))->format('Y-m-d');
    $max_fecha = $hoy;
    if ((int)date('N', strtotime($max_fecha)) === 7) {
        $max_fecha = date('Y-m-d', strtotime($max_fecha . ' -1 day'));
    }
    if ($fechaFin > $max_fecha) $fechaFin = $max_fecha;

    $todos_movs = [];
    $api_respondio = false;
    $total_api_calls = 0;
    $actual = new \DateTime($fechaIni);
    $final  = new \DateTime($fechaFin);

    while ($actual <= $final) {
        $dia_sem = (int)$actual->format('N');
        if ($dia_sem === 7) {
            $fi = $actual->format('Y-m-d');
            $r  = consultar_movimientos_banco($id_banco, $fi, $fi);
            $total_api_calls++;
            if (!empty($r['success'])) $api_respondio = true;
            if (!empty($r['success']) && !empty($r['movs'])) {
                $todos_movs = array_merge($todos_movs, $r['movs']);
            }
            $actual->modify('+1 day');
            continue;
        }
        $bloque_fin = clone $actual;
        $dias_hasta_sab = 6 - $dia_sem;
        if ($dias_hasta_sab > 0) $bloque_fin->modify("+{$dias_hasta_sab} days");
        if ($bloque_fin > $final) $bloque_fin = $final;

        $fi = $actual->format('Y-m-d');
        $ff = $bloque_fin->format('Y-m-d');
        $r  = consultar_movimientos_banco($id_banco, $fi, $ff);
        $total_api_calls++;
        if (!empty($r['success'])) $api_respondio = true;
        if (!empty($r['success']) && !empty($r['movs'])) {
            $todos_movs = array_merge($todos_movs, $r['movs']);
        }

        $actual = clone $bloque_fin;
        $actual->modify('+1 day');
    }

    return [
        'success'         => !empty($todos_movs),
        'movs'            => $todos_movs,
        'api_respondio'   => $api_respondio,
        'total_api_calls' => $total_api_calls,
    ];
}

/**
 * Obtiene todas las transacciones de Crédito del banco BDV de los últimos 30 días.
 * Sin límite de cantidad — busca en TODOS los créditos disponibles.
 *
 * @param  int $id_banco  ID del banco en bancos.json.
 *
 * @return array [
 *   'success'       => bool,
 *   'movs'          => array,  // Todos los créditos encontrados (sin límite)
 *   'api_respondio' => bool,
 * ]
 */
function obtener_creditos_recientes(int $id_banco): array
{
    $hoy = (new \DateTime('now', new \DateTimeZone('America/Caracas')))->format('Y-m-d');
    $fecha_ini = date('Y-m-d', strtotime('-30 days', strtotime($hoy)));

    $resultado = consultar_movimientos_rango($id_banco, $fecha_ini, $hoy);

    if (!$resultado['api_respondio']) {
        return ['success' => false, 'movs' => [], 'api_respondio' => false];
    }

    $creditos = [];
    foreach ($resultado['movs'] as $mov) {
        $tipo = strtoupper($mov['mov'] ?? $mov['Tipo'] ?? '');
        $desc = strtoupper($mov['descripcion'] ?? '');
        if ($tipo === 'CREDITO' && strpos($desc, 'DEBITO') === false) {
            $creditos[] = $mov;
        }
    }

    return [
        'success'       => !empty($creditos),
        'movs'          => $creditos,
        'api_respondio' => $resultado['api_respondio'],
    ];
}

/**
 * Busca una referencia en un array de movimientos.
 * Compara referencia completa y los últimos 6 y 8 dígitos.
 * Solo busca en movimientos de tipo CREDITO.
 *
 * @param  array  $movs        Array de movimientos BDV.
 * @param  string $referencia  Referencia a buscar (solo dígitos, 6-15).
 * @param  string $metodo_pago 'Transferencia' u otro (Pago Móvil, etc.).
 *
 * @return array|null  El movimiento encontrado o null.
 */
function buscar_referencia_en_movs(array $movs, string $referencia, string $metodo_pago = ''): ?array
{
    $ref_user_clean = preg_replace('/\D/', '', $referencia);

    if ($metodo_pago === 'Transferencia') {
        $ref_user_6 = strlen($ref_user_clean) >= 6 ? substr($ref_user_clean, -6) : $ref_user_clean;
        $ref_user_8 = strlen($ref_user_clean) >= 8 ? substr($ref_user_clean, -8) : $ref_user_clean;
    } else {
        $ref_search = $ref_user_clean;
        if (strlen($ref_search) > 8) $ref_search = substr($ref_search, -8);
        $ref_user_6 = strlen($ref_search) >= 6 ? substr($ref_search, -6) : $ref_search;
        $ref_user_8 = $ref_search;
    }

    foreach ($movs as $mov) {
        $tipo = strtoupper($mov['mov'] ?? $mov['Tipo'] ?? '');
        $desc = strtoupper($mov['descripcion'] ?? '');
        if ($tipo !== 'CREDITO' || strpos($desc, 'DEBITO') !== false) continue;
        if (!isset($mov['referencia'])) continue;

        $ref_banco_clean = preg_replace('/\D/', '', $mov['referencia']);
        $ref_banco_6 = strlen($ref_banco_clean) >= 6 ? substr($ref_banco_clean, -6) : $ref_banco_clean;
        $ref_banco_8 = strlen($ref_banco_clean) >= 8 ? substr($ref_banco_clean, -8) : $ref_banco_clean;

        if (
            $ref_banco_clean === $ref_user_clean ||
            ($ref_banco_8 !== '' && $ref_banco_8 === $ref_user_8) ||
            ($ref_banco_6 !== '' && $ref_banco_6 === $ref_user_6)
        ) {
            return $mov;
        }
    }

    return null;
}
