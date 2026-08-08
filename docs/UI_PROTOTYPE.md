# Prototipo y Wireframes de UI/UX

> **Estado:** Implementado. Las secciones 1-8 describen el diseño tal como se aprobó y en general coinciden con el código actual — ver §9 para las diferencias encontradas al auditar contra la implementación real.
> **Perfil de Marca:** PORTAFOLIO (Cintillo visible y firma de créditos)
> **UI Components:** PrimeVue

---

## 1. Árbol de Componentes Vue (Jerarquía Frontend)

La estructura de componentes está dividida de forma modular (Feature-First) para evitar el acoplamiento:

```
AppLayout (DemoBanner + AppSidebar + AppFooter)
 ├── components/
 │    ├── app/                         # Componentes de estructura global
 │    │    ├── AppSidebar.vue          (★ Sidebar 260px sticky · Nav por rol · Role switcher admin)
 │    │    └── DashboardHeader.vue     (★ Eyebrow + Título + Status pill + Action btn)
 │    ├── dashboard/                   # Componentes reutilizables del dashboard
 │    │    ├── StatCard.vue            (★ KPI card: icono + valor + trend)
 │    │    ├── DataTable.vue           (★ Tabla ligera: filtros pill + slots #cell-{key})
 │    │    ├── BarChart.vue            (★ Barras CSS puro — sin Chart.js)
 │    │    ├── AssistantWidget.vue     (★ Card oscuro "Asistente Salvia" + acciones)
 │    │    ├── AlertCard.vue           (★ Alerta urgente warning/critical con pulso)
 │    │    └── ActivityFeed.vue        (★ Timeline vertical de actividad reciente)
 │    ├── DemoBanner.vue               (★ Banner entorno demo/dev)
 │    └── AppFooter.vue                (★ Footer © Salvia)
 │
 ├── Pages/
 │    ├── Auth/
 │    │    ├── Login.vue
 │    │    └── Register.vue
 │    ├── Dashboard/                    # Dashboards por rol (★ Reescritos completos)
 │    │    ├── AdminDashboard.vue       (Verificaciones + KPIs + chart + widgets)
 │    │    ├── DoctorDashboard.vue      (Agenda del día + notas + chart + tareas)
 │    │    ├── PatientDashboard.vue     (Mis citas + recetas + chart + tratamiento)
 │    │    └── AgentDashboard.vue       (Citas pendientes + acciones rápidas)
 │    ├── Appointments/
 │    │    ├── BookingWizard.vue        (Reservas del Paciente / Conversión de Zona Horaria)
 │    │    └── AgendaManager.vue        (Configuración de Agenda del Médico)
 │    └── Clinical/
 │         ├── Directory.vue            (Directorio de Especialistas)
 │         └── ConsultationRoom.vue     (Pantalla Crítica del Médico)
 │              ├── PatientClinicalFile.vue (Panel Izquierdo - Longitudinal)
 │              │    ├── AllergyList.vue
 │              │    ├── ConditionList.vue
 │              │    ├── MedicationList.vue
 │              │    └── PastConsultationsList.vue
 │              └── InteractionArea.vue (Panel Derecho - En Vivo)
 │                   ├── ChatWindow.vue (Laravel Reverb Client)
 │                   └── SOAPNoteEditor.vue (Formulario de Notas SOAP)
 │                        ├── AmendmentList.vue (Histórico de enmiendas amarillas)
 │                        └── VerificationStatus.vue (Estampado del Hash SHA-256 + QR)
 │
 └── layouts/
      └── AppLayout.vue                (★ Integra Sidebar + provide activeViewRole)
```

> **★** = Componente creado o reescrito en el sprint Salvia (2026-08-05).
> Ver documentación detallada en [`docs/UI_COMPONENTS.md`](./UI_COMPONENTS.md).

---

## 2. Los Cuatro Estados en las Vistas Principales

A continuación se define de forma explícita el comportamiento y textos exactos para cada uno de los cuatro estados en las nueve vistas clave de la aplicación:

