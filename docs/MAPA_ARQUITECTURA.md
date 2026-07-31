# Mapa Arquitectónico del Proyecto (Gate 1.5)

> **Estado:** Propuesta de Diseño para Aprobación
> **Stack:** Laravel 11 + Vue 3 (Inertia) + Redis
> **Arquitectura:** Feature-First / Action-Driven

---

## 1. Estructura Completa de Carpetas

```
mi-proyecto/
├── app/
│   ├── Actions/                    # Lógica de Negocio (Una clase por operación)
│   │   ├── Auth/
│   │   ├── Clinical/
│   │   ├── Appointments/
│   │   ├── Billing/
│   │   └── Consultations/
│   ├── Http/
│   │   ├── Controllers/            # Traductores de Transporte (Inertia / JSON)
│   │   ├── Middleware/             # Filtros de Frontera (Contexto RLS, Roles)
│   │   └── Requests/               # Validaciones de Entrada en la Frontera
│   ├── Models/                     # Representación de Entidades y Global Scopes
│   └── Policies/                   # Autorización de Negocio (Laravel Policies)
├── bootstrap/
│   └── app.php                     # Registro de Middlewares Globales
├── config/
│   └── database.php                # Configuración de pgsql (runtime) y pgsql_owner
├── docs/
│   ├── PRD.md                      # Especificación v2.0 Aprobada
│   ├── DATABASE_SCHEMA.md          # DDL e índices RLS Aprobados
│   └── AUTHORIZATION.md            # Protocolo de Seguridad RLS Aprobado
├── resources/
│   └── js/                         # Frontend Vue 3 (Inertia SPA)
│       ├── Components/             # Componentes Reactivos con 4 Estados
│       │   ├── Clinical/
│       │   ├── Appointments/
│       │   └── UI/
│       ├── Composables/            # Ganchos de Estado y API (useUsers, useChat)
│       └── Pages/                  # Vistas del Enrutador Inertia
│           ├── Auth/
│           ├── Clinical/
│           ├── Appointments/
│           └── Doctor/
```

---

## 2. Matriz de Responsabilidad Única (Una Línea por Archivo)

