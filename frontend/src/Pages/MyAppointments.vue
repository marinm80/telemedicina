<!--
  ====================================================================
  MyAppointments — Mis Citas (Paciente)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';
import { i18nKey } from '@/i18n/plugin';
import { useAppState } from '@/composables/useAppState';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { mockAppointments, STATUS_CONFIG, getInitials, getAvatarColor } from '@/lib/mockData';
import { formatInUserTimezone } from '@/lib/timezone';
import type { AppointmentDisplay } from '@/lib/mockData';

const t = inject(i18nKey)!;
const USER_TZ = 'America/Argentina/Buenos_Aires';

type FilterTab = 'todas' | 'proximas' | 'pasadas';
const activeTab = ref<FilterTab>('todas');

const fetcher = async (signal: AbortSignal): Promise<AppointmentDisplay[]> => {
  await new Promise((resolve) => setTimeout(resolve, 800));
  signal.throwIfAborted();
  return [...mockAppointments];
};

const { items, estado, error, estaVacio, cargar } = useAppState<AppointmentDisplay>(fetcher);

const controller = new AbortController();

onMounted(() => {
  cargar(controller.signal);
});

onUnmounted(() => {
  controller.abort();
});

const filteredAppointments = computed(() => {
  if (activeTab.value === 'proximas') {
    return items.value.filter((a) => a.status === 'pending' || a.status === 'confirmed');
  }
  if (activeTab.value === 'pasadas') {
    return items.value.filter((a) => a.status === 'completed' || a.status === 'cancelled');
  }
  return items.value;
});

function formatDate(iso: string): string {
  return formatInUserTimezone(iso, USER_TZ, {
    timeZone: USER_TZ,
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function formatTime(iso: string): string {
  return formatInUserTimezone(iso, USER_TZ, {
    timeZone: USER_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}

const TABS: { id: FilterTab; label: string }[] = [
  { id: 'todas', label: 'Todas' },
  { id: 'proximas', label: 'Próximas' },
  { id: 'pasadas', label: 'Pasadas' },
];

function statusBorderColor(status: string): string {
  const map: Record<string, string> = {
    pending: 'var(--color-warning-600)',
    confirmed: 'var(--color-success-700)',
    cancelled: 'var(--color-error-600)',
    completed: 'var(--color-primary-500)',
  };
  return map[status] ?? 'var(--color-surface-200)';
}
</script>

<template>
  <div class="appointments">
    <header class="appointments__header">
      <h1 class="appointments__title">Mis Citas</h1>
      <p v-if="estado === 'listo'" class="appointments__subtitle">
        {{ items.length }} citas en total
      </p>
    </header>

    <div class="appointments__tabs">
      <button
        v-for="tab in TABS"
        :key="tab.id"
        type="button"
        :class="['appointments__tab', { 'appointments__tab--active': activeTab === tab.id }]"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Estado: cargando -->
    <SpinnerLoader v-if="estado === 'cargando'" variant="list" :lines="4" />

    <!-- Estado: error -->
    <ErrorFallback
      v-else-if="estado === 'error'"
      :message="error ?? t('history.error')"
      :on-retry="() => cargar()"
    />

    <!-- Estado: vacío -->
    <EmptyState
      v-else-if="estaVacio"
      :message="t('history.empty')"
      :action-label="t('history.empty_action')"
    />

    <!-- Sin resultados en el filtro activo -->
    <EmptyState
      v-else-if="estado === 'listo' && filteredAppointments.length === 0"
      message="No hay citas en esta categoría."
    />

    <!-- Estado: listo -->
    <div v-else-if="estado === 'listo'" class="appointments__list">
      <div
        v-for="appt in filteredAppointments"
        :key="appt.id"
        class="appt-card"
        :style="{ borderLeftColor: statusBorderColor(appt.status) }"
      >
        <div
          class="appt-card__avatar"
          :style="{ backgroundColor: getAvatarColor(appt.doctor_name) }"
        >
          {{ getInitials(appt.doctor_name) }}
        </div>

        <div class="appt-card__info">
          <span class="appt-card__doctor">{{ appt.doctor_name }}</span>
          <span class="appt-card__specialty">{{ appt.doctor_specialty }}</span>
          <span class="appt-card__datetime">
            <i class="pi pi-calendar" aria-hidden="true" />
            {{ formatDate(appt.franja_inicio) }}
            · {{ formatTime(appt.franja_inicio) }} – {{ formatTime(appt.franja_fin) }}
          </span>
        </div>

        <div class="appt-card__actions">
          <span :class="['appt-card__badge', STATUS_CONFIG[appt.status]?.cssClass]">
            <i :class="['pi', STATUS_CONFIG[appt.status]?.icon]" aria-hidden="true" />
            {{ STATUS_CONFIG[appt.status]?.label }}
          </span>
          <button
            v-if="appt.status === 'pending' || appt.status === 'confirmed'"
            type="button"
            class="appt-card__cancel"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.appointments {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.appointments__header {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.appointments__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.appointments__subtitle {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.appointments__tabs {
  display: flex;
  gap: var(--spacing-2);
}

.appointments__tab {
  padding: var(--spacing-1) var(--spacing-3);
  background-color: var(--color-surface-100);
  color: var(--color-text-muted);
  border: none;
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.appointments__tab:hover {
  background-color: var(--color-surface-200);
}

.appointments__tab--active {
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
}

.appointments__tab:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.appointments__list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

/* --- Appointment Card --- */
.appt-card {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-left: 4px solid;
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  transition: background-color var(--transition-fast);
}

.appt-card:hover {
  background-color: var(--color-surface-50);
}

.appt-card__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
  color: var(--color-surface-0);
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  flex-shrink: 0;
}

.appt-card__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.appt-card__doctor {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.appt-card__specialty {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.appt-card__datetime {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  margin-top: 2px;
}

.appt-card__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--spacing-2);
  flex-shrink: 0;
}

.appt-card__badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px var(--spacing-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  white-space: nowrap;
}

.status--pending {
  background-color: var(--color-warning-50);
  color: var(--color-warning-800);
}

.status--confirmed {
  background-color: var(--color-success-50);
  color: var(--color-success-800);
}

.status--cancelled {
  background-color: var(--color-error-50);
  color: var(--color-error-700);
}

.status--completed {
  background-color: var(--color-surface-100);
  color: var(--color-text-muted);
}

.appt-card__cancel {
  padding: 2px var(--spacing-2);
  background: none;
  border: 1px solid var(--color-error-600);
  border-radius: var(--radius-sm);
  color: var(--color-error-700);
  font-size: var(--text-xs);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.appt-card__cancel:hover {
  background-color: var(--color-error-50);
}

.appt-card__cancel:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

@media (max-width: 640px) {
  .appt-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .appt-card__actions {
    flex-direction: row;
    align-items: center;
    width: 100%;
  }
}
</style>
