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
  2. *Capa PostgreSQL (RLS):* Se imponen políticas RLS segregadas para SELECT y escrituras (DML).
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
  2. *Capa PostgreSQL:* Las tablas clínicas tienen RLS configurado con una política restrictiva que niega toda operación si el rol de sesión es `agent`.
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
  3. *Capa PostgreSQL (RLS):* La política de seguridad RLS en `consultation_notes` exige que la nota esté `firmada` para ser visible al rol de paciente.
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

## 2. Decisión del Rol de Conexión y Gestión de RLS (Opción A)

Para evitar el fallo de las políticas RLS en entornos locales y CLI (comandos Artisan, seeders de base de datos y migraciones iniciales), se implementa la **Bifurcación de Roles de Conexión (Opción A)**.

### Diseño de Conexiones en Laravel
El archivo `config/database.php` posee dos configuraciones separadas para el motor PostgreSQL:
* **`pgsql_owner`:** Utiliza las credenciales de `app_owner`. Tiene privilegios DDL completos, es dueño de las tablas y no tiene RLS activo de forma predeterminada al no forzarlo. Es utilizado en el pipeline de despliegue mediante:
  `php artisan migrate --database=pgsql_owner`
* **`pgsql` (Por defecto):** Utiliza las credenciales de `app_runtime`. Es el rol limitado empleado por el servidor web HTTP en producción. Al no ser dueño de las tablas y carecer de privilegios `BYPASSRLS`, PostgreSQL evalúa rígidamente las políticas RLS sobre todas sus operaciones.

### Implicación para el Runtime y Seguridad
El rol `app_runtime` posee privilegios estrictos `GRANT SELECT, INSERT, UPDATE, DELETE` y carece de permisos de alteración de esquema (DDL). RLS se le aplica de forma natural y automática al realizar cualquier consulta en caliente.

---

## 3. Blindaje de la Inmutabilidad por Privilegios de Tabla y Secuencias

La inmutabilidad del sistema no depende únicamente del código o de políticas RLS. Se imponen restricciones físicas a nivel de privilegios PostgreSQL sobre el rol `app_runtime`:

* **Bloqueo Absoluto de Borrado/Edición en Tablas Clínicas/Auditoría:**
  El rol `app_runtime` carece del privilegio `DELETE` (y en su caso `UPDATE`) en tablas inmutables. Cualquier intento de borrado físico lanzará un error SQL antes de evaluar RLS:
  * `audit_logs` -> `GRANT SELECT, INSERT` (Bloqueados `UPDATE` y `DELETE`).
  * `note_amendments` -> `GRANT SELECT, INSERT` (Bloqueados `UPDATE` y `DELETE`).
  * `processed_stripe_events` -> `GRANT SELECT, INSERT` (Bloqueados `UPDATE` y `DELETE`).
  * `consultation_notes` -> `GRANT SELECT, INSERT, UPDATE` (Bloqueado `DELETE`).
* **Justificación de Privilegios para `vital_signs` (Signos Vitales):**
  * **Privilegios:** `GRANT SELECT, INSERT` (Bloqueados `UPDATE` y `DELETE`).
  * *Justificación:* Los signos vitales (peso, presión, temperatura) representan una medición fisiológica objetiva tomada en un instante específico. Clínicamente es inaceptable alterar una lectura pasada o eliminarla; si se requiere una nueva medición, se realiza una nueva inserción de registro.
* **Privilegios en Secuencias:**
  * **Privilegios:** `GRANT USAGE, SELECT` (Bloqueado `UPDATE`).
  * *Justificación:* Para que funcione `nextval()`, el rol solo requiere privilegios de `USAGE` y `SELECT` sobre la secuencia. Conceder `GRANT UPDATE` permitiría al runtime ejecutar `setval()`, pudiendo alterar correlativos intencionadamente y causar colisiones de IDs o denegación de servicios.
* **Seguridad por Defecto en Tablas Nuevas (`ALTER DEFAULT PRIVILEGES`):**
  Cualquier tabla nueva creada por `app_owner` otorgará únicamente `GRANT SELECT` a `app_runtime` de manera automática. Esto obliga a los programadores a declarar explícitamente privilegios de escritura (`INSERT`, `UPDATE`) en sus archivos de migración de forma manual, evitando que hereden privilegios destructivos por defecto.

