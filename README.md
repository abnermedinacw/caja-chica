# T-RINDE — Sistema de Gestión de Caja Chica y Pagos Bancarios

**T-RINDE** es una aplicación web empresarial para gestionar el ciclo completo de **fondos de caja chica, rendición de gastos, aprobaciones multi-nivel y desembolsos bancarios masivos** en formato TXT para **BBVA Net Cash** y **BCP Telecrédito**.

---

## Stack Tecnológico

| Capa | Tecnología |
| --- | --- |
| **Backend** | PHP 8 (arquitectura por capas: `Controllers` → `Negocio` → `Datos` → `Vistas`), PDO con consultas preparadas |
| **Base de datos** | MariaDB 10.4 (InnoDB, transacciones `FOR UPDATE`, columnas generadas `STORED`, trazabilidad por eventos) |
| **Frontend** | Vue 3 (ES Modules nativos con `importmap`, sin build step) + CSS modular y diseño responsivo móvil |
| **Generación de PDF** | FPDF + FPDI (consolidación de comprobantes, firmas/rúbricas digitales, mosaico de evidencias de movilidad) |
| **Pagos Bancarios** | Generación de TXT de ancho fijo para **BBVA Net Cash** y **BCP Telecrédito (Haberes)** |
| **Integraciones** | Consulta RUC, verificación de CPE SUNAT, sincronización desde BD externa de planilla, correo SMTP, Sentry |
| **Entorno local** | Docker + Docker Compose (Apache/PHP + MariaDB 10.4) |

---

## Funcionalidades Principales

### 1. Fondos y Rendición de Caja Chica
- Gestión de fondos **Recurrentes** (con reposición periódica) y **No Recurrentes** (con cierre automático al rendir y cálculo exacto de excedente a reembolsar).
- Registro de documentos **Contables**, **No Contables** y **Planilla de Movilidad**, con validación de comprobantes electrónicos (CPE SUNAT) y consulta de RUC.
- Carga de evidencias en PDF e imágenes; exportación de **Resumen Consolidado en PDF** con firmas digitales por etapa y mosaico automático de capturas para planillas de movilidad.
- Reutilización segura de correlativos al anular resúmenes mediante columna generada `STORED` (`numero_activo`) e índices únicos.

### 2. Flujo de Aprobación Multi-nivel
- Circuito configurable por etapas: **Jefatura → Contabilidad → Control → Gerencia → Tesorería**.
- Bandeja de pendientes, historial de aprobaciones, devolución con motivo de rechazo y reenvío en modo corrección sin perder el historial.
- Notificaciones por correo en cada transición y registro completo de auditoría en `evento_caja_chica`.

### 3. Pagos Bancarios Multi-banco (BBVA + BCP)
- Módulo unificado de **Pagos Bancarios** para desembolso de fondos y reembolso de cajas visadas, agrupado por empresa matriz y banco emisor (**BBVA Net Cash** / **BCP Telecrédito**).
- Generación exacta del archivo **TXT BCP Telecrédito Haberes** (cabecera de 113 caracteres, detalle de 195 caracteres, checksum oficial de 15 dígitos, normalización ASCII y fecha de proceso en zona horaria `America/Lima`).
- Descarga simultánea del archivo **TXT bancario** y su **Reporte PDF de desembolso** con detalle de beneficiarios y totales.
- **Control estricto anti-cruce:** separación por banco predeterminado del trabajador en frontend y revalidación transaccional (`FOR UPDATE`) en backend antes de emitir el TXT.
- **Validación de titularidad en sistema:** cuando un trabajador agrega o modifica una cuenta en su perfil, Tesorería recibe una notificación interna (badge en navegación + bandeja de validación) y debe confirmar la titularidad antes de habilitar pagos a esa cuenta.
- Pestaña de **Reactivación** exclusiva para administradores en caso de requerir re-emitir un lote.

### 4. Perfil, Trabajadores y Experiencia Móvil
- Sincronización de trabajadores y cuentas bancarias (BBVA y BCP) desde base de datos externa de planilla.
- En **Mi perfil**, el trabajador administra su correo, firma personal, cuentas BBVA/BCP y selecciona su banco predeterminado directamente sobre las tarjetas de cuenta con confirmación previa.
- Interfaz completamente responsiva (`responsive.css`): barra superior fija y menú lateral *off-canvas* en móvil, transformación *Table → Cards*, acordeón para documentos y modales estilo *bottom-sheet*.

---

## Roles del Sistema

