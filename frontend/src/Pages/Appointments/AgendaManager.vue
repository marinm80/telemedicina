<!--
  ====================================================================
  AgendaManager — Configuración de Agenda (Médico)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  El médico configura sus bloques de disponibilidad semanal.
  Modo simulado con datos mock.
-->
<script setup lang="ts">
import { ref, computed, inject } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { i18nKey } from '@/i18n/plugin';

const t = inject(i18nKey)!;

interface TimeBlock {
  id: string;
  day: number;         // 0=Domingo, 1=Lunes, ..., 6=Sábado
  start_time: string;  // HH:MM
  end_time: string;    // HH:MM
  slot_duration: number; // minutos
}

const DAYS = [
  { id: 1, label: 'Lunes', short: 'Lun' },
  { id: 2, label: 'Martes', short: 'Mar' },
  { id: 3, label: 'Miércoles', short: 'Mié' },
  { id: 4, label: 'Jueves', short: 'Jue' },
  { id: 5, label: 'Viernes', short: 'Vie' },
  { id: 6, label: 'Sábado', short: 'Sáb' },
];

const blocks = ref<TimeBlock[]>([
  { id: 'b1', day: 1, start_time: '08:00', end_time: '12:00', slot_duration: 30 },
  { id: 'b2', day: 1, start_time: '14:00', end_time: '17:00', slot_duration: 30 },
  { id: 'b3', day: 3, start_time: '09:00', end_time: '13:00', slot_duration: 30 },
  { id: 'b4', day: 5, start_time: '08:00', end_time: '11:00', slot_duration: 30 },
]);

const isSaving = ref(false);
const showAddForm = ref(false);

const newBlock = ref({
  day: 1,
  start_time: '08:00',
  end_time: '12:00',
  slot_duration: 30,
});

function blocksForDay(dayId: number): TimeBlock[] {
  return blocks.value.filter((b) => b.day === dayId);
}

function totalHoursPerWeek(): string {
  let minutes = 0;
  for (const block of blocks.value) {
    const [sh, sm] = block.start_time.split(':').map(Number);
    const [eh, em] = block.end_time.split(':').map(Number);
    minutes += (eh * 60 + em) - (sh * 60 + sm);
  }
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
}

function totalSlotsPerWeek(): number {
  let total = 0;
  for (const block of blocks.value) {
    const [sh, sm] = block.start_time.split(':').map(Number);
    const [eh, em] = block.end_time.split(':').map(Number);
    const duration = (eh * 60 + em) - (sh * 60 + sm);
    total += Math.floor(duration / block.slot_duration);
  }
  return total;
}

function addBlock() {
  const id = `b${Date.now()}`;
  blocks.value.push({
    id,
    day: newBlock.value.day,
    start_time: newBlock.value.start_time,
    end_time: newBlock.value.end_time,
    slot_duration: newBlock.value.slot_duration,
  });
  showAddForm.value = false;
}

function removeBlock(id: string) {
  blocks.value = blocks.value.filter((b) => b.id !== id);
}

function saveAgenda() {
  isSaving.value = true;
  // TODO: Inertia router.put('/api/schedule', blocks.value)
  setTimeout(() => {
    isSaving.value = false;
  }, 1000);
}
</script>

