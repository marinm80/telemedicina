# Brief — Cerrar el flujo de Referidos → Agendamiento

> **Estado**: decisiones cerradas (ver sección 7), sin implementar todavía.
> **Por qué existe**: es el hueco más señalado en la revisión de portfolio — un paciente referido a un especialista hoy tiene que buscarlo manualmente, sin continuidad desde la consulta que generó el referido.

---

## 0. Prerrequisitos — bugs que hay que arreglar antes de construir la UI encima

### P1 — RLS de `referrals` no filtra por el GUC correcto

En [`2026_08_06_000002_create_referrals_table.php`](../backend/database/migrations/2026_08_06_000002_create_referrals_table.php) las tres políticas usan `app.user_id` / `app.user_role`:

```sql
CREATE POLICY referrals_patient_policy ON referrals
    FOR SELECT TO app_runtime
    USING (patient_id = (current_setting('app.user_id', true))::uuid);
```

Cada otra migración del proyecto (ver `2026_07_31_000000_create_initial_schema.php`) usa `app.current_user_id` / `app.current_user_role`, seteados por `SetPostgresSessionContext` y por `AuthController::store()`. Con el nombre equivocado, `current_setting('app.user_id', true)` devuelve `NULL` siempre, y `patient_id = NULL::uuid` nunca es verdadero — **la política nunca deja pasar ninguna fila**, ni al paciente ni al médico referente.

`ReferralController::index()` no lo nota porque para `admin` cambia a la conexión `pgsql_admin` (bypass RLS) — solo el camino no-admin está afectado, que es justo el que un paciente real usaría. Con `RlsCoverageTest` cubriendo *que* haya RLS, pero no *que la policy realmente deje pasar filas legítimas*, esto pasó sin que ningún test lo agarrara.

**Fix**: migración nueva (la aplicada es inmutable) que dropea y recrea las tres políticas con `app.current_user_id` / `app.current_user_role`.

**Test que lo prueba en rojo primero**: un paciente autenticado real (no admin) hace `GET /api/referrals` después de que un médico le creó un referido → hoy devuelve `data: []`; después del fix, devuelve el referido.

### P2 — `specialty_name` es texto libre, no hay forma confiable de enlazar al directorio

`referrals.specialty_name` es `varchar(100)` sin constraint contra `specialties`. `DirectoryController::index()` filtra por `specialty_id` (entero, FK). Si el médico que refiere escribe "Cardiologia" sin tilde o "Cardio", no hay manera de resolver eso a un `specialty_id` real para armar el deep-link.

**Fix**: agregar `specialty_id` (FK nullable a `specialties.id`) a `referrals` vía migración nueva, poblado desde el frontend con un `<select>` contra el catálogo real (ya existe en `Directory.vue`/`ConsultationView.vue` como referencia). Mantener `specialty_name` como snapshot histórico (por si el catálogo cambia después), pero el filtro usa `specialty_id`.

---

## 1. Objetivo funcional

Cuando un médico general refiere a un paciente a un especialista durante una consulta, el paciente debe poder ir de "tengo un referido pendiente" a "tengo la cita agendada" sin tener que re-explicar nada ni buscar desde cero — en lo posible, en menos clics que el flujo de booking normal.

## 2. Alcance

**Dentro de alcance (v1):**
- Página dedicada `Patient/MyReferrals.vue` con tabs Pendientes / Aceptados / Completados (patrón consistente con `MyAppointments.vue`), más su entrada en `AppSidebar.vue` con badge de conteo de pendientes.
- Deep-link desde un referido hacia el flujo de booking:
  - Si el médico especificó `referred_doctor_id` → directo a `/booking/{doctorProfileId}` con ese médico precargado.
  - Si solo especificó especialidad → a `/directory?specialty_id=X`, con el filtro ya aplicado.
- Ciclo de vida completo de dos transiciones (ver decisión ❓1): `pending` → `accepted` al agendar la cita → `completed` al firmarse la nota de la consulta con el especialista.
- Tratamiento visual de prioridad `urgente` (ver decisión ❓3): badge + orden + copy, sin bloqueo funcional.
- Corregir P1 y P2.

**Fuera de alcance (v1) — decidí explícitamente no hacerlo para no inflar el brief:**
- Auto-crear la cita sin que el paciente confirme horario (rompería la elección de slot/modalidad que ya es fuerte en el wizard).
- Notificaciones push/email cuando se crea un referido (hoy no hay sistema de notificaciones en el proyecto; sería un brief aparte).
- Referidos entre especialistas (especialista → especialista). El flujo actual asume general → especialista.

## 3. Cambios de datos

