# Protocolo de Autorización y Seguridad de Datos (Gate 2B - Apéndice)

> **Estado:** Propuesta de Diseño de Seguridad para Aprobación
> **Stack:** Laravel 11 + PostgreSQL 16.0 (Row-Level Security)
> **Rigor:** MÁXIMO (Privacidad de datos médicos y blindaje transaccional)

Este documento detalla la arquitectura de autorización del sistema, diseñada bajo el principio de **Defensa en Superficies Múltiples**. Ninguna regla de privacidad depende de que el desarrollador "se acuerde" de aplicar una Policy; el cumplimiento se hace obligatorio por restricciones a nivel de motor de base de datos y validaciones automatizadas en el pipeline de CI/CD.

---

## 1. Matriz de Autorización por Reglas de Negocio

A continuación se detalla el mecanismo de protección, justificación de capas y pruebas de aceptación para las cuatro reglas críticas de privacidad:

### Regla 1: Acceso del Médico a Ficha, Notas, Chat y Documentos
*Un médico accede a la ficha clínica longitudinal, notas de consulta, chat y documentos adjuntos de un paciente SOLO si tiene o ha tenido una cita confirmada o completada con dicho paciente.*

* **Capas implicadas:** `Laravel Policy` (Capa de Enrutamiento/Aplicación) + `PostgreSQL Row-Level Security (RLS)` (Capa de Almacenamiento).
* **Mecanismo Concreto:**
  1. *Capa Laravel:* El middleware de ruta invoca a `PatientProfilePolicy@view`. Este comprueba la existencia de un registro en `appointments` donde `doctor_id = auth()->id()`, `patient_id = $patient->user_id` y `status IN ('confirmed', 'completed')`.
  2. *Capa PostgreSQL:* Se activa RLS en la tabla `patient_profiles` y en las tablas clínicas dependientes (`patient_allergies`, `consultation_notes`, `consultation_messages`). La política SQL de PostgreSQL evalúa:
     ```sql
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
     ```
* **Por qué esta combinación (y no solo una):** Si solo usamos Laravel Policies y un desarrollador en seis meses crea un endpoint `/api/v2/stats/patient-details` y olvida adjuntar la Policy, la información clínica quedaría expuesta a cualquier médico autenticado. Con RLS activo en PostgreSQL, la consulta del nuevo endpoint retornará `0` filas automáticamente al fallar la validación a nivel de motor, actuando como red de seguridad definitiva.
* **Escenario Gherkin de Verificación:**
  ```gherkin
  Escenario: Médico intenta ver la ficha de un paciente sin cita registrada
    Dado que existe un médico autenticado "Dr. Gregory" (UUID: 550e8400-e29b-41d4-a716-446655440000)
    Y existe un paciente "Juan Pérez" (UUID: 660e8400-e29b-41d4-a716-446655441111)
    Y no existe ninguna cita confirmada ni completada entre ambos en la tabla "appointments"
    Cuando el médico envía una petición GET a "/api/consultations/detail?patient_id=660e8400-e29b-41d4-a716-446655441111"
    Entonces el servidor responde con código HTTP 403 Forbidden
    Y el cuerpo de la respuesta no contiene ningún dato clínico del paciente.
  ```

---

### Regla 2: Agente Administrativo y Datos Clínicos
*El agente administrativo NUNCA accede a datos clínicos: ni fichas, ni notas, ni chats, ni documentos. Solo visualiza y gestiona la agenda y datos de contacto.*

* **Capas implicadas:** `Middleware de Laravel` + `PostgreSQL RLS`.
* **Mecanismo Concreto:**
  1. *Capa Laravel:* El grupo de rutas `/api/clinical/*` está protegido por el middleware `EnsureUserIsNotAgent`. Este intercepta el rol y, si es `agent`, aborta con `403 Forbidden` inmediatamente, impidiendo la instanciación de controladores clínicos.
  2. *Capa PostgreSQL:* Las tablas `patient_allergies`, `consultation_notes` y `consultation_messages` tienen RLS configurado con una política restrictiva que niega toda operación si el rol de sesión es `agent`:
     ```sql
     CREATE POLICY agente_bloqueo_clinico ON consultation_notes
     FOR ALL
     USING (current_setting('app.current_user_role', true) <> 'agent');
     ```
* **Por qué esta combinación:** El middleware bloquea la petición en la frontera de enrutamiento, evitando consultas costosas y simplificando el flujo de aplicación. El RLS previene que fallos de configuración de rutas expongan tablas críticas a través de endpoints administrativos genéricos.
* **Escenario Gherkin de Verificación:**
  ```gherkin
  Escenario: Agente administrativo intenta consultar el chat clínico de una consulta
    Dado que existe un agente administrativo autenticado "Ana Gómez"
    Cuando intenta enviar una petición GET a "/api/consultations/120/messages"
    Entonces el servidor responde con código HTTP 403 Forbidden
    Y no se registra ninguna lectura de datos en el historial.
  ```

---

