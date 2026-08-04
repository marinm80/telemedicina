<!--
  ====================================================================
  AgendaManager — RF-08 Configuración de Agenda y Bloqueos
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  Dos recursos del contrato real:
    1. Franjas recurrentes: POST/DELETE /api/schedules
    2. Bloqueos puntuales:  POST/DELETE /api/schedule-blocks

  Mutaciones individuales via fetch (API REST, no navegación Inertia).
  Los datos iniciales llegan como props de Inertia desde el controlador.

  Validación client-side espeja las reglas del controlador:
    schedules:  day_of_week (1-7), inicio (H:i:s), fin (H:i:s, after:inicio), slot_duration (10-120)
    blocks:     blocked_date (Y-m-d), inicio (H:i:s), fin (H:i:s, after:inicio), reason (max 255)
-->
<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
  Schedule,
  ScheduleBlock,
  DeleteScheduleResult,
  ScheduleValidationErrors,
  BlockValidationErrors,
} from '@/lib/scheduleHelpers';
import {
  parseFranja,
  timeToApi,
  validateSchedule,
  validateBlock,
  DAYS,
} from '@/lib/scheduleHelpers';

// ── Props de Inertia (datos iniciales del controlador) ─────────────────
const props = withDefaults(
  defineProps<{
    schedules?: Schedule[];
    blocks?: ScheduleBlock[];
  }>(),
  {
    schedules: () => [],
    blocks: () => [],
  },
);

// ── Estado local ───────────────────────────────────────────────────────
const schedules = ref<Schedule[]>([...props.schedules]);
const blocks = ref<ScheduleBlock[]>([...props.blocks]);

type ActiveTab = 'schedules' | 'blocks';
const activeTab = ref<ActiveTab>('schedules');

// ── Formulario de nueva franja ─────────────────────────────────────────
const showScheduleForm = ref(false);
const scheduleForm = ref({
  day_of_week: 1,
  inicio: '08:00',
  fin: '12:00',
  slot_duration: 30,
});
const scheduleFormErrors = ref<ScheduleValidationErrors>({});
const scheduleSubmitting = ref(false);

// ── Formulario de nuevo bloqueo ────────────────────────────────────────
const showBlockForm = ref(false);
const blockForm = ref({
  blocked_date: '',
  inicio: '09:00',
  fin: '11:00',
  reason: '',
});
const blockFormErrors = ref<BlockValidationErrors>({});
const blockSubmitting = ref(false);

// ── Estado global de errores/éxito ─────────────────────────────────────
const alertMessage = ref('');
const alertType = ref<'error' | 'success' | 'warning'>('success');
const isDeleting = ref<string | null>(null);

// ── Confirmación de borrado con citas afectadas ────────────────────────
const deleteConfirm = ref<{
  id: string;
  result: DeleteScheduleResult;
} | null>(null);

// ── Computed ───────────────────────────────────────────────────────────
function schedulesForDay(dayId: number): Schedule[] {
  return schedules.value.filter((s) => s.day_of_week === dayId);
}

const totalHoursPerWeek = computed(() => {
  let minutes = 0;
  for (const s of schedules.value) {
    try {
      const { inicio, fin } = parseFranja(s.franja);
      const [sh, sm] = inicio.split(':').map(Number);
      const [eh, em] = fin.split(':').map(Number);
      minutes += (eh * 60 + em) - (sh * 60 + sm);
    } catch {
      // franja inválida — ignorar para el cálculo
    }
  }
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
});

const totalSlotsPerWeek = computed(() => {
  let total = 0;
  for (const s of schedules.value) {
    try {
      const { inicio, fin } = parseFranja(s.franja);
      const [sh, sm] = inicio.split(':').map(Number);
      const [eh, em] = fin.split(':').map(Number);
      const duration = (eh * 60 + em) - (sh * 60 + sm);
      total += Math.floor(duration / s.slot_duration);
    } catch {
      // franja inválida — ignorar
    }
  }
  return total;
});

