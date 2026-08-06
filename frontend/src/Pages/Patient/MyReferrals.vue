<!--
  ====================================================================
  MyReferrals — Mis Referidos (Paciente)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <AppLayout>
    <div class="referrals">
      <header class="referrals__header">
        <h1>Mis Referidos</h1>
        <p>{{ props.referrals.length }} referidos en total</p>
      </header>

      <div class="referrals__tabs">
        <button
          class="tab-btn"
          :class="{ 'tab-btn--active': currentTab === 'pending' }"
          @click="currentTab = 'pending'"
        >
          Pendientes
        </button>
        <button
          class="tab-btn"
          :class="{ 'tab-btn--active': currentTab === 'accepted' }"
          @click="currentTab = 'accepted'"
        >
          Aceptados
        </button>
        <button
          class="tab-btn"
          :class="{ 'tab-btn--active': currentTab === 'completed' }"
          @click="currentTab = 'completed'"
        >
          Completados
        </button>
      </div>

      <div class="referrals__list">
        <div v-if="filteredReferrals.length === 0" class="referrals__empty">
          <i class="pi pi-folder-open empty-icon"></i>
          <p>No tienes referidos en esta categoría.</p>
        </div>

        <div
          v-else
          v-for="ref in filteredReferrals"
          :key="ref.id"
          class="referral-card"
          :class="{ 'referral-card--urgente': ref.priority === 'urgente' }"
        >
          <div class="card-header">
            <div class="specialty-info">
              <h3>{{ ref.specialty_name || 'Especialista' }}</h3>
              <span class="badge" :class="getPriorityBadgeClass(ref.priority)">
                {{ ref.priority === 'urgente' ? 'Urgente' : 'Normal' }}
              </span>
            </div>
            <span class="badge" :class="getStatusBadgeClass(ref.status)">
              {{ formatStatus(ref.status) }}
            </span>
          </div>
          
          <div v-if="ref.priority === 'urgente'" class="urgent-notice">
            <i class="pi pi-exclamation-triangle"></i>
            Tu médico marcó esto como urgente — agenda lo antes posible
          </div>

          <div class="card-body">
            <div class="info-row">
              <i class="pi pi-file-edit"></i>
              <div>
                <strong>Motivo:</strong>
                <p>{{ ref.reason }}</p>
              </div>
            </div>
            <div class="info-row" v-if="ref.referring_doctor">
              <i class="pi pi-user"></i>
              <div>
                <strong>Referido por:</strong>
                <p>{{ ref.referring_doctor.name }}</p>
              </div>
            </div>
            <div class="info-row" v-if="ref.referred_doctor">
              <i class="pi pi-star"></i>
              <div>
                <strong>Especialista sugerido:</strong>
                <p>{{ ref.referred_doctor.name }}</p>
              </div>
            </div>
            <div class="info-row">
              <i class="pi pi-calendar"></i>
              <div>
                <strong>Fecha de derivación:</strong>
                <p>{{ formatDate(ref.created_at) }}</p>
              </div>
            </div>
          </div>

          <div class="card-actions">
            <template v-if="ref.status === 'pending'">
              <button class="btn btn-primary" @click="handleAction(ref)">
                Agendar con Especialista
              </button>
            </template>
            <template v-else-if="ref.status === 'accepted'">
              <div class="action-info success">
                <i class="pi pi-calendar-plus"></i>
                <span>
                  Cita agendada
                  <template v-if="ref.appointment">
                    para el {{ formatDate(ref.appointment.franja_start) }}
                  </template>
                </span>
              </div>
            </template>
            <template v-else-if="ref.status === 'completed'">
              <div class="action-info completed">
                <i class="pi pi-check-circle"></i>
                Consulta completada
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
  referrals: Array<{
    id: string;
    specialty_name: string;
    specialty_id: string | null;
    reason: string;
    priority: 'normal' | 'urgente';
    status: 'pending' | 'accepted' | 'completed' | 'cancelled';
    notes: string | null;
    referring_doctor: { id: string; name: string } | null;
    referred_doctor: { id: string; name: string } | null;
    referred_doctor_profile_id: string | null;
    appointment: { id: string; franja_start: string; status: string } | null;
    created_at: string;
  }>;
}>();

const currentTab = ref<'pending' | 'accepted' | 'completed'>('pending');

const filteredReferrals = computed(() => {
  const filtered = props.referrals.filter((ref) => ref.status === currentTab.value);
  
  // Sort: urgente first, then by date descending
  return filtered.sort((a, b) => {
    if (a.priority === 'urgente' && b.priority !== 'urgente') return -1;
    if (a.priority !== 'urgente' && b.priority === 'urgente') return 1;
    return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
  });
});

