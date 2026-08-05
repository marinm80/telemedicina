<!--
  ====================================================================
  ScheduleManager — Admin panel for managing doctor work schedules
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';

interface ScheduleItem {
  id: string;
  day_of_week: number;
  franja: string;
  slot_duration: number;
}

interface DoctorScheduleGroup {
  doctor_profile_id: string;
  doctor_name: string;
  schedules: ScheduleItem[];
}

const DAY_NAMES: Record<number, string> = {
  0: 'Domingo', 1: 'Lunes', 2: 'Martes', 3: 'Miércoles',
  4: 'Jueves', 5: 'Viernes', 6: 'Sábado'
};

const loading = ref(true);
const saving = ref(false);
const doctorGroups = ref<DoctorScheduleGroup[]>([]);
const error = ref('');
const successMsg = ref('');

// Form state
const showForm = ref(false);
const formDoctor = ref('');
const formDays = ref<number[]>([]);
const formInicio = ref('08:00');
const formFin = ref('17:00');
const formSlotDuration = ref(30);

// Available doctors from booking shared props
const allDoctors = ref<any[]>([]);

async function fetchSchedules() {
  loading.value = true;
  error.value = '';
  try {
    const res = await fetch('/api/admin/schedules', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    doctorGroups.value = data.data || [];
  } catch (e: any) {
    error.value = e.message || 'Error al cargar horarios';
  } finally {
    loading.value = false;
  }
}

async function fetchDoctors() {
  try {
    const res = await fetch('/api/admin/schedules', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    // We'll get doctor list from the shared Inertia props instead
  } catch (e) {
    // silent
  }
}

onMounted(async () => {
  await fetchSchedules();
  // Get doctors from Inertia shared props
  const pageProps = (window as any).__page?.props;
  if (pageProps?.booking?.doctors) {
    allDoctors.value = pageProps.booking.doctors;
  }
});

function parseFranja(franja: string): { inicio: string; fin: string } {
  // "[08:00:00,17:00:00)" → { inicio: "08:00", fin: "17:00" }
  const clean = franja.replace(/[\[\]\(\)]/g, '');
  const [inicio, fin] = clean.split(',');
  return {
    inicio: inicio?.substring(0, 5) || '08:00',
    fin: fin?.substring(0, 5) || '17:00',
  };
}

function getDoctorScheduleForDay(group: DoctorScheduleGroup, day: number): ScheduleItem | undefined {
  return group.schedules.find(s => s.day_of_week === day);
}

async function addSchedule() {
  if (!formDoctor.value || formDays.value.length === 0) {
    error.value = 'Selecciona un médico y al menos un día';
    return;
  }

  saving.value = true;
  error.value = '';
  successMsg.value = '';

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta?.getAttribute('content') || '';

  let created = 0;
  let errors: string[] = [];

  for (const day of formDays.value) {
    try {
      const res = await fetch('/api/admin/schedules', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          doctor_profile_id: formDoctor.value,
          day_of_week: day,
          inicio: `${formInicio.value}:00`,
          fin: `${formFin.value}:00`,
          slot_duration: formSlotDuration.value,
        }),
      });

      if (res.ok) {
        created++;
      } else if (res.status === 409) {
        errors.push(`${DAY_NAMES[day]}: ya existe horario`);
      } else {
        const data = await res.json().catch(() => ({}));
        errors.push(`${DAY_NAMES[day]}: ${data.message || 'error'}`);
      }
    } catch (e: any) {
      errors.push(`${DAY_NAMES[day]}: ${e.message}`);
    }
  }

  saving.value = false;

  if (created > 0) {
    successMsg.value = `✅ ${created} horario(s) creado(s)`;
    if (errors.length) successMsg.value += ` (${errors.length} conflicto(s))`;
    showForm.value = false;
    formDays.value = [];
    await fetchSchedules();
  } else {
    error.value = errors.join('; ') || 'No se pudo crear ningún horario';
  }

  setTimeout(() => { successMsg.value = ''; }, 4000);
}

