<!--
  ====================================================================
  AdminDashboard — Panel admin con tabla de citas paginada + buscador
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <AppLayout>
    <DashboardHeader
      eyebrow="Panel de administración"
      title="Toda la clínica en una vista"
      subtitle="Supervisión de citas, cancelaciones y salud del sistema."
      status-text="Sistema operativo · 99,8 %"
      action-text="Panel de Control"
    />

    <!-- Stats row -->
    <div class="stats-grid">
      <StatCard icon="pi-users" label="Usuarios activos" :value="total_users.toString()" :trend="`${total_users} registrados`" trendType="neutral" />
      <StatCard icon="pi-verified" label="Médicos por verificar" :value="pending_doctor_approvals.toString()" :trend="pending_doctor_approvals > 0 ? 'Requieren atención' : 'Todo al día'" :trendType="pending_doctor_approvals > 0 ? 'negative' : 'positive'" iconBg="var(--color-alert)" />
      <StatCard icon="pi-calendar" label="Citas del mes" :value="monthly_appointments_count.toString()" :trend="`${pending_appointments_count} pendientes`" trendType="neutral" />
      <StatCard icon="pi-times-circle" label="Cancelaciones del mes" :value="cancelled_count.toString()" :trend="`${completed_count} completadas`" :trendType="cancelled_count > 0 ? 'negative' : 'positive'" iconBg="var(--color-error-600, #DC2626)" />
    </div>

    <div class="dashboard-content">
      <div class="dashboard-main">
        <!-- ═══ APPOINTMENTS TABLE WITH SEARCH + PAGINATION ═══ -->
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-calendar"></i> Todas las Citas</h2>
            <span class="section-count">{{ appointments_total }} resultado{{ appointments_total !== 1 ? 's' : '' }}</span>
          </div>

          <!-- Search + Status filter bar -->
          <div class="search-bar">
            <div class="search-input-wrap">
              <i class="pi pi-search"></i>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Buscar por médico o paciente..."
                class="search-input"
                @keydown.enter="doSearch"
              />
              <button v-if="searchQuery" class="search-clear" @click="searchQuery = ''; doSearch()">
                <i class="pi pi-times"></i>
              </button>
            </div>
            <select v-model="statusFilterLocal" class="status-select" @change="doSearch">
              <option value="">Todos los estados</option>
              <option value="pending">⏳ Pendiente</option>
              <option value="confirmed">✅ Confirmada</option>
              <option value="completed">🏁 Completada</option>
              <option value="cancelled">❌ Cancelada</option>
            </select>
            <button class="search-btn" @click="doSearch">
              <i class="pi pi-search"></i> Buscar
            </button>
          </div>

          <!-- Table -->
          <div class="appt-table-wrap">
            <table class="appt-table">
              <thead>
                <tr>
                  <th>Paciente</th>
                  <th>Médico</th>
                  <th>Fecha / Hora</th>
                  <th>Estado</th>
                  <th>Detalle</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="all_appointments.length === 0">
                  <td colspan="5" class="empty-row">
                    <i class="pi pi-inbox"></i> No se encontraron citas
                  </td>
                </tr>
                <tr v-for="a in all_appointments" :key="a.id" :class="['appt-row', `appt-row--${a.status}`]">
                  <td class="cell-name">{{ a.patient_name }}</td>
                  <td class="cell-name">{{ a.doctor_name }}</td>
                  <td class="cell-date">
                    <span class="date-primary">{{ formatDate(a.franja_start) }}</span>
                    <span class="date-time">{{ formatTime(a.franja_start) }} – {{ formatTime(a.franja_end) }}</span>
                  </td>
                  <td>
                    <span :class="['status-pill', `status-pill--${a.status}`]">
                      {{ statusLabels[a.status] || a.status }}
                    </span>
                  </td>
                  <td class="cell-detail">
                    <template v-if="a.status === 'cancelled'">
                      <span v-if="a.cancelled_by_label" :class="['who-badge', whoClass(a.cancelled_by_label)]">{{ a.cancelled_by_label }}</span>
                      <span v-if="a.reason" class="cancel-reason" :title="a.reason">{{ truncate(a.reason, 40) }}</span>
                    </template>
                    <span v-else class="cell-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="appointments_last_page > 1" class="pagination">
            <button class="page-btn" :disabled="appointments_page <= 1" @click="goPage(appointments_page - 1)">
              <i class="pi pi-chevron-left"></i>
            </button>
            <template v-for="p in visiblePages" :key="p">
              <button v-if="p === '...'" class="page-btn page-dots" disabled>…</button>
              <button v-else :class="['page-btn', { 'page-btn--active': p === appointments_page }]" @click="goPage(p as number)">{{ p }}</button>
            </template>
            <button class="page-btn" :disabled="appointments_page >= appointments_last_page" @click="goPage(appointments_page + 1)">
              <i class="pi pi-chevron-right"></i>
            </button>
          </div>
        </div>

        <!-- Pending doctors -->
        <div v-if="pending_doctors.length > 0" class="section-card">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-user-plus"></i> Médicos Pendientes</h2>
            <a href="/admin/panel" class="section-link">Gestionar →</a>
          </div>
          <div v-for="doc in pending_doctors" :key="doc.id" class="pending-doc">
            <div class="pending-doc__avatar">{{ doc.name.charAt(0) }}{{ doc.last_name.charAt(0) }}</div>
            <div class="pending-doc__info">
              <span class="pending-doc__name">{{ doc.name }} {{ doc.last_name }}</span>
              <span class="pending-doc__license">Lic. {{ doc.license_number }}</span>
            </div>
            <a href="/admin/panel" class="btn-review">Revisar</a>
          </div>
        </div>

        <BarChart title="Citas por día" subtitle="Últimos 7 días" :data="formattedChartData" :total="totalAppointments" color="var(--color-primary-600)" />
      </div>

      <!-- Sidebar -->
      <div class="dashboard-sidebar">
        <div class="quick-stats">
          <h3 class="quick-stats__title">📊 Resumen de Citas</h3>
          <div class="qs-item"><span class="qs-label">⏳ Pendientes</span><span class="qs-val qs-val--warn">{{ pending_appointments_count }}</span></div>
          <div class="qs-item"><span class="qs-label">✅ Completadas (mes)</span><span class="qs-val qs-val--ok">{{ completed_count }}</span></div>
          <div class="qs-item"><span class="qs-label">❌ Canceladas (mes)</span><span class="qs-val qs-val--err">{{ cancelled_count }}</span></div>
          <div class="qs-item"><span class="qs-label">📅 Total del mes</span><span class="qs-val">{{ monthly_appointments_count }}</span></div>
          <div v-if="monthly_appointments_count > 0" class="qs-rate">
            Tasa cancelación: {{ Math.round((cancelled_count / monthly_appointments_count) * 100) }}%
          </div>
        </div>
        <ActivityFeed :items="recentActivityItems" />
        <AlertCard v-if="pending_doctor_approvals > 0" title="Verificaciones pendientes" :subtitle="`${pending_doctor_approvals} médicos esperan aprobación.`" action-text="Revisar ahora" severity="critical" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardHeader from '@/components/app/DashboardHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import BarChart from '@/components/dashboard/BarChart.vue';