| Vista | Estado: Cargando | Estado: Vacío (Texto exacto + acción) | Estado: Error (Mensaje exacto + reintento) | Estado: Sin permisos (Qué visualiza) |
|---|---|---|---|---|
| **Directory** (Buscador Médicos) | Rejilla de 12 tarjetas en skeleton (imagen redonda y líneas de texto en pulso animado). | *"No encontramos médicos disponibles con las especialidades o filtros seleccionados."*<br>Acción: Botón `[ Limpiar Filtros ]`. | *"Error al recuperar el directorio de médicos."*<br>Acción: Botón `[ Reintentar Carga ]` (Axios reload). | Panel difuminado con mensaje central:<br>*"Acceso restringido. Inicie sesión para ver perfiles."* |
| **BookingWizard** (Agendamiento Paciente) | Grid mensual del calendario parpadeando junto con un listado lateral de horas vacío. | *"El médico no posee slots slots libres para la semana seleccionada."*<br>Acción: Botón `[ Ver Siguiente Semana ]`. | *"No se pudo calcular la disponibilidad de la agenda médica."*<br>Acción: Botón `[ Reintentar ]` (Carga slots). | Bloqueo total de la reserva:<br>*"Verifique su dirección de email para poder reservar citas."* |
| **AgendaManager** (Configuración Agenda) | Tabla de filas recurrentes semanales (Lunes-Domingo) con skeletons de texto. | *"Aún no has configurado tu agenda de atención. Los pacientes no podrán reservar citas contigo."*<br>Acción: Botón `[ Configurar Horario ]`. | *"Error al guardar o recuperar tus franjas horarias de agenda."*<br>Acción: Botón `[ Reintentar ]`. | Tarjeta de advertencia administrativa:<br>*"Tu perfil está PENDIENTE de aprobación. La agenda se activará al ser aprobado."* |
| **ConsultationRoom** (Médico en Consulta) | Globos de chat vacíos a la derecha y formulario SOAP inhabilitado con skeletons de entrada. | *"La consulta ha iniciado. El chat está disponible."*<br>(Aparece en el feed de conversación vacío). | *"Se perdió la conexión con el servidor de chat en vivo."*<br>Acción: Cintillo superior `[ Reconectar Chat ]`. | Pantalla en blanco con mensaje de error:<br>*"Acceso denegado. No eres el médico asignado a esta cita."* |
| **PatientClinicalFile** (Antecedentes Clínicos) | Tres listas laterales independientes con animaciones de pulso simulando líneas de texto. | **Alergias:** *"Sin alergias conocidas declaradas ni confirmadas."*<br>**Condiciones:** *"Sin condiciones de salud registradas."*<br>**Medicamentos:** *"Sin medicación habitual reportada."* | *"Falla parcial: Error al recuperar los antecedentes médicos del paciente."*<br>Acción: Botón `[ Reintentar Carga ]`. | Panel completamente bloqueado:<br>*"Acceso denegado. El personal administrativo no tiene acceso a la ficha clínica."* |
| **SOAPNoteEditor** (Formulario SOAP) | Campos de texto vacíos de PrimeVue parpadeando con skeletons de formulario. | *"La nota SOAP está vacía. Inicia la redacción del diagnóstico."*<br>(Mensaje en el indicador de autoguardado). | *"Error al autoguardar el borrador clínico. Cambios locales no sincronizados."*<br>Acción: Botón `[ Forzar Guardado ]`. | Formulario inhabilitado con candado:<br>*"No tienes permisos de escritura. Sólo el médico tratante puede editar."* |
| **ChatWindow** (Feed del Chat) | Globos de texto simulados alternando izquierda/derecha. | *"El canal de chat ha sido abierto. Salude al paciente para iniciar la consulta."* | *"Error al enviar el mensaje clínico. Intente nuevamente."*<br>Acción: Icono de reenvío rojo al lado del mensaje. | Mensaje de error centralizado:<br>*"Acceso denegado. No perteneces a esta consulta."* |
| **PastConsultationsList** (Historial Paciente) | Acordeón vertical de consultas anteriores colapsado en skeleton. | *"Este paciente no registra consultas previas en la plataforma."* | *"No pudimos recuperar el historial de consultas del paciente."*<br>Acción: Botón `[ Reintentar ]`. | Lista vacía con advertencia:<br>*"No tienes relación clínica previa con este paciente para ver su historial."* |
| **Historial Citas Paciente** (Mis Citas) | Listado vertical de tarjetas en skeleton con botones simulados. | *"Aún no has reservado ninguna cita médica en la plataforma."*<br>Acción: Botón `[ Buscar Especialista ]`. | *"Error al recuperar tu historial de citas."*<br>Acción: Botón `[ Reintentar ]`. | Pantalla en blanco con redirección:<br>*"Inicie sesión para visualizar su historial de citas."* |

