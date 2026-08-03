# Walkthrough — Entrega 1: Flujo de Reserva y Agenda (Gate 3)

Se ha completado de forma exitosa la reorganización estructural del proyecto y la implementación del backend de la **Entrega 1 del Módulo de Citas y Agenda**, con el rediseño de idempotencia en base de datos.

---

## 📁 Estructura del Monorrepo

Toda la aplicación se ha reorganizado para aislar las responsabilidades físicas:
- **`backend/`**: Laravel 11. Contiene la lógica API, migraciones, modelos, y la suite de pruebas.
- **`frontend/`**: Vue 3 + TypeScript + Vite + PrimeVue. Los archivos compilados se envían automáticamente al directorio `backend/public/build/` durante el empaquetado de producción.
- **`docker-compose.yml`**: Define los contenedores aislados de desarrollo:
  - `postgres`: PostgreSQL 16 (con extensiones `uuid-ossp` y `btree_gist` habilitadas). Mapea el puerto local a `5433` para no colisionar con la base de datos de Windows.
  - `redis`: Redis de caché y colas.
  - `backend`: PHP 8.4 CLI con el driver nativo de PostgreSQL `pdo_pgsql` compilado bajo Linux, solucionando las limitaciones del compilador en Windows.

---

## 🔑 Idempotencia Defendida en Base de Datos
Para solventar el defecto de "Verificar y Después Escribir" (TOCTOU) y cumplir con la regla **D2** (la seguridad de unicidad debe residir en el esquema):
1. **Esquema**: Se añadieron las columnas `idempotency_key` (UUID UNIQUE NULL) e `idempotency_payload_hash` (varchar(64) NULL) directamente en la tabla `appointments`.
2. **Atomicidad**: `BookAppointmentAction::handle()` realiza el insert directamente dentro de la transacción de base de datos.
3. **Manejo de Errores PostgreSQL**:
   - **`23505`** (Violación de clave única): Se trata de una reejecución de idempotencia (por ejemplo, doble clic concurrente). Se busca la cita existente y se verifica el payload hash. Si coincide, devuelve la cita original con un `201 Created`. Si difiere, lanza `IdempotencyCollisionException` (retornando `400 Bad Request`).
   - **`23P01`** (Violación de exclusión): El slot ya está tomado por otra cita activa del médico. Lanza `SlotCollisionException` (retornando `409 Conflict`).

---

## 🔒 Base de Datos y Seguridad RLS (Gate 2B & Gauntlet)

La migración inicial `2026_07_31_000000_create_initial_schema.php` inyecta las políticas estructurales:
- **Tipo timerange**: Se creó un tipo de rango personalizado en PostgreSQL para manejar horarios recurrentes planos sin fecha.
- **Bifurcación de Roles**: Creación segura y dinámica de `app_owner` (migraciones/seeders), `app_runtime` (web runtime, sujeto a RLS) y `app_worker` ( Horizon / Jobs / Asíncronos, sin `DELETE`).
- **Inmutabilidad**: Las tablas clínicas y de auditoría tienen permisos selectivos de DML (sin `DELETE`, y secuencias sin `UPDATE`).
- **Políticas RLS**: Bloqueo absoluto de perfiles y citas. Los pacientes sólo pueden leer sus propios registros o citas asociadas a médicos aprobados en su agenda.

---

## 🧪 Resultados de la Suite de Pruebas

Se ejecutó la suite de pruebas `BookAppointmentTest.php` dentro del contenedor con un resultado exitoso de **14 pruebas aprobadas y 70 aserciones**:

```bash
docker compose exec backend php artisan test
```

### 💎 Gauntlet RLS Aprobado
- **Gauntlet Rol Runtime**: Se aserta que la conexión ordinaria se ejecuta bajo el rol `app_runtime` y no como superusuario.
- **Ataque SQL de Lectura Directa**: Un paciente que intente realizar una consulta SQL directa (`SELECT * FROM patient_profiles`) sobre registros ajenos usando `app_runtime` con RLS activo recibe **0 filas**.
- **Inserción SOAP Autorizada**: RLS bloquea la inserción de borradores SOAP a pacientes y otros médicos no asignados, permitiendo únicamente el registro al médico a cargo de la consulta.

### ⚡ Concurrencia Real de Slots e Idempotencia
La suite ejecuta dos pruebas concurrentes reales abriendo **dos conexiones PDO concurrentes independientes** en la base de datos de PostgreSQL con contextos de RLS de pacientes distintos:
- **Colisión de Slot (409)**: Dos pacientes intentan reservar en paralelo el mismo slot libre. PostgreSQL frena uno e inmediatamente dispara una violación del índice de exclusión GIST de solapamiento (`23P01`).
- **Colisión de Idempotencia por Doble Clic (201/400)**: Dos peticiones del mismo paciente con la misma clave de idempotencia se ejecutan concurrentemente. PostgreSQL lanza una violación de restricción única (`23505` o `55P03` lock_timeout), que es interceptada por el Action para asegurar que sólo se cree una cita físicamente y que se responda exitosamente al cliente.
