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
| 1 | **Sin Luxon.** Zona horaria con `Intl.DateTimeFormat` nativo + opción `timeZone` | El backend manda `timestamptz` y calcula slots en el servidor. El frontend solo muestra un instante en la zona IANA del usuario. Una dependencia se justifica cuando hace algo que la plataforma no puede, no cuando es más cómoda |
| 2 | **Preset Aura** (PrimeVue 4) | Es el actual. Los componentes consumen TOKENS de diseño, nunca el preset directamente. Cambiar de preset cuesta un archivo, no cuarenta componentes |
| 3 | **Paleta azul profundo**, definida por el humano | Máxima separación con rojo (error) y verde (éxito). Info en neutral (NO azul) para no colisionar con marca. Tokens de estado separados de marca. Todos los pares verificados WCAG AA por script |
| 4 | **El plan vive en el repositorio** (`frontend/PLAN.md`) | Un plan que vive solo en la interfaz del agente no se puede auditar |
| 5 | **Contraste WCAG AA verificado por script, no manualmente** | "Verificar manualmente" es vigilancia, no barrera. El script lee tokens.css, calcula ratios de pares declarados y falla si alguno baja del umbral |
| 6 | **Pruebas con Vitest + @vue/test-utils** | Mínimo: useAppState pasa por los 4 estados, cancela con AbortError, expone estaVacio. El motivo de "no tocar package.json" no se sostiene porque el npm install del preset ya lo toca |
| 7 | **`suggestTimezoneForRegistration()`**, no `detectBrowserTimezone()` | La zona sale de `users.timezone`, no del navegador. El único uso legítimo de detectar la zona del navegador es prellenar el campo en el registro. El nombre hace de barrera |
| 8 | **`formatInUserTimezone()` calcula el offset para un instante dado** | El desplazamiento cambia con horario de verano. No es texto fijo |
| 9 | **No construir tipos Schedule ni RescheduleRequest** | El contrato de RF-08 se está decidiendo ahora y el de reprogramación cambió (el médico ya no reprograma, cancela). Construirlos hoy es garantizar retrabajo |
| 10 | **Cada tipo en `api.types.ts` lleva comentario con la sección del contrato** | Un tipo escrito a mano es una segunda fuente de verdad. El comentario hace la divergencia diffeable |
| 11 | **Cobertura de tokens en el script de contraste** | La barrera prueba que lo declarado pasa, no que lo nuevo esté declarado. Todo token `--color-*` debe aparecer en un par o en `DECORATIVE_ONLY` con motivo. Si no está en ninguno, el script falla |
| 12 | **Fuentes autohospedadas (`@fontsource`), cero peticiones a terceros** | (a) `@import` CSS es serial y bloquea renderizado. (b) App clínica con rigor máximo no le entrega IP/referrer a Google. (c) La demo se cae sin internet (wifi de conferencia) |
| 13 | **`prebuild` corre verificación de tipos + contraste** | Sin `prebuild`, la barrera se ejecuta solo si alguien escribe el comando. Con `prebuild`, `vite build` no puede terminar con un par en falla |
| 14 | **warning-600 NO sirve como borde de control** | warning-600 / warning-50 = 2.84 (< 3:1 WCAG 1.4.11). El borde/icono del panel warning usa warning-800 (6.62). warning-600 queda como fondo sólido de badge con texto oscuro encima |

---

## Paleta de colores (definida por el humano)

```
MARCA (azul profundo)              ESTADO: error (rojo)
--color-primary-50   #EFF6FF       --color-error-50    #FEF2F2
--color-primary-100  #DBEAFE       --color-error-100   #FEE2E2
--color-primary-500  #3B82F6       --color-error-600   #DC2626
--color-primary-600  #2563EB       --color-error-700   #B91C1C
--color-primary-700  #1D4ED8       ESTADO: éxito (verde)
--color-primary-900  #1E3A8A       --color-success-50  #F0FDF4
                                   --color-success-700 #15803D
SUPERFICIE                         --color-success-800 #166534
--color-surface-0    #FFFFFF       ESTADO: advertencia (ámbar)
--color-surface-50   #F8FAFC       --color-warning-50  #FEFCE8
--color-surface-100  #F1F5F9       --color-warning-600 #CA8A04
--color-surface-200  #E2E8F0       --color-warning-800 #854D0E
TEXTO                              ESTADO: información (neutral)
--color-text-strong  #0F172A       --color-info-bg     #F1F5F9
--color-text-muted   #475569       --color-info-text   #334155
--color-text-subtle  #64748B
BORDES Y FOCO
--color-border       #64748B
--color-focus-ring   #2563EB
```