const handleAction = (referral: any) => {
  if (referral.referred_doctor_profile_id) {
    router.visit(`/booking/${referral.referred_doctor_profile_id}`);
  } else if (referral.specialty_id) {
    router.visit(`/paciente/directorio?specialty_id=${referral.specialty_id}`);
  } else {
    router.visit(`/paciente/directorio`);
  }
};

const formatDate = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatStatus = (status: string) => {
  const map: Record<string, string> = {
    pending: 'Pendiente',
    accepted: 'Agendada',
    completed: 'Completada',
    cancelled: 'Cancelada',
  };
  return map[status] || status;
};

const getPriorityBadgeClass = (priority: string) => {
  return priority === 'urgente' ? 'badge--urgente' : 'badge--normal';
};

const getStatusBadgeClass = (status: string) => {
  return `badge--status-${status}`;
};
</script>

<style scoped>
.referrals {
  padding: var(--spacing-4);
  max-width: 900px;
  margin: 0 auto;
  font-family: var(--font-body);
  color: var(--color-text-primary);
}

.referrals__header {
  margin-bottom: var(--spacing-5);
}

.referrals__header h1 {
  font-family: var(--font-heading);
  font-size: 1.75rem;
  margin: 0 0 var(--spacing-2) 0;
}

.referrals__header p {
  color: var(--color-text-muted);
  margin: 0;
  font-size: var(--text-sm);
}

.referrals__tabs {
  display: flex;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-4);
  border-bottom: 1px solid var(--color-surface-200);
  padding-bottom: var(--spacing-2);
}

.tab-btn {
  background: transparent;
  border: none;
  padding: var(--spacing-2) var(--spacing-4);
  font-family: var(--font-body);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  cursor: pointer;
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
}

.tab-btn:hover {
  background: var(--color-surface-100);
  color: var(--color-text-primary);
}

.tab-btn--active {
  background: var(--color-surface-200);
  color: var(--color-text-primary);
  font-weight: 600;
}

.referrals__list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.referrals__empty {
  text-align: center;
  padding: var(--spacing-5);
  background: var(--color-surface-0);
  border-radius: var(--radius-lg);
  border: 1px dashed var(--color-surface-200);
  color: var(--color-text-muted);
}

.empty-icon {
  font-size: 2.5rem;
  margin-bottom: var(--spacing-3);
  color: var(--color-surface-200);
}

.referral-card {
  background: var(--color-surface-0);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--spacing-4);
  border-left: 4px solid var(--color-surface-200);
  transition: box-shadow var(--transition-fast);
}

.referral-card:hover {
  box-shadow: var(--shadow-md);
}

@keyframes pulse-urgente {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

.referral-card--urgente {
  border-left-color: #C2410C;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--spacing-3);
}

.specialty-info h3 {
  margin: 0 0 var(--spacing-2) 0;
  font-family: var(--font-heading);
  font-size: 1.125rem;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge--normal {
  background: var(--color-surface-100);
  color: var(--color-text-muted);
}

.badge--urgente {
  background: #C2410C;
  color: #fff;
  animation: pulse-urgente 2s ease-in-out infinite;
}

.badge--status-pending {
  background: var(--color-warning-50);
  color: var(--color-warning-600);
}

.badge--status-accepted {
  background: var(--color-success-50);
  color: var(--color-success-600);
}

.badge--status-completed {
  background: var(--color-surface-100);
  color: var(--color-text-muted);
}

.badge--status-cancelled {
  background: var(--color-error-50);
  color: var(--color-error-600);
}

.urgent-notice {
  background: #FFF7ED;
  color: #C2410C;
  padding: var(--spacing-2) var(--spacing-3);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-3);
  font-weight: 500;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
  margin-bottom: var(--spacing-4);
}

.info-row {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-3);
  font-size: var(--text-sm);
}

.info-row i {
  margin-top: 0.2rem;
  color: var(--color-primary-600);
  font-size: 1rem;
}

.info-row strong {
  display: block;
  font-weight: 600;
  margin-bottom: 0.125rem;
}

.info-row p {
  margin: 0;
  color: var(--color-text-muted);
  line-height: 1.4;
}

.card-actions {
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid var(--color-surface-100);
  padding-top: var(--spacing-3);
}

.btn {
  padding: var(--spacing-2) var(--spacing-4);
  border-radius: var(--radius-md);
  font-family: var(--font-body);
  font-weight: 600;
  font-size: var(--text-sm);
  cursor: pointer;
  border: none;
  transition: all var(--transition-fast);
}

.btn-primary {
  background: var(--color-primary-600);
  color: white;
}

.btn-primary:hover {
  background: var(--color-primary-700);
}

.action-info {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-sm);
  font-weight: 500;
}

.action-info.success {
  color: var(--color-success-600);
}

.action-info.completed {
  color: var(--color-text-muted);
}
</style>
