<!--
  ====================================================================
  MyAppointments â€” Mis Citas (Paciente)
  AUTHOR: Rafael MarÃ­n Â· PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<script setup lang="ts">
import { ref, computed, inject } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { i18nKey } from '@/i18n/plugin';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_CONFIG, getInitials, getAvatarColor } from '@/lib/mockData';
import { formatInUserTimezone } from '@/lib/timezone';
import { validateCancel, validateReschedule, getCsrfToken, refundLabel } from '@/lib/appointmentHelpers';
import type { CancelledAppointment, RescheduleResponse } from '@/types/api.types';

const props = defineProps<{
  appointments: Array<{
    id: string;
    patient_id: string;
    patient_name: string;
    doctor_id: string;
    doctor_name: string;
    doctor_specialty?: string;
    franja_start: string;
    franja_end: string;
    status: string;
    cancelled_by: string | null;
    cancellation_reason: string | null;
    consultation_id: string | null;
    can_cancel: boolean;
    can_reschedule: boolean;
  }>;
  filters: Record<string, string>;
}>();

const t = inject(i18nKey)!;
const USER_TZ = 'America/Argentina/Buenos_Aires';

const page = usePage();
const userRole = computed(() => (page.props as any)?.auth?.user?.role || 'patient');
const isDoctor = computed(() => userRole.value === 'doctor' || userRole.value === 'admin');
const startingConsultation = ref<string | null>(null);

async function startConsultation(appt: typeof props.appointments[0]) {
  startingConsultation.value = appt.id;
  try {
    // If consultation_id exists, navigate directly
    if (appt.consultation_id) {
      router.visit(`/doctor/consulta/${appt.consultation_id}`);
      return;
    }
    // Create consultation first
    const res = await fetch(`/api/consultations/${appt.id}/start`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    });
    if (res.ok) {
      const data = await res.json();
      router.visit(`/doctor/consulta/${data.consultation_id}`);
    } else {
      const errData = await res.json().catch(() => ({}));
      alert(errData.message || 'Error al iniciar consulta');
    }
  } catch (e: any) {
    alert('Error: ' + e.message);
  } finally {
    startingConsultation.value = null;
  }
}

type FilterTab = 'todas' | 'proximas' | 'pasadas';
const activeTab = ref<FilterTab>('todas');

const filteredAppointments = computed(() => {
  if (activeTab.value === 'proximas') {
    return props.appointments.filter((a) => a.status === 'pending' || a.status === 'confirmed');
  }
  if (activeTab.value === 'pasadas') {
    return props.appointments.filter((a) => a.status === 'completed' || a.status === 'cancelled');
  }
  return props.appointments;
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
  { id: 'proximas', label: 'PrÃ³ximas' },
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

// â”€â”€ CancelaciÃ³n RF-25 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const cancelTarget = ref<any | null>(null);
const cancelReason = ref('');
const cancelSubmitting = ref(false);
const cancelError = ref('');
const cancelResult = ref<CancelledAppointment | null>(null);

function openCancelModal(appt: any) {
  cancelTarget.value = appt;
  cancelReason.value = '';
  cancelError.value = '';
  cancelResult.value = null;
}

function closeCancelModal() {
  cancelTarget.value = null;
  cancelReason.value = '';
  cancelError.value = '';
  cancelResult.value = null;
}

async function submitCancel() {
  if (!cancelTarget.value) return;

  const errs = validateCancel({ reason: cancelReason.value });
  if (Object.keys(errs).length > 0) {
    cancelError.value = errs.reason ?? '';
    return;
  }

  cancelSubmitting.value = true;
  cancelError.value = '';

  try {
    const res = await fetch(`/api/appointments/${cancelTarget.value.id}/cancel`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        reason: cancelReason.value || null,
      }),
    });

    if (res.ok) {
      const json = await res.json();
      cancelResult.value = json;
      // Actualizar estado local recargando
      router.reload({ only: ['appointments'] });
    } else if (res.status === 409) {
      cancelError.value = 'Esta cita ya fue cancelada o completada.';
    } else if (res.status === 403) {
      const json = await res.json();
      cancelError.value = json.message ?? 'No tienes permiso para cancelar esta cita.';
    } else {
      cancelError.value = 'Error inesperado. Intenta nuevamente.';
    }
  } catch {
    cancelError.value = 'Error de red. Verifica tu conexiÃ³n.';
  } finally {
    cancelSubmitting.value = false;
  }
}

