# PRD — Plataforma de Telemedicina (Fase 1)

> **Versión:** 2.0
> **Fecha:** 2026-07-31
> **Autor:** Rafael Marín (Perfil: PORTAFOLIO)
> **Estado:** En revisión
> **Combo de stack:** Laravel 11 + Vue 3 (Inertia) + Redis + Reverb + Horizon + Stripe
> **Motor de base de datos:** PostgreSQL 16 — propio / elegido
> **Perfil de marca:** PORTAFOLIO
> **Nivel de rigor:** MÁXIMO (Datos clínicos, Dinero real en Stripe y Exclusividad de agenda)
> **Protocolo del repositorio:** `PROTOCOLO.md` — sello v3.2 copiado el 2026-07-31
> **Ejemplares:** `EJEMPLARES.md` + carpeta `ejemplares/`
> **Plano arquitectónico:** `MAPA_ARQUITECTURA.md` — [PENDIENTE — GATE 1.5]
> **Desviaciones del protocolo:** ninguna

---

## 1. Descripción y problema

**Problema que resuelve:**
En los sistemas de salud convencionales y plataformas de telemedicina MVP típicas, existe una desconexión crítica entre tres pilares fundamentales que deben tratarse con absoluta irreversibilidad: la veracidad y persistencia de los **datos clínicos** de los pacientes, la integridad del **dinero en transacciones de pago**, y la **exclusividad de la agenda médica** para evitar solapamientos. 
Esta plataforma resuelve la centralización geográfica de los especialistas médicos y la ineficiencia logística mediante una arquitectura robusta de telemedicina web, permitiendo consultas asíncronas y síncronas por chat persistente y en tiempo real, garantizando la inmutabilidad de los informes, la seguridad en el cobro y la infalibilidad técnica de las citas agendadas directamente a nivel de base de datos.

**Diferenciadores clave:**
- **Rigor en la agenda médica:** Evita colisiones de citas de forma determinista usando exclusión de rangos nativos (`EXCLUDE USING gist`) en PostgreSQL, impidiendo solapamientos horarios incluso ante peticiones concurrentes de nivel de milisegundos.
- **Canal clínico persistente:** Chatear en tiempo real mediante Laravel Reverb. La conversación se trata como dato clínico protegido y persistido, en lugar de depender de servicios efímeros de videollamada externos.
- **Inmutabilidad y verificación por hash:** Las notas SOAP firmadas por el médico generan un hash de integridad SHA-256 del contenido que se estampa en un PDF inmutable y en un QR para verificación de autenticidad. Las enmiendas no sobreescriben la nota clínica.
- **Estricta separación de privilegios:** El personal administrativo (agente) puede gestionar agendas y datos de contacto, pero tiene un bloqueo absoluto de acceso a los datos de la ficha clínica o conversaciones de chat.

---

## 2. Alcance

### 2.1 Incluido
1. **Autenticación y Registro:** Registro público de pacientes con verificación de email. Solicitud de registro de médicos con validación de credenciales profesionales sujeta a aprobación administrativa.
2. **Invitación por Email (Agentes):** Capacidad del personal administrativo para pre-registrar y enviar invitaciones a pacientes especificando departamento y cargo (si aplica).
3. **Roles y Permisos Dinámicos:** Gestión de accesos basada en base de datos. Soporta asignación de permisos temporales individuales con fecha de expiración configurada a nivel de tabla.
4. **Perfiles de Paciente y Médico:** Administración de perfiles. Especialidades médicas y selección estricta de zona horaria por usuario para corregir desfases de agenda.
5. **Ficha Clínica Longitudinal:** Registro histórico acumulativo del paciente que incluye alergias estructuradas (sustancia, severidad, reacción, declarante y confirmante), condiciones de salud y medicación habitual.
6. **Directorio Médico Público:** Buscador paginado de especialistas médicos con filtros por especialidad y disponibilidad para citas.
7. **Agenda y Disponibilidad:** Configuración de horarios semanales recurrentes por médico y bloqueos de fechas específicas (excepciones). Cálculo automatizado de slots libres.
8. **Reserva de Citas sin Solapamiento:** Motor transaccional de reservas que bloquea el slot en el instante en que se selecciona.
9. **Reserva en Nombre del Paciente:** Habilidad del agente administrativo para agendar citas en nombre del paciente.
10. **Reprogramación Controlada:** Solicitud de cambio de horario iniciada por paciente o médico que requiere aprobación de la contraparte, ejecutada bajo una sola transacción ACID.
11. **Pagos con Stripe:** Procesamiento de cobros integrando Stripe Checkout en modo Sandbox con validaciones de firma de webhook y procesamiento idempotente ante reintentos.
12. **Cuestionario Pre-consulta:** Formulario dinámico con motivo y síntomas completado por el paciente antes de habilitarse la consulta.
13. **Consulta por Chat en Tiempo Real:** Chat persistente bidireccional médico-paciente gestionado mediante Laravel Reverb y tratado como dato clínico.
14. **Nota Clínica SOAP:** Redacción de informe médico en estructura SOAP (Subjetivo, Objetivo, Análisis, Plan) con estados: Borrador (con autoguardado) y Firmada.
15. **Firma Electrónica Simple:** Stamping de firma mediante hash SHA-256 del contenido de la consulta, metadatos del autor, IP y User Agent al momento de la firma. Inmutabilidad absoluta tras firmar.
16. **Enmiendas Clínicas:** Adición de anotaciones correctivas o aclaratorias a notas clínicas ya firmadas. Se prohíbe la edición directa del original.
17. **Materialización de PDF (Horizon):** Generación asíncrona del PDF clínico usando DomPDF tras el commit de firma, encolado en Horizon.
18. **Acuse de Recibo de Paciente:** Firma opcional de acuse de recibo por parte del paciente que regenera el PDF agregando una hoja de constancia.
19. **Verificación Pública por QR:** Impresión de un QR al pie del PDF clínico que apunta a una URL pública de verificación del hash de integridad contra la base de datos.
20. **Documentos Adjuntos:** Carga y descarga de archivos adjuntos (recetas, exámenes) con URLs firmadas temporalmente en S3 y acceso restringido según rol de consulta.
21. **Notificaciones Multi-canal:** Envío de correos electrónicos transaccionales y notificaciones in-app.
22. **Auditoría Inmutable:** Registro de logs a nivel de base de datos de todas las modificaciones críticas de tablas (quién, cuándo, valor anterior y nuevo).

### 2.2 Fuera de alcance
1. **Videollamadas directas:** Desestimado el uso de Daily.co o WebRTC en la Fase 1 debido a dependencias de terceros en el camino crítico. Toda consulta se procesa mediante chat persistente.
2. **Módulo de Inventario de Farmacia:** No se implementará control de lotes, almacén, compras a proveedores ni inventario de medicamentos.
3. **Recetas con Firma Digital Certificada (PKI, X.509):** Se limita exclusivamente a la firma electrónica simple con hash e integridad en base de datos.
4. **Multi-moneda:** Operación financiera restringida a Dólares Estadounidenses (USD) para evitar complejidades de conversión y descuadre contable.
5. **Paneles y Flujos para Enfermería o Asistentes:** Los roles y permisos existen en la base de datos para futuras implementaciones, pero las interfaces de usuario no se desarrollarán en la Fase 1.
6. **Reprogramación Libre:** No se permite que el paciente cambie la cita unilateralmente sin la aprobación formal del médico tratante.
7. **Soporte Multi-idioma en Caliente:** La interfaz opera únicamente en Español, aunque la arquitectura técnica debe utilizar el helper `__()` de Laravel para facilitar traducciones futuras.

