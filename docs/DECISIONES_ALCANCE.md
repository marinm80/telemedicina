# Decisiones de alcance — Telemedicina (Proyecto 11)

> **Fecha:** 2026-07-29 · **Autor:** Rafael Marín
> **Estado:** Aprobadas. Son la entrada para reescribir `docs/PRD.md` a la versión 2.0.
> **El PRD v1.0 (2025-07-13) está desactualizado.** Donde este archivo y el PRD difieran, **manda este archivo**.

---

## 1. Cambios respecto al PRD v1.0

| # | Cambio | Motivo |
|---|---|---|
| 1 | **Motor: PostgreSQL 16**, no MySQL 8 | Proyecto nuevo sin código, la decisión es propia. `EXCLUDE USING gist` resuelve el no-solapamiento de citas y horarios **en el esquema**, no en el código. Más `timestamptz` real y DDL transaccional |
| 2 | **Canal de consulta: chat en tiempo real.** El video sale | Daily.co es una dependencia externa en el camino crítico: cuenta, claves, costo y una demo que depende de terceros. El chat es código propio (Laravel Reverb) y demuestra más |
| 3 | **Sistema general, no específico de Honduras** | Salió de un encabezado de Workana. Sin cliente real no hay forma de saber qué aplica localmente. Consecuencia: **zona horaria por usuario** y moneda única (USD) |
| 4 | **Idioma: español únicamente**, con estructura de claves de traducción preparada | Bilingüe cuesta mucho; dejar la estructura cuesta poco |
| 5 | **Perfil de marca: PORTAFOLIO** | Proyecto propio, sin cliente. Firma con nombre real y enlace, más cintillo de demostración |
| 6 | **Nivel de rigor: MÁXIMO** | Datos clínicos + dinero real (Stripe) + exclusividad de agenda. Las tres cosas irreversibles a la vez |

---

## 2. Alcance de la fase 1

1. Autenticación: registro de paciente, registro de médico con aprobación, verificación de email, **invitación por email** con cargo y departamento
2. **Roles y permisos en tablas**: roles, permisos, permisos individuales por usuario con **fecha de expiración**
3. Perfiles de paciente y médico. Especialidades. **Zona horaria por usuario**
4. **Ficha clínica longitudinal** del paciente: alergias, condiciones, medicación habitual
5. Directorio de médicos con búsqueda y filtros
6. Agenda: horarios recurrentes, bloqueos de fecha, cálculo de slots
7. **Reserva de cita sin solapamiento** ← el núcleo técnico del proyecto
8. **Reserva en nombre del paciente por parte de un agente**
9. **Solicitud de reprogramación con aprobación del médico**
10. Pago con Stripe + **webhook idempotente**
11. Cuestionario previo a la consulta
12. **Consulta por chat** en tiempo real, persistente, tratado como dato clínico
13. **Nota clínica** en formato SOAP: draft → signed
14. **Firma electrónica simple del médico**: hash de integridad, nota inmutable, **enmiendas** en lugar de ediciones
15. **PDF del informe** materializado al firmar, generado en cola
16. **Acuse de recibo del paciente** (opcional), que regenera el PDF con constancia
17. **Verificación pública de integridad** por QR
18. Documentos clínicos adjuntos a la consulta
19. Notificaciones por email e in-app
20. **Auditoría inmutable**

---

## 3. Fuera de alcance — con su motivo

| Excluido | Motivo |
|---|---|
| **Videollamada** (Daily.co) | Dependencia externa en el camino crítico. Fase 2; el modelo deja el canal declarado |
| **Módulo de farmacia**: inventario, compras a proveedores, costeo, utilidad, recetas | Es un ERP, no telemedicina. El subsistema que más cuesta y menos dice del autor |
| **Lotes y fecha de vencimiento** | Va con farmacia |
| **Doble portal paciente / empleado** | Fase 2. En fase 1 el rol determina lo que se ve |
| **Interfaces de enfermera, asistente y administrador de farmacia** | Los tres roles **existen en el modelo de permisos** con sus permisos definidos y verificables por API. No se construyen sus paneles |
| **Multi-moneda** | USD fijo. Stripe cobra en una moneda |
| **Bilingüe** | Español, con estructura preparada |
| **Reprogramación libre** | Solo por solicitud con aprobación del médico |
| **Firma digital con certificado (PKI, X.509)** | Se implementa **firma electrónica simple** con hash de integridad. La distinción se declara explícitamente para no prometer validez legal plena |

