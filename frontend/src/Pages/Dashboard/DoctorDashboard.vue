<!--
  ====================================================================
  DoctorDashboard — Doctor dashboard with agenda and clinical notes
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardHeader from '@/components/app/DashboardHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import BarChart from '@/components/dashboard/BarChart.vue';
import AssistantWidget from '@/components/dashboard/AssistantWidget.vue';
import AlertCard from '@/components/dashboard/AlertCard.vue';
import { formatUSD } from '@/lib/currency';

interface Appointment {
  id: string;
  status: string;
  reason?: string;
  franja_start: string;
  franja_end: string;
  patient_name: string;
  patient_last_name: string;
}

const props = defineProps<{
  profile_status: string;
  today_appointments: Appointment[];
  active_patients_count: number;
  chart_consultations_by_day: Array<{ day: string; count: number }>;
  pending_tasks: Array<any>;
  month_earnings: number;
}>();

const page = usePage();
const user = computed(() => (page.props as any).auth?.user as { id: string, name: string, last_name: string, email: string, role: string, timezone: string });

const greeting = computed(() => {
  const hour = new Date().getHours();
  let timeGreeting = 'Buenas tardes';
  if (hour < 12) timeGreeting = 'Buenos días';
  else if (hour >= 19) timeGreeting = 'Buenas noches';
  return `${timeGreeting}, Dr(a). ${user.value.last_name || user.value.name}`;
});

const nextAppointment = computed(() => {
  const now = new Date();
  return props.today_appointments.find(a => new Date(a.franja_start) > now) || null;
});

const todaySummary = computed(() => {
  const count = props.today_appointments.length;
  if (!nextAppointment.value) {
    return `Tienes ${count} consultas hoy. No hay próximas citas.`;
  }
  const nextTime = new Date(nextAppointment.value.franja_start);
  const diffMs = nextTime.getTime() - new Date().getTime();
  const diffMins = Math.floor(diffMs / 60000);
  return `Tienes ${count} consultas hoy. La próxima empieza en ${diffMins} minutos.`;
});

const formatTime = (isoStr: string) => {
  if (!isoStr) return '';
  const date = new Date(isoStr);
  return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
};

