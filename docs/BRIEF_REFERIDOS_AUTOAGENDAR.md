# Brief — Cerrar el flujo de Referidos → Agendamiento

> **Estado**: sin implementar. Este documento es el punto de partida, no una decisión cerrada — las secciones marcadas ❓ necesitan tu decisión antes de codear.
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
- Vista del paciente donde ve sus referidos pendientes (hoy no existe ninguna).
- Deep-link desde un referido hacia el flujo de booking:
  - Si el médico especificó `referred_doctor_id` → directo a `/booking/{doctorProfileId}` con ese médico precargado.
  - Si solo especificó especialidad → a `/directory?specialty_id=X`, con el filtro ya aplicado.
- Al completarse la cita desde ese flujo, marcar el referido como `accepted` (o `completed` si preferís esperar a que la consulta ocurra — ver ❓ abajo).
- Corregir P1 y P2.

**Fuera de alcance (v1) — decidí explícitamente no hacerlo para no inflar el brief:**
- Auto-crear la cita sin que el paciente confirme horario (rompería la elección de slot/modalidad que ya es fuerte en el wizard).
- Notificaciones push/email cuando se crea un referido (hoy no hay sistema de notificaciones en el proyecto; sería un brief aparte).
- Referidos entre especialistas (especialista → especialista). El flujo actual asume general → especialista.

## 3. Cambios de datos

```sql
-- Migración nueva (no tocar la de 2026_08_06_000002 — es inmutable)
ALTER TABLE referrals ADD COLUMN specialty_id integer REFERENCES specialties(id);

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
- Nuevo endpoint (o extender `update()`): al agendar desde un referido, el frontend debería poder mandar `referral_id` junto con `POST /api/appointments`, y el backend marca el referido como `accepted` dentro de la misma transacción de `BookAppointmentAction`. ❓ ver decisión abajo.
- Limpieza menor: `backend/routes/api.php` registra las 3 rutas de `/referrals` dos veces (líneas ~58-61 dentro del grupo `['web', SetPostgresSessionContext::class]`, y otra vez al final dentro de `auth:sanctum` — esta segunda copia es código muerto, Laravel nunca la alcanza). Borrar el bloque `auth:sanctum` duplicado.

## 5. Cambios de frontend

- **Nuevo**: sección "Referidos pendientes" en `PatientDashboard.vue` (o página dedicada `Patient/MyReferrals.vue`, tu decisión de IA — dado que ya existe `MyAppointments.vue` como referencia de patrón, una página dedicada es más consistente).
  - Cada card: especialidad, motivo (`reason`), prioridad (`urgente` destacado en terracotta, coherente con la paleta Salvia), médico que refirió, y botón de acción.
  - Botón de acción resuelve el link: `referred_doctor_id` presente → `/booking/{doctorProfileId}`; si no → `/directory?specialty_id=X`.
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
  Entonces el referido pasa a status='accepted'
  Y ya no aparece en la lista de "pendientes" del dashboard
```

## 7. Preguntas abiertas — necesito tu decisión antes de tasks.md

| # | Pregunta | Opciones |
|---|----------|----------|
| ❓1 | ¿El referido pasa a `accepted` al agendar, o recién a `completed` cuando la consulta con el especialista termina? | (a) `accepted` al agendar — más simple, es lo que describe este brief. (b) Dos transiciones: `accepted` al agendar + `completed` al finalizar consulta — más fiel al ciclo de vida real, más trabajo. |
| ❓2 | ¿Página dedicada `Patient/MyReferrals.vue` o sección embebida en `PatientDashboard.vue`? | Afecta cuánto se toca el dashboard que ya armaste con Antigravity. |
| ❓3 | ¿Un referido `urgente` necesita algo visualmente distinto más allá del badge (ej. no puede "descartarse" sin agendar)? | Definilo ahora — evita retrabajo de UI después. |

## 8. Fuera del brief pero anotado para más adelante

- No hay sistema de notificaciones (push/email/in-app) — cuando se cree un referido, hoy el paciente solo se entera si entra a su dashboard. Si esto importa para la demo, es un brief propio.
- `RlsCoverageTest` prueba *que existe* RLS, no *que la policy deja pasar filas legítimas*. El bug P1 pasó desapercibido por eso. Vale la pena, en algún momento, agregar un test de "smoke" por tabla RLS que loguee como cada rol y confirme que ve exactamente lo que debería ver — no solo que RLS está prendido.