---

## 4. Actores

**Con interfaz en fase 1:**

| Actor | Puede | **NO puede** |
|---|---|---|
| **Paciente** | Registrarse, completar su ficha clínica, buscar médicos, reservar y cancelar, solicitar reprogramación, pagar, chatear en su consulta, ver y firmar el acuse de sus notas, descargar sus PDF | Ver historias de otros pacientes. Editar una nota clínica. Ver notas en borrador |
| **Médico** | Configurar agenda y bloqueos, ver su lista de citas, chatear, ver la ficha clínica **solo de pacientes con quienes tiene o tuvo consulta**, redactar y firmar notas, enmendar, aprobar o rechazar reprogramaciones, subir documentos | Ver pacientes con quienes no tuvo consulta. Editar una nota ya firmada. Ver ingresos de otros médicos |
| **Agente / recepcionista** | Crear pacientes y enviarles invitación, **reservar y cancelar en nombre del paciente**, **solicitar reprogramación en nombre del paciente** (NO aprobarla — aprobar es decisión del médico sobre su propia agenda), ver agenda y datos de contacto | **Ver la ficha clínica, la nota clínica o el chat. Nunca.** Es el límite de privacidad más importante del sistema |
| **Administrador** | Aprobar o rechazar médicos con motivo, gestionar especialidades, gestionar usuarios, otorgar y revocar permisos, ver auditoría | Desactivar su propia cuenta. Editar notas clínicas. Otorgarse permisos a sí mismo |

**Sin interfaz — modelo sí, pantallas no:** Enfermera, Asistente de farmacia, Administrador de farmacia. Sus roles y permisos existen en las tablas y se validan por API. Declarar esto en el README del proyecto.

---

## 5. Modelo clínico

**Dos cosas distintas, y no se mezclan:**

| | Qué | Dónde vive | Ciclo de vida |
|---|---|---|---|
| **Ficha del paciente** | Alergias, condiciones, medicación habitual, grupo sanguíneo | En el perfil | **Longitudinal**: persiste y se acumula |
| **Nota de consulta** | Motivo, síntomas, hallazgos, evaluación, plan (SOAP) | En esa consulta | **Puntual**: se cierra, se firma, no se edita |

Tablas nuevas:

```
patient_allergies      sustancia · tipo (medicamento/alimento/ambiental) · severidad ·
                       reaccion · declarada_por · confirmada_por · confirmada_en
patient_conditions     condicion · desde · estado (activa/resuelta) · notas
patient_medications    nombre · dosis · frecuencia · desde
pre_consultation_forms motivo · sintomas   (lo llena el paciente ANTES de la cita)
consultation_notes     motivo · sintomas · hallazgos · evaluacion · plan ·
                       status (draft|signed) · content_hash · signed_by ·
                       signed_at · signed_ip · signed_user_agent ·
                       acknowledged_by · acknowledged_at · pdf_status · pdf_path
note_amendments        nota_id · autor · motivo · contenido · created_at
vital_signs            peso · presion · temperatura   (reportados por el paciente)
audit_log              tabla · registro_id · accion · autor · antes · despues · created_at
```

**Las alergias van estructuradas, no en texto libre**: son lo que permitirá detectar contraindicaciones cuando llegue la farmacia. Modelarlas bien ahora es gratis; convertir un `textarea` en datos después, no.

**Alergias: las declara el paciente, el médico las confirma.** De ahí `declarada_por` y `confirmada_por`.

---

## 6. Firma, inmutabilidad y PDF

**Dos firmas con pesos distintos:**

| Firma | Significa | Obligatoria |
|---|---|---|
| **Del médico** | Cierra la nota: inmutable, hash calculado, PDF generado | **Sí.** Sin ella no hay informe |
| **Del paciente** | Acuse de recibo / conformidad | **No.** La validez del informe **no depende** de que el paciente firme |

