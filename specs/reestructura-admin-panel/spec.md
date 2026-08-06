# Spec: Reestructuración Panel Admin + Reglas de Negocio Telemedicina

> Slug: `reestructura-admin-panel` · Modo de entrada: B · Generado: 2026-08-06

## Historia de usuario

**Como administrador**, quiero un panel de control unificado donde gestionar médicos (fichas, horarios, verificación) y configuraciones del sistema, para no navegar entre múltiples secciones dispersas.

**Como paciente**, quiero ver el listado de médicos con su información y agendar citas validadas, para asegurarme de que mi cita no se superponga con otra.

**Como médico**, quiero poder editar mis propios horarios de trabajo por día, para reflejar mi disponibilidad real.

## Alcance

### Incluye
- Reestructuración del sidebar: unificar Verificaciones + Médicos + Ajustes en un solo "Panel de Control"
- Panel admin con tabs: Médicos (fichas + horarios), Usuarios, Configuración
- Horarios flexibles por día (Lun-Dom) con horas diferentes por día
- Validación: paciente no puede tener 2 citas simultáneas
- Validación: GIST constraint ya impide 2 citas del mismo doctor simultáneas ✓
- Regla: mínimo 30 min entre citas consecutivas del mismo paciente
- Badge dinámico de verificaciones pendientes (conteo real desde DB)
- Vista paciente: Directorio de médicos (read-only) + Agendar Cita (asistente)
- Vista médico: editar SUS propios horarios

### NO incluye (out of scope)
- Videollamada / teleconsulta en vivo
- Recetas médicas / prescripciones
- Historial clínico detallado
- Facturación real / pasarela de pago funcional
- Notificaciones push / email
- Chat médico-paciente en tiempo real
- Multi-idioma

## Actores

| Rol        | Descripción                                    | Permisos relevantes                                          |
|------------|------------------------------------------------|--------------------------------------------------------------|
| Admin      | Gestiona toda la plataforma                    | CRUD médicos, CRUD horarios de cualquier médico, cambiar contraseñas, cambiar roles, ver/editar config |
| Médico     | Profesional de salud registrado                | Ver/editar SUS horarios, ver SUS citas, ver SUS pacientes     |
| Paciente   | Usuario final que busca atención               | Ver directorio médicos (read-only), agendar citas, ver MIS citas |

## Precondiciones
- El admin debe estar autenticado con rol `admin`
- Los médicos deben tener perfil aprobado (`status = 'approved'`) para aparecer en el directorio
- La base de datos tiene la extensión `btree_gist` y constraint `appointments_sin_solapamiento`

## Postcondiciones
- El sidebar admin muestra máximo 3 items: Resumen, Panel de Control, Agendar Cita
- El sidebar paciente muestra: Directorio Médicos, Mis Citas, Agendar Cita
- Los horarios pueden variar por día de la semana (incluidos Sáb/Dom)
- No existen citas solapadas para un mismo doctor (constraint GIST)
- No existen citas solapadas para un mismo paciente (validación aplicativa)
- Badge de verificaciones muestra conteo real desde DB

## Flujo principal (caso feliz)

### Admin — Gestión de médicos
1. Admin navega a "Panel de Control" en el sidebar
2. Ve tabs: **Médicos** | **Usuarios** | **Configuración**
3. En tab Médicos: lista de todos los médicos con estado, especialidades
4. Click "Editar Ficha" → modal con info + estado (aprobar/rechazar/pendiente)
5. Click en un médico → se expande sección de horarios por día
6. Admin puede asignar horarios individuales por día (Lun: 08:00-17:00, Mar: 10:00-18:00, Sáb: 09:00-13:00)
7. Click "Nuevo Médico" → formulario completo de registro

### Paciente — Ver médicos y agendar
1. Paciente navega a "Directorio Médicos" en el sidebar
2. Ve cards de médicos aprobados con: nombre, especialidad, universidad, experiencia
3. Click "Agendar Cita" → se abre FloatingAssistant con flujo guiado
4. Selecciona especialidad → doctor → fecha → horario disponible → confirma
5. Sistema valida que no tiene otra cita en el mismo horario
6. Cita creada → resumen completo mostrado

### Médico — Gestionar sus horarios
1. Médico navega a "Mis Horarios" en sidebar
2. Ve grilla semanal con sus horarios actuales (Lun-Dom)
3. Puede agregar/eliminar franjas por día
4. Cambios se reflejan inmediatamente en la disponibilidad

## Flujos alternativos

### Alt-1: Paciente intenta agendar en horario ya ocupado por él mismo
1. Paciente selecciona horario que ya tiene cita con otro doctor
2. Sistema rechaza: "Ya tienes una cita agendada en ese horario (Dr. X a las HH:MM)"
3. Paciente selecciona otro horario

### Alt-2: Slot ocupado por otro paciente (race condition)
1. Paciente confirma cita pero otro paciente la tomó antes
2. GIST constraint rechaza → 409 Conflict
3. Agente muestra: "Ese horario acaba de ser ocupado. Busquemos otro."

### Alt-3: Admin modifica horario con citas existentes
1. Admin reduce horario de un médico que tiene citas agendadas fuera del nuevo rango
2. Sistema advierte: "Existen N citas fuera del nuevo horario. ¿Desea continuar?"
3. Las citas existentes NO se cancelan automáticamente (solo se advierte)

## Reglas de negocio

