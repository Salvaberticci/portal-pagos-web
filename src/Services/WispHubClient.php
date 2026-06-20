<?php

namespace Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WispHubClient
{
    /** @var string Base URL (sandbox o producci├│n), siempre con trailing slash */
    private $baseUrl;
    /** @var string API key */
    private $apiKey;
    /** @var Client Guzzle HTTP client */
    private $http;
    /** @var float Tiempo del ├║ltimo request (para rate limiting) */
    private $lastRequestTime = 0;
    /** @var int Microsegundos m├¡nimos entre requests */
    private $minIntervalUs = 0;
    /** @var array Cache per-request para evitar duplicar llamadas GET */
    private $requestCache = [];

    /**
     * Constructor
     * @param array $config ['base_url' => ..., 'api_key' => ..., 'api_secret' => ...]
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.wisphub.net/api', '/') . '/';
        $this->apiKey  = $config['api_key'] ?? '';

        $verifySsl = $config['verify_ssl'] ?? true;

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 5,
            'verify'   => $verifySsl,
            'headers'  => [
                'Authorization' => "Api-Key {$this->apiKey}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /**
     * Realiza una petici├│n HTTP a la API de WispHub.
     * Incluye rate limiting b├ísico (200ms entre requests).
     */
    private function request(string $method, string $uri, array $data = []): array
    {
        // Per-request cache: deduplica llamadas GET id├®nticas en el mismo proceso
        $cacheKey = '';
        if ($method === 'GET') {
            $cacheKey = md5($method . "\0" . $uri . "\0" . json_encode($data));
            if (isset($this->requestCache[$cacheKey])) {
                return $this->requestCache[$cacheKey];
            }
        }

        $now = microtime(true);
        $elapsed = ($now - $this->lastRequestTime) * 1_000_000;
        if ($elapsed < $this->minIntervalUs) {
            usleep((int)($this->minIntervalUs - $elapsed));
        }
        $this->lastRequestTime = microtime(true);

        $opts = [];
        if (!empty($data)) {
            if ($method === 'GET') {
                $opts['query'] = $data;
            } else {
                $opts['body'] = json_encode($data);
            }
        }

        $maxAttempts = 1;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->http->request($method, $uri, $opts);
                $status = $response->getStatusCode();
                $body   = (string) $response->getBody();
                $json   = json_decode($body, true);
                $result = ['status' => $status, 'data' => $json];
                if ($cacheKey) {
                    $this->requestCache[$cacheKey] = $result;
                }
                return $result;
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $status = $e->getResponse()->getStatusCode();
                    $body   = (string) $e->getResponse()->getBody();
                    $json   = json_decode($body, true);
                    if ($status >= 500 && $status <= 599 && $attempt < $maxAttempts) {
                        $delay = $attempt * 1000000;
                        error_log("[WispHubClient] Reintento $attempt/$maxAttempts tras HTTP $status, esperando " . ($delay/1000000) . "s");
                        usleep($delay);
                        continue;
                    }
                    $result = ['status' => $status, 'data' => $json, 'error' => $body];
                    if ($cacheKey) {
                        $this->requestCache[$cacheKey] = $result;
                    }
                    return $result;
                }
                if ($attempt < $maxAttempts) {
                    $delay = $attempt * 1000000;
                    error_log("[WispHubClient] Reintento $attempt/$maxAttempts sin respuesta: " . $e->getMessage() . ", esperando " . ($delay/1000000) . "s");
                    usleep($delay);
                    continue;
                }
                error_log('[WispHubClient] RequestException sin respuesta: ' . $e->getMessage());
                return ['status' => 0, 'error' => $e->getMessage()];
            }
        }
        return ['status' => 0, 'error' => 'Max retries exceeded'];
    }

    // -----------------------------------------------------------------
    //  M├®todos de la API
    // -----------------------------------------------------------------

    /**
     * Activa el servicio de uno o m├ís clientes en WispHub.
     * Endpoint: POST /clientes/activar/
     * Body: { "servicios": [id, ...] }
     */
    public function activateService(string $serviceId): array
    {
        return $this->request('POST', 'clientes/activar/', [
            'servicios' => [$serviceId],
        ]);
    }

    /**
     * Suspende (desactiva) el servicio de uno o m├ís clientes en WispHub.
     * Endpoint: POST /clientes/desactivar/
     * Body: { "servicios": [id, ...], "motivo": "..." }
     */
    public function suspendService(string $serviceId, string $reason = ''): array
    {
        $data = ['servicios' => [$serviceId]];
        if ($reason) {
            $data['motivo'] = $reason;
        }
        return $this->request('POST', 'clientes/desactivar/', $data);
    }

    /**
     * Da de baja definitivamente un servicio.
     */
    public function deactivateService(string $serviceId, string $reason = ''): array
    {
        return $this->suspendService($serviceId, $reason ?: 'Baja definitiva del servicio');
    }

    /**
     * ID de forma de pago "Operacion Bancaria" en WispHub.
     */
    const FORMA_PAGO_OPERACION_BANCARIA = 45181;
    /**
     * ID de la acci├│n "Pagar" en WispHub.
     */
    const ACCION_PAGAR = 1;

    /**
     * Notifica a WispHub que se ha registrado un pago contra una factura.
     * Endpoint: POST /facturas/{id_factura}/registrar-pago/
     *
     * Datos esperados:
     *   - invoice_id (int)        ÔåÆ ID de la factura en WispHub
     *   - total_cobrado (float)   ÔåÆ Monto pagado
     *   - referencia (string)     ÔåÆ N├║mero de referencia
     *   - fecha_pago (string)     ÔåÆ "YYYY-MM-DD HH:MM"
     *   - forma_pago (int, opc)   ÔåÆ ID forma de pago WispHub (default 45181)
     *   - accion (int, opc)       ÔåÆ ID acci├│n (default 1)
     */
    public function notifyPayment(array $paymentData): array
    {
        $invoiceId   = $paymentData['invoice_id'] ?? 0;
        $amount      = $paymentData['total_cobrado'] ?? $paymentData['amount_usd'] ?? 0;
        $reference   = $paymentData['referencia'] ?? $paymentData['reference'] ?? '';
        $paymentDate = $paymentData['fecha_pago'] ?? $paymentData['date'] ?? date('Y-m-d H:i');
        $formaPago   = $paymentData['forma_pago'] ?? self::FORMA_PAGO_OPERACION_BANCARIA;
        $accion      = $paymentData['accion'] ?? self::ACCION_PAGAR;

        if (!$invoiceId) {
            return ['status' => 400, 'error' => 'invoice_id requerido para registrar pago en WispHub'];
        }

        $data = [
            'forma_pago'    => $formaPago,
            'accion'        => $accion,
            'fecha_pago'    => $paymentDate,
            'referencia'    => $reference,
            'total_cobrado' => $amount,
        ];
        return $this->request('POST', "facturas/{$invoiceId}/registrar-pago/", $data);
    }

    /**
     * Obtiene las facturas pendientes de un cliente.
     * Endpoint: GET /clientes/{id_servicio}/saldo/
     * Retorna array con las facturas pendientes (campo 'facturas').
     */
    public function getPendingInvoices(string $serviceId): array
    {
        $result = $this->request('GET', "clientes/{$serviceId}/saldo/");
        if ($result['status'] === 200 && !empty($result['data']['facturas'])) {
            return $result['data']['facturas'];
        }
        if ($result['status'] !== 200) {
            error_log('[WispHubClient] getPendingInvoices fall├│ para service ' . $serviceId
                . ' status=' . ($result['status'] ?? 0)
                . ' error=' . ($result['error'] ?? 'sin error'));
        }
        return [];
    }

    /**
     * Consulta facturas en WispHub con filtros.
     * Endpoint: GET /facturas/
     *
     * Filtros disponibles:
     *   - estado: 1=Pendiente, 2=Pagada, 3=Cancelada, 4=En Revision, 5=Transferida
     *   - cliente: nombre de usuario (ej. "usuario@empresa")
     *   - fecha_pago__range_0, fecha_pago__range_1: rango de fecha de pago (YYYY-MM-DD)
     *   - fecha_emision__range_0, fecha_emision__range_1: rango de emision
     *   - fecha_vencimiento__range_0, fecha_vencimiento__range_1: rango de vencimiento
     *   - limit: max resultados por pagina (default 10)
     *   - offset: desplazamiento
     *
     * @param array $filters Asociativo de query params
     * @return array Lista de facturas (results del JSON de WispHub)
     */
    public function getInvoices(array $filters = []): array
    {
        $result = $this->request('GET', 'facturas/', $filters);
        if ($result['status'] === 200 && !empty($result['data']['results'])) {
            return $result['data']['results'];
        }
        if ($result['status'] !== 200) {
            error_log('[WispHubClient] getInvoices fall├│ filters=' . json_encode($filters)
                . ' status=' . ($result['status'] ?? 0));
        }
        return [];
    }

    /**
     * Verifica si una referencia de pago ya fue usada en WispHub.
     * Consulta facturas pagadas filtradas por referencia.
     */
    public function isReferenceUsed(string $referencia): bool
    {
        $invoices = $this->getInvoices([
            'estado'     => 2,
            'referencia' => $referencia,
            'limit'      => 1,
        ]);
        return !empty($invoices);
    }

    /**
     * Obtiene la ├║ltima factura pagada de un cliente por su nombre de usuario.
     *
     * @param string $usuario Nombre de usuario en WispHub (ej. "usuario@empresa")
     * @return array|null {monto, fecha_pago, referencia} o null si no hay
     */
    public function getLastPaidInvoice(string $usuario): ?array
    {
        $invoices = $this->getInvoices([
            'estado'               => 2,
            'cliente'              => $usuario,
            'limit'                => 1,
            'ordering'             => '-id',
            'fecha_pago__range_0'  => date('Y-m-d', strtotime('-90 days')),
            'fecha_pago__range_1'  => date('Y-m-d'),
        ]);
        if (!empty($invoices[0])) {
            $inv = $invoices[0];
            return [
                'id'         => $inv['id'] ?? $inv['id_factura'] ?? 0,
                'monto'      => floatval($inv['total_cobrado'] ?? $inv['total'] ?? 0),
                'fecha_pago' => $inv['fecha_pago'] ?? '',
                'referencia' => $inv['referencia'] ?? '',
            ];
        }
        return null;
    }

    /**
     * Flujo completo: registra el pago en WispHub y activa el servicio.
     *
     * 1. (Opcional) Busca el service_id por c├®dula si se proporciona
     * 2. Obtiene facturas pendientes del cliente en WispHub
     * 3. Registra el pago contra cada factura pendiente
     * 4. Activa el servicio si sigue suspendido
     *
     * @param string $serviceId    ID del servicio en WispHub (ej. "902").
     *                             Se ignora si se proporciona $cedula.
     * @param float  $amount       Monto pagado en USD
     * @param string $reference    Referencia/n├║mero de pago
     * @param string $paymentDate  Fecha del pago "YYYY-MM-DD HH:MM"
     * @param int    $formaPagoId  ID forma de pago en WispHub (default 45181)
     * @param bool   $forceActivate Forzar activaci├│n aunque ya est├® activo
     * @param string $cedula       C├®dula del cliente (opcional). Si se pasa,
     *                             busca el service_id en WispHub autom├íticamente.
     * @return array Resultado con payments_registered[] y activation
     */
    public function registerPaymentAndActivate(
        string $serviceId,
        float  $amount,
        string $reference,
        string $paymentDate,
        int    $formaPagoId = self::FORMA_PAGO_OPERACION_BANCARIA,
        bool   $forceActivate = false,
        string $cedula = '',
        array  $invoiceIds = []
    ): array {
        // Si se pas├│ c├®dula, buscar service_id en WispHub en tiempo real
        if (!empty($cedula)) {
            $clientInfo = $this->getClientByDocument($cedula);
            if ($clientInfo['status'] !== 200 || empty($clientInfo['data']['data']['service_id'])) {
                $clientInfo = $this->findClientByDocument($cedula);
            }
            if ($clientInfo['status'] !== 200 || empty($clientInfo['data']['data']['service_id'])) {
                $msg = $clientInfo['data']['message'] ?? 'Cliente no encontrado en WispHub';
                return [
                    'service_id' => '',
                    'cedula'     => $cedula,
                    'status'     => $clientInfo['status'] ?: 404,
                    'error'      => $msg,
                ];
            }
            $serviceId = (string)$clientInfo['data']['data']['service_id'];
        }

        $results = [
            'service_id'          => $serviceId,
            'cedula'              => $cedula ?: null,
            'invoices_found'      => 0,
            'payments_registered' => [],
            'activation'          => null,
            'status'              => 200,
        ];

        // 1. Obtener facturas pendientes (ordenadas por vencimiento, m├ís antigua primero)
        $invoices = $this->getPendingInvoices($serviceId);

        // Filtrar solo las facturas seleccionadas si se especificaron IDs
        if (!empty($invoiceIds)) {
            $invoiceIds = array_map('strval', $invoiceIds);
            $invoices = array_values(array_filter($invoices, function ($inv) use ($invoiceIds) {
                return in_array(strval($inv['id'] ?? $inv['id_factura'] ?? ''), $invoiceIds, true);
            }));
        }

        $results['invoices_found'] = count($invoices);

        // 2. Distribuir el monto entre las facturas, pagando la m├ís antigua primero
        $remaining = $amount;
        foreach ($invoices as $invoice) {
            if (empty($invoice['id']) || $remaining <= 0) continue;

            $invoiceAmount = (float)($invoice['total'] ?? $invoice['monto'] ?? $invoice['monto_pendiente'] ?? $remaining);
            $toPay = min($remaining, $invoiceAmount);

            $payResult = $this->request('POST', "facturas/{$invoice['id']}/registrar-pago/", [
                'forma_pago'    => $formaPagoId,
                'accion'        => self::ACCION_PAGAR,
                'fecha_pago'    => $paymentDate,
                'referencia'    => $reference,
                'total_cobrado' => $toPay,
            ]);

            $results['payments_registered'][] = [
                'invoice_id'      => $invoice['id'],
                'invoice_amount'  => $invoiceAmount,
                'payment_applied' => $toPay,
                'status'          => $payResult['status'],
            ];

            if ($payResult['status'] !== 200 && $payResult['status'] !== 201) {
                $results['status'] = $payResult['status'];
            }

            $remaining -= $toPay;
        }

        $results['amount_total']  = $amount;
        $results['amount_applied'] = $amount - $remaining;
        $results['amount_unused']  = $remaining;

        // 3. Activar servicio si es necesario
        if ($forceActivate) {
            $results['activation'] = $this->activateService($serviceId);
        } else {
            $balance = $this->getServiceBalance($serviceId);
            $isActive = $balance['status'] === 200
                && !empty($balance['data']['estado'])
                && strtolower($balance['data']['estado']) === 'activo';

            if (!$isActive) {
                $results['activation'] = $this->activateService($serviceId);
            } else {
                $results['activation'] = ['status' => 200, 'data' => ['message' => 'Servicio ya activo']];
            }
        }

        return $results;
    }

    /**
     * Obtiene el perfil completo de un cliente/servicio de WispHub.
     */
    public function getServiceProfile(string $serviceId): array
    {
        return $this->request('GET', "clientes/{$serviceId}/perfil/");
    }

    /**
     * Obtiene el detalle completo del servicio (zona, plan_internet, estado, etc.).
     * Endpoint: GET /clientes/{id_servicio}/
     */
    public function getServiceDetail(string $serviceId): array
    {
        return $this->request('GET', "clientes/{$serviceId}/");
    }

    /**
     * Obtiene el saldo/deuda de un cliente.
     * Endpoint: GET /clientes/{id_servicio}/saldo/
     */
    public function getServiceBalance(string $serviceId): array
    {
        return $this->request('GET', "clientes/{$serviceId}/saldo/");
    }

    /**
     * Busca un cliente en WispHub por su n├║mero de c├®dula/documento.
     * Usa el filtro ?cedula= que es el ├║nico que funciona correctamente.
     * Prueba m├║ltiples formatos (con letra, sin letra, solo d├¡gitos).
     *
     * Retorna el service_id y datos completos del cliente.
     */
    public function getClientByDocument(string $document): array
    {
        $variantes = [$document];
        // Con letra → sin letra (V20788775 → 20788775)
        if (preg_match('/^[A-Z]/i', $document)) {
            $variantes[] = preg_replace('/^[A-Z]/i', '', $document);
        }
        // Solo d├¡gitos (V16533735-9 → 165337359)
        $soloDigitos = preg_replace('/[^0-9]/', '', $document);
        if (!in_array($soloDigitos, $variantes) && $soloDigitos !== '') {
            $variantes[] = $soloDigitos;
        }
        // Sin sufijo despu├®s de gui├│n (V16533735-9 → V16533735)
        $sinSufijo = preg_replace('/-.*$/', '', $document);
        if (!in_array($sinSufijo, $variantes)) {
            $variantes[] = $sinSufijo;
            // Y su versi├│n sin letra
            if (preg_match('/^[A-Z]/i', $sinSufijo)) {
                $sinSufijoNum = preg_replace('/^[A-Z]/i', '', $sinSufijo);
                if (!in_array($sinSufijoNum, $variantes)) {
                    $variantes[] = $sinSufijoNum;
                }
            }
        }
        $variantes = array_unique($variantes);
        foreach ($variantes as $ced) {
            $result = $this->request('GET', 'clientes/', ['cedula' => $ced]);
            if ($result['status'] === 200 && !empty($result['data']['results'])) {
                return ['status' => 200, 'data' => ['data' => $result['data']['results'][0]]];
            }
        }
        return ['status' => 404, 'data' => ['message' => 'Cliente no encontrado']];
    }

    /**
     * Busca un cliente en WispHub por su c├®dula/documento recorriendo
     * p├ígina por p├ígina el endpoint listClients con offset/limit.
     * ├Ütil si la API no expone un endpoint by-document.
     *
     * @param string $document C├®dula (ej: V20788775 o 20788775)
     * @param int    $maxPages M├íximo de p├íginas a recorrer (default 50)
     * @return array ['status' => 200|404, 'data' => [...datos del cliente...]]
     */
    public function findClientByDocument(string $document, int $maxPages = 50): array
    {
        $cleanDoc = preg_replace('/[^0-9]/', '', $document);
        $limit = 300;
        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->request('GET', 'clientes/', ['offset' => $page * $limit, 'limit' => $limit]);
            if ($result['status'] !== 200) {
                return ['status' => $result['status'], 'data' => $result['data'] ?? null, 'error' => $result['error'] ?? 'Error en listClients'];
            }
            $clients = $result['data']['results'] ?? [];
            foreach ($clients as $c) {
                $cedula = $c['cedula'] ?? $c['documento'] ?? '';
                $cedulaClean = preg_replace('/[^0-9]/', '', $cedula);
                $cedulaBase  = preg_replace('/[^0-9]/', '', preg_replace('/-.*$/', '', $cedula));
                if ($cedulaClean === $cleanDoc || $cedulaBase === $cleanDoc || $cedula === $document) {
                    return ['status' => 200, 'data' => ['data' => $c]];
                }
            }
            // Si devolvi├│ menos resultados que el l├¡mite, no hay m├ís p├íginas
            if (count($clients) < $limit) {
                break;
            }
        }
        return ['status' => 404, 'data' => ['message' => "Cliente no encontrado despu├®s de revisar $maxPages p├íginas"]];
    }

    /**
     * Lista los clientes de la cuenta WispHub con paginaci├│n.
     * Endpoint: GET /clientes/?page=1&limit=50
     */
    public function listClients(array $filters = []): array
    {
        return $this->request('GET', 'clientes/', $filters);
    }

    /**
     * Obtiene el detalle completo de una factura/recibo.
     * Endpoint: GET /facturas/{id}/
     * Retorna la factura con artículos, zona, cliente, etc.
     *
     * @param string $invoiceId ID de la factura
     * @return array Datos completos de la factura
     */
    public function getInvoiceDetail(string $invoiceId): array
    {
        $result = $this->request('GET', "facturas/{$invoiceId}/");
        if ($result['status'] === 200 && !empty($result['data'])) {
            return $result['data'];
        }
        return [];
    }

    /**
     * Consulta el estado de una tarea as├¡ncrona.
     * Endpoint: GET /tasks/{task_id}/
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->request('GET', "tasks/{$taskId}/");
    }

    /**
     * Obtiene el saldo a favor disponible del cliente en WispHub.
     * Endpoint: GET /clientes/{serviceId}/saldo/
     *
     * @param string $serviceId ID del servicio
     * @return float Saldo a favor en USD
     */
    public function getClientBalance(string $serviceId): float
    {
        $result = $this->request('GET', "clientes/{$serviceId}/saldo/");
        if ($result['status'] === 200 && !empty($result['data'])) {
            $saldoFavor = $result['data']['saldo_favor'] ?? null;
            if ($saldoFavor !== null) {
                return floatval($saldoFavor);
            }
            $saldo = floatval($result['data']['saldo'] ?? 0);
            $facturas = $result['data']['facturas'] ?? [];
            $totalFacturas = 0;
            foreach ($facturas as $f) {
                $totalFacturas += floatval($f['total'] ?? $f['monto_pendiente'] ?? 0);
            }
            if ($saldo > 0 && $totalFacturas > 0 && $saldo <= $totalFacturas) {
                return 0.0;
            }
            return $saldo > $totalFacturas ? $saldo - $totalFacturas : 0.0;
        }
        return 0.0;
    }
}
?>