---

## 3. Pantalla Crítica: El Médico en Consulta (ConsultationRoom.vue)

### Wireframe ASCII (Caso Listo / En Curso)

```
+--------------------------------------------------------------------------------------------------------------------+
| [Cintillo Demo: Modo de Demostración Activo. Enlace al Portafolio: https://rafaelmarin.dev ]                        |
+--------------------------------------------------------------------------------------------------------------------+
| Paciente: Juan Pérez (34 años) | ID: JP-8892 | Consulta Activa #50                     | Médico: Dr. Gregory House  |
+--------------------------------------------------------------------------------------------------------------------+
|                                                  |                                                                 |
| PANEL IZQUIERDO: FICHA CLÍNICA LONGITUDINAL      | PANEL DERECHO: INTERACCIÓN Y NOTAS                              |
| (Expediente Clínico del Paciente - Solo Lectura) | (Comunicación y SOAP - Autoguardado Borrador)                    |
|                                                  |                                                                 |
| +----------------------------------------------+ | +-------------------------------------------------------------+ |
| | ALERGIAS REGISTRADAS                         | | | CHAT DE LA CONSULTA EN TIEMPO REAL (Reverb)                 | |
| | * Penicilina (Medicamento | Severe)          | | | [15:02] Paciente: Buenas tardes Doctor.                     | |
| |   Declarado por: Paciente                    | | | [15:03] Dr. House: Hola Juan, veo tus alergias aquí.       | |
| |   [ Confirmar Alergia ] <--- Botón de Médico  | | | [15:03] Paciente: Sí, la penicilina casi me mata.           | |
| |                                              | | |                                                             | |
| | * Maní (Alimento | Moderate)                 | | | +---------------------------------------------------------+ | |
| |   Confirmado por: Dr. Wilson                 | | | | Escribe tu mensaje clínico aquí...                      | | |
| +----------------------------------------------+ | | +---------------------------------------------------------+ | |
| |                                              | | +-------------------------------------------------------------+ |
| +----------------------------------------------+ |                                                                 |
| | CONDICIONES DE SALUD                         | | +-------------------------------------------------------------+ |
| | * Hipertensión Primaria                      | | | NOTA CLÍNICA SOAP   [ ESTADO: BORRADOR - Autoguardado 15:05] | |
| |   Desde: 2024 (Activa)                       | | |                                                             | |
| | * Asma Leve                                  | | | [S] Subjetivo (Síntomas, motivo y relato del paciente):     | |
| |   Desde: 2021 (Resuelta)                     | | | [ Paciente refiere disnea leve y dolor de pecho opresivo... ] | |
| +----------------------------------------------+ | |                                                             | |
|                                                  | | | [O] Objetivo (Constantes vitales e inspección física):      | |
| +----------------------------------------------+ | | | [ PA: 120/80 mmHg | Temp: 36.5 °C | Peso: 81 kg             ] | |
| | MEDICACIÓN HABITUAL                          | | |                                                             | |
| | * Enalapril 10mg (1 vez al día)              | | | [A] Análisis (Juicio clínico y diagnóstico diferencial):     | |
| | * Salbutamol Inhalador (SOS)                 | | | [ Sospecha de angina estable, se solicita EKG de control... ] | |
| +----------------------------------------------+ | |                                                             | |
|                                                  | | | [P] Plan (Prescripción, exámenes y remisiones):             | |
| +----------------------------------------------+ | | | [ Reposo y remisión a Cardiología. Evitar penicilina.     ] | |
| | HISTORIAL DE CONSULTAS ANTERIORES            | | |                                                             | |
| | * 2026-07-15: Resfriado Común (Dr. Wilson)   | | | [x] Declaro haber obtenido Consentimiento Informado.        | |
| |   [ Ver Nota SOAP ]                          | | |                                                             | |
| | * 2026-06-10: Control de Presión (Dr. Wilson)| | | [ Guardar Borrador ]                 [ FIRMAR NOTA CLÍNICA ] | |
| +----------------------------------------------+ | | +-------------------------------------------------------------+ |
|                                                  | |                                                                 |
+--------------------------------------------------------------------------------------------------------------------+
| Portafolio de Telemedicina © 2026 · https://rafaelmarin.dev · Todos los derechos reservados                         |
+--------------------------------------------------------------------------------------------------------------------+
```

