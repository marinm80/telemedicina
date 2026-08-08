# Catálogo de Componentes UI — Plataforma Salvia

> Estado: Implementado
> Stack: Vue 3 + TypeScript + PrimeVue 4 (PrimeIcons)
> Paleta: Salvia (Dark Teal / Sage / Terracotta / Warm Cream)

## 1. Design System — Paleta Salvia

El sistema de diseño "Salvia" se compone de tres capas separadas: marca, estado y superficie. Los tokens de estado nunca coinciden con los tokens de marca para mantener un alto contraste y claridad visual (cumpliendo con WCAG AA).

### Colores (Tokens)

**Fondo de Página**
- `--color-page-bg`: `#FAF5EE` (Warm Cream)

**Marca (Teal Profundo)**
- `--color-primary-50`: `#E8F5F2`
- `--color-primary-100`: `#C8E6DE`
- `--color-primary-200`: `#A0D4C5`
- `--color-primary-300`: `#73BFAA`
- `--color-primary-400`: `#4FA997`
- `--color-primary-500`: `#2E9E6B` (Teal principal)
- `--color-primary-600`: `#0E5D52` (Deep teal — acciones, links)
- `--color-primary-700`: `#0B4D44` (Acciones hover)
- `--color-primary-800`: `#093E37`
- `--color-primary-900`: `#17302B` (Sidebar, botones primarios oscuros)

**Acento (Sage / Mint)**
- `--color-accent`: `#8FC9B3` (Items activos sidebar, highlights)
- `--color-accent-soft`: `#E3EFE9` (Badge info background)
- `--color-accent-muted`: `#CFE3DA` (Hover sutil)

**Alerta (Terracotta / Rust)**
- `--color-alert`: `#D9603E` (Badges urgentes, notificaciones)
- `--color-alert-dark`: `#B34A2A` (Texto sobre fondo claro)
- `--color-alert-bg`: `#FBEAE3` (Fondo de alertas)
- `--color-alert-soft`: `#F2D6C9` (Borde de alertas)

**Sidebar**
- `--color-sidebar-bg`: `#17302B`
- `--color-sidebar-text`: `#B0C4BC`
- `--color-sidebar-active`: `rgba(143, 201, 179, 0.16)`

**Estado: Error (Rojo)**
- `--color-error-50`: `#FEF2F2`
- `--color-error-100`: `#FEE2E2`
- `--color-error-600`: `#DC2626`
- `--color-error-700`: `#B91C1C`

**Estado: Éxito (Verde)**
- `--color-success-50`: `#E6F4EC`
- `--color-success-700`: `#2E9E6B`
- `--color-success-800`: `#166534`

**Estado: Advertencia (Ámbar)**
- `--color-warning-50`: `#FEFCE8`
- `--color-warning-600`: `#CA8A04`
- `--color-warning-800`: `#854D0E`

**Estado: Información (Neutral)**
- `--color-info-bg`: `#E3EFE9`
- `--color-info-text`: `#0E5D52`

**Clínica (Específicos)**
- `--color-clinical-accent`: `#0E5D52`
- `--color-clinical-danger`: `#D32F2F`
- `--color-clinical-danger-bg`: `#FFF5F5`
- `--color-clinical-warning`: `#854D0E`
- `--color-clinical-warning-bg`: `#FFF8E1`

**Superficie**
- `--color-surface-0`: `#FFFFFF`
- `--color-surface-50`: `#FAF9F5`
- `--color-surface-100`: `#F4F1EA`
- `--color-surface-200`: `#EDE4D8`

**Texto**
- `--color-text-strong`: `#17302B` (Dark green/charcoal)
- `--color-text-secondary`: `#3D5A52` (Verde oscuro medio)
- `--color-text-muted`: `#5F7A73` (Teal-gray para subtextos)
- `--color-text-subtle`: `#8FA39D` (Labels uppercase)
- `--color-text-label`: `#8FA39D` (Alias semántico)

**Bordes y Foco**
- `--color-border`: `#EDE4D8` (Warm border para cards y tablas)
- `--color-border-warm`: `#E8DFD3` (Variante ligeramente más oscura)
- `--color-focus-ring`: `#0E5D52` (Teal profundo para foco)