---

## 3. Actores y permisos

| Actor | Descripción | Puede | NO puede |
|---|---|---|---|
| **Paciente** | Usuario final que busca atención de salud. | Registrarse, completar su perfil y su ficha clínica longitudinal (declarar alergias/condiciones), buscar médicos, reservar y pagar citas, chatear en consultas activas, firmar acuse de notas clínicas, descargar sus informes PDF. | Ver fichas clínicas de otros pacientes, modificar notas SOAP de médicos, ver notas clínicas en estado borrador, reservar citas sin realizar el pago previo. |
| **Médico** | Profesional sanitario autorizado y aprobado por el administrador. | Configurar agenda semanal y bloqueos, aceptar solicitudes de reprogramación, ver fichas clínicas de pacientes **con quienes tiene o ha tenido consultas registradas**, chatear en la consulta, redactar y firmar notas clínicas SOAP, adjuntar documentos, emitir enmiendas. | Ver fichas clínicas de pacientes sin consulta previa o agendada, editar una nota clínica firmada, eliminar el registro de una consulta o chat, ver ingresos de otros médicos de la plataforma. |
| **Agente / Recepcionista** | Personal administrativo de la clínica u hospital. | Pre-registrar pacientes y enviar invitaciones, agendar citas en nombre de los pacientes, reprogramar y cancelar citas en la agenda general, ver información de contacto básica. | **Ver la ficha clínica longitudinal, el chat de consultas, los documentos adjuntos de la consulta o las notas SOAP bajo ninguna circunstancia (Acceso 403 Forbidden).** |
| **Administrador** | Operador central de la plataforma. | Aprobar o rechazar médicos ingresando un motivo de rechazo, gestionar el catálogo de especialidades, dar de alta y revocar permisos (incluyendo temporales), auditar logs del sistema. | Desactivar su propia cuenta de administrador, editar notas clínicas, ver chats de pacientes, auto-otorgarse permisos sin registro de auditoría. |

> **Nota de gobernanza de base de datos:** Los roles **Enfermera**, **Asistente de Farmacia** y **Administrador de Farmacia** no dispondrán de vistas de interfaz desarrolladas en esta fase, pero sus permisos de API estarán modelados en la tabla de permisos e inhabilitados a nivel de rutas mediante middlewares de autorización.

---

## 4. Requerimientos funcionales

| ID | Nombre | Descripción | Prioridad | Rigor | Estado |
|---|---|---|---|---|---|
| RF-01 | Registro y Verificación de Paciente | Un paciente se registra de manera autónoma y se le envía un enlace de verificación de email por 24 horas. | Alta | Alta | PENDIENTE |
| RF-02 | Registro de Médico y Aprobación | Un médico solicita registrarse en la plataforma y permanece bloqueado en estado `pending` hasta que un administrador lo aprueba. | Alta | Alta | PENDIENTE |
| RF-03 | Invitación de Pacientes por Agente | El personal administrativo registra e invita a un paciente por email, asociando datos de departamento o cargo. | Alta | Media | PENDIENTE |
| RF-04 | Roles y Permisos Temporales | Asignación de roles y permisos granulares a usuarios con fecha de expiración en base de datos. | Alta | Alta | PENDIENTE |
| RF-05 | Gestión de Perfiles y Zonas Horarias | Configuración de especialidades por médico y almacenamiento de zona horaria por usuario. | Alta | Alta | PENDIENTE |
| RF-06 | Ficha Clínica Longitudinal | Registro acumulativo de alergias (estructuradas), condiciones de salud y medicación. | Alta | MÁXIMO | PENDIENTE |
| RF-07 | Directorio y Buscador de Médicos | Buscador de médicos aprobados y activos con filtrado por especialidad y slots. | Alta | Media | PENDIENTE |
| RF-08 | Configuración de Agenda y Bloqueos | Definición de horarios recurrentes semanales y bloqueos de fechas por médico. | Alta | MÁXIMO | PARCIAL |
| RF-09 | Reserva de Citas sin Solapamiento | Reserva e inserción atómica de cita garantizando la exclusión de franja horaria coincidente. | Alta | MÁXIMO | CONSTRUIDO |
| RF-10 | Reserva en Nombre del Paciente | Agente reserva un slot libre para un paciente, validando que el agente no acceda a datos clínicos. | Alta | MÁXIMO | PARCIAL |
| RF-11 | Solicitud y Aprobación de Reprogramación | Flujo de reprogramación con confirmación del médico que reasigna el slot en una sola transacción. | Alta | MÁXIMO | PENDIENTE |
| RF-12 | Pago con Stripe e Idempotencia | Cobro del slot con redirección y webhook de Stripe con protección contra reintentos. | Alta | MÁXIMO | PENDIENTE |
| RF-13 | Cuestionario Pre-consulta | Captura del motivo e historial de síntomas del paciente previo a iniciar el chat. | Alta | Media | PENDIENTE |
| RF-14 | Consulta por Chat en Tiempo Real | Canal persistente médico-paciente con WebSocket (Reverb) tratado como dato clínico. | Alta | MÁXIMO | PENDIENTE |
| RF-15 | Nota SOAP (Borrador a Firmada) | Creación, autoguardado de borrador y cambio de estado a firma inmutable por el médico. | Alta | MÁXIMO | PENDIENTE |
| RF-16 | Firma Electrónica e Inmutabilidad | Hash SHA-256 de integridad y metadatos de firma que bloquean la nota para siempre. | Alta | MÁXIMO | PENDIENTE |
| RF-17 | Enmiendas Clínicas | Registro de anotaciones modificatorias anexadas a una nota firmada sin alterar el original. | Alta | MÁXIMO | PENDIENTE |
| RF-18 | Generación de PDF y QR Clínico | Encolado en Horizon para materializar el PDF con DomPDF e incluir hash y código QR de validación. | Alta | MÁXIMO | PENDIENTE |
| RF-19 | Acuse de Recibo de Paciente | Paciente firma conformidad del informe regenerando el PDF con hoja de constancia. | Media | Media | PENDIENTE |
| RF-20 | Auditoría Inmutable de BD | Registro en base de datos de toda modificación en tablas clínicas, financieras y de agenda. | Alta | MÁXIMO | PARCIAL |
| RF-23 | Asistente Informativo (Landing) | Asistente conversacional de lectura pura en landing pública (sin sesión, cero escrituras en BD). | Media | MÁXIMO | PENDIENTE |
| RF-24 | Asistente Clínico (Dashboard) | Asistente conversacional en el portal del paciente (con sesión paciente, hereda RLS, bloqueado de notas SOAP). | Alta | MÁXIMO | PENDIENTE |
| RF-25 | Cancelación de Citas y Reembolsos | Cancelación por médico (100% reembolso siempre) y paciente (reembolso condicional a 24h previas). | Alta | MÁXIMO | PENDIENTE |

---

