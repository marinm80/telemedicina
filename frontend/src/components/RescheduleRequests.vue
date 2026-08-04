<!--
  ====================================================================
  RescheduleRequests — Gestión de solicitudes de reprogramación (Médico)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  RF-11 Solicitud y Aprobación de Reprogramación (lado médico)
  Endpoints:
    POST /api/reschedule-requests/{id}/approve
    POST /api/reschedule-requests/{id}/reject
  ====================================================================
-->
<script setup lang="ts">
import { ref } from 'vue';
import { getCsrfToken } from '@/lib/appointmentHelpers';

interface RescheduleItem {
  id: string;
  patient_name: string;
  current_start: string;
  current_end: string;
  requested_start: string;
  requested_end: string;
  reason: string;
  status: 'pending' | 'approved' | 'rejected';
}

const props = defineProps<{
  requests: RescheduleItem[];
}>();

const processingId = ref<string | null>(null);
const error = ref('');
const localRequests = ref<RescheduleItem[]>([...props.requests]);

async function handleAction(requestId: string, action: 'approve' | 'reject') {
  processingId.value = requestId;
  error.value = '';

  try {
    const res = await fetch(`/api/reschedule-requests/${requestId}/${action}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    });

    if (res.ok) {
      const idx = localRequests.value.findIndex((r) => r.id === requestId);
      if (idx >= 0) {
        localRequests.value[idx] = {
          ...localRequests.value[idx],
          status: action === 'approve' ? 'approved' : 'rejected',
        };
      }
    } else {
      const json = await res.json().catch(() => ({}));
      error.value = (json as Record<string, string>).message ?? `Error al ${action === 'approve' ? 'aprobar' : 'rechazar'}.`;
    }
  } catch {
    error.value = 'Error de red.';
  } finally {
    processingId.value = null;
  }
}

function formatDT(iso: string): string {
  return new Intl.DateTimeFormat('es', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(iso));
}
</script>

<template>
  <section class="resched">
    <h2 class="resched__title">
      <i class="pi pi-calendar-plus" aria-hidden="true" />
      Solicitudes de Reprogramación
    </h2>

    <p v-if="localRequests.length === 0" class="resched__empty">
      No hay solicitudes pendientes.
    </p>

    <p v-if="error" class="resched__error" role="alert">
      <i class="pi pi-exclamation-circle" aria-hidden="true" />
      {{ error }}
    </p>

    <div class="resched__list">
      <div
        v-for="req in localRequests"
        :key="req.id"
        :class="['resched__card', `resched__card--${req.status}`]"
      >
        <div class="resched__card-header">
          <strong>{{ req.patient_name }}</strong>
          <span :class="['resched__badge', `resched__badge--${req.status}`]">
            {{ req.status === 'pending' ? 'Pendiente' : req.status === 'approved' ? 'Aprobada' : 'Rechazada' }}
          </span>
        </div>

        <div class="resched__card-body">
          <div class="resched__row">
            <span class="resched__label">Horario actual:</span>
            <span>{{ formatDT(req.current_start) }} – {{ formatDT(req.current_end) }}</span>
          </div>
          <div class="resched__row">
            <span class="resched__label">Horario solicitado:</span>
            <span>{{ formatDT(req.requested_start) }} – {{ formatDT(req.requested_end) }}</span>
          </div>
          <div class="resched__row">
            <span class="resched__label">Motivo:</span>
            <span>{{ req.reason }}</span>
          </div>
        </div>

        <div v-if="req.status === 'pending'" class="resched__actions">
          <button
            type="button"
            class="resched__btn resched__btn--approve"
            :disabled="processingId === req.id"
            @click="handleAction(req.id, 'approve')"
          >
            <i class="pi pi-check" aria-hidden="true" />
            Aprobar
          </button>
          <button
            type="button"
            class="resched__btn resched__btn--reject"
            :disabled="processingId === req.id"
            @click="handleAction(req.id, 'reject')"
          >
            <i class="pi pi-times" aria-hidden="true" />
            Rechazar
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.resched__title {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0 0 var(--spacing-4) 0;
}

.resched__title i {
  color: var(--color-primary-700);
}

.resched__empty {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  text-align: center;
  padding: var(--spacing-4);
}

.resched__error {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2);
  background-color: var(--color-danger-50);
  color: var(--color-danger-800);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  margin-bottom: var(--spacing-3);
}

.resched__list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.resched__card {
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  padding: var(--spacing-3);
  background-color: var(--color-surface-0);
}

.resched__card--approved {
  border-color: var(--color-success-200);
  background-color: var(--color-success-50);
}

.resched__card--rejected {
  border-color: var(--color-surface-200);
  opacity: 0.6;
}

.resched__card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--spacing-2);
}

.resched__badge {
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  padding: 2px var(--spacing-2);
  border-radius: var(--radius-full);
}

.resched__badge--pending {
  background-color: var(--color-warning-100);
  color: var(--color-warning-800);
}

.resched__badge--approved {
  background-color: var(--color-success-100);
  color: var(--color-success-800);
}

.resched__badge--rejected {
  background-color: var(--color-surface-200);
  color: var(--color-text-muted);
}

.resched__card-body {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.resched__row {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.resched__label {
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin-right: var(--spacing-1);
}

.resched__actions {
  display: flex;
  gap: var(--spacing-2);
  margin-top: var(--spacing-3);
  padding-top: var(--spacing-2);
  border-top: 1px solid var(--color-surface-100);
}

.resched__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-3);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
  border: none;
}

.resched__btn--approve {
  background-color: var(--color-success-700);
  color: white;
}

.resched__btn--approve:hover:not(:disabled) {
  background-color: var(--color-success-600);
}

.resched__btn--reject {
  background-color: transparent;
  color: var(--color-text-muted);
  border: 1px solid var(--color-surface-300);
}

.resched__btn--reject:hover:not(:disabled) {
  background-color: var(--color-surface-100);
}

.resched__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