### Regla 3: Paciente y Acceso Propietario (Borradores Ocultos)
*Un paciente accede única y exclusivamente a sus propios datos personales e informes médicos. No puede visualizar notas de consulta en estado de "borrador" (`draft`).*

* **Capas implicadas:** `Laravel Policy` + `Eloquent Global Scope` + `PostgreSQL RLS`.
* **Mecanismo Concreto:**
  1. *Capa Laravel (Policy):* `ConsultationNotePolicy` verifica que `auth()->id() === $appointment->patient_id`.
  2. *Capa Laravel (Scope Global):* El modelo `ConsultationNote` tiene un Query Scope global que aplica un filtro implícito si el rol del usuario autenticado es `patient`:
     ```php
     static::addGlobalScope('solo_firmadas', function (Builder $builder) {
         if (auth()->check() && auth()->user()->hasRole('patient')) {
             $builder->where('status', 'firmada');
         }
     });
     ```
  3. *Capa PostgreSQL (RLS):* La política de seguridad RLS de la tabla `consultation_notes` evalúa:
     ```sql
     CREATE POLICY paciente_acceso_notas ON consultation_notes
     FOR SELECT
     USING (
         current_setting('app.current_user_role', true) = 'patient'
         AND status = 'firmada'
         AND EXISTS (
             SELECT 1 FROM appointments a JOIN consultations c ON c.appointment_id = a.id
             WHERE c.id = consultation_notes.consultation_id
               AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
         )
     );
     ```
* **Por qué esta combinación:** El Global Scope de Eloquent garantiza que cualquier consulta del desarrollador (ej: `$patient->notes`) excluya automáticamente los borradores de forma transparente para evitar errores visuales en el portal del paciente. El RLS actúa en la base de datos asegurando que no se pueda eludir la regla inyectando sentencias SQL alternativas.
* **Escenario Gherkin de Verificación:**
  ```gherkin
  Escenario: Paciente intenta leer una nota SOAP en estado borrador
    Dado que el paciente "Juan Pérez" está autenticado
    Y la nota clínica de su consulta tiene el campo status = "draft"
    Cuando el paciente envía una petición GET a "/api/consultations/50/notes"
    Entonces el servidor responde con código HTTP 403 Forbidden (o 404 Not Found)
    Y no se expone el texto de los campos SOAP de la nota.
  ```

---

### Regla 4: Médico y Visualización de Ingresos Propios
*Un médico visualiza únicamente los ingresos financieros derivados de las consultas y comisiones asociadas a su propia agenda médica. No tiene acceso a la contabilidad global de la plataforma.*

* **Capas implicadas:** `Laravel Policy` + `Eloquent Global Scope`.
* **Mecanismo Concreto:**
  1. *Laravel Policy:* `CommissionPolicy@view` y `PaymentPolicy@view` validan que la cita asociada al pago tenga `doctor_id === auth()->id()`.
  2. *Eloquent Global Scope:* El modelo `Commission` filtra automáticamente todas las lecturas de los médicos por su ID:
     ```php
     static::addGlobalScope('mis_comisiones', function (Builder $builder) {
         if (auth()->check() && auth()->user()->hasRole('doctor')) {
             $builder->whereHas('payment.appointment', function ($q) {
                 $q->where('doctor_id', auth()->id());
             });
         }
     });
     ```
* **Por qué esta combinación:** Evita fugas financieras accidentales. El scope global automatiza la restricción en todos los listados de facturación.
* **Escenario Gherkin de Verificación:**
  ```gherkin
  Escenario: Médico intenta ver el desglose de ingresos de otro profesional
    Dado que el "Dr. Gregory" está autenticado
    Y existe un registro de comisión con ID "990e8400-e29b-41d4-a716-446655440000" perteneciente al "Dr. Wilson"
    Cuando el "Dr. Gregory" envía una petición GET a "/api/commissions/990e8400-e29b-41d4-a716-446655440000"
    Entonces el servidor responde con código HTTP 403 Forbidden.
  ```

---

## 2. Decisión del Rol de Conexión de la Aplicación

Para aplicar Row-Level Security de manera robusta, adoptamos la **Opción (b): Runtime y Migraciones con el Mismo Rol de Conexión + `FORCE ROW LEVEL SECURITY`**.

### Implicación para el Pipeline de Migraciones
1. **Un solo usuario DDL/DML:** El usuario de base de datos configurado en `.env` (`DB_USERNAME`) actúa como el propietario (owner) que ejecuta `php artisan migrate`. Por lo tanto, es el dueño físico de las tablas creadas.
2. **Forzado de RLS en Propietarios:** Dado que PostgreSQL, por estándar, ignora las políticas RLS para el propietario de la tabla, agregamos de forma mandatoria al final del DDL de creación de cada tabla clínica:
   ```sql
   ALTER TABLE <tabla> ENABLE ROW LEVEL SECURITY;
   ALTER TABLE <tabla> FORCE ROW LEVEL SECURITY;
   ```
   El comando `FORCE ROW LEVEL SECURITY` obliga a PostgreSQL a evaluar y aplicar las restricciones de RLS incluso cuando las consultas son lanzadas por el runtime de Laravel utilizando el rol de conexión owner.
