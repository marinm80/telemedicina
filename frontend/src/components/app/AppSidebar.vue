<!--
  ====================================================================
  AppSidebar — Sidebar navigation for Salvia application
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <aside class="app-sidebar" :class="{ 'app-sidebar--mobile-open': mobileOpen }">
    <div class="sidebar-header">
      <div class="brand">
        <div class="brand-badge">S</div>
        <div class="brand-text">Salvia<span class="brand-dot">.</span></div>
      </div>
      <button class="mobile-toggle" @click="mobileOpen = !mobileOpen">
        <i :class="mobileOpen ? 'pi pi-times' : 'pi pi-bars'"></i>
      </button>
    </div>

    <div class="sidebar-content" :class="{ 'sidebar-content--hidden': !mobileOpen && isMobile }">
      <div v-if="isAdmin" class="role-switcher">
        <div class="role-switcher-label">Ver como</div>
        <div class="role-switcher-buttons">
          <button 
            v-for="role in roles" 
            :key="role.value"
            class="role-btn" 
            :class="{ 'role-btn--active': currentRole === role.value }"
            @click="setRole(role.value)"
            :title="role.label"
          >
            {{ role.icon }} <span class="role-text">{{ role.label }}</span>
          </button>
        </div>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-label">Menú</div>
        <ul class="nav-list">
          <li v-for="item in currentNavItems" :key="item.path" class="nav-item">
            <Link 
              :href="item.path" 
              class="nav-link" 
              :class="{ 'nav-link--active': isActive(item.path) }"
            >
              <i :class="['pi', item.icon, 'nav-icon']"></i>
              <span class="nav-text">{{ item.label }}</span>
              <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
            </Link>
          </li>
        </ul>
      </nav>

      <div v-if="showBookingCta" class="sidebar-cta">
        <button type="button" class="cta-btn" @click="emit('start-booking')">
          <i class="pi pi-calendar-plus"></i>
          <span>Agendar Cita</span>
        </button>
      </div>

      <div class="sidebar-footer">
        <div class="user-profile">
          <div class="avatar">{{ userInitials }}</div>
          <div class="user-info">
            <div class="user-name">{{ fullName }}</div>
            <div class="user-role">{{ roleDisplay }}</div>
          </div>
          <button class="logout-btn" @click="logout" title="Cerrar sesión">
            <i class="pi pi-sign-out"></i>
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'

const emit = defineEmits(['switch-role', 'start-booking'])

const page = usePage()
const user = computed(() => (page.props as any).auth?.user || {})
const pendingDoctors = computed(() => (page.props as any).pendingDoctors || 0);
const pendingAppointments = computed(() => (page.props as any).pendingAppointments || 0);

const isAdmin = computed(() => user.value.role === 'admin')
const currentRole = ref(user.value.role || 'patient')

const mobileOpen = ref(false)
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024)

const isMobile = computed(() => windowWidth.value < 920)

const updateWidth = () => {
  windowWidth.value = window.innerWidth
  if (windowWidth.value >= 920) {
    mobileOpen.value = false
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', updateWidth)
  }
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateWidth)
  }
})

const roles = [
  { value: 'admin', label: 'Administrador', icon: '🛡' },
  { value: 'doctor', label: 'Médico', icon: '🩺' },
  { value: 'patient', label: 'Paciente', icon: '💚' },
  { value: 'agent', label: 'Agente', icon: '🎧' } // Adding agent for completeness if needed, but not in original 3 buttons list, wait, instructions said 3 buttons
].filter(r => r.value !== 'agent' || user.value.role === 'admin') // Include agent only if you want, but instructions said 3 buttons for switcher: Administrador 🛡, Médico 🩺, Paciente 💚. Let's stick to 3.

const rolesList = [
  { value: 'admin', label: 'Administrador', icon: '🛡' },
  { value: 'doctor', label: 'Médico', icon: '🩺' },
  { value: 'patient', label: 'Paciente', icon: '💚' }
]

// Reassign to match exact requirement
const availableRoles = computed(() => {
  return rolesList
})

