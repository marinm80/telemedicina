<!--
  ====================================================================
  MisHorarios — Doctor self-service schedule management (Lun-Dom)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

interface ScheduleEntry {
  id: string;
  day_of_week: number;
  franja_inicio: string;
  franja_fin: string;
  slot_duration: number;
}

const page = usePage();
const auth = computed(() => (page.props as any)?.auth || {});
const loading = ref(true);
const error = ref('');
const successMsg = ref('');
const schedules = ref<ScheduleEntry[]>([]);

const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const dayAbbr = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

// Form for adding new schedule
const showAddForm = ref(false);
const addForm = ref({ day_of_week: 1, inicio: '08:00', fin: '17:00', slot_duration: 30 });
const saving = ref(false);

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function fetchSchedules() {
  loading.value = true;
  error.value = '';
  try {
    const res = await fetch('/api/doctor/schedules', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    schedules.value = data.data || [];
  } catch (e: any) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchSchedules);

// Group by day
const schedulesByDay = computed(() => {
  const grouped: Record<number, ScheduleEntry[]> = {};
  for (let d = 0; d <= 6; d++) grouped[d] = [];
  schedules.value.forEach(s => {
    if (!grouped[s.day_of_week]) grouped[s.day_of_week] = [];
    grouped[s.day_of_week].push(s);
  });
  return grouped;
});

const orderedDays = [1, 2, 3, 4, 5, 6, 0]; // Lun-Dom

async function addSchedule() {
  saving.value = true;
  error.value = '';
  try {
    const res = await fetch('/api/doctor/schedules', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        day_of_week: addForm.value.day_of_week,
        inicio: addForm.value.inicio + ':00',
        fin: addForm.value.fin + ':00',
        slot_duration: addForm.value.slot_duration,
      }),
    });

    if (res.ok) {
      successMsg.value = '✅ Horario agregado';
      showAddForm.value = false;
      await fetchSchedules();
      setTimeout(() => { successMsg.value = ''; }, 3000);
    } else {
      const data = await res.json().catch(() => ({}));
      error.value = data.message || 'Error al crear horario';
    }
  } catch (e: any) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