| Nombre de Archivo | Qué hace | Qué NO hace |
|---|---|---|
| `SetPostgresSessionContext.php` | Inyecta las variables de sesión del usuario en PostgreSQL. | No verifica permisos ni manipula respuestas HTTP. |
| `EnsureUserIsNotAgent.php` | Bloquea el acceso a rutas clínicas a usuarios con el rol Agent. | No gestiona sesiones ni consulta registros clínicos. |
| `EnsureDoctorApproved.php` | Bloquea el login y el acceso a médicos no aprobados. | No realiza validaciones de credenciales ni contraseñas. |
| `AuthController.php` | Traduce peticiones HTTP de login y registro a vistas de Inertia. | No contiene lógica de encriptación de claves ni guarda usuarios. |
| `ClinicalController.php` | Expone endpoints para historial, alergias y documentos clínicos. | No accede a la base de datos ni realiza auditorías directamente. |
| `AppointmentController.php` | Maneja peticiones de reserva, visualización y cancelaciones de citas. | No calcula colisiones de horarios ni aplica cobros de Stripe. |
| `BillingController.php` | Recibe llamadas HTTP de Stripe y despacha el webhook a la Action. | No genera firmas de webhooks ni calcula comisiones de plataforma. |
| `ConsultationController.php` | Expone el chat de consulta y la redacción de notas clínicas SOAP. | No calcula hashes SHA-256 de notas ni despacha PDFs a Horizon. |
| `StoreUserRequest.php` | Valida y autoriza en la frontera la entrada del registro. | No persiste el usuario ni encripta la contraseña. |
| `BookAppointmentRequest.php` | Valida la fecha, hora y validez del ID del slot en el request. | No consulta si el slot está libre en la base de datos. |
| `StoreClinicalNoteRequest.php` | Valida que los campos SOAP tengan formato y extensión correctos. | No firma electrónicamente la nota ni cambia su estado. |
| `PatientProfilePolicy.php` | Comprueba si un usuario tiene permisos para ver un perfil de paciente. | No filtra los registros resultantes del query SQL. |
| `ConsultationNotePolicy.php` | Comprueba si el médico es el autor de la consulta o el paciente tratante. | No verifica el hash de integridad de la nota clínica. |
| `AppointmentPolicy.php` | Valida que el usuario sea el paciente o el médico dueño de la cita. | No valida si el slot está dentro del horario laboral. |
| `RegisterPatientAction.php` | Crea el registro en Users y PatientProfiles en transacción ACID. | No maneja redirecciones HTTP ni despacha emails de bienvenida. |
| `BookAppointmentAction.php` | Inserta atómicamente la cita validando los slots disponibles. | No procesa cargos en la tarjeta de crédito. |
| `ApproveRescheduleAction.php` | Modifica en una transacción la cita vieja y confirma el nuevo slot. | No notifica por correo electrónico a las partes afectadas. |
| `ProcessPaymentWebhookAction.php` | Registra el pago, cambia estado de la cita y calcula la comisión. | No valida la firma de Stripe-Signature. |
| `SignSOAPNoteAction.php` | Estampa la firma, calcula hash SHA-256 de integridad y cierra la nota. | No genera el archivo PDF ni lo carga en el storage S3. |
| `AddNoteAmendmentAction.php` | Inserta la enmienda enlazada a la nota SOAP firmada inmutable. | No modifica los campos de texto del original. |
| `User.php` | Representa la entidad de usuario y sus relaciones de autenticación. | No maneja lógica de visualización clínica. |
| `PatientProfile.php` | Modela la ficha del paciente y sus relaciones con alergias y condiciones. | No almacena credenciales ni claves. |
| `DoctorProfile.php` | Modela los datos profesionales del médico, especialidades y cobros. | No filtra los ingresos financieros totales del sistema. |
| `Appointment.php` | Representa la cita y aplica la exclusión nativa de solapamiento. | No calcula las tarifas netas de comisiones. |
| `Consultation.php` | Representa el canal clínico activo y sus mensajes enlazados. | No realiza la firma digital de las notas SOAP. |
| `ConsultationNote.php` | Modela la nota SOAP y aplica el Global Scope para ocultar borradores. | No permite ediciones una vez guardado en estado firmada. |

---

## 3. Diccionario de Actions y Firmas de Negocio

* **`App\Actions\Auth\RegisterPatientAction`**
  * *Firma:* `public function handle(array $data): User`
  * *Propósito:* Ejecuta la creación del registro en `users` y `patient_profiles` dentro de una transacción ACID, retornando el usuario e iniciando la cola de verificación.
* **`App\Actions\Appointments\BookAppointmentAction`**
  * *Firma:* `public function handle(string $patientUserId, string $doctorUserId, string $franjaRange): Appointment`
  * *Propósito:* Reserva un slot horario libre insertándolo atómicamente en `appointments`.
* **`App\Actions\Appointments\ApproveRescheduleAction`**
  * *Firma:* `public function handle(string $appointmentId, string $rescheduleRequestId): Appointment`
  * *Propósito:* Libera el slot de la cita original y activa el nuevo en una única transacción.
* **`App\Actions\Billing\ProcessPaymentWebhookAction`**
  * *Firma:* `public function handle(string $stripeEventId, array $payload): void`
  * *Propósito:* Valida la idempotencia del evento, confirma la cita, registra el pago en `payments` y calcula la comisión en `commissions`.
* **`App\Actions\Consultations\SignSOAPNoteAction`**
  * *Firma:* `public function handle(string $consultationId, array $soapData, string $doctorUserId, string $ip, string $userAgent): ConsultationNote`
  * *Propósito:* Calcula el hash SHA-256 de la nota clínica SOAP, inmoviliza el registro (`status = 'firmada'`) y encola el job de generación de PDF en Horizon.
* **`App\Actions\Consultations\AddNoteAmendmentAction`**
  * *Firma:* `public function handle(string $noteId, string $reason, string $content, string $doctorUserId): NoteAmendment`
  * *Propósito:* Registra una anotación modificatoria en `note_amendments` vinculada a la nota firmada inmutable.

---

## 4. Dirección Permitida de Dependencias entre Módulos

El flujo de importaciones de dependencias es estrictamente descendente, prohibiendo ciclos o dependencias cruzadas:

