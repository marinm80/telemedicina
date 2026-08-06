# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.7.0] - 2026-08-06 - Flujo Referidos → Agendamiento

### Añadido
- **Página `Patient/MyReferrals.vue`**: Página dedicada con 3 tabs (Pendientes / Aceptados / Completados). Cards con especialidad, motivo, médico referente, fecha y botón de acción.
- **Deep-link desde referido**: Si el médico especificó `referred_doctor_id` → directo a `/booking/{doctorProfileId}`. Si solo especialidad → `/directory?specialty_id=X`.
- **Ciclo de vida completo**: `pending` → `accepted` (al agendar cita) → `completed` (al firmar nota del especialista). Dos transiciones: `BookAppointmentAction` y `ConsultationFormController::archive()`.
- **Badge urgente**: Referidos con prioridad `urgente` muestran badge terracotta con animación pulsante y texto de urgencia. Se ordenan primero en cada tab.
- **Sidebar**: Nuevo item “Mis Referidos” para pacientes con badge de pendientes.
- **Endpoint `GET /api/specialties`**: Catálogo de especialidades activas para el select dinámico.
- **Ruta `/paciente/referidos`**: Página Inertia con joins a users, doctor_profiles, specialties y appointments.
- **`appointment_id` en referrals**: FK a appointments para rastrear la cita del especialista.

### Corregido
- **RLS referrals (P1)**: Políticas usaban `app.user_id` en vez de `app.current_user_id`. Corregido con DROP + CREATE.
- **specialty_id FK (P2)**: Agregado `specialty_id uuid REFERENCES specialties(id)` con resolución automática.
- **Rutas duplicadas**: Eliminado bloque `auth:sanctum` duplicado de referrals en `api.php`.

### Cambiado
- `ConsultationView.vue`: Input de especialidad cambiado de texto libre a `<select>` contra catálogo real (`GET /api/specialties`).
- `ReferralController::store()`: Resuelve `specialty_id` desde catálogo automáticamente.
- `ReferralController::index()`: Ordena por urgente primero, eager load de `appointment` y `specialty`.
- `BookAppointmentRequest`: Acepta `referral_id` (nullable uuid).

### Notas Técnicas
- `referrals.consultation_id` = consulta del médico que REFIERE (origen).
- `referrals.appointment_id` = cita que el paciente AGENDÓ con el especialista (destino).
- La transición `accepted→completed` ocurre dentro de `archive()` sin tocar el flujo de firma.
- `pendingReferrals` es un shared prop lazy-loaded solo para pacientes.

## [0.6.0] - 2026-08-06 - Filtro Medicina General + Sistema de Referidos

### Añadido
- **Filtro Medicina General**: El chat siempre dirige al paciente a un médico de Medicina General. El paciente NO elige especialidad — eso lo decide el médico general tras la consulta.
- **Sistema de Referidos (backend)**: Nueva tabla `referrals` con migración, modelo `Referral`, y `ReferralController` (store, index, update).
  - Tabla con RLS: política paciente (ver propios), doctor (CRUD propios), admin (todo).
  - Validación: solo el médico asignado puede crear referidos para su consulta.
  - Filtros por patient_id y consultation_id en el listado.
- **Sección "Referir a Especialista" en ConsultationView**: El médico general puede agregar 1+ referidos con especialidad (15 opciones), motivo, prioridad (normal/urgente), y notas.
- **Rutas API**: GET/POST `/api/referrals`, PUT `/api/referrals/{id}`.
- **Auto-selección**: Si solo hay 1 médico general disponible, se selecciona automáticamente.
- **Modalidad de consulta**: Nuevo paso en el chat para elegir entre 💻 Teleconsulta (remota) o 🏢 Presencial (en sitio). Se muestra en la confirmación, resumen y email demo.
- **Dirección clínica para citas presenciales**: Al elegir modalidad presencial, el chat muestra la dirección completa (Av. Los Próceres 1240, Torre Médica Salvia, piso 4), referencia, parqueo y horarios. Al confirmar la cita, se repite como recordatorio con fecha y hora.
- **Checklist pre-visita adaptable**: El checklist post-confirmación cambia según la modalidad — presencial incluye llegar 15 min antes y validar parqueo; teleconsulta incluye verificar cámara/mic y conexión.