### Criterio de aceptación — RF-01 (Registro y Verificación de Paciente)
```gherkin
Escenario: Registro exitoso de paciente autónomo
  Dado que un visitante no autenticado envía una petición POST a "/api/auth/register" con:
    | name                  | "Juan"                  |
    | last_name             | "Pérez"                 |
    | email                 | "juan.perez@example.com"|
    | password              | "Secret123!"            |
    | password_confirmation | "Secret123!"            |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y debe existir una fila en la tabla "users" con email "juan.perez@example.com" y email_verified_at NULL
  Y se debe encolar un email de verificación con un token firmado que expira en 24 horas.

Escenario: Intento de registro con email duplicado
  Dado que ya existe un usuario en la tabla "users" con email "juan.perez@example.com"
  Cuando un visitante envía una petición POST a "/api/auth/register" con ese mismo email y contraseñas válidas
  Entonces el servidor debe responder con código HTTP 422 Unprocessable Entity
  Y el JSON de respuesta debe contener el error de validación para el campo "email".
```

### Criterio de aceptación — RF-02 (Registro de Médico y Aprobación)
```gherkin
Escenario: Registro de médico queda pendiente
  Dado que un visitante envía una petición POST a "/api/auth/register/doctor" con:
    | name            | "Dr. Gregory" |
    | email           | "house@clinic.com" |
    | license_number  | "MD-998877" |
    | specialty_ids   | [1, 2] |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y la tabla "doctor_profiles" debe contener un registro enlazado al nuevo usuario con status = "pending"
  Y si el médico intenta enviar POST a "/api/auth/login" con sus credenciales
  Entonces el servidor debe responder con código HTTP 403 Forbidden y el mensaje "Cuenta pendiente de aprobación".

Escenario: Administrador aprueba al médico
  Dado que existe un administrador autenticado y un médico en estado "pending" con ID 5
  Cuando el administrador envía una petición PUT a "/api/admin/doctors/5/review" con:
    | status          | "approved" |
  Entonces el servidor debe responder con código HTTP 200 OK
  Y la fila del médico en "doctor_profiles" debe actualizar status a "approved" y approved_at no nulo
  Y se debe encolar una notificación por email confirmando su habilitación.
```

### Criterio de aceptación — RF-03 (Invitación de Pacientes por Agente)
```gherkin
Escenario: Agente invita a un paciente por email
  Dado que un agente administrativo autenticado envía una petición POST a "/api/agent/patients/invite" con:
    | email        | "paciente.invitado@example.com" |
    | name         | "Carlos"                        |
    | last_name    | "Rodríguez"                     |
    | department   | "Recursos Humanos"              |
    | position     | "Analista de Nómina"            |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y debe existir una fila en la tabla "users" con email "paciente.invitado@example.com" y status "invited"
  Y debe encolarse un correo de invitación conteniendo el enlace de finalización de perfil.

Escenario: Error al invitar a un email previamente registrado
  Dado que ya existe un usuario con email "carlos@example.com"
  Cuando el agente envía la petición POST a "/api/agent/patients/invite" con el correo "carlos@example.com"
  Entonces el servidor debe responder con código HTTP 422 Unprocessable Entity
  Y no se debe generar ningún correo de invitación.
```

### Criterio de aceptación — RF-04 (Roles y Permisos Temporales)
```gherkin
Escenario: Asignación exitosa de permiso temporal con fecha de expiración
  Dado que un administrador autenticado envía una petición POST a "/api/admin/users/12/permissions" con:
    | permission_id | 3                      |
    | expires_at    | "2026-08-31 23:59:59"  |
  Entonces el servidor debe responder con código HTTP 200 OK
  Y debe existir una fila en la tabla "user_permissions" con user_id = 12, permission_id = 3 y expires_at = "2026-08-31 23:59:59".

Escenario: Denegación de acceso si el permiso temporal ha expirado
  Dado que el usuario 12 tiene asignado el permiso 3 con expires_at = "2026-07-30 12:00:00" (fecha en el pasado)
  Cuando el usuario 12 envía una petición GET a un endpoint restringido por el permiso 3
  Entonces el servidor debe responder con código HTTP 403 Forbidden.
```

### Criterio de aceptación — RF-05 (Gestión de Perfiles y Zonas Horarias)
```gherkin
Escenario: Actualización de perfil con especialidades y zona horaria
  Dado que un médico con perfil aprobado está autenticado
  Cuando envía una petición PUT a "/api/doctor/profile" con:
    | timezone      | "America/Tegucigalpa" |
    | description   | "Cardiólogo pediatra" |
    | specialty_ids  | [1, 3]                |
  Entonces el servidor debe responder con código HTTP 200 OK
  Y la tabla "users" debe registrar timezone = "America/Tegucigalpa" para ese usuario
  Y la tabla pivot "doctor_specialties" debe tener exactamente las especialidades 1 y 3 para ese perfil.

Escenario: Rechazo de aprobación de médico sin especialidades asignadas
  Dado que un administrador intenta PUT a "/api/admin/doctors/8/review" con status = "approved"
  Y el perfil del médico 8 no cuenta con registros en la tabla pivot "doctor_specialties"
  Entonces el servidor debe responder con código HTTP 422 Unprocessable Entity y el mensaje "El médico requiere al menos una especialidad antes de ser aprobado".
```

### Criterio de aceptación — RF-06 (Ficha Clínica Longitudinal)
```gherkin
Escenario: Médico añade una alergia estructurada confirmada
  Dado que un médico está autenticado y tiene una cita programada con el paciente 4
  Cuando envía una petición POST a "/api/patients/4/allergies" con:
    | substance     | "Penicilina" |
    | type          | "medicamento" |
    | severity      | "severe" |
    | reaction      | "Anafilaxia" |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y la tabla "patient_allergies" debe contener la fila con declarada_por = medico_id, confirmada_por = medico_id, y confirmada_en no nulo.

Escenario: Paciente declara alergia y queda sin confirmar por médico
  Dado que un paciente está autenticado en su perfil
  Cuando envía una petición POST a "/api/patient/my-allergies" con sustancia "Maní"
  Entonces el servidor debe responder con código HTTP 201 Created
  Y la tabla "patient_allergies" debe registrar declarada_por = paciente_id, confirmada_por = NULL, y confirmada_en = NULL.
```

### Criterio de aceptación — RF-07 (Directorio y Buscador de Médicos)
```gherkin
Escenario: Paciente busca especialistas y visualiza solo médicos aprobados y activos
  Dado que existen 3 médicos en la base de datos:
    | Dr. A | status = "approved" | is_active = true  |
    | Dr. B | status = "pending"  | is_active = true  |
    | Dr. C | status = "approved" | is_active = false |
  Cuando el paciente envía una petición GET a "/api/doctors?specialty_id=1"
  Entonces el servidor debe responder con código HTTP 200 OK
  Y el listado en la respuesta JSON debe incluir únicamente al Dr. A.

Escenario: Paginación activa en el listado de directorio
  Cuando un usuario consulta GET a "/api/doctors?page=1&per_page=12"
  Entonces la respuesta JSON debe contener una clave de metadata "meta" con total, per_page, current_page y last_page.
```

