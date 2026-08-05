<!--
  ====================================================================
  SettingsManager — Admin system settings + user management
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';

interface UserInfo {
  id: string;
  name: string;
  last_name: string;
  email: string;
  timezone: string;
  role: string;
  created_at: string;
}

const activeTab = ref<'users' | 'system'>('users');
const loading = ref(true);
const users = ref<UserInfo[]>([]);
const error = ref('');
const successMsg = ref('');
const searchQuery = ref('');
const filterRole = ref('all');

// Password modal
const pwdModal = ref(false);
const pwdUserId = ref('');
const pwdUserName = ref('');
const pwdNew = ref('');
const pwdConfirm = ref('');
const pwdSaving = ref(false);

// Role modal
const roleModal = ref(false);
const roleUserId = ref('');
const roleUserName = ref('');
const roleNew = ref('');
const roleSaving = ref(false);

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

const filteredUsers = computed(() => {
  let result = users.value;
  if (filterRole.value !== 'all') {
    result = result.filter(u => u.role === filterRole.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    result = result.filter(u =>
      `${u.name} ${u.last_name}`.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q)
    );
  }
  return result;
});

const roleCounts = computed(() => {
  const counts: Record<string, number> = { all: users.value.length };
  users.value.forEach(u => {
    counts[u.role] = (counts[u.role] || 0) + 1;
  });
  return counts;
});

async function fetchUsers() {
  loading.value = true;
  error.value = '';
  try {
    const res = await fetch('/api/admin/users', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    users.value = data.data || [];
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchUsers);

function openPasswordModal(user: UserInfo) {
  pwdUserId.value = user.id;
  pwdUserName.value = `${user.name} ${user.last_name}`;
  pwdNew.value = '';
  pwdConfirm.value = '';
  pwdModal.value = true;
}

async function changePassword() {
  if (pwdNew.value.length < 8) {
    error.value = 'La contraseña debe tener al menos 8 caracteres';
    return;
  }
  if (pwdNew.value !== pwdConfirm.value) {
    error.value = 'Las contraseñas no coinciden';
    return;
  }

  pwdSaving.value = true;
  error.value = '';
  try {
    const res = await fetch(`/api/admin/users/${pwdUserId.value}/password`, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        password: pwdNew.value,
        password_confirmation: pwdConfirm.value,
      }),
    });

    if (res.ok) {
      successMsg.value = `✅ Contraseña de ${pwdUserName.value} actualizada`;
      pwdModal.value = false;
      setTimeout(() => { successMsg.value = ''; }, 4000);
    } else {
      const data = await res.json().catch(() => ({}));
      error.value = data.message || 'Error al cambiar contraseña';
    }
  } catch (e: any) {
    error.value = e.message;
  } finally {
    pwdSaving.value = false;
  }
}

function openRoleModal(user: UserInfo) {
  roleUserId.value = user.id;
  roleUserName.value = `${user.name} ${user.last_name}`;
  roleNew.value = user.role;
  roleModal.value = true;
}

async function changeRole() {
  roleSaving.value = true;
  error.value = '';
  try {
    const res = await fetch(`/api/admin/users/${roleUserId.value}/role`, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({ role: roleNew.value }),
    });

    if (res.ok) {
      successMsg.value = `✅ Rol de ${roleUserName.value} actualizado a "${roleNew.value}"`;
      roleModal.value = false;
      await fetchUsers();
      setTimeout(() => { successMsg.value = ''; }, 4000);
    } else {
      const data = await res.json().catch(() => ({}));
      error.value = data.message || 'Error al cambiar rol';
    }
  } catch (e: any) {
    error.value = e.message;
  } finally {
    roleSaving.value = false;
  }
}

function getRoleLabel(role: string) {
  return { admin: 'Administrador', doctor: 'Médico', patient: 'Paciente', agent: 'Agente' }[role] || role;
}

function getRoleClass(role: string) {
  return {
    admin: 'role-badge--admin',
    doctor: 'role-badge--doctor',
    patient: 'role-badge--patient',
    agent: 'role-badge--agent',
  }[role] || '';
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-ES', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
}
</script>