### Cambiado
- `agentStateMachine.ts`: Estados `TIME_PREFERENCE`, `SUGGEST_PRESENCIAL`, `COLLECT_SYMPTOMS_ONSET` ahora saltan directamente a `SELECT_DOCTOR` (skip `SELECT_SPECIALTY`).
- `agentStateMachine.ts`: Nuevo estado `SELECT_MODALITY` entre triage y preferencia horaria. Campo `modality` en `AgentContext`.
- `FloatingAssistant.vue`: Eliminada función `selectSpecialty()`, eliminada sección template de `SELECT_SPECIALTY`, `matchingDoctors` auto-filtra a Medicina General.
- `SELECT_DOCTOR` message: "Te conectaremos con un médico de Medicina General que evaluará tu caso."

### Corregido
- **RLS referrals (P1)**: Las políticas usaban `app.user_id` y `app.user_role` en vez de la convención del proyecto `app.current_user_id` / `app.current_user_role`. `current_setting()` devolvía NULL → pacientes y médicos veían array vacío sin error. Corregido con DROP + CREATE policies.
- **specialty_id FK (P2)**: `specialty_name` era texto libre sin enlace al catálogo de especialidades. Agregado `specialty_id uuid REFERENCES specialties(id)` con resolución automática en `ReferralController.store()`.
- **Modelo Specialty.php**: Creado para completar la relación FK `Referral → Specialty`.

### Notas Técnicas
- La tabla `referrals` usa CHECK constraints para `priority` y `status`.
- Índices en `patient_id`, `consultation_id`, `referring_doctor_id`.
- El referido NO auto-crea cita con especialista — el paciente la agenda manualmente.

## [0.5.0] - 2026-08-06 - Agente Inteligente sin LLM (3 Fases)

### Añadido

**Fase 1 — State Machine + Emergency Detection + Demo Preview:**
- `frontend/src/lib/agentStateMachine.ts`: Motor de estados declarativo con 19 estados. Incluye detección de emergencia por keywords (dolor torácico, dificultad respirar, etc.), validaciones por estado, transiciones basadas en reglas, y audit logging con timestamp.
- `frontend/src/lib/agentDemoMessage.ts`: Generador de mensajes de confirmación de cita para modo demo/portafolio. Genera email preview con datos clínicos, instrucciones pre-visita, y enlace de teleconsulta simulado.
- `frontend/src/components/FloatingAssistant.vue`: Reescritura completa con state machine. Emergency check obligatorio antes de agendar. Estado EMERGENCY_STOP terminal con instrucciones 911. Modal de vista previa del email con botones Copiar/Descargar .txt/Enviar (demo). Botón reiniciar y escalado humano.

**Fase 2 — Triage Rules Engine + Slot Scoring:**
- `frontend/src/lib/agentTriageRules.ts`: Motor de reglas clínicas declarativas. 6 reglas priorizadas: EMRG-001 (emergency keywords), PRES-001/002/003 (presencial por severidad), ESCL-001 (escalado por alergias), TELE-DEFAULT. Cada regla retorna resultado, razón, y ID de regla para auditoría.
- `frontend/src/lib/agentSlotScoring.ts`: Sistema de scoring de horarios. Factores: preferencia horaria (+30), proximidad urgencia (+20), bonus matutino (+10). Genera top 3 recomendaciones con medallas (🥇🥈🥉) y razones.
- Nuevo estado TIME_PREFERENCE: permite al paciente indicar preferencia de mañana/tarde/noche antes de buscar slots.

**Fase 3 — Hold Temporal + Checklist Pre-visita:**
- Hold temporal de 5 minutos al seleccionar slot. Timer visual MM:SS con animación pulsante cuando queda < 1 minuto.
- Checklist pre-visita automática tras confirmar: formulario, documentos, medicamentos, cámara/mic, lugar tranquilo.
- Escalado humano: link permanente en footer + estado dedicado ESCALATE_HUMAN.

### Cambiado
- `FloatingAssistant.vue`: De 858 líneas con lógica ad-hoc a ~860 líneas con state machine + imports modulares.
- `agentStateMachine.ts`: TRIAGE_DECISION delegado al motor de reglas real (evaluateTriage) en vez de if/else básico.

