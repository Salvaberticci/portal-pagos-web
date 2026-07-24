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

## Flujo de abono parcial
1. WispHub recibe pago < total de factura
2. WispHub crea factura "Saldo pendiente tras abono - Factura #X" por el saldo restante
3. `wisp_helper.php` filtra: elimina factura padre #X, muestra solo la hija
4. `dashboard.php` usa `monto_pendiente = total - total_cobrado` para el saldo real

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