```sql
-- Migración nueva (no tocar la de 2026_08_06_000002 — es inmutable)
ALTER TABLE referrals ADD COLUMN specialty_id integer REFERENCES specialties(id);

-- appointment_id ES la pieza que falta para la transición accepted -> completed:
-- consultation_id (ya existe) apunta a la consulta del médico QUE REFIERE (el origen).
-- appointment_id (nuevo) apunta a la cita QUE EL PACIENTE AGENDÓ con el especialista
-- (el destino). Sin este campo, ConsultationFormController::archive() no tiene cómo
-- saber que la consulta que está firmando cierra un referido.
ALTER TABLE referrals ADD COLUMN appointment_id uuid REFERENCES appointments(id) ON DELETE SET NULL;
CREATE INDEX idx_referrals_appointment_id ON referrals(appointment_id);

DROP POLICY referrals_patient_policy ON referrals;
DROP POLICY referrals_doctor_policy ON referrals;
DROP POLICY referrals_admin_policy ON referrals;

CREATE POLICY referrals_patient_policy ON referrals
    FOR SELECT TO app_runtime
    USING (patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid);

CREATE POLICY referrals_doctor_policy ON referrals
    FOR ALL TO app_runtime
    USING (referring_doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid);

CREATE POLICY referrals_admin_policy ON referrals
    FOR ALL TO app_runtime
    USING (current_setting('app.current_user_role', true) = 'admin');
```
(El `NULLIF(..., '')` replica el patrón defensivo que ya usan las demás políticas del proyecto contra el GUC vacío.)

## 4. Cambios de backend

- `ReferralController::store()`: aceptar `specialty_id` (validar que existe en `specialties` y está `is_active`), seguir aceptando `specialty_name` como snapshot de texto (autocompletado del nombre, no editable).
- `ReferralController::index()`: sin cambios de lógica — una vez arreglado P1, ya filtra correctamente por RLS. Confirmar que el eager-load de `referredDoctor` incluya lo necesario para armar el link (`doctor_profile_id` no está en `users`, está en `doctor_profiles` — puede necesitar un join adicional o exponerlo desde `v_doctor_directory`).

**Transición 1 — `pending` → `accepted` (al agendar):**
El frontend manda `referral_id` junto con `POST /api/appointments`. Dentro de `BookAppointmentAction::handle()` (misma transacción, para que no quede un estado a medias si la cita falla por colisión de slot):
```php
if (!empty($data['referral_id'])) {
    DB::connection('pgsql_admin')->table('referrals')
        ->where('id', $data['referral_id'])
        ->update(['status' => 'accepted', 'appointment_id' => $appointment->id, 'updated_at' => now()]);
}
```

**Transición 2 — `accepted` → `completed` (al firmar la nota):**
`ConsultationFormController::archive()` ya resuelve `$consultation->appointment_id` en la línea 96 para validar autorización. Justo después de eso, un `UPDATE` con la misma condición que propusiste:
```php
$db->table('referrals')
    ->where('appointment_id', $consultation->appointment_id)
    ->where('status', 'accepted')
    ->update(['status' => 'completed', 'updated_at' => now()]);
```
Es literalmente el UPDATE de una línea que mencionaste — no hace falta tocar la firma de la nota ni el resto del método.

- Limpieza menor: `backend/routes/api.php` registra las 3 rutas de `/referrals` dos veces (líneas ~58-61 dentro del grupo `['web', SetPostgresSessionContext::class]`, y otra vez al final dentro de `auth:sanctum` — esta segunda copia es código muerto, Laravel nunca la alcanza). Borrar el bloque `auth:sanctum` duplicado.

## 5. Cambios de frontend

- **Nuevo**: `Patient/MyReferrals.vue`, página dedicada con 3 tabs (Pendientes / Aceptados / Completados), mismo patrón de `MyAppointments.vue`.
  - Dentro de "Pendientes": los `urgente` van primero (sort en el frontend por `priority` antes que por fecha, o en el backend en `ReferralController::index()` con `ORDER BY (priority = 'urgente') DESC, created_at ASC` — mejor en backend para no repetir la lógica si otra vista lista referidos).
  - Cada card: especialidad, motivo (`reason`), médico que refirió, y botón de acción. Si `priority = 'urgente'`: badge terracotta con ⚠️ reutilizando el estilo `severity="critical"` de `AlertCard.vue` (misma animación pulsante que ya existe, no una nueva), + texto "Tu médico marcó esto como urgente — agenda lo antes posible". Sin botón de "descartar" que bloquee nada — es solo señal visual.
  - Botón de acción resuelve el link: `referred_doctor_id` presente → `/booking/{doctorProfileId}`; si no → `/directory?specialty_id=X`.
