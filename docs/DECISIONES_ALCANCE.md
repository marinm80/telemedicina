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
