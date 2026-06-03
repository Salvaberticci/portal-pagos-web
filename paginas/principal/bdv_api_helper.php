<?php
/**
 * bdv_api_helper.php
 * Módulo de integración con la API Consulta de Movimientos del Banco de Venezuela (Producción).
 * 
 * Endpoint: https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos
 * Método:   POST
 * Header:   X-API-KEY: 650D973744E70DFD936382F9B734405A
 */

define('BDV_API_URL',  'https://bdvconciliacion.banvenez.com:443/apis/bdv/consulta/movimientos');
define('BDV_API_KEY',  '650D973744E70DFD936382F9B734405A');

// Número de cuenta mercantil BDV por defecto para consultas
define('BDV_CUENTA_DEFECTO', '01020589150000001371');

// IDs de banco en bancos.json que corresponden a BDV (Pago Móvil y Transferencia)
define('BDV_IDS_BANCO', [9, 12]);

/**
 * Consulta los movimientos bancarios del Banco de Venezuela para un rango de fechas.
 *
 * @param string $cuenta      Número de cuenta a consultar (20 dígitos).
 * @param string $fechaIni    Fecha inicio en formato Y-m-d o d/m/Y.
 * @param string $fechaFin    Fecha fin en formato Y-m-d o d/m/Y.
 * @param string $nroMovimiento  Opcional: número de movimiento específico.
 *
 * @return array  [
 *   'success'   => bool,
 *   'message'   => string,       // mensaje de error o éxito
 *   'movs'      => array,        // array de movimientos (puede ser vacío)
 *   'raw'       => mixed,        // respuesta cruda de la API (para depuración)
 * ]
 */
function consultar_movimientos_bdv(string $cuenta, string $fechaIni, string $fechaFin, string $nroMovimiento = ''): array {
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

    $payload = json_encode([
        'cuenta'        => $cuenta,
        'fechaIni'      => $fechaIni,
        'fechaFin'      => $fechaFin,
        'tipoMoneda'    => 'VES',
        'nroMovimiento' => $nroMovimiento,
    ]);

    $ch = curl_init(BDV_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-KEY: ' . BDV_API_KEY,
        ],
    ]);

    $respuesta   = curl_exec($ch);
    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error  = curl_error($ch);
    curl_close($ch);

    // Error de red
    if ($respuesta === false || !empty($curl_error)) {
        return [
            'success' => false,
            'message' => 'Error de conexión con la API BDV: ' . $curl_error,
            'movs'    => [],
            'raw'     => null,
        ];
    }

    $data = json_decode($respuesta, true);

    // Error HTTP
    if ($http_code < 200 || $http_code >= 300) {
        return [
            'success' => false,
            'message' => "HTTP $http_code desde API BDV. Respuesta: " . $respuesta,
            'movs'    => [],
            'raw'     => $data,
        ];
    }

    // La API retorna code "1000" para consulta exitosa según la documentación
    $code = $data['code'] ?? $data['status'] ?? null;
    if ($code != '1000' && $code != 200) {
        return [
            'success' => false,
            'message' => 'API BDV respondió con código: ' . ($data['message'] ?? $code),
            'movs'    => [],
            'raw'     => $data,
        ];
    }

    $movs = $data['data']['movs'] ?? [];

    return [
        'success' => true,
        'message' => $data['message'] ?? 'consulta exitosa',
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
function buscar_movimiento_bdv(array $movimientos, string $referencia, float $monto_bs, float $tolerancia = 1.00): ?array {
    $ref_limpia = preg_replace('/\D/', '', $referencia); // solo dígitos
    $ref_6 = strlen($ref_limpia) >= 6 ? substr($ref_limpia, -6) : $ref_limpia;

    foreach ($movimientos as $mov) {
        // Solo considerar créditos (pagos recibidos)
        $tipo = strtoupper($mov['mov'] ?? '');
        if ($tipo !== 'CREDITO') {
            continue;
        }

        // Limpiar la referencia del movimiento bancario
        $ref_banco_limpia = preg_replace('/\D/', '', $mov['referencia'] ?? '');
        $ref_banco_6      = strlen($ref_banco_limpia) >= 6 ? substr($ref_banco_limpia, -6) : $ref_banco_limpia;

        // Verificar coincidencia de referencia (exacta o últimos 6 dígitos)
        $ref_match = (
            $ref_banco_limpia === $ref_limpia ||
            $ref_banco_6      === $ref_6
        );

        if (!$ref_match) {
            continue;
        }

        // Verificar coincidencia de monto
        $importe_banco = floatval(str_replace(',', '.', preg_replace('/[^\d,.]/', '', $mov['importe'] ?? '0')));
        if (abs($importe_banco - $monto_bs) <= $tolerancia) {
            return $mov;
        }
    }

    return null;
}

/**
 * Convierte una fecha en formato Y-m-d a DD/MM/YYYY.
 * Si ya viene en DD/MM/YYYY, la retorna tal cual.
 *
 * @param string $fecha
 * @return string|null
 */
function _bdv_normalizar_fecha(string $fecha): ?string {
    $fecha = trim($fecha);

    // Ya en formato DD/MM/YYYY
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
        return $fecha;
    }

    // Formato Y-m-d (ISO)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $parts = explode('-', $fecha);
        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }

    return null;
}