// ── Helpers de display ─────────────────────────────────────────────────
function displayFranja(franja: string): string {
  try {
    const { inicio, fin } = parseFranja(franja);
    return `${inicio} – ${fin}`;
  } catch {
    return franja;
  }
}

function dayLabel(dayId: number): string {
  return DAYS.find((d) => d.id === dayId)?.label ?? `Día ${dayId}`;
}

function showAlert(message: string, type: 'error' | 'success' | 'warning') {
  alertMessage.value = message;
  alertType.value = type;
  setTimeout(() => { alertMessage.value = ''; }, 5000);
}

// ── Crear franja recurrente ────────────────────────────────────────────
async function createSchedule() {
  const errs = validateSchedule(scheduleForm.value);
  scheduleFormErrors.value = errs;
  if (Object.keys(errs).length > 0) return;

  scheduleSubmitting.value = true;
  try {
    const res = await fetch('/api/schedules', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        day_of_week: scheduleForm.value.day_of_week,
        inicio: timeToApi(scheduleForm.value.inicio),
        fin: timeToApi(scheduleForm.value.fin),
        slot_duration: scheduleForm.value.slot_duration,
      }),
    });

    if (res.status === 201) {
      const json = await res.json();
      schedules.value.push(json.data);
      showScheduleForm.value = false;
      showAlert('Franja creada correctamente.', 'success');
    } else if (res.status === 409) {
      const json = await res.json();
      showAlert(json.message, 'error');
    } else if (res.status === 422) {
      const json = await res.json();
      scheduleFormErrors.value = json.errors ?? {};
    } else if (res.status === 403) {
      const json = await res.json();
      showAlert(json.message, 'error');
    } else {
      showAlert('Error inesperado al crear la franja.', 'error');
    }
  } catch {
    showAlert('Error de red. Verifica tu conexión.', 'error');
  } finally {
    scheduleSubmitting.value = false;
  }
}

// ── Eliminar franja recurrente ─────────────────────────────────────────
async function deleteSchedule(id: string) {
  isDeleting.value = id;
  try {
    const res = await fetch(`/api/schedules/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    });

    if (res.ok) {
      const json: DeleteScheduleResult = await res.json();
      if (json.affected_appointments_count > 0) {
        deleteConfirm.value = { id, result: json };
      }
      schedules.value = schedules.value.filter((s) => s.id !== id);
      if (json.affected_appointments_count === 0) {
        showAlert('Franja eliminada.', 'success');
      }
    } else if (res.status === 404) {
      showAlert('Franja no encontrada.', 'error');
    } else if (res.status === 403) {
      const json = await res.json();
      showAlert(json.message, 'error');
    }
  } catch {
    showAlert('Error de red al eliminar.', 'error');
  } finally {
    isDeleting.value = null;
  }
}

// ── Crear bloqueo puntual ──────────────────────────────────────────────
async function createBlock() {
  const errs = validateBlock(blockForm.value);
  blockFormErrors.value = errs;
  if (Object.keys(errs).length > 0) return;

  blockSubmitting.value = true;
  try {
    const res = await fetch('/api/schedule-blocks', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        blocked_date: blockForm.value.blocked_date,
        inicio: timeToApi(blockForm.value.inicio),
        fin: timeToApi(blockForm.value.fin),
        reason: blockForm.value.reason,
      }),
    });

    if (res.status === 201) {
      const json = await res.json();
      blocks.value.push(json.data);
      showBlockForm.value = false;
      blockForm.value = { blocked_date: '', inicio: '09:00', fin: '11:00', reason: '' };
      showAlert('Bloqueo creado correctamente.', 'success');
    } else if (res.status === 409) {
      const json = await res.json();
      showAlert(json.message, 'error');
    } else if (res.status === 422) {
      const json = await res.json();
      blockFormErrors.value = json.errors ?? {};
    } else if (res.status === 403) {
      const json = await res.json();
      showAlert(json.message, 'error');
    } else {
      showAlert('Error inesperado al crear el bloqueo.', 'error');
    }
  } catch {
    showAlert('Error de red. Verifica tu conexión.', 'error');
  } finally {
    blockSubmitting.value = false;
  }
}

// ── Eliminar bloqueo puntual ───────────────────────────────────────────
async function deleteBlock(id: string) {
  isDeleting.value = id;
  try {
    const res = await fetch(`/api/schedule-blocks/${id}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    });

    if (res.ok) {
      blocks.value = blocks.value.filter((b) => b.id !== id);
      showAlert('Bloqueo eliminado.', 'success');
    } else if (res.status === 404) {
      showAlert('Bloqueo no encontrado.', 'error');
    } else if (res.status === 403) {
      const json = await res.json();
      showAlert(json.message, 'error');
    }
  } catch {
    showAlert('Error de red al eliminar.', 'error');
  } finally {
    isDeleting.value = null;
  }
}

