# Frontend Foundation — Capa sin dependencia de contrato

> **Fecha:** 2026-08-03 · **Autor de la sesión:** Agente frontend (AntiGravity)
> **Estado:** Aprobado con correcciones del humano (ver sección de decisiones)
> **Territorio:** solo `frontend/`. Nada en `backend/`, `docs/` ni `agent/`.

Capa de infraestructura del frontend Vue 3 + PrimeVue que puede construirse **en
paralelo** con el backend porque ningún componente hace fetch: toda llamada de red
pasa por un único módulo (`apiClient`) que hoy devuelve datos simulados.

---

## Decisiones del humano incorporadas

| # | Decisión | Motivo declarado |
|---|---|---|
| 1 | **Sin Luxon.** Zona horaria con `Intl.DateTimeFormat` nativo + opción `timeZone` | El backend manda `timestamptz` y calcula slots en el servidor. El frontend solo muestra un instante en la zona IANA del usuario. Luxon se justifica para aritmética dentro de una zona, y esa aritmética vive en PostgreSQL. Una dependencia se justifica cuando hace algo que la plataforma no puede, no cuando es más cómoda |
| 2 | **Preset Aura** (PrimeVue 4) | Es el actual. Pero la restricción real: los componentes consumen TOKENS de diseño, nunca el preset directamente. Cambiar de preset tiene que costar un archivo, no cuarenta componentes |
| 3 | **Paleta la define el humano** | Se recibirá en mensaje posterior. Restricciones no negociables: (a) color de error distinguible del color de marca, (b) contraste WCAG AA mínimo (hay datos clínicos en pantalla), (c) tokens de estado (éxito, error, advertencia, información) separados de tokens de marca desde el inicio |
| 4 | **El plan vive en el repositorio** (`frontend/PLAN.md`) | Un plan que vive solo en la interfaz del agente no se puede auditar |

---

## Árbol de archivos — estado final de esta entrega

```
frontend/src/
├── main.ts                          [MODIFY] — registra PrimeVue con tema, i18n plugin
├── vite-env.d.ts                    [NO CHANGE]
├── assets/
│   └── styles/
│       ├── tokens.css               [NEW] — variables CSS: colores, sombras, radios, espaciado
│       ├── typography.css           [NEW] — escala tipográfica, font-face
│       └── base.css                 [NEW] — reset mínimo, importa tokens + typography
├── config/
│   └── primevue.preset.ts           [NEW] — preset Aura sobrescrito con tokens propios
├── types/
│   ├── api.types.ts                 [NEW] — tipos derivados de API_CONTRACTS.md
│   ├── auth.types.ts                [NEW] — tipos de usuario/sesión (mínimos)
│   └── common.types.ts              [NEW] — EstadoCarga, ApiErrorResponse, PaginatedResponse
├── lib/
│   ├── apiClient.ts                 [NEW] — único punto de red, hoy retorna mocks
│   ├── apiClient.mock.ts            [NEW] — datos simulados de API_CONTRACTS.md
│   └── timezone.ts                  [NEW] — wrappers de Intl.DateTimeFormat (cero dependencias)
├── i18n/
│   ├── plugin.ts                    [NEW] — plugin Vue ligero con función t()
│   └── es.ts                        [NEW] — claves de traducción en español
├── composables/
│   └── useAppState.ts               [NEW] — composable genérico de 4 estados
├── components/
│   └── ui/
│       ├── SpinnerLoader.vue        [NEW] — estado: cargando (skeleton configurable)
│       ├── ErrorFallback.vue        [NEW] — estado: error con reintento
│       └── EmptyState.vue           [NEW] — estado: vacío con acción
├── layouts/
│   ├── AppLayout.vue                [NEW] — shell: cintillo demo + sidebar + footer
│   ├── DemoBanner.vue               [NEW] — cintillo de modo demo (PORTAFOLIO)
│   └── AppFooter.vue                [NEW] — footer de créditos
└── pages/
    └── Dashboard.vue                [NEW] — stub para verificar infraestructura
```

---

## Detalle por módulo

### 1. Sistema de diseño (tokens + tipografía + preset PrimeVue)

**`tokens.css`** — Custom properties CSS organizadas en tres capas:

```
/* MARCA — los define el humano, pendiente de recibir */
--color-primary-*         (escala 50–950)
--color-secondary-*

/* ESTADO — separados de marca, no negociable */
--color-success-*
--color-error-*           (debe distinguirse del color de marca)
--color-warning-*
--color-info-*

/* SUPERFICIE — neutrales para fondos, bordes, texto */
--color-surface-*
--color-text-*

/* ESPACIADO — escala de 4px */
--spacing-1 a --spacing-16

/* RADIOS, SOMBRAS, TRANSICIONES */
--radius-*
--shadow-*
--transition-*
```

**Restricción WCAG AA:** todo par texto/fondo cumple ratio ≥ 4.5:1 (texto normal)
o ≥ 3:1 (texto grande). Se declara en el archivo y se verifica manualmente.

**`primevue.preset.ts`** — Extiende Aura remapeando los design tokens de PrimeVue
a las custom properties de `tokens.css`. Los componentes PrimeVue consumen tokens,
no valores directos del preset. Cambiar de preset = cambiar este archivo.

