# Integración de la API WispHub

## Objetivo
Implementar la conectividad con la API **WispHub** para que, una vez confirmada la transacción de pago, el sistema pueda **cortar, editar o restablecer** automáticamente el servicio del cliente. Todo el proceso debe ser **seguro**, auditable y extensible.

---

## Revisión requerida por el usuario
> [!IMPORTANT]
> - **Credenciales de WispHub**: `api_key`, `api_secret`, `client_id` (o token OAuth).
> - **URL base de la API** (producción y sandbox).
> - **Mapeo interno** entre la tabla `contratos` y los identificadores de servicio en WispHub (campo `wisp_hub_account_id`).
> - **Política de seguridad**: si desea usar OAuth2, JWT o HMAC para validar webhooks.
> - **Entorno de pruebas**: URL del sandbox de WispHub y si se debe habilitar modo «dry‑run».

---

## Preguntas abiertas
> [!WARNING]
> - ¿Qué acciones exactas necesita (corte, habilitación, cambio de plan, suspenso temporal, etc.)?
> - ¿Existe ya un flujo de aprobación interno antes de ejecutar la acción? (p. ej., revisión manual de un supervisor).
> - ¿Cuánto tiempo después del pago se debe ejecutar la acción (inmediato vs. retrasado)?
> - ¿Quiere registrar cada llamada a la API y su respuesta en un log central?

---

## Cambios propuestos

### 1. Base de datos
- **Crear tabla `wisp_hub_links`** para almacenar el mapeo y estado:
```sql
CREATE TABLE wisp_hub_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contrato_id INT NOT NULL,
    wisp_account_id VARCHAR(64) NOT NULL,
    status ENUM('ACTIVE','SUSPENDED','PENDING') NOT NULL DEFAULT 'PENDING',
    last_sync TIMESTAMP NULL,
    CONSTRAINT fk_contrato FOREIGN KEY (contrato_id) REFERENCES contratos(id)
);
```

### 2. Configuración
- Añadir archivo `config/wisp_hub.php` con constantes o variables:
```php
<?php
return [
    'api_key'    => getenv('WISPHUB_API_KEY'),
    'api_secret' => getenv('WISPHUB_API_SECRET'),
    'client_id'  => getenv('WISPHUB_CLIENT_ID'),
    'base_url'   => getenv('WISPHUB_BASE_URL'), // sandbox o prod
];
?>
```
- Documentar en `.env.example` los nuevos campos.

### 3. Cliente HTTP
- **Clase `src/Services/WispHubClient.php`** que encapsula todas las operaciones necesarias para la gestión del servicio mediante la API de WispHub.
  - `activateService($accountId)` → POST `/accounts/{id}/activate`  (corte del servicio -> activar).
  - `suspendService($accountId)` → POST `/accounts/{id}/suspend`  (corte por impago o suspensión temporal).
  - `updatePlan($accountId, $payload)` → PATCH `/accounts/{id}`  (cambio de plan o ajustes de configuración).
  - `deactivateService($accountId)` → POST `/accounts/{id}/deactivate`  (baja definitiva del servicio).
  - `notifyPayment($payload)` → POST `/payments/notify`  (envío de información del pago a Wisphub, incluido en el flujo de integración).
  - **Gestión de firma HMAC** usando `api_secret` para firmar cada petición (`X‑WispHub‑Signature`).
  - **Manejo de retries** con back‑off exponencial y registro estructurado de errores.
  - **Flujo de aprobación interno**: antes de invocar cualquier método que modifique el estado del servicio, el código verifica si el pago está marcado como `APROBADO` en la tabla `pagos_reportados`. Si el pago está pendiente de revisión manual, se registra una tarea en `logs/wisphub_pending.log` y se espera la aprobación del supervisor; sólo entonces se ejecuta la llamada a la API.

### 4. Integración en flujo de pago
- Modificar `portal/procesar_pago_cliente.php` (o el helper que ya llama a BDV) para, **después de la verificación exitosa**, ejecutar:
```php
require_once __DIR__.'/../src/Services/WispHubClient.php';
$config = include __DIR__.'/../../config/wisp_hub.php';
$client = new WispHubClient($config);

// Obtener el id del contrato asociado al pago
$contratoId = $id_contrato_asociado ?? $id_contrato; // ya disponible en el script
// Buscar o crear en wisp_hub_links
$link = $db->query("SELECT * FROM wisp_hub_links WHERE contrato_id=$contratoId")->fetch_assoc();
if (!$link) {
    // Supongamos que el ID del cliente en WispHub se crea mediante otro endpoint o ya está configurado
    $wispAccountId = $client->createAccount([...datos del cliente...]);
    $db->query("INSERT INTO wisp_hub_links (contrato_id,wisp_account_id,status) VALUES ($contratoId,'$wispAccountId','ACTIVE')");
    $client->activateService($wispAccountId);
} else {
    // Si el estado es PENDING o SUSPENDED, activamos
    if ($link['status'] !== 'ACTIVE') {
        $client->activateService($link['wisp_account_id']);
        $db->query("UPDATE wisp_hub_links SET status='ACTIVE',last_sync=NOW() WHERE id={$link['id']}");
    }
}
```
- En caso de **corte** (por impago) se llamará `suspendService` y se actualizará el campo `status`.

