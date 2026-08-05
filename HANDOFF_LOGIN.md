# Informe de traspaso — diagnóstico de login y trabajo restante

> **Para:** una sesión nueva que no tiene el contexto de las 21 auditorías previas.
> **Fecha:** 2026-08-04 · **Autor del informe:** auditoría externa
> **Leé esto completo antes de tocar un archivo.**

---

## 0. Lo que NO tenés que hacer

Este proyecto pasó por veintiún hallazgos de seguridad, y cada clase quedó convertida en
barrera automática. **El mecanismo de autenticación está verificado y funciona.** No lo
reescribas.

| No toques | Por qué |
|---|---|
| `SecureEloquentUserProvider` | Verificado. `AuthTest` hace `POST /login` real y pasa |
| `fn_user_for_auth`, `fn_user_by_remember_token`, `fn_rotate_remember_token` | Funciones `SECURITY DEFINER`. Dos primitivas de suplantación de cuenta se cerraron acá |
| `User::SELECTABLE_COLUMNS` | `password` y `remember_token` están fuera a propósito |
| Cualquier política RLS | 22 tablas. Hay barreras que fallan si se aflojan |
| El mensaje de error de credenciales | Genérico a propósito: anti-enumeración |
| Migraciones ya aplicadas | **Inmutables.** Todo cambio va en una migración nueva |

**Prohibido, sin excepción:** `migrate:refresh`, `migrate:fresh`, `migrate:reset`,
`DROP SCHEMA`, `DELETE FROM` sin `WHERE`, `git checkout --` sobre archivos que no estás
editando. Todos destruyen datos o trabajo. Si creés que hace falta uno, **pará y preguntá.**

---

## 1. El síntoma

El formulario de login devuelve *"Las credenciales ingresadas no son válidas"* al intentar
entrar con `euclidesm195@gmail.com`.

**Ese mensaje es idéntico para "el correo no existe" y "la contraseña es incorrecta", por
diseño** (anti-enumeración de usuarios). No indica cuál de los dos ocurrió.

---

## 2. Lo que ya está verificado — no lo re-diagnostiques

- `AuthTest` ejecuta `POST /login` real: inserta un usuario con `bcrypt($raw)` y envía
  `$raw`. Seis pruebas verdes, incluido login exitoso, anti-enumeración, usuario inactivo
  bloqueado, límite de intentos y redirección por rol.
- `DatabaseSeeder` crea cuatro cuentas, todas con contraseña `Password123!`:
  `admin@telemedicina.com` · `doctor@telemedicina.com` · `paciente@telemedicina.com` ·
  `agente@telemedicina.com`
- El correo del intento fallido **no es ninguna de las cuatro.**

---

## 3. Diagnóstico, en este orden. No salteés pasos.

**Paso 1 — ¿Existe el usuario?**

```sql
SELECT email, is_active, deleted_at, left(password, 7) AS hash_prefijo
FROM users ORDER BY created_at;
```

- Si la tabla está **vacía** → el seeder nunca corrió. Ir al paso 2.
- Si están las cuatro cuentas y **no** está `euclidesm195@gmail.com` → **no hay ningún
  fallo.** El usuario estaba usando un correo inexistente. Reportarlo y pasar a la
  sección 4.
- Si `euclidesm195@gmail.com` **sí existe** → ir al paso 3.

**Paso 2 — Correr el seeder.**

```
php artisan db:seed
```

El seeder corre sobre la conexión por defecto (`app_runtime`, sujeta a RLS) y fija
`app.current_user_role = 'admin'` para poder escribir. Eso es deliberado: un seeder que
corre como superusuario puede crear estados que RLS prohíbe, y entonces la demo vive en un
mundo imposible.

Si el seeder falla, **el error es el hallazgo** — no lo evadas elevando privilegios ni
cambiando la conexión a `pgsql_migration`. Reportá el error exacto.

**Paso 3 — Si el usuario existe y el login falla.** Verificar en este orden:

1. `is_active = true` y `deleted_at IS NULL`. `fn_user_for_auth` filtra por los dos, así
   que un usuario inactivo produce exactamente el mismo mensaje.
2. El prefijo del hash empieza con `$2y$`. Si el usuario se creó con SQL directo y un hash
   inventado, `Hash::check` falla siempre.