### Criterio de aceptación — RF-08 (Configuración de Agenda y Bloqueos)
```gherkin
Escenario: Médico añade horario semanal recurrente
  Dado que un médico con perfil aprobado envía una petición POST a "/api/doctor/schedules" con:
    | day_of_week   | 1 (Lunes) |
    | start_time    | "08:00"   |
    | end_time      | "12:00"   |
    | slot_duration | 30        |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y debe existir un registro en la tabla "schedules" asociado al doctor.

Escenario: Médico bloquea rango de fechas
  Dado que el médico tiene disponibilidad los lunes de 08:00 a 12:00
  Cuando envía una petición POST a "/api/doctor/schedule-blocks" con:
    | blocked_date  | "2026-08-03" (Lunes) |
    | start_time    | "09:00"              |
    | end_time      | "10:30"              |
    | reason        | "Cirugía de emergencia" |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y al consultar los slots disponibles para esa fecha, no deben mostrarse los slots de "09:00", "09:30" y "10:00".
```

### Criterio de aceptación — RF-09 (Reserva de Citas sin Solapamiento)
```gherkin
Escenario: Paciente reserva slot libre exitosamente
  Dado que un paciente autenticado y con email verificado envía una petición POST a "/api/appointments" con:
    | doctor_id      | 3                     |
    | scheduled_date | "2026-08-03"          |
    | scheduled_time | "08:30"               |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y la tabla "appointments" debe contener un registro con status = "pending" y franja configurada.

Escenario: Conflicto al intentar reservar slot ocupado concurrentemente
  Dado que la cita del médico 3 para la fecha "2026-08-03" y hora "08:30" ya está registrada en estado "confirmed"
  Cuando otro paciente envía una petición POST a "/api/appointments" para el mismo médico, fecha y hora
  Entonces el servidor debe responder con código HTTP 409 Conflict o 422 Unprocessable Entity
  Y el motor de base de datos debe rechazar la transacción por violación de la restricción EXCLUDE.
```

### Criterio de aceptación — RF-10 (Reserva en Nombre del Paciente)
```gherkin
Escenario: Agente realiza reserva en nombre de un paciente
  Dado que un agente administrativo está autenticado
  Cuando envía una petición POST a "/api/agent/appointments" con:
    | patient_id     | 14           |
    | doctor_id      | 3            |
    | scheduled_date | "2026-08-03" |
    | scheduled_time | "11:00"      |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y la cita debe ser creada con status = "pending" asociada al paciente 14.

Escenario: Agente tiene prohibido ver información clínica al gestionar citas
  Dado que un agente intenta realizar una petición GET a "/api/patients/14/clinical-file"
  Entonces el servidor debe responder con código HTTP 403 Forbidden
  Y el JSON de respuesta debe denegar explícitamente el acceso por falta de permisos.
```

### Criterio de aceptación — RF-23 (Asistente Informativo - Landing Pública)
```gherkin
Escenario: Visitante consulta información al asistente de la landing
  Dado que un visitante no autenticado interactúa con el Asistente Informativo en la landing pública
  Cuando realiza consultas sobre servicios, especialidades o médicos disponibles
  Entonces el asistente responde en modo de lectura pura consultando la vista pública "v_doctor_directory"
  Y el servidor rechaza categóricamente cualquier intento de inserción o modificación en la base de datos (0 escrituras)
  Y guía al visitante a registrarse o iniciar sesión si manifiesta intención de reservar una cita.
```

### Criterio de aceptación — RF-24 (Asistente Clínico - Dashboard del Paciente)
```gherkin
Escenario: Paciente interactúa con el Asistente Clínico para registrar antecedentes
  Dado que un paciente autenticado interactúa con el Asistente Clínico en su dashboard
  Cuando el paciente dicta sus alergias, condiciones o formulario pre-consulta
  Entonces el asistente ejecuta la inserción bajo el ID del paciente (app.current_user_id)
  Y las políticas de RLS garantizan que solo se escriban registros pertenecientes a ese paciente
  Y el atributo declarada_por se fija con el ID del paciente, quedando pendiente de confirmación médica (confirmada_por).

Escenario: Asistente Clínico intenta escribir una nota médica SOAP (Bloqueado por RLS)
  Dado que el Asistente Clínico opera bajo la sesión del paciente
  Cuando intenta ejecutar una inserción en la tabla "consultation_notes"
  Entonces PostgreSQL rechaza la transacción inmediatamente por violación de la política RLS "consultation_notes_insert"
  Debido a que la creación de notas SOAP está strictly restringida al médico de la consulta.

Escenario: Rechazo de interacción con Asistente Clínico durante consulta en curso (in_progress)
  Dado que un paciente autenticado tiene una consulta médica activa en estado status = "in_progress"
  Cuando el paciente o cliente envía una petición POST al endpoint del Asistente Clínico (RF-24)
  Entonces el servidor backend responde con código HTTP 409 Conflict
  Y el payload JSON contiene error_code = "ASSISTANT_DISABLED_DURING_CONSULTATION"
  Y la interfaz del Asistente Clínico se mantiene totalmente ausente durante toda la consulta en vivo.
```

### Criterio de aceptación — RF-25 (Cancelación de Citas y Política de Reembolso)

> [!IMPORTANT]
> **Limitación de Verificación de RF-25:** La lógica de negocio y política de reembolsos (cálculo de 100% vs 0% e indicación de estado de refund) está totalmente implementada en las Actions de dominio y base de datos, pero la emisión del reembolso monetario end-to-end en pasarela no es verificable en vivo hasta la construcción de **RF-12 (Pago con Stripe e Idempotencia)**.
```gherkin
Escenario: Médico cancela cita confirmada (Reembolso 100% automático)
  Dado que un médico cancela una cita en cualquier momento previo o posterior
  Cuando se registra la cancelación con cancelled_by = "doctor"
  Entonces el estado de la cita cambia a "cancelled"
  Y el sistema emite el reembolso completo (100%) al paciente sin aplicar penalizaciones ni restricciones de ventana temporal.

Escenario: Paciente cancela cita con más de 24 horas de antelación
  Dado que un paciente cancela una cita con > 24 horas antes del horario de inicio (local_start)
  Cuando se ejecuta la cancelación con cancelled_by = "patient"
  Entonces la cita pasa a estado "cancelled" y se procesa el reembolso correspondiente al paciente.

Escenario: Paciente cancela cita con menos de 24 horas de antelación
  Dado que un paciente cancela una cita con < 24 horas antes del horario de inicio
  Cuando se ejecuta la cancelación con cancelled_by = "patient"
  Entonces la cita pasa a estado "cancelled" y NO se emite reembolso al paciente conforme a las políticas de cancelación.
```

### Criterio de aceptación — RF-11 (Solicitud y Aprobación de Reprogramación)
```gherkin
Escenario: Aprobación de reprogramación exitosa en una sola transacción
  Dado que existe una cita confirmada con ID 100 para el slot viejo "2026-08-03 08:00"
  Y existe una solicitud de reprogramación en estado "pending" para el slot nuevo "2026-08-04 09:00"
  Cuando el médico envía PUT a "/api/appointments/100/reschedule-approve"
  Entonces el servidor ejecuta una única transacción DB que:
    1. Marca la cita original con status = "cancelled"
    2. Crea una nueva cita enlazada con status = "confirmed" en "2026-08-04 09:00"
  Y el servidor responde con código HTTP 200 OK.

Escenario: Falla la reprogramación si el nuevo slot se ocupa en el intertanto
  Dado el escenario anterior, pero el slot "2026-08-04 09:00" es reservado por otro paciente instantes antes de la aprobación
  Cuando el médico envía el PUT de aprobación de reprogramación
  Entonces el servidor ejecuta un rollback de la transacción
  Y responde con código HTTP 409 Conflict.
```