// ── CSRF ───────────────────────────────────────────────────────────────
function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
  <AppLayout>
    <div class="agenda">
      <header class="agenda__header">
        <h1 class="agenda__title">Mi Agenda</h1>
        <p class="agenda__subtitle">
          Configura tus franjas de atención semanal y bloquea fechas puntuales.
        </p>
      </header>

      <!-- Alert banner -->
      <div
        v-if="alertMessage"
        :class="['agenda__alert', `agenda__alert--${alertType}`]"
        role="alert"
      >
        <i
          :class="[
            'pi',
            alertType === 'error' ? 'pi-exclamation-triangle' :
            alertType === 'warning' ? 'pi-info-circle' :
            'pi-check-circle'
          ]"
          aria-hidden="true"
        />
        <span>{{ alertMessage }}</span>
      </div>

      <!-- Affected appointments warning -->
      <div v-if="deleteConfirm" class="agenda__alert agenda__alert--warning" role="alert">
        <i class="pi pi-info-circle" aria-hidden="true" />
        <div>
          <strong>Franja eliminada.</strong>
          {{ deleteConfirm.result.affected_appointments_count }}
          cita(s) futura(s) afectada(s) que ya estaban en esa franja.
          <button type="button" class="agenda__dismiss" @click="deleteConfirm = null">
            Entendido
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="agenda__stats">
        <div class="stat">
          <span class="stat__value">{{ schedules.length }}</span>
          <span class="stat__label">Franjas</span>
        </div>
        <div class="stat">
          <span class="stat__value">{{ totalHoursPerWeek }}</span>
          <span class="stat__label">Horas / semana</span>
        </div>
        <div class="stat">
          <span class="stat__value">{{ totalSlotsPerWeek }}</span>
          <span class="stat__label">Slots / semana</span>
        </div>
        <div class="stat">
          <span class="stat__value">{{ blocks.length }}</span>
          <span class="stat__label">Bloqueos</span>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button
          type="button"
          :class="['tabs__btn', { 'tabs__btn--active': activeTab === 'schedules' }]"
          @click="activeTab = 'schedules'"
        >
          <i class="pi pi-calendar" aria-hidden="true" />
          Franjas Recurrentes
        </button>
        <button
          type="button"
          :class="['tabs__btn', { 'tabs__btn--active': activeTab === 'blocks' }]"
          @click="activeTab = 'blocks'"
        >
          <i class="pi pi-ban" aria-hidden="true" />
          Bloqueos Puntuales
        </button>
      </div>

      <!-- ===== TAB: Franjas Recurrentes ===== -->
      <div v-if="activeTab === 'schedules'">
        <div class="week-grid">
          <div v-for="day in DAYS" :key="day.id" class="day-col">
            <h3 class="day-col__title">{{ day.label }}</h3>

            <div v-if="schedulesForDay(day.id).length === 0" class="day-col__empty">
              <i class="pi pi-minus" aria-hidden="true" />
              <span>Sin horario</span>
            </div>

            <div v-for="s in schedulesForDay(day.id)" :key="s.id" class="block-card">
              <div class="block-card__times">
                <span class="block-card__time">{{ displayFranja(s.franja) }}</span>
              </div>
              <span class="block-card__slots">
                {{ s.slot_duration }}min / slot
              </span>
              <button
                type="button"
                class="block-card__remove"
                :disabled="isDeleting === s.id"
                title="Eliminar franja"
                @click="deleteSchedule(s.id)"
              >
                <i
                  :class="isDeleting === s.id ? 'pi pi-spin pi-spinner' : 'pi pi-trash'"
                  aria-hidden="true"
                />
              </button>
            </div>
          </div>
        </div>

        <!-- Add schedule form -->
        <div class="add-section">
          <button
            v-if="!showScheduleForm"
            type="button"
            class="add-btn"
            @click="showScheduleForm = true"
          >
            <i class="pi pi-plus" aria-hidden="true" />
            Agregar Franja
          </button>

          <form v-else class="add-form" @submit.prevent="createSchedule" novalidate>
            <h3 class="add-form__title">Nueva Franja Recurrente</h3>
            <div class="add-form__fields">
              <div class="add-form__field">
                <label class="add-form__label" for="sched-day">Día</label>
                <select id="sched-day" v-model.number="scheduleForm.day_of_week" class="add-form__select">
                  <option v-for="d in DAYS" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
                <span v-if="scheduleFormErrors.day_of_week" class="add-form__error">
                  {{ scheduleFormErrors.day_of_week }}
                </span>
              </div>
              <div class="add-form__field">
                <label class="add-form__label" for="sched-inicio">Inicio</label>
                <input id="sched-inicio" v-model="scheduleForm.inicio" type="time" class="add-form__input" />
                <span v-if="scheduleFormErrors.inicio" class="add-form__error">
                  {{ scheduleFormErrors.inicio }}
                </span>
              </div>
              <div class="add-form__field">
                <label class="add-form__label" for="sched-fin">Fin</label>
                <input id="sched-fin" v-model="scheduleForm.fin" type="time" class="add-form__input" />
                <span v-if="scheduleFormErrors.fin" class="add-form__error">
                  {{ scheduleFormErrors.fin }}
                </span>
              </div>
              <div class="add-form__field">
                <label class="add-form__label" for="sched-duration">Duración slot</label>
                <select id="sched-duration" v-model.number="scheduleForm.slot_duration" class="add-form__select">
                  <option :value="10">10 min</option>
                  <option :value="15">15 min</option>
                  <option :value="20">20 min</option>
                  <option :value="30">30 min</option>
                  <option :value="45">45 min</option>
                  <option :value="60">60 min</option>
                  <option :value="90">90 min</option>
                  <option :value="120">120 min</option>
                </select>
                <span v-if="scheduleFormErrors.slot_duration" class="add-form__error">
                  {{ scheduleFormErrors.slot_duration }}
                </span>
              </div>
            </div>
            <div class="add-form__actions">
              <button type="button" class="add-form__cancel" @click="showScheduleForm = false">
                Cancelar
              </button>
              <button type="submit" class="add-form__confirm" :disabled="scheduleSubmitting">
                <i v-if="scheduleSubmitting" class="pi pi-spin pi-spinner" aria-hidden="true" />
                <template v-else>
                  <i class="pi pi-plus" aria-hidden="true" />
                  Crear Franja
                </template>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- ===== TAB: Bloqueos Puntuales ===== -->
      <div v-if="activeTab === 'blocks'">
        <div v-if="blocks.length === 0" class="blocks-empty">
          <i class="pi pi-inbox" aria-hidden="true" />
          <p>No hay bloqueos puntuales configurados.</p>
        </div>

        <div v-else class="blocks-list">
          <div v-for="b in blocks" :key="b.id" class="block-item">
            <div class="block-item__info">
              <span class="block-item__date">{{ b.blocked_date }}</span>
              <span class="block-item__franja">{{ displayFranja(b.franja) }}</span>
              <span class="block-item__reason">{{ b.reason }}</span>
            </div>
            <button
              type="button"
              class="block-card__remove"
              :disabled="isDeleting === b.id"
              title="Eliminar bloqueo"
              @click="deleteBlock(b.id)"
            >
              <i
                :class="isDeleting === b.id ? 'pi pi-spin pi-spinner' : 'pi pi-trash'"
                aria-hidden="true"
              />
            </button>
          </div>
        </div>

        <!-- Add block form -->
        <div class="add-section">
          <button
            v-if="!showBlockForm"
            type="button"
            class="add-btn"
            @click="showBlockForm = true"
          >
            <i class="pi pi-plus" aria-hidden="true" />
            Agregar Bloqueo
          </button>

          <form v-else class="add-form" @submit.prevent="createBlock" novalidate>
            <h3 class="add-form__title">Nuevo Bloqueo Puntual</h3>
            <div class="add-form__fields">
              <div class="add-form__field">
                <label class="add-form__label" for="block-date">Fecha</label>
                <input id="block-date" v-model="blockForm.blocked_date" type="date" class="add-form__input" />
                <span v-if="blockFormErrors.blocked_date" class="add-form__error">
                  {{ blockFormErrors.blocked_date }}
                </span>
              </div>
              <div class="add-form__field">
                <label class="add-form__label" for="block-inicio">Inicio</label>
                <input id="block-inicio" v-model="blockForm.inicio" type="time" class="add-form__input" />
                <span v-if="blockFormErrors.inicio" class="add-form__error">
                  {{ blockFormErrors.inicio }}
                </span>
              </div>
              <div class="add-form__field">
                <label class="add-form__label" for="block-fin">Fin</label>
                <input id="block-fin" v-model="blockForm.fin" type="time" class="add-form__input" />
                <span v-if="blockFormErrors.fin" class="add-form__error">
                  {{ blockFormErrors.fin }}
                </span>
              </div>
              <div class="add-form__field add-form__field--full">
                <label class="add-form__label" for="block-reason">Motivo</label>
                <input
                  id="block-reason"
                  v-model="blockForm.reason"
                  type="text"
                  class="add-form__input"
                  placeholder="Ej: Vacaciones, Conferencia médica"
                  maxlength="255"
                />
                <span v-if="blockFormErrors.reason" class="add-form__error">
                  {{ blockFormErrors.reason }}
                </span>
              </div>
            </div>
            <div class="add-form__actions">
              <button type="button" class="add-form__cancel" @click="showBlockForm = false">
                Cancelar
              </button>
              <button type="submit" class="add-form__confirm" :disabled="blockSubmitting">
                <i v-if="blockSubmitting" class="pi pi-spin pi-spinner" aria-hidden="true" />
                <template v-else>
                  <i class="pi pi-plus" aria-hidden="true" />
                  Crear Bloqueo
                </template>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.agenda {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-5);
  max-width: 64rem;
  margin: 0 auto;
}

