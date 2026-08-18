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
- WispHub puede tener facturas con `total_cobrado = total` pero estado "Pendiente de Pago" (residuo sin pago real, ej. referencia vacía)
- `monto_pendiente` se calcula: base `total - cobrado`; `saldo_nuevo` solo si es MAYOR a la base y hay abono parcial real (`cobrado < total`); si la factura está pendiente y `cobrado >= total`, la deuda es el `total` (los campos `saldo`/`saldo_nuevo` son residuos no confiables)
- Ej. real: factura #10873 de Jorcelis Linares (V19794781, service 795) tenía `total=30, total_cobrado=30, saldo=10` y el portal mostraba $10; con la regla nueva muestra $30 (lo que WispHub reporta)

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

## Tests y auditoría (local, `tests/`)
- `tests/test_monto_pendiente.php` — valida `wisp_normalize_invoice()` / `wisp_filter_saldo_pendiente()` con 15 casos (11 sintéticos + filtro hija + 1 live read-only service 795). La regla de `monto_pendiente` vive en esas funciones (extraídas de `wisp_get_cached_data`).
- `tests/audit_facturas_residuo.php` — auditoría read-only de las 3 cuentas: lista facturas pendientes con patrón residuo (`total_cobrado >= total` y `saldo > 0`). Solo GETs, no modifica nada.
- Ejecutar: `php tests/test_monto_pendiente.php` y `php tests/audit_facturas_residuo.php`
- Hallazgo 2026-08-14: 33 facturas con patrón residuo (sitelco 25, jalisco 8, pampanito 0). La mayoría importadas por el cajero "Wilmer Ramirez" (12-13 Ago) con `total = sub_total(20) + saldo(10) = 30` y cobro fantasma (`total_cobrado=30`, `referencia=""`). La API no expone edición de facturas → corrección manual en admin WispHub (total = sub_total, sin cobro fantasma).