### Criterio de aceptación — RF-12 (Pago con Stripe e Idempotencia)
```gherkin
Escenario: Webhook procesa confirmación de pago por primera vez
  Dado que existe una cita con ID 105 en estado "pending"
  Cuando Stripe envía una petición POST a "/api/webhooks/stripe" con el evento "payment_intent.succeeded" y firma válida
  Entonces el servidor debe responder con código HTTP 200 OK
  Y la tabla "payments" debe actualizar status = "completed"
  Y la cita 105 debe cambiar status = "confirmed"
  Y se debe registrar el id del evento de Stripe en la tabla "processed_stripe_events".

Escenario: Webhook recibe evento duplicado de Stripe
  Dado que el evento de Stripe "evt_12345" ya está en la tabla "processed_stripe_events"
  Cuando Stripe reintenta enviar la petición POST con el mismo evento "evt_12345"
  Entonces el servidor debe responder con código HTTP 200 OK sin ejecutar transacciones de actualización en base de datos.
```

### Criterio de aceptación — RF-13 (Cuestionario Pre-consulta)
```gherkin
Escenario: Paciente completa cuestionario pre-consulta
  Dado que un paciente tiene una cita con ID 110 en estado "confirmed"
  Cuando el paciente envía una petición POST a "/api/appointments/110/pre-consultation" con:
    | motivo   | "Dolor en el pecho persistente" |
    | sintomas | "Disnea leve, palpitaciones"    |
  Entonces el servidor debe responder con código HTTP 201 Created
  Y debe existir un registro en la tabla "pre_consultation_forms" enlazado a la cita.

Escenario: Error al enviar cuestionario para una cita ya cancelada
  Dado que la cita 110 está en estado "cancelled"
  Cuando el paciente intenta enviar la petición POST con el cuestionario
  Entonces el servidor debe responder con código HTTP 422 Unprocessable Entity o 403 Forbidden.
```

### Criterio de aceptación — RF-14 (Consulta por Chat en Tiempo Real)
```gherkin
Escenario: Envío de mensaje en chat de consulta activa
  Dado que existe una consulta con ID 50 iniciada y la hora actual está dentro del slot de la cita
  Cuando el paciente de esa consulta envía un POST a "/api/consultations/50/messages" con contenido "Hola doctor"
  Entonces el servidor responde con código HTTP 201 Created
  Y el mensaje se persiste en la tabla "consultation_messages"
  Y el mensaje es despachado a través del canal privado de WebSocket mediante Laravel Reverb.

Escenario: Agente intenta enviar un mensaje o unirse a la consulta
  Dado que un agente autenticado intenta enviar un mensaje al chat de la consulta 50
  Entonces el servidor debe responder con código HTTP 403 Forbidden.
```

### Criterio de aceptación — RF-15 (Nota SOAP - Borrador a Firmada)
```gherkin
Escenario: Médico guarda borrador de la nota SOAP
  Dado que un médico tiene una consulta activa con ID 50
  Cuando envía una petición POST a "/api/consultations/50/notes" con:
    | symptoms   | "Dolor torácico" |
    | objective  | "Presión 120/80" |
    | status     | "draft"          |
  Entonces el servidor responde con código HTTP 200 OK
  Y la tabla "consultation_notes" almacena la nota en status = "draft".

Escenario: Paciente intenta ver la nota en borrador
  Cuando el paciente de la consulta 50 consulta GET a "/api/consultations/50/notes"
  Entonces el servidor debe responder con código HTTP 404 Not Found o 403 Forbidden (el borrador es confidencial para el médico).
```

### Criterio de aceptación — RF-16 (Firma Electrónica e Inmutabilidad)
```gherkin
Escenario: Médico firma la nota clínica
  Dado que existe una nota clínica en borrador para la consulta 50
  Cuando el médico envía una petición POST a "/api/consultations/50/notes/sign"
  Entonces el servidor realiza la firma:
    1. Calcula el hash SHA-256 del contenido clínico (motivo, síntomas, hallazgos, evaluación, plan)
    2. Guarda en "consultation_notes": status = "signed", content_hash, signed_by = doctor_id, signed_at = tiempo actual, signed_ip, signed_user_agent
  Y el servidor responde con código HTTP 200 OK.

Escenario: Bloqueo de modificación directa en nota firmada
  Dado que la nota clínica de la consulta 50 tiene status = "signed"
  Cuando el médico intenta enviar una petición PUT para modificar el campo "symptoms" de esa nota
  Entonces el servidor responde con código HTTP 403 Forbidden o 422 Unprocessable Entity
  Y rechaza cualquier modificación.
```

### Criterio de aceptación — RF-17 (Enmiendas Clínicas)
```gherkin
Escenario: Médico añade enmienda a una nota firmada
  Dado que la nota de la consulta 50 está en estado "signed"
  Cuando el médico de esa consulta envía una petición POST a "/api/consultations/50/notes/amendments" con:
    | reason  | "Olvidé detallar dosis de aspirina" |
    | content | "Se indica aspirina 100mg diarios"  |
  Entonces el servidor responde con código HTTP 201 Created
  Y se crea un registro en "note_amendments" enlazado a la nota
  Y la nota original en "consultation_notes" permanece exactamente idéntica.

Escenario: Usuario no autorizado intenta enmendar una nota
  Dado que un usuario que no es el médico de la consulta 50 intenta enviar la petición POST para enmendarla
  Entonces el servidor responde con código HTTP 403 Forbidden.
```

### Criterio de aceptación — RF-18 (Generación de PDF y QR Clínico)
```gherkin
Escenario: Firma de nota dispara generación de PDF en Horizon
  Dado que el médico firma la nota clínica de la consulta 50 y se confirma la transacción en base de datos
  Entonces se encola el job "GenerateClinicalNotePdfJob" en la cola de Horizon
  Y al procesarse el job se materializa el PDF usando DomPDF
  Y se estampa el hash SHA-256 y un QR con enlace de validación en el pie del documento
  Y la fila en "consultations" actualiza pdf_status a "pdf_ready" y pdf_path al storage privado.

Escenario: Verificación de autenticidad por QR público
  Cuando un visitante lee el QR del PDF e ingresa a "/verify/note/{hash}"
  Entonces el servidor responde con código HTTP 200 OK y muestra la ficha de verificación del documento clínico confirmando la integridad del hash contra la base de datos.
```

### Criterio de aceptación — RF-19 (Acuse de Recibo de Paciente)
```gherkin
Escenario: Paciente firma acuse de recibo de su nota clínica
  Dado que la nota clínica de la consulta 50 está firmada por el médico y el PDF está en estado "pdf_ready"
  Cuando el paciente envía una petición POST a "/api/consultations/50/acknowledge"
  Entonces el servidor responde con código HTTP 200 OK
  Y la base de datos actualiza acknowledged_at al tiempo actual
  Y se encola un job en Horizon para regenerar el PDF agregando la página de constancia de acuse de recibo del paciente.

Escenario: Error al firmar acuse si la nota está en borrador
  Dado que la nota de la consulta 50 tiene status = "draft"
  Cuando el paciente intenta enviar la petición POST de acuse de recibo
  Entonces el servidor responde con código HTTP 422 Unprocessable Entity.
```