3. **Migraciones Libres de Filtros:** RLS aplica únicamente a sentencias DML (`SELECT`, `INSERT`, `UPDATE`, `DELETE`). Las sentencias estructurales DDL (`CREATE`, `ALTER`, `DROP`) ejecutadas por `php artisan migrate` se procesan sin interferencia del RLS. Para la ejecución de seeders o backfills de datos de prueba dentro de las migraciones, la sesión no inyecta credenciales locales de RLS, comportándose como un bypass seguro de administración.

---

## 3. Pruebas de Integridad del Gauntlet de Testing (CI/CD)

Para garantizar que la seguridad RLS permanezca **activa y configurada de verdad** en PostgreSQL y no se convierta en una política decorativa deshabilitada accidentalmente, agregamos dos pruebas automatizadas en el conjunto de testing que fallan el pipeline ante cualquier regresión:

### Prueba 1: Ataque SQL Directo (Fuga de Ficha Clínica)
Esta prueba simula una inyección o llamada SQL directa usando la conexión y el rol de runtime de la aplicación (el mismo dueño que migró las tablas). Intenta leer la ficha clínica de un paciente sin que exista cita relacionada, verificando que la base de datos intercepte el query en disco y devuelva cero resultados.

```php
test('rls impide leer la ficha clinica de un paciente por consulta sql directa si no hay relacion de cita', function () {
    // 1. Crear pacientes y médico
    $paciente = User::factory()->create(['role' => 'patient']);
    $medico = User::factory()->create(['role' => 'doctor']);
    
    // Crear el perfil del paciente
    $patientProfile = PatientProfile::create([
        'user_id' => $paciente->id,
        'phone' => '12345678',
        'date_of_birth' => '1990-01-01',
        'gender' => 'Masculino',
        'address' => 'Dirección de prueba'
    ]);

    // 2. Establecer el contexto de sesión de la BD para el médico (sin cita activa)
    DB::statement("SET LOCAL app.current_user_id = '{$medico->id}'");
    DB::statement("SET LOCAL app.current_user_role = 'doctor'");

    // 3. Ejecutar consulta SQL directa (saltando Eloquent)
    $resultados = DB::select("SELECT * FROM patient_profiles WHERE user_id = :user_id", [
        'user_id' => $paciente->id
    ]);

    // 4. Aserción: RLS debe obligar a PostgreSQL a retornar un conjunto vacío (0 filas)
    expect($resultados)->toBeEmpty();
});
```

---

### Prueba 2: Auditoría del Catálogo del Sistema de PostgreSQL
Esta prueba automatiza el compliance. Consulta las tablas del sistema de PostgreSQL `pg_class` y `pg_policies` para asegurar que:
1. Toda tabla clasificada como clínica posee `relrowsecurity` en `true`.
2. Toda tabla clínica posee `relforcerowsecurity` en `true` (garantizando el forzado de RLS para el owner).
3. Cada una de ellas posee al menos una política RLS declarada y activa en base de datos.

```php
test('todas las tablas clinicas tienen habilitado y forzado el row level security con politicas activas', function () {
    $tablasClinicas = [
        'patient_profiles',
        'patient_allergies',
        'patient_conditions',
        'patient_medications',
        'appointments',
        'pre_consultation_forms',
        'consultations',
        'consultation_messages',
        'consultation_notes',
        'note_amendments',
        'documents'
    ];

    foreach ($tablasClinicas as $tabla) {
        // Consultar el catálogo del sistema para verificar ENABLE RLS y FORCE RLS
        $estadoRls = DB::selectOne("
            SELECT relrowsecurity, relforcerowsecurity 
            FROM pg_class 
            WHERE relname = :tablename
        ", ['tablename' => $tabla]);

        expect($estadoRls)->not->toBeNull();
        
        // Assertions de activación
        expect($estadoRls->relrowsecurity)->toBeTrue("La tabla '{$tabla}' no tiene ENABLE ROW LEVEL SECURITY.");
        expect($estadoRls->relforcerowsecurity)->toBeTrue("La tabla '{$tabla}' no tiene FORCE ROW LEVEL SECURITY.");

        // Verificar la existencia de al menos una política RLS activa
        $politicas = DB::select("
            SELECT policyname 
            FROM pg_policies 
            WHERE tablename = :tablename
        ", ['tablename' => $tabla]);

        expect($politicas)->not->toBeEmpty("La tabla '{$tabla}' tiene RLS activo pero carece de CREATE POLICY.");
    }
});
```

**Consecuencia:** Si en seis meses un desarrollador crea una nueva tabla de datos clínicos y olvida añadir el código RLS en su migración inicial, **esta segunda prueba fallará inmediatamente en el pipeline de CI/CD** al no encontrar la tabla configurada con `relrowsecurity` y `relforcerowsecurity`, impidiendo la integración del código vulnerable.