import AlertCard from '@/components/dashboard/AlertCard.vue';
import ActivityFeed from '@/components/dashboard/ActivityFeed.vue';

const props = defineProps<{
  total_users: number;
  pending_doctor_approvals: number;
  monthly_appointments_count: number;
  cancelled_count: number;
  completed_count: number;
  pending_appointments_count: number;
  pending_doctors: Array<{ id: string; name: string; last_name: string; license_number: string; status: string; created_at: string; }>;
  chart_appointments_by_day: Array<{ day: string; count: number }>;
  recent_activity: Array<{ text: string; time: string }>;
  all_appointments: Array<{
    id: string; patient_name: string; doctor_name: string; franja_start: string; franja_end: string;
    status: string; reason: string | null; cancelled_by_label: string | null; created_at: string;
  }>;
  appointments_total: number;
  appointments_page: number;
  appointments_per_page: number;
  appointments_last_page: number;
  filters: { search: string; status_filter: string; };
}>();

const searchQuery = ref(props.filters.search || '');
const statusFilterLocal = ref(props.filters.status_filter || '');

const statusLabels: Record<string, string> = {
  pending: 'Pendiente',
  confirmed: 'Confirmada',
  completed: 'Completada',
  cancelled: 'Cancelada',
};

function doSearch() {
  router.get('/admin', {
    search: searchQuery.value || undefined,
    status_filter: statusFilterLocal.value || undefined,
    page: 1,
  }, { preserveState: true, preserveScroll: true });
}