.agenda__header { display: flex; flex-direction: column; gap: var(--spacing-2); }

.agenda__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.agenda__subtitle {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}

/* Alert banner */
.agenda__alert {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-4);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  line-height: var(--leading-normal);
}

.agenda__alert--error {
  background-color: var(--color-error-100);
  color: var(--color-error-700);
  border-left: 4px solid var(--color-error-600);
}

.agenda__alert--success {
  background-color: var(--color-success-50);
  color: var(--color-success-800);
  border-left: 4px solid var(--color-success-700);
}

.agenda__alert--warning {
  background-color: var(--color-warning-50);
  color: var(--color-warning-800);
  border-left: 4px solid var(--color-warning-800);
}

.agenda__alert i { flex-shrink: 0; margin-top: 2px; }

.agenda__dismiss {
  display: inline;
  background: none;
  border: none;
  color: inherit;
  font-weight: var(--font-semibold);
  text-decoration: underline;
  cursor: pointer;
  font-size: var(--text-sm);
  padding: 0;
  margin-left: var(--spacing-2);
}

/* Stats */
.agenda__stats {
  display: flex;
  gap: var(--spacing-3);
  flex-wrap: wrap;
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--spacing-3) var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  min-width: 6rem;
}

.stat__value {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: var(--color-primary-700);
}

