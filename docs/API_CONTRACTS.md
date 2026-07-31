# Contratos de API (Gate 2C) — Módulo de Citas y Agenda (Primer Módulo)

> **Estado:** Propuesta de Contratos para Aprobación
> **Módulo Elegido:** Citas y Agenda (`appointments` y `schedules`)
> **Formato de Error Común:**
> ```json
> {
>   "message": "El slot horario seleccionado ya no está disponible.",
>   "error_code": "AGENDA_SLOT_COLLISION",
>   "errors": {
>     "franja": ["El horario de 08:30 a 09:00 colisiona con una cita confirmada."]
>   }
> }
> ```

---

## 1. Justificación de la Elección del Primer Módulo

Proponemos el **Módulo de Citas y Agenda (Appointments & Schedules)** como el primero a implementar por las siguientes razones:

1. **Prueba de Fuego de Concurrencia:** Ejercita la exclusión nativa de PostgreSQL (`EXCLUDE USING gist` combinando igualdad y solapamiento) ante peticiones de reserva concurrentes, validando si el motor aborta las colisiones de slot de forma atómica.
2. **Evaluación de RLS y Bifurcación de Roles:** Permite verificar en un flujo transaccional real que el middleware `SetPostgresSessionContext` inicializa el contexto de la base de datos de manera correcta y que RLS restringe a `app_runtime` de ver/agendar citas ajenas.
3. **Complejidad Controlada:** No requiere la integración con pasarelas de pago externas activas de Stripe en el flujo crítico inicial (se puede simular la confirmación de pago por webhook o marcar el estado de pago temporalmente) ni la generación compleja de PDFs clínicos en Horizon, lo que minimiza el costo y esfuerzo de refactorización si detectamos fallos de base en la arquitectura de capas de Laravel (Actions vs Controllers).

---

## 2. Regla General de Retorno Clínico: 403 Forbidden contra 404 Not Found

Para evitar la fuga de información privada mediante enumeración de identificadores (e.g. un atacante infiriendo la existencia de pacientes o citas a través de las respuestas de error del servidor), el sistema impone la siguiente regla de diseño rígida:

* **`404 Not Found` (Ocultamiento de Existencia):**
  Se retorna cuando el recurso solicitado no existe, **o** cuando el usuario autenticado carece por completo de la autorización clínica para saber que dicho recurso existe (ej. un paciente intentando consultar una cita de otro paciente, o un médico consultando una cita de la agenda de otro médico). El atacante recibe la misma respuesta que si el UUID no estuviera en el sistema.
* **`403 Forbidden` (Denegación de Operación):**
  Se retorna cuando el usuario autenticado tiene derecho legítimo de saber que el recurso existe (ej. es el paciente propietario de su propia cita) pero la operación DML de negocio está inhabilitada o prohibida en su estado actual (ej. un paciente intentando reprogramar o cancelar una cita que ya fue completada, o un médico no aprobado intentando modificar su agenda laboral).

---

## 3. Definición de Endpoints y Contratos

### Endpoint 1: `GET /api/doctors/{id}/availability`
*Consulta de slots de citas libres del médico para una fecha específica, calculados en caliente restando citas ocupadas y bloqueos sobre la disponibilidad recurrente semanal.*

* **Auth / Roles:** Requiere autenticación (Sanctum). Accesible para: `patient`, `agent`, `admin`.
* **Criterio 403 vs 404:**
  * Si el UUID `{id}` del médico no existe en el sistema -> **`404 Not Found`**.
  * Si el médico existe pero está en estado `pending` o `rejected` por administración -> **`403 Forbidden`** (se sabe que existe el registro pero la agenda no está habilitada).
* **Parámetros de Consulta (Query Parameters):**
  * `date`: `string (format: YYYY-MM-DD, obligatorio)`. Ejemplo: `2026-08-03`.
* **Respuesta 200 OK:**
  ```json
  {
    "doctor_id": "550e8400-e29b-41d4-a716-446655440000",
    "date": "2026-08-03",
    "timezone": "America/Tegucigalpa",
    "slots": [
      {
        "start": "2026-08-03T14:00:00Z",
        "end": "2026-08-03T14:30:00Z",
        "local_start": "08:00 AM",
        "local_end": "08:30 AM",
        "available": true
      },
      {
        "start": "2026-08-03T14:30:00Z",
        "end": "2026-08-03T15:00:00Z",
        "local_start": "08:30 AM",
        "local_end": "09:00 AM",
        "available": false
      }
    ]
  }
  ```
