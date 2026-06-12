<?php
/**
 * WispHubClient.php
 *
 * Cliente PHP para la integración con la API de WispHub.
 * Provee métodos para cortar, editar y restablecer servicios de clientes
 * después de la confirmación de pago.
 *
 * La implementación utiliza cURL y JWT (HMAC‑SHA256) para autenticación.
 * Reemplace los valores de los constantes API_* con sus credenciales reales.
 */

class WispHubClient {
    // ---- CONFIGURACIÓN ----
    // URL base de la API (ejemplo: https://api.wisphub.com/v1)
    private const BASE_URL = 'https://api.wisphub.com/v1';

    // Credenciales de la API – reemplazar con valores reales
    private const API_KEY    = 'TU_API_KEY_AQUI';
    private const API_SECRET = 'TU_API_SECRET_AQUI';

    // Tiempo de expiración del token en segundos (ej. 300 = 5 minutos)
    private const TOKEN_TTL = 300;

    // Token de acceso cacheado en memoria y en archivo temporal
    private $accessToken = null;
    private $tokenExpiresAt = 0;

    // ---- MÉTODOS PÚBLICOS ----
    /**
     * Corta (desactiva) un servicio para el cliente especificado.
     * @param string $clientId  Identificador interno del cliente (ej. V99999999)
     * @param string $serviceId Identificador del servicio en WispHub
     * @return array Resultado de la llamada (['success'=>bool,'data'=>...])
     */
    public function cutService(string $clientId, string $serviceId): array {
        $payload = [
            'client_id'  => $clientId,
            'service_id' => $serviceId,
            'action'     => 'cut'
        ];
        return $this->request('/services/action', $payload);
    }

    /**
     * Edita la configuración de un servicio existente.
     * @param string $clientId   Identificador del cliente
     * @param string $serviceId  Identificador del servicio
     * @param array  $params     Parámetros a modificar (clave=>valor)
     */
    public function editService(string $clientId, string $serviceId, array $params): array {
        $payload = array_merge([
            'client_id'  => $clientId,
            'service_id' => $serviceId,
            'action'     => 'edit'
        ], $params);
        return $this->request('/services/action', $payload);
    }

    /**
     * Restablece (reactiva) un servicio previamente cortado.
     */
    public function restoreService(string $clientId, string $serviceId): array {
        $payload = [
            'client_id'  => $clientId,
            'service_id' => $serviceId,
            'action'     => 'restore'
        ];
        return $this->request('/services/action', $payload);
    }

    // ---- MÉTODOS INTERNOS ----
    /**
     * Realiza una petición HTTP POST a la API.
     */
    private function request(string $endpoint, array $data): array {
        $url = self::BASE_URL . $endpoint;
        $token = $this->getAccessToken();
        $jsonData = json_encode($data);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-API-KEY: ' . self::API_KEY
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => $err];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded];
        }
        return ['success' => false, 'status' => $httpCode, 'data' => $decoded];
    }

    /**
     * Obtiene un token de acceso válido, renovándolo si es necesario.
     */
    private function getAccessToken(): string {
        $now = time();
        if ($this->accessToken && $now < $this->tokenExpiresAt - 30) {
            // Token aún válido
            return $this->accessToken;
        }
        // Generar nuevo JWT
        $header = base64_encode(json_encode(['alg' => 'HS256','typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => self::API_KEY,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL
        ]));
        $signature = hash_hmac('sha256', "$header.$payload", self::API_SECRET, true);
        $jwt = "$header.$payload." . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=' );
        $this->accessToken = $jwt;
        $this->tokenExpiresAt = $now + self::TOKEN_TTL;
        return $this->accessToken;
    }
}
?>