### Notas Técnicas
- Todo el triage, scoring y detección de emergencia funciona sin LLM — puro pattern matching + reglas declarativas.
- El audit log registra: transiciones, inputs del usuario, opciones rápidas seleccionadas, resultados de triage, holds, y cancelaciones.
- El modal de demo message incluye: asunto, destinatarios simulados, cuerpo HTML con datos clínicos, botones Copiar/Descargar.
- El hold timer usa setInterval con cleanup en confirm/cancel/restart.

## [0.4.0] - 2026-08-06 - Reestructuración: Consultas, Recetas, Paginación

### Añadido

**Flujo de Consulta Médica:**
- `frontend/src/Pages/Doctor/ConsultationView.vue`: Formulario médico completo con 10 secciones (motivo, síntomas, historial, medicinas actuales, examen físico, resultados lab, diagnóstico, plan de tratamiento, seguimiento con cita automática).
- `backend/app/Http/Controllers/Api/ConsultationFormController.php`: Endpoints para guardar borrador (`POST /api/consultations/{id}/form`), archivar consulta (`POST /api/consultations/{id}/archive`), y obtener datos (`GET /api/consultations/{id}/form`).
- Botón 'Atender' en Mis Citas para médicos — crea consulta y navega a la vista de consulta.
- Endpoint `POST /api/consultations/{id}/start` para crear registro de consulta.

**Módulo de Recetas (Prescriptions):**
- Tabla `prescriptions` con campos JSONB para medicamentos, RLS, indexes.
- `backend/app/Http/Controllers/Api/PrescriptionController.php`: CRUD (index, store, update, destroy) con permisos por rol.
- Rutas API: `GET/POST/PUT/DELETE /api/prescriptions`.

**Panel de Control Admin:**
- `frontend/src/Pages/Admin/AdminPanel.vue`: Panel unificado con 3 tabs (Médicos, Usuarios, Configuración).
- Gestión de fichas de médicos con modal de edición.
- Configuración de horarios por médico (Lun-Dom).

**Directorio Médicos (Paciente):**
- `frontend/src/Pages/Patient/DoctorDirectory.vue`: Vista read-only con cards, búsqueda y filtro por especialidad.

**Horarios del Médico:**
- `frontend/src/Pages/Doctor/MisHorarios.vue`: Grilla semanal (Lun-Dom) para configurar franjas horarias.
- `backend/app/Http/Controllers/Api/DoctorScheduleController.php`: API CRUD para schedules.

**Historial de Citas:**
- 4 tabs en Mis Citas: Todas, Próximas, Completadas, Canceladas (con contadores).
- Filtro por paciente (dropdown, solo admin/médico).
- Información de cancelación visible: motivo y quién canceló.

**Dashboard Admin Paginado:**
- Tabla 'Todas las Citas' con paginación server-side (10/página).
- Buscador por nombre de médico o paciente (LIKE case-insensitive).
- Filtro por estado (Pendiente/Confirmada/Completada/Cancelada).
- Card 'Cancelaciones del mes' con dato real.
- Sidebar con resumen: pendientes, completadas, canceladas, tasa de cancelación.

**Validación Anti-solapamiento:**
- `backend/app/Actions/Appointments/BookAppointmentAction.php`: Validación `franja && tstzrange()` antes del INSERT para pacientes.
- `backend/app/Exceptions/PatientSlotCollisionException.php`: Nueva excepción → HTTP 409.

**Sidebar Dinámico:**
- `frontend/src/components/app/AppSidebar.vue`: Menú por rol (Admin: Resumen/Panel/Citas, Paciente: Directorio/Citas, Médico: Citas/Horarios).
- Badges reactivos con datos reales de Inertia shared props.

### Corregido
- `MyAppointments.vue`: Removidos datos mock, usa `defineProps` con datos reales de Inertia.
- `User.php`: `getRoleAttribute()` usa `pgsql_admin` con cache estático para evitar error 403.
- `HandleInertiaRequests.php`: Badges dinámicos `pendingDoctors` + `pendingAppointments` (antes hardcodeado '3').
- `AppointmentController.php`: Fix de citas duplicadas por LEFT JOIN multi-specialty — reemplazado por `leftJoinSub` con `string_agg()`.
- `AdminDashboard.vue`: Removidos datos fake ('Ingresos US$0', 'Asistente Salvia 312 conversaciones').

