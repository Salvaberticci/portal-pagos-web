<?php
/**
 * banco_api_router.php
 * Router genérico de APIs bancarias.
 *
 * Uso:
 *   require_once 'banco_api_router.php';
 *   $result = consultar_movimientos_banco($id_banco, '2025-01-01', '2025-01-31');
 *
 * El router:
 *   1. Lee el api_config del banco desde bancos.json.
 *   2. Verifica que la API esté habilitada.
 *   3. Delega al helper correcto según el campo `tipo`.
 *
 * Para agregar un nuevo banco en el futuro:
 *   - Crear el helper (ej: banesco_api_helper.php)
 *   - Agregar un case en el switch de consultar_movimientos_banco()
 */

require_once __DIR__ . '/bdv_api_helper.php';

/**
 * Consulta los movimientos de un banco usando su API configurada.
 *
 * @param  int|string $id_banco   ID del banco en bancos.json.
 * @param  string     $fechaIni   Fecha inicio (Y-m-d o d/m/Y).
 * @param  string     $fechaFin   Fecha fin (Y-m-d o d/m/Y).
 * @param  string     $nroMovimiento  Opcional: referencia específica a buscar.
 *
 * @return array [
 *   'success' => bool,
 *   'message' => string,
 *   'movs'    => array,
 *   'raw'     => mixed,
 *   'tipo'    => string,   // tipo de API usada, ej. 'bdv'
 * ]
 */
function consultar_movimientos_banco($id_banco, string $fechaIni, string $fechaFin, string $nroMovimiento = ''): array
{
    // 1. Leer configuración del banco
    $cfg = obtener_config_api_banco($id_banco);

    if ($cfg === null) {
        return [
            'success' => false,
            'message' => "El banco #$id_banco no tiene una API habilitada o configurada.",
            'movs'    => [],
            'raw'     => null,
            'tipo'    => null,
        ];
    }

    $tipo     = $cfg['tipo']     ?? '';
    $api_key  = $cfg['api_key']  ?? '';
    $cuenta   = $cfg['cuenta']   ?? '';
    $endpoint = $cfg['endpoint'] ?? '';

    // 2. Delegar al helper correcto
    switch ($tipo) {

        case 'bdv':
            $result = consultar_movimientos_bdv(
                $cuenta,
                $fechaIni,
                $fechaFin,
                $nroMovimiento,
                $api_key,
                $endpoint
            );
            $result['tipo'] = 'bdv';
            return $result;

        // ── Futuros bancos ────────────────────────────────────────────────
        // case 'banesco':
        //     require_once __DIR__ . '/banesco_api_helper.php';
        //     $result = consultar_movimientos_banesco($cuenta, $fechaIni, $fechaFin, $api_key, $endpoint);
        //     $result['tipo'] = 'banesco';
        //     return $result;
        //
        // case 'mercantil':
        //     require_once __DIR__ . '/mercantil_api_helper.php';
        //     $result = consultar_movimientos_mercantil($cuenta, $fechaIni, $fechaFin, $api_key, $endpoint);
        //     $result['tipo'] = 'mercantil';
        //     return $result;
        // ─────────────────────────────────────────────────────────────────

        default:
            return [
                'success' => false,
                'message' => "Tipo de API '$tipo' no soportado por el router.",
                'movs'    => [],
                'raw'     => null,
                'tipo'    => $tipo,
            ];
    }
}

/**
 * Busca un movimiento específico en TODOS los bancos que tengan API habilitada
 * del tipo dado. Útil para la auto-verificación cuando no sabemos qué banco
 * específico usó el cliente.
 *
 * @param  string $tipo          Tipo de API, ej. 'bdv'.
 * @param  string $fechaIni      Fecha inicio.
 * @param  string $fechaFin      Fecha fin.
 * @param  string $referencia    Referencia del pago.
 * @param  float  $monto_bs      Monto en Bs.
 * @param  float  $tolerancia    Tolerancia de monto (default Bs 1.00).
 *
 * @return array|null  El movimiento encontrado o null.
 */
function buscar_movimiento_en_bancos(
    string $tipo,
    string $fechaIni,
    string $fechaFin,
    string $referencia,
    float  $monto_bs,
    float  $tolerancia = 1.00
): ?array {
    // Obtener IDs de bancos con API habilitada del tipo dado
    $ids = obtener_ids_banco_con_api($tipo);

    // Usar IDs únicos para no hacer llamadas duplicadas a la misma cuenta
    $cuentas_consultadas = [];

    foreach ($ids as $id_banco) {
        $cfg    = obtener_config_api_banco($id_banco);
        $cuenta = $cfg['cuenta'] ?? '';

        // Evitar consultar la misma cuenta dos veces
        if (in_array($cuenta, $cuentas_consultadas)) {
            continue;
        }
        $cuentas_consultadas[] = $cuenta;

        $result = consultar_movimientos_banco($id_banco, $fechaIni, $fechaFin);
        if (!$result['success']) {
            continue;
        }

        // Usar el buscador del tipo correcto
        $movimiento = null;
        switch ($tipo) {
            case 'bdv':
                $movimiento = buscar_movimiento_bdv($result['movs'], $referencia, $monto_bs, $tolerancia);
                break;
        }

        if ($movimiento !== null) {
            return $movimiento;
        }
    }

    return null;
}
