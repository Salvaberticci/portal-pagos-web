# Guia FTP - Portal de Pagos Maratel

## Cache URL
```
https://app.marateltru.com/portal/clear_cache.php?token=m4r4t3lt2026
```

## Credenciales FTP

### Para app.marateltru.com (Portal de Pagos) ✅ USAR ESTA

| Campo | Valor |
|-------|-------|
| Usuario | `adminappmarateltru@app.marateltru.com` |
| Password | `admappMT2026*` |
| Host | `ftp.marateltru.com` |
| Puerto | 21 (FTPS explícito) |

### Para www.marateltru.com (Dominio Principal)

| Campo | Valor |
|-------|-------|
| Usuario | `adminmarateltru@marateltru.com` |
| Password | `admMT2026*` |
| Host | `ftp.marateltru.com` |
| Puerto | 21 (FTPS explícito) |

### Para pagos.marateltru.com (Subdominio de Pagos)

| Campo | Valor |
|-------|-------|
| Usuario | `adminpagosmarateltru@pagos.marateltru.com` |
| Password | `admpagosMT2026*` |
| Host | `ftp.marateltru.com` |
| Puerto | 21 (FTPS explícito) |

## ⚠️ IMPORTANTE: Estructura de Directorios

**El document root de `app.marateltru.com` es `/home2/darwinra/app.marateltru.com/`**

En el servidor:
- `app.marateltru.com` → `/home2/darwinra/app.marateltru.com/`
- El portal está en `/home2/darwinra/app.marateltru.com/portal/`

En el FTP (usuario `adminappmarateltru@app.marateltru.com`):
- La raíz del FTP (/) corresponde a la carpeta home del usuario
- **`portal/`** en la raíz del FTP → **`/home2/darwinra/app.marateltru.com/portal/`** ✅
- `public_html/portal/` en el FTP → `/home2/darwinra/public_html/portal/` ❌ (NO es el portal)

```
ftp://ftp.marateltru.com/ (acceso con FTP APP)
│   Ruta real: /home2/darwinra/ (home del usuario FTP)
├── portal/                   ← ✅ RUTA CORRECTA para app.marateltru.com/portal/
│   ├── dashboard.php
│   ├── pago.php
│   ├── index.php
│   ├── login.php
│   ├── verificar_pago.php
│   ├── clear_cache.php
│   ├── procesar_pago_cliente.php
│   ├── api_verificar_pago.php
│   ├── auth.php
│   ├── security_helper.php
│   ├── wisp_helper.php
│   ├── referencia_helper.php
│   ├── bdv_autoverify_helper.php
│   ├── importar_pagos.php
│   ├── simulador.php
│   ├── css/
│   │   └── style.css
│   └── .htaccess
├── public_html/
│   └── portal/               ← ❌ NO USAR (es otro proyecto)
├── config/                   ← Configuración (database.php, WispHub, etc.)
│   ├── database.php
│   ├── wisp_hub.php
│   └── wisphub_credentials.php
├── src/
│   └── Services/
│       └── WispHubClient.php
└── ... (otros archivos del home)
```

### Directorio correcto para el portal:
**`ftp://ftp.marateltru.com/portal/`**

### Directorio INCORRECTO (NO usar):
~~`ftp://ftp.marateltru.com/public_html/portal/`~~

## Comandos FTP con curl

### Subir un archivo (FTPS explícito)

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -T "C:\ruta\local\archivo.php" `
  "ftp://ftp.marateltru.com/portal/archivo.php"
```

### Subir múltiples archivos

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -T "C:\ruta\dashboard.php" "ftp://ftp.marateltru.com/portal/dashboard.php" `
  -T "C:\ruta\pago.php" "ftp://ftp.marateltru.com/portal/pago.php"
```

### Listar archivos de un directorio

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  --list-only "ftp://ftp.marateltru.com/portal/"
```

### Descargar un archivo (para verificar contenido)

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -o "C:\temp\verificar.php" `
  "ftp://ftp.marateltru.com/portal/dashboard.php"