### Cambiado
- `DashboardController.php`: Usa `pgsql_admin` para bypass RLS. Soporta paginación (`page`), búsqueda (`search`), y filtro de estado (`status_filter`).
- Actividad reciente muestra datos reales con labels descriptivos por estado.

### Notas Técnicas
- Migración `2026_08_06_000001_create_prescriptions_table.php` ejecutada.
- Todas las consultas admin usan `DB::connection('pgsql_admin')` para bypass RLS.
- La tabla de citas del dashboard usa paginación server-side con preserveState/preserveScroll.
- El constraint GIST `appointments_sin_solapamiento` previene solapamiento a nivel de BD.

## [0.3.0] - 2026-08-05 - Rediseño Visual Salvia + Dashboards por Rol

### Añadido

**Design System:**
- `frontend/src/config/primevue.preset.ts`: Preset de PrimeVue 4 migrado a la paleta 'Salvia'. Escala de superficie warm-cream (#FAF9F5 a #091815), primarios teal (#E8F5F2 a #091815) y highlight scheme actualizado.
- `frontend/src/assets/styles/tokens.css`: Paleta completa de design tokens CSS: colores (primary, accent, alert, success, warning, error, info, clinical), tipografía (Inter 400-700 + Outfit 500-700), spacing (4px-80px), radii, shadows, transitions, z-index, breakpoints.

**Layout System (3 componentes nuevos):**
- `frontend/src/components/app/AppSidebar.vue`: Sidebar principal sticky (260px). Incluye:
  - Sección de marca 'Salvia' con icono badge.
  - Role switcher 'Ver como' (solo visible para admin) que emite `switch-role`.
  - Menú de navegación dinámico por rol (admin: 6 items, doctor: 7, patient: 6, agent: 4) con PrimeIcons y badges.
  - Tarjeta de usuario en footer con avatar de iniciales, nombre, rol, y botón de logout.
  - Modo responsive: rail horizontal sticky en pantallas < 920px.
- `frontend/src/components/app/DashboardHeader.vue`: Header reutilizable para dashboards. Props: `eyebrow`, `title`, `subtitle`, `statusText`, `statusDot`, `actionText`, `actionHref`. Emite: `action-click`.
- `frontend/src/components/DemoBanner.vue`: Banner sutil para entorno de demostración/desarrollo.
- `frontend/src/components/AppFooter.vue`: Footer con copyright '© 2026 Salvia — Plataforma de Telemedicina'.

**Componentes Dashboard Reutilizables (6):**
- `frontend/src/components/dashboard/StatCard.vue`: Tarjeta KPI con icono en círculo coloreado, valor grande, etiqueta, y texto de tendencia (positive/negative/neutral).
- `frontend/src/components/dashboard/DataTable.vue`: Tabla lightweight sin dependencia de PrimeVue DataTable. Soporta: filtros pill, columnas tipadas, slots por celda (`#cell-{key}`), estado vacío personalizable.
- `frontend/src/components/dashboard/BarChart.vue`: Gráfico de barras CSS puro (sin Chart.js). Barras verticales proporcionales con etiquetas y valores.
- `frontend/src/components/dashboard/AssistantWidget.vue`: Card oscuro (#0E5D52) del 'Asistente Salvia' con mensaje IA y enlaces de acción. Usa Inertia Link para navegación.
- `frontend/src/components/dashboard/AlertCard.vue`: Tarjeta de alerta urgente con severidad warning/critical. El modo critical incluye animación pulsante CSS.
- `frontend/src/components/dashboard/ActivityFeed.vue`: Timeline vertical de actividad reciente con dots conectados por línea.

### Cambiado

**Layout:**
- `frontend/src/layouts/AppLayout.vue`: Reescrito completamente.
  - Integra AppSidebar + DemoBanner + AppFooter.
  - Proporciona `activeViewRole` via `provide`/`inject` para que los dashboards reaccionen al role switcher del admin.
  - Layout full-width (eliminada restricción max-width 80rem anterior).
  - CSS Grid responsive: sidebar + main en desktop, stack vertical en mobile.

**Dashboards (4 archivos reescritos):**
- `frontend/src/Pages/Dashboard/AdminDashboard.vue`: De stub de 35 líneas a dashboard completo (321 líneas).
  - 4 StatCards: usuarios activos, médicos por verificar, citas del mes, ingresos del mes.
  - DataTable de verificación de médicos con filtros (Pendientes/En revisión/Aprobados), avatares, badges de especialidad y estado, botones de acción.
  - BarChart de citas por día (últimos 7 días).
  - AssistantWidget con estadísticas de conversaciones IA.
  - ActivityFeed de actividad reciente.
  - AlertCard para verificaciones pendientes (severity critical).

- `frontend/src/Pages/Dashboard/DoctorDashboard.vue`: De stub de 43 líneas a dashboard completo (~490 líneas).
  - Banner de advertencia si perfil no aprobado.
  - Saludo dinámico por hora del día ('Buenos días/tardes/noches, Dr(a). {apellido}').
  - 4 StatCards: consultas hoy, notas pendientes, pacientes activos, ingresos del mes.
  - DataTable agenda del día con filtros (Hoy/Mañana/Semana), avatares de pacientes, hora formateada, motivo, estado, botones ficha/videollamada.
  - BarChart consultas por día (semana en curso, color terracotta).
  - AssistantWidget con contexto clínico del próximo paciente.
  - AlertCard con la próxima consulta.
  - Tarjeta de tareas pendientes (notas clínicas borrador).

- `frontend/src/Pages/Dashboard/PatientDashboard.vue`: De stub de 39 líneas a dashboard completo (~530 líneas).
  - 4 StatCards: próxima cita, recetas activas, consultas realizadas, cobertura.
  - DataTable 'Mis citas' con filtros (Próximas/Pasadas/Canceladas), avatares de doctores, fecha, especialidad, estado, botones contextuales (Entrar/Reprogramar/Ver resumen).
  - BarChart consultas por mes (últimos 7 meses, color sage).
  - AssistantWidget con asistente de agendamiento y recetas.
  - AlertCard si hay consulta programada para hoy.
  - Tarjeta de tratamiento actual con lista de prescripciones.

- `frontend/src/Pages/Dashboard/AgentDashboard.vue`: De stub de 38 líneas a dashboard completo (~430 líneas).
  - 3 StatCards: citas pendientes, médicos activos, citas de hoy.
  - DataTable de citas recientes con avatares, doctor, fecha, estado, botón 'Ver detalle'.
  - AssistantWidget con asistente de disponibilidad.
  - Tarjeta de acciones rápidas (agendar, buscar paciente, directorio).

**Backend:**
- `backend/app/Http/Controllers/DashboardController.php`: Expandido significativamente.
  - `adminDashboard()`: Añadidos `pending_doctors` (join users + specialties), `chart_appointments_by_day` (date_trunc últimos 7 días), `recent_activity` placeholder. Todos los queries envueltos en try/catch.
  - `doctorDashboard()`: Añadidos `today_appointments` con join patient names, `active_patients_count` (distinct), `chart_consultations_by_day` (semana actual), `pending_tasks` (notas draft). Try/catch con fallback para columna 'reason'.
  - `patientDashboard()`: Añadidos `upcoming_appointments` con join doctor names + specialties, `active_prescriptions` (patient_medications), `chart_consultations_by_month` (últimos 7 meses). Try/catch.
  - `agentDashboard()`: Añadidos `today_appointments_count`, `recent_appointments` con join patient + doctor names. Try/catch.
  - Todas las queries usan `DB::table()` para respetar RLS de PostgreSQL.

- `backend/app/Http/Middleware/HandleInertiaRequests.php`: Añadido flash messages (success/error) a shared props de Inertia.

### Notas Técnicas
- Todo el CSS usa custom properties de `tokens.css` (cero clases de Tailwind).
- Los componentes usan `<script setup lang="ts">` (Composition API).
- El tipo `usePage().props.auth` requiere cast `(page.props as any)` por limitación de tipos de Inertia.
- El componente `BarChart.vue` es CSS puro para evitar dependencias externas como Chart.js.
- `DataTable.vue` usa slots dinámicos `#cell-{key}` con props `{ row, value }` para un renderizado personalizado.
- Bundle de producción optimizado: 550 KB JS + 140 KB CSS (gzipped: 154 KB + 22 KB).
