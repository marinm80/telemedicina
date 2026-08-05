# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
