-- ====================================================================
-- EJEMPLAR CANÓNICO — Migración con declaración de riesgo (Gate 2B)
-- AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
-- ====================================================================
--
-- QUÉ FIJA ESTE EJEMPLAR
-- Cómo se PRESENTA una migración para aprobación. No es solo el SQL: es el SQL
-- más la declaración de riesgo. Sin la declaración, el Gate 2B no se aprueba.
--
-- En Prisma este archivo es prisma/migrations/<fecha>/migration.sql, y se puede
-- EDITAR antes de aplicarlo — que es la única forma de agregar cosas que Prisma
-- no sabe expresar (EXCLUDE, índices CONCURRENTLY, extensiones).

-- ┌──────────────────────────────────────────────────────────────────┐
-- │ DECLARACIÓN DE RIESGO — obligatoria, la escribe la IA            │
-- ├──────────────────────────────────────────────────────────────────┤
-- │ Reescritura completa de tabla ....... NO                          │
-- │ Bloqueo prolongado .................. NO (índices CONCURRENTLY)   │
-- │ Backfill sin lotes .................. NO                          │
-- │ Operación destructiva ............... NO                          │
-- │ Filas afectadas estimadas ........... 0 (tabla nueva)             │
-- │ Memoria estimada .................... < 50 MB                     │
-- │ Reversa ............................. DROP TABLE citas            │
-- │ Snapshot previo verificado ..........  [ ] marcar antes de aplicar│
-- └──────────────────────────────────────────────────────────────────┘

-- Requisito para poder combinar igualdad con solapamiento en un EXCLUDE.
-- Sin esta extensión, la restricción de más abajo falla.
CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE TABLE citas (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    doctor_id   uuid NOT NULL REFERENCES doctores(id) ON DELETE RESTRICT,
    paciente_id uuid NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    franja      tstzrange NOT NULL,
    estado      text NOT NULL DEFAULT 'solicitada',
    created_at  timestamptz NOT NULL DEFAULT now(),

    -- Estados válidos en el esquema, no solo en el código: el patrón State
    -- respaldado por la base de datos.
    CONSTRAINT citas_estado_valido
        CHECK (estado IN ('solicitada','confirmada','en_curso','finalizada','cancelada')),

    -- ★ LA LÍNEA QUE IMPORTA ★
    -- Impide dos citas solapadas del mismo médico. Es la defensa contra el
    -- write skew: ninguna cantidad de validación en PHP o TypeScript lo logra,
    -- porque entre leer y escribir hay una ventana.
    CONSTRAINT citas_sin_solapamiento
        EXCLUDE USING gist (doctor_id WITH =, franja WITH &&)
        WHERE (estado <> 'cancelada')
);

-- PostgreSQL NO crea índices en claves foráneas automáticamente, y Prisma
-- tampoco. Sin estos, cada borrado de doctor o paciente hace un Seq Scan.
CREATE INDEX CONCURRENTLY IF NOT EXISTS citas_doctor_id_idx   ON citas (doctor_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS citas_paciente_id_idx ON citas (paciente_id);

-- Índice para la consulta real de la agenda: médico + rango de fechas.
-- Igualdad primero, rango después.
CREATE INDEX CONCURRENTLY IF NOT EXISTS citas_doctor_franja_idx ON citas USING gist (doctor_id, franja);

-- NOTA SOBRE CONCURRENTLY
-- No bloquea escrituras, y por eso NO puede ir dentro de una transacción.
-- Prisma envuelve las migraciones en una transacción: hay que marcar este
-- archivo para ejecución fuera de transacción o crear los índices en un paso
-- aparte. Es exactamente el tipo de detalle que se detecta leyendo el SQL y es
-- invisible leyendo el schema.prisma.