async function deleteSchedule(id: string) {
  if (!confirm('¿Eliminar este horario?')) return;
  try {
    const res = await fetch(`/api/doctor/schedules/${id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    });
    if (res.ok) {
      successMsg.value = '✅ Horario eliminado';
      await fetchSchedules();
      setTimeout(() => { successMsg.value = ''; }, 3000);
    } else {
      error.value = 'Error al eliminar';
    }
  } catch (e: any) {
    error.value = e.message;
  }
}

function formatTime(t: string) {
  return t.substring(0, 5);
}
</script>

<template>
  <AppLayout>
    <div class="horarios">
      <header class="horarios__header">
        <div>
          <h1 class="horarios__title">🕐 Mis Horarios</h1>
          <p class="horarios__subtitle">Configura tu disponibilidad por día de la semana</p>
        </div>
        <button class="btn-primary" @click="showAddForm = !showAddForm">
          <i class="pi" :class="showAddForm ? 'pi-times' : 'pi-plus'"></i>
          {{ showAddForm ? 'Cancelar' : 'Agregar Horario' }}
        </button>
      </header>

      <!-- Alerts -->
      <Transition name="fade">
        <div v-if="successMsg" class="alert alert--success">{{ successMsg }}</div>
      </Transition>
      <Transition name="fade">
        <div v-if="error" class="alert alert--error">{{ error }}<button class="alert__close" @click="error = ''">×</button></div>
      </Transition>

      <!-- Add form -->
      <Transition name="slide-down">
        <div v-if="showAddForm" class="add-form-card">
          <h3>Nuevo Horario</h3>
          <div class="add-form-grid">
            <div class="form-group">
              <label class="form-label">Día</label>
              <select v-model.number="addForm.day_of_week" class="form-select">
                <option v-for="d in orderedDays" :key="d" :value="d">{{ dayNames[d] }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Inicio</label>
              <input v-model="addForm.inicio" type="time" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Fin</label>
              <input v-model="addForm.fin" type="time" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Duración slot (min)</label>
              <select v-model.number="addForm.slot_duration" class="form-select">
                <option :value="15">15 min</option>
                <option :value="20">20 min</option>
                <option :value="30">30 min</option>
                <option :value="45">45 min</option>
                <option :value="60">60 min</option>
              </select>
            </div>
          </div>
          <div class="add-form-actions">
            <button class="btn-secondary" @click="showAddForm = false">Cancelar</button>
            <button class="btn-primary" @click="addSchedule" :disabled="saving">
              {{ saving ? 'Guardando...' : '💾 Guardar' }}
            </button>
          </div>
        </div>
      </Transition>

      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: var(--color-primary, #0E5D52);"></i>
        <p>Cargando horarios...</p>
      </div>

      <!-- Weekly Grid -->
      <div v-else class="week-grid">
        <div v-for="day in orderedDays" :key="day" class="day-col">
          <div class="day-col__header">
            <span class="day-col__name">{{ dayNames[day] }}</span>
            <span class="day-col__abbr">{{ dayAbbr[day] }}</span>
          </div>
          <div class="day-col__body">
            <div v-if="schedulesByDay[day].length === 0" class="day-col__empty">
              <span>Sin horario</span>
            </div>
            <div v-for="sch in schedulesByDay[day]" :key="sch.id" class="time-block">
              <div class="time-block__range">
                {{ formatTime(sch.franja_inicio) }} - {{ formatTime(sch.franja_fin) }}
              </div>
              <div class="time-block__meta">{{ sch.slot_duration }} min/cita</div>
              <button class="time-block__delete" @click="deleteSchedule(sch.id)" title="Eliminar">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.horarios { max-width: 1100px; margin: 0 auto; padding: 1rem; }
.horarios__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.horarios__title { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
.horarios__subtitle { font-size: 0.875rem; color: #6B7280; margin: 4px 0 0; }

.btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: var(--color-primary, #0E5D52); color: #FFF; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-primary:hover:not(:disabled) { filter: brightness(1.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { padding: 10px 20px; background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; font-weight: 500; cursor: pointer; }

.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; }
.alert--success { background: #D1FAE5; color: #065F46; }
.alert--error { background: #FEE2E2; color: #991B1B; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; }

.add-form-card { background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.add-form-card h3 { margin: 0 0 1rem; font-size: 1rem; color: #111827; }
.add-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
.add-form-actions { margin-top: 1rem; display: flex; justify-content: flex-end; gap: 10px; }

.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 0.82rem; font-weight: 600; color: #374151; }
.form-input, .form-select { padding: 8px 12px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 0.9rem; outline: none; }
.form-input:focus, .form-select:focus { border-color: var(--color-primary, #0E5D52); }

.loading-state { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 3rem; color: #6B7280; }

.week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }

.day-col { background: #FFF; border: 1px solid #E5E7EB; border-radius: 10px; overflow: hidden; min-height: 180px; }
.day-col__header { background: var(--color-primary, #0E5D52); color: #FFF; padding: 10px; text-align: center; }
.day-col__name { display: block; font-size: 0.82rem; font-weight: 600; }
.day-col__abbr { display: none; font-size: 0.78rem; font-weight: 600; }
.day-col__body { padding: 8px; display: flex; flex-direction: column; gap: 6px; }
.day-col__empty { padding: 16px 4px; text-align: center; color: #9CA3AF; font-size: 0.78rem; }

.time-block {
  background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px;
  padding: 8px 10px; position: relative;
}
.time-block__range { font-size: 0.85rem; font-weight: 600; color: #065F46; }
.time-block__meta { font-size: 0.72rem; color: #6B7280; margin-top: 2px; }
.time-block__delete {
  position: absolute; top: 4px; right: 4px; background: none; border: none;
  color: #9CA3AF; cursor: pointer; font-size: 0.75rem; padding: 2px;
}
.time-block__delete:hover { color: #EF4444; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-10px); opacity: 0; }

@media (max-width: 900px) {
  .week-grid { grid-template-columns: repeat(2, 1fr); }
  .day-col__name { display: none; }
  .day-col__abbr { display: block; }
}
@media (max-width: 500px) {
  .week-grid { grid-template-columns: 1fr; }
  .day-col__name { display: block; }
  .day-col__abbr { display: none; }
}
</style>
