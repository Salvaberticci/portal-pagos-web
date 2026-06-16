<?php
/**
 * MikrotikController.php
 * Controlador central para todas las operaciones con la API de MikroTik.
 * 
 * MODO DRY RUN: Cuando un router tiene dry_run=1, los comandos NO se envían
 * al router real. Solo se registran en mikrotik_logs para verificación.
 * 
 * Cuando el ISP confirme la técnica (PPPoE / Address List / Queues),
 * actualizar los métodos cortarServicio() y activarServicio() con
 * los comandos RouterOS específicos.
 */

class MikrotikController
{
    // ─── Técnica de corte (se definirá cuando el ISP responda) ─────────────
    // Opciones: 'address_list' | 'pppoe' | 'simple_queue'
    const TECNICA_CORTE = 'address_list'; // ← CAMBIAR SEGÚN RESPUESTA DEL ISP

    // Nombre del Address List de morosos en el MikroTik
    const ADDRESS_LIST_MOROSOS = 'Morosos';

    // ─── Conexión a la base de datos ───────────────────────────────────────
    private static function getDB()
    {
        static $conn = null;
        if ($conn === null) {
            $base = dirname(__FILE__) . '/../conexion.php';
            require_once $base;
            $conn = $GLOBALS['conn'];
        }
        return $conn;
    }

    // ─── Obtener el router principal activo ───────────────────────────────
    public static function getRouterActivo($id_router = null)
    {
        $conn = self::getDB();
        if ($id_router) {
            $stmt = $conn->prepare("SELECT * FROM mikrotik_routers WHERE id = ? AND activo = 1 LIMIT 1");
            $stmt->bind_param("i", $id_router);
        } else {
            $stmt = $conn->prepare("SELECT * FROM mikrotik_routers WHERE activo = 1 ORDER BY id ASC LIMIT 1");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    // ─── Registrar operación en el log ────────────────────────────────────
    private static function registrarLog(array $data): int
    {
        $conn = self::getDB();
        $stmt = $conn->prepare("
            INSERT INTO mikrotik_logs 
                (id_router, id_contrato, nombre_cliente, accion, ip_cliente, mac_cliente, comando, estado, mensaje_error, ejecutado_por, origen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iisssssssss",
            $data['id_router'],
            $data['id_contrato'],
            $data['nombre_cliente'],
            $data['accion'],
            $data['ip_cliente'],
            $data['mac_cliente'],
            $data['comando'],
            $data['estado'],
            $data['mensaje_error'],
            $data['ejecutado_por'],
            $data['origen']
        );
        $stmt->execute();
        return $conn->insert_id;
    }

    // ─── Intentar conexión real al MikroTik via RouterOS API ──────────────
    // Se activará cuando la librería PEAR2/Net_RouterOS esté instalada y
    // el ISP confirme la técnica de corte.
    private static function conectar(array $router)
    {
        // Verificar si la librería está disponible
        $autoload = dirname(__FILE__) . '/../../vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new Exception("Librería RouterOS no instalada. Ejecuta: composer require pear2/net_routeros");
        }

        require_once $autoload;

        $client = new \PEAR2\Net\RouterOS\Client(
            $router['ip'],
            $router['usuario'],
            $router['contrasena'],
            $router['puerto']
        );
        return $client;
    }

    // ─── CORTAR SERVICIO ──────────────────────────────────────────────────
    /**
     * Corta el servicio de un cliente en el MikroTik.
     *
     * @param string $ip_cliente  IP de la ONU del cliente (campo ip_onu en contratos)
     * @param int    $id_contrato ID del contrato
     * @param string $nombre_cliente Nombre del cliente (para el log)
     * @param string $mac_cliente MAC de la ONU (opcional, campo mac_onu en contratos)
     * @param int    $id_router   ID específico del router (null = usar el primero activo)
     * @param string $ejecutado_por Nombre del usuario que ejecuta la acción
     * @return array ['success' => bool, 'dry_run' => bool, 'mensaje' => string, 'log_id' => int]
     */
    public static function cortarServicio(
        string $ip_cliente,
        int $id_contrato = 0,
        string $nombre_cliente = '',
        string $mac_cliente = '',
        int $id_router = null,
        string $ejecutado_por = 'SISTEMA',
        string $origen = 'manual'
    ): array {
        $router = self::getRouterActivo($id_router);

        if (!$router) {
            return [
                'success' => false,
                'dry_run' => false,
                'mensaje' => 'No hay ningún router MikroTik configurado y activo.',
                'log_id'  => null
            ];
        }

        // Generar el comando RouterOS según la técnica configurada
        $comando = self::generarComandoCorte($ip_cliente, $mac_cliente);

        // ── MODO DRY RUN ──────────────────────────────────────────────────
        if ($router['dry_run'] == 1) {
            $log_id = self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => $id_contrato,
                'nombre_cliente' => $nombre_cliente,
                'accion'         => 'CORTE',
                'ip_cliente'     => $ip_cliente,
                'mac_cliente'    => $mac_cliente,
                'comando'        => $comando,
                'estado'         => 'DRY_RUN',
                'mensaje_error'  => null,
                'ejecutado_por'  => $ejecutado_por,
                'origen'         => $origen,
            ]);

            return [
                'success'  => true,
                'dry_run'  => true,
                'mensaje'  => "[DRY RUN] Comando que se enviaría: $comando",
                'log_id'   => $log_id
            ];
        }

        // ── MODO REAL ─────────────────────────────────────────────────────
        try {
            // TODO: Implementar envío real cuando ISP confirme técnica
            // $client = self::conectar($router);
            // ... enviar $comando al router ...
            throw new Exception("Modo real aún no implementado. Esperando confirmación del ISP sobre técnica de corte.");

        } catch (Exception $e) {
            $log_id = self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => $id_contrato,
                'nombre_cliente' => $nombre_cliente,
                'accion'         => 'CORTE',
                'ip_cliente'     => $ip_cliente,
                'mac_cliente'    => $mac_cliente,
                'comando'        => $comando,
                'estado'         => 'ERROR',
                'mensaje_error'  => $e->getMessage(),
                'ejecutado_por'  => $ejecutado_por,
                'origen'         => $origen,
            ]);