### Tipografía (Sizes, Weights)
- **Familias**: `sans-serif` (general), `var(--font-heading, sans-serif)` (Títulos)
- **Tamaños**: 
  - `--text-xs`: `12px` (Labels, descripciones pequeñas)
  - `--text-sm`: `13px / 14px` (Subtítulos, datos)
  - `--text-base`: `15px / 16px` (Texto normal, títulos de tarjeta)
  - `--text-lg`: `1.125rem` (18px)
  - `--text-xl`: `1.25rem` (20px)
  - `--text-2xl`: `28px`
  - `--text-3xl`: `36px` (KPIs)
- **Pesos**:
  - `--font-medium`: `500`
  - `--font-semibold`: `600`
  - `--font-bold`: `700`

### Escala de Espaciado (Base de 4px)
- `--spacing-0`: `0`
- `--spacing-1`: `0.25rem` (4px)
- `--spacing-2`: `0.5rem` (8px)
- `--spacing-3`: `0.75rem` (12px)
- `--spacing-4`: `1rem` (16px)
- `--spacing-5`: `1.25rem` (20px)
- `--spacing-6`: `1.5rem` (24px)
- `--spacing-8`: `2rem` (32px)
- `--spacing-10`: `2.5rem` (40px)
- `--spacing-12`: `3rem` (48px)
- `--spacing-16`: `4rem` (64px)

### Border Radii
- `--radius-sm`: `0.25rem` (4px)
- `--radius-md`: `0.5rem` (8px)
- `--radius-lg`: `0.75rem` (12px)
- `--radius-xl`: `1rem` (16px)
- `--radius-full`: `9999px`

### Sombras
- `--shadow-sm`: `0 1px 2px 0 rgb(0 0 0 / 0.05)`
- `--shadow-md`: `0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.06)`
- `--shadow-lg`: `0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.04)`

### Transiciones
- `--transition-fast`: `150ms ease`
- `--transition-normal`: `250ms ease`
- `--transition-slow`: `350ms ease`

### Z-Index y Breakpoints
- **Z-Index**: Sidebar (`z-index: 100`)
- **Breakpoints**: 
  - Mobile (max-width: `919px`): Sidebar se vuelve superior, toggle de menú.
  - Tablet/Desktop (min-width: `768px`, `920px`): Layout flex-row, Header row.
  - Sidebar Width: `--sidebar-width: 260px`


## 2. Layout System

### AppLayout (`AppLayout.vue`)
Envuelve a la aplicación entera proveyendo la estructura base (Sidebar + Main Content).
- **Provide/Inject**: Utiliza `provide('activeViewRole', activeViewRole)` para exponer a los componentes hijos el rol actualmente visualizado (útil para el administrador que cambia de vista).
- **Responsive Behavior**: En pantallas menores a `920px`, el layout cambia su `flex-direction` a `column`, y el padding principal pasa de `2rem` a `1rem`.
- **Integración**: Incluye `AppSidebar` y escucha su evento `@switch-role` para actualizar el `activeViewRole`. Todo el contenido de la página se inyecta vía el slot `<slot />`.

### AppSidebar (`AppSidebar.vue`)
Navegación principal de la plataforma.
- **Brand Section**: Muestra un badge con la letra inicial ("S"), el texto "Salvia" y un punto decorativo de color de acento. Incluye botón tipo hamburguesa solo visible en móvil.
- **Role Switcher (Solo Admin)**: Permite a usuarios administradores alternar la vista (Administrador 🛡, Médico 🩺, Paciente 💚). Emite el evento `switch-role`.
- **Navegación**: Menú dinámico basado en el rol actual. Cambia sus items e íconos automáticamente.
- **User Card**: Sección al fondo que incluye el avatar (iniciales del nombre y apellido), nombre completo, nombre del rol y el botón para cerrar sesión.
- **Responsive**: Se esconde bajo un toggle y pasa a ser *sticky* de ancho total en `max-width: 919px`.

