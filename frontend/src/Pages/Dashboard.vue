<template>
  <div class="dashboard">
    <header class="dashboard__header">
      <h1 class="dashboard__title">Bienvenido, Juan</h1>
      <p class="dashboard__subtitle">Aquí está el resumen de tu actividad</p>
    </header>

    <div class="dashboard__stats">
      <div class="stat-card stat-card--primary">
        <div class="stat-card__icon-wrapper stat-card__icon-wrapper--primary">
          <i class="pi pi-calendar"></i>
        </div>
        <div class="stat-card__content">
          <span class="stat-card__value">1</span>
          <span class="stat-card__label">Citas Hoy</span>
        </div>
      </div>
      <div class="stat-card stat-card--success">
        <div class="stat-card__icon-wrapper stat-card__icon-wrapper--success">
          <i class="pi pi-clock"></i>
        </div>
        <div class="stat-card__content">
          <span class="stat-card__value">2</span>
          <span class="stat-card__label">Próximas</span>
        </div>
      </div>
      <div class="stat-card stat-card--info">
        <div class="stat-card__icon-wrapper stat-card__icon-wrapper--info">
          <i class="pi pi-check-circle"></i>
        </div>
        <div class="stat-card__content">
          <span class="stat-card__value">1</span>
          <span class="stat-card__label">Completadas</span>
        </div>
      </div>
    </div>

    <div class="dashboard__grid">
      <section class="dashboard__section dashboard__section--main">
        <h2 class="dashboard__section-title">Citas de Hoy</h2>
        <div class="appointment-list" v-if="todayAppointments.length > 0">
          <div class="appointment-item" v-for="appt in todayAppointments" :key="appt.id">
            <div class="appointment-item__time">
              {{ formatTime(appt.franja_inicio) }}
            </div>
            <div class="appointment-item__doctor">
              <div class="appointment-item__avatar" :style="{ backgroundColor: getAvatarColor(appt.doctor_name) }">
                {{ getInitials(appt.doctor_name) }}
              </div>
              <div class="appointment-item__doctor-info">
                <span class="appointment-item__doctor-name">{{ appt.doctor_name }}</span>
                <span class="appointment-item__specialty">{{ appt.doctor_specialty }}</span>
              </div>
            </div>
            <div class="appointment-item__status">
              <span :class="['status-badge', STATUS_CONFIG[appt.status]?.cssClass]">
                <i :class="['pi', STATUS_CONFIG[appt.status]?.icon]" aria-hidden="true" />
                {{ STATUS_CONFIG[appt.status]?.label }}
              </span>
            </div>
          </div>
        </div>
        <div v-else class="dashboard__empty">
          <i class="pi pi-calendar-times dashboard__empty-icon"></i>
          <p class="dashboard__empty-text">No tienes citas programadas para hoy.</p>
        </div>
      </section>

      <aside class="dashboard__section dashboard__section--side">
        <h2 class="dashboard__section-title">Acciones Rápidas</h2>
        <div class="quick-actions">
          <button type="button" class="quick-action-btn quick-action-btn--primary">
            <i class="pi pi-search quick-action-btn__icon"></i>
            <span class="quick-action-btn__text">Buscar Especialista</span>
          </button>
          <button type="button" class="quick-action-btn quick-action-btn--secondary">
            <i class="pi pi-list quick-action-btn__icon"></i>
            <span class="quick-action-btn__text">Ver Historial de Citas</span>
          </button>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { mockAppointments, STATUS_CONFIG, getInitials, getAvatarColor } from '@/lib/mockData';
import { formatInUserTimezone } from '@/lib/timezone';

const USER_TZ = 'America/Argentina/Buenos_Aires';

const todayAppointments = computed(() => {
  return mockAppointments.filter((a) => a.status === 'confirmed' || a.status === 'pending');
});

function formatTime(isoString: string): string {
  return formatInUserTimezone(isoString, USER_TZ, {
    timeZone: USER_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });
}
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
  padding: var(--spacing-4);
  max-width: 1200px;
  margin: 0 auto;
}