**Reglas:**

- La nota firmada **no se edita nunca**. Se agrega una **enmienda** con autor, fecha, motivo y contenido. El original permanece intacto.
- Al firmar se calcula un **SHA-256 del contenido**. La verificación recalcula y compara: si no coincide, la nota fue alterada fuera del sistema.
- **El PDF se materializa al firmar**, no se genera al descargar. Se guarda una vez y se sirve siempre el mismo archivo — el PDF *es* el documento, no una vista de la base. Si se generara al vuelo, cambiar la plantilla alteraría el aspecto de todos los informes históricos.
- La generación del PDF va en **job de Horizon, después del commit**. Nunca dentro de la transacción de firma. Estado: `pdf_pendiente` → `pdf_listo`. Si el job falla, se reintenta; la nota ya está firmada.
- **Herramienta: DomPDF, no Browsershot.** Browsershot exige Chrome en el servidor (300–500 MB por instancia) y el VPS es chico. El informe es texto estructurado.
- El PDF lleva al pie el **hash y un QR** que apunta a una URL pública de verificación de integridad.
- Cuando el paciente firma el acuse, el PDF **se regenera agregando una página de constancia**. El informe original no se altera.

**Borrador:**

- Uno por consulta, editable **solo por el médico de esa consulta**, con autoguardado.
- **El paciente no ve el borrador.** Aparece en su portal solo al firmar.
- No expira, pero: recordatorio a las 24 h, y **el sistema no permite abrir una consulta nueva con el mismo paciente si quedó una nota sin firmar**.

---

## 7. Concurrencia e idempotencia

| Operación | Riesgo | Defensa |
|---|---|---|
| **Reservar cita** | *Write skew*: dos pacientes, mismo slot | `EXCLUDE USING gist (doctor_id WITH =, franja WITH &&) WHERE (status <> 'cancelada')` + `CREATE EXTENSION btree_gist`. **La base lo impide, no el código** |
| **Crear horario del médico** | Solapamiento de franjas en el mismo día | Mismo mecanismo sobre `schedules` |
| **Paciente duplica cita** | Dos citas con el mismo médico, misma fecha y hora | `UNIQUE` compuesto |
| **Webhook de Stripe** | **Stripe reintenta: va a llegar dos veces** | Clave de idempotencia con `UNIQUE` sobre el id del evento. Verificar firma `Stripe-Signature` |
| **Aprobar reprogramación** | Liberar el slot viejo y tomar el nuevo | Tabla separada `reschedule_requests` (solo paciente/agente solicitan, médico aprueba). EXCLUDE USING gist propio sobre `requested_franja`. Índice parcial único `WHERE status = 'pending'` por cita. La aprobación ejecuta **una sola transacción**: cancela la cita original + inserta la nueva. Si el nuevo slot ya está tomado, falla completo |
| **Médico quiere mover la cita** | — | **El médico NO reprograma. Cancela.** Cancela con motivo obligatorio (`cancelled_by = doctor`), reembolso completo SIEMPRE (sin ventana de 24h). El paciente vuelve a reservar eligiendo de la disponibilidad actual. Cero estado intermedio, cero slot retenido, el paciente elige |
| **Cita pendiente sin pago** | Bloquea el slot indefinidamente | Comando programado que expira a los 30 minutos |
| **Firmar nota** | Doble firma, doble PDF | Transición de estado verificada en la base; el job de PDF es idempotente por `nota_id` |

**Regla transversal:** ningún efecto externo —correo, notificación, generación de PDF— ocurre dentro de una transacción abierta. Todo después del commit, con `afterCommit` o encolado.

**Principio de diseño:** ante una operación nueva, primero probar si se puede expresar con las garantías que ya existen. Agregar estado es la última opción, no la primera.

---

## 8. Autorización y privacidad