#### Envío de información de pago a Wisphub
- Después de validar el pago (estado `PAGADO` en la tabla `pagos_reportados`), se recopilan los datos críticos: ID de pago, monto, moneda, fecha, ID de contrato, y cualquier referencia externa.
- Estos datos se empaquetan en un JSON y se envían al endpoint sandbox de Wisphub `/payments/notify` mediante una petición **POST** segura.
- La petición incluye el `api_key` en el encabezado `Authorization: Bearer <api_key>` y una firma HMAC (`X-WispHub-Signature`) generada con `api_secret` para garantizar la integridad.
- Wisphub responde con un `transaction_id` que se almacena en la tabla `wisp_hub_links` (campo `wisphub_tx_id`).
- Si la respuesta indica éxito, se procede a **activar** el servicio con `activateService`. En caso de error, el sistema registra el mensaje en `logs/wisphub.log` y notifica al administrador.
- En modo sandbox, la URL base será `https://sandbox.wisphub.com/api/v1`; en producción se cambiará a `https://api.wisphub.com/v1` mediante la variable de entorno `WISPHUB_BASE_URL`.
- Este flujo permite pruebas sin afectar clientes reales y garantiza que toda la información del pago se comparta de forma auditable con Wisphub.

### 5. Webhook de notificaciones de WispHub
- Crear archivo `portal/wisp_hub_webhook.php` que reciba POSTs de WispHub.
- Verificar firma HMAC (`X-WispHub-Signature` header) usando `api_secret`.

#### Panel administrativo de WispHub
- Página accesible sólo para usuarios con rol **admin** (controlada mediante `auth.php`).
- Permite **ver y filtrar** el log `logs/wisphub.log` (paginación y búsqueda por request_id, endpoint, código de respuesta).
- Formulario para **modificar** las credenciales (`WISPHUB_API_KEY`, `WISPHUB_API_SECRET`, `WISPHUB_BASE_URL`) que actualiza el archivo `.env` mediante una escritura segura.
- Cambios de credencial se registran en `logs/wisphub_admin.log` con timestamp y usuario.
- UI construida con HTML, CSS moderno (gradientes, tipografía Inter) y micro‑animaciones para mejorar la experiencia.
- Actualizar la tabla `wisp_hub_links` según el evento (`status_changed`).
- Responder `200 OK` para confirmar recepción.

### 6. Seguridad
- **HTTPS obligatorio** para todas las comunicaciones externas.
- **Variables de entorno** (`.env`) para credenciales, nunca hardcodeadas.
- **Validación de payload**: sanitizar datos antes de enviarlos a la API.
- **Rate limiting** interno para evitar llamadas masivas accidentales.
- **Logs**: registrar `request_id`, endpoint, código de respuesta y cuerpo de error en `logs/wisphub.log`.

### 7. Pruebas
- **Unit tests** para `WispHubClient` usando mocks de HTTP (Guzzle mock handler).
- **Integration tests** contra el sandbox de WispHub (creación, activación, suspensión, webhook).
- **Escenarios**:
  1. Pago exitoso → servicio activado.
  2. Pago rechazado → servicio suspendido.
  3. Webhook de suspensión externa → base de datos sincronizada.
- **CI**: ejecutar los tests en cada push.

### 8. Despliegue
1. **Actualización de dependencias** – añadir Guzzle (o cURL wrapper) al `composer.json`.
2. **Migración** – script SQL para crear `wisp_hub_links`.
3. **Despliegue gradual** – habilitar la integración primero en entorno *staging* usando el sandbox de WispHub.
4. **Monitoreo** – revisar `logs/wisphub.log` y crear alertas en Grafana/Prometheus para errores de API.

---

## Verificación
- Ejecutar un pago de prueba con el usuario *V99999999* y confirmar que el script llama a `WispHubClient::activateService` (mirar logs).
- Simular un webhook de suspensión y verificar que el estado en la tabla cambia a `SUSPENDED`.
- Revisar que los cambios de UI (p. ej., columna “Estado del servicio”) reflejen el estado real.

---

*Plan elaborado por Antigravity AI. Se solicita revisión y aprobación antes de comenzar la implementación.*