const getInitials = (name: string, lastName: string) => {
  return `${name.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
};

const chartData = computed(() => {
  return props.chart_consultations_by_day.map(d => ({
    label: d.day,
    value: d.count
  }));
});
</script>

<template>
  <AppLayout>
    <div class="doctor-dashboard">
      <div v-if="profile_status !== 'approved'" class="profile-warning">
        <i class="pi pi-exclamation-triangle"></i>
        <span>Tu perfil está pendiente de aprobación. Algunas funciones pueden estar limitadas hasta que el equipo médico valide tus credenciales.</span>
      </div>

      <DashboardHeader
        eyebrow="Consultorio"
        :title="greeting"
        :subtitle="todaySummary"
        status-text="Agenda abierta hoy"
        action-text="Iniciar consulta"
      />

      <div class="dashboard-stats">
        <StatCard
          icon="pi pi-calendar"
          label="Consultas hoy"
          :value="today_appointments.length"
          trend="0 por videollamada"
          trendType="positive"
        />
        <StatCard
          icon="pi pi-file-edit"
          label="Notas pendientes"
          :value="pending_tasks.length"
          :trend="pending_tasks.length > 0 ? 'Cierra antes de las 19:00' : 'Todo al día'"
          :trendType="pending_tasks.length > 0 ? 'negative' : 'positive'"
          iconBg="var(--color-surface-100)"
        />
        <StatCard
          icon="pi pi-users"
          label="Pacientes activos"
          :value="active_patients_count"
          trend="+9 este mes"
          trendType="positive"
        />
        <StatCard
          icon="pi pi-wallet"
          label="Ingresos del mes"
          :value="formatUSD(month_earnings)"
          trend="consultas facturadas"
          trendType="positive"
        />
      </div>

      <div class="dashboard-content">
        <div class="main-column">
          <DataTable
            :columns="[
              { key: 'patient', label: 'Paciente' },
              { key: 'time', label: 'Hora' },
              { key: 'reason', label: 'Motivo' },
              { key: 'status', label: 'Estado' },
              { key: 'actions', label: 'Acciones' }
            ]"
            :rows="today_appointments"
            :filters="[
              { key: 'hoy', label: 'Hoy' },
              { key: 'manana', label: 'Mañana' },
              { key: 'semana', label: 'Semana' }
            ]"
            activeFilter="hoy"
          >
            <template #cell-patient="{ row }">
              <div class="patient-cell">
                <div class="avatar">{{ getInitials(row.patient_name, row.patient_last_name) }}</div>
                <span>{{ row.patient_name }} {{ row.patient_last_name }}</span>
              </div>
            </template>
            <template #cell-time="{ row }">
              {{ formatTime(row.franja_start) }}
            </template>
            <template #cell-reason="{ row }">
              {{ row.reason || 'Consulta general' }}
            </template>
            <template #cell-status="{ row }">
              <span :class="['status-badge', row.status.toLowerCase().replace(' ', '-')]">{{ row.status }}</span>
            </template>
            <template #cell-actions="{ row }">
              <div class="action-cell">
                <button class="action-btn">
                  <i class="pi pi-file"></i> Ficha
                </button>
                <button v-if="row.status === 'En espera'" class="action-btn primary">
                  <i class="pi pi-video"></i> Entrar
                </button>
              </div>
            </template>
          </DataTable>

          <div class="chart-section">
            <BarChart
              title="Consultas por día"
              subtitle="Tu semana en curso"
              :data="chartData"
              color="var(--color-accent, #D9603E)"
            />
          </div>
        </div>

        <div class="sidebar-column">
          <AssistantWidget
            message="Hola Doctor. He revisado el historial de tu próximo paciente. Recuerda revisar los últimos resultados de laboratorio antes de iniciar la consulta."
          />
          
          <AlertCard
            v-if="nextAppointment"
            title="Próxima consulta"
            :subtitle="`${nextAppointment.patient_name} ${nextAppointment.patient_last_name} a las ${formatTime(nextAppointment.franja_start)}`"
            severity="warning"
          />

          <div class="tasks-card">
            <h3><i class="pi pi-list"></i> Tareas pendientes</h3>
            <ul v-if="pending_tasks.length > 0" class="task-list">
              <li v-for="(task, idx) in pending_tasks" :key="idx" class="task-item">
                <i class="pi pi-check-circle"></i>
                <div class="task-details">
                  <span class="task-title">{{ task.title || 'Completar nota clínica' }}</span>
                  <span class="task-desc">{{ task.description || 'Paciente de las 10:00' }}</span>
                </div>
              </li>
            </ul>
            <div v-else class="no-tasks">
              No tienes tareas pendientes.
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.doctor-dashboard {
  padding: var(--spacing-6);
  background-color: var(--color-page-bg, var(--color-surface-50));
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.profile-warning {
  background-color: var(--color-warning-50);
  border-left: 4px solid var(--color-warning-500);
  padding: var(--spacing-4);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  color: var(--color-warning-900);
  font-family: var(--font-body);
  font-size: var(--text-sm);
}

.profile-warning i {
  font-size: var(--text-lg);
  color: var(--color-warning-600);
}

.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: var(--spacing-4);
}

.dashboard-content {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: var(--spacing-6);
  align-items: start;
}

@media (max-width: 1024px) {
  .dashboard-content {
    grid-template-columns: 1fr;
  }
}

.main-column {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.sidebar-column {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.table-filters {
  display: flex;
  gap: var(--spacing-2);
}

.filter-btn {
  background: transparent;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: var(--spacing-1) var(--spacing-3);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: all var(--transition-fast) ease;
}

.filter-btn:hover {
  background-color: var(--color-surface-100);
}

.filter-btn.active {
  background-color: var(--color-primary-50);
  border-color: var(--color-primary-500);
  color: var(--color-primary-700);
}

.row {
  display: grid;
  grid-template-columns: 2fr 1fr 1.5fr 1fr 1fr;
  gap: var(--spacing-4);
  padding: var(--spacing-3) var(--spacing-4);
  align-items: center;
}

.header-row {
  font-family: var(--font-heading);
  font-size: var(--text-xs);
  text-transform: uppercase;
  color: var(--color-text-subtle);
  border-bottom: 1px solid var(--color-border);
  font-weight: var(--font-semibold);
}

.data-row {
  border-bottom: 1px solid var(--color-surface-100);
  font-size: var(--text-sm);
  color: var(--color-text-strong);
  transition: background-color var(--transition-fast);
}

.data-row:hover {
  background-color: var(--color-surface-50);
}

.patient-cell {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  font-weight: var(--font-medium);
}

.avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  background-color: var(--color-primary-100);
  color: var(--color-primary-700);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--text-xs);
  font-weight: var(--font-bold);
}

.status-badge {
  padding: var(--spacing-1) var(--spacing-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  background-color: var(--color-surface-200);
  color: var(--color-text-muted);
}

.status-badge.completada {
  background-color: var(--color-success-50);
  color: var(--color-success-700);
}

.status-badge.en-espera {
  background-color: var(--color-warning-50);
  color: var(--color-warning-700);
}

.status-badge.cancelada {
  background-color: var(--color-error-50);
  color: var(--color-error-700);
}

.action-cell {
  display: flex;
  gap: var(--spacing-2);
}

.action-btn {
  background: transparent;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: var(--spacing-1) var(--spacing-2);
  font-size: var(--text-xs);
  color: var(--color-text-strong);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
}

.action-btn:hover {
  background-color: var(--color-surface-100);
}

.action-btn.primary {
  background-color: var(--color-primary-600);
  border-color: var(--color-primary-600);
  color: white;
}

.action-btn.primary:hover {
  background-color: var(--color-primary-700);
}

.chart-section {
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-4);
  box-shadow: var(--shadow-sm);
}

.tasks-card {
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-4);
  box-shadow: var(--shadow-sm);
}

.tasks-card h3 {
  font-family: var(--font-heading);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0 0 var(--spacing-4) 0;
}

.task-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.task-item {
  display: flex;
  gap: var(--spacing-3);
  align-items: flex-start;
  padding-bottom: var(--spacing-3);
  border-bottom: 1px solid var(--color-surface-100);
}

.task-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.task-item i {
  color: var(--color-primary-500);
  font-size: var(--text-lg);
  margin-top: 2px;
}

.task-details {
  display: flex;
  flex-direction: column;
}

.task-title {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
}

.task-desc {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.no-tasks {
  font-size: var(--text-sm);
  color: var(--color-text-subtle);
  text-align: center;
  padding: var(--spacing-4) 0;
}
</style>