---

## 4. Decisiones de Renderizado en la Sala de Consulta

### Caso A: Comportamiento de Carga de la Pantalla
* El canal de chat en tiempo real (`ChatWindow.vue`) y el editor de notas SOAP (`SOAPNoteEditor.vue`) se inicializan y visualizan inmediatamente (con skeletons internos en el feed y formularios inhabilitados transitoriamente) para permitir la interacción y la comunicación por chat.
* El panel izquierdo del expediente del paciente (`PatientClinicalFile.vue`) carga de manera diferida, mostrando skeletons de pulso. Esto evita que la la latencia del historial clínico demore la apertura de la sala y el saludo inicial al paciente.

### Caso B: Falla Parcial (Ficha clínica inactiva, Chat activo)
Si falla la recuperación del historial longitudinal, el sistema adopta un modo degradado seguro:
1. **Comunicación Activa:** El chat en vivo y la edición SOAP permanecen 100% operativos.
2. **Advertencia Visual Clínicamente Restrictiva:** En la parte superior del chat se despliega un banner de alerta amarillo parpadeante:
   `¡ADVERTENCIA CLÍNICA!: Operando sin acceso a la Ficha Longitudinal. Indague alergias y contraindicaciones verbalmente antes de formular.`
3. **Bloqueo del Expediente:** El panel izquierdo muestra la tarjeta de error: *"Error al recuperar los antecedentes clínicos del paciente. [ Reintentar Carga ]"*.
4. **Firma Bajo Consentimiento:** El botón `[ FIRMAR NOTA CLÍNICA ]` se bloquea y sólo se habilita si el médico marca obligatoriamente un checkbox de validación analógica:
   `[x] Confirmo que he verificado verbalmente las alergias y medicamentos con el paciente debido a indisponibilidad del expediente digital.`
5. **Auditoría de Falla Parcial Inmutable:** Al completarse la firma con la validación verbal activa, el backend inyecta en la tabla `audit_logs` un registro inmutable con la marca exacta en su campo de metadatos:
   `new_values: { "partial_failure_verbal_confirmation": true }`
   Esto permite auditar legalmente de forma asíncrona qué decisiones clínicas se tomaron a ciegas por fallas de la infraestructura de base de datos.

---

## 5. Estado de la Nota SOAP: Borrador contra Firmada

### Comportamiento en Estado: Borrador
* **Edición Activa:** Los campos SOAP son editables (`<textarea>`) y el botón `[ FIRMAR NOTA CLÍNICA ]` está activo.
* **Autoguardado:** Un indicador en la barra superior muestra el estado de guardado en la base de datos de manera silenciosa (ej: *"Borrador autoguardado a las 15:05"*).
* **Confidencialidad:** El paciente **no puede ver** la nota en su portal mientras el estado sea `draft`.

### Comportamiento en Estado: Firmada (Cambios en Interfaz)
Una vez que el médico pulsa firmar, la nota pasa a ser inmutable:

1. **Campos Desactivados:** Los textareas SOAP pasan a modo de solo lectura (`readonly="true"`, `disabled="true"`). Se les aplica una clase CSS de atenuación visual (`bg-slate-50 border-dashed`).
2. **Desaparición de Acciones de Edición:** Se eliminan los botones `[ Guardar Borrador ]` y `[ FIRMAR NOTA CLÍNICA ]` de la pantalla del médico.
3. **Banner de Integridad y Firma:** Se renderiza un banner verde en la parte superior de la nota SOAP:
   ```
   +-----------------------------------------------------------------------------------------+
   |  Nota SOAP Firmada e Inmutable. Hash de Integridad SHA-256: 8a9f...b231                  |
   |  Firmado por: Dr. Gregory House | Fecha: 2026-07-31 15:06 (UTC) | IP: 181.115.32.40     |
   +-----------------------------------------------------------------------------------------+
   ```