---

## 4. Comportamiento RLS para Escrituras: USING vs WITH CHECK

Dado que PostgreSQL bloquea por defecto cualquier operación DML si RLS está activo y no se especifica una política explícita para la operación, configuramos políticas granulares utilizando las cláusulas `USING` y `WITH CHECK`:

* **`USING` (Filtro de Filas Existentes):** 
  Aplica a lecturas y escrituras de registros preexistentes (`SELECT`, `UPDATE`, `DELETE`). Define qué filas de la base de datos son visibles o modificables por la petición. Si una fila no cumple el criterio de `USING`, PostgreSQL actúa como si no existiera (retorna 0 filas o lanza error de no encontrado).
* **`WITH CHECK` (Validación de Nuevos Valores):**
  Aplica a inserciones y modificaciones de datos (`INSERT`, `UPDATE`). Evalúa si la fila resultante de la operación cumple con la regla de negocio. Si falla, el motor aborta la transacción lanzando un error de violación de política RLS, previniendo que un usuario inyecte datos ajenos o altere metadatos.

### Ejemplo de Divergencia en `consultation_notes` (Notas SOAP):
* **`INSERT` (`WITH CHECK`):** Un médico que tiene una consulta activa puede insertar una nota en estado `draft` (borrador) con su UUID de autor. No existe fila previa (`USING` no aplica), pero el nuevo registro es validado.
* **`SELECT` (`USING`):** El paciente puede leer la nota sólo si el estado de la fila es `firmada`. El médico puede leerla en estado `draft` o `firmada` siempre que sea el médico de la consulta.
* **`UPDATE` (`USING` y `WITH CHECK`):** El médico puede actualizar la nota sólo si el registro existente en la base de datos tiene estado `draft` y él es el autor (`USING`), y los nuevos valores modificados deben seguir cumpliendo que él es el autor y no se intente forzar un cambio de firmante a otro usuario (`WITH CHECK`).

---

## 5. Pruebas de Integridad del Gauntlet de Testing (CI/CD)

Para garantizar que RLS y los privilegios permanezcan activos, no se salten por una mala configuración en el archivo `.env` de runtime y permitan la escritura legítima, el pipeline de CI/CD ejecuta tres pruebas obligatorias:

### Prueba 1: Ataque SQL Directo (Fuga de Ficha Clínica)
Esta prueba se conecta usando el rol runtime (`app_runtime`) y simula una lectura directa saltando Laravel. Verifica que PostgreSQL retorne `0` filas si se intenta leer un perfil de paciente ajeno.

```php
test('rls impide leer la ficha clinica de un paciente por consulta sql directa con app_runtime si no hay relacion de cita', function () {
    // 1. Crear perfiles en la base de datos usando el rol pgsql_owner
    $paciente = User::factory()->create(['role' => 'patient']);
    $medico = User::factory()->create(['role' => 'doctor']);
    
    $patientProfile = PatientProfile::create([
        'user_id' => $paciente->id,
        'phone' => '12345678',
        'date_of_birth' => '1990-01-01',
        'gender' => 'Masculino',
        'address' => 'Dirección de prueba'
    ]);

    // 2. Cambiar la conexión activa al rol app_runtime y fijar variables de sesión
    Config::set('database.default', 'pgsql_runtime');
    DB::purge('pgsql_runtime');

    DB::statement("SET LOCAL app.current_user_id = '{$medico->id}'");
    DB::statement("SET LOCAL app.current_user_role = 'doctor'");

    // 3. Ejecutar consulta directa
    $resultados = DB::select("SELECT * FROM patient_profiles WHERE user_id = :user_id", [
        'user_id' => $paciente->id
    ]);

    // 4. Aserción: RLS retorna vacío
    expect($resultados)->toBeEmpty();
});
```

---

### Prueba 2: Auditoría de Rol y Configuración de Conexión Runtime
Esta prueba previene el "bypass por .env" verificando que la conexión activa del runtime de la aplicación no sea dueña de las tablas, no sea superusuario y no tenga la propiedad `BYPASSRLS` ni la herede recursivamente de ningún rol con privilegios de bypass.