// â”€â”€ ReprogramaciÃ³n RF-11 Solicitud y AprobaciÃ³n de ReprogramaciÃ³n â”€â”€â”€â”€â”€â”€
const rescheduleTarget = ref<any | null>(null);
const rescheduleStart = ref('');
const rescheduleEnd = ref('');
const rescheduleReason = ref('');
const rescheduleSubmitting = ref(false);
const rescheduleError = ref('');
const rescheduleResult = ref<RescheduleResponse | null>(null);

function openRescheduleModal(appt: any) {
  rescheduleTarget.value = appt;
  rescheduleStart.value = '';
  rescheduleEnd.value = '';
  rescheduleReason.value = '';
  rescheduleError.value = '';
  rescheduleResult.value = null;
}

function closeRescheduleModal() {
  rescheduleTarget.value = null;
}

function onRescheduleStartChange() {
  if (rescheduleStart.value) {
    const start = new Date(rescheduleStart.value);
    const end = new Date(start.getTime() + 30 * 60_000);
    rescheduleEnd.value = end.toISOString().slice(0, 16);
  }
}

async function submitReschedule() {
  if (!rescheduleTarget.value) return;

  const payload = {
    requested_start: rescheduleStart.value ? new Date(rescheduleStart.value).toISOString() : '',
    requested_end: rescheduleEnd.value ? new Date(rescheduleEnd.value).toISOString() : '',
    reason: rescheduleReason.value,
  };

  const errs = validateReschedule(payload);
  if (Object.keys(errs).length > 0) {
    rescheduleError.value = Object.values(errs).join(' ');
    return;
  }

  rescheduleSubmitting.value = true;
  rescheduleError.value = '';

  try {
    const res = await fetch(
      `/api/appointments/${rescheduleTarget.value.id}/reschedule-request`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      },
    );

    if (res.ok) {
      rescheduleResult.value = await res.json();
    } else if (res.status === 409) {
      rescheduleError.value = 'No se puede reprogramar esta cita en su estado actual.';
    } else if (res.status === 422) {
      const json = await res.json();
      rescheduleError.value = json.message ?? 'Error de validaciÃ³n.';
    } else {
      rescheduleError.value = 'Error inesperado. Intenta nuevamente.';
    }
  } catch {
    rescheduleError.value = 'Error de red. Verifica tu conexiÃ³n.';
  } finally {
    rescheduleSubmitting.value = false;
  }
}

// â”€â”€ Acuse de recibo RF-19 Acuse de Recibo de Paciente â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const acknowledgeLoading = ref<string | null>(null);
const acknowledgeError = ref('');

async function acknowledgeNote(appointmentId: string, consultationId: string) {
  acknowledgeLoading.value = appointmentId;
  acknowledgeError.value = '';

  try {
    const res = await fetch(`/api/consultations/${consultationId}/acknowledge`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    });

    if (res.ok) {
      // Marcar la cita como acusada localmente
      router.reload({ only: ['appointments'] });
    } else if (res.status === 422) {
      acknowledgeError.value = 'La nota aÃºn estÃ¡ en borrador.';
    } else {
      acknowledgeError.value = 'Error al acusar recibo.';
    }
  } catch {
    acknowledgeError.value = 'Error de red.';
  } finally {
    acknowledgeLoading.value = null;
  }
}

// â”€â”€ Descarga de PDF RF-18 GeneraciÃ³n de PDF y QR ClÃ­nico â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const pdfLoading = ref<string | null>(null);
const pdfError = ref('');