function goPage(p: number) {
  router.get('/admin', {
    search: searchQuery.value || undefined,
    status_filter: statusFilterLocal.value || undefined,
    page: p,
  }, { preserveState: true, preserveScroll: true });
}

const visiblePages = computed(() => {
  const last = props.appointments_last_page;
  const cur = props.appointments_page;
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
  const pages: (number | string)[] = [1];
  if (cur > 3) pages.push('...');
  for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
  if (cur < last - 2) pages.push('...');
  pages.push(last);
  return pages;
});

const totalAppointments = computed(() => props.chart_appointments_by_day.reduce((s, d) => s + d.count, 0));
const formattedChartData = computed(() => props.chart_appointments_by_day.map(i => ({ label: i.day, value: i.count })));
const recentActivityItems = computed(() => props.recent_activity.length > 0 ? props.recent_activity : [{ text: 'Sistema iniciado correctamente.', time: 'Hace 2 horas' }]);

function formatDate(iso: string) {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleDateString('es-ES', {
      weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
    });
  } catch { return iso; }
}
function formatTime(iso: string) {
  if (!iso) return '';
  try { return new Date(iso).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', hour12: false }); } catch { return iso; }
}
function truncate(s: string, len: number) { return s.length > len ? s.slice(0, len) + '…' : s; }
function whoClass(who: string) {
  if (who === 'Paciente') return 'who--patient';
  if (who === 'Médico') return 'who--doctor';
  return 'who--system';
}
</script>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--spacing-6); margin-bottom: var(--spacing-8); }
.dashboard-content { display: grid; grid-template-columns: 2.2fr 1fr; gap: var(--spacing-6); }
.dashboard-main { display: flex; flex-direction: column; gap: var(--spacing-6); }
.dashboard-sidebar { display: flex; flex-direction: column; gap: var(--spacing-6); }

/* Section card */
.section-card { background: var(--color-surface-0); border: 1px solid var(--color-surface-200); border-radius: var(--radius-lg); padding: var(--spacing-5); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-4); }
.section-title { font-size: 1rem; font-weight: 700; color: var(--color-text-strong); margin: 0; display: flex; align-items: center; gap: 6px; }
.section-title i { font-size: 0.9rem; }
.section-count { font-size: 0.82rem; color: var(--color-text-muted); font-weight: 500; }
.section-link { font-size: 0.82rem; color: var(--color-primary-600); font-weight: 600; text-decoration: none; }
.section-link:hover { text-decoration: underline; }

