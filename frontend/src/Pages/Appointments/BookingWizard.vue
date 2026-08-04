<!--
  ====================================================================
  BookingWizard — RF-09 Reserva de Citas sin Solapamiento
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  Wizard de 3 pasos: Seleccionar médico → Elegir horario → Confirmar.

  Contrato real:
    GET  /api/doctors/{id}/availability?date=YYYY-MM-DD → slots
    POST /api/appointments  (X-Idempotency-Key, franja_inicio, franja_fin)

  Step 1 recibe doctores como props de Inertia.
  Steps 2-3 usan fetch contra la API REST.

  Validación client-side espeja BookAppointmentRequest:
    patient_id, doctor_id: required, uuid
    franja_inicio: required, date, after:now, max 1 año
    franja_fin: required, date, after:franja_inicio, exactamente 30 min
-->
<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';
import { formatInUserTimezone } from '@/lib/timezone';
import {
  validateBooking,
  generateIdempotencyKey,
  getCsrfToken,
} from '@/lib/appointmentHelpers';
import type { PublicDoctor } from '@/types/public.types';
import type { Slot, AvailabilityResponse } from '@/types/api.types';

// ── Props de Inertia ───────────────────────────────────────────────────
const props = withDefaults(
  defineProps<{
    doctors?: PublicDoctor[];
  }>(),
  {
    doctors: () => [],
  },
);

// ── Wizard state ───────────────────────────────────────────────────────
type Step = 1 | 2 | 3;
const currentStep = ref<Step>(1);
const selectedDoctor = ref<PublicDoctor | null>(null);
const selectedDate = ref('');
const selectedSlot = ref<Slot | null>(null);
const isBooking = ref(false);
const bookingSuccess = ref(false);
const bookingError = ref('');

// ── Step 2: Availability ───────────────────────────────────────────────
const slots = ref<Slot[]>([]);
const slotEstado = ref<'idle' | 'cargando' | 'listo' | 'error'>('idle');
const slotError = ref<string | null>(null);
const doctorTimezone = ref('');

const availableSlots = computed(() => slots.value.filter((s) => s.available));

async function fetchAvailability(doctorId: string, date: string) {
  slotEstado.value = 'cargando';
  slotError.value = null;
  slots.value = [];

  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const res = await fetch(
      `/api/availability?doctor_id=${doctorId}&date=${date}&timezone=${encodeURIComponent(tz)}`,
      {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      },
    );

    if (res.ok) {
      const json: AvailabilityResponse = await res.json();
      slots.value = json.available_slots;
      doctorTimezone.value = json.timezone;
      slotEstado.value = 'listo';
    } else if (res.status === 422) {
      const json = await res.json();
      slotError.value = json.message ?? 'Fecha inválida.';
      slotEstado.value = 'error';
    } else {
      slotError.value = 'Error al cargar horarios.';
      slotEstado.value = 'error';
    }
  } catch {
    slotError.value = 'Error de red. Verifica tu conexión.';
    slotEstado.value = 'error';
  }
}

// ── Navigation ─────────────────────────────────────────────────────────
function selectDoctor(doctor: PublicDoctor) {
  selectedDoctor.value = doctor;
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  selectedDate.value = tomorrow.toISOString().split('T')[0];
  currentStep.value = 2;
  fetchAvailability(doctor.id, selectedDate.value);
}

function onDateChange() {
  if (selectedDoctor.value && selectedDate.value) {
    selectedSlot.value = null;
    fetchAvailability(selectedDoctor.value.id, selectedDate.value);
  }
}

function selectSlot(slot: Slot) {
  selectedSlot.value = slot;
  currentStep.value = 3;
  bookingError.value = '';
}

function goBack() {
  if (currentStep.value === 3) {
    selectedSlot.value = null;
    bookingError.value = '';
    currentStep.value = 2;
  } else if (currentStep.value === 2) {
    selectedDoctor.value = null;
    selectedSlot.value = null;
    currentStep.value = 1;
  }
}

