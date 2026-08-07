<!--
  ====================================================================
  PatientDashboard — Main dashboard for patients showing appointments, prescriptions, and stats
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardHeader from '@/components/app/DashboardHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import DataTable from '@/components/dashboard/DataTable.vue';
import BarChart from '@/components/dashboard/BarChart.vue';
import AssistantWidget from '@/components/dashboard/AssistantWidget.vue';
import AlertCard from '@/components/dashboard/AlertCard.vue';

const props = defineProps<{
  upcoming_appointments: Array<{
    id: string;
    status: string;
    franja_start: string;
    franja_end: string;
    doctor_name: string;
    doctor_last_name: string;
    specialty_name: string | null;
  }>;
  active_prescriptions: Array<any>;
  chart_consultations_by_month: Array<{ month: string; count: number }>;
  past_consultations_count: number;
  active_prescriptions_count: number;
}>();

const page = usePage();
const auth = computed(() => page.props.auth as any);

const nextAppointment = computed(() => {
  if (props.upcoming_appointments && props.upcoming_appointments.length > 0) {
    return props.upcoming_appointments[0];
  }
  return null;
});

const formatDate = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
};

const formatJustDate = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
};

const nextAppointmentSummary = computed(() => {
  if (!nextAppointment.value) return 'No tienes citas próximas agendadas.';
  const dateStr = formatDate(nextAppointment.value.franja_start);
  return `Tu próxima cita es el ${dateStr} con Dr(a). ${nextAppointment.value.doctor_last_name}, por videollamada.`;
});

const nextAppointmentDate = computed(() => {
  if (!nextAppointment.value) return 'Sin citas';
  return formatJustDate(nextAppointment.value.franja_start);
});

const nextAppointmentInfo = computed(() => {
  if (!nextAppointment.value) return '';
  return `Dr(a). ${nextAppointment.value.doctor_last_name}`;
});

const isToday = (dateString: string) => {
  if (!dateString) return false;
  const date = new Date(dateString);
  const today = new Date();
  return date.getDate() === today.getDate() && date.getMonth() === today.getMonth() && date.getFullYear() === today.getFullYear();
};

const hasAppointmentToday = computed(() => {
  if (!nextAppointment.value) return false;
  return isToday(nextAppointment.value.franja_start);
});

const tableColumns = [
  { key: 'doctor', label: 'Médico' },
  { key: 'fecha', label: 'Fecha' },
  { key: 'specialty', label: 'Especialidad' },
  { key: 'status', label: 'Estado' },
  { key: 'actions', label: 'Acciones' },
];

const chartData = computed(() => {
  return (props.chart_consultations_by_month || []).map(item => ({
    label: item.month,
    value: item.count
  }));
});

const tableFilters = [
  { label: 'Próximas', key: 'active' },
  { label: 'Pasadas', key: 'past' },
  { label: 'Canceladas', key: 'cancelled' },
];