4. **Acción de Enmienda y Descarga:** Aparecen dos botones nuevos en la barra de herramientas de la nota:
   * `[ Registrar Enmienda ]` (Abre un cuadro de diálogo modal para registrar enmiendas correctoras).
   * `[ Descargar Informe PDF ]` (Descarga el PDF generado asíncronamente en Horizon).

---

## 6. Visualización de Notas Clínicas con Enmiendas

Para evitar la alteración de registros clínicos cerrados, las enmiendas se visualizan de manera cronológica al pie de la nota original. **El texto original permanece intacto e inalterado en pantalla.**

### Wireframe de Nota Firmada con Enmienda Registrada

```
+---------------------------------------------------------------------------------------------+
| NOTA CLÍNICA SOAP   [ ESTADO: FIRMADA — Hash: 8a9f...b231 ]                                 |
|                                                                                             |
| [S] Subjetivo:                                                                              |
| Paciente refiere disnea leve y dolor de pecho opresivo...                                   |
|                                                                                             |
| [O] Objetivo:                                                                               |
| PA: 120/80 mmHg | Temp: 36.5 °C | Peso: 81 kg                                               |
|                                                                                             |
| [A] Análisis:                                                                               |
| Sospecha de angina estable, se solicita EKG de control...                                   |
|                                                                                             |
| [P] Plan:                                                                                   |
| Reposo y remisión a Cardiología. Evitar penicilina.                                         |
|                                                                                             |
| =========================================================================================== |
| ENMIENDAS REGISTRADAS                                                                       |
|                                                                                             |
| ┌─────────────────────────────────────────────────────────────────────────────────────────┐ |
| │ ENMIENDA #1 - Registrada el 2026-07-31 15:30 por Dr. Gregory House (IP: 181.115.32.40)   │ |
| │ Motivo de la enmienda: Fe de erratas en Plan de Medicación                              │ |
| ├─────────────────────────────────────────────────────────────────────────────────────────┤ |
| │ Se corrige error en redacción: El paciente debe tomar Aspirina 100mg diarios y reposar │ |
| │ por 48 horas en su domicilio antes de acudir al cardiólogo si persisten síntomas.       │ |
| └─────────────────────────────────────────────────────────────────────────────────────────┘ |
|                                                                                             |
| [ Registrar Nueva Enmienda ]                                       [ Descargar PDF con QR ] |
+---------------------------------------------------------------------------------------------+
```

---

## 7. Configuración de Agenda y Zona Horaria en Vue

Para eliminar la ambigüedad en citas transfronterizas, la agenda del paciente maneja el cálculo de husos horarios de forma explícita:

1. **Detección Automática de Zona Horaria:** Al ingresar a reservar, el frontend detecta la zona horaria del navegador (`Intl.DateTimeFormat().resolvedOptions().timeZone`) y la contrasta con la preferida del perfil del usuario.
2. **Cintillo Indicador de Hora Local:** En el calendario de selección de slots, se muestra de manera prominente el huso horario en uso:
   `Mostrando horarios en tu zona horaria local: America/Tegucigalpa (UTC-6) [ Cambiar ]`
3. **Tarjeta de Confirmación de Reserva:**
   ```
   Cita médica seleccionada con el Dr. Gregory House
   -------------------------------------------------
   Fecha: Lunes, 3 de Agosto de 2026
   Hora Paciente: 08:00 AM (America/Tegucigalpa)
   Hora Médico:   10:00 AM (America/New_York)
   ```
4. **Almacenamiento en BD:** La petición se envía al backend con la franja en formato ISO-8601 UTC (`2026-08-03T14:00:00Z`). El backend lo guarda como `timestamptz`, evitando desajustes y desfases.

---

## 8. Delimitación Visual y Regla de Convivencia: Chat Clínico (RF-14) vs. Asistente Conversacional (RF-23/RF-24)

Para resolver de manera definitiva la ambigüedad visual y arquitectónica entre la comunicación médico-paciente y la asistencia por modelo de lenguaje, se establece la siguiente delimitación de interfaz (Gate 2A):

1. **Ubicación del Chat Clínico (RF-14):**
   El chat de la consulta (`RF-14 Consulta por Chat en Tiempo Real`) es el **CONTENIDO PRINCIPAL** del panel de interacción clínica (`ConsultationRoom.vue` / `InteractionArea.vue`). No es una ventana flotante ni un drawer lateral. Representa el canal directo con el médico tratante respaldado por Laravel Reverb y guardado como registro médico.