### DashboardHeader (`DashboardHeader.vue`)
Cabecera reutilizable para las páginas del dashboard.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `eyebrow` | `string` | No | `undefined` | Texto de pre-título superior. |
| `title` | `string` | **Sí** | - | Título principal de la página. |
| `subtitle` | `string` | No | `undefined` | Texto descriptivo debajo del título. |
| `statusText` | `string` | No | `undefined` | Texto para el badge de estado. |
| `statusDot` | `boolean`| No | `true` | Muestra un punto verde junto al `statusText`. |
| `actionText` | `string` | No | `undefined` | Texto del botón de acción. |
| `actionHref` | `string` | No | `undefined` | URL del botón. Si se omite, renderiza un `<button>`. |

**Eventos Emitidos**:
- `action-click`: Si se usa como `<button>` (no hay `actionHref`), se emite al hacer click en él.

**Ejemplo de uso**:
```vue
<DashboardHeader 
  eyebrow="Resumen" 
  title="Panel de Control" 
  subtitle="Bienvenido a tu resumen diario."
  statusText="En línea"
  actionText="Nueva Cita"
  @action-click="openModal"
/>
```


## 3. Componentes Dashboard

### 3.1. StatCard (`StatCard.vue`)
**Descripción**: Tarjeta para mostrar métricas clave (KPIs) con un ícono, valor numérico y una tendencia opcional.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `icon` | `string` | **Sí** | - | Clase del ícono PrimeIcon (ej. `pi-users`). |
| `label` | `string` | **Sí** | - | Etiqueta superior de la estadística (ej. "Total Pacientes"). |
| `value` | `string \| number` | **Sí** | - | El valor principal a mostrar. |
| `trend` | `string` | No | `undefined` | Texto de la tendencia (ej. "+5%"). |
| `trendType` | `'positive' \| 'negative' \| 'neutral'` | No | `'neutral'` | Cambia el color y el ícono de la flecha de tendencia. |
| `iconBg` | `string` | No | `'var(--color-surface-100)'` | Color de fondo circular para el ícono. |

**Diseño**: Borde `var(--color-border-warm)`, fondo `var(--color-surface-0)`. Padding `20px 24px`. Label usa `var(--color-text-muted-teal)` uppercase `12px`. Valor principal usa `--text-3xl` (`36px`) y `font-weight: 700`.

**Ejemplo de uso**:
```vue
<StatCard 
  icon="pi-users" 
  label="Pacientes Activos" 
  :value="142" 
  trend="+12 este mes" 
  trendType="positive" 
/>
```

---

### 3.2. DataTable (`DataTable.vue`)
**Descripción**: Tabla de datos ligera con diseño limpio, soporte de filtros (pills) y slots dinámicos para formateo de celdas.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `columns` | `Column[]` | **Sí** | - | Definición de columnas `{ key, label, align }`. |
| `rows` | `Record<string, any>[]` | **Sí** | - | Array de objetos con los datos de las filas. |
| `filters` | `Filter[]` | No | `undefined` | Botones de filtro `{ key, label, count }`. |
| `activeFilter` | `string` | No | `undefined` | El filtro actualmente seleccionado. |
| `emptyIcon` | `string` | No | `'pi-inbox'` | Ícono a mostrar cuando no hay datos. |
| `emptyMessage` | `string` | No | `'No hay datos para mostrar'` | Mensaje para empty state. |

**Eventos**:
- `filter-change`: Emite la `key` del filtro cuando el usuario hace clic.

**Slots**:
- `cell-{columnKey}`: `{ row, value }`. Slot dinámico para personalizar la visualización de una celda específica.

**Diseño**: Cabeceras usan `--color-text-muted-teal` uppercase `12px`. Borde inferior `--color-border-warm`. El contenedor de tabla tiene borde `--color-border-warm` y `radius-lg`. Filas hacen hover en `var(--color-surface-50)`.

**Ejemplo de uso**:
```vue
<DataTable 
  :columns="[{ key: 'name', label: 'Nombre' }, { key: 'status', label: 'Estado' }]"
  :rows="[{ name: 'Juan', status: 'Pendiente' }]"
  :filters="[{ key: 'all', label: 'Todos' }]"
  activeFilter="all"
  @filter-change="handleFilter"
>
  <template #cell-status="{ value }">
    <span class="badge">{{ value }}</span>
  </template>
</DataTable>
```

---

