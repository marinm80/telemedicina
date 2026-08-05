<!--
  ====================================================================
  Directory — Directorio de Especialistas
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';
import { i18nKey } from '@/i18n/plugin';
import { useAppState } from '@/composables/useAppState';
import AppLayout from '@/layouts/AppLayout.vue';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { mockDoctors, SPECIALTIES, getInitials, getAvatarColor } from '@/lib/mockData';
import type { DoctorProfile } from '@/lib/mockData';

const t = inject(i18nKey)!;

const fetcher = async (signal: AbortSignal): Promise<DoctorProfile[]> => {
  await new Promise((resolve) => setTimeout(resolve, 1000));
  signal.throwIfAborted();
  return [...mockDoctors];
};

const { items, estado, error, estaVacio, cargar } = useAppState<DoctorProfile>(fetcher);

const searchQuery = ref('');
const selectedSpecialty = ref('');

const controller = new AbortController();

onMounted(() => {
  cargar(controller.signal);
});

onUnmounted(() => {
  controller.abort();
});

const filteredDoctors = computed(() => {
  return items.value.filter((doctor) => {
    const matchesSearch = doctor.name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesSpecialty = selectedSpecialty.value
      ? doctor.specialty === selectedSpecialty.value
      : true;
    return matchesSearch && matchesSpecialty;
  });
});

function selectSpecialty(specialty: string) {
  selectedSpecialty.value = selectedSpecialty.value === specialty ? '' : specialty;
}

function clearFilters() {
  searchQuery.value = '';
  selectedSpecialty.value = '';
}
</script>

<template>
  <AppLayout>
  <div class="directory">
    <header class="directory__header">
      <h1 class="directory__title">Directorio de Especialistas</h1>
      <div class="directory__search-container">
        <i class="pi pi-search directory__search-icon" aria-hidden="true" />
        <input
          v-model="searchQuery"
          type="text"
          class="directory__search-input"
          placeholder="Buscar por nombre…"
        />
      </div>
    </header>

    <div class="directory__filters">
      <button
        type="button"
        :class="['directory__chip', { 'directory__chip--active': selectedSpecialty === '' }]"
        @click="selectSpecialty('')"
      >
        Todas
      </button>
      <button
        v-for="sp in SPECIALTIES"
        :key="sp"
        type="button"
        :class="['directory__chip', { 'directory__chip--active': selectedSpecialty === sp }]"
        @click="selectSpecialty(sp)"
      >
        {{ sp }}
      </button>
    </div>

    <!-- Estado: cargando -->
    <SpinnerLoader v-if="estado === 'cargando'" variant="card" :lines="6" />

    <!-- Estado: error -->
    <ErrorFallback
      v-else-if="estado === 'error'"
      :message="error ?? t('directory.error')"
      :on-retry="() => cargar()"
    />

    <!-- Estado: vacío (sin resultados tras filtrar) -->
    <EmptyState
      v-else-if="estado === 'listo' && filteredDoctors.length === 0"
      :message="t('directory.empty')"
      :action-label="t('directory.empty_action')"
      :on-action="clearFilters"
    />

    <!-- Estado: listo -->
    <div v-else-if="estado === 'listo'" class="directory__grid">
      <div v-for="doctor in filteredDoctors" :key="doctor.id" class="card">
        <div class="card__header">
          <div
            class="card__avatar"
            :style="{ backgroundColor: getAvatarColor(doctor.name) }"
          >
            {{ getInitials(doctor.name) }}
          </div>
          <div class="card__info">
            <h2 class="card__name">{{ doctor.name }}</h2>
            <span class="card__specialty">{{ doctor.specialty }}</span>
          </div>
        </div>

        <div class="card__body">
          <div class="card__rating">
            <div class="card__stars">
              <i
                v-for="i in 5"
                :key="i"
                :class="[
                  'pi',
                  i <= Math.round(doctor.rating)
                    ? 'pi-star-fill card__star--filled'
                    : 'pi-star card__star--empty',
                ]"
                aria-hidden="true"
              />
            </div>
            <span class="card__rating-text">
              {{ doctor.rating }} ({{ doctor.reviews_count }} reseñas)
            </span>
          </div>

          <div class="card__availability">
            <span
              :class="[
                'card__dot',
                doctor.available_slots_today > 0 ? 'card__dot--available' : 'card__dot--unavailable',
              ]"
            />
            <span class="card__avail-text">
              <template v-if="doctor.available_slots_today > 0">
                {{ doctor.available_slots_today }} turnos disponibles hoy
              </template>
              <template v-else>
                Sin disponibilidad hoy
              </template>
            </span>
          </div>
        </div>

        <div class="card__footer">
          <button type="button" class="card__cta">
            Ver Disponibilidad
          </button>
        </div>
      </div>
    </div>
  </div>
  </AppLayout>
</template>

<style scoped>
.directory {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.directory__header {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

@media (min-width: 768px) {
  .directory__header {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}

.directory__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.directory__search-container {
  position: relative;
  width: 100%;
  max-width: 22rem;
}

.directory__search-icon {
  position: absolute;
  left: var(--spacing-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-subtle);
  font-size: var(--text-sm);
}

.directory__search-input {
  width: 100%;
  padding: var(--spacing-2) var(--spacing-4) var(--spacing-2) 2.5rem;
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}

.directory__search-input:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.directory__filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2);
}

.directory__chip {
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

.directory__chip:hover {
  background-color: var(--color-surface-200);
}

.directory__chip--active {
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
}

.directory__chip--active:hover {
  background-color: var(--color-primary-600);
}

.directory__chip:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.directory__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-4);
}

@media (min-width: 640px) {
  .directory__grid { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
  .directory__grid { grid-template-columns: repeat(3, 1fr); }
}

/* --- Doctor Card --- */
.card {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-5);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.card__header {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
}

.card__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-full);
  color: var(--color-surface-0);
  font-size: var(--text-lg);
  font-weight: var(--font-bold);
  flex-shrink: 0;
}

.card__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.card__name {
  font-family: var(--font-heading);
  font-size: var(--text-base);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
}

.card__specialty {
  display: inline-block;
  padding: 1px var(--spacing-2);
  background-color: var(--color-surface-100);
  color: var(--color-text-muted);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  align-self: flex-start;
}

.card__body {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.card__rating {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.card__stars { display: flex; gap: 2px; }

.card__star--filled { color: var(--color-warning-600); font-size: var(--text-sm); }
.card__star--empty { color: var(--color-surface-200); font-size: var(--text-sm); }

.card__rating-text {
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
}

.card__availability {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.card__dot {
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.card__dot--available { background-color: var(--color-success-700); }
.card__dot--unavailable { background-color: var(--color-surface-200); }

.card__avail-text {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.card__footer {
  margin-top: auto;
  padding-top: var(--spacing-2);
}

.card__cta {
  width: 100%;
  padding: var(--spacing-2) var(--spacing-4);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.card__cta:hover { background-color: var(--color-primary-600); }

.card__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
