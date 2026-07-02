# Guia FTP - Portal de Pagos Maratel

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

**El document root de `app.marateltru.com` es `public_html/portal/`**

```
ftp://ftp.marateltru.com/ (acceso con FTP APP)
├── public_html/              ← Document root del dominio
│   └── portal/               ← ✅ AQUÍ vive app.marateltru.com/portal/
│       ├── dashboard.php
│       ├── pago.php
│       ├── index.php
│       ├── login.php
│       ├── verificar_pago.php
│       ├── clear_cache.php
│       ├── procesar_pago_cliente.php
│       ├── api_verificar_pago.php
│       ├── auth.php
│       ├── security_helper.php
│       ├── wisp_helper.php
│       ├── referencia_helper.php
│       ├── bdv_autoverify_helper.php
│       ├── importar_pagos.php
│       ├── simulador.php
│       ├── css/
│       │   └── style.css
│       └── .htaccess
├── config/                  ← Configuración (database.php, WispHub, etc.)
│   ├── database.php
│   ├── wisp_hub.php
│   └── wisphub_credentials.php
├── src/
│   └── Services/
│       └── WispHubClient.php
├── portal/                  ← ❌ NO USAR (tiene un index.php de mantenimiento)
│   └── index.php            ← ❌ Tiene "ACTUALIZACION INMEDIATA"
└── public_html/             ← Document root del dominio
```

### Directorio correcto para el portal:
**`ftp://ftp.marateltru.com/public_html/portal/`**

### Directorios INCORRECTOS (NO usar):
~~`ftp://ftp.marateltru.com/portal/`~~ (tiene mantenimiento)
~~`ftp://ftp.marateltru.com/public_html/`~~ (es el padre)

## Comandos FTP con curl

### Subir un archivo (FTPS explícito)

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -T "C:\ruta\local\archivo.php" `
  "ftp://ftp.marateltru.com/public_html/portal/archivo.php"
```

### Subir múltiples archivos

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -T "C:\ruta\dashboard.php" "ftp://ftp.marateltru.com/public_html/portal/dashboard.php" `
  -T "C:\ruta\pago.php" "ftp://ftp.marateltru.com/public_html/portal/pago.php"
```

### Listar archivos de un directorio

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  --list-only "ftp://ftp.marateltru.com/public_html/portal/"
```

### Descargar un archivo (para verificar contenido)

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -o "C:\temp\verificar.php" `
  "ftp://ftp.marateltru.com/public_html/portal/dashboard.php"
```

### Verificar que un archivo contiene cierto texto

```powershell
C:\Windows\System32\curl.exe --ssl-reqd --insecure `
  -u "adminappmarateltru@app.marateltru.com:admappMT2026*" `
  -o "C:\temp\check.php" `
  "ftp://ftp.marateltru.com/public_html/portal/dashboard.php"
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
   - ✅ `ftp://ftp.marateltru.com/public_html/portal/`
   - ❌ `ftp://ftp.marateltru.com/portal/` (tiene archivo de mantenimiento)

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
  "ftp://ftp.marateltru.com/public_html/portal/dashboard.php"
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