| Rol | Alcance principal |
| --- | --- |
| **Solicitante** | Crea y rinde cajas chicas sobre sus fondos activos, adjunta sustentos y gestiona sus cuentas/firma en su perfil. |
| **Jefatura** | Aprueba o rechaza rendiciones de su equipo y consulta los fondos asignados a su área. |
| **Contabilidad / Control / Gerencia** | Revisan, observan o aprueban rendiciones en sus respectivas etapas y auditan el historial y fondos. |
| **Tesorería** | Asigna/anula fondos, realiza el visado final, valida cuentas bancarias nuevas de trabajadores y genera los TXT de pago BBVA/BCP. |
| **Administrador** | Sincroniza trabajadores, administra resúmenes (consulta/anulación) y reactiva descargas TXT cuando corresponde. |

---

## Estructura del Proyecto

```text
caja-chica/
├── config/                  # Configuración de BD, rutas, correo, BBVA, BCP, RUC y SUNAT
├── database/                # Guías y scripts de estructura / limpieza de datos
├── docker/                  # Dockerfile y configuración de Apache/PHP
├── public/                  # Punto de entrada (index.php), CSS modular, JS (Vue 3) e íconos/logos SVG
│   ├── css/                 # base.css, componentes.css, modulos.css, responsive.css, login.css
│   ├── img/                 # Logos bancarios SVG (BBVA, BCP)
│   └── js/                  # Módulos Vue 3 por vista y utilidades (descarga TXT/PDF, formatos)
├── src/
│   ├── Controllers/         # Endpoints HTTP y control de acceso por sesión/rol
│   ├── Datos/               # Consultas SQL (PDO) organizadas por entidad
│   ├── Negocio/             # Reglas de negocio, aprobaciones, motores TXT (BBVA/BCP) y PDF
│   ├── Vistas/              # Vistas PHP + templates Vue 3, layout y helpers
│   ├── fpdf/                # Librerías FPDF/FPDI para generación y fusión de PDFs
│   └── pdf/                 # Plantillas PDF de resúmenes y anexos de evidencias
├── storage/                 # Adjuntos, firmas digitales y sellos (no versionados)
├── docker-compose.yml       # Orquestación local (app PHP + MariaDB 10.4)
└── composer.json            # Dependencias PHP (Sentry)
```

---

## Puesta en Marcha Local (Docker)

### Requisitos
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (o Docker Engine + Docker Compose)

### Pasos
1. **Clonar el repositorio:**
   ```bash
   git clone <url-del-repositorio>
   cd caja-chica
   ```
2. **Configurar archivos locales (opcional para integraciones externas):**
   - Copiar `config/ruc.example.php` → `config/ruc.php`
   - Copiar `config/sunat.example.php` → `config/sunat.php`
   - Revisar `config/correo.php` y `config/database.php` (en Docker, `database.php` toma las variables de entorno definidas en `docker-compose.yml`).
3. **Levantar los contenedores:**
   ```bash
   docker compose up -d --build
   ```
4. **Acceder a la aplicación:**
   - Web: `http://localhost:8080`
   - Base de datos MariaDB: `127.0.0.1:3307` (base de datos `caja_chica_demo`, configurada en `docker-compose.yml`)

---

## Decisiones Técnicas Destacadas

- **Integridad contra doble pago:** la generación de TXT bloquea las filas seleccionadas con `SELECT ... FOR UPDATE` dentro de una transacción PDO; si falla cualquier validación (cuenta, importe, banco predeterminado o estado), se ejecuta `rollBack()` completo sin marcar nada como descargado.
- **Reutilización limpia de correlativos:** la columna generada `STORED` `numero_activo = IF(estado_general = 'ANULADO', NULL, numero)` combinada con índices `UNIQUE` permite que los resúmenes anulados liberen su correlativo sin perder trazabilidad histórica ni depender de bloqueos manuales frágiles.
- **Frontend ligero sin paso de compilación:** Vue 3 en modo ESM nativo con un `importmap` generado dinámicamente desde PHP (`mapaModulosJs()` + `assetUrl()` con `filemtime`), logrando despliegue inmediato en hosting compartido (cPanel) y invalidación automática de caché.
- **Descarga dual resiliente:** los lotes de pago devuelven en una sola respuesta JSON el TXT bancario y el PDF de soporte codificados en base64, descargados secuencialmente en cliente con margen de liberación de `Blob URL` para evitar truncamientos (`.crdownload`) en navegadores Chromium.

---

## Capturas de Pantalla

> _Espacio reservado para agregar capturas con datos de demostración en `docs/screenshots/` (Bandeja de aprobaciones, Rendición de resumen, Pagos Bancarios BBVA/BCP y Vista móvil)._