// ── Step 3: Book Appointment ───────────────────────────────────────────
async function confirmBooking() {
  if (!selectedDoctor.value || !selectedSlot.value) return;

  const payload = {
    doctor_id: selectedDoctor.value.id,
    start_time: selectedSlot.value.start,
    end_time: selectedSlot.value.end,
  };

  const errs = validateBooking(payload);
  if (Object.keys(errs).length > 0) {
    bookingError.value = Object.values(errs).join(' ');
    return;
  }

  isBooking.value = true;
  bookingError.value = '';

  try {
    const res = await fetch('/api/appointments', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
        'Idempotency-Key': generateIdempotencyKey(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });

    if (res.status === 201) {
      bookingSuccess.value = true;
    } else if (res.status === 409) {
      bookingError.value = 'Este horario ya fue reservado. Por favor, elige otro.';
      currentStep.value = 2;
      selectedSlot.value = null;
      if (selectedDoctor.value && selectedDate.value) {
        fetchAvailability(selectedDoctor.value.id, selectedDate.value);
      }
    } else if (res.status === 422) {
      const json = await res.json();
      const messages = json.errors
        ? Object.values(json.errors as Record<string, string[]>).flat().join(' ')
        : json.message ?? 'Error de validación.';
      bookingError.value = messages;
    } else if (res.status === 403) {
      const json = await res.json();
      bookingError.value = json.message ?? 'No tienes permiso para esta acción.';
    } else {
      bookingError.value = 'Error inesperado al reservar. Intenta nuevamente.';
    }
  } catch {
    bookingError.value = 'Error de red. Verifica tu conexión.';
  } finally {
    isBooking.value = false;
  }
}

const STEPS = [
  { num: 1, label: 'Especialista' },
  { num: 2, label: 'Horario' },
  { num: 3, label: 'Confirmación' },
];

function todayISO(): string {
  return new Date().toISOString().split('T')[0];
}