- **`AppSidebar.vue`**: nuevo item de navegación "Mis Referidos" para el rol `patient`, con badge de conteo (cantidad de `pending`, con variante de color si hay algún `urgente` entre ellos).
- `BookingWizard.vue` / flujo de confirmación: si la navegación vino de un referido (pasar `referral_id` como query param), incluirlo en el payload de `POST /api/appointments` para que el backend cierre el círculo.
- `ConsultationView.vue` (lado médico, donde ya se crea el referido): cambiar el input de especialidad de texto libre a `<select>` contra `specialties` activas, mandando `specialty_id`.

## 6. Criterios de aceptación (BDD, para que sirvan de test de aceptación)

```gherkin
Escenario: Paciente ve un referido pendiente creado por su médico
  Dado que el Dr. Carlos (medicina general) refirió a María a Cardiología con motivo "arritmia"
  Cuando María entra a su dashboard
  Entonces ve una card de referido pendiente con especialidad "Cardiología" y el motivo

Escenario: Referido con médico específico lleva directo al wizard
  Dado un referido con referred_doctor_id apuntando a la Dra. García
  Cuando María hace click en "Agendar" desde la card
  Entonces cae en /booking/{doctorProfileId} de la Dra. García, sin pasos intermedios

Escenario: Referido sin médico específico lleva al directorio filtrado
  Dado un referido con specialty_id de Cardiología y sin referred_doctor_id
  Cuando María hace click en "Agendar"
  Entonces cae en /directory?specialty_id=X ya filtrado por Cardiología

Escenario: Agendar desde un referido lo marca como aceptado
  Dado un referido pendiente
  Cuando María completa el booking desde ese referido
  Entonces el referido pasa a status='accepted' con appointment_id apuntando a la nueva cita
  Y aparece en la tab "Aceptados" de MyReferrals.vue, no en "Pendientes"

Escenario: Firmar la nota de la consulta del especialista completa el referido
  Dado un referido en status='accepted' con appointment_id apuntando a la cita de la Dra. García
  Cuando la Dra. García firma y archiva la nota de esa consulta (ConsultationFormController::archive)
  Entonces el referido pasa a status='completed'
  Y aparece en la tab "Completados" de MyReferrals.vue

Escenario: Un referido urgente se muestra primero y con badge distintivo
  Dado que María tiene un referido normal creado ayer y uno urgente creado hoy
  Cuando entra a la tab "Pendientes" de MyReferrals.vue
  Entonces el referido urgente aparece primero, con badge terracotta y el texto de urgencia
  Y no hay ningún botón que le impida ignorarlo o posponerlo
```

## 7. Decisiones (cerradas 2026-08-06)

| # | Pregunta | Decisión | Por qué |
|---|----------|----------|---------|
| ❓1 | ¿`accepted` al agendar o `completed` al finalizar consulta? | **(b) Dos transiciones**: `accepted` al agendar, `completed` al firmar la nota del especialista. | El costo real es un `UPDATE` de una línea en `ConsultationFormController::archive()` (ver sección 4) — y demuestra un ciclo de vida completo, más valioso para portfolio que la versión simplificada. Requiere agregar `appointment_id` a `referrals` (sección 3) para que `archive()` sepa qué referido cerrar. |
| ❓2 | ¿Página dedicada o sección en el dashboard? | **Página dedicada `Patient/MyReferrals.vue`**. | Consistente con el patrón ya establecido por `MyAppointments.vue`. `PatientDashboard.vue` ya tiene 466 líneas (4 StatCards + DataTable + BarChart + AssistantWidget + AlertCard) — meter más ahí lo satura. Una página propia da espacio a tabs, filtros y el deep-link sin comprometer el dashboard. |
| ❓3 | ¿Tratamiento visual para `urgente`? | **Sí, solo visual — sin bloqueo funcional.** Badge terracotta reutilizando `severity="critical"` de `AlertCard.vue` (misma animación pulsante), orden primero en la lista, copy de urgencia. Sin restricción para descartar/ignorar. | Coherente con lo que ya existe en el design system Salvia — no se inventa un componente nuevo. Bloquear acciones agrega fricción que no corresponde a un proyecto de portfolio. |

## 8. Fuera del brief pero anotado para más adelante

- No hay sistema de notificaciones (push/email/in-app) — cuando se cree un referido, hoy el paciente solo se entera si entra a su dashboard. Si esto importa para la demo, es un brief propio.
- `RlsCoverageTest` prueba *que existe* RLS, no *que la policy deja pasar filas legítimas*. El bug P1 pasó desapercibido por eso. Vale la pena, en algún momento, agregar un test de "smoke" por tabla RLS que loguee como cada rol y confirme que ve exactamente lo que debería ver — no solo que RLS está prendido.