```php
test('la conexion runtime activa en el .env cumple los requisitos de seguridad y no es propietaria de las tablas clinicas', function () {
    // 1. Verificar que el usuario runtime de la base de datos no sea el propietario de las tablas
    $propietario = DB::selectOne("
        SELECT pg_catalog.pg_get_userbyid(c.relowner) as owner
        FROM pg_class c
        WHERE c.relname = 'patient_profiles'
    ");
    
    $currentUser = DB::selectOne("SELECT current_user");
    
    expect($currentUser->current_user)->not->toBe($propietario->owner, 
        "¡ALERTA DE SEGURIDAD!: La conexión runtime está usando el usuario propietario de las tablas ({$propietario->owner}), lo que anularía RLS en silencio.");

    // 2. Verificar que el rol actual de runtime no posea el atributo BYPASSRLS
    $bypassRlsCheck = DB::selectOne("
        SELECT rolbypassrls, rolsuper
        FROM pg_roles
        WHERE rolname = current_user
    ");

    expect($bypassRlsCheck->rolbypassrls)->toBeFalse(
        "¡ALERTA DE SEGURIDAD!: El rol runtime posee la propiedad BYPASSRLS.");
        
    expect($bypassRlsCheck->rolsuper)->toBeFalse(
        "¡ALERTA DE SEGURIDAD!: El rol runtime es superusuario.");

    // 3. Verificar que el rol no pertenezca a ningún grupo con BYPASSRLS o privilegios superusuario heredados
    $rolesAdministradores = DB::select("
        SELECT rolname 
        FROM pg_roles 
        WHERE rolbypassrls = true OR rolsuper = true
    ");

    foreach ($rolesAdministradores as $adminRol) {
        $tieneMembresia = DB::selectOne("SELECT pg_has_role(current_user, :admin_rol, 'member') as member", [
            'admin_rol' => $adminRol->rolname
        ]);
        
        expect($tieneMembresia->member)->toBeFalse(
            "¡ALERTA DE SEGURIDAD!: El rol runtime hereda privilegios del rol administrador '{$adminRol->rolname}'.");
    }

    // 4. Verificar que RLS esté activado en las tablas del catálogo
    $estadoRls = DB::selectOne("SELECT relrowsecurity FROM pg_class WHERE relname = 'patient_profiles'");
    expect($estadoRls->relrowsecurity)->toBeTrue("La tabla 'patient_profiles' no tiene ENABLE ROW LEVEL SECURITY.");
});
```

---

### Prueba 3: Verificación de Permiso de Escritura Legítima (INSERT RLS)
Esta prueba garantiza que RLS no rompa la aplicación en producción. Simula el rol runtime (`app_runtime`) para un médico con consulta activa y comprueba que PostgreSQL le permita insertar una nota clínica en estado `draft`.

```php
test('rls permite a app_runtime insertar una nota clinica en borrador si el medico es el asignado a la consulta', function () {
    // 1. Configurar datos iniciales (dueño)
    $paciente = User::factory()->create(['role' => 'patient']);
    $medico = User::factory()->create(['role' => 'doctor']);
    
    $appointment = Appointment::create([
        'patient_id' => $paciente->id,
        'doctor_id' => $medico->id,
        'franja' => '[2026-08-01 08:00:00+00, 2026-08-01 08:30:00+00)',
        'status' => 'confirmed'
    ]);
    
    $consultation = Consultation::create([
        'appointment_id' => $appointment->id
    ]);

    // 2. Cambiar a la conexión app_runtime
    Config::set('database.default', 'pgsql_runtime');
    DB::purge('pgsql_runtime');

    DB::statement("SET LOCAL app.current_user_id = '{$medico->id}'");
    DB::statement("SET LOCAL app.current_user_role = 'doctor'");

    // 3. Ejecutar inserción directa con app_runtime
    $insertado = DB::insert("
        INSERT INTO consultation_notes (id, consultation_id, symptoms, objective, analysis, plan, status, created_at, updated_at)
        VALUES (gen_random_uuid(), :consultation_id, 'Tos', 'Fiebre', 'Gripe', 'Reposo', 'draft', now(), now())
    ", ['consultation_id' => $consultation->id]);

    // 4. Aserción: La inserción debe ser exitosa
    expect($insertado)->toBeTrue();
});
```
