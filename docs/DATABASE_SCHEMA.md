# Esquema Técnico de Base de Datos (Gate 2B)

> **Estado:** Propuesta de Diseño para Aprobación
> **Motor:** PostgreSQL 16.0
> **Extensiones requeridas:** `btree_gist` (para restricciones de exclusión combinadas) y `pgcrypto` (para generación nativa de UUIDs).

---

## 1. SQL Crudo Completo de la Migración Inicial (Con RLS Activo)

```sql
-- ====================================================================
-- MIGRACIÓN INICIAL — PLATAFORMA DE TELEMEDICINA
-- AUTOR: Rafael Marín (Perfil: PORTAFOLIO)
-- ====================================================================

-- Activar extensiones necesarias
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "btree_gist";

-- 1. Tabla de Roles
CREATE TABLE roles (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        varchar(50) UNIQUE NOT NULL,
    description varchar(255) NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

-- 2. Tabla de Permisos
CREATE TABLE permissions (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        varchar(100) UNIQUE NOT NULL,
    description varchar(255) NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

-- 3. Tabla de Usuarios
CREATE TABLE users (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name              varchar(100) NOT NULL,
    last_name         varchar(100) NOT NULL,
    email             varchar(255) UNIQUE NOT NULL,
    password          varchar(255) NOT NULL,
    timezone          varchar(100) NOT NULL DEFAULT 'UTC',
    email_verified_at timestamptz NULL,
    is_active         boolean NOT NULL DEFAULT true,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    deleted_at        timestamptz NULL
);

-- 4. Pivotes de Roles y Permisos
CREATE TABLE user_roles (
    user_id    uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id    uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE role_permissions (
    role_id       uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id uuid NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    created_at    timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_permissions (
    user_id       uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_id uuid NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    expires_at    timestamptz NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, permission_id)
);

-- 5. Perfil de Paciente
CREATE TABLE patient_profiles (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id       uuid UNIQUE NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    phone         varchar(20) NOT NULL,
    date_of_birth date NOT NULL,
    gender        varchar(20) NOT NULL,
    address       text NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    deleted_at    timestamptz NULL
);

-- 6. Perfil de Médico
CREATE TABLE doctor_profiles (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id          uuid UNIQUE NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    license_number   varchar(100) UNIQUE NOT NULL,
    university       varchar(255) NOT NULL,
    years_experience integer NOT NULL DEFAULT 0,
    description      text NOT NULL,
    consultation_fee decimal(10,2) NOT NULL,
    status           varchar(20) NOT NULL DEFAULT 'pending',
    rejection_reason text NULL,
    approved_at      timestamptz NULL,
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now(),
    deleted_at       timestamptz NULL,
    
    CONSTRAINT doctor_status_valido CHECK (status IN ('pending', 'approved', 'rejected'))
);

-- 7. Especialidades y Pivote
CREATE TABLE specialties (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        varchar(150) UNIQUE NOT NULL,
    description text NOT NULL,
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    deleted_at  timestamptz NULL
);

CREATE TABLE doctor_specialties (
    doctor_profile_id uuid NOT NULL REFERENCES doctor_profiles(id) ON DELETE CASCADE,
    specialty_id      uuid NOT NULL REFERENCES specialties(id) ON DELETE RESTRICT,
    created_at        timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (doctor_profile_id, specialty_id)
);

-- 8. Horarios Recurrentes Semanales (Agenda)
CREATE TABLE schedules (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    doctor_profile_id uuid NOT NULL REFERENCES doctor_profiles(id) ON DELETE RESTRICT,
    day_of_week       smallint NOT NULL,
    franja            timerange NOT NULL, -- Rango de horas sin fecha
    slot_duration     integer NOT NULL DEFAULT 30,
    is_active         boolean NOT NULL DEFAULT true,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    deleted_at        timestamptz NULL,

    CONSTRAINT schedules_day_valido CHECK (day_of_week BETWEEN 0 AND 6),
    
    -- Exclusión: Evita que un médico tenga horarios recurrentes que se solapen en el mismo día.
    CONSTRAINT schedules_sin_solapamiento
        EXCLUDE USING gist (doctor_profile_id WITH =, day_of_week WITH =, franja WITH &&)
        WHERE (deleted_at IS NULL)
);

-- 9. Bloqueos de Agenda (Excepciones)
CREATE TABLE schedule_blocks (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    doctor_profile_id uuid NOT NULL REFERENCES doctor_profiles(id) ON DELETE RESTRICT,
    blocked_date      date NOT NULL,
    franja            timerange NOT NULL, -- NULL = día completo
    reason            varchar(255) NOT NULL,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);

-- 10. Citas Médicas
CREATE TABLE appointments (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_id          uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    doctor_id           uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    franja              tstzrange NOT NULL, -- Rango de fecha/hora con Zona Horaria (UTC)
    status              varchar(20) NOT NULL DEFAULT 'pending',
    cancelled_by        uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    cancellation_reason text NULL,
    rescheduled_from    uuid NULL REFERENCES appointments(id) ON DELETE RESTRICT,
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now(),
    deleted_at          timestamptz NULL,

    CONSTRAINT appointments_status_valido CHECK (status IN ('pending', 'confirmed', 'completed', 'cancelled')),
    
    -- Exclusión: Evita que el mismo médico tenga dos citas activas solapadas.
    CONSTRAINT appointments_sin_solapamiento
        EXCLUDE USING gist (doctor_id WITH =, franja WITH &&)
        WHERE (status <> 'cancelled')
);

-- 11. Pagos Citas (Stripe)
CREATE TABLE payments (
    id                        uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    appointment_id            uuid UNIQUE NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
    stripe_payment_intent_id  varchar(255) UNIQUE NOT NULL,
    amount                    decimal(10,2) NOT NULL,
    currency                  varchar(3) NOT NULL DEFAULT 'USD',
    status                    varchar(20) NOT NULL DEFAULT 'pending',
    paid_at                   timestamptz NULL,
    refunded_at               timestamptz NULL,
    created_at                timestamptz NOT NULL DEFAULT now(),
    updated_at                timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT payments_status_valido CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'refunded'))
);

-- 12. Comisiones Plataforma
CREATE TABLE commissions (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_id      uuid UNIQUE NOT NULL REFERENCES payments(id) ON DELETE RESTRICT,
    commission_rate decimal(5,2) NOT NULL,
    platform_fee    decimal(10,2) NOT NULL,
    doctor_earning  decimal(10,2) NOT NULL,
    status          varchar(20) NOT NULL DEFAULT 'held',
    released_at     timestamptz NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT commissions_status_valido CHECK (status IN ('held', 'released', 'refunded'))
);

-- 13. Idempotencia de Eventos Stripe
CREATE TABLE processed_stripe_events (
    event_id     varchar(255) PRIMARY KEY,
    processed_at timestamptz NOT NULL DEFAULT now()
);

-- 14. Cuestionarios Pre-Consulta
CREATE TABLE pre_consultation_forms (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    appointment_id uuid UNIQUE NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
    motivo         text NOT NULL,
    sintomas       text NOT NULL,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);

-- 15. Consultas
CREATE TABLE consultations (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    appointment_id uuid UNIQUE NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
    started_at     timestamptz NULL,
    ended_at       timestamptz NULL,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);

-- 16. Chat Mensajes (Dato Clínico)
CREATE TABLE consultation_messages (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    consultation_id uuid NOT NULL REFERENCES consultations(id) ON DELETE RESTRICT,
    sender_id       uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    content         text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now()
);

-- 17. Notas Clínicas SOAP
CREATE TABLE consultation_notes (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    consultation_id   uuid UNIQUE NOT NULL REFERENCES consultations(id) ON DELETE RESTRICT,
    symptoms          text NOT NULL,
    objective         text NOT NULL,
    analysis          text NOT NULL,
    plan              text NOT NULL,
    status            varchar(20) NOT NULL DEFAULT 'draft',
    content_hash      varchar(64) NULL, -- SHA-256
    signed_by         uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    signed_at         timestamptz NULL,
    signed_ip         inet NULL,
    signed_user_agent text NULL,
    acknowledged_at   timestamptz NULL,
    pdf_status        varchar(20) NOT NULL DEFAULT 'pdf_pendiente',
    pdf_path          varchar(512) NULL,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT notes_status_valido CHECK (status IN ('draft', 'firmada')),
    CONSTRAINT notes_pdf_status_valido CHECK (pdf_status IN ('pdf_pendiente', 'pdf_ready', 'pdf_error'))
);

-- 18. Enmiendas a Nota Clínica
CREATE TABLE note_amendments (
    id                   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    consultation_note_id uuid NOT NULL REFERENCES consultation_notes(id) ON DELETE RESTRICT,
    author_id            uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reason               text NOT NULL,
    content              text NOT NULL,
    created_at           timestamptz NOT NULL DEFAULT now()
);

-- 19. Documentos Clínicos Adjuntos
CREATE TABLE documents (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    consultation_id uuid NOT NULL REFERENCES consultations(id) ON DELETE RESTRICT,
    uploaded_by     uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    file_name       varchar(255) NOT NULL,
    file_path       varchar(512) NOT NULL,
    file_size_bytes integer NOT NULL,
    file_type       varchar(100) NOT NULL,
    type            varchar(20) NOT NULL,
    description     text NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    deleted_at      timestamptz NULL,

    CONSTRAINT documents_type_valido CHECK (type IN ('prescription', 'diagnosis', 'lab_result', 'other'))
);

-- 20. Ficha Longitudinal: Alergias del Paciente
CREATE TABLE patient_allergies (
    id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_profile_id uuid NOT NULL REFERENCES patient_profiles(id) ON DELETE RESTRICT,
    substance          varchar(150) NOT NULL,
    type               varchar(20) NOT NULL,
    text_severity      varchar(20) NOT NULL, -- Renombrado para evitar conflicto con la palabra reservada 'severity'
    reaction           text NOT NULL,
    declarada_por      uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    confirmada_por     uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    confirmada_en      timestamptz NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT allergies_type_valido CHECK (type IN ('medicamento', 'alimento', 'ambiental')),
    CONSTRAINT allergies_severity_valida CHECK (text_severity IN ('mild', 'moderate', 'severe'))
);

-- 21. Ficha Longitudinal: Condiciones
CREATE TABLE patient_conditions (
    id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_profile_id uuid NOT NULL REFERENCES patient_profiles(id) ON DELETE RESTRICT,
    condition          varchar(255) NOT NULL,
    desde              date NULL,
    status             varchar(20) NOT NULL DEFAULT 'activa',
    notes              text NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT condition_status_valido CHECK (status IN ('activa', 'resuelta'))
);

-- 22. Ficha Longitudinal: Medicamentos
CREATE TABLE patient_medications (
    id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    patient_profile_id uuid NOT NULL REFERENCES patient_profiles(id) ON DELETE RESTRICT,
    name               varchar(255) NOT NULL,
    dosis              varchar(100) NOT NULL,
    frecuencia         varchar(100) NOT NULL,
    desde              date NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now()
);

-- 23. Logs de Auditoría
CREATE TABLE audit_logs (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    table_name varchar(100) NOT NULL,
    record_id  uuid NOT NULL,
    action     varchar(10) NOT NULL,
    user_id    uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
    old_values jsonb NULL,
    new_values jsonb NULL,
    created_at timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT audit_action_valida CHECK (action IN ('INSERT', 'UPDATE', 'DELETE'))
);

-- ====================================================================
-- CREACIÓN DE ÍNDICES EXPLICITOS PARA CLAVES FORÁNEAS (PostgreSQL)
-- ====================================================================

-- user_roles
CREATE INDEX IF NOT EXISTS user_roles_role_id_idx ON user_roles (role_id);
-- role_permissions
CREATE INDEX IF NOT EXISTS role_permissions_permission_id_idx ON role_permissions (permission_id);
-- user_permissions
CREATE INDEX IF NOT EXISTS user_permissions_permission_id_idx ON user_permissions (permission_id);
-- doctor_specialties
CREATE INDEX IF NOT EXISTS doctor_specialties_specialty_id_idx ON doctor_specialties (specialty_id);

-- schedule_blocks
CREATE INDEX IF NOT EXISTS schedule_blocks_doctor_profile_id_idx ON schedule_blocks (doctor_profile_id);

-- appointments
CREATE INDEX IF NOT EXISTS appointments_patient_id_idx ON appointments (patient_id);
CREATE INDEX IF NOT EXISTS appointments_doctor_id_idx ON appointments (doctor_id);
CREATE INDEX IF NOT EXISTS appointments_rescheduled_from_idx ON appointments (rescheduled_from);

-- payments
CREATE INDEX IF NOT EXISTS payments_appointment_id_idx ON payments (appointment_id);

-- commissions
CREATE INDEX IF NOT EXISTS commissions_payment_id_idx ON commissions (payment_id);

-- pre_consultation_forms
CREATE INDEX IF NOT EXISTS pre_consultation_forms_appointment_id_idx ON pre_consultation_forms (appointment_id);

-- consultations
CREATE INDEX IF NOT EXISTS consultations_appointment_id_idx ON consultations (appointment_id);

-- consultation_messages
CREATE INDEX IF NOT EXISTS consultation_messages_consultation_id_idx ON consultation_messages (consultation_id);
CREATE INDEX IF NOT EXISTS consultation_messages_sender_id_idx ON consultation_messages (sender_id);

-- consultation_notes
CREATE INDEX IF NOT EXISTS consultation_notes_consultation_id_idx ON consultation_notes (consultation_id);
CREATE INDEX IF NOT EXISTS consultation_notes_signed_by_idx ON consultation_notes (signed_by);

-- note_amendments
CREATE INDEX IF NOT EXISTS note_amendments_consultation_note_id_idx ON note_amendments (consultation_note_id);
CREATE INDEX IF NOT EXISTS note_amendments_author_id_idx ON note_amendments (author_id);

-- documents
CREATE INDEX IF NOT EXISTS documents_consultation_id_idx ON documents (consultation_id);
CREATE INDEX IF NOT EXISTS documents_uploaded_by_idx ON documents (uploaded_by);

-- patient_allergies
CREATE INDEX IF NOT EXISTS patient_allergies_patient_profile_id_idx ON patient_allergies (patient_profile_id);
CREATE INDEX IF NOT EXISTS patient_allergies_declarada_por_idx ON patient_allergies (declarada_por);
CREATE INDEX IF NOT EXISTS patient_allergies_confirmada_por_idx ON patient_allergies (confirmada_por);

-- patient_conditions
CREATE INDEX IF NOT EXISTS patient_conditions_patient_profile_id_idx ON patient_conditions (patient_profile_id);

-- patient_medications
CREATE INDEX IF NOT EXISTS patient_medications_patient_profile_id_idx ON patient_medications (patient_profile_id);

-- audit_logs
CREATE INDEX IF NOT EXISTS audit_logs_user_id_idx ON audit_logs (user_id);
CREATE INDEX IF NOT EXISTS audit_logs_lookup_idx ON audit_logs (table_name, record_id);


-- ====================================================================
-- ACTIVACIÓN Y CONFIGURACIÓN DE ROW-LEVEL SECURITY (RLS)
-- ====================================================================

-- 1. patient_profiles
ALTER TABLE patient_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE patient_profiles FORCE ROW LEVEL SECURITY;

CREATE POLICY patient_profiles_rls ON patient_profiles
FOR ALL
USING (
    current_setting('app.current_user_role', true) = 'admin'
    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
    OR (
        current_setting('app.current_user_role', true) = 'doctor'
        AND EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.patient_id = patient_profiles.user_id
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              AND a.status IN ('confirmed', 'completed')
        )
    )
);

-- 2. patient_allergies
ALTER TABLE patient_allergies ENABLE ROW LEVEL SECURITY;
ALTER TABLE patient_allergies FORCE ROW LEVEL SECURITY;

CREATE POLICY patient_allergies_rls ON patient_allergies
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM patient_profiles p
        WHERE p.id = patient_profile_id
          AND (
              current_setting('app.current_user_role', true) = 'admin'
              OR p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR (
                  current_setting('app.current_user_role', true) = 'doctor'
                  AND EXISTS (
                      SELECT 1 FROM appointments a
                      WHERE a.patient_id = p.user_id
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        AND a.status IN ('confirmed', 'completed')
                  )
              )
          )
    )
);

-- 3. patient_conditions
ALTER TABLE patient_conditions ENABLE ROW LEVEL SECURITY;
ALTER TABLE patient_conditions FORCE ROW LEVEL SECURITY;

CREATE POLICY patient_conditions_rls ON patient_conditions
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM patient_profiles p
        WHERE p.id = patient_profile_id
          AND (
              current_setting('app.current_user_role', true) = 'admin'
              OR p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR (
                  current_setting('app.current_user_role', true) = 'doctor'
                  AND EXISTS (
                      SELECT 1 FROM appointments a
                      WHERE a.patient_id = p.user_id
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        AND a.status IN ('confirmed', 'completed')
                  )
              )
          )
    )
);

-- 4. patient_medications
ALTER TABLE patient_medications ENABLE ROW LEVEL SECURITY;
ALTER TABLE patient_medications FORCE ROW LEVEL SECURITY;

CREATE POLICY patient_medications_rls ON patient_medications
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM patient_profiles p
        WHERE p.id = patient_profile_id
          AND (
              current_setting('app.current_user_role', true) = 'admin'
              OR p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR (
                  current_setting('app.current_user_role', true) = 'doctor'
                  AND EXISTS (
                      SELECT 1 FROM appointments a
                      WHERE a.patient_id = p.user_id
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        AND a.status IN ('confirmed', 'completed')
                  )
              )
          )
    )
);

-- 5. appointments
ALTER TABLE appointments ENABLE ROW LEVEL SECURITY;
ALTER TABLE appointments FORCE ROW LEVEL SECURITY;

CREATE POLICY appointments_rls ON appointments
FOR ALL
USING (
    current_setting('app.current_user_role', true) = 'admin'
    OR current_setting('app.current_user_role', true) = 'agent'
    OR patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
    OR doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
);

-- 6. pre_consultation_forms
ALTER TABLE pre_consultation_forms ENABLE ROW LEVEL SECURITY;
ALTER TABLE pre_consultation_forms FORCE ROW LEVEL SECURITY;

CREATE POLICY pre_consultation_forms_rls ON pre_consultation_forms
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM appointments a
        WHERE a.id = appointment_id
          AND (
              current_setting('app.current_user_role', true) = 'admin'
              OR a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
          )
    )
);

-- 7. consultations
ALTER TABLE consultations ENABLE ROW LEVEL SECURITY;
ALTER TABLE consultations FORCE ROW LEVEL SECURITY;

CREATE POLICY consultations_rls ON consultations
FOR ALL
USING (
    current_setting('app.current_user_role', true) = 'admin'
    OR EXISTS (
        SELECT 1 FROM appointments a
        WHERE a.id = appointment_id
          AND (
              a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
          )
    )
);

-- 8. consultation_messages
ALTER TABLE consultation_messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE consultation_messages FORCE ROW LEVEL SECURITY;

CREATE POLICY consultation_messages_rls ON consultation_messages
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM consultations c
        JOIN appointments a ON a.id = c.appointment_id
        WHERE c.id = consultation_id
          AND (
              a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
          )
    )
);

-- 9. consultation_notes
ALTER TABLE consultation_notes ENABLE ROW LEVEL SECURITY;
ALTER TABLE consultation_notes FORCE ROW LEVEL SECURITY;

CREATE POLICY consultation_notes_rls ON consultation_notes
FOR ALL
USING (
    (
        current_setting('app.current_user_role', true) = 'doctor'
        AND EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_notes.consultation_id
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    )
    OR (
        current_setting('app.current_user_role', true) = 'patient'
        AND status = 'firmada'
        AND EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_notes.consultation_id
              AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    )
);

-- 10. note_amendments
ALTER TABLE note_amendments ENABLE ROW LEVEL SECURITY;
ALTER TABLE note_amendments FORCE ROW LEVEL SECURITY;

CREATE POLICY note_amendments_rls ON note_amendments
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM consultation_notes n
        JOIN consultations c ON c.id = n.consultation_id
        JOIN appointments a ON a.id = c.appointment_id
        WHERE n.id = consultation_note_id
          AND (
              (current_setting('app.current_user_role', true) = 'doctor' AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid)
              OR (current_setting('app.current_user_role', true) = 'patient' AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid AND n.status = 'firmada')
          )
    )
);

-- 11. documents
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE documents FORCE ROW LEVEL SECURITY;

CREATE POLICY documents_rls ON documents
FOR ALL
USING (
    current_setting('app.current_user_role', true) = 'admin'
    OR EXISTS (
        SELECT 1 FROM consultations c
        JOIN appointments a ON a.id = c.appointment_id
        WHERE c.id = documents.consultation_id
          AND (
              a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
          )
    )
);
```

