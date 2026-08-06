# 🏥 Informe Técnico — Plataforma de Telemedicina Salvia

> **Proyecto**: AI-Proyecto_11_Telemedicina (Portafolio)
> **Autor**: Rafael Marín · [rafaelmarin.dev](https://rafaelmarin.dev)
> **Fecha**: 2026-08-06
> **Versión actual**: 0.7.0
> **Commits totales**: 79

---

## 1. Descripción General

Plataforma de telemedicina completa que permite a pacientes agendar teleconsultas o citas presenciales con médicos. El sistema incluye un agente inteligente de agendamiento **sin LLM** (basado en state machine + reglas declarativas), sistema de referidos a especialistas, dashboards por rol, consultas médicas con notas SOAP, recetas, y un flujo clínico de extremo a extremo.

**Propósito**: Proyecto de portafolio técnico que demuestra competencias en full-stack development, arquitectura de seguridad (RLS), y diseño de sistemas clínicos.

---

## 2. Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| **Frontend** | Vue 3 (Composition API, `<script setup lang="ts">`) + Inertia.js + Vite |
| **Backend** | Laravel 11 (PHP 8.3) + Sanctum Auth |
| **Base de Datos** | PostgreSQL 16 con Row-Level Security (RLS) |
| **Extensiones PG** | `btree_gist` (exclusion constraints), `pgcrypto` |
| **Styling** | Vanilla CSS con design tokens (cero Tailwind en dashboards) |
| **Icons** | PrimeIcons |
| **Fonts** | Inter + Outfit (Google Fonts) |
| **Timezone** | Luxon (manejo de DST) |
| **Despliegue** | Docker-ready, Coolify compatible |

---

## 3. Arquitectura de Seguridad (PostgreSQL RLS)

### 3 Conexiones de Base de Datos
```
app_owner    → Migraciones y DDL (superuser)
app_runtime  → Web runtime (RLS enforced)
app_worker   → Background jobs
```

### Roles de la aplicación
| Rol | Permisos |
|-----|---------|
| `admin` | Bypass RLS vía `pgsql_admin`, CRUD total |
| `doctor` | Ver/editar sus propios pacientes y citas |
| `patient` | Ver/editar solo sus propios datos |
| `agent` | Acceso limitado a citas y disponibilidad |

### Tablas con RLS habilitado
`appointments`, `consultations`, `consultation_messages`, `consultation_notes`, `pre_consultation_forms`, `note_amendments`, `documents`, `vital_signs`, `reschedule_requests`, `patient_profiles`, `patient_allergies`, `referrals`

---

## 4. Estructura del Proyecto

### Métricas Generales
| Métrica | Valor |
|---------|-------|
| Archivos Vue | 50 componentes |
| Archivos TypeScript (lib) | 17 módulos |
| Archivos PHP (app/) | 88 clases |
| Migraciones | 20 |
| Tablas de BD | 28+ |
| Total frontend files | 79 |

### Top 10 Archivos por Líneas de Código
| Archivo | Líneas | Función |
|---------|--------|---------|
| ConsultationRoom.vue | 1,125 | Sala de videoconsulta |
| MyAppointments.vue | 1,000 | Historial de citas |
| ConsultationView.vue | 917 | Formulario médico + referidos |
| FloatingAssistant.vue | 889 | Agente de agendamiento |
| BookingWizard.vue | 698 | Wizard de reserva |
| PreConsultation.vue | 642 | Formulario pre-consulta |
| DoctorManager.vue | 626 | Gestión de médicos |
| ScheduleManager.vue | 545 | Config de horarios |
| SettingsManager.vue | 518 | Ajustes del sistema |
| agentStateMachine.ts | 495 | Motor de estados |

### Estructura de Directorios (Frontend)
```
frontend/src/
├── Pages/
│   ├── Admin/          → AdminPanel, DoctorManager, ScheduleManager, SettingsManager
│   ├── Appointments/   → AgendaManager, BookingWizard, MyAppointments
│   ├── Auth/           → Login, Register
│   ├── Clinical/       → ConsultationRoom, PreConsultation
│   ├── Dashboard/      → AdminDashboard, DoctorDashboard, PatientDashboard, AgentDashboard
│   ├── Doctor/         → ConsultationView, MisHorarios
│   └── Patient/        → DoctorDirectory
├── components/
│   ├── app/            → AppSidebar, DashboardHeader
│   ├── dashboard/      → StatCard, DataTable, BarChart, AssistantWidget, AlertCard, ActivityFeed
│   ├── landing/        → LandingHero, LandingBenefits, LandingDoctors, etc.
│   ├── ui/             → EmptyState, ErrorFallback, SpinnerLoader
│   ├── FloatingAssistant.vue (agente de agendamiento)
│   └── ClinicalAssistant.vue, PublicAssistant.vue, RescheduleRequests.vue
├── lib/
│   ├── agentStateMachine.ts   → Motor de estados (21 estados)
│   ├── agentTriageRules.ts    → 6 reglas clínicas de triage
│   ├── agentSlotScoring.ts    → Scoring de horarios
│   ├── agentDemoMessage.ts    → Generador de email demo
│   ├── apiClient.ts, appointmentHelpers.ts, timezone.ts, etc.
│   └── *.test.ts              → Tests unitarios
└── layouts/
    └── AppLayout.vue, LandingLayout.vue
```

### Estructura de Directorios (Backend)
```
backend/app/
├── Actions/
│   ├── Appointments/   → Book, Cancel, Reschedule, Availability (6 actions)
│   ├── Assistant/      → PublicAssistant, ClinicalAssistant
│   ├── Auth/           → RegisterPatient
│   ├── Clinical/       → SaveDraft, Sign, Amend, Acknowledge, SendMessage, PreConsultation (6)
│   ├── Payments/       → ProcessStripeWebhook
│   └── Schedules/      → Create, Delete, CreateBlock, DeleteBlock (4)
├── Http/Controllers/
│   ├── Api/            → 10+ controllers (Referral, Prescription, ConsultationForm, etc.)
│   ├── Appointments/   → CancelAppointmentController
│   └── AppointmentController, DashboardController
├── Models/             → User, Referral, Consultation, Prescription, etc.
├── Exceptions/         → 14 custom exceptions
└── Auth/               → SecureEloquentUserProvider
```

---

## 5. Base de Datos — Tablas Principales

| Tabla | Propósito |
|-------|----------|
| `users` | Usuarios con roles (admin, doctor, patient, agent) |
| `doctor_profiles` | Perfiles médicos (fee, experience, universidad) |
| `specialties` | 8 especialidades médicas |
| `schedules` | Horarios recurrentes de médicos |
| `schedule_blocks` | Bloqueos temporales de agenda |
| `appointments` | Citas con exclusion constraint (GIST) anti-solapamiento |
| `consultations` | Sesiones de consulta (started_at, ended_at) |
| `consultation_notes` | Notas SOAP firmadas con hash SHA-256 |
| `consultation_messages` | Chat en tiempo real durante consulta |
| `pre_consultation_forms` | Formularios pre-consulta (JSONB) |
| `prescriptions` | Recetas médicas (medications JSONB) |
| `referrals` | **Referidos a especialistas** (v0.6.0) |
| `reschedule_requests` | Solicitudes de reprogramación |
| `payments` / `commissions` | Pagos y comisiones (Stripe) |
| `audit_logs` | Trail de auditoría inmutable |

### Constraint Crítico
```sql
EXCLUDE USING gist (
  doctor_id WITH =,
  tstzrange(franja_inicio, franja_fin) WITH &&
) WHERE (status NOT IN ('cancelled', 'rescheduled'))
```
→ Previene solapamiento de citas a nivel de BD (no solo aplicación).

---

## 6. API Endpoints

### Citas y Disponibilidad
| Método | Ruta | Función |
|--------|------|---------|
| GET | `/api/doctors/{id}/availability` | Slots disponibles |
| POST | `/api/appointments` | Crear cita (idempotente) |
| POST | `/api/appointments/{id}/cancel` | Cancelar cita |
| POST | `/api/appointments/{id}/reschedule-request` | Solicitar reprogramación |
| PUT | `/api/appointments/{id}/reschedule-approve` | Aprobar reprogramación |
| PUT | `/api/appointments/{id}/reschedule-reject` | Rechazar reprogramación |

### Consultas Clínicas
| Método | Ruta | Función |
|--------|------|---------|
| POST | `/api/consultations/{id}/start` | Iniciar consulta |
| POST | `/api/consultations/{id}/form` | Guardar borrador SOAP |
| POST | `/api/consultations/{id}/archive` | Firmar y archivar |
| GET | `/api/consultations/{id}/form` | Obtener formulario |
| POST | `/api/consultations/{id}/messages` | Enviar mensaje chat |
| POST | `/api/consultations/{id}/notes/sign` | Firmar nota |
| POST | `/api/consultations/{id}/notes/amendments` | Agregar enmienda |

### Recetas y Referidos
| Método | Ruta | Función |
|--------|------|---------|
| GET/POST/PUT/DELETE | `/api/prescriptions` | CRUD recetas |
| GET/POST | `/api/referrals` | Listar/crear referidos |
| PUT | `/api/referrals/{id}` | Actualizar estado referido |

---

## 7. Agente Inteligente de Agendamiento (sin LLM)

### Arquitectura
```
State Machine (21 estados) → Triage Rules Engine → Slot Scoring → Demo Preview
```

### Flujo Completo del Agente
```
WELCOME → EMERGENCY_CHECK → COLLECT_MOTIVO → COLLECT_SYMPTOMS_ONSET
  → COLLECT_SYMPTOMS_SEVERITY → COLLECT_SYMPTOMS_DURATION
  → COLLECT_ALLERGIES → COLLECT_MEDICATIONS
  → TRIAGE_DECISION → SELECT_MODALITY (teleconsulta/presencial)
  → TIME_PREFERENCE (mañana/tarde/noche)
  → SELECT_DOCTOR (auto-filtrado a Medicina General)
  → SELECT_DATE → SELECT_SLOT (con scoring top 3)
  → CONFIRMATION (hold 5 min) → BOOKING_SUCCESS + DEMO_PREVIEW
```

### Componentes del Motor
| Módulo | Líneas | Función |
|--------|--------|---------|
| `agentStateMachine.ts` | 495 | 21 estados declarativos, context, audit |
| `agentTriageRules.ts` | 143 | 6 reglas clínicas priorizadas |
| `agentSlotScoring.ts` | 144 | Scoring por preferencia + urgencia + proximidad |
| `agentDemoMessage.ts` | 142 | Generador de email preview |

### Reglas de Triage
| ID | Condición | Resultado |
|----|-----------|----------|
| EMRG-001 | Keywords de emergencia | → EMERGENCY_STOP (911) |
| PRES-001 | Severidad severa + onset < 24h | → Presencial sugerido |
| PRES-002 | Severidad severa + duración constante | → Presencial sugerido |
| PRES-003 | Severidad moderada + onset < 24h + constante | → Presencial sugerido |
| ESCL-001 | Alergias múltiples + medicamentos | → Escalado humano |
| TELE-DEFAULT | Todo lo demás | → Teleconsulta |

### Features del Agente
- **Emergency detection**: 15+ keywords monitoreados en cada input
- **Triage clínico**: Motor de reglas declarativas con audit trail
- **Slot scoring**: Ranking con medallas 🥇🥈🥉
- **Hold temporal**: 5 min countdown con auto-expire
- **Modalidad**: Teleconsulta remota o presencial (con dirección)
- **Checklist pre-visita**: Adaptado a la modalidad elegida
- **Demo preview**: Email simulado con Copiar/Descargar
- **Escalado humano**: Link permanente + estado dedicado
- **Audit log**: Cada transición, input y decisión registrada

---

## 8. Sistema de Referidos (v0.6.0)

### Flujo
```
Paciente → Chat → Auto-Medicina General → Consulta
  → Médico general evalúa → Refiere a 1+ especialistas
  → Paciente agenda manualmente con especialista
```

### Tabla referrals
```sql
id, consultation_id, referring_doctor_id, patient_id,
specialty_name, referred_doctor_id, reason,
priority CHECK('normal','urgente'),
status CHECK('pending','accepted','completed','cancelled'),
notes, created_at, updated_at
```

### RLS Policies
- Paciente: SELECT propios
- Doctor: ALL donde es referring_doctor
- Admin: ALL

---

## 9. Datos Demo (Seeder)

### 8 Especialidades
Cardiología, Dermatología, Pediatría, Neurología, Traumatología, Psiquiatría, **Medicina General**, Ginecología

### 5 Médicos Demo
| Doctor | Especialidad | Zona Horaria | Fee |
|--------|-------------|--------------|-----|
| Dra. María García | Cardiología | Buenos Aires | $75,000 |
| Dr. Alejandro Ruiz | Dermatología | Tegucigalpa | $60,000 |
| Dra. Lucía Fernández | Pediatría | CDMX | $55,000 |
| Dr. Carlos Mendoza | Neurología | Bogotá | $80,000 |
| **Dra. Ana Torres** | **Medicina General**, Psiquiatría | Santo Domingo | $45,000 |

### 2 Pacientes Demo
- Juan Pérez (`patient@salvia.test`)
- María López (`maria@salvia.test`)

---

## 10. Documentación del Proyecto

| Archivo | Tamaño | Contenido |
|---------|--------|-----------|
| `docs/PRD.md` | 57 KB | Product Requirements Document completo |
| `docs/DATABASE_SCHEMA.md` | 55 KB | Esquema completo con SQL, RLS, triggers |
| `docs/API_CONTRACTS.md` | 29 KB | Contratos de API + Inertia props |
| `docs/DECISIONES_ALCANCE.md` | 23 KB | Decisiones de alcance y trade-offs |
| `docs/AUTHORIZATION.md` | 19 KB | Modelo de autorización y RLS |
| `docs/CHANGELOG.md` | 16 KB | Changelog v0.3.0 → v0.6.0 |
| `docs/UI_COMPONENTS.md` | 16 KB | Componentes UI documentados |
| `docs/MAPA_ARQUITECTURA.md` | 14 KB | Mapa de arquitectura del sistema |

---

## 11. Git History (últimos 20 commits)

```
a1cf02e docs: dirección presencial + checklist adaptable
677b486 feat: dirección de clínica para citas presenciales + checklist adaptable
6821c51 docs: modalidad teleconsulta/presencial documentada
22e7781 feat: opción de modalidad teleconsulta o presencial en el chat
2ac1b33 docs: documentación completa v0.6.0
ed77fb5 feat: filtro Medicina General + sistema de referidos a especialistas
7775bee docs: documentación del agente inteligente sin LLM v0.5.0
4726b29 feat(fase-3): hold temporal 5min + checklist pre-visita + timer visual
1b1bf2a feat(fase-2): motor de triage + scoring de slots + preferencia horaria
c9806d3 feat(fase-1): agente inteligente sin LLM — state machine + emergency + demo
acd1fa3 fix: formato de fechas en español con día de la semana
00a4c76 docs: actualización completa de documentación v0.4.0
21fa231 feat: dashboard admin - tabla de citas paginada (10/pág) + buscador
7fda6f3 feat: dashboard admin con datos reales + cancelaciones + sin ingresos fake
3a4c1c4 feat: historial de citas con tabs Completadas/Canceladas + filtro por paciente
80d5f34 fix: appointments duplicados por LEFT JOIN multi-specialty
a719810 feat: flujo consulta médica completo + módulo de recetas
5e7a5bf feat: flujo consulta médica - botón Atender + endpoint start consultation
6b320e7 feat: reestructuración completa del sistema de telemedicina
a9cbe04 fix: getRoleAttribute usa pgsql_admin para bypass RLS
```

---

## 12. Áreas para Revisión / Mejora Sugeridas

| Área | Detalle |
|------|---------|
| **Testing** | Existen tests unitarios para helpers pero faltan tests E2E y de integración para el flujo de agendamiento y consulta. |
| **Validación backend** | El ReferralController podría validar que `specialty_name` existe en la tabla `specialties`. |
| **WebSocket/Pusher** | El chat de consulta usa polling; podría migrar a WebSocket para tiempo real. |
| **PDF de consulta** | El endpoint `/api/consultations/{id}/pdf` está definido pero la generación PDF podría no estar completa. |
| **i18n** | Mensajes hardcodeados en español; no hay sistema de internacionalización formal. |
| **Rate limiting** | Sin rate limiting explícito en endpoints de booking. |
| **Soft deletes** | Appointments usan status cancelled, pero no hay soft delete formal en Eloquent. |
| **File uploads** | La sección de resultados de laboratorio en ConsultationView es placeholder (drag & drop UI sin backend). |
| **Referidos → auto-agendar** | Los referidos no auto-crean cita con el especialista; el paciente debe agendar manualmente. |
| **Mobile** | Responsive design implementado pero no testeado en dispositivos reales. |
