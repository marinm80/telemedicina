# 🏥 Plataforma de Telemedicina de Alto Rendimiento & Seguridad RLS

Sistema web de telemedicina con arquitectura **Laravel 11 (Inertia.js + Vue 3 + TypeScript)** y base de datos **PostgreSQL con Row Level Security (RLS)** estricto a nivel de fila y permisos de columna.

---

## 🔑 Credenciales de Demostración (Demo Accounts)

Para evaluar y navegar el sistema de punta a punta en entorno local o de producción, el seeder principal (`DatabaseSeeder.php`) provee **cuatro cuentas con roles diferenciados**. Todas las cuentas comparten la misma contraseña de prueba:

> **Contraseña única para todas las cuentas de demostración:** `Password123!`

| Rol | Correo Electrónico | Descripción & Datos de Perfil | Redirección Post-Login |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin@telemedicina.com` | Acceso completo de gestión, auditoría global y supervisión. | `/admin` |
| **Médico** | `doctor@telemedicina.com` | Dr. Carlos Mendoza. Perfil aprobado (`approved`), especialidad **Cardiología**, franja horaria recurrente (Lunes 09:00 - 17:00). | `/admin` |
| **Paciente** | `paciente@telemedicina.com` | María González. Perfil de paciente completo (teléfono, fecha nacimiento, dirección en Santiago). | `/admin` |
| **Agente** | `agente@telemedicina.com` | Sofía López. Recepcionista/Agente de soporte para coordinación de citas. | `/admin` |

---

## 🛠️ Comandos de Inicialización & Seeding

Para cargar las cuentas de demostración en el entorno local manteniendo el aislamiento RLS:

```bash
# Ejecutar migraciones del sistema
docker compose exec backend php artisan migrate --database=pgsql_migration

# Cargar Seeder de Demostración (ejercita RLS bajo conexión app_runtime con contexto admin)
docker compose exec backend php artisan db:seed
```

---

## 🛡️ Arquitectura de Seguridad RLS en PostgreSQL

1. **Aislamiento Multi-inquilino por RLS:** Las tablas sensibles (`patient_profiles`, `doctor_profiles`, `schedules`, `appointments`, `consultation_notes`) aplican `ROW LEVEL SECURITY` directamente en PostgreSQL.
2. **Restricción de Columnas:** Las columnas sensibles como `users.password` y `users.remember_token` no son accesibles vía `SELECT` directo para `app_runtime`; se gestionan a través de funciones estables `SECURITY DEFINER` (`fn_user_for_auth`, `fn_update_remember_token`).
3. **Auditoría Automática:** Triggers transparentes en PostgreSQL registran cambios en `audit_logs` con el `app.current_user_id` de la sesión.

---

## 🎨 Sistema Visual — Paleta Salvia

La interfaz utiliza el design system **"Salvia"** — una paleta médica profesional basada en tonos teal oscuro, sage, terracotta y crema cálido.

| Elemento | Color | Uso |
|----------|-------|-----|
| 🌿 Primary | `#17302B` / `#0E5D52` | Sidebar, botones, encabezados |
| 🍃 Accent | `#8FC9B3` | Badges, indicadores activos |
| 🔥 Alert | `#D9603E` | Notificaciones urgentes, terracotta |
| 🧈 Surface | `#FAF5EE` / `#FFFFFF` | Fondos crema cálido, cards |

**4 Dashboards por rol:**
- **Admin**: Verificación de médicos, KPIs globales, monitoreo del sistema
- **Doctor**: Agenda del día, notas clínicas, pacientes activos
- **Paciente**: Mis citas, recetas, historial de consultas
- **Agente**: Citas pendientes, acciones rápidas de coordinación

> El admin puede visualizar cualquier dashboard usando el **role switcher** del sidebar.

---

## 📄 Documentación

| Documento | Descripción |
|-----------|-------------|
| [`docs/PRD.md`](docs/PRD.md) | Especificación de Producto v2.0 |
| [`docs/DATABASE_SCHEMA.md`](docs/DATABASE_SCHEMA.md) | DDL, índices y políticas RLS |
| [`docs/AUTHORIZATION.md`](docs/AUTHORIZATION.md) | Protocolo de seguridad y autorización |
| [`docs/MAPA_ARQUITECTURA.md`](docs/MAPA_ARQUITECTURA.md) | Estructura de carpetas y dependencias |
| [`docs/UI_PROTOTYPE.md`](docs/UI_PROTOTYPE.md) | Wireframes, árbol de componentes, 4 estados |
| [`docs/UI_COMPONENTS.md`](docs/UI_COMPONENTS.md) | Catálogo de componentes Salvia (props, slots, tokens) |
| [`docs/CHANGELOG.md`](docs/CHANGELOG.md) | Log de cambios (Keep a Changelog) |
| [`docs/DECISIONES_ALCANCE.md`](docs/DECISIONES_ALCANCE.md) | Alcance y trade-offs |
| [`docs/walkthrough.md`](docs/walkthrough.md) | Resumen de entregas implementadas |