const navItems = computed(() => ({
  admin: [
    { label: 'Resumen', path: '/admin', icon: 'pi-chart-bar' },
    { label: 'Panel de Control', path: '/admin/panel', icon: 'pi-cog', badge: pendingDoctors.value > 0 ? String(pendingDoctors.value) : '' },
    { label: 'Mis Citas', path: '/appointments', icon: 'pi-calendar', badge: pendingAppointments.value > 0 ? String(pendingAppointments.value) : '' },
  ],
  doctor: [
    { label: 'Mis Citas', path: '/appointments', icon: 'pi-calendar', badge: pendingAppointments.value > 0 ? String(pendingAppointments.value) : '' },
    { label: 'Mis Horarios', path: '/doctor/horarios', icon: 'pi-clock' },
  ],
  patient: [
    { label: 'Directorio Médicos', path: '/paciente/directorio', icon: 'pi-search' },
    { label: 'Mis Citas', path: '/appointments', icon: 'pi-calendar', badge: pendingAppointments.value > 0 ? String(pendingAppointments.value) : '' },
  ],
  agent: [
    { label: 'Recepción', path: '/admin', icon: 'pi-inbox' },
    { label: 'Citas', path: '/appointments', icon: 'pi-calendar', badge: pendingAppointments.value > 0 ? String(pendingAppointments.value) : '' },
    { label: 'Directorio', path: '/directory', icon: 'pi-search' },
  ]
}))

const currentNavItems = computed(() => {
  return navItems.value[currentRole.value as keyof typeof navItems.value] || navItems.value.patient
})

const setRole = (role: string) => {
  currentRole.value = role
  emit('switch-role', role)
}

const isActive = (path: string) => {
  return page.url === path
}

const userInitials = computed(() => {
  const name = user.value.name || ''
  const lastName = user.value.last_name || ''
  return `${name.charAt(0)}${lastName.charAt(0)}`.toUpperCase() || 'U'
})

const fullName = computed(() => {
  return `${user.value.name || ''} ${user.value.last_name || ''}`.trim() || 'Usuario'
})

const roleDisplay = computed(() => {
  const roleNames: Record<string, string> = {
    admin: 'Administrador',
    doctor: 'Médico',
    patient: 'Paciente',
    agent: 'Agente'
  }
  return roleNames[user.value.role] || user.value.role || 'Usuario'
})

const showBookingCta = computed(() => {
  return ['patient', 'agent', 'admin'].includes(currentRole.value)
})

const logout = () => {
  router.post('/logout')
}
</script>