```
[HTTP Controllers]
       │
       ▼
  [Form Requests]
       │
       ▼
   [Actions] (Lógica de Negocio)
       │
       ├────────────────────────┐
       ▼                        ▼
 [Eloquent Models]      [Laravel Policies]
       │
       ▼
[PostgreSQL Database] (Restricción RLS)
```

### Reglas de Dependencias:
1. Un módulo en `Actions` (ej. `Clinical`) **nunca** puede importar o invocar una Action de otro módulo directamente (ej. `Billing`). Lo compartido debe orquestarse en el controlador o delegarse a la cola de eventos.
2. Los `Models` **nunca** importan ni dependen de `Actions` o `Controllers`.
3. Las `Policies` leen el estado de los `Models` para realizar aserciones, pero nunca disparan transacciones o llamadas DML de escritura.
4. `app_runtime` es el único rol que toca las tablas en producción a través de la capa de acceso a datos de Laravel.

---

## 5. Mapeo de Módulos a Ejemplares Canónicos

| Módulo / Archivo a Crear | Ejemplar Canónico de Referencia | Tipo de Archivo |
|---|---|---|
| `app/Http/Requests/*` | [StoreUserRequest.php](file:///c:/Users/marin/OneDrive/Documents/Workspace/projects/AI-Proyecto_11_Telemedicina/agent/ejemplares/laravel/StoreUserRequest.php) | Form Request de Validación |
| `app/Actions/*` | [CreateUserAction.php](file:///c:/Users/marin/OneDrive/Documents/Workspace/projects/AI-Proyecto_11_Telemedicina/agent/ejemplares/laravel/CreateUserAction.php) | Lógica de Negocio (Action) |
| `app/Http/Controllers/*` | [UserController.php](file:///c:/Users/marin/OneDrive/Documents/Workspace/projects/AI-Proyecto_11_Telemedicina/agent/ejemplares/laravel/UserController.php) | Controlador HTTP |
| Componentes Vue (`.vue`) | [UserList.vue](file:///c:/Users/marin/OneDrive/Documents/Workspace/projects/AI-Proyecto_11_Telemedicina/agent/ejemplares/vue/UserList.vue) | Componente Vue (SFC con 4 Estados) |
| Composables Vue (`.ts`) | [useUsers.ts](file:///c:/Users/marin/OneDrive/Documents/Workspace/projects/AI-Proyecto_11_Telemedicina/agent/ejemplares/vue/useUsers.ts) | Composable de Estado / API Client |

---

## 6. Mapeo de la Capa de Autorización y Seguridad

Las defensas declaradas en `docs/AUTHORIZATION.md` se localizan físicamente en los siguientes archivos y componentes de la arquitectura:

* **Middleware de Contexto RLS (`App\Http\Middleware\SetPostgresSessionContext`):**
  * *Ubicación:* `app/Http/Middleware/SetPostgresSessionContext.php`.
  * *Acción:* Intercepta toda petición HTTP autenticada antes de llegar al controlador, inicializando las variables `app.current_user_id` y `app.current_user_role` en la conexión SQL de PostgreSQL.
* **Global Scopes (Eloquent Models):**
  * *Ubicación `ConsultationNote.php`:* Excluye automáticamente notas en estado `draft` si el usuario autenticado tiene el rol `patient`.
  * *Ubicación `Commission.php`:* Filtra implícitamente las comisiones e ingresos para que los médicos visualicen únicamente las vinculadas a su `doctor_id`.
* **Laravel Policies (`App\Policies/*`):**
  * *Ubicación:* `app/Policies/PatientProfilePolicy.php` y `app/Policies/ConsultationNotePolicy.php`.
  * *Acción:* Ejecuta comprobaciones del flujo de negocio (ej. validar existencia de cita confirmada) retornando HTTP 403 antes de ejecutar la consulta a la base de datos.
* **Filtro de Consulta (Join Restrictivo SQL):**
  * *Ubicación:* Repositorio de consulta en `app/Http/Controllers/ClinicalController.php` (métodos de lectura).
  * *Acción:* Fuerza a que toda visualización de expediente se resuelva a través de la relación de la cita `/api/consultations/{consultation_uuid}/patient-file`, garantizando la asociación relacional.