/* Search bar */
.search-bar { display: flex; gap: 8px; margin-bottom: var(--spacing-4); align-items: center; flex-wrap: wrap; }
.search-input-wrap {
  flex: 1; min-width: 200px; display: flex; align-items: center; gap: 6px;
  border: 1px solid var(--color-surface-200); border-radius: var(--radius-md);
  padding: 6px 12px; background: var(--color-surface-50, #FAFAFA);
  transition: border-color 0.2s;
}
.search-input-wrap:focus-within { border-color: var(--color-primary-500); }
.search-input-wrap i { color: var(--color-text-muted); font-size: 0.85rem; }
.search-input { flex: 1; border: none; outline: none; background: transparent; font-size: 0.88rem; font-family: var(--font-body); }
.search-clear { background: none; border: none; cursor: pointer; color: var(--color-text-muted); padding: 2px; }
.status-select {
  border: 1px solid var(--color-surface-200); border-radius: var(--radius-md);
  padding: 6px 10px; font-size: 0.85rem; font-family: var(--font-body);
  background: var(--color-surface-50, #FAFAFA); color: var(--color-text-primary);
}
.search-btn {
  display: flex; align-items: center; gap: 4px;
  padding: 6px 14px; border: none; border-radius: var(--radius-md);
  background: var(--color-primary-700, #0E5D52); color: #FFF;
  font-size: 0.85rem; font-weight: 600; cursor: pointer;
  transition: background 0.2s;
}
.search-btn:hover { background: var(--color-primary-800, #0A4A42); }

/* Appointments table */
.appt-table-wrap { overflow-x: auto; }
.appt-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.appt-table th {
  text-align: left; padding: 8px 12px; font-size: 0.75rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.03em; color: var(--color-text-muted);
  border-bottom: 2px solid var(--color-surface-200); white-space: nowrap;
}
.appt-table td { padding: 10px 12px; border-bottom: 1px solid var(--color-surface-100); vertical-align: middle; }
.appt-row { transition: background 0.15s; }
.appt-row:hover { background: var(--color-surface-50, #FAFAFA); }
.appt-row--cancelled { opacity: 0.75; }
.cell-name { font-weight: 600; color: var(--color-text-strong); white-space: nowrap; }
.cell-date { white-space: nowrap; }
.date-primary { display: block; font-weight: 500; }
.date-time { display: block; font-size: 0.75rem; color: var(--color-text-muted); }
.cell-detail { max-width: 220px; }
.cell-muted { color: var(--color-text-muted); }
.empty-row { text-align: center; padding: 32px 12px !important; color: var(--color-text-muted); font-size: 0.9rem; }

/* Status pills */
.status-pill { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
.status-pill--pending { background: #FEF3C7; color: #92400E; }
.status-pill--confirmed { background: #DBEAFE; color: #1D4ED8; }
.status-pill--completed { background: #D1FAE5; color: #065F46; }
.status-pill--cancelled { background: #FEE2E2; color: #991B1B; }

/* Who badge */
.who-badge { display: inline-block; padding: 1px 7px; border-radius: 6px; font-size: 0.68rem; font-weight: 600; margin-right: 4px; }
.who--patient { background: #DBEAFE; color: #1D4ED8; }
.who--doctor { background: #FEF3C7; color: #92400E; }
.who--system { background: #F3F4F6; color: #374151; }
.cancel-reason { font-size: 0.78rem; color: var(--color-error-700); }

/* Pagination */
.pagination { display: flex; justify-content: center; gap: 4px; margin-top: var(--spacing-4); padding-top: var(--spacing-3); border-top: 1px solid var(--color-surface-100); }
.page-btn {
  display: flex; align-items: center; justify-content: center;
  min-width: 32px; height: 32px; padding: 0 8px;
  border: 1px solid var(--color-surface-200); border-radius: var(--radius-md);
  background: var(--color-surface-0); color: var(--color-text-muted);
  font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
}
.page-btn:hover:not(:disabled) { background: var(--color-surface-100); color: var(--color-text-strong); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn--active { background: var(--color-primary-700, #0E5D52); color: #FFF; border-color: var(--color-primary-700, #0E5D52); }
.page-dots { border: none; background: transparent; }

/* Pending doctors */
.pending-doc { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--color-surface-100); }
.pending-doc:last-child { border-bottom: none; }
.pending-doc__avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--color-surface-200); display: flex; align-items: center; justify-content: center; font-size: 0.78rem; font-weight: 700; }
.pending-doc__info { flex: 1; }
.pending-doc__name { display: block; font-size: 0.88rem; font-weight: 600; }
.pending-doc__license { display: block; font-size: 0.75rem; color: var(--color-text-muted); }
.btn-review { padding: 5px 14px; background: var(--color-text-strong); color: var(--color-surface-0); border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 600; text-decoration: none; }

/* Quick stats sidebar */
.quick-stats { background: var(--color-surface-0); border: 1px solid var(--color-surface-200); border-radius: var(--radius-lg); padding: var(--spacing-5); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.quick-stats__title { margin: 0 0 12px; font-size: 0.95rem; font-weight: 700; }
.qs-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--color-surface-100); }
.qs-item:last-of-type { border-bottom: none; }
.qs-label { font-size: 0.85rem; color: var(--color-text-muted); }
.qs-val { font-size: 1.1rem; font-weight: 700; }
.qs-val--warn { color: var(--color-warning-700, #92400E); }
.qs-val--ok { color: var(--color-success-700, #065F46); }
.qs-val--err { color: var(--color-error-700, #991B1B); }
.qs-rate { margin-top: 8px; padding: 6px 10px; background: var(--color-surface-50); border-radius: 6px; font-size: 0.78rem; color: var(--color-text-muted); text-align: center; font-weight: 600; }

@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .dashboard-content { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
</style>