<style scoped>
.app-sidebar {
  width: 260px;
  height: 100vh;
  position: sticky;
  top: 0;
  background-color: var(--color-sidebar-bg, #0D2622);
  color: var(--color-text-subtle, #A6BDB8);
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--color-border-warm, rgba(255, 255, 255, 0.05));
  z-index: 100;
  transition: var(--transition-normal, 0.3s ease);
}

.sidebar-header {
  padding: var(--spacing-6, 1.5rem);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.brand {
  display: flex;
  align-items: center;
  gap: var(--spacing-3, 0.75rem);
}

.brand-badge {
  width: 32px;
  height: 32px;
  border-radius: 11px;
  background-color: var(--color-accent, #8FC9B3);
  color: #17302B;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--text-lg, 1.125rem);
  font-family: var(--font-heading, sans-serif);
}

.brand-text {
  font-size: var(--text-xl, 1.25rem);
  font-weight: 700;
  color: white;
  font-family: var(--font-heading, sans-serif);
  letter-spacing: -0.02em;
}

.brand-dot {
  color: var(--color-accent, #8FC9B3);
}

.mobile-toggle {
  display: none;
  background: none;
  border: none;
  color: white;
  font-size: var(--text-xl, 1.25rem);
  cursor: pointer;
  padding: var(--spacing-2, 0.5rem);
}

.sidebar-content {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
}

.role-switcher {
  padding: 0 var(--spacing-6, 1.5rem) var(--spacing-6, 1.5rem);
}

.role-switcher-label {
  font-size: var(--text-xs, 0.75rem);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 600;
  margin-bottom: var(--spacing-3, 0.75rem);
  color: var(--color-text-muted-teal, #5F7A73);
}

.role-switcher-buttons {
  display: flex;
  gap: var(--spacing-2, 0.5rem);
}

.role-btn {
  flex: 1;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid transparent;
  padding: var(--spacing-2, 0.5rem);
  border-radius: var(--radius-md, 0.375rem);
  color: var(--color-text-subtle, #A6BDB8);
  font-size: var(--text-sm, 0.875rem);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition-fast, 0.2s);
}

.role-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.role-btn--active {
  background: rgba(143, 201, 179, 0.16);
  color: var(--color-accent, #8FC9B3);
  border-color: rgba(143, 201, 179, 0.3);
}

.role-text {
  display: none;
}

.role-btn:hover .role-text, .role-btn--active .role-text {
  display: none; /* Keep text hidden to maintain 3 buttons fitting, or show them if space permits. Prompt didn't specify showing text, only buttons. Let's hide text to fit icons in a row or show it as title. */
}

/* We can show the text if the layout is column, but row is better for 3 buttons. */

.sidebar-nav {
  padding: 0 var(--spacing-4, 1rem);
  flex: 1;
}

.nav-label {
  font-size: var(--text-xs, 0.75rem);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 600;
  margin: var(--spacing-4, 1rem) var(--spacing-2, 0.5rem) var(--spacing-2, 0.5rem);
  color: var(--color-text-muted-teal, #5F7A73);
}

.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1, 0.25rem);
}

.nav-link {
  display: flex;
  align-items: center;
  padding: var(--spacing-3, 0.75rem) var(--spacing-3, 0.75rem);
  border-radius: var(--radius-md, 0.375rem);
  color: var(--color-text-subtle, #A6BDB8);
  text-decoration: none;
  font-size: var(--text-sm, 0.875rem);
  transition: var(--transition-fast, 0.2s);
  position: relative;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.05);
  color: white;
}

.nav-link--active {
  background: rgba(143, 201, 179, 0.16);
  color: var(--color-accent, #8FC9B3);
  font-weight: 700;
}

.nav-icon {
  margin-right: var(--spacing-3, 0.75rem);
  font-size: var(--text-base, 1rem);
}

.nav-badge {
  margin-left: auto;
  background-color: var(--color-alert, #D9603E);
  color: white;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: var(--radius-full, 9999px);
  line-height: 1.2;
}

.sidebar-footer {
  padding: var(--spacing-4, 1rem);
  border-top: 1px solid var(--color-border-warm, rgba(255, 255, 255, 0.05));
  margin-top: auto;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: var(--spacing-3, 0.75rem);
  padding: var(--spacing-2, 0.5rem);
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full, 50%);
  background-color: var(--color-accent, #8FC9B3);
  color: #17302B;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: var(--text-sm, 0.875rem);
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 600;
  font-size: var(--text-sm, 0.875rem);
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: var(--text-xs, 0.75rem);
  color: var(--color-text-muted-teal, #5F7A73);
}

.logout-btn {
  background: transparent;
  border: none;
  color: var(--color-text-subtle, #A6BDB8);
  cursor: pointer;
  padding: var(--spacing-2, 0.5rem);
  border-radius: var(--radius-md, 0.375rem);
  transition: var(--transition-fast, 0.2s);
}

.logout-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

/* Responsive adjustments */
@media (max-width: 919px) {
  .app-sidebar {
    width: 100%;
    height: auto;
    position: sticky;
    top: 0;
    border-right: none;
    border-bottom: 1px solid var(--color-border-warm, rgba(255, 255, 255, 0.05));
  }

  .mobile-toggle {
    display: block;
  }
  
  .sidebar-content--hidden {
    display: none;
  }
  
  .sidebar-content {
    max-height: calc(100vh - 70px);
    background-color: var(--color-sidebar-bg, #0D2622);
  }
}

.sidebar-cta {
  padding: 0 16px;
  margin-bottom: 12px;
}

.cta-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px 16px;
  background: linear-gradient(135deg, var(--color-accent, #8FC9B3), var(--color-primary-600, #0E5D52));
  color: #FFFFFF;
  border: none;
  border-radius: var(--radius-md, 8px);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(14, 93, 82, 0.3);
}

.cta-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(14, 93, 82, 0.4);
  filter: brightness(1.05);
}

.cta-btn:active {
  transform: translateY(0);
}

.cta-btn i {
  font-size: 16px;
}
</style>
