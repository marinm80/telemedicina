# Walkthrough — Entrega 1: Flujo de Reserva y Agenda (Gate 3)

Se ha completado de forma exitosa la reorganización estructural del proyecto y la implementación del backend de la **Entrega 1 del Módulo de Citas y Agenda**, con el rediseño de idempotencia en base de datos.

---

## 📁 Estructura del Monorrepo

Toda la aplicación se ha reorganizado para aislar las responsabilidades físicas:
- **`backend/`**: Laravel 11. Contiene la lógica API, migraciones, modelos, y la suite de pruebas.
- **`frontend/`**: Vue 3 + TypeScript + Vite + PrimeVue. Los archivos compilados se envían automáticamente al directorio `backend/public/build/` durante el empaquetado de producción.
- **`docker-compose.yml`**: Define los contenedores aislados de desarrollo:
  - `postgres`: PostgreSQL 16 (con extensiones `uuid-ossp` y `btree_gist` habilitadas). Mapea el puerto local a `5433` para no colisionar con la base de datos de Windows.
  - `redis`: Redis de caché y colas.
  - `backend`: PHP 8.4 CLI con el driver nativo de PostgreSQL `pdo_pgsql` compilado bajo Linux, solucionando las limitaciones del compilador en Windows.

---

## 🔑 Idempotencia Defendida en Base de Datos
Para solventar el defecto de "Verificar y Después Escribir" (TOCTOU) y cumplir con la regla **D2** (la seguridad de unicidad debe residir en el esquema):
1. **Esquema**: Se añadieron las columnas `idempotency_key` (UUID UNIQUE NULL) e `idempotency_payload_hash` (varchar(64) NULL) directamente en la tabla `appointments`.
2. **Atomicidad**: `BookAppointmentAction::handle()` realiza el insert directamente dentro de la transacción de base de datos.
3. **Manejo de Errores PostgreSQL**:
   - **`23505`** (Violación de clave única): Se trata de una reejecución de idempotencia (por ejemplo, doble clic concurrente). Se busca la cita existente y se verifica el payload hash. Si coincide, devuelve la cita original con un `201 Created`. Si difiere, lanza `IdempotencyCollisionException` (retornando `400 Bad Request`).
   - **`23P01`** (Violación de exclusión): El slot ya está tomado por otra cita activa del médico. Lanza `SlotCollisionException` (retornando `409 Conflict`).

---

## 🔒 Base de Datos y Seguridad RLS (Gate 2B & Gauntlet)

La migración inicial `2026_07_31_000000_create_initial_schema.php` inyecta las políticas estructurales:
- **Tipo timerange**: Se creó un tipo de rango personalizado en PostgreSQL para manejar horarios recurrentes planos sin fecha.
- **Bifurcación de Roles**: Creación segura y dinámica de `app_owner` (migraciones/seeders), `app_runtime` (web runtime, sujeto a RLS) y `app_worker` ( Horizon / Jobs / Asíncronos, sin `DELETE`).
- **Inmutabilidad**: Las tablas clínicas y de auditoría tienen permisos selectivos de DML (sin `DELETE`, y secuencias sin `UPDATE`).
- **Políticas RLS**: Bloqueo absoluto de perfiles y citas. Los pacientes sólo pueden leer sus propios registros o citas asociadas a médicos aprobados en su agenda.

---

## 🧪 Resultados de la Suite de Pruebas

Se ejecutó la suite de pruebas `BookAppointmentTest.php` dentro del contenedor con un resultado exitoso de **14 pruebas aprobadas y 70 aserciones**:

```bash
docker compose exec backend php artisan test
```

### 💎 Gauntlet RLS Aprobado
- **Gauntlet Rol Runtime**: Se aserta que la conexión ordinaria se ejecuta bajo el rol `app_runtime` y no como superusuario.
- **Ataque SQL de Lectura Directa**: Un paciente que intente realizar una consulta SQL directa (`SELECT * FROM patient_profiles`) sobre registros ajenos usando `app_runtime` con RLS activo recibe **0 filas**.
- **Inserción SOAP Autorizada**: RLS bloquea la inserción de borradores SOAP a pacientes y otros médicos no asignados, permitiendo únicamente el registro al médico a cargo de la consulta.