.dashboard__header {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.dashboard__title {
  font-size: var(--text-2xl);
  font-weight: bold;
  color: var(--color-surface-900);
  margin: 0;
}

.dashboard__subtitle {
  font-size: var(--text-base);
  color: var(--color-surface-600);
  margin: 0;
}

.dashboard__stats {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-4);
}

@media (min-width: 768px) {
  .dashboard__stats {
    grid-template-columns: repeat(3, 1fr);
  }
}

.stat-card {
  background-color: var(--color-surface-0);
  border-radius: var(--radius-lg);
  padding: var(--spacing-4);
  display: flex;
  align-items: center;
  gap: var(--spacing-4);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition-normal);
  border-left: 4px solid transparent;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
}

.stat-card--primary { border-left-color: var(--color-primary-700); }
.stat-card--success { border-left-color: var(--color-success-700); }
.stat-card--info { border-left-color: var(--color-primary-500); }

.stat-card__icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  color: var(--color-surface-0);
  font-size: var(--text-xl);
}

.stat-card__icon-wrapper--primary { background-color: var(--color-primary-700); }
.stat-card__icon-wrapper--success { background-color: var(--color-success-700); }
.stat-card__icon-wrapper--info { background-color: var(--color-primary-500); }

.stat-card__content {
  display: flex;
  flex-direction: column;
}

.stat-card__value {
  font-size: var(--text-2xl);
  font-weight: bold;
  color: var(--color-surface-900);
}

.stat-card__label {
  font-size: var(--text-xs);
  color: var(--color-surface-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.dashboard__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-6);
}

@media (min-width: 992px) {
  .dashboard__grid {
    grid-template-columns: 2fr 1fr;
  }
}

.dashboard__section {
  background-color: var(--color-surface-0);
  border-radius: var(--radius-lg);
  padding: var(--spacing-5);
  box-shadow: var(--shadow-sm);
}

.dashboard__section-title {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--color-surface-900);
  margin-top: 0;
  margin-bottom: var(--spacing-4);
  padding-bottom: var(--spacing-2);
  border-bottom: 1px solid var(--color-surface-200);
}

.appointment-list {
  display: flex;
  flex-direction: column;
}

.appointment-item {
  display: flex;
  align-items: center;
  padding: var(--spacing-3) 0;
  border-bottom: 1px solid var(--color-surface-100);
  gap: var(--spacing-4);
}

.appointment-item:last-child {
  border-bottom: none;
}

.appointment-item__time {
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-surface-700);
  min-width: 60px;
}

.appointment-item__doctor {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  flex: 1;
}

.appointment-item__avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-surface-0);
  font-weight: bold;
  font-size: var(--text-sm);
}

.appointment-item__doctor-info {
  display: flex;
  flex-direction: column;
}

.appointment-item__doctor-name {
  font-weight: 600;
  font-size: var(--text-sm);
  color: var(--color-surface-900);
}

.appointment-item__specialty {
  font-size: var(--text-xs);
  color: var(--color-surface-500);
}

.status-badge {
  padding: var(--spacing-1) var(--spacing-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 600;
  white-space: nowrap;
}

.dashboard__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-8) 0;
  color: var(--color-surface-500);
}

.dashboard__empty-icon {
  font-size: var(--text-3xl);
  margin-bottom: var(--spacing-2);
  color: var(--color-surface-300);
}

.dashboard__empty-text {
  font-size: var(--text-sm);
}

.quick-actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  width: 100%;
  padding: var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  text-align: left;
}

.quick-action-btn:hover {
  background-color: var(--color-surface-50);
  border-color: var(--color-surface-300);
  transform: translateY(-1px);
}

.quick-action-btn:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.quick-action-btn__icon {
  font-size: var(--text-xl);
}

.quick-action-btn--primary .quick-action-btn__icon {
  color: var(--color-primary-700);
}

.quick-action-btn--secondary .quick-action-btn__icon {
  color: var(--color-primary-500);
}

.quick-action-btn__text {
  font-weight: 500;
  font-size: var(--text-sm);
  color: var(--color-surface-800);
}
</style>