### Criterio de aceptación — RF-20 (Auditoría Inmutable de BD)
```gherkin
Escenario: Registro de log de auditoría al modificar estado de médico
  Dado que un administrador cambia el estado de un médico a "approved"
  Entonces tras confirmarse el commit, se registra una fila en la tabla "audit_logs" con:
    | table_name  | "doctor_profiles" |
    | record_id   | 5                 |
    | action      | "UPDATE"          |
    | user_id     | admin_id          |
    | old_values  | {"status": "pending"} |
    | new_values  | {"status": "approved"} |

Escenario: Bloqueo de mutación en tabla de logs de auditoría
  Cuando un usuario (incluso administrador) intenta enviar una petición HTTP o query de eliminación DELETE a la tabla "audit_logs"
  Entonces la base de datos o el modelo debe abortar la operación y responder con error de base de datos inmutable.
```

---

## 5. Requerimientos no funcionales

| ID | Categoría | Requisito | Medido cómo | Sobre qué conjunto | Línea base | Umbral |
|---|---|---|---|---|---|---|
| RNF-01 | Rendimiento | Tiempos de respuesta rápidos para peticiones API de consulta de slots y agenda. | Tiempo de procesamiento HTTP registrado en los logs de Nginx/Laravel. | 95% de todas las peticiones GET a "/api/doctors/{id}/availability". | < 300 ms | < 500 ms |
| RNF-02 | Seguridad | Hasheo de credenciales de usuario con protección frente a fuerza bruta. | Algoritmo de hasheo bcrypt (12 rounds) + Rate limiting (middleware throttle). | Intentos de POST a "/api/auth/login" por dirección IP. | - | Máximo 5 peticiones por minuto. |
| RNF-03 | Privacidad | Aislamiento absoluto de archivos clínicos adjuntos. | Acceso denegado a través de URLs de almacenamiento públicas de S3. | Peticiones HTTP a rutas de archivos clínicos en S3. | Uptime de links privados | URLs temporales firmadas con expiración de 15 minutos. |
| RNF-04 | Disponibilidad | Continuidad operativa del canal de comunicación clínico (Reverb). | Pruebas automatizadas de reconexión del cliente WebSocket ante caídas de red. | Sesiones de chat activas de pacientes y médicos. | 99.5% de éxito en Handshake | Reconexión automática en menos de 5 segundos. |
| RNF-05 | Concurrencia | Prevención absoluta de reservas superpuestas concurrentes. | Intento de inserción concurrente de slots superpuestos en la base de datos PostgreSQL. | Set de 50 peticiones simultáneas sobre el mismo slot. | 100% rechazadas por base de datos | 0% de solapamientos permitidos en DB. |
| RNF-06 | Mantenibilidad | Cobertura de pruebas unitarias sobre transacciones clínicas críticas (reservas, cobros, firmas). | Reporte de cobertura de tests de Laravel (PHPUnit/Pest). | Lógica en directorio "app/Actions". | - | Cobertura mínima del 90%. |

---

## 6. Reglas de negocio

| ID | Regla | Punto de aplicación | Detalle |
|---|---|---|---|
| BR-01 | Médico no aprobado no opera | Middleware de backend (`EnsureDoctorApproved`) | El médico con status != "approved" en "doctor_profiles" tiene prohibido iniciar sesión, configurar slots, aparecer en el directorio de búsqueda y participar en consultas. |
| BR-02 | Slot de cita único y no solapable | Restricción EXCLUDE en PostgreSQL | La base de datos impide de forma estricta que existan dos citas en estado activo (no canceladas) del mismo médico cuyas franjas de tiempo (`tstzrange`) se solapen. |
| BR-03 | Bloqueo preventivo de citas pendientes de pago | Comando programado en Scheduler | Las citas creadas en estado "pending" que no registren un pago completado en Stripe dentro de los 30 minutos desde su creación se cancelan automáticamente por comando Cron. |
| BR-04 | Límite de reprogramación | Lógica en base de datos e interfaz | No es posible reprogramar una cita si faltan menos de 24 horas para su inicio, a menos que el médico la cancele administrativamente de manera explícitamente programada. |
| BR-05 | Comisión única y fija de plataforma | Action de facturación (`CalculatePlatformCommission`) | La comisión fija es del 15% (configurable por variables del sistema) aplicada sobre el total cobrado al paciente en USD. El monto neto del médico permanece retenido hasta que la cita sea completada. |
| BR-06 | Restricción de acceso a ficha clínica | Políticas de autorización de Laravel (Policies) | Un médico solo puede ver la ficha clínica longitudinal de un paciente si tiene una consulta agendada o registrada en el histórico con él. El acceso directo está prohibido. |
| BR-07 | Privacidad absoluta de agentes administrativos | Middleware y políticas de rutas de Laravel | El rol de agente tiene prohibido por completo el acceso a las rutas `/api/consultations/*` y `/api/patients/*/clinical-file`. Cualquier intento arroja HTTP 403. |
| BR-08 | Inmutabilidad de notas firmadas | Lógica en `SignConsultationNoteAction` | Una vez que el estado de la nota cambia a "signed" (firmada), se bloquean las peticiones UPDATE sobre ella. Solo se aceptan adiciones vía inserción en `note_amendments`. |
| BR-09 | Control de consulta única activa | Lógica en `CreateAppointmentAction` | El sistema impide que un paciente cree o tenga activa una nueva cita con el mismo médico si existe una consulta previa cuya nota clínica sigue en estado "draft" (borrador) y no ha sido formalmente firmada. |
| BR-10 | Aprobación sujeta a especialidades | Lógica en `ApproveDoctorAction` | Un administrador no puede aprobar el perfil de un médico que no tenga registrado al menos una especialidad médica activa asociada en la tabla pivot `doctor_specialties`. |

---

## 7. Contratos de API

[PENDIENTE — GATE 2C] (Se definirán detalladamente en el paso 4 del plan de trabajo para el módulo específico elegido).

---

## 8. Gobernanza de IA

> **NO APLICA**
> 
> **Motivo:** De acuerdo con el alcance definitivo de la Fase 1 aprobado en `docs/DECISIONES_ALCANCE.md`, el bot de WhatsApp híbrido impulsado por la API de Gemini y la integración de videollamadas con herramientas externas fueron totalmente descartados.
> 
> La plataforma se enfoca al 100% en la robustez clínica tradicional, seguridad transaccional e inmutabilidad de datos históricos. Por ende, la aplicación no utiliza modelos de lenguaje (LLMs), generación automatizada de textos clínicos, asistentes virtuales ni inteligencia artificial generativa en esta etapa de desarrollo. Toda la lógica del sistema es determinista.

---

## 9. Plano arquitectónico

[PENDIENTE — GATE 1.5] (Se definirá en el paso 3 del plan junto con el prototipo de UI).

---

## 10. Concurrencia e idempotencia