* **Comportamiento ante Entrada Inválida/Ausente (422 Unprocessable Entity):**
  Si `date` no es enviada o tiene formato incorrecto:
  ```json
  {
    "message": "La fecha proporcionada no es válida.",
    "error_code": "VALIDATION_FAILED",
    "errors": {
      "date": ["El campo fecha debe cumplir con el formato YYYY-MM-DD."]
    }
  }
  ```

---

### Endpoint 2: `POST /api/appointments`
*Reserva preventiva de un slot de cita para un paciente. El registro se crea en estado `pending` bloqueando el slot durante 30 minutos.*

* **Auth / Roles:** Requiere autenticación. Accesible para roles: `patient`, `agent`.
* **Criterio 403 vs 404:**
  * Si el `doctor_id` o `patient_id` del payload no existen en la base de datos -> **`404 Not Found`** (evita enumeración de identidades).
  * Si el solicitante tiene el rol `patient` y el `patient_id` enviado corresponde al ID de otro usuario -> **`403 Forbidden`** (sabe que el otro usuario existe, pero tiene denegada la delegación de reservas).
* **Mecanismo de Idempotencia (Obligatorio):**
  * Requiere el header de petición: `X-Idempotency-Key` (UUIDv4 obligatorio).
  * Si la clave se recibe repetida dentro de una ventana de 5 minutos:
    * Si el cuerpo del payload es exactamente **idéntico**: Devuelve la respuesta en caché original (`201 Created`).
    * Si el cuerpo del payload **difiere** en algún campo: Devuelve **`400 Bad Request`** con código de error `IDEMPOTENCY_KEY_REUSED_WITH_DIFFERENT_PAYLOAD`, abortando la transacción.
* **Cuerpo de Petición (Request Body):**
  ```json
  {
    "patient_id": "660e8400-e29b-41d4-a716-446655441111",
    "doctor_id": "550e8400-e29b-41d4-a716-446655440000",
    "franja_inicio": "2026-08-03T14:00:00Z",
    "franja_fin": "2026-08-03T14:30:00Z"
  }
  ```
* **Headers:**
  * `X-Idempotency-Key`: `990e8400-e29b-41d4-a716-446655449999`
* **Respuesta 201 Created:**
  ```json
  {
    "id": "770e8400-e29b-41d4-a716-446655442222",
    "patient_id": "660e8400-e29b-41d4-a716-446655441111",
    "doctor_id": "550e8400-e29b-41d4-a716-446655440000",
    "franja": "[2026-08-03 14:00:00+00, 2026-08-03 14:30:00+00)",
    "status": "pending",
    "created_at": "2026-07-31T16:00:00Z"
  }
  ```
* **Comportamiento ante Solapamiento Concurrentes (409 Conflict):**
  Si el slot ya está tomado por otra cita activa confirmada o pendiente:
  ```json
  {
    "message": "El slot horario seleccionado ya está reservado.",
    "error_code": "SLOT_ALREADY_BOOKED",
    "errors": {
      "franja": ["La franja horaria coincide con una cita activa del médico."]
    }
  }
  ```

---

### Endpoint 3: `POST /api/appointments/{id}/reschedule-request`
*Solicitud de reprogramación iniciada por paciente o médico. La cita original permanece activa hasta la aprobación.*

* **Auth / Roles:** Requiere autenticación. Accesible para: `patient`, `doctor`.
* **Criterio 403 vs 404:**
  * Si el `{id}` de la cita no existe en base de datos, o si existe pero el solicitante no es el paciente ni el médico asociado a ella -> **`404 Not Found`** (Oculta la existencia de citas de terceros).
  * Si la cita pertenece al solicitante pero su estado es `cancelled` o `completed` -> **`403 Forbidden`** (Sabe que existe la cita pero no se puede reprogramar en su estado actual).
