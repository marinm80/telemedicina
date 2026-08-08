<!--
  ====================================================================
  AgentDashboard — Agent/Receptionist dashboard component
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
import AssistantWidget from '@/components/dashboard/AssistantWidget.vue';

interface Appointment {
  id: string;
  status: string;
  franja_start: string;
  franja_end: string;
  patient_name: string;
  patient_last_name: string;
  doctor_name: string;
  doctor_last_name: string;
}

const props = defineProps<{
  pending_appointments_count: number;
  unassigned_requests_count: number;
  active_doctors_count: number;
  recent_appointments: Appointment[];
  today_appointments_count: number;
}>();

const page = usePage();
const user = computed(() => (page.props as any).auth?.user);

const assistantActions = [
  { text: 'Buscar disponibilidad', href: '/directory' },
  { text: 'Registrar paciente', href: '/patients/create' } // Assuming this route exists, otherwise just a placeholder
];

const formatDateTime = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
};

const getStatusColor = (status: string) => {
  const map: Record<string, string> = {
    pending: 'var(--color-warning-500)',
    confirmed: 'var(--color-success-500)',
    completed: 'var(--color-primary-500)',
    cancelled: 'var(--color-error-500)',
  };
  return map[status?.toLowerCase()] || 'var(--color-text-muted)';
};

const getStatusLabel = (status: string) => {
  const map: Record<string, string> = {
    pending: 'Pendiente',
    confirmed: 'Confirmada',
    completed: 'Completada',
    cancelled: 'Cancelada',
  };
  return map[status?.toLowerCase()] || status;
};
</script>

<template>
  <AppLayout>
    <div class="agent-dashboard">
      <DashboardHeader
        eyebrow="Recepción"
        title="Panel de Recepción"
        subtitle="Gestiona citas, atiende pacientes y coordina con los médicos."
        status-text="Recepción activa"
        action-text="Agendar cita"
        action-href="/directory"
      />

      <div class="kpi-grid">
        <StatCard
          icon="pi pi-calendar"
          label="Citas pendientes"
          :value="pending_appointments_count"
          trend="Requieren confirmación"
          :trendType="pending_appointments_count > 0 ? 'negative' : 'neutral'"
        />
        <StatCard
          icon="pi pi-users"
          label="Médicos activos"
          :value="active_doctors_count"
          trend="Disponibles hoy"
          trendType="positive"
        />
        <StatCard
          icon="pi pi-clock"
          label="Citas de hoy"
          :value="today_appointments_count"
          trend="Programadas para hoy"
          trendType="neutral"
        />
      </div>

      <div class="main-content">
        <div class="content-left">
          <DataTable
            :columns="[
              { key: 'patient', label: 'Paciente' },
              { key: 'doctor', label: 'Médico' },
              { key: 'fecha', label: 'Fecha y Hora' },
              { key: 'status', label: 'Estado' },
              { key: 'actions', label: 'Acciones', align: 'right' as const }
            ]"
            :rows="recent_appointments"
            empty-icon="pi-calendar-times"
            empty-message="No hay citas recientes para mostrar."
          >
            <template #cell-patient="{ row }">
              <div class="patient-cell">
                <div class="avatar">
                  {{ row.patient_name.charAt(0) }}{{ row.patient_last_name.charAt(0) }}
                </div>
                <div class="patient-info">
                  <span class="name">{{ row.patient_name }} {{ row.patient_last_name }}</span>
                </div>
              </div>
            </template>

            <template #cell-doctor="{ row }">
              <span class="doctor-name">Dr(a). {{ row.doctor_name }} {{ row.doctor_last_name }}</span>
            </template>

            <template #cell-fecha="{ row }">
              <div class="date-cell">
                <i class="pi pi-calendar"></i>
                <span>{{ formatDateTime(row.franja_start) }}</span>
              </div>
            </template>

            <template #cell-status="{ row }">
              <span class="status-badge" :style="{ '--status-color': getStatusColor(row.status) }">
                <span class="status-dot"></span>
                {{ getStatusLabel(row.status) }}
              </span>
            </template>

            <template #cell-actions="{ row }">
              <div class="actions-cell">
                <Link :href="`/appointments/${row.id}`" class="btn-action">
                  Ver detalle
                </Link>
              </div>
            </template>
          </DataTable>
        </div>

        <div class="content-right">
          <AssistantWidget
            message="Puedo ayudarte a buscar disponibilidad de médicos y agendar citas para los pacientes."
            :actions="assistantActions"
          />

          <div class="quick-actions-card">
            <h3 class="card-title">Acciones rápidas</h3>
            <div class="action-list">
              <Link href="/directory" class="quick-action-btn">
                <i class="pi pi-calendar-plus"></i>
                <span>Agendar cita para paciente</span>
              </Link>
              <button class="quick-action-btn">
                <i class="pi pi-search"></i>
                <span>Buscar paciente</span>
              </button>
              <Link href="/directory" class="quick-action-btn">
                <i class="pi pi-address-book"></i>
                <span>Ver directorio médico</span>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.agent-dashboard {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  padding: var(--spacing-6) 0;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: var(--spacing-6);
}

@media (min-width: 768px) {
  .kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.main-content {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-8);
  align-items: start;
}

@media (min-width: 1024px) {
  .main-content {
    grid-template-columns: 2fr 1fr;
  }
}

.content-left {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.content-right {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

/* Table Cells */
th {
  text-align: left;
  color: var(--color-text-muted);
  font-weight: var(--font-medium);
  font-size: var(--text-sm);
  padding: var(--spacing-4);
  border-bottom: 1px solid var(--color-border);
}

th.text-right {
  text-align: right;
}

td {
  padding: var(--spacing-4);
  vertical-align: middle;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text-strong);
  font-size: var(--text-sm);
}

.patient-cell {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
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
  text-transform: uppercase;
}

.patient-info {
  display: flex;
  flex-direction: column;
}

.patient-info .name {
  font-weight: var(--font-medium);
}

.doctor-name {
  color: var(--color-text-strong);
}

.date-cell {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  color: var(--color-text-strong);
}

.date-cell i {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-1) var(--spacing-3);
  border-radius: var(--radius-full);
  background-color: var(--color-surface-100);
  color: var(--color-text-strong);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background-color: var(--status-color);
}

.actions-cell {
  text-align: right;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-2) var(--spacing-4);
  background-color: transparent;
  color: var(--color-primary-600);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  text-decoration: none;
  transition: all var(--transition-fast) ease;
  cursor: pointer;
}

.btn-action:hover {
  background-color: var(--color-surface-50);
  border-color: var(--color-primary-500);
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-12) var(--spacing-6);
  color: var(--color-text-muted);
  text-align: center;
}

.empty-state i {
  font-size: var(--text-4xl);
  margin-bottom: var(--spacing-4);
  color: var(--color-border);
}

/* Quick Actions Card */
.quick-actions-card {
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--spacing-6);
  box-shadow: var(--shadow-sm);
}

.card-title {
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0 0 var(--spacing-4) 0;
}

.action-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  width: 100%;
  padding: var(--spacing-3) var(--spacing-4);
  background-color: var(--color-surface-50);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text-strong);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  text-align: left;
  text-decoration: none;
  cursor: pointer;
  transition: all var(--transition-fast) ease;
}

.quick-action-btn i {
  color: var(--color-primary-600);
  font-size: var(--text-lg);
}

.quick-action-btn:hover {
  background-color: var(--color-primary-50);
  border-color: var(--color-primary-500);
  color: var(--color-primary-700);
}
</style>