| Operación | ¿Dos veces? | ¿Dos usuarios a la vez? | Defensa |
|---|---|---|---|
| **Reservar cita** | Impedido por regla de validación de unicidad. | Evita colisiones por solapamiento horario. | Restricción nativa PostgreSQL `EXCLUDE USING gist (doctor_id WITH =, franja WITH &&) WHERE (status <> 'cancelada')` y extensión `btree_gist`. |
| **Crear horario médico** | Bloqueado en backend. | Evita colisiones de horarios recurrentes en un mismo día. | Restricción de exclusión en la tabla `schedules` sobre el día de la semana y rango de horas. |
| **Webhook de Stripe** | Se procesa una sola vez de forma exitosa. | Stripe puede enviar reintentos del mismo evento. | Tabla de eventos procesados con restricción de clave `UNIQUE` en el ID del evento de Stripe. |
| **Aprobar reprogramación** | El slot original se libera y el nuevo se toma. | Riesgo de que el nuevo slot sea tomado en ese instante. | Transacción ACID con aislamiento que evalúa la restricción de exclusión al momento de confirmar el commit. Si el slot está tomado, la transacción completa falla (`409 Conflict`). |
| **Cita sin pago** | Comandos Scheduler limpian. | Libera el slot para otros usuarios concurrentes. | Expiración programada por Cron a los 30 minutos desde la creación del registro "pending". |
| **Firmar nota clínica** | Bloqueo de firma duplicada. | Evita duplicidad de firmas o PDF clínico. | Transición atómica de estados controlada en la base de datos: `UPDATE ... WHERE status = 'draft'`. |

**Patrones verificar-y-después-escribir identificados:**
- **Verificar slot libre y reservar:** Si se verifica en PHP y luego se escribe, existe una condición de carrera clásica (dos solicitudes concurrentes leen el slot vacío a la vez y ambas escriben la cita). **Defensa:** Restricción de exclusión `EXCLUDE` de PostgreSQL. La base de datos arroja un error ante la coincidencia de franja, haciendo que una de las transacciones aborte.
- **Verificar y aplicar webhook de pago:** Si llega el webhook de Stripe confirmando el pago, el sistema verifica si la cita ya está confirmada y si no, actualiza. **Defensa:** Clave de idempotencia única del ID del evento en base de datos.

**Efectos externos y su clave de idempotencia:**
- **Cobro en Stripe:** Se crea un `PaymentIntent` único guardando su ID en la tabla de pagos enlazado a la cita. El webhook valida la firma contra `STRIPE_WEBHOOK_SECRET` y valida la unicidad del ID de evento.
- **Notificaciones por email:** Las notificaciones se despachan a Horizon después de confirmada la transacción en base de datos (`afterCommit`), evitando enviar correos si la base de datos hace un rollback.

**Consecuencias del motor PostgreSQL 16:**
- **No solapamiento de rangos:** Soportado de forma nativa con tipos de datos `tstzrange` (rangos de fecha/hora con zona horaria) y restricciones de exclusión `EXCLUDE USING gist`.
- **Zonas horarias:** Almacenamiento seguro usando `timestamptz`. Toda hora se guarda en UTC y se convierte en el frontend usando la propiedad `timezone` del usuario.
- **DDL transaccional:** Garantiza que las migraciones corran dentro de una transacción permitiendo rollback limpio si algo falla en la estructura.

---

## 11. Marca y políticas

**Perfil de marca:** PORTAFOLIO

| Característica | Detalle |
|---|---|
| **Firma en código** | Nombre real y enlace directo al portafolio al inicio de cada archivo de lógica. |
| **Cintillo / Footer** | Cintillo superior visible en la demo del frontend indicando el estado de demostración, y enlace a créditos en el pie de página. |

**Políticas de desarrollo:**
El trabajo en este repositorio sigue el protocolo de proceso propio del autor (`agent/PROTOCOLO.md` y `agent/EJEMPLARES.md`) — framework reutilizado en todos sus proyectos de portafolio, mantenido fuera de este repo público (ver `.gitignore`) porque no es contenido específico de telemedicina. La lógica de negocio se implementa únicamente en la carpeta `app/Actions/` con una sola clase y método `handle()` por caso de uso.

### 11.1 Jerarquía de autoridad

| Ámbito | Manda | Si hay conflicto |
|---|---|---|
| **El cómo** — proceso, estructura, capas, gates | `PROTOCOLO.md` | Si este PRD contradice una regla de proceso, **es un error de este PRD** |
| **El qué** — alcance, reglas de negocio, contratos | **Este documento (PRD v2.0)** | El protocolo no opina sobre el producto |
| **La forma de un archivo** | `EJEMPLARES.md` | Ver reglas X del protocolo |

### 11.2 Desviaciones declaradas del protocolo
Ninguna. Se adopta la configuración por defecto de PostgreSQL 16 y el combo de stack (a) de Laravel 11 + Vue 3 (Inertia).

---

### 11.3 Guion de la demo (Flujo Prioritario Fase 1)

El siguiente flujo define la prioridad técnica y de interfaz. Si una característica no participa en este guion, no es prioridad de entrega:

1. **Registro de Paciente:** Un visitante se registra como paciente en el portal público. Verifica su dirección de email a través de un enlace simulado en Mailtrap.
2. **Ficha Clínica:** El paciente inicia sesión y completa su ficha clínica longitudinal declarando alergias (ej: Penicilina), condiciones de salud preexistentes y medicación activa.
3. **Búsqueda e Interfaz Horaria:** El paciente ingresa al directorio, busca a un cardiólogo específico y observa su agenda. Los horarios se adaptan automáticamente a la zona horaria del paciente.
4. **Reserva y Pago:** El paciente selecciona un slot y procede al pago. Se le redirige al Checkout de Stripe (modo prueba) donde ingresa la tarjeta de pruebas. Completa la compra y se le redirige al portal de confirmación.
5. **Intento de Duplicidad (Concurrencia):** En una ventana de incógnito paralela, otro usuario intenta reservar exactamente el mismo slot a la misma hora para ese médico. Al enviar la petición, el sistema responde con un código de error HTTP `409 Conflict` (Defensa de la base de datos).
6. **Cuestionario:** El paciente original completa el cuestionario médico previo de síntomas detallando disnea y dolor torácico.
7. **Invitación de Agente:** En una sesión de Agente Administrativo, se crea un nuevo paciente por vía telefónica y se le envía un email de invitación. Al mismo tiempo, se demuestra que el Agente tiene bloqueado el acceso a cualquier ficha clínica.
8. **Consulta por Médico:** El médico inicia sesión, visualiza la cita activa y la abre. Se despliega la pantalla crítica: panel izquierdo con la ficha clínica longitudinal (donde se observa la alergia a la Penicilina declarada por el paciente) y panel derecho con la interfaz del chat clínico y las notas SOAP.
9. **Chat e Interacción:** El médico chatea con el paciente. Durante la conversación, el médico hace clic para confirmar la alergia de "Penicilina" (la cual cambia de estado en la ficha longitudinal).
10. **Nota SOAP:** El médico redacta los campos SOAP (Motivo, Objetivo, Análisis, Plan) en el panel derecho. El borrador se autoguarda periódicamente en la base de datos.
11. **Firma e Integridad:** El médico pulsa "Firmar Nota". El sistema calcula el hash SHA-256 del contenido clínico, actualiza la nota a "firmada" e inicia un proceso de encolado en Horizon.
12. **Inmutabilidad:** El médico intenta modificar la nota ya firmada por consola o vía HTTP. El sistema aborta y lanza un error `403 Forbidden`. Se comprueba que solo se pueden ingresar enmiendas complementarias con su respectivo motivo.
13. **Portal del Paciente:** El paciente refresca su portal. La nota clínica firmada aparece ahora en su pantalla de forma automática.
14. **Acuse de Recibo:** El paciente hace clic en "Firmar Acuse de Conformidad". El sistema registra el acuse y encola en Horizon la regeneración del PDF agregando la página de constancia.
15. **Descarga y QR:** El paciente descarga el PDF oficial de la consulta. Se observa el hash SHA-256 y un código QR al pie de página.
16. **Verificación:** Se escanea el código QR, abriendo un portal público de verificación que retorna "Documento íntegro y verificado con firma del Dr. Gregory".
17. **Detección de Alteraciones:** Se simula un ataque directo a la base de datos modificando el texto del diagnóstico firmado. Al volver a escanear el QR o verificar la nota, el sistema calcula el hash, detecta la disparidad y alerta: "¡ALERTA: Documento alterado!".
18. **Log de Auditoría:** El administrador ingresa al portal de auditoría y visualiza el historial inmutable de todas las mutaciones realizadas en las tablas de pagos, citas y notas SOAP.