function formatSlotTime(iso: string): string {
  const tz = doctorTimezone.value || Intl.DateTimeFormat().resolvedOptions().timeZone;
  return formatInUserTimezone(iso, tz, {
    timeZone: tz,
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
}
</script>

<template>
  <AppLayout>
    <div class="booking">
      <header class="booking__header">
        <h1 class="booking__title">Reservar Cita</h1>

        <!-- Stepper -->
        <div class="stepper">
          <div
            v-for="step in STEPS"
            :key="step.num"
            :class="[
              'stepper__step',
              { 'stepper__step--active': currentStep === step.num },
              { 'stepper__step--done': currentStep > step.num },
            ]"
          >
            <span class="stepper__num">{{ step.num }}</span>
            <span class="stepper__label">{{ step.label }}</span>
          </div>
        </div>
      </header>

      <!-- Alert banner -->
      <div
        v-if="bookingError"
        class="booking__alert booking__alert--error"
        role="alert"
      >
        <i class="pi pi-exclamation-triangle" aria-hidden="true" />
        <span>{{ bookingError }}</span>
      </div>

      <!-- ===== STEP 1: Select Doctor ===== -->
      <section v-if="currentStep === 1" class="booking__step">
        <h2 class="booking__step-title">Selecciona un especialista</h2>

        <EmptyState
          v-if="props.doctors.length === 0"
          message="No hay especialistas disponibles."
        />

        <div v-else class="doc-grid">
          <button
            v-for="doc in props.doctors"
            :key="doc.id"
            type="button"
            class="doc-pick"
            @click="selectDoctor(doc)"
          >
            <div
              class="doc-pick__avatar"
              :style="{ backgroundColor: getAvatarColor(doc.id) }"
            >
              {{ getInitials(doc.name + ' ' + doc.last_name) }}
            </div>
            <div class="doc-pick__info">
              <span class="doc-pick__name">{{ doc.name }} {{ doc.last_name }}</span>
              <span class="doc-pick__specialty">{{ doc.specialty }}</span>
              <span class="doc-pick__fee">{{ doc.consultation_fee.toFixed(2) }} USD</span>
            </div>
            <i class="pi pi-chevron-right doc-pick__arrow" aria-hidden="true" />
          </button>
        </div>
      </section>

      <!-- ===== STEP 2: Select Slot ===== -->
      <section v-if="currentStep === 2" class="booking__step">
        <button type="button" class="booking__back" @click="goBack">
          <i class="pi pi-arrow-left" aria-hidden="true" /> Volver
        </button>

        <h2 class="booking__step-title">
          Horarios disponibles —
          <span class="booking__doctor-name">{{ selectedDoctor?.name }} {{ selectedDoctor?.last_name }}</span>
        </h2>

        <div class="booking__date-picker">
          <label class="booking__date-label" for="booking-date">
            <i class="pi pi-calendar" aria-hidden="true" />
            Fecha
          </label>
          <input
            id="booking-date"
            v-model="selectedDate"
            type="date"
            class="booking__date-input"
            :min="todayISO()"
            @change="onDateChange"
          />
          <span v-if="doctorTimezone" class="booking__tz-label">
            Zona del médico: {{ doctorTimezone }}
          </span>
        </div>

        <SpinnerLoader v-if="slotEstado === 'cargando'" variant="list" :lines="6" />
        <ErrorFallback v-else-if="slotEstado === 'error'" :message="slotError ?? 'Error'" :on-retry="() => fetchAvailability(selectedDoctor!.id, selectedDate)" />
        <EmptyState v-else-if="slotEstado === 'listo' && availableSlots.length === 0" message="No hay horarios disponibles para esta fecha." />

        <div v-else-if="slotEstado === 'listo'" class="slot-grid">
          <button
            v-for="slot in slots"
            :key="slot.start"
            type="button"
            :class="['slot-btn', { 'slot-btn--unavailable': !slot.available }]"
            :disabled="!slot.available"
            @click="selectSlot(slot)"
          >
            <span class="slot-btn__time">{{ formatSlotTime(slot.start) }}</span>
            <span class="slot-btn__range">{{ formatSlotTime(slot.start) }} – {{ formatSlotTime(slot.end) }}</span>
            <span v-if="!slot.available" class="slot-btn__badge">Ocupado</span>
          </button>
        </div>
      </section>

      <!-- ===== STEP 3: Confirmation ===== -->
      <section v-if="currentStep === 3" class="booking__step">
        <button type="button" class="booking__back" @click="goBack">
          <i class="pi pi-arrow-left" aria-hidden="true" /> Volver
        </button>

        <div v-if="bookingSuccess" class="booking__success">
          <div class="booking__success-icon">
            <i class="pi pi-check-circle" aria-hidden="true" />
          </div>
          <h2 class="booking__success-title">¡Cita Reservada!</h2>
          <p class="booking__success-text">
            Tu cita con {{ selectedDoctor?.name }} {{ selectedDoctor?.last_name }}
            ha sido reservada exitosamente.
          </p>
        </div>

        <div v-else class="confirm-card">
          <h2 class="booking__step-title">Confirmar Reserva</h2>

          <div class="confirm-card__details">
            <div class="confirm-card__row">
              <span class="confirm-card__label">Especialista</span>
              <span class="confirm-card__value">
                {{ selectedDoctor?.name }} {{ selectedDoctor?.last_name }}
              </span>
            </div>
            <div class="confirm-card__row">
              <span class="confirm-card__label">Especialidad</span>
              <span class="confirm-card__value">{{ selectedDoctor?.specialty }}</span>
            </div>
            <div class="confirm-card__row">
              <span class="confirm-card__label">Fecha</span>
              <span class="confirm-card__value">{{ selectedDate }}</span>
            </div>
            <div class="confirm-card__row">
              <span class="confirm-card__label">Horario</span>
              <span class="confirm-card__value">
                {{ formatSlotTime(selectedSlot!.start) }} – {{ formatSlotTime(selectedSlot!.end) }}
              </span>
            </div>
            <div class="confirm-card__row">
              <span class="confirm-card__label">Tarifa</span>
              <span class="confirm-card__value confirm-card__value--fee">
                ${{ selectedDoctor?.consultation_fee.toFixed(2) }} USD
              </span>
            </div>
          </div>

          <button
            type="button"
            class="confirm-card__cta"
            :disabled="isBooking"
            @click="confirmBooking"
          >
            <i v-if="isBooking" class="pi pi-spin pi-spinner" aria-hidden="true" />
            <template v-else>
              <i class="pi pi-check" aria-hidden="true" />
              Confirmar Cita
            </template>
          </button>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.booking {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-5);
  max-width: 48rem;
  margin: 0 auto;
}