async function downloadPdf(consultationId: string) {
  pdfLoading.value = consultationId;
  pdfError.value = '';

  try {
    const res = await fetch(`/api/consultations/${consultationId}/pdf`, {
      headers: { 'Accept': 'application/pdf' },
      credentials: 'same-origin',
    });

    if (res.ok) {
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `nota-clinica-${consultationId}.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } else if (res.status === 425) {
      pdfError.value = 'El PDF estÃ¡ generÃ¡ndose. Intenta en unos segundos.';
    } else {
      pdfError.value = 'Error al descargar el PDF.';
    }
  } catch {
    pdfError.value = 'Error de red.';
  } finally {
    pdfLoading.value = null;
  }
}
</script>

<template>
  <AppLayout>
  <div class="appointments">
    <header class="appointments__header">
      <h1 class="appointments__title">Mis Citas</h1>
      <p class="appointments__subtitle">
        {{ props.appointments.length }} citas en total
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

    <!-- Estado: vacÃ­o -->
    <div v-if="props.appointments.length === 0" style="padding: 2rem; text-align: center; color: var(--color-text-muted);">
      <p>{{ t('history.empty') }}</p>
    </div>

    <!-- Sin resultados en el filtro activo -->
    <div v-else-if="filteredAppointments.length === 0" style="padding: 2rem; text-align: center; color: var(--color-text-muted);">
      <p>No hay citas en esta categorÃ­a.</p>
    </div>

    <!-- Estado: listo -->
    <div v-else class="appointments__list">
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
            {{ formatDate(appt.franja_start) }}
            Â· {{ formatTime(appt.franja_start) }} â€“ {{ formatTime(appt.franja_end) }}
          </span>
        </div>

        <div class="appt-card__actions">
          <span :class="['appt-card__badge', STATUS_CONFIG[appt.status]?.cssClass]">
            <i :class="['pi', STATUS_CONFIG[appt.status]?.icon]" aria-hidden="true" />
            {{ STATUS_CONFIG[appt.status]?.label }}
          </span>
          <!-- Doctor: Atender button -->
          <button
            v-if="isDoctor && (appt.status === 'pending' || appt.status === 'confirmed')"
            type="button"
            class="appt-card__action appt-card__action--attend"
            :disabled="startingConsultation === appt.id"
            @click="startConsultation(appt)"
          >
            <i class="pi pi-video" aria-hidden="true" />
            {{ startingConsultation === appt.id ? 'Abriendo...' : 'Atender' }}
          </button>
          <button
            v-if="appt.status === 'pending' || appt.status === 'confirmed'"
            type="button"
            class="appt-card__action appt-card__action--reschedule"
            @click="openRescheduleModal(appt)"
          >
            <i class="pi pi-calendar" aria-hidden="true" />
            Reprogramar
          </button>
          <button
            v-if="appt.status === 'pending' || appt.status === 'confirmed'"
            type="button"
            class="appt-card__cancel"
            @click="openCancelModal(appt)"
          >
            Cancelar
          </button>
          <button
            v-if="appt.status === 'completed'"
            type="button"
            class="appt-card__action appt-card__action--pdf"
            :disabled="pdfLoading === appt.id"
            @click="downloadPdf(appt.id)"
          >
            <i class="pi pi-file-pdf" aria-hidden="true" />
            Descargar PDF
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de cancelaciÃ³n -->
    <div v-if="cancelTarget" class="cancel-overlay" @click.self="closeCancelModal">
      <div class="cancel-modal" role="dialog" aria-labelledby="cancel-modal-title">
        <!-- Resultado exitoso -->
        <div v-if="cancelResult" class="cancel-modal__success">
          <i class="pi pi-check-circle cancel-modal__success-icon" aria-hidden="true" />
          <h3 id="cancel-modal-title" class="cancel-modal__title">Cita Cancelada</h3>
          <p class="cancel-modal__refund">
            {{ refundLabel(cancelResult.refund_status, cancelResult.refund_percentage) }}
          </p>
          <button type="button" class="cancel-modal__close-btn" @click="closeCancelModal">
            Cerrar
          </button>
        </div>

        <!-- Formulario -->
        <template v-else>
          <h3 id="cancel-modal-title" class="cancel-modal__title">Cancelar Cita</h3>
          <p class="cancel-modal__desc">
            Â¿EstÃ¡s seguro de cancelar tu cita con
            <strong>{{ cancelTarget.doctor_name }}</strong>?
          </p>
          <p class="cancel-modal__refund-info">
            <i class="pi pi-info-circle" aria-hidden="true" />
            Si cancelas con mÃ¡s de 24h de anticipaciÃ³n, recibirÃ¡s un reembolso completo.
          </p>

          <div v-if="cancelError" class="cancel-modal__error" role="alert">
            <i class="pi pi-exclamation-triangle" aria-hidden="true" />
            {{ cancelError }}
          </div>

          <div class="cancel-modal__field">
            <label class="cancel-modal__label" for="cancel-reason">Motivo (opcional)</label>
            <textarea
              id="cancel-reason"
              v-model="cancelReason"
              class="cancel-modal__textarea"
              placeholder="Ej: Cambio de planes, problema personal..."
              maxlength="500"
              rows="3"
            />
            <span class="cancel-modal__count">{{ cancelReason.length }}/500</span>
          </div>

          <div class="cancel-modal__actions">
            <button type="button" class="cancel-modal__btn-secondary" @click="closeCancelModal">
              Volver
            </button>
            <button
              type="button"
              class="cancel-modal__btn-danger"
              :disabled="cancelSubmitting"
              @click="submitCancel"
            >
              <i v-if="cancelSubmitting" class="pi pi-spin pi-spinner" aria-hidden="true" />
              <template v-else>
                <i class="pi pi-times" aria-hidden="true" />
                Confirmar CancelaciÃ³n
              </template>
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- Modal de reprogramaciÃ³n RF-11 -->
    <div v-if="rescheduleTarget" class="cancel-overlay" @click.self="closeRescheduleModal">
      <div class="cancel-modal" role="dialog" aria-labelledby="reschedule-modal-title">
        <!-- Resultado exitoso -->
        <div v-if="rescheduleResult" class="cancel-modal__success">
          <i class="pi pi-check-circle cancel-modal__success-icon" aria-hidden="true" />
          <h3>Â¡Solicitud Enviada!</h3>
          <p>Tu mÃ©dico revisarÃ¡ la solicitud de reprogramaciÃ³n.</p>
          <p class="cancel-modal__refund-text">ID de solicitud: {{ rescheduleResult.id }}</p>
          <button type="button" class="cancel-modal__close-btn" @click="closeRescheduleModal">Cerrar</button>
        </div>
        <!-- Formulario -->
        <template v-else>
          <h2 id="reschedule-modal-title" class="cancel-modal__title">
            <i class="pi pi-calendar" aria-hidden="true" />
            Reprogramar Cita
          </h2>
          <p class="cancel-modal__desc">Selecciona un nuevo horario para tu cita.</p>

          <div class="cancel-modal__field">
            <label for="reschedule-start" class="cancel-modal__label">Nueva Fecha y Hora</label>
            <input
              id="reschedule-start"
              v-model="rescheduleStart"
              type="datetime-local"
              class="cancel-modal__input"
              @change="onRescheduleStartChange"
            />
          </div>

          <div class="cancel-modal__field">
            <label for="reschedule-reason" class="cancel-modal__label">Motivo de reprogramaciÃ³n</label>
            <textarea
              id="reschedule-reason"
              v-model="rescheduleReason"
              class="cancel-modal__textarea"
              rows="3"
              placeholder="Ej: Cambio de turno laboral"
            />
          </div>

          <p v-if="rescheduleError" class="cancel-modal__error" role="alert">
            <i class="pi pi-exclamation-circle" aria-hidden="true" />
            {{ rescheduleError }}
          </p>

          <div class="cancel-modal__actions">
            <button type="button" class="cancel-modal__btn cancel-modal__btn--outline" @click="closeRescheduleModal">
              Volver
            </button>
            <button
              type="button"
              class="cancel-modal__btn cancel-modal__btn--primary"
              :disabled="rescheduleSubmitting || !rescheduleStart || !rescheduleReason"
              @click="submitReschedule"
            >
              <i v-if="rescheduleSubmitting" class="pi pi-spin pi-spinner" aria-hidden="true" />
              <template v-else>
                <i class="pi pi-calendar" aria-hidden="true" />
                Enviar Solicitud
              </template>
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
  </AppLayout>
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

.appt-card__action {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: 2px var(--spacing-2);
  background: none;
  border: 1px solid var(--color-surface-300);
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.appt-card__action:hover:not(:disabled) {
  background-color: var(--color-surface-100);
}

.appt-card__action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.appt-card__action--attend {
  border-color: #0E5D52;
  color: #0E5D52;
  font-weight: 700;
  background: #F0FDF4;
}
.appt-card__action--attend:hover:not(:disabled) {
  background: #0E5D52;
  color: #FFF;
}

.appt-card__action--reschedule {
  border-color: var(--color-primary-400);
  color: var(--color-primary-700);
}

.appt-card__action--reschedule:hover {
  background-color: var(--color-primary-50);
}

.appt-card__action--pdf {
  border-color: var(--color-success-400);
  color: var(--color-success-700);
}

.appt-card__action--pdf:hover:not(:disabled) {
  background-color: var(--color-success-50);
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

/* Cancel modal */
.cancel-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: var(--spacing-4);
}

.cancel-modal {
  background-color: var(--color-surface-0);
  border-radius: var(--radius-lg);
  padding: var(--spacing-5);
  max-width: 28rem;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  box-shadow: var(--shadow-lg);
}

.cancel-modal__title {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
}

.cancel-modal__desc {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}

.cancel-modal__refund-info {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-2);
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  background-color: var(--color-surface-100);
  padding: var(--spacing-2) var(--spacing-3);
  border-radius: var(--radius-md);
  margin: 0;
}

.cancel-modal__refund-info i { flex-shrink: 0; margin-top: 1px; }

.cancel-modal__error {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-sm);
  color: var(--color-error-700);
  background-color: var(--color-error-100);
  padding: var(--spacing-2) var(--spacing-3);
  border-radius: var(--radius-md);
}

.cancel-modal__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.cancel-modal__label {
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-text-strong);
}

.cancel-modal__textarea {
  padding: var(--spacing-2);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-strong);
  background-color: var(--color-surface-0);
  resize: vertical;
}

.cancel-modal__textarea:focus {
  outline: none;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-focus-ring);
}

.cancel-modal__count {
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  text-align: right;
}

.cancel-modal__actions {
  display: flex;
  gap: var(--spacing-2);
  justify-content: flex-end;
}

.cancel-modal__btn-secondary {
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-text-muted);
  background: none;
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.cancel-modal__btn-secondary:hover { background-color: var(--color-surface-100); }

.cancel-modal__btn-danger {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  color: var(--color-surface-0);
  background-color: var(--color-error-600);
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.cancel-modal__btn-danger:hover:not(:disabled) { background-color: var(--color-error-700); }
.cancel-modal__btn-danger:disabled { opacity: 0.7; cursor: not-allowed; }

.cancel-modal__btn-danger:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.cancel-modal__success {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-3);
  text-align: center;
  padding: var(--spacing-4);
}

.cancel-modal__success-icon {
  font-size: 2.5rem;
  color: var(--color-success-700);
}

.cancel-modal__refund {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}

.cancel-modal__close-btn {
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-primary-700);
  background: none;
  border: 1px solid var(--color-primary-500);
  border-radius: var(--radius-md);
  cursor: pointer;
}

.cancel-modal__close-btn:hover { background-color: var(--color-primary-50); }
</style>