Reglas derivadas del cálculo:
1. Warning NUNCA lleva texto blanco (blanco/warning-600 = 2.94, FALLA).
2. Borde de controles es neutral-500, no neutral-400 (neutral-400 = 2.56, FALLA 3:1).
3. Warning-600 NO sirve como borde/icono de control (warning-600/warning-50 = 2.84, FALLA 3:1).
   El borde/icono del panel warning usa warning-800.

---

## Árbol de archivos — estado final de esta entrega

```
frontend/
├── PLAN.md                          [THIS FILE]
├── Dockerfile                       [NEW] — imagen dev (node:22-alpine + pnpm + vite)
├── .dockerignore                    [NEW] — excluye node_modules/dist del contexto
├── vitest.config.ts                 [NEW] — extiende vite config, happy-dom
├── scripts/
│   └── check-contrast.mjs           [NEW] — barrera WCAG AA (21 pares + cobertura 27 tokens)
└── src/
    ├── main.ts                      [MODIFY] — PrimeVue + i18n + @fontsource
    ├── vite-env.d.ts                [NO CHANGE]
    ├── assets/
    │   └── styles/
    │       ├── tokens.css           [NEW] — variables CSS: colores, sombras, radios, espaciado
    │       ├── typography.css       [NEW] — escala tipográfica (fuentes autohospedadas)
    │       └── base.css             [NEW] — reset mínimo, importa tokens + typography
    ├── config/
    │   └── primevue.preset.ts       [NEW] — preset Aura con tokens propios
    ├── types/
    │   ├── api.types.ts             [NEW] — Slot, Appointment, CancelledAppointment (ref a contrato)
    │   ├── auth.types.ts            [NEW] — AuthUser con role y timezone IANA
    │   └── common.types.ts          [NEW] — EstadoCarga
    ├── lib/
    │   ├── apiClient.ts             [NEW] — único punto de red (hoy mocks, mañana fetch)
    │   ├── apiClient.mock.ts        [NEW] — datos de API_CONTRACTS.md + latencia simulada
    │   └── timezone.ts              [NEW] — wrappers Intl.DateTimeFormat (cero dependencias)
    ├── i18n/
    │   ├── plugin.ts                [NEW] — plugin Vue con t(key, params?)
    │   └── es.ts                    [NEW] — diccionario español (textos de UI_PROTOTYPE.md §2)
    ├── composables/
    │   ├── useAppState.ts           [NEW] — composable genérico de 4 estados
    │   └── useAppState.test.ts      [NEW] — 4 pruebas Vitest
    ├── components/
    │   └── ui/
    │       ├── SpinnerLoader.vue    [NEW] — skeleton con shimmer, aria-busy
    │       ├── ErrorFallback.vue    [NEW] — panel error + reintento, role=alert
    │       └── EmptyState.vue       [NEW] — icono + mensaje + acción
    ├── layouts/
    │   ├── AppLayout.vue            [NEW] — shell: DemoBanner + main + AppFooter
    │   ├── DemoBanner.vue           [NEW] — cintillo demo con enlace al portafolio
    │   └── AppFooter.vue            [NEW] — footer de créditos
    └── Pages/
        └── Dashboard.vue            [NEW] — stub que ejercita toda la infraestructura
```

---

## Detalle por módulo

### 1. Sistema de diseño ✅ COMPLETADO

**`tokens.css`** — Custom properties CSS en tres capas (marca, estado, superficie).
Reglas de contraste WCAG AA documentadas en el archivo.

**`typography.css`** — Inter (cuerpo) + Outfit (encabezados) autohospedadas
con `@fontsource`. Cero peticiones a terceros.

**`base.css`** — Reset mínimo. Importa tokens + typography. Único archivo CSS que
main.ts necesita importar (las fuentes van como imports JS de @fontsource).

**`primevue.preset.ts`** — Extiende Aura con los tokens propios. Valores de
primary-200/300/400/800/950 completados de la misma escala Tailwind blue.

**`check-contrast.mjs`** — Barrera automática con dos aserciones:
1. Ratios de contraste: 21 pares declarados (texto y no-texto).
2. Cobertura de tokens: todo `--color-*` en un par o en `DECORATIVE_ONLY` con motivo.
27 tokens: 25 en pares, 2 exentos (`surface-200` divisor decorativo,
`warning-600` fondo sólido de badge). Falla con exit code 1 si algo viola.

---

### 2. Tipos TypeScript ✅ COMPLETADO

