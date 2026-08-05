<!--
  ====================================================================
  AdminDashboard — Admin dashboard for Salvia platform
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <AppLayout>
    <DashboardHeader
      eyebrow="Panel de administración"
      title="Toda la clínica en una vista"
      subtitle="Verificación de médicos, ocupación de agenda, ingresos y salud del sistema."
      status-text="Sistema operativo · 99,8 %"
      action-text="Invitar médico"
    />

    <div class="stats-grid">
      <StatCard 
        icon="pi-users" 
        label="Usuarios activos" 
        :value="total_users.toString()" 
        trend="+14 este mes" 
        trendType="positive" 
      />
      <StatCard 
        icon="pi-verified" 
        label="Médicos por verificar" 
        :value="pending_doctor_approvals.toString()" 
        trend="2 esperan más de 48 h" 
        trendType="negative" 
        iconBg="var(--color-alert)" 
      />
      <StatCard 
        icon="pi-calendar" 
        label="Citas del mes" 
        :value="monthly_appointments_count.toString()" 
        trend="+12,4 % vs. julio" 
        trendType="positive" 
      />
      <StatCard 
        icon="pi-credit-card" 
        label="Ingresos del mes" 
        :value="formattedRevenue" 
        trend="+8,1 % vs. julio" 
        trendType="positive" 
      />
    </div>

    <div class="dashboard-content">
      <div class="dashboard-main">
        <DataTable
          :columns="doctorColumns"
          :rows="pending_doctors"
          :filters="[
            {key:'pending', label:'Pendientes', count: pending_doctor_approvals},
            {key:'review', label:'En revisión'},
            {key:'approved', label:'Aprobados'}
          ]"
          activeFilter="pending"
        >
          <template #cell-name="{ row }">
            <div class="doctor-name">
              <div class="avatar">{{ row.name.charAt(0) }}{{ row.last_name.charAt(0) }}</div>
              <span class="name-text">{{ row.name }} {{ row.last_name }}</span>
            </div>
          </template>
          
          <template #cell-specialty_name="{ row }">
            <span class="badge specialty-badge">{{ row.specialty_name || 'General' }}</span>
          </template>

          <template #cell-status="{ row }">
            <span :class="['badge', 'status-badge', `status-${row.status.toLowerCase()}`]">
              {{ row.status === 'pending' ? 'Pendiente' : row.status === 'approved' ? 'Aprobado' : row.status }}
            </span>
          </template>

          <template #cell-actions>
            <div class="action-buttons">
              <button class="btn btn-primary">Revisar</button>
              <button class="btn btn-ghost">Rechazar</button>
            </div>
          </template>
        </DataTable>

        <BarChart
          title="Citas por día"
          subtitle="Últimos 7 días"
          :data="formattedChartData"
          :total="totalAppointments"
          color="var(--color-primary-600)"
        />
      </div>

      <div class="dashboard-sidebar">
        <AssistantWidget
          message="312 conversaciones esta semana. El 74 % se resolvió sin intervención humana; 41 usuarios crearon cuenta desde el chat."
          :actions="[
            {text: 'Ver conversaciones sin resolver'},
            {text: 'Editar respuestas de recepción'}
          ]"
        />

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
import DataTable from '@/components/dashboard/DataTable.vue';
import BarChart from '@/components/dashboard/BarChart.vue';
import AssistantWidget from '@/components/dashboard/AssistantWidget.vue';
import AlertCard from '@/components/dashboard/AlertCard.vue';
import ActivityFeed from '@/components/dashboard/ActivityFeed.vue';

const props = defineProps<{
  total_users: number;
  pending_doctor_approvals: number;
  monthly_appointments_count: number;
  total_revenue: number;
  pending_doctors: Array<{
    id: string;
    name: string;
    last_name: string;
    specialty_name: string | null;
    license_number: string;
    status: string;
    created_at: string;
  }>;
  chart_appointments_by_day: Array<{ day: string; count: number }>;
  recent_activity: Array<{ text: string; time: string }>;
}>();

const page = usePage();
// Optional: If you need to access user data directly inside template
const user = computed(() => (page.props as any).auth?.user);

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value).replace('$', 'US$ ');
};

const formattedRevenue = computed(() => formatCurrency(props.total_revenue));

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
    : [
        { text: 'Sistema iniciado correctamente.', time: 'Hace 2 horas' }
      ];
});

const doctorColumns = [
  { key: 'name', label: 'Médico' },
  { key: 'specialty_name', label: 'Especialidad' },
  { key: 'license_number', label: 'Colegiatura' },
  { key: 'status', label: 'Estado' },
  { key: 'actions', label: 'Acciones', align: 'right' as const }
];
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

/* Custom Table Cell Styles */
.doctor-name {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
}

.avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-full);
  background-color: var(--color-surface-200);
  color: var(--color-text-strong);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
}

.name-text {
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: var(--spacing-1) var(--spacing-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
}

.specialty-badge {
  background-color: var(--color-surface-100);
  color: var(--color-primary-600);
}

.status-badge {
  text-transform: capitalize;
}

.status-pending {
  background-color: var(--color-warning-50, #FFF3CD);
  color: var(--color-warning-700, #856404);
}

.status-approved {
  background-color: var(--color-success-50, #D4EDDA);
  color: var(--color-success-700, #155724);
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: var(--spacing-2);
}

.btn {
  padding: var(--spacing-2) var(--spacing-4);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  cursor: pointer;
  border: none;
  transition: all var(--transition-fast);
}

.btn-primary {
  background-color: var(--color-text-strong);
  color: var(--color-surface-0);
}

.btn-primary:hover {
  background-color: var(--color-text-muted);
}

.btn-ghost {
  background-color: transparent;
  color: var(--color-text-muted);
}

.btn-ghost:hover {
  background-color: var(--color-surface-100);
  color: var(--color-text-strong);
}

@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .dashboard-content {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
