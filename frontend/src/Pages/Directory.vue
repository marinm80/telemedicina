<!--
  ====================================================================
  Directory — Directorio de Especialistas
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';

interface Doctor {
  user_id: string;
  doctor_profile_id: string;
  name: string;
  last_name: string;
  timezone: string;
  consultation_fee: number;
  description: string | null;
  years_experience: number | null;
  university: string | null;
  specialties: string[];
  photo_url: string | null;
}

interface Specialty {
  id: string;
  name: string;
  description: string | null;
}

interface Props {
  doctors: {
    data: Doctor[];
    current_page: number;
    last_page: number;
  };
  specialties: Specialty[];
  filters: {
    specialty_id?: string;
    search?: string;
  };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search || '');
const selectedSpecialtyId = ref(props.filters?.specialty_id || '');

const doctorList = computed(() => props.doctors?.data || []);

const filteredDoctors = computed(() => {
  return doctorList.value.filter((doctor) => {
    const fullName = `${doctor.name} ${doctor.last_name}`.toLowerCase();
    const matchesSearch = fullName.includes(searchQuery.value.toLowerCase());
    return matchesSearch;
  });
});

function selectSpecialty(specialtyId: string) {
  selectedSpecialtyId.value = specialtyId;
  router.get('/directory', {
    specialty_id: specialtyId || undefined,
    search: searchQuery.value || undefined,
  }, { preserveState: true, preserveScroll: true });
}

function doSearch() {
  router.get('/directory', {
    specialty_id: selectedSpecialtyId.value || undefined,
    search: searchQuery.value || undefined,
  }, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
  searchQuery.value = '';
  selectedSpecialtyId.value = '';
  router.get('/directory');
}

function doctorDisplayName(doc: Doctor): string {
  return `${doc.name} ${doc.last_name}`.trim();
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
          @keyup.enter="doSearch"
        />
      </div>
    </header>

    <div class="directory__filters">
      <button
        type="button"
        :class="['directory__chip', { 'directory__chip--active': selectedSpecialtyId === '' }]"
        @click="selectSpecialty('')"
      >
        Todas
      </button>
      <button
        v-for="sp in specialties"
        :key="sp.id"
        type="button"
        :class="['directory__chip', { 'directory__chip--active': selectedSpecialtyId === sp.id }]"
        @click="selectSpecialty(sp.id)"
      >
        {{ sp.name }}
      </button>
    </div>

    <!-- Estado: vacío -->
    <div v-if="filteredDoctors.length === 0" class="directory__empty">
      <i class="pi pi-search" style="font-size: 3rem; color: var(--color-text-muted, #9CA3AF); margin-bottom: 1rem;" />
      <p style="color: var(--color-text-muted, #6B7280); font-size: 1.1rem;">No se encontraron médicos con los filtros seleccionados.</p>
      <button type="button" class="directory__chip directory__chip--active" @click="clearFilters" style="margin-top: 1rem;">
        Limpiar filtros
      </button>
    </div>

    <!-- Estado: listo -->
    <div v-else class="directory__grid">
      <div v-for="doctor in filteredDoctors" :key="doctor.doctor_profile_id" class="card">
        <div class="card__header">
          <img v-if="doctor.photo_url" :src="doctor.photo_url" :alt="doctorDisplayName(doctor)" class="card__avatar card__avatar--photo" />
          <div
            v-else
            class="card__avatar"
            :style="{ backgroundColor: getAvatarColor(doctorDisplayName(doctor)) }"
          >
            {{ getInitials(doctorDisplayName(doctor)) }}
          </div>
          <div class="card__info">
            <h2 class="card__name">{{ doctorDisplayName(doctor) }}</h2>
            <span class="card__specialty">{{ doctor.specialties?.join(', ') || 'General' }}</span>
          </div>
        </div>

        <div class="card__body">
          <div v-if="doctor.university" class="card__detail">
            <i class="pi pi-building" aria-hidden="true" />
            <span>{{ doctor.university }}</span>
          </div>
          <div v-if="doctor.years_experience" class="card__detail">
            <i class="pi pi-clock" aria-hidden="true" />
            <span>{{ doctor.years_experience }} años de experiencia</span>
          </div>
          <div v-if="doctor.consultation_fee" class="card__detail">
            <i class="pi pi-dollar" aria-hidden="true" />
            <span>${{ doctor.consultation_fee.toFixed(2) }} por consulta</span>
          </div>
        </div>

        <div class="card__footer">
          <Link :href="`/booking/${doctor.doctor_profile_id}`" class="card__cta">
            Ver Disponibilidad
          </Link>
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
.card__avatar--photo { object-fit: cover; }

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

.card__detail {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.card__detail i {
  font-size: var(--text-sm);
  color: var(--color-primary-600);
  width: 16px;
  text-align: center;
}

.directory__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-8) var(--spacing-4);
  text-align: center;
}

.card__footer {
  margin-top: auto;
  padding-top: var(--spacing-2);
}

.card__cta {
  display: block;
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
  text-decoration: none;
  text-align: center;
  transition: background-color var(--transition-fast);
}

.card__cta:hover { background-color: var(--color-primary-600); }

.card__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