**`common.types.ts`** — `EstadoCarga` del ejemplar canónico.

**`api.types.ts`** — `Slot`, `AvailabilityResponse`, `Appointment`,
`CreateAppointmentPayload`, `CancelledAppointment`. Cada tipo con comentario
referenciando la sección exacta de API_CONTRACTS.md. NO Schedule ni
RescheduleRequest (contratos en definición).

**`auth.types.ts`** — `AuthUser` con role (`UserRole`) y timezone IANA.

---

### 3. Cliente de API tipado ✅ COMPLETADO

**`apiClient.ts`** — Funciones tipadas: `getAvailability()`, `createAppointment()`,
`cancelAppointment()`. Hoy retorna mocks (`VITE_USE_MOCKS`). Mañana hace
fetch real. La interfaz pública no cambia.

**`apiClient.mock.ts`** — Datos copiados textualmente de API_CONTRACTS.md.
Delay aleatorio 200–800ms para ejercitar estados de carga.

---

### 4. Zona horaria por usuario ✅ COMPLETADO

**`timezone.ts`** — Tres funciones puras con `Intl.DateTimeFormat`:
- `formatInUserTimezone()`: offset calculado para el instante dado (respeta DST).
- `getUserTimezoneLabel()`: etiqueta legible con offset.
- `suggestTimezoneForRegistration()`: nombre como barrera, solo para registro.

---

### 5. i18n ✅ COMPLETADO

**`plugin.ts`** — Plugin Vue con `t(key, params?)` vía `app.provide`.
Sin vue-i18n (idioma único).

**`es.ts`** — Diccionario plano con textos exactos de UI_PROTOTYPE.md §2.

---

### 6. Composable genérico de estado ✅ COMPLETADO

**`useAppState.ts`** — Copia la forma del ejemplar `useUsers.ts`, generalizado
con `<T>` y fetcher como argumento.

**`useAppState.test.ts`** — 4 pruebas Vitest:
1. ✅ Pasa por los 4 estados: inicial → cargando → listo
2. ✅ Maneja error: inicial → cargando → error
3. ✅ Cancela con AbortError sin cambiar a estado error
4. ✅ Expone `estaVacio` correctamente (listo + items vacíos)

---

### 7. Componentes de estado ✅ COMPLETADO

| Componente | Props | Accesibilidad |
|---|---|---|
| `SpinnerLoader` | `lines`, `variant` (list/card/form) | `aria-busy="true"`, `role="status"` |
| `ErrorFallback` | `message`, `onRetry` | `role="alert"` |
| `EmptyState` | `message`, `actionLabel`, `onAction` | Semántico |

---

### 8. Layout shell ✅ COMPLETADO

**`AppLayout.vue`** — Shell de tres franjas: DemoBanner + main slot + AppFooter.
Preparado para sidebar lateral (cuando las rutas estén listas).

**`DemoBanner.vue`** — Cintillo warning-800/warning-50 con enlace al portafolio.

**`AppFooter.vue`** — Créditos con enlace al autor.

---

### 9. Página stub ✅ COMPLETADO

**`Dashboard.vue`** — Ejercita toda la infraestructura: layout, tokens,
i18n, useAppState con los 4 estados, fuentes Inter + Outfit.

---

## Fuera de alcance (declarado)

- Pantallas de login/registro (auth se reconstruye en la otra sesión)
- Feature de reserva de citas
- Componentes que asuman enum de estados concretos
- Cablear páginas a rutas de Inertia (las rutas son del backend)
- Tipos `Schedule` y `RescheduleRequest` (contratos en definición)

---

## Comandos de instalación (AMARILLO — ejecuta el humano)

```bash
# Desde frontend/
npm install @primevue/themes @fontsource/inter @fontsource/outfit
npm install -D vitest @vue/test-utils happy-dom
```

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

## Scripts en package.json (AMARILLO — ejecuta el humano)

Diff propuesto para `package.json`:
```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "verify": "vue-tsc --noEmit && node scripts/check-contrast.mjs",
    "prebuild": "npm run verify"
  }
}
```

Con `prebuild`, `vite build` NO puede terminar con un par en falla o un error
de tipos. Ahí deja de ser un script y pasa a ser una barrera.

---

## Verificación

1. `npm run verify` — tipos + contraste (21 pares + cobertura de 27 tokens).
2. `npm run build` — ejecuta verify (via prebuild) + bundle sin warnings.
3. `npx vitest run` — useAppState pasa las 4 pruebas mínimas (pendiente paso 2).