<template>
  <AppLayout>
    <div class="agenda">
      <header class="agenda__header">
        <div class="agenda__title-row">
          <h1 class="agenda__title">Mi Agenda</h1>
          <button type="button" class="agenda__save" :disabled="isSaving" @click="saveAgenda">
            <i v-if="isSaving" class="pi pi-spin pi-spinner" aria-hidden="true" />
            <template v-else>
              <i class="pi pi-save" aria-hidden="true" />
              Guardar Cambios
            </template>
          </button>
        </div>
        <p class="agenda__subtitle">
          Configura tus bloques de atención semanal. Los pacientes solo podrán
          reservar en los horarios que definas aquí.
        </p>
      </header>

      <!-- Stats -->
      <div class="agenda__stats">
        <div class="stat">
          <span class="stat__value">{{ blocks.length }}</span>
          <span class="stat__label">Bloques</span>
        </div>
        <div class="stat">
          <span class="stat__value">{{ totalHoursPerWeek() }}</span>
          <span class="stat__label">Horas / semana</span>
        </div>
        <div class="stat">
          <span class="stat__value">{{ totalSlotsPerWeek() }}</span>
          <span class="stat__label">Slots / semana</span>
        </div>
      </div>

      <!-- Weekly view -->
      <div class="week-grid">
        <div v-for="day in DAYS" :key="day.id" class="day-col">
          <h3 class="day-col__title">{{ day.label }}</h3>

          <div v-if="blocksForDay(day.id).length === 0" class="day-col__empty">
            <i class="pi pi-minus" aria-hidden="true" />
            <span>Sin horario</span>
          </div>

          <div v-for="block in blocksForDay(day.id)" :key="block.id" class="block-card">
            <div class="block-card__times">
              <span class="block-card__time">{{ block.start_time }}</span>
              <span class="block-card__separator">–</span>
              <span class="block-card__time">{{ block.end_time }}</span>
            </div>
            <span class="block-card__slots">
              {{ block.slot_duration }}min / slot
            </span>
            <button
              type="button"
              class="block-card__remove"
              @click="removeBlock(block.id)"
              title="Eliminar bloque"
            >
              <i class="pi pi-trash" aria-hidden="true" />
            </button>
          </div>
        </div>
      </div>

      <!-- Add block -->
      <div class="add-section">
        <button
          v-if="!showAddForm"
          type="button"
          class="add-btn"
          @click="showAddForm = true"
        >
          <i class="pi pi-plus" aria-hidden="true" />
          Agregar Bloque
        </button>

        <div v-else class="add-form">
          <h3 class="add-form__title">Nuevo Bloque de Atención</h3>
          <div class="add-form__fields">
            <div class="add-form__field">
              <label class="add-form__label">Día</label>
              <select v-model.number="newBlock.day" class="add-form__select">
                <option v-for="d in DAYS" :key="d.id" :value="d.id">{{ d.label }}</option>
              </select>
            </div>
            <div class="add-form__field">
              <label class="add-form__label">Inicio</label>
              <input v-model="newBlock.start_time" type="time" class="add-form__input" />
            </div>
            <div class="add-form__field">
              <label class="add-form__label">Fin</label>
              <input v-model="newBlock.end_time" type="time" class="add-form__input" />
            </div>
            <div class="add-form__field">
              <label class="add-form__label">Duración slot</label>
              <select v-model.number="newBlock.slot_duration" class="add-form__select">
                <option :value="15">15 min</option>
                <option :value="30">30 min</option>
                <option :value="45">45 min</option>
                <option :value="60">60 min</option>
              </select>
            </div>
          </div>
          <div class="add-form__actions">
            <button type="button" class="add-form__cancel" @click="showAddForm = false">
              Cancelar
            </button>
            <button type="button" class="add-form__confirm" @click="addBlock">
              <i class="pi pi-plus" aria-hidden="true" />
              Agregar
            </button>
          </div>
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

.agenda__title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

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

.agenda__save {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2);
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

.agenda__save:hover:not(:disabled) { background-color: var(--color-primary-600); }
.agenda__save:disabled { opacity: 0.7; cursor: not-allowed; }
.agenda__save:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 2px; }

/* Stats */
.agenda__stats {
  display: flex;
  gap: var(--spacing-3);
}

.stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--spacing-3);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
}

.stat__value {
  font-family: var(--font-heading);
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: var(--color-primary-700);
}

.stat__label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

/* Week grid */
.week-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: var(--spacing-2);
}

@media (max-width: 768px) {
  .week-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 480px) {
  .week-grid { grid-template-columns: repeat(2, 1fr); }
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
  text-align: center;
  padding-bottom: var(--spacing-1);
  border-bottom: 2px solid var(--color-primary-500);
  margin: 0;
}

.day-col__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--spacing-3);
  color: var(--color-text-subtle);
  font-size: var(--text-xs);
}

.block-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2);
  background-color: var(--color-primary-50);
  border: 1px solid var(--color-primary-100);
  border-radius: var(--radius-md);
  position: relative;
}

.block-card__times { display: flex; align-items: center; gap: 4px; }
.block-card__time { font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--color-primary-700); }
.block-card__separator { font-size: var(--text-xs); color: var(--color-text-muted); }
.block-card__slots { font-size: 10px; color: var(--color-text-subtle); }

.block-card__remove {
  position: absolute;
  top: 2px;
  right: 2px;
  padding: 2px;
  background: none;
  border: none;
  color: var(--color-error-600);
  cursor: pointer;
  font-size: 10px;
  opacity: 0;
  transition: opacity var(--transition-fast);
}

.block-card:hover .block-card__remove { opacity: 1; }

/* Add section */
.add-section { margin-top: var(--spacing-2); }

.add-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 2px dashed var(--color-surface-200);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.add-btn:hover {
  border-color: var(--color-primary-500);
  color: var(--color-primary-700);
}

.add-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
  padding: var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
}

.add-form__title {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
}

.add-form__fields {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-3);
}

@media (max-width: 640px) {
  .add-form__fields { grid-template-columns: repeat(2, 1fr); }
}

.add-form__field { display: flex; flex-direction: column; gap: var(--spacing-1); }

.add-form__label {
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  color: var(--color-text-muted);
}

.add-form__input,
.add-form__select {
  padding: var(--spacing-2);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
}

.add-form__input:focus,
.add-form__select:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.add-form__actions {
  display: flex;
  gap: var(--spacing-2);
  justify-content: flex-end;
}

.add-form__cancel {
  padding: var(--spacing-2) var(--spacing-3);
  background: none;
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  cursor: pointer;
}

.add-form__confirm {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-3);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  cursor: pointer;
}

.add-form__confirm:hover { background-color: var(--color-primary-600); }
</style>