2. **Diferenciación Visual y Paleta de Tokens (RF-23 / RF-24):**
   El asistente (Informativo en landing `RF-23` o Clínico en dashboard `RF-24`) **NO se ubica en el panel derecho** de la consulta. Utiliza un widget flotante/modal exclusivo en el dashboard del paciente con una identidad visual claramente diferenciada:
   - No utiliza los tokens primarios de marca (`--color-primary-*`), los cuales quedan reservados para las acciones clínicas y médicas de la interfaz.
   - Utiliza exclusivamente los tokens de la paleta neutral e informativa (`--color-info-*`), con etiquetas y cabecera explícita: `"Asistente Informativo IA - No es atención médica en vivo"`.

3. **Ausencia Absoluta durante Consulta Activa (Regla de Exclusión Total):**
   El asistente **NO EXISTE** (no se encuentra ni siquiera colapsado o en segundo plano) mientras el paciente mantenga una consulta clínica en curso (`consultations.status = 'in_progress'`). 
   - **Garantía Backend:** El endpoint del asistente clínico (`RF-24`) rechaza cualquier petición con código `409 Conflict` / `403 Forbidden` si detecta una consulta activa para el usuario autenticado.

4. **Separación Temporal (Asistente Antes, Médico Durante):**
   La utilidad del asistente se enmarca en la ventana previa a la cita: guiar al paciente en la recolección de motivos y antecedentes para el **RF-13 (Cuestionario Pre-consulta)** y el refresco de la ficha longitudinal. Al momento de abrirse la consulta en vivo (`status = 'in_progress'`), la IA se apaga por completo, garantizando que el canal sea 100% humano con el profesional de la salud.

   > Nota (auditoría posterior): `Clinical/PreConsultation.vue` quedó huérfano — ninguna ruta lo renderiza — y se eliminó del repositorio. El objetivo de "RF-13" descrito arriba sigue siendo válido como intención de diseño; la pantalla dedicada específicamente no llegó a conectarse.

---

## 9. Auditoría de los 4 Dashboards contra este documento

Los 4 dashboards por rol (`Pages/Dashboard/*.vue`) fueron revisados contra §1-§2 de este documento. Coinciden en estructura general (header + StatCards + tabla/gráfico + sidebar), pero se encontraron los siguientes puntos de deriva — cosas que se agregaron o cambiaron después de este prototipo y que vale la pena que quien lea el código sepa de antemano:

| Dashboard | Deriva encontrada |
|---|---|
| `AdminDashboard.vue` | Incluye una tabla de citas paginada y con buscador (`router.get('/admin', {search, status_filter, page})`) — no descrita en el prototipo original, agregada como funcionalidad real posterior. |
| `DoctorDashboard.vue` | El KPI de ingresos usa `formatUSD()` (`@/lib/currency`) — helper de moneda único, no existía en el prototipo original. `pending_tasks` cae a texto hardcodeado ("Completar nota clínica" / "Paciente de las 10:00") cuando el backend no manda `title`/`description` — parece un placeholder de una función de tareas no completamente conectada a datos reales todavía. |
| `PatientDashboard.vue` | La tarjeta "Cobertura" (`80 %`, "Copago 9 US$ por consulta") es **texto estático**, no viene de ningún prop — no confundir con un dato real del paciente. El botón "Ir a sala de espera" del `AlertCard` de cita-hoy usa `actionHref="#"` — enlace sin destino real todavía. |
| `AgentDashboard.vue` | Prop `unassigned_requests_count` se recibe del backend pero nunca se renderiza en el template — código muerto, probablemente de un widget planeado y no construido. La acción "Registrar paciente" del `AssistantWidget` apunta a `/patients/create`, ruta que **no existe** en `web.php` — enlace especulativo, confirmado con un comentario en el propio código (`// Assuming this route exists`). La tabla de citas recientes usaba slots `#header`/`#row`/`#empty` que `DataTable.vue` no reconoce (solo soporta `cell-{key}`) — las filas quedaban en blanco sin ningún error visible; corregido en esta misma auditoría. |

Ninguno de estos puntos es una regresión del prototipo — son extensiones reales hechas durante el desarrollo que este documento no había capturado todavía.
