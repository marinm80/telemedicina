# PLAN — Frontend Telemedicina

## Deuda declarada — Páginas construidas contra contrato inventado

> [!CAUTION]
> Las siguientes páginas fueron construidas ANTES de que su contrato de API existiera.
> Los props, la forma del flujo, y la estructura de la página adivinan lo que el
> backend va a entregar. Cuando el contrato real llegue, estas páginas se reescriben.
> El mock aísla los DATOS, no la FORMA.

| Página | RF del que depende | Estado del contrato | Riesgo |
|---|---|---|---|
| `ConsultationRoom.vue` | RF-12 (consulta en vivo), RF-13 (nota SOAP) | **NO EXISTE**. Ni especificación hay. | Reescritura total probable |
| `AgendaManager.vue` | RF-08 (escritura de agenda) | **En definición** del otro lado. | Modelo de datos adivinado |
| `BookingWizard.vue` | RF-03 (reserva de cita) | API_CONTRACTS.md §3 tiene lectura, **escritura adivinada** | Flujo de pasos y props adivinados |

**Regla:** No se agrega ninguna página más hasta que su contrato exista.

---

## Migraciones pendientes (BLOQUEADAS hasta que exista ruta + contrato)

| Página | Qué falta | Cuándo |
|---|---|---|
| `Register.vue` | Migrar a `useForm` de Inertia | Cuando exista `POST /register` y su contrato |
| `BookingWizard.vue` | Migrar mutación a `useForm` | Cuando RF-03 escritura cierre |
| `AgendaManager.vue` | Migrar a `useForm` | Cuando RF-08 escritura cierre |

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
| **Total** | **42** | |