<template>
  <AppLayout>
    <div class="settings-mgr">
      <header class="settings-mgr__header">
        <div>
          <h1 class="settings-mgr__title">⚙️ Ajustes del Sistema</h1>
          <p class="settings-mgr__subtitle">Gestiona usuarios, contraseñas y configuraciones</p>
        </div>
      </header>

      <!-- Alerts -->
      <Transition name="fade">
        <div v-if="successMsg" class="alert alert--success">{{ successMsg }}</div>
      </Transition>
      <Transition name="fade">
        <div v-if="error" class="alert alert--error">
          {{ error }}
          <button class="alert__close" @click="error = ''">×</button>
        </div>
      </Transition>

      <!-- Tab Navigation -->
      <div class="tab-nav">
        <button :class="['tab-btn', { 'tab-btn--active': activeTab === 'users' }]" @click="activeTab = 'users'">
          <i class="pi pi-users"></i> Gestión de Usuarios
        </button>
        <button :class="['tab-btn', { 'tab-btn--active': activeTab === 'system' }]" @click="activeTab = 'system'">
          <i class="pi pi-cog"></i> Configuración
        </button>
      </div>

      <!-- Users Tab -->
      <div v-if="activeTab === 'users'">
        <!-- Search + Filter -->
        <div class="toolbar">
          <div class="search-box">
            <i class="pi pi-search"></i>
            <input v-model="searchQuery" class="search-input" placeholder="Buscar por nombre o correo..." />
          </div>
          <div class="role-filters">
            <button
              v-for="tab in [
                { key: 'all', label: 'Todos' },
                { key: 'admin', label: 'Admin' },
                { key: 'doctor', label: 'Médicos' },
                { key: 'patient', label: 'Pacientes' },
              ]"
              :key="tab.key"
              :class="['filter-pill', { 'filter-pill--active': filterRole === tab.key }]"
              @click="filterRole = tab.key"
            >
              {{ tab.label }}
              <span class="filter-pill__count">{{ roleCounts[tab.key] || 0 }}</span>
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="loading-state">
          <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: var(--color-primary, #0E5D52);"></i>
          <p>Cargando usuarios...</p>
        </div>

        <!-- Users Table -->
        <div v-else class="users-table-wrap">
          <table class="users-table">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Zona Horaria</th>
                <th>Registrado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in filteredUsers" :key="user.id">
                <td>
                  <div class="user-cell">
                    <div class="user-cell__avatar" :style="{ backgroundColor: getAvatarColor(user.name + ' ' + (user.last_name || '')) }">
                      {{ getInitials(user.name + ' ' + (user.last_name || '')) }}
                    </div>
                    <span class="user-cell__name">{{ user.name }} {{ user.last_name }}</span>
                  </div>
                </td>
                <td class="td-email">{{ user.email }}</td>
                <td>
                  <span :class="['role-badge', getRoleClass(user.role)]">
                    {{ getRoleLabel(user.role) }}
                  </span>
                </td>
                <td class="td-tz">{{ user.timezone || '—' }}</td>
                <td class="td-date">{{ formatDate(user.created_at) }}</td>
                <td>
                  <div class="action-group">
                    <button class="icon-btn" title="Cambiar contraseña" @click="openPasswordModal(user)">
                      <i class="pi pi-key"></i>
                    </button>
                    <button class="icon-btn" title="Cambiar rol" @click="openRoleModal(user)">
                      <i class="pi pi-user-edit"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredUsers.length === 0">
                <td colspan="6" class="td-empty">No se encontraron usuarios</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- System Config Tab -->
      <div v-if="activeTab === 'system'" class="system-config">
        <div class="config-card">
          <h3><i class="pi pi-globe"></i> Información del Sistema</h3>
          <div class="config-grid">
            <div class="config-item">
              <span class="config-label">Plataforma</span>
              <span class="config-value">Salvia Telemedicina v1.0</span>
            </div>
            <div class="config-item">
              <span class="config-label">Backend</span>
              <span class="config-value">Laravel 12 + PHP 8.4</span>
            </div>
            <div class="config-item">
              <span class="config-label">Frontend</span>
              <span class="config-value">Vue 3 + Inertia.js</span>
            </div>
            <div class="config-item">
              <span class="config-label">Base de datos</span>
              <span class="config-value">PostgreSQL 17 (RLS)</span>
            </div>
            <div class="config-item">
              <span class="config-label">Usuarios registrados</span>
              <span class="config-value">{{ users.length }}</span>
            </div>
            <div class="config-item">
              <span class="config-label">Zona horaria del servidor</span>
              <span class="config-value">UTC</span>
            </div>
          </div>
        </div>

        <div class="config-card">
          <h3><i class="pi pi-shield"></i> Seguridad</h3>
          <div class="config-grid">
            <div class="config-item">
              <span class="config-label">Row Level Security</span>
              <span class="config-value config-value--ok">✅ Activo</span>
            </div>
            <div class="config-item">
              <span class="config-label">Autenticación</span>
              <span class="config-value config-value--ok">✅ Sesión + CSRF</span>
            </div>
            <div class="config-item">
              <span class="config-label">Encriptación contraseñas</span>
              <span class="config-value config-value--ok">✅ Bcrypt (12 rounds)</span>
            </div>
            <div class="config-item">
              <span class="config-label">Multi-tenant DB roles</span>
              <span class="config-value config-value--ok">✅ app_runtime / app_owner / app_worker</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Password Modal -->
      <Teleport to="body">
        <Transition name="fade">
          <div v-if="pwdModal" class="modal-overlay" @click.self="pwdModal = false">
            <div class="modal-card">
              <h3 class="modal-title">🔑 Cambiar Contraseña</h3>
              <p class="modal-subtitle">{{ pwdUserName }}</p>

              <div class="modal-form">
                <div class="form-group">
                  <label class="form-label">Nueva contraseña</label>
                  <input v-model="pwdNew" type="password" class="form-input" placeholder="Mínimo 8 caracteres" />
                </div>
                <div class="form-group">
                  <label class="form-label">Confirmar contraseña</label>
                  <input v-model="pwdConfirm" type="password" class="form-input" placeholder="Repite la contraseña" />
                </div>
              </div>

              <div class="modal-actions">
                <button class="btn-secondary" @click="pwdModal = false">Cancelar</button>
                <button class="btn-primary" @click="changePassword" :disabled="pwdSaving">
                  {{ pwdSaving ? 'Guardando...' : 'Cambiar Contraseña' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>

      <!-- Role Modal -->
      <Teleport to="body">
        <Transition name="fade">
          <div v-if="roleModal" class="modal-overlay" @click.self="roleModal = false">
            <div class="modal-card">
              <h3 class="modal-title">👤 Cambiar Rol</h3>
              <p class="modal-subtitle">{{ roleUserName }}</p>

              <div class="modal-form">
                <div class="form-group">
                  <label class="form-label">Nuevo rol</label>
                  <select v-model="roleNew" class="form-select">
                    <option value="admin">Administrador</option>
                    <option value="doctor">Médico</option>
                    <option value="patient">Paciente</option>
                    <option value="agent">Agente</option>
                  </select>
                </div>
              </div>

              <div class="modal-actions">
                <button class="btn-secondary" @click="roleModal = false">Cancelar</button>
                <button class="btn-primary" @click="changeRole" :disabled="roleSaving">
                  {{ roleSaving ? 'Guardando...' : 'Actualizar Rol' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
    </div>
  </AppLayout>
</template>

<style scoped>
.settings-mgr { max-width: 1100px; margin: 0 auto; padding: 1rem; }

.settings-mgr__header { margin-bottom: 1.5rem; }
.settings-mgr__title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.settings-mgr__subtitle { font-size: 0.875rem; color: #6B7280; margin: 4px 0 0; }

.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
.alert--success { background: #D1FAE5; color: #065F46; }
.alert--error { background: #FEE2E2; color: #991B1B; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; }

.tab-nav { display: flex; gap: 4px; margin-bottom: 1.5rem; border-bottom: 2px solid #E5E7EB; }
.tab-btn {
  padding: 10px 20px; background: none; border: none; border-bottom: 2px solid transparent;
  margin-bottom: -2px; font-size: 0.9rem; color: #6B7280; cursor: pointer;
  display: flex; align-items: center; gap: 6px; transition: all 0.2s;
}
.tab-btn--active { color: var(--color-primary, #0E5D52); border-bottom-color: var(--color-primary, #0E5D52); font-weight: 600; }
.tab-btn:hover { color: #111827; }

.toolbar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; }
.search-box {
  display: flex; align-items: center; gap: 8px; background: #FFF;
  border: 1px solid #D1D5DB; border-radius: 8px; padding: 6px 12px; flex: 1; min-width: 200px;
}
.search-box i { color: #9CA3AF; font-size: 0.85rem; }
.search-input { border: none; outline: none; font-size: 0.9rem; width: 100%; background: transparent; }

.role-filters { display: flex; gap: 4px; flex-wrap: wrap; }
.filter-pill {
  padding: 6px 14px; background: #F3F4F6; border: 1px solid transparent;
  border-radius: 20px; font-size: 0.8rem; color: #374151; cursor: pointer;
  transition: all 0.2s; display: flex; align-items: center; gap: 4px;
}
.filter-pill--active { background: var(--color-primary, #0E5D52); color: #FFF; }
.filter-pill__count {
  background: rgba(0,0,0,0.08); padding: 0px 6px; border-radius: 8px; font-size: 0.72rem; font-weight: 700;
}
.filter-pill--active .filter-pill__count { background: rgba(255,255,255,0.25); }

.loading-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 3rem; color: #6B7280; }

.users-table-wrap { background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.users-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.users-table thead { background: #F9FAFB; }
.users-table th { padding: 12px 16px; text-align: left; font-weight: 600; color: #374151; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
.users-table td { padding: 12px 16px; border-top: 1px solid #F3F4F6; color: #374151; }
.users-table tbody tr:hover { background: #FAFAFA; }

.user-cell { display: flex; align-items: center; gap: 10px; }
.user-cell__avatar {
  width: 34px; height: 34px; border-radius: 50%; display: flex;
  align-items: center; justify-content: center; color: #FFF;
  font-weight: 700; font-size: 0.72rem; flex-shrink: 0;
}
.user-cell__name { font-weight: 500; white-space: nowrap; }
.td-email { color: #6B7280; font-size: 0.82rem; }
.td-tz { font-size: 0.78rem; color: #6B7280; }
.td-date { font-size: 0.78rem; color: #9CA3AF; }
.td-empty { text-align: center; color: #9CA3AF; padding: 2rem !important; }

.role-badge {
  display: inline-block; padding: 2px 10px; border-radius: 10px;
  font-size: 0.72rem; font-weight: 600;
}
.role-badge--admin { background: #EDE9FE; color: #6D28D9; }
.role-badge--doctor { background: #DBEAFE; color: #1D4ED8; }
.role-badge--patient { background: #D1FAE5; color: #065F46; }
.role-badge--agent { background: #FEF3C7; color: #92400E; }

.action-group { display: flex; gap: 6px; }
.icon-btn {
  width: 32px; height: 32px; border-radius: 6px; border: 1px solid #E5E7EB;
  background: #FFF; color: #374151; cursor: pointer; display: flex;
  align-items: center; justify-content: center; transition: all 0.2s;
}
.icon-btn:hover { background: #F3F4F6; border-color: var(--color-primary, #0E5D52); color: var(--color-primary, #0E5D52); }

/* System Config */
.system-config { display: flex; flex-direction: column; gap: 1.5rem; }
.config-card {
  background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px;
  padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.config-card h3 { margin: 0 0 16px; font-size: 1.05rem; color: #111827; display: flex; align-items: center; gap: 8px; }
.config-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
.config-item { display: flex; flex-direction: column; gap: 2px; padding: 10px; background: #F9FAFB; border-radius: 8px; }
.config-label { font-size: 0.78rem; color: #6B7280; font-weight: 500; }
.config-value { font-size: 0.88rem; color: #111827; font-weight: 600; }
.config-value--ok { color: #065F46; }

/* Modals */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex;
  align-items: center; justify-content: center; z-index: 9999;
}
.modal-card {
  background: #FFF; border-radius: 14px; padding: 28px; width: 90%;
  max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.modal-title { margin: 0 0 4px; font-size: 1.15rem; color: #111827; }
.modal-subtitle { margin: 0 0 20px; color: #6B7280; font-size: 0.9rem; }
.modal-form { display: flex; flex-direction: column; gap: 12px; }
.modal-actions { margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }

.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 0.82rem; font-weight: 600; color: #374151; }
.form-input, .form-select {
  padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px;
  font-size: 0.9rem; outline: none; transition: border-color 0.2s;
}
.form-input:focus, .form-select:focus { border-color: var(--color-primary, #0E5D52); }

.btn-primary {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 10px 20px; background: var(--color-primary, #0E5D52); color: #FFF;
  border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.btn-primary:hover:not(:disabled) { filter: brightness(1.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary {
  padding: 10px 20px; background: #F3F4F6; color: #374151;
  border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem;
  font-weight: 500; cursor: pointer;
}
.btn-secondary:hover { background: #E5E7EB; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 768px) {
  .toolbar { flex-direction: column; }
  .users-table th:nth-child(4), .users-table td:nth-child(4) { display: none; }
}
</style>