const getInitials = (name: string, lastName: string) => {
  return `${name?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
};

const getStatusColor = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'confirmada':
    case 'confirmed':
      return 'success';
    case 'completada':
    case 'completed':
      return 'neutral';
    case 'cancelada':
    case 'cancelled':
      return 'warning';
    default:
      return 'neutral';
  }
};

const getStatusLabel = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'confirmada':
    case 'confirmed':
      return 'Confirmada';
    case 'completada':
    case 'completed':
      return 'Completada';
    case 'cancelada':
    case 'cancelled':
      return 'Cancelada';
    default:
      return status;
  }
};
</script>

<template>
  <AppLayout>
    <div class="patient-dashboard">
      <DashboardHeader
        eyebrow="Mi salud"
        :title="`Hola, ${auth.user.name}`"
        :subtitle="nextAppointmentSummary"
        status-text="Seguro activo"
        action-text="Agendar cita"
        action-href="/directory"
      />

      <div class="stats-grid">
        <StatCard
          icon="pi pi-calendar"
          label="Próxima cita"
          :value="nextAppointmentDate"
          :trend="nextAppointmentInfo"
          trendType="positive"
        />
        <StatCard
          icon="pi pi-box"
          label="Recetas activas"
          :value="active_prescriptions_count.toString()"
          :trend="active_prescriptions_count > 0 ? '1 vence pronto' : 'Todo al día'"
          :trendType="active_prescriptions_count > 0 ? 'negative' : 'neutral'"
          iconBg="#FBEAE3"
        />
        <StatCard
          icon="pi pi-chart-line"
          label="Consultas realizadas"
          :value="past_consultations_count.toString()"
          trend="Total histórico"
          trendType="neutral"
        />
        <StatCard
          icon="pi pi-shield"
          label="Cobertura"
          value="80 %"
          trend="Copago 9 US$ por consulta"
          trendType="positive"
        />
      </div>

      <div class="dashboard-content">
        <main class="main-column">
          <section class="section-card">
            <header class="section-header">
              <h2>Mis citas</h2>
            </header>
            <DataTable
              :columns="tableColumns"
              :rows="upcoming_appointments"
              :filters="tableFilters"
              activeFilter="active"
            >
              <template #cell-doctor="{ row }">
                <div class="doctor-cell">
                  <div class="avatar">{{ getInitials(row.doctor_name, row.doctor_last_name) }}</div>
                  <span>Dr(a). {{ row.doctor_name }} {{ row.doctor_last_name }}</span>
                </div>
              </template>
              
              <template #cell-fecha="{ row }">
                <span class="date-cell">{{ formatDate(row.franja_start) }}</span>
              </template>
              
              <template #cell-specialty="{ row }">
                <span class="badge badge-subtle">{{ row.specialty_name || 'Medicina General' }}</span>
              </template>
              
              <template #cell-status="{ row }">
                <span :class="['badge', `badge-${getStatusColor(row.status)}`]">
                  {{ getStatusLabel(row.status) }}
                </span>
              </template>
              
              <template #cell-actions="{ row }">
                <div class="actions-cell">
                  <button v-if="isToday(row.franja_start)" class="btn btn-primary btn-sm">Entrar</button>
                  <button v-else-if="row.status === 'confirmada' || row.status === 'confirmed'" class="btn btn-outline btn-sm">Reprogramar</button>
                  <button v-else class="btn btn-ghost btn-sm">Ver resumen</button>
                </div>
              </template>
            </DataTable>
          </section>

          <section class="section-card">
            <header class="section-header">
              <h2>Mis consultas por mes</h2>
            </header>
            <div class="chart-container">
              <BarChart title="Consultas históricas" :data="chartData" color="#8FC9B3" />
            </div>
          </section>
        </main>

        <aside class="sidebar-column">
          <AssistantWidget
            message="Puedo agendar, reprogramar o resolver dudas sobre tu tratamiento. También te recuerdo cuándo renovar tus recetas."
            :actions="[
              { text: 'Agendar nueva cita', href: '/directory' }
            ]"
          />

          <AlertCard
            v-if="hasAppointmentToday"
            title="Consulta programada para hoy"
            subtitle="Tienes una videoconsulta próxima a iniciar."
            severity="warning"
            actionText="Ir a sala de espera"
            actionHref="#"
          />

          <div class="treatment-card">
            <h3>Tratamiento actual</h3>
            <ul v-if="active_prescriptions && active_prescriptions.length > 0" class="prescription-list">
              <li v-for="prescription in active_prescriptions" :key="prescription.id" class="prescription-item">
                <i class="pi pi-file-prescription"></i>
                <div class="prescription-details">
                  <span class="med-name">{{ prescription.medication_name || 'Medicamento' }}</span>
                  <span class="med-dosage">{{ prescription.dosage || '1 tableta diaria' }}</span>
                </div>
              </li>
            </ul>
            <div v-else class="empty-state">
              <p>No tienes recetas activas en este momento.</p>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.patient-dashboard {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  padding: var(--spacing-6);
  background-color: var(--color-page-bg);
  min-height: 100vh;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: var(--spacing-4);
}

@media (min-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.dashboard-content {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-6);
}

@media (min-width: 1024px) {
  .dashboard-content {
    grid-template-columns: 2fr 1fr;
    align-items: start;
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

.section-card {
  background-color: var(--color-surface-0);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--color-border);
  overflow: hidden;
}

.section-header {
  padding: var(--spacing-4) var(--spacing-6);
  border-bottom: 1px solid var(--color-border);
}

.section-header h2 {
  margin: 0;
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.doctor-cell {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
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
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
}

.date-cell {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: var(--spacing-1) var(--spacing-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  line-height: 1;
}

.badge-subtle {
  background-color: var(--color-surface-100);
  color: var(--color-text-strong);
}

.badge-success {
  background-color: var(--color-success-50, #E8F5E9);
  color: var(--color-success-700, #2E7D32);
}

.badge-neutral {
  background-color: var(--color-surface-100);
  color: var(--color-text-muted);
}

.badge-warning {
  background-color: var(--color-warning-50, #FFF3E0);
  color: var(--color-warning-700, #E65100);
}

.actions-cell {
  display: flex;
  gap: var(--spacing-2);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  font-family: var(--font-body);
  font-weight: var(--font-medium);
  cursor: pointer;
  transition: all var(--transition-fast) ease;
  border: none;
  text-decoration: none;
}

.btn-sm {
  padding: var(--spacing-1) var(--spacing-3);
  font-size: var(--text-xs);
}

.btn-primary {
  background-color: var(--color-primary-600);
  color: white;
}

.btn-primary:hover {
  background-color: var(--color-primary-700);
}

.btn-outline {
  background-color: transparent;
  color: var(--color-primary-600);
  border: 1px solid var(--color-primary-600);
}

.btn-outline:hover {
  background-color: var(--color-primary-50);
}

.btn-ghost {
  background-color: transparent;
  color: var(--color-text-muted);
}

.btn-ghost:hover {
  background-color: var(--color-surface-100);
  color: var(--color-text-strong);
}

.chart-container {
  padding: var(--spacing-6);
  height: 300px;
}

.treatment-card {
  background-color: var(--color-surface-0);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--color-border);
  padding: var(--spacing-6);
}

.treatment-card h3 {
  margin: 0 0 var(--spacing-4) 0;
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.prescription-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.prescription-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-3);
  background-color: var(--color-surface-50);
  border-radius: var(--radius-md);
}

.prescription-item i {
  color: var(--color-primary-600);
  font-size: var(--text-lg);
}

.prescription-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.med-name {
  font-weight: var(--font-medium);
  font-size: var(--text-sm);
  color: var(--color-text-strong);
}

.med-dosage {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.empty-state {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  text-align: center;
  padding: var(--spacing-4) 0;
}
</style>