.stat__label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

/* Tabs */
.tabs {
  display: flex;
  gap: var(--spacing-1);
  border-bottom: 2px solid var(--color-surface-200);
  padding-bottom: 0;
}

.tabs__btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  color: var(--color-text-muted);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  transition: color var(--transition-fast), border-color var(--transition-fast);
}

.tabs__btn:hover { color: var(--color-text-strong); }

.tabs__btn--active {
  color: var(--color-primary-700);
  border-bottom-color: var(--color-primary-700);
}

.tabs__btn:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* Week grid */
.week-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
  gap: var(--spacing-3);
}

.day-col {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.day-col__title {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
  padding-bottom: var(--spacing-1);
  border-bottom: 1px solid var(--color-surface-200);
}

.day-col__empty {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  padding: var(--spacing-2);
}

.block-card {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
  padding: var(--spacing-2);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-left: 3px solid var(--color-primary-500);
  border-radius: var(--radius-md);
  position: relative;
}

.block-card__times { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--color-text-strong); }

.block-card__slots { font-size: var(--text-xs); color: var(--color-text-muted); }

.block-card__remove {
  position: absolute;
  top: var(--spacing-1);
  right: var(--spacing-1);
  background: none;
  border: none;
  color: var(--color-text-subtle);
  cursor: pointer;
  padding: var(--spacing-1);
  border-radius: var(--radius-sm);
  font-size: var(--text-xs);
  transition: color var(--transition-fast), background-color var(--transition-fast);
}