```

### Verificar que un archivo contiene cierto texto

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -o "C:\temp\check.php" `
  "ftp://ftp.marateltru.com/portal/dashboard.php"
C:\Windows\System32\findstr.exe "TextoABuscar" "C:\temp\check.php"
```

## Archivos por subir según tipo de cambio

### Cambios en el Dashboard (estilos, botones, layout)
- `portal/dashboard.php`
- `portal/css/style.css` (si se modificaron estilos CSS)

### Cambios en el Pago (formulario, validación, modales)
- `portal/pago.php`
- `portal/api_verificar_pago.php` (si cambia validación de referencia)
- `portal/procesar_pago_cliente.php` (si cambia procesamiento)
- `portal/bdv_autoverify_helper.php` (si cambia verificación bancaria)

### Cambios en Login/Logout/Sesión
- `portal/index.php`
- `portal/login.php`
- `portal/auth.php`
- `portal/security_helper.php`

### Cambios en WispHub (API, facturas, notas de crédito)
- `src/Services/WispHubClient.php`
- `portal/wisp_helper.php`
- `config/wisphub_credentials.php`
- `config/wisp_hub.php`

### Cambios en Base de Datos
- `config/database.php`
- `portal/referencia_helper.php`

### Cambios en Verificación de Pago
- `portal/verificar_pago.php`
- `portal/api_verificar_pago.php`
- `portal/bdv_autoverify_helper.php`

## Archivos de utilidad en el servidor

### clear_cache.php
URL: `https://app.marateltru.com/portal/clear_cache.php?token=m4r4t3lt2026`

Ejecuta `opcache_reset()` para limpiar la caché de PHP. Usar cuando:
- Los archivos se suben pero no se ven los cambios en el navegador
- Se actualizan archivos PHP y el servidor sirve versiones viejas

### verificar_pago.php
URL: `https://app.marateltru.com/portal/verificar_pago.php?token=m4r4t3lt2026`

Página de verificación de estado de pagos y facturas pendientes.

## Solución de Problemas

### Los cambios no se ven después de subir por FTP

1. **Verificar que se subió al directorio correcto:**
   - ✅ `ftp://ftp.marateltru.com/portal/`
   - ❌ `ftp://ftp.marateltru.com/public_html/portal/` (es otro proyecto)

2. **Ejecutar clear_cache.php:**
   ```
   https://app.marateltru.com/portal/clear_cache.php?token=m4r4t3lt2026
   ```

3. **Hard refresh en el navegador:** `Ctrl+F5` o `Ctrl+Shift+R`

4. **Verificar en ventana de incógnito** para descartar caché del navegador

5. **Verificar contenido del archivo en el servidor:**
   ```powershell
   C:\Windows\System32\curl.exe --ssl-reqd --insecure `
     -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
     -o "C:\temp\verify.php" `
  "ftp://ftp.marateltru.com/portal/dashboard.php"
C:\Windows\System32\findstr.exe "Continuar" "C:\temp\verify.php"
   ```

### 404 al acceder a clear_cache.php
- Significa que se está accediendo al directorio incorrecto
- Verificar que la URL sea `app.marateltru.com/portal/clear_cache.php`

### El FTP con usuario `adminmarateltru@marateltru.com` no funciona para el portal
- Ese usuario es para el dominio principal `marateltru.com`
- Para `app.marateltru.com` usar `adminappmarateltru@app.marateltru.com`

## Git

Repositorio: `https://github.com/Salvaberticci/portal-pagos-web`

### Push a GitHub
```powershell
cd C:\xampp\htdocs\portal-pagos-web
git add .
git commit -m "descripcion del cambio"
git push
```

## Base de Datos

| Campo | Valor |
|-------|-------|
| Host | `sh00002.hostgator.co` |
| DB | `salvxkld_portal_pagos` |
| Usuario | `salvxkld_admin` |
| Password | `Nana2121.` |

### Para cambios en la DB del dominio principal
| Campo | Valor |
|-------|-------|
| DB | `darwinra_BDmainMarateltru` |
| Usuario | `darwinra_bdppalmarateltruadm` |
| Password | `Adminbdmarateltru2026` |