- **Un médico ve la ficha clínica de un paciente solo si tiene o tuvo una consulta con él.** No "cualquier médico ve cualquier paciente".
- **El agente nunca accede a datos clínicos**: ni ficha, ni nota, ni chat, ni documentos. Solo agenda y contacto.
- **El correo nunca lleva contenido clínico.** Es una notificación con enlace, y el enlace exige sesión. Si hay descarga de PDF, es un enlace **temporal y firmado** que caduca, nunca un adjunto.
- Nadie puede otorgarse permisos a sí mismo.
- El chat es **dato clínico**: se retiene, se audita y lo ve quien puede ver la consulta.
- **Auditoría vía triggers de PostgreSQL, no listeners de Eloquent.** Un listener no captura escrituras por SQL directo (worker, migraciones, seeders). Los triggers interceptan toda escritura sin importar el origen. La función `fn_audit_log()` lee `current_setting('app.current_user_id', true)` para el actor; cuando no hay contexto HTTP (worker, migraciones), registra `current_user` (el rol de PostgreSQL) como actor de sistema.

---

## 9. Guion de la demo — define la prioridad

**Si algo no está en este guion, no es prioridad de la fase 1.**

```
 1. Me registro como paciente. Verifico el email.
 2. Completo mi ficha: alergias, condiciones, medicación habitual.
 3. Busco un cardiólogo. Veo su agenda EN MI ZONA HORARIA.
 4. Reservo un slot. Pago con Stripe (tarjeta de prueba).
 5. --- Otra pestaña, mismo slot: 409 Conflict. ---
 6. Lleno el cuestionario previo: motivo y síntomas.
 7. Entro como agente: creo otro paciente por teléfono y le envío invitación.
 8. Entro como el médico. Abro la consulta:
    izquierda la ficha del paciente, derecha el chat y la nota.
 9. Chateamos. Confirmo una alergia que el paciente había declarado.
10. Completo la nota: hallazgos, diagnóstico, plan. Guardo borrador.
11. Firmo. Hash calculado, PDF encolado, paciente notificado.
12. --- Intento editar la nota: bloqueado. Solo enmienda con motivo. ---
13. Entro como paciente: la nota firmada está en mi portal.
14. Firmo el acuse. El PDF se regenera con la constancia.
15. Descargo el PDF: hash y QR al pie.
16. Escaneo el QR: "documento íntegro".
17. --- Altero la nota en la base a mano. Reescaneo: "documento alterado". ---
18. Auditoría completa: quién, qué, cuándo, antes y después.
```

Los pasos **5**, **12** y **17** son los tres momentos que demuestran competencia técnica. Son los que hay que clavar.

---

## 10. Decisiones que siguen pendientes

1. Cantidad de especialidades y catálogo inicial
2. Duración estándar del slot (¿30 min configurable por médico?)
3. Política de retención de datos clínicos: ¿cuántos años?
4. Comisión de la plataforma: ¿se mantiene el 15% del PRD v1?
5. Ventana de cancelación gratuita: ¿se mantienen las 24 h del PRD v1?
6. Texto del consentimiento informado para teleconsulta, y si se versiona

---

## 11. RF-08 — Decisiones de agenda del médico (cerradas 2026-08-03)

### 11.1 Zona horaria en horarios recurrentes

`schedules.franja` es `timerange` (hora de pared). `users.timezone` es la zona IANA.

- **Sin desplazamiento ni 'Z'.** Rechazo 422. Una hora de pared con desplazamiento es una contradicción; se cierra en la frontera, no se interpreta. Formato: `HH:MM` o `HH:MM:SS`.
- **Hora inexistente (DST primavera):** el slot se omite. No se corre el bloque hacia adelante: correrlo genera slots a horas que el médico no autorizó.
- **Hora duplicada (DST otoño):** primera ocurrencia gana. Dos slots mostrando "01:30" son indistinguibles para el paciente. El médico pierde una hora dos veces al año.
- **Cambio de timezone con citas futuras: PROHIBIDO (409).** Trigger `BEFORE UPDATE ON users`. Sin trigger es verificar-y-después-escribir. Recalcular está descartado: mover la cita de un paciente sin su consentimiento. El remedio del médico es cancelar con reembolso completo.

### 11.2 schedules.is_active eliminada

Se queda solo `deleted_at`. Motivos:
1. Dos mecanismos para "apagado" divergen.
2. `deleted_at` preserva la fila para auditoría y el EXCLUDE ya lo maneja.
3. La pausa temporal se hace con `schedule_blocks`, que existe para eso.

