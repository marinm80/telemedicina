# 🏥 Informe Técnico — Plataforma de Telemedicina Salvia

> **Proyecto**: AI-Proyecto_11_Telemedicina (Portafolio)
> **Autor**: Rafael Marín · [rafaelmarin.dev](https://rafaelmarin.dev)
> **Fecha**: 2026-08-08
> **Producción**: [telemedicina.rafaelmarin.dev](https://telemedicina.rafaelmarin.dev)
> **Commits totales**: 104+

---

## 1. Descripción General

Plataforma de telemedicina completa que permite a pacientes agendar teleconsultas o citas presenciales con médicos. El sistema incluye un agente inteligente de agendamiento **sin LLM** (basado en state machine + reglas declarativas), sistema de referidos a especialistas, dashboards por rol, consultas médicas con notas SOAP firmadas, recetas, y un flujo clínico de extremo a extremo — todo desplegado y funcionando en producción real, no solo en desarrollo.

**Propósito**: Proyecto de portafolio técnico que demuestra competencias en full-stack development, arquitectura de seguridad (RLS a nivel de base de datos, no solo de aplicación), y diseño de sistemas clínicos con integridad verificable.

---

## 2. Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| **Frontend** | Vue 3 (Composition API, `<script setup lang="ts">`) + Inertia.js + Vite + TypeScript |
| **Backend** | Laravel 12 (PHP 8.4) |
| **Base de Datos** | PostgreSQL 17 con Row-Level Security (RLS) |
| **Extensiones PG** | `btree_gist` (exclusion constraints) |
| **Cache/Colas** | Redis |
| **Styling** | Vanilla CSS con design tokens (paleta "Salvia": teal oscuro, sage, terracotta, crema) |
| **Icons** | PrimeIcons |
| **Fonts** | Inter + Outfit (Google Fonts) |
| **Despliegue** | Docker multi-stage (Apache + PHP) → Coolify sobre VPS propio |

---

## 3. Arquitectura de Seguridad (PostgreSQL RLS)

La decisión de diseño central: **la seguridad no vive en el código PHP, vive en PostgreSQL.** Un `where()` olvidado no expone datos de otro usuario porque la base misma rechaza la fila.

### Conexiones de base de datos (Laravel) sobre roles de PostgreSQL

| Conexión Laravel | Rol / privilegio Postgres | Uso |
|---|---|---|
| `pgsql` | `app_runtime` — RLS enforced | Tráfico web normal |
| `pgsql_admin` | superusuario / bypass | Paneles y reportes administrativos |
| `pgsql_owner` | `app_owner` | Dueño declarado de las tablas |
| `pgsql_worker` | `app_worker` | Jobs en background, sin `DELETE` |
| `pgsql_migration` | — | Ejecutar migraciones |

Ninguno de los dos roles que atienden tráfico (`app_runtime`, `app_worker`) tiene `BYPASSRLS`.

### Cómo RLS sabe quién pregunta

`SetPostgresSessionContext` fija `app.current_user_id` y `app.current_user_role` en cada request **después** de que la sesión HTTP está cargada y **antes** de que Laravel rehidrate al usuario autenticado — invertir ese orden deja al usuario "deslogueado" en cada request, porque la consulta de rehidratación queda bloqueada por su propia RLS. Este fue un bug real encontrado y corregido en esta fase del proyecto.

### Tablas con RLS habilitado

`appointments`, `consultations`, `consultation_messages`, `consultation_notes`, `pre_consultation_forms`, `note_amendments`, `documents`, `vital_signs`, `reschedule_requests`, `patient_profiles`, `patient_allergies`, `referrals`, `doctor_profiles`, `schedules`, `schedule_blocks`, `user_roles`, entre otras.

### Otras defensas de la base, no de la aplicación

- **Anti-solapamiento**: `EXCLUDE USING gist (doctor_id WITH =, franja WITH &&)` — imposible insertar dos citas solapadas del mismo médico, sin importar el código.
- **Idempotencia**: clave de idempotencia + hash del payload en `appointments`.
- **Columnas protegidas**: `users.password`/`remember_token` no son legibles por `SELECT` directo bajo `app_runtime`; acceso solo vía funciones `SECURITY DEFINER` (`fn_user_for_auth`, `fn_rotate_remember_token`).
- **Auditoría por triggers**, no por listeners de Eloquent — captura escrituras SQL directas también.
- **Vistas públicas** con `security_barrier`: `v_doctor_directory` (sin correo ni licencia) y `v_schedule_blocks_availability` (sin el motivo del bloqueo).

Ver `docs/DATABASE_SCHEMA.md` para el detalle exhaustivo de tablas/políticas, y el `Registro_Trabajo_Telemedicina.pdf` (fuera del repo) para el registro completo de hallazgos de auditoría de seguridad.

---

## 4. Estructura del Proyecto

### Métricas Generales

| Métrica | Valor |
|---------|-------|
| Archivos Vue | 46 |
| Módulos TypeScript (`lib/`) | 13 |
| Clases PHP (`app/`) | 90 |
| Migraciones | 22 |

### Top archivos por líneas de código

| Archivo | Líneas | Función |
|---------|--------|---------|
| `ConsultationRoom.vue` | 1,263 | Sala de videoconsulta / chat clínico |
| `MyAppointments.vue` | 1,140 | Historial de citas |
| `AgendaManager.vue` | 985 | Configuración de agenda del médico |
| `FloatingAssistant.vue` | 979 | Agente de agendamiento sin LLM |
| `ConsultationView.vue` | 926 | Formulario médico + referidos |
| `BookingWizard.vue` | 815 | Wizard de reserva |
| `AdminPanel.vue` | 552 | Panel admin unificado (médicos + usuarios + config) |
| `PatientDashboard.vue` | 526 | Dashboard del paciente |

> `DoctorManager.vue`, `ScheduleManager.vue` y `SettingsManager.vue` (predecesores de `AdminPanel.vue`) y `VerifyNote.vue`/`PreConsultation.vue` (páginas sin ruta que las renderizara) se eliminaron del repositorio — quedaron huérfanos tras cambios de arquitectura posteriores.

### Estructura de Directorios (Frontend, actual)

```
frontend/src/
├── Pages/
│   ├── Admin/          → AdminPanel.vue (único — tabs: Médicos, Usuarios, Configuración)
│   ├── Appointments/   → AgendaManager, BookingWizard, MyAppointments
│   ├── Auth/           → Login, Register
│   ├── Clinical/       → ConsultationRoom
│   ├── Dashboard/      → AdminDashboard, DoctorDashboard, PatientDashboard, AgentDashboard
│   ├── Doctor/         → ConsultationView, MisHorarios
│   └── Patient/        → DoctorDirectory, MyReferrals
├── components/
│   ├── app/            → AppSidebar, DashboardHeader
│   ├── dashboard/      → StatCard, DataTable, BarChart, AssistantWidget, AlertCard, ActivityFeed
│   ├── landing/        → LandingHero, LandingBenefits, LandingDoctors, etc.
│   ├── ui/             → EmptyState, ErrorFallback, SpinnerLoader
│   └── FloatingAssistant.vue, ClinicalAssistant.vue, PublicAssistant.vue, RescheduleRequests.vue
├── lib/
│   ├── agentStateMachine.ts, agentTriageRules.ts, agentSlotScoring.ts, agentDemoMessage.ts
│   ├── currency.ts      → formatUSD() — único helper de moneda de toda la plataforma
│   ├── apiClient.ts, appointmentHelpers.ts, timezone.ts
│   └── *.test.ts
└── layouts/
    └── AppLayout.vue, LandingLayout.vue
```

### Panel de Administración (`AdminPanel.vue`)

Reemplazó tres páginas separadas (`DoctorManager`, `ScheduleManager`, `SettingsManager`). Tres tabs:

1. **Médicos**: filtro por estado, alta de médico con **foto de perfil real** (subida `multipart/form-data`, misma foto visible en landing + directorios), edición de ficha (universidad/años/tarifa/descripción — con un bug de guardado silencioso encontrado y corregido), gestión de horarios por médico inline.
2. **Usuarios**: búsqueda + filtro por rol, cambio de contraseña de cualquier usuario.
3. **Configuración**: informativa — versión de stack, conteos en vivo, checklist de seguridad. Nada editable todavía.

---

## 5. Base de Datos — Tablas Principales

| Tabla | Propósito |
|-------|----------|
| `users` | Usuarios con roles (admin, doctor, patient, agent) |
| `doctor_profiles` | Perfiles médicos (fee en USD, experiencia, universidad, `photo_path`) |
| `specialties` | 8 especialidades médicas |
| `schedules` | Horarios recurrentes de médicos |
| `schedule_blocks` | Bloqueos temporales de agenda |
| `appointments` | Citas con exclusion constraint (GIST) anti-solapamiento |
| `consultations` | Sesiones de consulta (started_at, ended_at) |
| `consultation_notes` | Notas SOAP firmadas con hash SHA-256 |
| `consultation_messages` | Chat durante consulta (polling) |
| `prescriptions` | Recetas médicas (medications JSONB) |
| `referrals` | Referidos a especialistas |
| `reschedule_requests` | Solicitudes de reprogramación |
| `audit_logs` | Trail de auditoría inmutable (triggers, no listeners) |

Ver `docs/DATABASE_SCHEMA.md` para el DDL completo, incluyendo `photo_path` en `doctor_profiles` y la tabla `referrals`, ambas agregadas después de la primera versión de este informe.

### Constraint Crítico

```sql
EXCLUDE USING gist (
  doctor_id WITH =,
  tstzrange(franja_inicio, franja_fin) WITH &&
) WHERE (status NOT IN ('cancelled', 'rescheduled'))
```

---

## 6. Agente Inteligente de Agendamiento (sin LLM)

```
State Machine (21 estados) → Triage Rules Engine → Slot Scoring → Demo Preview
```

Flujo: saludo → detección de emergencia por palabras clave → recolección de motivo/síntomas → motor de triage (6 reglas) → modalidad (teleconsulta/presencial) → filtrado a Medicina General → selección de horario con scoring (🥇🥈🥉) → hold de 5 min → confirmación.

Por qué sin LLM: 100% determinístico y auditable — cada transición y regla disparada queda en un log, valioso para un flujo con implicancia clínica.

---

## 7. Sistema de Referidos

```
Paciente → Chat → Auto-Medicina General → Consulta
  → Médico general evalúa → Refiere a 1+ especialistas
  → Paciente agenda manualmente con especialista
```

El paciente ve sus referidos en `/paciente/referidos`. **Limitación conocida y declarada**: el referido no auto-crea la cita con el especialista todavía; el paciente debe buscar y reservar manualmente.

---

## 8. Consulta Clínica y Notas SOAP

- El médico entra a la ficha longitudinal del paciente (alergias, condiciones, medicación) junto al chat y la nota SOAP.
- La nota es **borrador → firmada**. Firmar calcula un hash SHA-256; la nota firmada es inmutable — solo se agregan **enmiendas**.
- El paciente puede dar acuse de recibo (opcional, no bloquea la validez del informe).

---

## 9. Datos Demo (Seeder)

### 8 Especialidades
Cardiología, Dermatología, Pediatría, Neurología, Traumatología, Psiquiatría, Medicina General, Ginecología

### Médicos Demo (tarifas en USD desde esta fase del proyecto)

| Doctor | Especialidad | Fee (USD) |
|--------|-------------|-----------|
| Dra. María García | Cardiología | $75 |
| Dr. Alejandro Ruiz | Dermatología | $60 |
| Dra. Lucía Fernández | Pediatría | $55 |
| Dr. Carlos Mendoza | Cardiología, Neurología | $45 |
| Dra. Ana Torres | Medicina General, Psiquiatría | $45 |
| Dra. Julieta Marras | Neurología | $80 |

> Todos los médicos demo tienen foto real (Pexels, licencia libre) subida a través del mismo flujo de alta del panel admin — no hay ninguna foto "hardcodeada" en el código del landing.

### Cuentas de Demostración

Ver `README.md` (raíz del repo) para el listado completo de credenciales — todas comparten `Password123!` (seeder principal) o `password` (seeder de datos demo extendido).

---

## 10. Documentación del Proyecto

| Archivo | Contenido |
|---------|-----------|
| `docs/PRD.md` | Product Requirements Document completo (fuente única de alcance) |
| `docs/DECISIONES_ALCANCE.md` | Decisiones de arquitectura y trade-offs, incluye entradas posteriores al PRD v2.0 |
| `docs/DATABASE_SCHEMA.md` | Esquema completo con SQL, RLS, triggers |
| `docs/API_CONTRACTS.md` | Contratos de API + Inertia props |
| `docs/AUTHORIZATION.md` | Modelo de autorización y RLS |
| `docs/CHANGELOG.md` | Changelog completo (Keep a Changelog) |
| `docs/UI_COMPONENTS.md` | Componentes UI documentados (props, slots, tokens) |
| `docs/UI_PROTOTYPE.md` | Wireframes, árbol de componentes, estados de UI |
| `docs/MAPA_ARQUITECTURA.md` | Mapa de arquitectura del sistema |
| `docs/BRIEF_REFERIDOS_AUTOAGENDAR.md` | Brief de diseño del sistema de referidos (implementado) |

---

## 11. Áreas para Revisión / Mejora Sugeridas

| Área | Detalle |
|------|---------|
| **Referidos → auto-agendar** | El referido no auto-crea cita con el especialista; el paciente agenda manualmente. |
| **Chat de consulta** | Usa polling, no WebSocket/Pusher — funcional pero no en tiempo real puro. |
| **Adjuntos de laboratorio** | La UI de drag & drop en `ConsultationView` es solo interfaz, sin backend de almacenamiento real. |
| **Rol Agente** | Sus flujos específicos se probaron menos a fondo que los otros 3 roles en el último diagnóstico general. |
| **Contraste WCAG en modo oscuro** | `check-contrast.mjs` solo audita pares en modo claro; si se agrega dark mode, esa barrera no cubre los pares oscuros. |
| **Validación backend de referidos** | `ReferralController` podría validar que `specialty_name` existe en el catálogo `specialties`. |
| **Rate limiting** | Sin rate limiting explícito en endpoints de booking. |
| **i18n** | Mensajes hardcodeados en español; sin sistema de internacionalización formal. |
| **Deuda de auditoría de seguridad (H6, H8, H10)** | Declarada en una fase anterior del proyecto; no se reverificó su estado exacto en la última sesión de trabajo — ver `Registro_Trabajo_Telemedicina.pdf`. |
