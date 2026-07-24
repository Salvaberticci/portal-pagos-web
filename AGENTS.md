# AGENTS.md — Portal de Pagos Maratel

## Entorno
- Producción: `app.marateltru.com` (DEV_MODE=false)
- Servidor: HostGator, FTP `ftp.marateltru.com` puerto 21 FTPS explícito
- Usuario FTP: `adminappmarateltru@app.marateltru.com`
- DB remota: `sh00002.hostgator.co`, DB `darwinra_bdmarateltru`

## Cuentas WispHub

| Cuenta | API Base URL | API Key (prefix) | forma_pago_id |
|--------|-------------|-------------------|---------------|
| sitelco | api.wisphub.net/api | ubxyK8jE.Bo... | **45181** |
| jalisco | api.wisphub.io/api | krxbkpsX.y0... | **18426** |
| pampanito | api.wisphub.app/api | oB9ajTrx.Ee... | **6645** |

## Cómo obtener forma_pago_id
Endpoint API que lista formas de pago:
```
GET /formas-de-pago/
Authorization: Api-Key {api_key}
```
Respuesta: `{"count":N,"results":[{"id":18426,"nombre":"Operacion Bancaria"},...]}`

## Timeouts
- `WispHubClient.php`: timeout=15s, connect_timeout=10s, read_timeout=15s (HostGator es lento)
- Si `getServiceProfile()` falla (timeout), `wisp_helper.php` usa `findClientByDocument()` con la cédula de la sesión como fallback

## Monto pendiente y estado
- WispHub puede tener facturas con `total_cobrado = total` pero estado "Pendiente de Pago"
- `monto_pendiente` se calcula: `saldo_nuevo > 0 ? saldo_nuevo : (estado pendiente ? total : total - cobrado)`

## Flujo de abono parcial
1. WispHub recibe pago < total de factura.
2. El portal crea una nueva factura "Saldo pendiente tras abono - Factura #X" ANTES de registrar el pago.
3. El portal registra el abono en WispHub.
4. El portal calcula los días extra proporcionales y agrega una Promesa de Pago a la NUEVA factura de saldo.
5. `wisp_helper.php` filtra: elimina de la vista la factura original (que WispHub ya cobró), y muestra solo la nueva factura hija con el saldo real pendiente.

## Archivos clave
- `config/wisphub_credentials.php` — credenciales + forma_pago_id por cuenta
- `config/wisp_hub.php` — puente que exporta constantes a arreglo
- `src/Services/WispHubClient.php` — cliente API WispHub
- `portal/wisp_helper.php` — caché + filtro facturas saldo pendiente
- `portal/dashboard.php` — vista principal del cliente
- `portal/pago.php` — wizard de pago
- `portal/procesar_pago_cliente.php` — backend que registra pago en WispHub

## Test clients
- Sitelco: V20788775 / service_id=902
- Jalisco: 30236536 / service_id=794
- Pampanito: 30236536 / service_id=908 (username: `usuario-prueba@gigatek-network`)