### 11.3 schedule_blocks: sin EXCLUDE, sin deleted_at, con UNIQUE

- **Sin EXCLUDE:** un bloqueo RESTA disponibilidad. Restar dos veces da lo mismo.
- **Sin deleted_at:** borrado físico. Un bloqueo es dato OPERATIVO, no hecho clínico. El trigger `trg_audit_schedule_blocks` (AFTER DELETE) graba el DELETE con `old_values` en `audit_logs`.
- **UNIQUE (doctor_profile_id, blocked_date, franja):** idempotencia gratis. Un reintento choca con 23505 y se traduce a 204.

### 11.4 Política de borrado por tabla

| Tabla | Mecanismo | Motivo |
|---|---|---|
| `schedules` | Soft delete (`deleted_at`) | El EXCLUDE necesita `WHERE (deleted_at IS NULL)` para liberar el rango |
| `appointments` | Soft delete (`deleted_at`) | FK `rescheduled_from` apunta a citas anteriores |
| `users` | Soft delete (`deleted_at`) | FK desde múltiples tablas |
| `schedule_blocks` | Borrado físico | Sin EXCLUDE, sin FK entrante. Dato operativo con trigger de auditoría |
| `audit_logs` | Inmutable | No se borra nunca |

### 11.5 Bug confirmado: GetDoctorAvailabilityAction líneas 83-84 y 99-100

La conversión `hora_pared + fecha → instante` trata la hora de pared como UTC (`Carbon::parse($date . ' ' . $time, 'UTC')`), ignorando `users.timezone`. Corrección: `(fecha + lower(franja)) AT TIME ZONE zona` en SQL. Los instantes resultantes deben ser strictly crecientes; descartar duplicados implementa DST sin lógica a mano.

---

## 12. Clarificación de Agentes y Asistentes Conversacionales (cerradas 2026-08-03)

### 12.1 Distinción estricta de términos y funciones
Se definen y diferencian tres subsistemas distintos para evitar ambigüedades arquitectónicas y de seguridad:

| Término | Entorno | Sesión | Rol de Sistema | Permisos y Capacidades |
|---|---|---|---|---|
| **Recepcionista** | Dashboard | Autenticada | `agent` | **Rol humano (RF-10, ya construido).** Puede pre-registrar pacientes, enviar invitaciones y agendar/reprogramar citas. **Bloqueo absoluto RLS a datos clínicos (403).** |
| **Asistente Informativo** | Landing pública | Sin sesión | Sin rol (Guest) | **Lectura pura (Read-Only). SIN ESCRITURA EN BASE DE DATOS.** Explica servicios/políticas, guía al registro/login y lee del directorio público (`v_doctor_directory` con `security_barrier`). Exige iniciar sesión para agendar. |
| **Asistente Clínico** | Dashboard | Autenticada | Mismo del paciente (`patient`) | **Asistente en el portal del paciente.** Actúa CON la sesión y el `app.current_user_id` del paciente autenticado. **NUNCA usa el rol `agent`** (preservando el límite de privacidad). |

> **Cancelación de alcance previo:** Se eliminan formalmente del alcance `verification_expires_at`, las reservas temporales de 20 minutos, las transiciones CAS para expiración, el reintento perezoso sobre error `23P01`, la limpieza en Horizon y la creación de cuentas sin sesión por parte del asistente. Al ser el Asistente Informativo un componente de **lectura pura**, no existen escrituras temporales que proteger ni limpiar.

### 12.2 Asistente Informativo (Landing Pública)
- **Cero escrituras:** Si intenta escribir en cualquier tabla, constituye un defecto de diseño y no un permiso faltante.
- **Acceso a directorio:** Consulta `v_doctor_directory` (vista pública con `security_barrier` que filtra médicos aprobados y oculta datos personales como licencias o correos).
- **Interacción:** Responde preguntas sobre especialidades, horarios generales y políticas de la clínica. Guía al usuario a registrarse o iniciar sesión.