- **RB-1:** Un paciente NO puede tener dos citas con status != 'cancelled' que se solapen en horario (validación aplicativa pre-insert).
- **RB-2:** Un doctor NO puede tener dos citas con status != 'cancelled' que se solapen en horario (GIST constraint `appointments_sin_solapamiento` ya existe).
- **RB-3:** Cada cita dura exactamente 30 minutos (slot_duration del schedule).
- **RB-4:** Los horarios de trabajo pueden variar por día de la semana (day_of_week 0-6).
- **RB-5:** Sábado (6) y Domingo (0) son días válidos de trabajo si el médico/admin lo configura.
- **RB-6:** Un médico solo aparece en el directorio si su perfil tiene `status = 'approved'`.
- **RB-7:** El badge de verificaciones pendientes es dinámico: `COUNT(*) FROM doctor_profiles WHERE status = 'pending'`.
- **RB-8:** Solo admin puede crear médicos y cambiar su estado. El médico solo edita SUS horarios.

## Escenarios BDD (Gherkin)

### Escenario 1: Paciente no puede agendar dos citas superpuestas
```gherkin
Given el paciente "Juan Pérez" tiene una cita con Dra. García el 2026-08-20 a las 10:00-10:30
When intenta agendar una cita con Dr. Mendoza el 2026-08-20 a las 10:00-10:30
Then el sistema rechaza con mensaje "Ya tienes una cita agendada en ese horario"
And permanece en el paso de selección de horario
```

### Escenario 2: Admin configura horarios por día
```gherkin
Given el admin está en el Panel de Control, tab Médicos
When selecciona a Dra. García y configura Lunes 08:00-17:00, Sábado 09:00-13:00
Then el schedule de Dra. García muestra Lunes [08:00,17:00) y Sábado [09:00,13:00)
And la disponibilidad del sábado aparece para los pacientes
```

### Escenario 3: Badge dinámico de verificaciones
```gherkin
Given existen 2 médicos con status "pending" en doctor_profiles
When el admin carga el sidebar
Then el badge del tab "Médicos" muestra "2"
```

### Escenario 4: Médico edita SUS horarios
```gherkin
Given Dr. Mendoza está autenticado con rol "doctor"
When navega a "Mis Horarios" y cambia Viernes de 08:00-17:00 a 10:00-15:00
Then el schedule de Viernes se actualiza a [10:00,15:00)
And los pacientes ven la nueva disponibilidad
```

## Criterios de aceptación

- [ ] Sidebar admin: solo "Resumen", "Panel de Control", "Agendar Cita"
- [ ] Panel de Control admin tiene 3 tabs: Médicos, Usuarios, Configuración
- [ ] Tab Médicos incluye listado + editar ficha + gestión de horarios por día
- [ ] Horarios permiten Sáb/Dom y horas diferentes por día
- [ ] Badge muestra conteo real de pending doctors
- [ ] Sidebar paciente: "Directorio Médicos", "Mis Citas", "Agendar Cita"
- [ ] Directorio médicos es read-only para pacientes
- [ ] Validación aplicativa: paciente no puede agendar 2 citas al mismo tiempo
- [ ] Sidebar médico: "Mis Horarios", "Mis Citas"
- [ ] Médico puede editar SUS horarios por día desde su panel

## Métricas de éxito
- 0 errores de "Call to member function on null" en el flujo de citas
- 100% de citas cumplen reglas RB-1 y RB-2 (sin solapamiento)
- Admin puede crear médico + asignar horarios en < 2 minutos
- Paciente puede agendar cita en < 1 minuto

## Preguntas abiertas (TBD)
- Ninguna — todas las asunciones fueron aceptadas

---

## Cambios Implementados v0.4.0 (2026-08-06)

### RF-CONSULTA: Flujo de Consulta Médica
- Médico puede iniciar consulta desde 'Mis Citas' con botón 'Atender'
- Formulario con 10 secciones: motivo, síntomas, historial, medicinas, examen, lab, diagnóstico, plan, seguimiento
- Guardar borrador sin cerrar + Firmar y Archivar (cambia estado a completed)
- Cita de seguimiento automática al archivar (configurable en semanas/meses)

### RF-RECETAS: Módulo de Recetas Médicas
- Tabla `prescriptions` con JSONB para medicamentos
- CRUD con permisos: doctor edita propias, admin edita todas, paciente solo lectura
- Integrado con consultas vía `consultation_id`

### RF-HIST: Historial de Citas
- 4 tabs: Todas, Próximas, Completadas, Canceladas (con contadores)
- Filtro por paciente individual (admin/médico)
- Info de cancelación: motivo + quién canceló

### RF-DASH-ADMIN: Dashboard Admin Renovado
- Tabla paginada de todas las citas (10/página)
- Buscador por médico o paciente
- Filtro por estado
- Métricas reales: cancelaciones, completadas, tasa
- Removidos datos fake (ingresos, asistente)

### RF-SOLAPAMIENTO: Validación Anti-solapamiento Paciente
- Validación `franja && tstzrange()` en BookAppointmentAction
- Error 409 con mensaje descriptivo

### BUG-FIXES
- Citas duplicadas por LEFT JOIN multi-specialty → string_agg()
- Badge estático '3' → datos dinámicos reales
- Datos mock en Mis Citas → props reales de Inertia
- Error 403 por getRoleAttribute → pgsql_admin + cache