## Changelog - Cambios Recientes

### 2026-07-07 — Corrección fecha promesa + Filtro facturas padre + Referencia real del banco
**Archivos:** `portal/procesar_pago_cliente.php`, `portal/pago.php`, `portal/api_verificar_pago.php`
- **procesar_pago_cliente.php:**
  - Fecha base promesa cambiada de `fechaVencOriginal` a `fechaEmiOriginal` (usa el día de emisión/pago como base, no el vencimiento)
  - Ahora usa la referencia REAL del banco (últimos 8 dígitos) extraída de la API, no lo que el cliente tecleó (que a veces omite dígitos)
  - `$accion_pre` (pre-burn) ahora se guarda en `$_SESSION['pago_data']['accion']` para el modal
  - Cobertura ahora usa `$fechaPromesaLocal` cuando existe
- **pago.php:**
  - Eliminado el rescate de facturas abonadas desde BD local (ya no es necesario porque WispHub crea facturas "Saldo pendiente tras abono")
  - Nuevo filtro: si una factura padre tiene un hijo "Saldo pendiente tras abono - Factura #X", la factura padre NO se muestra (solo se ve la hija con el saldo real)
- **api_verificar_pago.php:**
  - Cobertura ahora usa `fecha_emision` de la factura en vez de `+X days from today`

### 2026-07-07 — Eliminados registros de prueba en DB
- Eliminados 6 registros de `pagos_registrados` para service_id=902 (Cliente OFICINA Prueba) en BD remota
- IDs eliminados: 1398, 1399, 1400, 1793, 1795, 1797

### 2026-07-06 — Fix rastreo recursivo precio + Tests
**Archivos:** `portal/procesar_pago_cliente.php`
- Reemplazado `$precioPlan` simple por función recursiva `$getTruePlanPrice()` que viaja por la cadena de facturas "Saldo pendiente tras abono - Factura #N" hasta encontrar la factura original
- Creado `test_simulador_abonos.php` con 3 tests: formato referencia, promesa ciclo atrasado, rastreo recursivo

### 2026-07-06 — Ref formato WispHub con monto BS
**Archivos:** `portal/procesar_pago_cliente.php`
- Referencia enviada a WispHub ahora incluye monto en BS: `últimos8dígitos-guion-montoEnteroBS` (ej: `60741024-130`)

### 2026-07-04 — Cache TTL 1s + Refrescar eliminado
**Archivos:** `portal/wisp_helper.php`, `portal/dashboard.php`
- TTL del cache reducido de 60s → 1s para cambios inmediatos
- Botón "Refrescar" eliminado del dashboard

### 2026-07-04 — Fix duplicado referencia + WispHub 400 en parciales
**Archivos:** `portal/referencia_helper.php`, `portal/procesar_pago_cliente.php`
- `getReferenciaInfo()` ahora busca primero coincidencia EXACTA, luego últimos 8 dígitos (antes solo 6, causaba colisiones como 60741024 vs 741024)
- `procesar_pago_cliente.php` ahora acepta HTTP 400 de WispHub si `amount_applied > 0` (pagos parciales)

### 2026-07-02 — Fix validación referencia API (6-15 dígitos)
**Archivos:** `portal/api_verificar_pago.php`
- Validación de referencia cambiada de 6-8 a 6-15 dígitos (transferencias/Zelle pueden tener hasta 15)

### 2026-07-02 — Cobertura + Fecha promesa + Nota crédito
**Archivos:** `portal/procesar_pago_cliente.php`, `portal/api_verificar_pago.php`, `src/Services/WispHubClient.php`
- Pago fraccionado: crea factura por saldo restante en WispHub ANTES de registrar el pago
- Fecha promesa: `diasServicio = round(30 * (monto_usd / precioPlan))`
- WispHub recibe +1 día en fecha promesa (portal muestra original)
- Excesos crean Nota de Crédito (factura negativa) en WispHub vía `createCreditNote()`
- Loading spinner se muestra ANTES de ocultar modal de confirmación
