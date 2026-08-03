# Esquema Técnico de Base de Datos (Gate 2B)

> **Estado:** Propuesta de Diseño para Aprobación
> **Motor:** PostgreSQL 16.0
> **Extensiones requeridas:** `btree_gist` (para restricciones de exclusión combinadas) y `pgcrypto` (para generación nativa de UUIDs).

---

## 1. Configuración de Roles y Seguridad (Opción A - Ampliada con Worker)

Para garantizar la seguridad de Row-Level Security (RLS) sin bloquear las tareas administrativas del CLI, y aislar los procesos en segundo plano de la plataforma, se implementa una arquitectura de **Bifurcación de Tres Conexiones**:

* **`app_owner`:** Rol administrador y dueño de las tablas. No sujeto a RLS por defecto. Ejecuta las migraciones (`php artisan migrate --database=pgsql_owner`) y la siembra de datos (`db:seed`).
* **`app_runtime`:** Rol de runtime limitado utilizado por la aplicación web Laravel (servidor web HTTP). No es dueño de las tablas, no posee privilegios de superusuario ni el atributo `BYPASSRLS`. RLS le aplica de forma natural y obligatoria.
* **`app_worker`:** Rol limitado para tareas en segundo plano (Horizon, comandos Artisan, webhooks de Stripe). No posee privilegios `BYPASSRLS` ni es superusuario. Sus GRANTs están estrictamente limitados a las operaciones mínimas necesarias para los procesos asíncronos y no puede realizar borrados físicos.

### Sentencias SQL de Configuración de Roles (Ejecutadas por Superusuario)
```sql
-- Crear los tres roles de conexión
CREATE ROLE app_owner WITH LOGIN PASSWORD '<ver .env>';
CREATE ROLE app_runtime WITH LOGIN PASSWORD '<ver .env>';
CREATE ROLE app_worker WITH LOGIN PASSWORD '<ver .env>';

-- Otorgar privilegios de esquema al dueño
GRANT ALL PRIVILEGES ON SCHEMA public TO app_owner;

-- Configurar privilegios básicos del runtime y worker sobre el esquema y secuencias (sin UPDATE en secuencias)
GRANT USAGE ON SCHEMA public TO app_runtime, app_worker;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_runtime, app_worker;

-- Seguridad por defecto: toda tabla nueva nace en modo SOLO LECTURA (SELECT) para los roles runtime y worker.
-- Las migraciones de escritura deberán conceder privilegios explícitos mediante sentencias GRANT puntuales.
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO app_runtime, app_worker;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO app_runtime, app_worker;

-- IMPORTANTE: app_runtime y app_worker NO tienen BYPASSRLS ni son superusuarios
ALTER ROLE app_runtime NOBYPASSRLS;
ALTER ROLE app_worker NOBYPASSRLS;
```

---

## 2. SQL Crudo Completo de la Migración Inicial (Con RLS y Privilegios Explícitos)

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
    text_severity      varchar(20) NOT NULL,
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

-- 23. Ficha Longitudinal: Signos Vitales (Reportados por Paciente)
CREATE TABLE vital_signs (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    appointment_id uuid NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
    peso           decimal(5,2) NOT NULL,
    presion        varchar(20) NOT NULL,
    temperatura    decimal(4,2) NOT NULL,
    created_at     timestamptz NOT NULL DEFAULT now()
);

-- 24. Logs de Auditoría
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
-- vital_signs
CREATE INDEX IF NOT EXISTS vital_signs_appointment_id_idx ON vital_signs (appointment_id);
-- audit_logs
CREATE INDEX IF NOT EXISTS audit_logs_user_id_idx ON audit_logs (user_id);
CREATE INDEX IF NOT EXISTS audit_logs_lookup_idx ON audit_logs (table_name, record_id);


-- ====================================================================
-- CONCESIÓN DE PRIVILEGIOS SELECTIVOS TABLA POR TABLA A app_runtime
-- ====================================================================

-- 1. Tablas Clínicas/Transaccionales Inmutables (No UPDATE/DELETE)
GRANT SELECT, INSERT ON audit_logs TO app_runtime;
GRANT SELECT, INSERT ON note_amendments TO app_runtime;
GRANT SELECT, INSERT ON processed_stripe_events TO app_runtime;
GRANT SELECT, INSERT ON vital_signs TO app_runtime;
GRANT SELECT, INSERT ON consultation_messages TO app_runtime;
GRANT SELECT, INSERT ON pre_consultation_forms TO app_runtime;

-- 2. Tablas Clínicas Modificables pero nunca eliminables (SELECT, INSERT, UPDATE, no DELETE)
GRANT SELECT, INSERT, UPDATE ON consultation_notes TO app_runtime;

-- 3. Tablas de Gestión y Perfiles (SELECT, INSERT, UPDATE, no DELETE)
GRANT SELECT, INSERT, UPDATE ON users TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON patient_profiles TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON doctor_profiles TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON appointments TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON payments TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON commissions TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON consultations TO app_runtime;