.booking__header {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.booking__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

/* Stepper */
.stepper {
  display: flex;
  gap: var(--spacing-1);
}

.stepper__step {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  flex: 1;
  padding: var(--spacing-2) var(--spacing-3);
  background-color: var(--color-surface-100);
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
}

.stepper__step--active {
  background-color: var(--color-primary-50);
  border: 1px solid var(--color-primary-500);
}

.stepper__step--done {
  background-color: var(--color-success-50);
}

.stepper__num {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background-color: var(--color-surface-200);
  font-size: var(--text-xs);
  font-weight: var(--font-bold);
  color: var(--color-text-muted);
  flex-shrink: 0;
}

.stepper__step--active .stepper__num {
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
}

.stepper__step--done .stepper__num {
  background-color: var(--color-success-700);
  color: var(--color-surface-0);
}

.stepper__label {
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  color: var(--color-text-muted);
}

.stepper__step--active .stepper__label {
  color: var(--color-primary-700);
  font-weight: var(--font-semibold);
}

.booking__step { display: flex; flex-direction: column; gap: var(--spacing-4); }

.booking__step-title {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
}

.booking__doctor-name { color: var(--color-primary-700); }

/* Alert */
.booking__alert {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-4);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  line-height: var(--leading-normal);
}

.booking__alert--error {
  background-color: var(--color-error-100);
  color: var(--color-error-700);
  border-left: 4px solid var(--color-error-600);
}

.booking__alert i { flex-shrink: 0; margin-top: 2px; }

/* Date picker */
.booking__date-picker {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  flex-wrap: wrap;
}

.booking__date-label {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
}

.booking__date-input {
  padding: var(--spacing-2);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
}

.booking__date-input:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.booking__tz-label {
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  font-style: italic;
}

.booking__back {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-1) var(--spacing-2);
  background: none;
  border: none;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  cursor: pointer;
  align-self: flex-start;
}

.booking__back:hover { color: var(--color-primary-700); }

/* Doctor pick cards */
.doc-grid {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.doc-pick {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-3) var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: var(--font-body);
  text-align: left;
  transition: all var(--transition-fast);
  width: 100%;
}

.doc-pick:hover {
  border-color: var(--color-primary-500);
  background-color: var(--color-primary-50);
}

.doc-pick:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.doc-pick__avatar {
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

.doc-pick__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.doc-pick__name {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.doc-pick__specialty {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.doc-pick__fee {
  font-size: var(--text-xs);
  color: var(--color-primary-700);
  font-weight: var(--font-medium);
}

.doc-pick__arrow {
  color: var(--color-text-subtle);
  font-size: var(--text-sm);
}

/* Slot grid */
.slot-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-2);
}

@media (max-width: 640px) {
  .slot-grid { grid-template-columns: repeat(2, 1fr); }
}

.slot-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--spacing-3);
  background-color: var(--color-surface-0);
  border: 2px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: var(--font-body);
  transition: all var(--transition-fast);
}

.slot-btn:hover:not(:disabled) {
  border-color: var(--color-primary-500);
  background-color: var(--color-primary-50);
}

.slot-btn:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.slot-btn--unavailable {
  opacity: 0.5;
  cursor: not-allowed;
  background-color: var(--color-surface-100);
}

.slot-btn__time {
  font-size: var(--text-base);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
}

.slot-btn__range {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.slot-btn__badge {
  font-size: var(--text-xs);
  color: var(--color-error-700);
  font-weight: var(--font-medium);
}

/* Confirm card */
.confirm-card {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-5);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.confirm-card__details {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.confirm-card__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: var(--spacing-2);
  border-bottom: 1px solid var(--color-surface-100);
}

.confirm-card__label {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.confirm-card__value {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
}

.confirm-card__value--fee {
  color: var(--color-primary-700);
  font-size: var(--text-base);
}

.confirm-card__cta {
  width: 100%;
  padding: var(--spacing-3);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
}

.confirm-card__cta:hover:not(:disabled) { background-color: var(--color-primary-600); }
.confirm-card__cta:disabled { opacity: 0.7; cursor: not-allowed; }
.confirm-card__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* Success */
.booking__success {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-6);
  text-align: center;
}

.booking__success-icon {
  font-size: 3rem;
  color: var(--color-success-700);
}

.booking__success-title {
  font-family: var(--font-heading);
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.booking__success-text {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}
</style>
