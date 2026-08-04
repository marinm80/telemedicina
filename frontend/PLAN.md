# PLAN — Frontend Telemedicina

## Deuda declarada — Pendientes

> [!NOTE]
> Register.vue sigue pendiente de migración a `useForm` hasta que exista `POST /register`.

| Página | Qué falta | Cuándo |
|---|---|---|
| `Register.vue` | Migrar a `useForm` de Inertia | Cuando exista `POST /register` y su contrato |

---

## Páginas ajustadas al contrato real

| Página / Componente | RF | Fecha | Cambio |
|---|---|---|---|
| `AgendaManager.vue` | RF-08 Configuración de Agenda y Bloqueos | 2026-08-04 | Reescritura completa: 2 recursos (schedules + schedule-blocks), POST/DELETE individual con fetch, validación client-side, manejo 409/403/422 |
| `BookingWizard.vue` | RF-09 Reserva de Citas sin Solapamiento | 2026-08-04 | Fase 0: URL corregida a `/api/availability?doctor_id=&date=&timezone=`, payload `start_time/end_time` (sin `patient_id`), header `Idempotency-Key`, `available_slots`, formateo local |
| `Login.vue` | RF-01 Registro y Verificación de Paciente | 2026-08-04 | Agregado campo `remember` al useForm |
| `MyAppointments.vue` | RF-25 Cancelación de Citas y Reembolsos, RF-11 Solicitud y Aprobación de Reprogramación, RF-18 Generación de PDF y QR Clínico, RF-19 Acuse de Recibo de Paciente | 2026-08-04 | Cancel con `appointment_id`, modal de reprogramación, botón PDF download (425 Too Early), acknowledge |
| `VerifyNote.vue` | RF-18 Generación de PDF y QR Clínico | 2026-08-04 | NUEVA. Verificación pública por hash SHA-256 |
| `PreConsultation.vue` | RF-13 Cuestionario Pre-consulta | 2026-08-04 | NUEVA. Wizard 8 secciones con progress bar y pill nav |
| `ConsultationRoom.vue` | RF-14 Consulta por Chat en Tiempo Real, RF-15 Nota SOAP (Borrador a Firmada), RF-16 Firma Electrónica e Inmutabilidad, RF-17 Enmiendas Clínicas | 2026-08-04 | REESCRITURA. Chat como contenido principal (2/3 width), SOAP panel lateral (1/3, solo médico), polling 5s, firma irreversible, enmiendas post-firma |
| `PublicAssistant.vue` | RF-23 Asistente Informativo (Landing) | 2026-08-04 | NUEVO. Floating chat bubble en Landing con sugerencias, doctor cards inline |
| `ClinicalAssistant.vue` | RF-24 Asistente Clínico (Dashboard) | 2026-08-04 | NUEVO. Panel lateral con auto-hide durante consultas (409 ASSISTANT_DISABLED) |
| `RescheduleRequests.vue` | RF-11 Solicitud y Aprobación de Reprogramación | 2026-08-04 | NUEVO. Componente para dashboard médico: approve/reject con transacción ACID |
| `Landing.vue` | RF-23 Asistente Informativo (Landing) | 2026-08-04 | Integrado PublicAssistant widget |

---

## Barrera de contraste — hueco conocido

> [!WARNING]
> La barrera de contraste (`check-contrast.mjs`) solo audita pares de **modo claro**.
> Si el modo oscuro entra, la barrera no dice nada sobre él y el proyecto pierde la
> garantía WCAG AA sin que ningún check falle.
>
> Cuando se implemente dark mode, los pares oscuros van en la misma lista de `PAIRS`
> dentro de `check-contrast.mjs`.

---

## Estado de pruebas

| Suite | Tests | Qué cubre |
|---|---|---|
| `useAppState.test.ts` | 4 | 4 estados, error, abort, vacío |
| `loginValidation.test.ts` | 11 | Validación client-side, seguridad del mensaje de credenciales |
| `stateComponents.test.ts` | 18 | SpinnerLoader (aria-busy, role=status), ErrorFallback (role=alert, retry), EmptyState (mensaje, acción) |
| `timezone.test.ts` | 9 | Dos zonas, fecha fija, DST spring forward NY, zona sin DST BsAs, label con offset |
| `scheduleHelpers.test.ts` | 33 | parseFranja (6), timeToApi (3), validateSchedule (13), validateBlock (9), DAYS (2) |
| `appointmentHelpers.test.ts` | 24 | validateBooking (9): UUIDs, `start_time/end_time`, 30 min. validateCancel (4). refundLabel (3). validateReschedule (6) |
| **Total** | **99** | |