3. `SELECT * FROM fn_user_for_auth('el@correo.com');` — si devuelve cero filas, el problema
   está en los filtros de la función, no en el controlador.
4. Que exista fila en `user_roles` para ese usuario. Sin rol, el middleware asume `patient`
   y el panel puede quedar vacío por RLS aunque el login sí haya funcionado. **Un panel
   vacío no es un fallo de login.**

**Paso 4 — Si nada de lo anterior explica el fallo**, entonces sí hay un defecto real.
Escribí primero una prueba que lo reproduzca **en rojo**, y pegá la salida del fallo antes
de escribir la corrección. No arregles nada que no hayas visto fallar.

---

## 4. Reglas de trabajo, no negociables

1. **Nunca debilites una aserción ni eleves un privilegio para que algo pase.** Ese fue el
   patrón de los tres primeros hallazgos de esta serie. Si una prueba falla o una operación
   es denegada, la corrección es reparar lo que la prueba descubrió.
2. **La prueba en rojo va primero.** Una barrera que nunca se vio fallar es una barrera de
   valor desconocido.
3. **Toda migración aplicada es inmutable.** Los cambios van en migraciones nuevas.
4. **Un cambio de documento y el código que lo vuelve cierto van en el mismo commit**
   (regla G4.5). Nunca un commit donde el documento describa algo que no existe.
5. **Citá los requisitos con número Y título** copiados de la tabla del PRD. Un número
   suelto se equivoca en silencio: pasó cuatro veces.
6. **La primera línea de todo informe** lista los comandos que modificaron el esquema,
   borraron datos o tocaron archivos ya commiteados. Antes de lo que salió bien.
7. **El commit y el push los hace el humano.** Siempre.
8. Las reglas completas están en `agent/PROTOCOLO.md`. Leelo.

---

## 5. Un hueco declarado que conviene conocer

`phpunit.xml` fija `SESSION_DRIVER=array` y `CACHE_STORE=array`. **La suite no ejercita
sesión, caché ni colas.** Por eso `AuthTest` puede estar verde mientras el navegador falla:
son dos caminos distintos.

La verificación de sesión es **manual y de una sola vez**: iniciar sesión en el navegador,
recargar, y confirmar que la sesión persiste. Hacela con las cuatro cuentas y reportá el
resultado de cada una.

---

## 6. Trabajo restante, con los números reales del PRD

Verificados contra la tabla de `docs/PRD.md` — no los renumeres:

| RF | Título exacto | Estado |
|---|---|---|
| RF-12 | Pago con Stripe e Idempotencia | pendiente — **RF-25 tiene reglas de reembolso sin nada que cobrar** |
| RF-13 | Cuestionario Pre-consulta | pendiente |
| RF-14 | Consulta por Chat en Tiempo Real | pendiente |
| RF-15 | Nota SOAP (Borrador a Firmada) | pendiente |
| RF-16 | Firma Electrónica e Inmutabilidad | pendiente |
| RF-17 | Enmiendas Clínicas | pendiente |
| RF-18 | Generación de PDF y QR Clínico | pendiente |
| RF-19 | Acuse de Recibo de Paciente | pendiente |
| RF-23 | Asistente Informativo (Landing) | pendiente |
| RF-24 | Asistente Clínico (Dashboard) | pendiente |

Y una tarea que desbloquea el panel:

**Contrato de props de las páginas Inertia.** `/admin` es hoy una closure sin props
(`Route::get('/admin', fn() => Inertia::render('Dashboard'))`), y ninguna página del panel
declara `defineProps`: los datos están escritos a mano dentro de los componentes. El
contrato tiene que escribirse en `docs/API_CONTRACTS.md` **antes** de escribir controladores.

**Un módulo por entrega.** No cinco.

---

## 7. Deuda declarada

| # | Qué | Por qué importa |
|---|---|---|
| H6 | Faltan triggers de auditoría en `user_roles`, `user_permissions`, `payments`, `users` | Las escrituras más sensibles no dejan rastro |
| H8 | Las tablas son propiedad de `postgres`, no de `app_owner` | El rol superior del modelo de tres roles es decorativo |
| H10 | `GRANT UPDATE` sin alcance de columnas en 13 tablas | RLS responde "cuáles filas", nunca "cuáles columnas" |
| — | La suite no cubre sesión, caché ni colas | Ver sección 5 |