* **Cuerpo de Petición (Request Body):**
  ```json
  {
    "nueva_franja_inicio": "2026-08-04T15:00:00Z",
    "nueva_franja_fin": "2026-08-04T15:30:00Z",
    "motivo": "Conflicto con horario laboral"
  }
  ```
* **Respuesta 200 OK:**
  ```json
  {
    "appointment_id": "770e8400-e29b-41d4-a716-446655442222",
    "status": "pending_reschedule",
    "requested_by": "660e8400-e29b-41d4-a716-446655441111",
    "nueva_franja": "[2026-08-04 15:00:00+00, 2026-08-04 15:30:00+00)",
    "motivo": "Conflicto con horario laboral"
  }
  ```

---

### Endpoint 4: `PUT /api/appointments/{id}/reschedule-approve`
*Aceptación transaccional de la solicitud de reprogramación por el médico.*

* **Auth / Roles:** Requiere autenticación. Accesible para: `doctor` (aprobado).
* **Criterio 403 vs 404:**
  * Si la cita o la solicitud de reprogramación no existe, o si no pertenece a la agenda del médico autenticado -> **`404 Not Found`**.
  * Si es su cita pero el médico se encuentra suspendido (`status` no es `approved`) -> **`403 Forbidden`**.
* **Cuerpo de Petición:** Vacío (aprobación implícita de la solicitud activa).
* **Respuesta 200 OK:**
  ```json
  {
    "cita_original_cancelada": {
      "id": "770e8400-e29b-41d4-a716-446655442222",
      "status": "cancelled",
      "cancellation_reason": "Reprogramada a la cita 880e8400..."
    },
    "nueva_cita_confirmada": {
      "id": "880e8400-e29b-41d4-a716-446655443333",
      "patient_id": "660e8400-e29b-41d4-a716-446655441111",
      "doctor_id": "550e8400-e29b-41d4-a716-446655440000",
      "franja": "[2026-08-04 15:00:00+00, 2026-08-04 15:30:00+00)",
      "status": "confirmed"
    }
  }
  ```

---

### Endpoint 5: `POST /api/appointments/{id}/cancel`
*Cancelación de una cita existente. Gestiona el reembolso automático en Stripe si cumple la ventana temporal > 24 horas.*

* **Auth / Roles:** Requiere autenticación. Accesible para: `patient`, `doctor`, `agent`.
* **Criterio 403 vs 404:**
  * Si el `{id}` de la cita no existe, o pertenece a terceros y el solicitante no es un agente administrativo autorizado -> **`404 Not Found`**.
  * Si pertenece al solicitante pero la cita ya fue finalizada (`completed`) -> **`403 Forbidden`**.
* **Cuerpo de Petición (Request Body):**
  ```json
  {
    "cancellation_reason": "Paciente reporta imposibilidad de conexión por viaje."
  }
  ```
* **Respuesta 200 OK:**
  ```json
  {
    "id": "770e8400-e29b-41d4-a716-446655442222",
    "status": "cancelled",
    "cancelled_by": "660e8400-e29b-41d4-a716-446655441111",
    "cancellation_reason": "Paciente reporta imposibilidad de conexión por viaje.",
    "refund_processed": true
  }
  ```

---

### Endpoint 6: `POST /api/doctor/schedules`
*Configuración de la disponibilidad recurrente semanal del médico.*

* **Auth / Roles:** Requiere autenticación. Accesible para: `doctor` (aprobado).
* **Criterio 403 vs 404:**
  * Si el médico autenticado intenta pasar un `doctor_profile_id` de otro médico -> **`403 Forbidden`** (sabe que el ID del otro médico existe, pero no tiene privilegios de escritura en su agenda).
* **Cuerpo de Petición (Request Body):**
  ```json
  {
    "doctor_profile_id": "aa0e8400-e29b-41d4-a716-446655445555",
    "day_of_week": 1,
    "franja_inicio": "08:00:00",
    "franja_fin": "12:00:00",
    "slot_duration": 30
  }
  ```
* **Respuesta 201 Created:**
  ```json
  {
    "id": "990e8400-e29b-41d4-a716-446655444444",
    "doctor_profile_id": "aa0e8400-e29b-41d4-a716-446655445555",
    "day_of_week": 1,
    "franja": "('08:00:00', '12:00:00')",
    "slot_duration": 30,
    "is_active": true
  }
  ```