### 12.3 Asistente Clínico (Dashboard del Paciente)
- **Identidad RLS:** Operando bajo la sesión del paciente, PostgreSQL asigna `app.current_user_id = '<patient_uuid>'` y `app.current_user_role = 'patient'`.
- **Verificación de Políticas RLS Existentes:**
  - `patient_allergies_insert`: Permite al paciente insertar registros donde `p.user_id = current_user_id`.
  - `patient_conditions_insert`: Permite al paciente insertar registros donde `p.user_id = current_user_id`.
  - `pre_consultation_forms_insert`: Permite al paciente insertar su formulario donde `a.patient_id = current_user_id`.
  - `vital_signs_insert`: Permite al paciente registrar signos vitales donde `a.patient_id = current_user_id`.
  - **Resultado:** El Asistente Clínico hereda estas reglas exactas sin necesidad de agregar una sola política RLS nueva ni conceder privilegios adicionales.
- **Límite Duro Inquebrantable:** `consultation_notes_insert` exige `status = 'draft'` y `a.doctor_id = current_user_id`. El asistente (actuando como paciente) tiene un **bloqueo estricto por RLS que le impide escribir o alterar notas clínicas del médico**.
- **Declaración vs Confirmación:** Todo dato ingresado a través del asistente se registra como `declarada_por = patient_id` y queda sujeto a validación y confirmación del profesional médico (`confirmada_por`).

### 12.4 Propuesta de Procedencia de Datos (Data Provenance)
- **Problema identificado:** Dado que el Asistente Clínico actúa bajo la sesión del paciente (`app.current_user_id`), ni `audit_logs.user_id` ni los triggers de auditoría distinguen si un dato fue tipeado manualmente por el paciente o transcrito conversacionalmente por el asistente de IA.
- **Mecanismo propuesto:** Incorporar una columna opcional `origin text NOT NULL DEFAULT 'user'` con restricción `CHECK (origin IN ('user', 'ai_assistant', 'doctor_confirmed'))` en las tablas de entrada del paciente (`patient_allergies`, `patient_conditions`, `patient_medications`, `pre_consultation_forms`, `vital_signs`).
- **Consecuencia:** 
  1. La auditoría captura `origin` dentro del payload JSON `new_values` de `fn_audit_log()` sin modificar los triggers existentes.
  2. Las políticas de RLS permanecen intactas (evalúan propiedad por `user_id`, no por `origin`).
  3. La ficha clínica y el informe pueden indicar visualmente la fuente del dato para el médico.

### 12.5 Orden de Construcción
`RF-01 (Auth / Login)` → `RF-08 (Escritura Agenda Médico)` → `RF-25 (Cancelación de Citas)` → `RF-23 (Asistente Informativo - Landing)` → `RF-24 (Asistente Clínico - Dashboard)`.

---

## 13. Declaración de Deuda Técnica Controlada (Hallazgo 10)

- **Hallazgo 10 (GRANT UPDATE blanket en `schedules` y `schedule_blocks`):**
  - **Estado:** Declarado como Deuda Técnica Controlada.
  - **Precisión Técnica RLS:** PostgreSQL Row Level Security (RLS) evalúa predicados a nivel de fila (`USING` / `WITH CHECK`), determinando cuáles *filas* se pueden consultar o modificar, **nunca cuáles *columnas***.
  - **Mecanismo en `schedules` y `schedule_blocks`:** La columna `doctor_profile_id` queda protegida contra alteraciones accidentales en un `UPDATE` únicamente porque la regla de la política RLS evalúa la coincidencia del propietario (`dp.id = doctor_profile_id AND dp.user_id = app.current_user_id`). Intentar cambiar `doctor_profile_id` a otro médico hace fallar el predicado de la fila resultante. Las demás columnas (`franja`, `slot_duration`, `day_of_week`, `blocked_date`, `reason`) son libremente editables por el dueño de la agenda, lo cual corresponde al comportamiento de negocio intencionado.
  - **Deuda Abierta:** Para las tablas donde esta coincidencia de predicado no se produce, o si se requiere inmutabilidad estricta a nivel DDL sobre columnas específicas, la deuda del Hallazgo 10 se saldará acotando los permisos con `GRANT UPDATE (columnas...)`.