            return [
                'success' => false,
                'dry_run' => false,
                'mensaje' => 'Error al conectar con MikroTik: ' . $e->getMessage(),
                'log_id'  => $log_id
            ];
        }
    }

    // ─── RECONECTAR / ACTIVAR SERVICIO ────────────────────────────────────
    /**
     * Reconecta el servicio de un cliente (tras pago o prórroga).
     */
    public static function activarServicio(
        string $ip_cliente,
        int $id_contrato = 0,
        string $nombre_cliente = '',
        string $mac_cliente = '',
        int $id_router = null,
        string $ejecutado_por = 'SISTEMA',
        string $origen = 'manual'
    ): array {
        $router = self::getRouterActivo($id_router);

        if (!$router) {
            return [
                'success' => false,
                'dry_run' => false,
                'mensaje' => 'No hay ningún router MikroTik configurado y activo.',
                'log_id'  => null
            ];
        }

        $comando = self::generarComandoReconexion($ip_cliente, $mac_cliente);

        if ($router['dry_run'] == 1) {
            $log_id = self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => $id_contrato,
                'nombre_cliente' => $nombre_cliente,
                'accion'         => 'RECONEXION',
                'ip_cliente'     => $ip_cliente,
                'mac_cliente'    => $mac_cliente,
                'comando'        => $comando,
                'estado'         => 'DRY_RUN',
                'mensaje_error'  => null,
                'ejecutado_por'  => $ejecutado_por,
                'origen'         => $origen,
            ]);

            return [
                'success' => true,
                'dry_run' => true,
                'mensaje' => "[DRY RUN] Comando que se enviaría: $comando",
                'log_id'  => $log_id
            ];
        }

        try {
            // TODO: Implementar envío real
            throw new Exception("Modo real aún no implementado. Esperando confirmación del ISP sobre técnica de corte.");

        } catch (Exception $e) {
            $log_id = self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => $id_contrato,
                'nombre_cliente' => $nombre_cliente,
                'accion'         => 'RECONEXION',
                'ip_cliente'     => $ip_cliente,
                'mac_cliente'    => $mac_cliente,
                'comando'        => $comando,
                'estado'         => 'ERROR',
                'mensaje_error'  => $e->getMessage(),
                'ejecutado_por'  => $ejecutado_por,
                'origen'         => $origen,
            ]);

            return [
                'success' => false,
                'dry_run' => false,
                'mensaje' => 'Error al conectar con MikroTik: ' . $e->getMessage(),
                'log_id'  => $log_id
            ];
        }
    }

    // ─── PING / TEST DE CONEXIÓN ──────────────────────────────────────────
    public static function testConexion(int $id_router): array
    {
        $router = self::getRouterActivo($id_router);
        if (!$router) {
            return ['success' => false, 'mensaje' => 'Router no encontrado.'];
        }

        if ($router['dry_run'] == 1) {
            self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => null,
                'nombre_cliente' => null,
                'accion'         => 'TEST',
                'ip_cliente'     => $router['ip'],
                'mac_cliente'    => null,
                'comando'        => '/system/identity/print',
                'estado'         => 'DRY_RUN',
                'mensaje_error'  => null,
                'ejecutado_por'  => $_SESSION['nombre_completo'] ?? 'SISTEMA',
                'origen'         => 'gestion_mikrotik.php',
            ]);
            return [
                'success' => true,
                'dry_run' => true,
                'mensaje' => "[DRY RUN] Se conectaría a {$router['ip']}:{$router['puerto']} con usuario '{$router['usuario']}'"
            ];
        }

        try {
            $client = self::conectar($router);
            $util = new \PEAR2\Net\RouterOS\Util($client);
            $identity = $util->setMenu('/system/identity')->getAll();
            $nombre = '';
            foreach ($identity as $item) {
                $nombre = $item('name');
            }
            self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => null,
                'nombre_cliente' => null,
                'accion'         => 'TEST',
                'ip_cliente'     => $router['ip'],
                'mac_cliente'    => null,
                'comando'        => '/system/identity/print',
                'estado'         => 'EXITO',
                'mensaje_error'  => null,
                'ejecutado_por'  => $_SESSION['nombre_completo'] ?? 'SISTEMA',
                'origen'         => 'gestion_mikrotik.php',
            ]);
            return ['success' => true, 'dry_run' => false, 'mensaje' => "Conexión exitosa. Router: $nombre"];

        } catch (Exception $e) {
            self::registrarLog([
                'id_router'      => $router['id'],
                'id_contrato'    => null,
                'nombre_cliente' => null,
                'accion'         => 'TEST',
                'ip_cliente'     => $router['ip'],
                'mac_cliente'    => null,
                'comando'        => '/system/identity/print',
                'estado'         => 'ERROR',
                'mensaje_error'  => $e->getMessage(),
                'ejecutado_por'  => $_SESSION['nombre_completo'] ?? 'SISTEMA',
                'origen'         => 'gestion_mikrotik.php',
            ]);
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ─── Generadores de comandos RouterOS ─────────────────────────────────

    private static function generarComandoCorte(string $ip, string $mac = ''): string
    {
        switch (self::TECNICA_CORTE) {
            case 'address_list':
                // Agrega la IP del cliente a la lista de Morosos del Firewall
                return "/ip/firewall/address-list/add list=" . self::ADDRESS_LIST_MOROSOS . " address=$ip comment=corte_automatico";

            case 'pppoe':
                // Deshabilitar el Secret PPPoE del cliente
                // El "name" del PPPoE deberá ajustarse (quizas sea la cédula o usuario único)
                return "/ppp/secret/set [find name=$ip] disabled=yes";

            case 'simple_queue':
                // Limitar velocidad a mínimo (corte efectivo)
                return "/queue/simple/set [find target=$ip/32] max-limit=1k/1k";

            default:
                return "/ip/firewall/address-list/add list=" . self::ADDRESS_LIST_MOROSOS . " address=$ip";
        }
    }

    private static function generarComandoReconexion(string $ip, string $mac = ''): string
    {
        switch (self::TECNICA_CORTE) {
            case 'address_list':
                // Remover la IP del cliente de la lista de Morosos
                return "/ip/firewall/address-list/remove [find list=" . self::ADDRESS_LIST_MOROSOS . " address=$ip]";

            case 'pppoe':
                return "/ppp/secret/set [find name=$ip] disabled=no";

            case 'simple_queue':
                // Restaurar velocidad normal (requiere conocer el plan del cliente)
                return "/queue/simple/set [find target=$ip/32] max-limit=10M/10M";

            default:
                return "/ip/firewall/address-list/remove [find list=" . self::ADDRESS_LIST_MOROSOS . " address=$ip]";
        }
    }
}