### ⚡ Concurrencia Real de Slots e Idempotencia
La suite ejecuta dos pruebas concurrentes reales abriendo **dos conexiones PDO concurrentes independientes** en la base de datos de PostgreSQL con contextos de RLS de pacientes distintos:
- **Colisión de Slot (409)**: Dos pacientes intentan reservar en paralelo el mismo slot libre. PostgreSQL frena uno e inmediatamente dispara una violación del índice de exclusión GIST de solapamiento (`23P01`).
- **Colisión de Idempotencia por Doble Clic (201/400)**: Dos peticiones del mismo paciente con la misma clave de idempotencia se ejecutan concurrentemente. PostgreSQL lanza una violación de restricción única (`23505` o `55P03` lock_timeout), que es interceptada por el Action para asegurar que sólo se cree una cita físicamente y que se responda exitosamente al cliente.

---
---

# Walkthrough — Entrega 2: Rediseño Visual Salvia + Dashboards por Rol

> **Fecha:** 2026-08-05
> **Alcance:** Rediseño completo del design system, layout y los 4 paneles de dashboard
> **Referencia de diseño:** `docs/rediseno_referencia/` (Dashboards.dc.html + mockups PNG)

---

## 🎨 Design System — Paleta Salvia

Se migró la paleta visual completa del prototipo genérico (azul/slate) a la paleta "Salvia" médica:

| Categoría | Token | Valor Hex | Uso |
|-----------|-------|-----------|-----|
| Primary Dark | `--color-primary` | `#17302B` | Sidebar, texto principal |
| Primary Mid | `--color-primary-600` | `#0E5D52` | Botones, cards activos |
| Accent | `--color-accent` | `#8FC9B3` | Badges, nav activo, avatar |
| Alert | `--color-alert` | `#D9603E` | Terracotta, badges urgentes |
| Page BG | `--color-page-bg` | `#FAF5EE` | Fondo crema cálido |
| Surface 0 | `--color-surface-0` | `#FFFFFF` | Cards, paneles |

Tipografía: **Inter** (cuerpo) + **Outfit** (headings, valores KPI).

**Archivos modificados:**
- [`tokens.css`](../frontend/src/assets/styles/tokens.css) — 49 tokens de color + spacing + radii + shadows
- [`primevue.preset.ts`](../frontend/src/config/primevue.preset.ts) — Preset PrimeVue 4 Aura migrado

---

## 🏗️ Sistema de Layout

### AppLayout.vue (reescrito)
- Integra `DemoBanner` + `AppSidebar` + `AppFooter`
- Proporciona `activeViewRole` vía `provide/inject` para que los dashboards reaccionen al role switcher del admin
- Layout full-width con CSS Grid responsive

### AppSidebar.vue (nuevo — 260px sticky)
- **Brand:** Logo badge 'S' + texto 'Salvia.'
- **Role Switcher:** Solo visible si `auth.user.role === 'admin'`. 3 botones (Administrador 🛡, Médico 🩺, Paciente 💚)
- **Navegación dinámica:** Menú cambia según el rol activo (admin: 6 items, doctor: 7, patient: 6, agent: 4)
- **User Card:** Avatar de iniciales + nombre + rol + logout
- **Responsive:** Colapsa a rail horizontal sticky en `< 920px`

### DashboardHeader.vue (nuevo)
- Props: `eyebrow`, `title`, `subtitle`, `statusText`, `actionText`, `actionHref`
- Status pill con dot verde + botón de acción primario

---

## 🧩 Componentes Reutilizables (6 nuevos)

| Componente | Props Clave | Función |
|------------|------------|---------|
| `StatCard` | `icon`, `label`, `value`, `trend`, `trendType`, `iconBg` | KPI con icono coloreado y tendencia |
| `DataTable` | `columns`, `rows`, `filters`, `activeFilter` + slots `#cell-{key}` | Tabla ligera con filtros pill |
| `BarChart` | `data [{label, value}]`, `color`, `title`, `total` | Barras CSS puro (sin Chart.js) |
| `AssistantWidget` | `message`, `actions [{text, href?, emit?}]` | Card oscuro "Asistente Salvia" |
| `AlertCard` | `title`, `subtitle`, `severity (warning\|critical)` | Alerta con animación pulsante |
| `ActivityFeed` | `items [{text, time}]` | Timeline vertical con dots |