.block-card__remove:hover { color: var(--color-error-600); background-color: var(--color-error-100); }
.block-card__remove:disabled { opacity: 0.5; cursor: not-allowed; }

.block-card__remove:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* Blocks list (puntuales) */
.blocks-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-8) var(--spacing-4);
  color: var(--color-text-subtle);
  text-align: center;
}

.blocks-empty i { font-size: var(--text-2xl); }
.blocks-empty p { font-size: var(--text-sm); margin: 0; }

.blocks-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.block-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-3) var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-left: 3px solid var(--color-warning-800);
  border-radius: var(--radius-md);
}

.block-item__info {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2);
  align-items: center;
}

.block-item__date {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.block-item__franja {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.block-item__reason {
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  font-style: italic;
}

/* Add section */
.add-section { margin-top: var(--spacing-3); }

.add-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  color: var(--color-primary-700);
  background: none;
  border: 1px dashed var(--color-primary-500);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.add-btn:hover { background-color: var(--color-primary-50); }

.add-btn:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.add-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
}

.add-form__title {
  font-size: var(--text-base);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
}

.add-form__fields {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
  gap: var(--spacing-3);
}

.add-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.add-form__field--full { grid-column: 1 / -1; }

.add-form__label {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
}

.add-form__input,
.add-form__select {
  padding: var(--spacing-2);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
}

.add-form__input:focus,
.add-form__select:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.add-form__error {
  font-size: var(--text-xs);
  color: var(--color-error-700);
}

.add-form__actions {
  display: flex;
  gap: var(--spacing-2);
  justify-content: flex-end;
}

.add-form__cancel {
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-muted);
  background: none;
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.add-form__cancel:hover { background-color: var(--color-surface-100); }

.add-form__confirm {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  color: var(--color-surface-0);
  background-color: var(--color-primary-700);
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.add-form__confirm:hover:not(:disabled) { background-color: var(--color-primary-600); }
.add-form__confirm:disabled { opacity: 0.7; cursor: not-allowed; }

.add-form__confirm:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