-- 4. Fichas Longitudinales del Paciente (SELECT, INSERT, UPDATE, no DELETE)
GRANT SELECT, INSERT, UPDATE ON patient_allergies TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON patient_conditions TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON patient_medications TO app_runtime;

-- 5. Tablas Administrativas y de Soporte (Permisos de Gestión completos)
GRANT SELECT, INSERT, DELETE ON doctor_specialties TO app_runtime;
GRANT SELECT, INSERT, DELETE ON user_roles TO app_runtime;
GRANT SELECT, INSERT, DELETE ON user_permissions TO app_runtime;
GRANT SELECT, INSERT, DELETE ON documents TO app_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON schedules TO app_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON schedule_blocks TO app_runtime;

-- 6. Tablas de Configuración (Solo Lectura)
GRANT SELECT ON roles TO app_runtime;
GRANT SELECT ON permissions TO app_runtime;
GRANT SELECT ON specialties TO app_runtime;
GRANT SELECT ON role_permissions TO app_runtime;


-- ====================================================================
-- CONCESIÓN DE PRIVILEGIOS SELECTIVOS TABLA POR TABLA A app_worker
-- ====================================================================

-- 1. Tablas Clínicas e Históricas (Solo lectura / inserción de logs, NUNCA UPDATE ni DELETE)
GRANT SELECT, INSERT ON audit_logs TO app_worker;
GRANT SELECT, INSERT ON processed_stripe_events TO app_worker;
GRANT SELECT ON consultation_notes TO app_worker;
GRANT SELECT ON note_amendments TO app_worker;
GRANT SELECT ON vital_signs TO app_worker;
GRANT SELECT ON consultation_messages TO app_worker;
GRANT SELECT ON pre_consultation_forms TO app_worker;

-- 2. Tablas Transaccionales de Gestión (SELECT y UPDATE selectivos de estado)
GRANT SELECT, UPDATE ON appointments TO app_worker;
GRANT SELECT, UPDATE ON payments TO app_worker;
GRANT SELECT, INSERT, UPDATE ON commissions TO app_worker;
GRANT SELECT, UPDATE ON users TO app_worker;
GRANT SELECT, UPDATE ON doctor_profiles TO app_worker;
GRANT SELECT, UPDATE ON patient_profiles TO app_worker;
GRANT SELECT, UPDATE ON consultations TO app_worker;

-- 3. Lectura de Soporte y Configuraciones
GRANT SELECT ON roles TO app_worker;
GRANT SELECT ON permissions TO app_worker;
GRANT SELECT ON specialties TO app_worker;
GRANT SELECT ON doctor_specialties TO app_worker;
GRANT SELECT ON user_roles TO app_worker;
GRANT SELECT ON user_permissions TO app_worker;
GRANT SELECT ON role_permissions TO app_worker;
GRANT SELECT ON patient_allergies TO app_worker;
GRANT SELECT ON patient_conditions TO app_worker;
GRANT SELECT ON patient_medications TO app_worker;
GRANT SELECT ON schedules TO app_worker;
GRANT SELECT ON schedule_blocks TO app_worker;
GRANT SELECT ON documents TO app_worker;


-- ====================================================================
-- ACTIVACIÓN Y CONFIGURACIÓN DE ROW-LEVEL SECURITY (RLS BIFURCADO)
-- ====================================================================

-- --------------------------------------------------------------------
-- 1. patient_profiles
-- --------------------------------------------------------------------
ALTER TABLE patient_profiles ENABLE ROW LEVEL SECURITY;

CREATE POLICY patient_profiles_select ON patient_profiles
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR current_setting('app.current_user_role', true) = 'admin'
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

CREATE POLICY patient_profiles_insert ON patient_profiles
    FOR INSERT
    WITH CHECK (
        user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        OR current_setting('app.current_user_role', true) = 'agent'
        OR current_setting('app.current_user_role', true) = 'admin'
    );

CREATE POLICY patient_profiles_update ON patient_profiles
    FOR UPDATE
    USING (
        current_user = 'app_worker'
        OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        OR current_setting('app.current_user_role', true) = 'admin'
    )
    WITH CHECK (
        current_user = 'app_worker'
        OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        OR current_setting('app.current_user_role', true) = 'admin'
    );

-- --------------------------------------------------------------------
-- 2. patient_allergies
-- --------------------------------------------------------------------
ALTER TABLE patient_allergies ENABLE ROW LEVEL SECURITY;

