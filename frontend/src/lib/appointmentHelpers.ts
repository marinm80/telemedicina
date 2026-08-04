/**
 * ====================================================================
 * Helpers de Citas — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Utilidades para los contratos de:
 *   RF-09 Reserva de Citas sin Solapamiento
 *   RF-25 Cancelación de Citas y Reembolsos
 *
 * Validación client-side espeja las reglas del FormRequest del backend.
 * Los tipos canónicos están en @/types/api.types.ts.
 */

// ── Validación para crear cita (espeja BookAppointmentRequest) ─────────

export interface BookingValidationErrors {
  doctor_id?: string;
  start_time?: string;
  end_time?: string;
}

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/**
 * Valida los campos de crear cita.
 * Espeja las reglas de BookAppointmentRequest:
 *   doctor_id: required, uuid
 *   start_time: required, date, after:now, no más de 1 año en el futuro
 *   end_time: required, date, after:start_time, exactamente 30 min después
 *
 * Nota: patient_id lo infiere el backend de la sesión autenticada.
 */
export function validateBooking(data: {
  doctor_id: string;
  start_time: string;
  end_time: string;
}): BookingValidationErrors {
  const errs: BookingValidationErrors = {};

  if (!data.doctor_id) {
    errs.doctor_id = 'El ID del médico es obligatorio.';
  } else if (!UUID_REGEX.test(data.doctor_id)) {
    errs.doctor_id = 'ID de médico inválido.';
  }

  if (!data.start_time) {
    errs.start_time = 'La fecha y hora de inicio es obligatoria.';
  } else {
    const inicio = new Date(data.start_time);
    if (isNaN(inicio.getTime())) {
      errs.start_time = 'Formato de fecha inválido.';
    } else {
      const now = new Date();
      if (inicio <= now) {
        errs.start_time = 'La cita debe ser en el futuro.';
      }
      const oneYearFromNow = new Date(now);
      oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
      if (inicio > oneYearFromNow) {
        errs.start_time = 'No se puede reservar a más de 1 año en el futuro.';
      }
    }
  }

  if (!data.end_time) {
    errs.end_time = 'La fecha y hora de fin es obligatoria.';
  } else if (data.start_time) {
    const inicio = new Date(data.start_time);
    const fin = new Date(data.end_time);
    if (isNaN(fin.getTime())) {
      errs.end_time = 'Formato de fecha inválido.';
    } else if (fin <= inicio) {
      errs.end_time = 'La hora de fin debe ser posterior a la de inicio.';
    } else {
      const diffMs = fin.getTime() - inicio.getTime();
      const diffMin = diffMs / (1000 * 60);
      if (diffMin !== 30) {
        errs.end_time = 'La duración de la cita debe ser exactamente 30 minutos.';
      }
    }
  }

  return errs;
}

// ── Validación para cancelar cita (espeja controlador) ─────────────────

export interface CancelValidationErrors {
  reason?: string;
}

/**
 * Valida los campos de cancelar cita.
 * Espeja las reglas inline del controlador:
 *   reason: nullable, string, max:500
 */
export function validateCancel(data: { reason: string }): CancelValidationErrors {
  const errs: CancelValidationErrors = {};

  if (data.reason.length > 500) {
    errs.reason = 'El motivo no puede exceder 500 caracteres.';
  }

  return errs;
}

// ── Helpers de idempotencia ────────────────────────────────────────────

/**
 * Genera un UUID v4 para el header Idempotency-Key.
 * Usa crypto.randomUUID() del navegador.
 */
export function generateIdempotencyKey(): string {
  return crypto.randomUUID();
}

// ── CSRF ───────────────────────────────────────────────────────────────

export function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}

// ── Helpers de display de reembolso ────────────────────────────────────

/**
 * Texto legible para el estado de reembolso.
 */
export function refundLabel(refundStatus: string, refundPercentage: number): string {
  if (refundStatus === 'full_refund') {
    return `Reembolso completo (${refundPercentage}%)`;
  }
  if (refundStatus === 'no_refund') {
    return 'Sin reembolso (cancelación con menos de 24h de anticipación)';
  }
  return refundStatus;
}

// ── Validación para reprogramación (espeja RF-11) ──────────────────────

export interface RescheduleValidationErrors {
  requested_start?: string;
  requested_end?: string;
  reason?: string;
}

export function validateReschedule(data: {
  requested_start: string;
  requested_end: string;
  reason: string;
}): RescheduleValidationErrors {
  const errs: RescheduleValidationErrors = {};

  if (!data.requested_start) {
    errs.requested_start = 'La nueva fecha de inicio es obligatoria.';
  } else {
    const start = new Date(data.requested_start);
    if (isNaN(start.getTime())) {
      errs.requested_start = 'Formato de fecha inválido.';
    } else if (start <= new Date()) {
      errs.requested_start = 'La nueva cita debe ser en el futuro.';
    }
  }

  if (!data.requested_end) {
    errs.requested_end = 'La nueva fecha de fin es obligatoria.';
  } else if (data.requested_start) {
    const start = new Date(data.requested_start);
    const end = new Date(data.requested_end);
    if (isNaN(end.getTime())) {
      errs.requested_end = 'Formato de fecha inválido.';
    } else {
      const diffMin = (end.getTime() - start.getTime()) / 60_000;
      if (diffMin !== 30) {
        errs.requested_end = 'La duración debe ser exactamente 30 minutos.';
      }
    }
  }

  if (!data.reason) {
    errs.reason = 'El motivo de reprogramación es obligatorio.';
  } else if (data.reason.length > 500) {
    errs.reason = 'El motivo no puede exceder 500 caracteres.';
  }

  return errs;
}