---

### 2. Tipos TypeScript

**`common.types.ts`**
- `EstadoCarga`: `'inicial' | 'cargando' | 'listo' | 'error'` (del ejemplar canónico)
- `ApiErrorResponse`: `{ message, error_code, errors }` (del contrato aprobado)
- `PaginatedResponse<T>`: `{ data, meta: { total, per_page, current_page, last_page } }`

**`api.types.ts`** — Transcripción literal de los JSON del contrato:
`Slot`, `Appointment`, `Schedule`, `RescheduleRequest`, `CancelledAppointment`.
No inventa campos.

**`auth.types.ts`** — `AuthUser`: `{ id, name, email, role, timezone }`. No asume
mecanismo de autenticación.

---

### 3. Cliente de API tipado

**`apiClient.ts`** — La regla estructural: ningún componente hace fetch.
- Exporta funciones tipadas: `getAvailability()`, `createAppointment()`, etc.
- Hoy importa de `apiClient.mock.ts` (controlado por `VITE_USE_MOCKS`).
- Mañana hace `fetch()` real. La interfaz pública no cambia.
- Cada función: `Promise<T>` o lanza error tipado.

**`apiClient.mock.ts`** — Datos copiados textualmente de los JSON de
`API_CONTRACTS.md`. Delay aleatorio 200–800ms para ejercitar estados de carga.

---

### 4. Zona horaria por usuario (sin dependencias)

**`timezone.ts`** — Tres funciones puras usando `Intl.DateTimeFormat`:

```typescript
formatInUserTimezone(isoUtc: string, timezone: string, options?: Intl.DateTimeFormatOptions): string
getUserTimezoneLabel(timezone: string): string     // "America/Tegucigalpa (UTC-6)"
detectBrowserTimezone(): string                    // Intl.DateTimeFormat().resolvedOptions().timeZone
```

El backend manda instantes UTC (`timestamptz`). El frontend los presenta en la zona
del usuario. No hay aritmética de fechas en el frontend.

---

### 5. i18n (español con claves preparadas)

**`plugin.ts`** — Plugin Vue que provee `t(key, params?)` vía `app.provide`.
Lookup + interpolación básica. Sin vue-i18n (50kB+, no necesario para idioma único).

**`es.ts`** — Diccionario plano con claves por feature:
- `common.*`, `directory.*`, `agenda.*`, `clinical.*`, `consultation.*`, `errors.*`
- Textos exactos de UI_PROTOTYPE.md §2 (tabla de 4 estados).

---

### 6. Composable genérico de estado

**`useAppState.ts`** — Copia la forma del ejemplar `useUsers.ts`.

**Delta contra el ejemplar:**
- Se generaliza el tipo: `<T>` en lugar de `User[]`.
- El fetcher se recibe como argumento.
- La llamada se despacha a través de `apiClient`, no `fetch()` directo.
- Todo lo demás (cancelación, AbortError, forma del error, `estaVacio`) es idéntico.

---

### 7. Componentes de estado (UI reutilizables)

Copian la forma del ejemplar `UserList.vue` para cada estado.

| Componente | Props | Función |
|---|---|---|
| `SpinnerLoader` | `lines`, `variant` ('card'│'list'│'form') | Skeleton animado, `aria-busy="true"` |
| `ErrorFallback` | `message`, `onRetry` | Panel de error con botón reintento, `role="alert"` |
| `EmptyState` | `message`, `actionLabel`, `onAction` | Mensaje descriptivo + botón de acción |

---

### 8. Layout shell

**`AppLayout.vue`** — Cintillo demo arriba + sidebar + contenido (`<slot />`) + footer.
No asume autenticación. Recibe prop `user?` opcional.

**`DemoBanner.vue`** — Cintillo del wireframe: "Modo de Demostración Activo."
con enlace `target="_blank" rel="noopener noreferrer"` (regla O2).

**`AppFooter.vue`** — Footer del wireframe: "Portafolio de Telemedicina © 2026 ·
https://rafaelmarin.dev" (regla O2).

---

### 9. Página stub

**`Dashboard.vue`** — Usa `AppLayout`, muestra los 3 componentes de estado y
la función `t()`. Sin lógica de negocio. Solo verificación de infraestructura.

---

## Fuera de alcance (declarado)

- Pantallas de login/registro (auth se reconstruye en la otra sesión)
- Feature de reserva de citas
- Componentes que asuman enum de estados concretos (el contrato cambió 2 veces)
- Cablear páginas a rutas de Inertia (las rutas son del backend)
- Framework de testing (configurarlo requiere editar package.json)

---

## Comandos de instalación (AMARILLO — ejecuta el humano)

```bash
# Desde frontend/
npm install @primevue/themes
```

> **Nota:** Luxon eliminado. No se necesitan más paquetes nuevos.

---

## Cambio en tsconfig.json (AMARILLO — ejecuta el humano)

Agregar al `compilerOptions`:
```json
{
  "noUncheckedIndexedAccess": true,
  "exactOptionalPropertyTypes": true
}
```

Requerido por regla C6 del protocolo.

---

## Verificación

1. `npx vue-tsc --noEmit` — sin errores de tipos.
2. `npx vite build` — bundle sin warnings.
3. Inspección visual de cada componente de estado.