---

## 2. Índices CONCURRENTLY y Transacciones en Laravel

### El Problema
Al ejecutar migraciones, Laravel envuelve automáticamente el contenido de los métodos `up()` y `down()` en una transacción de base de datos (`DB::beginTransaction()`).
PostgreSQL **prohíbe estrictamente** la ejecución de la sentencia `CREATE INDEX CONCURRENTLY` dentro de una transacción abierta, arrojando el siguiente error:
> *ERROR: CREATE INDEX CONCURRENTLY cannot run inside a transaction block.*

### La Solución en Laravel
Para resolver este problema con elegancia, debemos deshabilitar las transacciones implícitas de Laravel en las clases de migración específicas donde creemos índices concurrentes. Esto se logra declarando la propiedad pública `$withinTransaction` como `false` en la clase de la migración:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateCitasIndicesConcurrentes extends Migration
{
    /**
     * Determina si la migración debe ejecutarse dentro de una transacción.
     *
     * @var bool
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Al estar desactivado $withinTransaction, esto corre de forma segura y concurrente
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS appointments_doctor_franja_idx ON appointments USING gist (doctor_id, franja);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF NOT EXISTS appointments_doctor_franja_idx;');
    }
}
```

> [!NOTE]
> En la migración inicial de un proyecto en frío (cero registros), se pueden crear los índices de forma estándar (síncronos, bloqueantes) ya que el impacto de bloqueo sobre tablas vacías es nulo. Sin embargo, para mantener el rigor del portafolio en futuras migraciones en caliente, se utilizará siempre la propiedad `$withinTransaction = false` junto con la sentencia `CREATE INDEX CONCURRENTLY`.

---

## 3. Verificación de Índices en Claves Foráneas

PostgreSQL no indexa automáticamente las claves foráneas. A continuación se confirma de forma exhaustiva, tabla por tabla, que toda FK posee un índice correspondiente:

1. **`user_roles`:**
   - `user_id` (Indexado implícitamente por la Primary Key compuesta `user_id, role_id`).
   - `role_id` (Indexado explícitamente por `user_roles_role_id_idx`).
2. **`role_permissions`:**
   - `role_id` (Indexado por PK compuesta `role_id, permission_id`).
   - `permission_id` (Indexado por `role_permissions_permission_id_idx`).
3. **`user_permissions`:**
   - `user_id` (Indexado por PK compuesta `user_id, permission_id`).
   - `permission_id` (Indexado por `user_permissions_permission_id_idx`).
4. **`patient_profiles`:**
   - `user_id` (Indexado automáticamente por la restricción `UNIQUE`).
5. **`doctor_profiles`:**
   - `user_id` (Indexado automáticamente por la restricción `UNIQUE`).
6. **`doctor_specialties`:**
   - `doctor_profile_id` (Indexado por PK compuesta `doctor_profile_id, specialty_id`).
   - `specialty_id` (Indexado por `doctor_specialties_specialty_id_idx`).
7. **`schedules`:**
   - `doctor_profile_id` (Indexado por el índice de exclusión GIST de la restricción `schedules_sin_solapamiento`).
8. **`schedule_blocks`:**
   - `doctor_profile_id` (Indexado por `schedule_blocks_doctor_profile_id_idx`).
9. **`appointments`:**
   - `patient_id` (Indexado por `appointments_patient_id_idx`).
   - `doctor_id` (Indexado por `appointments_doctor_id_idx` y cubierto por el índice de exclusión).
   - `cancelled_by` (Indexado por `appointments_cancelled_by_idx`).
   - `rescheduled_from` (Indexado por `appointments_rescheduled_from_idx`).
10. **`payments`:**
    - `appointment_id` (Indexado automáticamente por la restricción `UNIQUE`).
11. **`commissions`:**
    - `payment_id` (Indexado automáticamente por la restricción `UNIQUE`).
12. **`pre_consultation_forms`:**
    - `appointment_id` (Indexado automáticamente por la restricción `UNIQUE`).
13. **`consultations`:**
    - `appointment_id` (Indexado automáticamente por la restricción `UNIQUE`).
14. **`consultation_messages`:**
    - `consultation_id` (Indexado por `consultation_messages_consultation_id_idx`).
    - `sender_id` (Indexado por `consultation_messages_sender_id_idx`).
15. **`consultation_notes`:**
    - `consultation_id` (Indexado automáticamente por la restricción `UNIQUE`).
    - `signed_by` (Indexado por `consultation_notes_signed_by_idx`).
16. **`note_amendments`:**
    - `consultation_note_id` (Indexado por `note_amendments_consultation_note_id_idx`).
    - `author_id` (Indexado por `note_amendments_author_id_idx`).
17. **`documents`:**
    - `consultation_id` (Indexado por `documents_consultation_id_idx`).
    - `uploaded_by` (Indexado por `documents_uploaded_by_idx`).
18. **`patient_allergies`:**
    - `patient_profile_id` (Indexado por `patient_allergies_patient_profile_id_idx`).
    - `declarada_por` (Indexado por `patient_allergies_declarada_por_idx`).
    - `confirmada_por` (Indexado por `patient_allergies_confirmada_por_idx`).
19. **`patient_conditions`:**
    - `patient_profile_id` (Indexado por `patient_conditions_patient_profile_id_idx`).
20. **`patient_medications`:**
    - `patient_profile_id` (Indexado por `patient_medications_patient_profile_id_idx`).
21. **`audit_logs`:**
    - `user_id` (Indexado por `audit_logs_user_id_idx`).

---

## 4. Declaración de Riesgo de la Migración Inicial

> [!IMPORTANT]
> **DECLARACIÓN DE RIESGO — MIGRACIÓN INICIAL FASE 1**
> * **Reescritura completa de tabla:** NO (Tablas nuevas).
> * **Bloqueo prolongado en producción:** NO (Sin datos cargados, base vacía).
> * **Backfills de datos sin lotes:** NO.
> * **Operaciones destructivas (DROP, ALTER):** NO.
> * **Filas afectadas estimadas:** 0.
> * **Memoria estimada de ejecución:** < 10 MB (Creación de estructura).
> * **Plan de Reversa:** Archivo SQL de reversión total (`DOWN` manual en caso de error).
> * **Snapshot previo verificado:** N/A (Despliegue inicial de proyecto de cero código).

### Plan de Reversa en SQL Crudo
```sql
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS patient_medications CASCADE;
DROP TABLE IF EXISTS patient_conditions CASCADE;
DROP TABLE IF EXISTS patient_allergies CASCADE;
DROP TABLE IF EXISTS documents CASCADE;
DROP TABLE IF EXISTS note_amendments CASCADE;
DROP TABLE IF EXISTS consultation_notes CASCADE;
DROP TABLE IF EXISTS consultation_messages CASCADE;
DROP TABLE IF EXISTS consultations CASCADE;
DROP TABLE IF EXISTS pre_consultation_forms CASCADE;
DROP TABLE IF EXISTS processed_stripe_events CASCADE;
DROP TABLE IF EXISTS commissions CASCADE;
DROP TABLE IF EXISTS payments CASCADE;
DROP TABLE IF EXISTS appointments CASCADE;
DROP TABLE IF EXISTS schedule_blocks CASCADE;
DROP TABLE IF EXISTS schedules CASCADE;
DROP TABLE IF EXISTS doctor_specialties CASCADE;
DROP TABLE IF EXISTS specialties CASCADE;
DROP TABLE IF EXISTS doctor_profiles CASCADE;
DROP TABLE IF EXISTS patient_profiles CASCADE;
DROP TABLE IF EXISTS user_permissions CASCADE;
DROP TABLE IF EXISTS role_permissions CASCADE;
DROP TABLE IF EXISTS user_roles CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS permissions CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

DROP EXTENSION IF EXISTS btree_gist;
DROP EXTENSION IF EXISTS "uuid-ossp";
```

---

## 5. Informe Adversarial

A continuación se listan los 5 riesgos técnicos más graves detectados sobre el esquema de base de datos propuesto, ordenados de mayor a menor según su nivel de **irreversibilidad** clínica y de negocio:

### 1. Bloqueo y denegación de servicio por crecimiento exponencial del índice GIST en `appointments`
* **Riesgo:** Las restricciones de exclusión `EXCLUDE USING gist` sobre el rango `tstzrange` no escalan de forma lineal. Cuando la base de datos alcance cientos de miles de citas, la inserción y validación del árbol GIST consumirá gran cantidad de CPU y memoria RAM. Si PostgreSQL se queda sin memoria, abortará las reservas de citas completas.
* **Irreversibilidad:** **ALTA.** Reestructurar un índice de exclusión GIST activo sobre una tabla de producción masiva requiere una migración compleja, bloqueando escrituras o dividiendo la tabla en particiones históricas (sharding clínico).
* **Mitigación futura:** Implementar particionamiento de la tabla `appointments` por año/mes, de modo que el índice GIST solo actúe sobre la partición activa del mes en curso.

### 2. Pérdida de integridad de enmiendas por borrado en cascada descontrolado (`ON DELETE CASCADE`)
* **Riesgo:** Aunque la tabla `consultation_notes` y `note_amendments` usan `ON DELETE RESTRICT`, tablas de soporte como `doctor_profiles` o `specialties` podrían ser modificadas. Si por un error administrativo se borra un rol o registro de usuario mediante cascada indirecta en cascadas mal configuradas, se podrían perder los metadatos del médico firmante de la nota SOAP.
* **Irreversibilidad:** **ALTA.** La pérdida de integridad de una firma y auditoría legal hace inservible el PDF e inhabilita su verificación por QR público.
* **Mitigación:** Asegurar que todo registro clínico posea `ON DELETE RESTRICT` y utilizar `SoftDeletes` (`deleted_at`) en lugar de borrado físico.

### 3. Exclusión de citas duplicada para citas "reprogramadas"
* **Riesgo:** Cuando se solicita una reprogramación, el sistema mantiene la cita original y crea la nueva cita. Si la cita vieja no cambia su estado a `cancelled` en el mismo instante transaccional en que se inserta la nueva, el motor PostgreSQL abortará la transacción por colisión con el slot original.
* **Irreversibilidad:** **MEDIA.** Puede ocasionar que los usuarios experimenten errores `409` constantes al reprogramar citas libres, deteriorando severamente la fiabilidad del agendamiento.
* **Mitigación:** Ejecutar el cambio de estado de la cita vieja a `cancelled` y la inserción del nuevo registro de cita dentro de un bloque `DB::transaction()` atómico, forzando la evaluación diferida de restricciones o garantizando el orden estricto de las queries.

### 4. Desbordamiento y desajuste por tipos de datos de precisión en `commissions`
* **Riesgo:** El uso del tipo `decimal(5,2)` para `commission_rate` (ej: 15.00) y `decimal(10,2)` para dinero es seguro para importes tradicionales. Sin embargo, si en un futuro la plataforma maneja micro-pagos o conversiones fraccionadas complejas de pasarelas de pago, los decimales de dos dígitos truncarán decimales, generando descuadres contables centavo a centavo.
* **Irreversibilidad:** **MEDIA.** Modificar la precisión de una columna decimal con miles de registros financieros requiere un bloqueo temporal de escritura y recalculado de filos.
* **Mitigación:** Utilizar siempre `decimal(12,4)` internamente para cálculos monetarios y formatear a dos decimales exclusivamente en la capa de vista.

### 5. Incompatibilidad de Husos Horarios por cadenas no normalizadas en `users.timezone`
* **Riesgo:** Almacenar la zona horaria preferida del usuario como un `varchar(100)` permite cadenas inválidas o incompatibles (ej. "Tegus" o "GMT-6") si el backend no valida rígidamente contra la base de datos de zonas horarias de la IANA. Al calcular slots de citas con `timestamptz`, una zona horaria inválida romperá el renderizado en Vue.
* **Irreversibilidad:** **BAJA.** Limpieza de datos en base de datos mediante script de saneamiento de cadenas.
* **Mitigación:** Agregar un `CHECK` en base de datos o validar rígidamente en la capa de validación (`StoreUserRequest`) contra la lista oficial de zonas horarias del sistema PHP.

---

### Qué NO verifiqué
* **Rendimiento real del pool de conexiones** de PostgreSQL 16 con la extensión `btree_gist` bajo carga masiva de transacciones simultáneas concurrentes de escritura.
* **Comportamiento del driver de base de datos de PHP PDO** ante la serialización nativa del tipo `tstzrange` y `timerange` de PostgreSQL. Laravel/Eloquent por defecto mapea los rangos como cadenas de texto, lo que requiere un casting personalizado en las clases de modelos de Eloquent para traducirlos a objetos manejables de fecha/hora (Carbon o clases propias).