> Ver [`docs/UI_COMPONENTS.md`](./UI_COMPONENTS.md) para documentación detallada de cada componente.

---

## 📊 4 Dashboards por Rol

### AdminDashboard.vue (de 35 → 321 líneas)
- 4 KPIs: usuarios activos, médicos por verificar, citas del mes, ingresos
- Tabla de verificación de médicos (join con specialties) con filtros y acciones
- BarChart de citas por día (últimos 7 días)
- AssistantWidget con stats del chatbot IA
- ActivityFeed + AlertCard para verificaciones pendientes

### DoctorDashboard.vue (de 43 → ~490 líneas)
- Banner de advertencia si perfil no aprobado
- Saludo dinámico por hora del día
- 4 KPIs: consultas hoy, notas pendientes, pacientes activos, ingresos
- Tabla de agenda del día con avatares, hora, motivo, estado, botones ficha/video
- BarChart semanal (color terracotta) + tarjeta de tareas pendientes

### PatientDashboard.vue (de 39 → ~530 líneas)
- 4 KPIs: próxima cita, recetas activas, consultas realizadas, cobertura
- Tabla "Mis citas" con filtros, avatares doctores, especialidad, acciones contextuales
- BarChart por mes (últimos 7 meses, color sage)
- Tarjeta de tratamiento actual con lista de prescripciones

### AgentDashboard.vue (de 38 → ~430 líneas)
- 3 KPIs: citas pendientes, médicos activos, citas de hoy
- Tabla de citas recientes con patient + doctor names
- Tarjeta de acciones rápidas (agendar, buscar, directorio)

---

## ⚙️ Cambios Backend

### DashboardController.php
Expandidos los métodos de cada dashboard para proporcionar datos reales:

| Método | Nuevas Props | Queries |
|--------|-------------|---------|
| `adminDashboard()` | `pending_doctors`, `chart_appointments_by_day` | Join users + specialties, `date_trunc` 7 días |
| `doctorDashboard()` | `today_appointments` (con patient names), `active_patients_count`, `chart_consultations_by_day`, `pending_tasks` | Join patient_profiles + users, distinct count, semana actual |
| `patientDashboard()` | `upcoming_appointments` (con doctor names + specialty), `chart_consultations_by_month` | Join doctor_profiles + users + specialties, 7 meses |
| `agentDashboard()` | `today_appointments_count`, `recent_appointments` (con names) | Join patient + doctor users, hoy |

Todos los queries envueltos en `try/catch` con valores por defecto para tolerar tablas faltantes.

### HandleInertiaRequests.php
Añadido `flash.success` y `flash.error` a shared props globales de Inertia.

---

## ✅ Verificación

| Check | Resultado |
|-------|-----------|
| `vue-tsc --noEmit` | ✅ Sin errores TypeScript |
| `vite build` | ✅ Compilación exitosa (4.39s) |
| Bundle JS | 550 KB (gzip: 154 KB) |
| Bundle CSS | 140 KB (gzip: 22 KB) |
| WCAG contraste | 3 pares menores pendientes en tokens.css |

---

## 📄 Documentación Actualizada

| Archivo | Cambio |
|---------|--------|
| [`docs/CHANGELOG.md`](./CHANGELOG.md) | **Nuevo** — Log completo de cambios (Keep a Changelog format) |
| [`docs/UI_COMPONENTS.md`](./UI_COMPONENTS.md) | **Nuevo** — Catálogo de componentes con props, slots, tokens |
| [`docs/UI_PROTOTYPE.md`](./UI_PROTOTYPE.md) | Actualizado árbol de componentes con los nuevos ★ |
| [`docs/MAPA_ARQUITECTURA.md`](./MAPA_ARQUITECTURA.md) | Actualizada estructura de carpetas del monorrepo |
| [`docs/walkthrough.md`](./walkthrough.md) | Este archivo — añadida Entrega 2 |
