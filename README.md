# Portal de Pagos Web - Wireless Supply

Portal exclusivo para clientes de Wireless Supply, diseñado para facilitar el reporte de pagos, consulta de mensualidades y la gestión de contratos con integraciones automáticas.

## Características

- **Portal de Clientes**: Interfaz segura mediante inicio de sesión con Cédula de Identidad.
- **Dashboard de Contratos**: Vista rápida del estado actual, deudas pendientes y tarifario.
- **Wizard de Pagos Integrado**: Experiencia fluida para el reporte de pagos y selección de bancos (Zelle, Pago Móvil, Transferencia).
- **Integración con API BDV (Banco de Venezuela)**: Auto-verificación de pagos en tiempo real.
- **Integración con WispHub**: Activación y registro de pagos automático en la plataforma WispHub.

## Requisitos

- PHP 7.4+ o PHP 8.x
- MySQL / MariaDB
- Dependencias manejadas por Composer (ver `composer.json`)
- Accesos configurados a las APIs de WispHub y BDV en el directorio `config/`

## Instalación y Configuración

1. Clonar el repositorio.
2. Instalar dependencias con `composer install`.
3. Configurar la base de datos en `paginas/conexion.php`.
4. Añadir credenciales de las integraciones en `config/wisp_hub.php` y `config/wisphub_credentials.php`.
5. Asegurar permisos de escritura en la carpeta `uploads/pagos/`.

## Modo de Pruebas

Se puede activar el modo de pruebas utilizando la cédula `V20788775` modificando el archivo `config/test_mode.php`. Este usuario permite realizar todo el flujo de pagos usando montos de prueba y conectando directamente con entornos Sandbox (WispHub).

## Estructura Principal

- `portal/`: Núcleo del portal de clientes (Vistas, Procesadores).
- `config/`: Archivos de configuración de las diferentes APIs.
- `src/`: Lógica principal como clientes para las APIs (ej. `WispHubClient.php`).
- `paginas/`: Archivos base y helpers adicionales (Conexión, Helper BDV, Listas json).
- `tests/`: Pruebas unitarias e integración de flujos de pago.
