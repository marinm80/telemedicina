<!--
  ====================================================================
  AdminDashboard — Admin dashboard with real stats + cancellations
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <AppLayout>
    <DashboardHeader
      eyebrow="Panel de administración"
      title="Toda la clínica en una vista"
      subtitle="Supervisión de citas, médicos, cancelaciones y salud del sistema."
      status-text="Sistema operativo · 99,8 %"
      action-text="Panel de Control"
      @action="$inertia.visit('/admin/panel')"
    />

    <div class="stats-grid">
      <StatCard 
        icon="pi-users" 
        label="Usuarios activos" 
        :value="total_users.toString()" 
        :trend="`${total_users} registrados`"
        trendType="neutral" 
      />
      <StatCard 
        icon="pi-verified" 
        label="Médicos por verificar" 
        :value="pending_doctor_approvals.toString()" 
        :trend="pending_doctor_approvals > 0 ? 'Requieren atención' : 'Todo al día'"
        :trendType="pending_doctor_approvals > 0 ? 'negative' : 'positive'" 
        iconBg="var(--color-alert)" 
      />
      <StatCard 
        icon="pi-calendar" 
        label="Citas del mes" 
        :value="monthly_appointments_count.toString()" 
        :trend="`${pending_appointments_count} pendientes`"
        trendType="neutral" 
      />
      <StatCard 
        icon="pi-times-circle" 
        label="Cancelaciones del mes" 
        :value="cancelled_count.toString()" 
        :trend="`${completed_count} completadas`"
        :trendType="cancelled_count > 0 ? 'negative' : 'positive'" 
        iconBg="var(--color-error-600, #DC2626)"
      />
    </div>

    <div class="dashboard-content">
      <div class="dashboard-main">
        <!-- Cancelled appointments list -->
        <div class="section-card">
          <div class="section-header">
            <h2 class="section-title"><i class="pi pi-times-circle"></i> Cancelaciones Recientes</h2>
            <a href="/appointments" class="section-link">Ver todas las citas →</a>
          </div>
          <div v-if="recent_cancelled.length === 0" class="empty-msg">
            <i class="pi pi-check-circle"></i>
            <span>Sin cancelaciones recientes</span>
          </div>
          <div v-else class="cancel-list">
            <div v-for="c in recent_cancelled" :key="c.id" class="cancel-item">
              <div class="cancel-item__info">
                <span class="cancel-item__patient">{{ c.patient_name }}</span>
                <span class="cancel-item__arrow">→</span>
                <span class="cancel-item__doctor">{{ c.doctor_name }}</span>
              </div>
              <div class="cancel-item__meta">
                <span class="cancel-item__date"><i class="pi pi-calendar"></i> {{ formatDate(c.franja_start) }}</span>
                <span class="cancel-item__who" :class="whoClass(c.cancelled_by_label)">{{ c.cancelled_by_label }}</span>
              </div>
              <div v-if="c.reason" class="cancel-item__reason">
                <i class="pi pi-info-circle"></i> {{ c.reason }}
              </div>
            </div>
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

        <BarChart
          title="Citas por día"
          subtitle="Últimos 7 días"
          :data="formattedChartData"
          :total="totalAppointments"
          color="var(--color-primary-600)"
        />
      </div>

      <div class="dashboard-sidebar">
        <!-- Quick stats cards -->
        <div class="quick-stats">
          <h3 class="quick-stats__title">📊 Resumen de Citas</h3>
          <div class="qs-item">
            <span class="qs-label">⏳ Pendientes</span>
            <span class="qs-val qs-val--warn">{{ pending_appointments_count }}</span>
          </div>
          <div class="qs-item">
            <span class="qs-label">✅ Completadas (mes)</span>
            <span class="qs-val qs-val--ok">{{ completed_count }}</span>
          </div>
          <div class="qs-item">
            <span class="qs-label">❌ Canceladas (mes)</span>
            <span class="qs-val qs-val--err">{{ cancelled_count }}</span>
          </div>
          <div class="qs-item">
            <span class="qs-label">📅 Total del mes</span>
            <span class="qs-val">{{ monthly_appointments_count }}</span>
          </div>
          <div v-if="monthly_appointments_count > 0" class="qs-rate">
            Tasa cancelación: {{ Math.round((cancelled_count / monthly_appointments_count) * 100) }}%
          </div>
        </div>

        <ActivityFeed :items="recentActivityItems" />

        <AlertCard
          v-if="pending_doctor_approvals > 0"
          title="Verificaciones pendientes"
          :subtitle="`${pending_doctor_approvals} médicos esperan aprobación para recibir citas.`"
          action-text="Revisar ahora"
          severity="critical"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
  pending_doctors: Array<{
    id: string;
    name: string;
    last_name: string;
    license_number: string;
    status: string;
    created_at: string;
  }>;
  chart_appointments_by_day: Array<{ day: string; count: number }>;
  recent_activity: Array<{ text: string; time: string }>;
  recent_cancelled: Array<{
    id: string;
    patient_name: string;
    doctor_name: string;
    franja_start: string;
    reason: string | null;
    cancelled_by_label: string;
    updated_at: string;
  }>;
}>();

