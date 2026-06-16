# Módulo Portal de Clientes + Integración WispHub

## Guía de Pruebas — Servidor Demo
**URL:** https://demo.salvanovasolutions.com  
**Estado:** Junio 2026

---

## 1. ¿Qué se implementó?

### Portal de Clientes (`portal/`)
- **Login por cédula** — el cliente ingresa solo con su número de cédula (sin contraseña)
- **Dashboard** — muestra contratos activos, deuda pendiente (en $ y Bs), historial de pagos, notificaciones
- **Asistente de pago** — wizard paso a paso para reportar un pago móvil:
  1. Seleccionar método (Pago Móvil / Transferencia)
  2. Seleccionar banco
  3. Ingresar monto y referencia
  4. Subir captura de pantalla (opcional)
  5. Confirmar
- **Auto‑verificación BDV** — al reportar un pago del Banco de Venezuela, el sistema consulta la API del banco, verifica la referencia, y si coincide, APRUEBA automáticamente el pago y reactiva el servicio en WispHub
- **Sesión segura** — timeout por inactividad (30 min), CSRF tokens, rate limiting

### Integración WispHub (`src/Services/WispHubClient.php`)
- Sincronización bidireccional entre el sistema local y WispHub
- Acciones disponibles:
  - **Activar / Suspender servicio**
  - **Registrar pago + activar automáticamente** (registerPaymentAndActivate)
  - **Consultar perfil, saldo y facturas pendientes**
  - **Listar clientes** (con paginación)

### Panel de Administración (WispHub)
- `paginas/test_wisphub.php` — simulador para pruebas (restringido a service ID 902)
- `paginas/lista_clientes_wisphub.php` — listado paginado de todos los clientes WispHub
- `paginas/principal/aprobar_pagos.php` — aprobación manual de pagos que no se auto‑verificaron
- `cron/cortar_servicios_vencidos.php` — suspensión automática por mora
- `wisphub_cron_dashboard.php` — endpoint web para cron‑job.org / UptimeRobot

---

## 2. Escenarios de Prueba

### A. Usuario de prueba
| Dato | Valor |
|------|-------|
| Cédula | `V20788775` |
| Servicio WispHub | `902` |
| Contrato | `#1668` |
| Monto forzado | `1 BS` (el sistema fuerza automáticamente 1 BS para pruebas) |

> ⚠️ En el servidor demo, el usuario V20788775 tiene montos forzados a 1 BS.  
> En producción, los montos son los reales de cada contrato.

---

### B. Flujo completo: Cliente paga → auto‑verificación → activación

```
1. Admin:   Ingresar a "WispHub" → "Simular impago"
            (crea deuda de 1 BS + suspende servicio 902)

2. Cliente: Ir a portal demo → ingresar cédula V20788775
            → ver contrato #1668 con deuda "Bs 1,00"

3. Cliente: Hacer clic en "Pagar"
            → wizard paso a paso:
              - Método: Pago Móvil
              - Banco: Banco de Venezuela
              - Monto: se calcula solo
              - Referencia: usar número de 6+ dígitos (ej: 12345678)
              - Captura: (opcional, se puede omitir)
            → "FINALIZAR REPORTE"

4. Sistema:  Llama a la API del BDV → busca el movimiento por referencia
             → si el movimiento existe y el monto coincide → APRUEBA automáticamente
             → registra pago en cuentas_por_cobrar
             → llama a WispHub registerPaymentAndActivate(service_id=902)
             → el servicio se reactiva en WispHub
             → redirige al dashboard

5. Cliente:  Dashboard muestra "Al día" y el servicio activo
```

**Importante:** Para que el paso 4 funcione, debe existir un pago móvil REAL
de 1 BS desde un titular de BDV hacia la cuenta de la empresa (`04247377954`),
con la referencia que el cliente ingresa. El auto‑verify solo aprueba si 
la API del BDV confirma el movimiento.

---

### C. Simular impago (desde admin)
```
1. Ir a:  Sistema → WispHub → "Simular impago"
2. Acción: Crea deuda de 1 BS en cuentas_por_cobrar
           y suspende el servicio 902 en WispHub
3. Cliente: Al ingresar al portal, ve la deuda "Bs 1,00"
           y el badge "SUSPENDIDO" en el contrato
```

---

### D. Aprobación manual por administración
```
1. Cliente: Reporta un pago con referencia que NO coincide con BDV
            → queda PENDIENTE en pagos_reportados

2. Admin:   Sistema → Aprobar Pagos Web
            → ver listado de pagos pendientes
            → hacer clic en "APROBAR"
            → llenar monto, referencia, banco
            → "Aprobar Pago"
            → el sistema registra el pago y activa WispHub
```

---

### E. Corte automático por mora (cron)
```
1. Admin:   Configurar cron en cPanel o cron-job.org:
            https://demo.salvanovasolutions.com/wisphub_cron_dashboard.php?action=run&key=SECRET

2. Sistema:  Busca contratos con cuentas_por_cobrar vencidas
             > los días de gracia configurados
             → suspende el servicio en WispHub automáticamente

3. Cliente:  Al ingresar al portal, ve su contrato como "SUSPENDIDO"
             y la deuda acumulada
```

---

### F. Listado de clientes WispHub
```
1. Admin:   Ir a WispHub → Lista de Clientes
2. Muestra: Tabla paginada con todos los clientes registrados en WispHub
            (522 clientes en el servidor actual)
3. Acción:  Perfil → ver detalle del cliente
            Balance → ver saldo y facturas pendientes
```

---

## 3. Endpoints técnicos

| URL | Propósito |
|-----|-----------|
| `portal/index.php` | Login de clientes |
| `portal/dashboard.php` | Dashboard del cliente |
| `portal/pago.php` | Wizard de pago |
| `paginas/test_wisphub.php` | Simulador WispHub (admin) |
| `paginas/lista_clientes_wisphub.php` | Lista de clientes (admin) |
| `paginas/principal/aprobar_pagos.php` | Aprobación manual (admin) |
| `wisphub_cron_dashboard.php?action=run&key=SECRET` | Cron de corte |
| `portal/test_by_document.php?cedula=V20788775` | Test de API WispHub |

---

## 4. Notas importantes

1. **Solo el Banco de Venezuela** tiene auto‑verificación API. Otros bancos (Bancamiga, Zelle, etc.) quedan siempre PENDIENTES hasta que un admin los apruebe manualmente.
2. **Tasa BCV** se consulta de dolarapi.com con caché de 1 hora.
3. **SSL** debe estar habilitado en producción (`WISP_HUB_VERIFY_SSL = true`).
4. **El servicio 902** es exclusivo para pruebas. No afecta clientes reales.
5. **El usuario V20788775** está configurado como `TEST_USER_CEDULA` en `config/test_mode.php`. En producción se deshabilita para que ningún cliente tenga montos forzados.

---

## 5. Comandos útiles (SSH)

```bash
# Probar conexión BDV + WispHub
php scratch/diagnostico_flujo.php

# Probar endpoints WispHub
php tests/test_wisphub_real.php

# Ejecutar corte manual
php cron/cortar_servicios_vencidos.php
```