---

## 12. Seguridad

| Superficie | Aplica | Definición |
|---|---|---|
| **Subida de archivos** | Sí | Límite máximo de 10 MB por archivo. Validación estricta del tipo MIME por contenido clínico (únicamente `application/pdf`, `image/jpeg` y `image/png`). Almacenamiento directo en el directorio privado del bucket S3. |
| **Autenticación** | Sí | Autenticación gestionada mediante Laravel Sanctum. Los tokens tienen un tiempo de expiración configurable de 24 horas y se revocan inmediatamente en todas las sesiones al pulsar cerrar sesión. |
| **Autorización** | Sí | Restricción por Policies y Middlewares de Laravel. Se valida que el médico sea el asignado a la cita del paciente para permitir lectura de su expediente. Bloqueo de rutas de salud clínica para usuarios con rol administrativo (agentes). |
| **CORS y Cabeceras** | Sí | Configuración estricta de CORS limitada al dominio de la SPA. Inclusión de cabeceras estándar de protección contra Clickjacking y XSS. Rate Limiting en la API para evitar denegación de servicios. |
| **Secretos** | Sí | Almacenamiento exclusivo de secretos API de Stripe, credenciales de base de datos y llaves criptográficas en variables de entorno `.env` cargadas mediante la configuración de Laravel. |

---

## 13. Decisiones pendientes

1. **[DECISIÓN PENDIENTE — HUMANO]** Cantidad de especialidades médicas admitidas en la plataforma y catálogo inicial de lanzamiento.
2. **[DECISIÓN PENDIENTE — HUMANO]** Duración estándar de los slots médicos (¿30 minutos fijos o configurables individualmente por profesional?).
3. **[DECISIÓN PENDIENTE — HUMANO]** Definición exacta de la ventana temporal de la política de retención de datos clínicos (¿cuántos años de resguardo legal obligatorio?).
4. **[DECISIÓN PENDIENTE — HUMANO]** Porcentaje exacto de la comisión del intermediario financiero de la plataforma (¿se consolida el 15.00% por defecto?).
5. **[DECISIÓN PENDIENTE — HUMANO]** Ventana de tiempo límite permitida para cancelaciones de cita sin cobro de penalidad (¿se mantiene el límite de 24 horas del borrador original?).
6. **[DECISIÓN PENDIENTE — HUMANO]** Aprobación del texto legal definitivo para el Consentimiento Informado de teleconsulta médica y si este debe controlarse con versionado en base de datos.

---

# PREFLIGHT — 18 preguntas antes de escribir la primera línea de código

| # | Pregunta | Estado / Respuesta |
|---|---|---|
| 1 | ¿Se puede derivar **el conjunto completo** de pruebas de aceptación leyendo solo el PRD, sin preguntar nada? | **Sí.** Los escenarios Gherkin detallados en la sección 4 y el guion detallado en la sección 11.3 proveen el 100% de los criterios para construir las pruebas. |
| 2 | ¿Todos los RF tienen Gherkin con al menos dos escenarios? | **Sí.** Los 20 RFs cuentan con sus escenarios específicos de camino feliz y caso de error en la sección 4. |
| 3 | ¿Todos los RNF con número declaran cómo se miden y sobre qué conjunto? | **Sí.** La tabla de la sección 5 define la métrica, el conjunto de peticiones, la línea base y el umbral de aceptación para cada requerimiento no funcional numérico. |
| 4 | ¿"Fuera de alcance" tiene al menos tres entradas concretas? | **Sí.** La sección 2.2 cuenta con 7 exclusiones específicas justificadas con sus respectivos motivos técnicos o de negocio. |
| 5 | ¿Cada regla de negocio dice **dónde** se hace cumplir? | **Sí.** La tabla de la sección 6 describe el punto exacto de aplicación (middleware, base de datos, políticas de código). |
| 6 | ¿Las reglas sobre dinero, stock o unicidad tienen defensa **en la base de datos**? | **Sí.** Se declaran restricciones de unicidad, llaves compuestas y la exclusión EXCLUDE gist de PostgreSQL 16. |
| 7 | ¿Está el contrato de cada endpoint, con errores y entrada inválida? | **No.** Se definirán en detalle durante el paso 4 (Gate 2C) del plan de trabajo para el módulo específico elegido. |
| 8 | Si usa IA: ¿están los siete puntos del bloque 8, incluido **retención de datos**? | **No aplica.** Declarado formalmente en la sección 8 que la plataforma no utiliza modelos de inteligencia artificial. |
| 9 | Si usa IA: ¿está escrito el **esquema de salida literal**? | **No aplica.** La plataforma no implementa modelos de IA. |
| 10 | ¿Está identificado cada patrón verificar-y-después-escribir, con su defensa? | **Sí.** Se detallan y resuelven en la sección 10 (concurrencia) identificando condiciones de carrera y defensas en base de datos. |
| 11 | ¿Está declarado el motor de BD y si fue **elegido o impuesto**? Si no es PostgreSQL, ¿están declaradas las sustituciones? | **Sí.** Declarado como PostgreSQL 16 (propio / elegido). No requiere tabla de sustitución por motor de base de datos alternativo. |
| 12 | ¿Existe `MAPA_ARQUITECTURA.md` aprobado (Gate 1.5)? | **No.** Queda pendiente su definición en el paso 3 (Gate 1.5 + 2A) del plan de trabajo. |
| 13 | ¿Está declarado el perfil de marca? | **Sí.** Configurado como perfil PORTAFOLIO en la sección 11. |
| 14 | ¿Está declarado el nivel de rigor según la matriz de reversibilidad? | **Sí.** Declarado como rigor MÁXIMO en el encabezado. |
| 15 | ¿El sello de `PROTOCOLO.md` en este repo coincide con la **versión vigente**? | **Sí.** Coincide con la versión 3.2 copiada en la fecha actual. |
| 16 | ¿Existe **ejemplar canónico** para cada tipo de archivo que este proyecto va a necesitar? Si falta uno, ¿está anotado? | **Sí.** Disponemos de los ejemplares de Laravel (StoreRequest, Action, Controller) y de base de datos en `agent/ejemplares/`. |
| 17 | ¿Toda desviación del protocolo está **declarada** en 11.2, con motivo y condición de retorno? | **Sí.** Declarada en la sección 11.2 indicando que no se registran desviaciones sobre el protocolo base. |
| 18 | ¿Cada RF tiene su columna **Estado** verificada contra el código real, y no contra la intención? | **Sí.** La columna de estado de todos los requerimientos funcionales está explícitamente marcada como PENDIENTE dado que el proyecto cuenta con cero código actual. |