const totalAppointments = computed(() => 
  props.chart_appointments_by_day.reduce((sum, day) => sum + day.count, 0)
);

const formattedChartData = computed(() => 
  props.chart_appointments_by_day.map(item => ({
    label: item.day,
    value: item.count
  }))
);

const recentActivityItems = computed(() => {
  return props.recent_activity.length > 0 
    ? props.recent_activity 
    : [{ text: 'Sistema iniciado correctamente.', time: 'Hace 2 horas' }];
});

function formatDate(iso: string) {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  } catch { return iso; }
}

function whoClass(who: string) {
  if (who === 'Paciente') return 'who--patient';
  if (who === 'Médico') return 'who--doctor';
  return 'who--system';
}
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-6);
  margin-bottom: var(--spacing-8);
}

.dashboard-content {
  display: grid;
  grid-template-columns: 2.05fr 1fr;
  gap: var(--spacing-6);
}

.dashboard-main {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.dashboard-sidebar {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

/* Section cards */
.section-card {
  background: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  padding: var(--spacing-5);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.section-header {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-4);
}
.section-title {
  font-size: 1rem; font-weight: 700; color: var(--color-text-strong); margin: 0;
  display: flex; align-items: center; gap: 6px;
}
.section-title i { font-size: 0.9rem; }
.section-link {
  font-size: 0.82rem; color: var(--color-primary-600); font-weight: 600; text-decoration: none;
}
.section-link:hover { text-decoration: underline; }

/* Cancel list */
.cancel-list { display: flex; flex-direction: column; gap: 8px; }
.cancel-item {
  padding: 10px 14px; background: var(--color-surface-50, #FAFAFA); border: 1px solid var(--color-surface-200);
  border-left: 3px solid var(--color-error-500, #EF4444); border-radius: 8px;
}
.cancel-item__info { display: flex; align-items: center; gap: 6px; font-size: 0.88rem; font-weight: 600; color: var(--color-text-strong); }
.cancel-item__arrow { color: var(--color-text-muted); }
.cancel-item__meta { display: flex; gap: 12px; margin-top: 4px; font-size: 0.78rem; color: var(--color-text-muted); align-items: center; }
.cancel-item__date { display: flex; align-items: center; gap: 3px; }
.cancel-item__who {
  padding: 1px 8px; border-radius: 8px; font-size: 0.7rem; font-weight: 600;
}
.who--patient { background: #DBEAFE; color: #1D4ED8; }
.who--doctor { background: #FEF3C7; color: #92400E; }
.who--system { background: #F3F4F6; color: #374151; }
.cancel-item__reason { margin-top: 4px; font-size: 0.78rem; color: var(--color-error-700); display: flex; align-items: center; gap: 4px; }

.empty-msg {
  display: flex; align-items: center; gap: 8px; padding: var(--spacing-4);
  color: var(--color-text-muted); font-size: 0.9rem;
}

/* Pending doctors */
.pending-doc {
  display: flex; align-items: center; gap: 12px; padding: 8px 0;
  border-bottom: 1px solid var(--color-surface-100);
}
.pending-doc:last-child { border-bottom: none; }
.pending-doc__avatar {
  width: 36px; height: 36px; border-radius: 50%; background: var(--color-surface-200);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.78rem; font-weight: 700; color: var(--color-text-strong);
}
.pending-doc__info { flex: 1; }
.pending-doc__name { display: block; font-size: 0.88rem; font-weight: 600; color: var(--color-text-strong); }
.pending-doc__license { display: block; font-size: 0.75rem; color: var(--color-text-muted); }
.btn-review {
  padding: 5px 14px; background: var(--color-text-strong); color: var(--color-surface-0);
  border-radius: var(--radius-md); font-size: 0.78rem; font-weight: 600;
  text-decoration: none; border: none;
}

/* Quick stats sidebar */
.quick-stats {
  background: var(--color-surface-0); border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg); padding: var(--spacing-5);
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.quick-stats__title { margin: 0 0 12px; font-size: 0.95rem; font-weight: 700; }
.qs-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 0; border-bottom: 1px solid var(--color-surface-100);
}
.qs-item:last-of-type { border-bottom: none; }
.qs-label { font-size: 0.85rem; color: var(--color-text-muted); }
.qs-val { font-size: 1.1rem; font-weight: 700; color: var(--color-text-strong); }
.qs-val--warn { color: var(--color-warning-700, #92400E); }
.qs-val--ok { color: var(--color-success-700, #065F46); }
.qs-val--err { color: var(--color-error-700, #991B1B); }
.qs-rate {
  margin-top: 8px; padding: 6px 10px; background: var(--color-surface-50, #F9FAFB);
  border-radius: 6px; font-size: 0.78rem; color: var(--color-text-muted);
  text-align: center; font-weight: 600;
}

@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .dashboard-content { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