CREATE POLICY patient_allergies_select ON patient_allergies
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR EXISTS (
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

CREATE POLICY patient_allergies_insert ON patient_allergies
    FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM patient_profiles p
            WHERE p.id = patient_profile_id
              AND (
                  p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
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

CREATE POLICY patient_allergies_update ON patient_allergies
    FOR UPDATE
    USING (
        EXISTS (
            SELECT 1 FROM patient_profiles p
            WHERE p.id = patient_profile_id
              AND (
                  p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
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

-- --------------------------------------------------------------------
-- 3. appointments
-- --------------------------------------------------------------------
ALTER TABLE appointments ENABLE ROW LEVEL SECURITY;

CREATE POLICY appointments_select ON appointments
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR current_setting('app.current_user_role', true) = 'admin'
        OR current_setting('app.current_user_role', true) = 'agent'
        OR patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        OR doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
    );

CREATE POLICY appointments_insert ON appointments
    FOR INSERT
    WITH CHECK (
        current_setting('app.current_user_role', true) = 'admin'
        OR current_setting('app.current_user_role', true) = 'agent'
        OR patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
    );

CREATE POLICY appointments_update ON appointments
    FOR UPDATE
    USING (
        current_user = 'app_worker'
        OR current_setting('app.current_user_role', true) = 'admin'
        OR current_setting('app.current_user_role', true) = 'agent'
        OR patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        OR doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
    );

-- --------------------------------------------------------------------
-- 4. pre_consultation_forms
-- --------------------------------------------------------------------
ALTER TABLE pre_consultation_forms ENABLE ROW LEVEL SECURITY;

CREATE POLICY pre_consultation_forms_select ON pre_consultation_forms
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.id = appointment_id
              AND (
                  current_setting('app.current_user_role', true) = 'admin'
                  OR a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              )
        )
    );

CREATE POLICY pre_consultation_forms_insert ON pre_consultation_forms
    FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.id = appointment_id
              AND (
                  a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR current_setting('app.current_user_role', true) = 'admin'
              )
        )
    );

-- --------------------------------------------------------------------
-- 5. consultations
-- --------------------------------------------------------------------
ALTER TABLE consultations ENABLE ROW LEVEL SECURITY;

CREATE POLICY consultations_select ON consultations
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR current_setting('app.current_user_role', true) = 'admin'
        OR EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.id = appointment_id
              AND (
                  a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              )
        )
    );

CREATE POLICY consultations_insert ON consultations
    FOR INSERT
    WITH CHECK (
        current_setting('app.current_user_role', true) = 'admin'
        OR EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.id = appointment_id
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    );

-- --------------------------------------------------------------------
-- 6. consultation_messages
-- --------------------------------------------------------------------
ALTER TABLE consultation_messages ENABLE ROW LEVEL SECURITY;

CREATE POLICY consultation_messages_select ON consultation_messages
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_id
              AND (
                  a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              )
        )
    );

CREATE POLICY consultation_messages_insert ON consultation_messages
    FOR INSERT
    WITH CHECK (
        sender_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        AND EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_id
              AND (
                  a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              )
        )
    );

-- --------------------------------------------------------------------
-- 7. consultation_notes
-- --------------------------------------------------------------------
ALTER TABLE consultation_notes ENABLE ROW LEVEL SECURITY;

CREATE POLICY consultation_notes_select ON consultation_notes
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR (
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

CREATE POLICY consultation_notes_insert ON consultation_notes
    FOR INSERT
    WITH CHECK (
        status = 'draft'
        AND EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_id
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    );

CREATE POLICY consultation_notes_update ON consultation_notes
    FOR UPDATE
    USING (
        status = 'draft'
        AND EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_notes.consultation_id
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    )
    WITH CHECK (
        (status = 'draft')
        OR (
            status = 'firmada'
            AND signed_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    );

-- --------------------------------------------------------------------
-- 8. note_amendments
-- --------------------------------------------------------------------
ALTER TABLE note_amendments ENABLE ROW LEVEL SECURITY;

CREATE POLICY note_amendments_select ON note_amendments
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR EXISTS (
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

CREATE POLICY note_amendments_insert ON note_amendments
    FOR INSERT
    WITH CHECK (
        author_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        AND EXISTS (
            SELECT 1 FROM consultation_notes n
            JOIN consultations c ON c.id = n.consultation_id
            JOIN appointments a ON a.id = c.appointment_id
            WHERE n.id = consultation_note_id
              AND n.status = 'firmada'
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    );

-- --------------------------------------------------------------------
-- 9. documents
-- --------------------------------------------------------------------
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;

CREATE POLICY documents_select ON documents
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR current_setting('app.current_user_role', true) = 'admin'
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

CREATE POLICY documents_insert ON documents
    FOR INSERT
    WITH CHECK (
        uploaded_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        AND EXISTS (
            SELECT 1 FROM consultations c
            JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = consultation_id
              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
        )
    );

CREATE POLICY documents_delete ON documents
    FOR DELETE
    USING (
        uploaded_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid
    );

-- --------------------------------------------------------------------
-- 10. vital_signs
-- --------------------------------------------------------------------
ALTER TABLE vital_signs ENABLE ROW LEVEL SECURITY;

CREATE POLICY vital_signs_select ON vital_signs
    FOR SELECT
    USING (
        current_user = 'app_worker'
        OR current_setting('app.current_user_role', true) = 'admin'
        OR EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.id = appointment_id
              AND (
                  a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
              )
        )
    );

CREATE POLICY vital_signs_insert ON vital_signs
    FOR INSERT
    WITH CHECK (
        EXISTS (
            SELECT 1 FROM appointments a
            WHERE a.id = appointment_id
              AND (
                  a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                  OR current_setting('app.current_user_role', true) = 'admin'
              )
        )
    );
```

