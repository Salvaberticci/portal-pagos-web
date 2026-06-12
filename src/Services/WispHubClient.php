<?php

namespace Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WispHubClient
{
    /** @var string Base URL (sandbox o producción), siempre con trailing slash */
    private $baseUrl;
    /** @var string API key */
    private $apiKey;
    /** @var Client Guzzle HTTP client */
    private $http;
    /** @var float Tiempo del último request (para rate limiting) */
    private $lastRequestTime = 0;
    /** @var int Microsegundos mínimos entre requests */
    private $minIntervalUs = 200000; // 200ms → ~5 req/segundo

    /**
     * Constructor
     * @param array $config ['base_url' => ..., 'api_key' => ..., 'api_secret' => ...]
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.wisphub.net/api', '/') . '/';
        $this->apiKey  = $config['api_key'] ?? '';

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 15,
            'verify'   => true,
            'headers'  => [
                'Authorization' => "Api-Key {$this->apiKey}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /**
     * Realiza una petición HTTP a la API de WispHub.
     * Incluye rate limiting básico (200ms entre requests).
     */
    private function request(string $method, string $uri, array $data = []): array
    {
        // Rate limiting: esperar si fue hace menos de minIntervalUs
        $now = microtime(true);
        $elapsed = ($now - $this->lastRequestTime) * 1_000_000; // a microsegundos
        if ($elapsed < $this->minIntervalUs) {
            usleep((int)($this->minIntervalUs - $elapsed));
        }
        $this->lastRequestTime = microtime(true);

        $opts = [];
        if (!empty($data)) {
            $opts['body'] = json_encode($data);
        }
        try {
            $response = $this->http->request($method, $uri, $opts);
            $status = $response->getStatusCode();
            $body   = (string) $response->getBody();
            $json   = json_decode($body, true);
            return ['status' => $status, 'data' => $json];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $body   = (string) $e->getResponse()->getBody();
                $json   = json_decode($body, true);
                return ['status' => $status, 'data' => $json, 'error' => $body];
            }
            error_log('[WispHubClient] RequestException sin respuesta: ' . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------
    //  Métodos de la API
    // -----------------------------------------------------------------

    /**
     * Activa el servicio de uno o más clientes en WispHub.
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
     * Suspende (desactiva) el servicio de uno o más clientes en WispHub.
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
     * ID de la acción "Pagar" en WispHub.
     */
    const ACCION_PAGAR = 1;

    /**
     * Notifica a WispHub que se ha registrado un pago contra una factura.
     * Endpoint: POST /facturas/{id_factura}/registrar-pago/
     *
     * Datos esperados:
     *   - invoice_id (int)        → ID de la factura en WispHub
     *   - total_cobrado (float)   → Monto pagado
     *   - referencia (string)     → Número de referencia
     *   - fecha_pago (string)     → "YYYY-MM-DD HH:MM"
     *   - forma_pago (int, opc)   → ID forma de pago WispHub (default 45181)
     *   - accion (int, opc)       → ID acción (default 1)
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
            error_log('[WispHubClient] getPendingInvoices falló para service ' . $serviceId
                . ' status=' . ($result['status'] ?? 0)
                . ' error=' . ($result['error'] ?? 'sin error'));
        }
        return [];
    }

    /**
     * Flujo completo: registra el pago en WispHub y activa el servicio.
     *
     * 1. Obtiene facturas pendientes del cliente en WispHub
     * 2. Registra el pago contra cada factura pendiente
     * 3. Activa el servicio si sigue suspendido
     *
     * @param string $serviceId    ID del servicio en WispHub (ej. "902")
     * @param float  $amount       Monto pagado en USD
     * @param string $reference    Referencia/número de pago
     * @param string $paymentDate  Fecha del pago "YYYY-MM-DD HH:MM"
     * @param int    $formaPagoId  ID forma de pago en WispHub (default 45181)
     * @param bool   $forceActivate Forzar activación aunque ya esté activo
     * @return array Resultado con payments_registered[] y activation
     */
    public function registerPaymentAndActivate(
        string $serviceId,
        float  $amount,
        string $reference,
        string $paymentDate,
        int    $formaPagoId = self::FORMA_PAGO_OPERACION_BANCARIA,
        bool   $forceActivate = false
    ): array {
        $results = [
            'service_id'          => $serviceId,
            'invoices_found'      => 0,
            'payments_registered' => [],
            'activation'          => null,
            'status'              => 200,
        ];

        // 1. Obtener facturas pendientes
        $invoices = $this->getPendingInvoices($serviceId);
        $results['invoices_found'] = count($invoices);

        // 2. Registrar pago contra cada factura pendiente
        foreach ($invoices as $invoice) {
            if (empty($invoice['id'])) continue;

            $payResult = $this->request('POST', "facturas/{$invoice['id']}/registrar-pago/", [
                'forma_pago'    => $formaPagoId,
                'accion'        => self::ACCION_PAGAR,
                'fecha_pago'    => $paymentDate,
                'referencia'    => $reference,
                'total_cobrado' => $amount,
            ]);

            $results['payments_registered'][] = [
                'invoice_id' => $invoice['id'],
                'status'     => $payResult['status'],
            ];

            if ($payResult['status'] !== 200 && $payResult['status'] !== 201) {
                $results['status'] = $payResult['status'];
            }
        }

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
     * Obtiene el saldo/deuda de un cliente.
     * Endpoint: GET /clientes/{id_servicio}/saldo/
     */
    public function getServiceBalance(string $serviceId): array
    {
        return $this->request('GET', "clientes/{$serviceId}/saldo/");
    }

    /**
     * Lista los clientes de la cuenta WispHub con paginación.
     * Endpoint: GET /clientes/?page=1&limit=50
     */
    public function listClients(array $filters = []): array
    {
        return $this->request('GET', 'clientes/', $filters);
    }

    /**
     * Consulta el estado de una tarea asíncrona.
     * Endpoint: GET /tasks/{task_id}/
     */
    public function getTaskStatus(string $taskId): array
    {
        return $this->request('GET', "tasks/{$taskId}/");
    }
}
?>
