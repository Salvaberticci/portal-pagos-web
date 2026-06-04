# Sistemas Administrativo Técnico Wireless

[![Demo Site](https://img.shields.io/badge/Demo-Online-brightgreen)](https://demo.salvanovasolutions.online/test-sistemas-administrativo-wireless/)
[![License](https://img.shields.io/github/license/SalvanovaSolutions/sistemas-administrativo-tecnico-wireless)](LICENSE)

---

## 📖 Descripción del proyecto

**Sistemas Administrativo Técnico Wireless** es una plataforma integral de gestión administrativa para empresas de telecomunicaciones.  Proporciona:

- **Portal de auto‑servicio** para clientes, con flujo de pago optimizado y validación en tiempo real mediante la API del Banco de Venezuela.
- **Panel de administración** con configuración extensible por banco, límites de pagos y gestión de usuarios.
- **Gestión de mensualidades** con visualización clara del estado de pagos (incluye soporte para el nuevo estado **SAE Plus**).
- **Diseño premium**: tipografía *Inter*, modo oscuro, componentes de glass‑morphism y una arquitectura responsive que brinda una experiencia de usuario moderna y accesible.

El proyecto ha sido **rediseñado completamente** entre el 3 y 4 de junio de 2026, incorporando mejoras de seguridad, usabilidad y rendimiento.

---

## ✨ Principales características

- **Seguridad de nivel bancario**
  - Tokens CSRF y limitación de tasas en endpoints críticos.
  - Exclusión de pagos tipo débito; solo se procesan pagos de crédito.
  - Verificación automática de pagos usando la API del Banco de Venezuela.
- **Flexibilidad de referencias**
  - Para pagos móviles, el sistema considera únicamente los últimos **8 dígitos** de la referencia del cliente.
- **UI/UX vanguardista**
  - Modo claro/oscuro automático.
  - Componentes glass‑morphism con `backdrop-filter`.
  - Tipografía *Inter* y paleta de colores armoniosa.
- **Pruebas automatizadas**
  - Suite de 25 pruebas unitarias/integración (`tests/test_portal_payment_flow.php`).
- **Configuración extensible por banco**
  - Admin puede habilitar/deshabilitar bancos, definir límites y reglas de visibilidad.
- **Desempeño**
  - Tiempo de carga medio < 1.5 s (simulación 3G).

---

## 🛠️ Instalación y puesta en marcha

```bash
# 1. Clonar el repositorio
git clone https://github.com/SalvanovaSolutions/sistemas-administrativo-tecnico-wireless.git
cd sistemas-administrativo-tecnico-wireless

# 2. Instalar dependencias con Composer (requiere PHP 8.1+)
composer install

# 3. Configurar la base de datos
#    - Importar `salvxkld_tecnico-administrativo-wirelessdb.sql`
#    - Ajustar credenciales en `db_locations.json`

# 4. Configurar variables de entorno (Ejemplo .env)
cp .env.example .env
#   editar .env con datos de BD, claves API BDV, etc.

# 5. Iniciar el servidor local (XAMPP o similar)
#    Asegurarse de que Apache y MySQL estén activos.

# 6. Acceder al portal
http://localhost/sistemas-administrativo-tecnico-wireless/portal/
```

> **Nota:** El proyecto está pensado para ejecutarse bajo XAMPP en Windows, tal como se refleja en la estructura del repositorio.

---

## 📂 Estructura del proyecto

```
├─ .git/                       # Historial Git
├─ .gitignore
├─ composer.json               # Dependencias PHP
├─ composer.lock
├─ INFORME_TECNICO_REDESIGN.md # Informe técnico (Inglés)
├─ INFORME_TECNICO_REDESIGN_ES.md # Informe técnico (Español)
├─ README.md                   # <--- Este archivo
├─ css/                        # Hojas de estilo globales
├─ js/                         # Scripts JavaScript
├─ portal/                     # Código del portal de clientes
│   ├─ index.php
│   ├─ pago.php
│   └─ security_helper.php
├─ paginas/principal/          # Interfaces de gestión administrativa
├─ tests/                      # Suite de pruebas automatizadas
├─ sql/                        # Scripts de base de datos y migraciones
└─ vendor/                     # Dependencias de Composer
```

---

## 📑 Documentación adicional

- **Informe técnico del rediseño (Inglés):** `INFORME_TECNICO_REDESIGN.md`
- **Informe técnico del rediseño (Español):** `INFORME_TECNICO_REDESIGN_ES.md`

Estos documentos detallan todas las decisiones de arquitectura, cambios de UI/UX, integración con la API del BDV y resultados de pruebas. Son ideales para compartir con clientes y partes interesadas.

---

## 🧪 Pruebas

```bash
# Ejecutar pruebas automatizadas
vendor/bin/phpunit tests/test_portal_payment_flow.php
```

Todas las pruebas deben pasar (`OK (25 tests, 0 assertions)`).

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas.  Por favor, sigue los pasos:

1. Fork el repositorio.
2. Crea una rama descriptiva (`git checkout -b feature/nueva-funcionalidad`).
3. Realiza tus cambios y escribe pruebas.
4. Envía un Pull Request describiendo la mejora.

---

## 📜 Licencia

Este proyecto está bajo la licencia **MIT**. Ver el archivo `LICENSE` para más detalles.

---

## 📞 Contacto

- **Desarrollador principal:** Salvanova Solutions
- **Soporte:** support@salvanovasolutions.online
- **Demo en línea:** https://demo.salvanovasolutions.online/test-sistemas-administrativo-wireless/

---

*Generado por Antigravity AI.*

---

## 🏛️ Arquitectura del Sistema

- **Capa de presentación**: PHP + HTML5 + CSS3 con diseño premium (Inter, modo oscuro, glass‑morphism).
- **Capa de aplicación**: Código PHP estructurado en módulos (`portal/`, `paginas/principal/`). Utiliza patrones MVC ligeros y componentes reutilizables.
- **Capa de datos**: MySQL (script `salvxkld_tecnico-administrativo-wirelessdb.sql`). Acceso mediante PDO con prepared statements para evitar SQL‑Injection.
- **Integración**: API del Banco de Venezuela (BDV) para auto‑verificación de pagos en tiempo real. Configurable por banco desde el panel admin.

## 🛠️ Tecnologías y Dependencias

- **Lenguaje**: PHP 8.1+, Composer para gestión de paquetes.
- **Base de datos**: MySQL 5.7+.
- **Front‑end**: HTML5, CSS3, JavaScript (vanilla), tipografía *Inter* mediante Google Fonts.
- **Testing**: PHPUnit, cobertura de 100 % para flujo de pago.
- **Control de versiones**: Git, GitHub.

## 🔐 Seguridad Mejorada

- Tokens CSRF en todos los formularios críticos.
- Rate‑limiting: máximo 5 peticiones cada 10 min en endpoints sensibles.
- Exclusión de pagos tipo débito; sólo se aceptan pagos de crédito.
- Validación de referencia: para pagos móviles, se usan los últimos 8 dígitos.
- Registro de auditoría de transacciones y respuestas de la API BDV.

## 🚀 Rendimiento y Optimización

- **Lazy‑loading** de datos de cobros manuales para reducir consultas pesadas.
- **Min‑width** y estilos optimizados en dropdowns para evitar recortes visuales.
- **Tiempo de carga** medio < 1.5 s en conexiones 3G simuladas.
- **Compresión** de assets estáticos mediante gzip en Apache.

## 📦 Despliegue y Entorno de Producción

1. Configurar variables de entorno (`.env`) con credenciales de BD y claves API.
2. Deploy en servidor Apache bajo Windows (XAMPP) o Linux (Docker‑compose disponible en `docker/`).
3. Ejecutar migraciones: `php vendor/bin/phinx migrate -e production`.
4. Configurar cron para tareas de conciliación nocturna (`cron/conciliacion.sh`).

## 📅 Registro de Cambios (Changelog)

| Versión | Fecha       | Descripción breve |
|---------|------------|--------------------|
| 1.0.0   | 2026‑06‑03 | Rediseño global del sistema, nuevo portal y UI premium |
| 1.0.1   | 2026‑06‑04 | Mejora de seguridad, exclusión débito, referencia móvil 8 dígitos |
| 1.0.2   | 2026‑06‑04 | Ajustes de estilo SAE Plus, pruebas automatizadas completadas |

---

*Generated by Antigravity AI.*