async function deleteSchedule(scheduleId: string) {
  if (!confirm('¿Eliminar este horario?')) return;

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta?.getAttribute('content') || '';

  try {
    const res = await fetch(`/api/admin/schedules/${scheduleId}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
    });

    if (res.ok) {
      successMsg.value = '✅ Horario eliminado';
      await fetchSchedules();
      setTimeout(() => { successMsg.value = ''; }, 3000);
    } else {
      error.value = 'No se pudo eliminar el horario';
    }
  } catch (e: any) {
    error.value = e.message;
  }
}

function toggleDay(day: number) {
  const idx = formDays.value.indexOf(day);
  if (idx >= 0) formDays.value.splice(idx, 1);
  else formDays.value.push(day);
}

function selectWeekdays() {
  formDays.value = [1, 2, 3, 4, 5];
}
</script>

<template>
  <AppLayout>
    <div class="schedule-mgr">
      <header class="schedule-mgr__header">
        <div>
          <h1 class="schedule-mgr__title">Gestión de Horarios</h1>
          <p class="schedule-mgr__subtitle">Administra los horarios de trabajo de cada médico</p>
        </div>
        <button class="btn-primary" @click="showForm = !showForm">
          <i class="pi pi-plus"></i>
          {{ showForm ? 'Cancelar' : 'Asignar Horario' }}
        </button>
      </header>

      <!-- Success/Error messages -->
      <Transition name="fade">
        <div v-if="successMsg" class="alert alert--success">{{ successMsg }}</div>
      </Transition>
      <Transition name="fade">
        <div v-if="error" class="alert alert--error">
          {{ error }}
          <button class="alert__close" @click="error = ''">×</button>
        </div>
      </Transition>

      <!-- Add Schedule Form -->
      <Transition name="slide-down">
        <div v-if="showForm" class="form-card">
          <h3 class="form-card__title">Asignar Nuevo Horario</h3>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Médico</label>
              <select v-model="formDoctor" class="form-select">
                <option value="">Seleccionar médico...</option>
                <option v-for="doc in allDoctors" :key="doc.doctor_profile_id" :value="doc.doctor_profile_id">
                  {{ doc.full_name }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Duración de turno</label>
              <select v-model.number="formSlotDuration" class="form-select">
                <option :value="15">15 min</option>
                <option :value="20">20 min</option>
                <option :value="30">30 min</option>
                <option :value="45">45 min</option>
                <option :value="60">60 min</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Hora inicio</label>
              <input type="time" v-model="formInicio" class="form-input" />
            </div>

            <div class="form-group">
              <label class="form-label">Hora fin</label>
              <input type="time" v-model="formFin" class="form-input" />
            </div>
          </div>

          <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label">Días de la semana</label>
            <div class="day-selector">
              <button
                v-for="day in [1,2,3,4,5,6,0]"
                :key="day"
                type="button"
                :class="['day-btn', { 'day-btn--active': formDays.includes(day) }]"
                @click="toggleDay(day)"
              >
                {{ DAY_NAMES[day]?.substring(0, 3) }}
              </button>
              <button type="button" class="day-btn day-btn--shortcut" @click="selectWeekdays">
                Lun-Vie
              </button>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn-primary" @click="addSchedule" :disabled="saving">
              {{ saving ? 'Guardando...' : '💾 Guardar Horario' }}
            </button>
          </div>
        </div>
      </Transition>

      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: var(--color-primary, #0E5D52);"></i>
        <p>Cargando horarios...</p>
      </div>

      <!-- Schedule Grid by Doctor -->
      <div v-else-if="doctorGroups.length === 0" class="empty-state">
        <i class="pi pi-calendar" style="font-size: 3rem; color: #9CA3AF;"></i>
        <p>No hay horarios configurados.</p>
        <button class="btn-primary" @click="showForm = true">Asignar primer horario</button>
      </div>

      <div v-else class="doctor-list">
        <div v-for="group in doctorGroups" :key="group.doctor_profile_id" class="doctor-schedule-card">
          <div class="doctor-schedule-card__header">
            <div class="doctor-avatar" :style="{ backgroundColor: getAvatarColor(group.doctor_name) }">
              {{ getInitials(group.doctor_name) }}
            </div>
            <div>
              <h3 class="doctor-schedule-card__name">{{ group.doctor_name }}</h3>
              <span class="doctor-schedule-card__count">{{ group.schedules.length }} franja(s) configurada(s)</span>
            </div>
          </div>

          <div class="week-grid">
            <div
              v-for="day in [1,2,3,4,5,6,0]"
              :key="day"
              :class="['week-cell', { 'week-cell--active': getDoctorScheduleForDay(group, day) }]"
            >
              <div class="week-cell__day">{{ DAY_NAMES[day]?.substring(0, 3) }}</div>
              <template v-if="getDoctorScheduleForDay(group, day)">
                <div class="week-cell__time">
                  {{ parseFranja(getDoctorScheduleForDay(group, day)!.franja).inicio }}
                  -
                  {{ parseFranja(getDoctorScheduleForDay(group, day)!.franja).fin }}
                </div>
                <div class="week-cell__slot">{{ getDoctorScheduleForDay(group, day)!.slot_duration }} min</div>
                <button
                  class="week-cell__delete"
                  title="Eliminar"
                  @click="deleteSchedule(getDoctorScheduleForDay(group, day)!.id)"
                >
                  <i class="pi pi-trash"></i>
                </button>
              </template>
              <template v-else>
                <div class="week-cell__off">—</div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.schedule-mgr {
  max-width: 1100px;
  margin: 0 auto;
  padding: var(--spacing-4, 1rem);
}

.schedule-mgr__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: var(--spacing-6, 1.5rem);
  flex-wrap: wrap;
  gap: 1rem;
}

.schedule-mgr__title {
  font-family: var(--font-heading, 'Inter', sans-serif);
  font-size: var(--text-2xl, 1.5rem);
  font-weight: 700;
  color: var(--color-text-strong, #111827);
  margin: 0;
}

.schedule-mgr__subtitle {
  font-size: var(--text-sm, 0.875rem);
  color: var(--color-text-muted, #6B7280);
  margin: 4px 0 0;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  background: var(--color-primary, #0E5D52);
  color: #FFF;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary:hover:not(:disabled) { filter: brightness(1.15); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert--success { background: #D1FAE5; color: #065F46; }
.alert--error { background: #FEE2E2; color: #991B1B; }
.alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; }

.form-card {
  background: #FFF;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.form-card__title { margin: 0 0 1rem; font-size: 1.1rem; color: #111827; }

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.form-group { display: flex; flex-direction: column; gap: 4px; }

.form-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
}

.form-select, .form-input {
  padding: 8px 12px;
  border: 1px solid #D1D5DB;
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.2s;
}

.form-select:focus, .form-input:focus { border-color: var(--color-primary, #0E5D52); }

.day-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.day-btn {
  padding: 6px 14px;
  border: 1px solid #D1D5DB;
  background: #FFF;
  color: #374151;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.day-btn--active {
  background: var(--color-primary, #0E5D52);
  color: #FFF;
  border-color: var(--color-primary, #0E5D52);
}

.day-btn--shortcut {
  border-style: dashed;
  color: var(--color-primary, #0E5D52);
  font-weight: 600;
}

.day-btn--shortcut:hover { background: #F0FDF4; }

.form-actions { margin-top: 1.5rem; display: flex; justify-content: flex-end; }

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 3rem;
  color: #6B7280;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 3rem;
  text-align: center;
  color: #6B7280;
}

.doctor-list { display: flex; flex-direction: column; gap: 1.5rem; }

.doctor-schedule-card {
  background: #FFF;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.doctor-schedule-card__header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.doctor-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #FFF;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.doctor-schedule-card__name {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.doctor-schedule-card__count {
  font-size: 0.8rem;
  color: #6B7280;
}

.week-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.week-cell {
  background: #F9FAFB;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  padding: 10px 6px;
  text-align: center;
  min-height: 90px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  position: relative;
}

.week-cell--active {
  background: #F0FDF4;
  border-color: #86EFAC;
}

.week-cell__day {
  font-weight: 700;
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #374151;
}

.week-cell__time {
  font-size: 0.78rem;
  color: #065F46;
  font-weight: 600;
}

.week-cell__slot {
  font-size: 0.7rem;
  color: #6B7280;
  background: #E5E7EB;
  padding: 2px 8px;
  border-radius: 10px;
}

.week-cell__off {
  font-size: 1.2rem;
  color: #D1D5DB;
  margin-top: 8px;
}

.week-cell__delete {
  position: absolute;
  top: 4px;
  right: 4px;
  background: none;
  border: none;
  color: #EF4444;
  cursor: pointer;
  font-size: 0.7rem;
  opacity: 0;
  transition: opacity 0.2s;
}

.week-cell:hover .week-cell__delete { opacity: 1; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-10px); opacity: 0; }

@media (max-width: 768px) {
  .week-grid { grid-template-columns: repeat(3, 1fr); }
  .schedule-mgr__header { flex-direction: column; }
}
</style>