### 3.3. BarChart (`BarChart.vue`)
**Descripción**: Gráfico de barras simple generado usando puramente CSS (flexbox) para rápida visualización sin dependencias de canvas.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `data` | `DataPoint[]` | **Sí** | - | Puntos de datos `{ label, value }`. |
| `title` | `string` | **Sí** | - | Título de la tarjeta. |
| `subtitle` | `string` | No | `undefined` | Subtítulo descriptivo. |
| `total` | `number \| string` | No | `undefined` | Valor grande a la derecha de la cabecera. |
| `color` | `string` | No | `'var(--color-primary-600)'` | Color de las barras CSS. |

**Diseño**: Contenedor usa `border-radius: var(--radius-lg, 12px)` y padding `24px`. Altura mínima del chart de `160px`. Total usa tipografía `--text-2xl` y font `--font-heading`. Las barras hacen transición de altura (`height var(--transition-normal, 0.3s) ease`).

**Ejemplo de uso**:
```vue
<BarChart 
  title="Consultas Semanales" 
  :total="124"
  :data="[
    { label: 'Lun', value: 20 },
    { label: 'Mar', value: 45 }
  ]" 
/>
```

---

### 3.4. AssistantWidget (`AssistantWidget.vue`)
**Descripción**: Tarjeta oscura (Dark Teal) que representa sugerencias o acciones proactivas de la IA / asistente del sistema.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `message` | `string` | **Sí** | - | Mensaje de texto a mostrar. |
| `actions` | `Action[]` | No | `undefined` | Array de acciones `{ text, href?, emit? }`. |

**Diseño**: Fondo sólido `var(--color-primary-600, #0E5D52)`, texto blanco. Ícono de destellos (`pi-sparkles`) con color `var(--color-accent, #8FC9B3)`. Los enlaces usan `font-weight: 600` y color acento.

**Ejemplo de uso**:
```vue
<AssistantWidget 
  message="Parece que tienes 3 resultados pendientes de revisar."
  :actions="[{ text: 'Revisar ahora', emit: 'review' }]"
  @review="openReviewModal"
/>
```

---

### 3.5. AlertCard (`AlertCard.vue`)
**Descripción**: Tarjeta horizontal para mostrar notificaciones urgentes, alertas críticas o advertencias, con anillo pulsante CSS.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `title` | `string` | **Sí** | - | Título de la alerta. |
| `subtitle` | `string` | **Sí** | - | Descripción de la alerta. |
| `severity` | `'warning' \| 'critical'` | No | `'warning'` | Define el estilo de borde (grosor) y animaciones. |
| `actionText` | `string` | No | `undefined` | Texto del botón secundario. |
| `actionHref` | `string` | No | `undefined` | Enlace del botón. |

**Eventos**:
- `action`: Se emite si se hace click en el botón sin `actionHref`.

**Diseño**: Fondo `var(--color-alert-light, #FBEAE3)` con texto rojo/terracota `var(--color-alert, #B34A2A)`. Cuando es `critical`, añade borde de 2px y una animación CSS `@keyframes pulse` en un elemento absoluto.

**Ejemplo de uso**:
```vue
<AlertCard 
  title="Actualización Requerida"
  subtitle="Por favor actualiza tus datos de facturación."
  severity="critical"
  actionText="Ir a Ajustes"
  actionHref="/ajustes/facturacion"
/>
```

---

### 3.6. ActivityFeed (`ActivityFeed.vue`)
**Descripción**: Un timeline visual para eventos recientes, limitado a mostrar los 5 eventos más recientes.

| Prop | Type | Required | Default | Descripción |
|------|------|----------|---------|-------------|
| `items` | `ActivityItem[]` | **Sí** | - | Elementos `{ text, time }`. |

**Diseño**: Utiliza puntos de línea de tiempo con `var(--color-accent, #8FC9B3)` de `8x8px` y una línea vertical de conexión. Card con borde regular, textos usan el `--text-sm` y tiempos `--text-xs` en color muted.

**Ejemplo de uso**:
```vue
<ActivityFeed 
  :items="[
    { text: 'Consulta de Dr. Pérez finalizada', time: 'Hace 10 min' },
    { text: 'Nueva reserva confirmada', time: 'Hace 2 horas' }
  ]"
/>
```

