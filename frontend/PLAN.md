# PLAN — Frontend Telemedicina

## Deuda declarada — Páginas construidas contra contrato inventado

> [!CAUTION]
> Las siguientes páginas fueron construidas ANTES de que su contrato de API existiera.
> Los props, la forma del flujo, y la estructura de la página adivinan lo que el
> backend va a entregar. Cuando el contrato real llegue, estas páginas se reescriben.
> El mock aísla los DATOS, no la FORMA.

| Página | RF del que depende | Estado del contrato | Riesgo |
|---|---|---|---|
| `ConsultationRoom.vue` | RF-14 Consulta por Chat en Tiempo Real, RF-15 Nota SOAP (Borrador a Firmada) | **NO EXISTE**. Ni especificación hay. | Reescritura total probable |

**Regla:** No se agrega ninguna página más hasta que su contrato exista.

---

## Migraciones pendientes (BLOQUEADAS hasta que exista ruta + contrato)

| Página | Qué falta | Cuándo |
|---|---|---|
| `Register.vue` | Migrar a `useForm` de Inertia | Cuando exista `POST /register` y su contrato |

---

## Páginas ajustadas al contrato real

| Página | RF | Fecha | Cambio |
|---|---|---|---|
| `AgendaManager.vue` | RF-08 Configuración de Agenda y Bloqueos | 2026-08-04 | Reescritura completa: 2 recursos (schedules + schedule-blocks), POST/DELETE individual con fetch, validación client-side, manejo 409/403/422 |
| `BookingWizard.vue` | RF-09 Reserva de Citas sin Solapamiento | 2026-08-04 | Reescritura: GET availability real, POST /api/appointments con X-Idempotency-Key, validación franja_inicio/franja_fin (30 min exactos), manejo 409 solapamiento |
| `Login.vue` | RF-01 Registro y Verificación de Paciente | 2026-08-04 | Agregado campo `remember` al useForm |
| `MyAppointments.vue` | RF-25 Cancelación de Citas y Reembolsos | 2026-08-04 | Botón cancelar funcional: modal con reason (max 500), POST /api/appointments/{id}/cancel, display refund_percentage/refund_status, manejo 409/403 |

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
| `appointmentHelpers.test.ts` | 20 | validateBooking (12): UUIDs, fechas, 30 min exactos. validateCancel (4): nullable, max 500. refundLabel (3) |
| **Total** | **95** | |
