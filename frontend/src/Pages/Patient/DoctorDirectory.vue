<!--
  ====================================================================
  DoctorDirectory — Patient view: browse approved doctors (read-only)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';

interface DoctorInfo {
  profile_id: string;
  user_id: string;
  name: string;
  last_name: string;
  specialties: string[];
  university: string;
  years_experience: number;
  consultation_fee: number;
  description: string;
}

const page = usePage();
const booking = computed(() => (page.props as any)?.booking || {});
const doctors = computed<DoctorInfo[]>(() => {
  const raw = booking.value?.doctors || [];
  const specs = booking.value?.specialties || [];
  return raw.map((d: any) => ({
    ...d,
    specialties: (d.specialty_ids || []).map((sid: string) => {
      const s = specs.find((sp: any) => sp.id === sid);
      return s ? s.name : '';
    }).filter(Boolean),
  }));
});

const searchQuery = ref('');
const filterSpec = ref('all');

import { ref } from 'vue';

const specialties = computed(() => booking.value?.specialties || []);

const filteredDoctors = computed(() => {
  let result = doctors.value;
  if (filterSpec.value !== 'all') {
    result = result.filter(d => d.specialties.some(s => {
      const spec = specialties.value.find((sp: any) => sp.id === filterSpec.value);
      return spec && s === spec.name;
    }));
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    result = result.filter(d =>
      `${d.name} ${d.last_name}`.toLowerCase().includes(q) ||
      d.specialties.some(s => s.toLowerCase().includes(q))
    );
  }
  return result;
});

function formatCurrency(val: number) {
  if (!val) return '—';
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val);
}
</script>

<template>
  <AppLayout>
    <div class="directory">
      <header class="directory__header">
        <div>
          <h1 class="directory__title">🏥 Directorio de Médicos</h1>
          <p class="directory__subtitle">Encuentra un especialista y agenda tu cita</p>
        </div>
      </header>

      <!-- Search + Filter -->
      <div class="toolbar">
        <div class="search-box">
          <i class="pi pi-search"></i>
          <input v-model="searchQuery" class="search-input" placeholder="Buscar por nombre o especialidad..." />
        </div>
        <select v-model="filterSpec" class="filter-select">
          <option value="all">Todas las especialidades</option>
          <option v-for="spec in specialties" :key="spec.id" :value="spec.id">{{ spec.name }}</option>
        </select>
      </div>

      <!-- Empty -->
      <div v-if="filteredDoctors.length === 0" class="empty-state">
        <i class="pi pi-users" style="font-size: 3rem; color: #9CA3AF;"></i>
        <p>No se encontraron médicos.</p>
      </div>

      <!-- Cards -->
      <div v-else class="doc-grid">
        <div v-for="doc in filteredDoctors" :key="doc.profile_id" class="doc-card">
          <div class="doc-card__top">
            <div class="doc-card__avatar" :style="{ backgroundColor: getAvatarColor(doc.name + ' ' + doc.last_name) }">
              {{ getInitials(doc.name + ' ' + doc.last_name) }}
            </div>
            <div class="doc-card__info">
              <h3 class="doc-card__name">Dr(a). {{ doc.name }} {{ doc.last_name }}</h3>
              <div class="doc-card__specs">
                <span v-for="spec in doc.specialties" :key="spec" class="spec-tag">{{ spec }}</span>
              </div>
            </div>
          </div>

          <div class="doc-card__body">
            <p v-if="doc.description" class="doc-card__desc">{{ doc.description }}</p>
            <div class="doc-card__meta">
              <span v-if="doc.university"><i class="pi pi-building"></i> {{ doc.university }}</span>
              <span v-if="doc.years_experience"><i class="pi pi-clock"></i> {{ doc.years_experience }} años exp.</span>
              <span v-if="doc.consultation_fee"><i class="pi pi-wallet"></i> {{ formatCurrency(doc.consultation_fee) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.directory { max-width: 1100px; margin: 0 auto; padding: 1rem; }

.directory__header { margin-bottom: 1.5rem; }
.directory__title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.directory__subtitle { font-size: 0.875rem; color: #6B7280; margin: 4px 0 0; }

.toolbar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.search-box {
  display: flex; align-items: center; gap: 8px; background: #FFF;
  border: 1px solid #D1D5DB; border-radius: 8px; padding: 8px 14px; flex: 1; min-width: 200px;
}
.search-box i { color: #9CA3AF; }
.search-input { border: none; outline: none; font-size: 0.9rem; width: 100%; background: transparent; }
.filter-select {
  padding: 8px 14px; border: 1px solid #D1D5DB; border-radius: 8px;
  font-size: 0.9rem; background: #FFF; outline: none; min-width: 180px;
}

.empty-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 3rem; color: #6B7280; }

.doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1rem; }

.doc-card {
  background: #FFF; border: 1px solid #E5E7EB; border-radius: 14px;
  padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  transition: transform 0.2s, box-shadow 0.2s;
  display: flex; flex-direction: column; gap: 14px;
}
.doc-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }

.doc-card__top { display: flex; align-items: center; gap: 12px; }
.doc-card__avatar {
  width: 52px; height: 52px; border-radius: 50%; display: flex;
  align-items: center; justify-content: center; color: #FFF;
  font-weight: 700; font-size: 0.95rem; flex-shrink: 0;
}
.doc-card__info { flex: 1; min-width: 0; }
.doc-card__name { margin: 0 0 4px; font-size: 1rem; font-weight: 600; color: #111827; }

.doc-card__specs { display: flex; flex-wrap: wrap; gap: 4px; }
.spec-tag {
  padding: 2px 10px; background: #EFF6FF; color: #1D4ED8;
  border-radius: 10px; font-size: 0.72rem; font-weight: 600;
}

.doc-card__body { display: flex; flex-direction: column; gap: 8px; }
.doc-card__desc { font-size: 0.85rem; color: #4B5563; margin: 0; line-height: 1.5; }

.doc-card__meta {
  display: flex; flex-wrap: wrap; gap: 12px;
  font-size: 0.8rem; color: #6B7280;
}
.doc-card__meta span { display: flex; align-items: center; gap: 4px; }
.doc-card__meta i { font-size: 0.75rem; }

@media (max-width: 768px) {
  .doc-grid { grid-template-columns: 1fr; }
  .toolbar { flex-direction: column; }
}
</style>