### 3.7. AdminPanel (`Pages/Admin/AdminPanel.vue`, 552 líneas)
**Descripción**: Página única de administración — reemplazó tres páginas separadas (`DoctorManager.vue`, `ScheduleManager.vue`, `SettingsManager.vue`, eliminadas del repo por quedar huérfanas). Ruta: `/admin/panel`. Tres tabs internos (`activeTab`: `doctors` | `users` | `config`), sin componentes hijos reutilizables — todo el markup vive en este archivo.

**Tab "Médicos"**:
- Filtro por estado (pills: Todos/Pendientes/Aprobados/Rechazados) con conteos en vivo.
- Formulario de alta de médico (`FormData` multipart, no JSON — por el archivo): nombre, apellido, email, contraseña, licencia, universidad, años de experiencia, tarifa (USD), especialidades (multi-select pills), estado inicial, y **campo de foto de perfil** con vista previa (`URL.createObjectURL`) y envío como parte del mismo `FormData`.
- Tarjetas de médico: foto (`photo_url`) o avatar de iniciales si no hay foto, especialidades, licencia, universidad, experiencia, badge de estado.
- "Editar" abre un modal (`Teleport`) que actualiza estado + universidad + años + tarifa + descripción vía un solo `PATCH /api/admin/doctors/{id}/status`.
- "Horarios" expande una grilla semanal (Lun–Dom) inline por médico, con alta/baja de franjas.

**Tab "Usuarios"**: búsqueda + filtro por rol, tabla de usuarios con acción de cambio de contraseña vía modal (`PATCH /api/admin/users/{id}/password`).

**Tab "Configuración"**: solo informativa — versión de stack, conteos en vivo, checklist de seguridad (RLS/CSRF/Bcrypt/GIST). Nada editable todavía.

---

## 4. Árbol de Componentes Actualizado

A continuación se ilustra la jerarquía y uso de los componentes en la estructura actual:

```text
src/
├── layouts/
│   └── AppLayout.vue
│       ├── AppSidebar.vue
│       └── [ slot para vistas ]
│
├── components/
│   ├── app/
│   │   ├── AppSidebar.vue
│   │   └── DashboardHeader.vue
│   │
│   └── dashboard/
│       ├── ActivityFeed.vue
│       ├── AlertCard.vue
│       ├── AssistantWidget.vue
│       ├── BarChart.vue
│       ├── DataTable.vue        (slots: solo cell-{columnKey} — no #header/#row/#empty)
│       └── StatCard.vue
│
├── Pages/Admin/
│   └── AdminPanel.vue           (único — ver §3.7. DoctorManager/ScheduleManager/SettingsManager
│                                  eliminados, quedaron huérfanos tras este merge)
```

## 5. Roles y Navegación

La plataforma provee experiencias adaptadas dependiendo del tipo de cuenta, expuestas en el `AppSidebar.vue`. Los 4 roles reales son `admin`, `doctor`, `patient`, `agent` (columna `role` resuelta vía join a `roles`/`user_roles`, no un enum fijo).

| Rol | Dashboard | Rutas propias principales |
|-----|-----------|---------------------------|
| **Administrador** | `AdminDashboard.vue` | `/admin/panel` (gestión unificada), `/admin` (tabla de citas paginada + buscador) |
| **Médico** | `DoctorDashboard.vue` | `/agenda`, `/doctor/horarios`, `/doctor/consulta/{id}` |
| **Paciente** | `PatientDashboard.vue` | `/paciente/directorio`, `/paciente/referidos`, `/booking/{doctorProfileId}` |
| **Agente** | `AgentDashboard.vue` | Comparte `/appointments` y `/directory` con los demás roles; sin páginas exclusivas propias |

**Role switcher (solo admin)**: el botón "Ver como" en `AppSidebar.vue` muestra 4 opciones (Administrador/Médico/Paciente/Agente). Es **puramente cosmético** — cambia qué ítems de menú se muestran vía `provide('activeViewRole', ...)`, pero no cambia el rol real de la sesión ni vuelve a pedir datos al backend con otro rol. No confundir con impersonación real de rol.
