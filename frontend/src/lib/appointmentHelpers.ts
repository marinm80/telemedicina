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
  patient_id?: string;
  doctor_id?: string;
  franja_inicio?: string;
  franja_fin?: string;
}

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/**
 * Valida los campos de crear cita.
 * Espeja las reglas de BookAppointmentRequest:
 *   patient_id: required, uuid
 *   doctor_id: required, uuid
 *   franja_inicio: required, date, after:now, no más de 1 año en el futuro
 *   franja_fin: required, date, after:franja_inicio, exactamente 30 min después
 */
export function validateBooking(data: {
  patient_id: string;
  doctor_id: string;
  franja_inicio: string;
  franja_fin: string;
}): BookingValidationErrors {
  const errs: BookingValidationErrors = {};

  if (!data.patient_id) {
    errs.patient_id = 'El ID del paciente es obligatorio.';
  } else if (!UUID_REGEX.test(data.patient_id)) {
    errs.patient_id = 'ID de paciente inválido.';
  }

  if (!data.doctor_id) {
    errs.doctor_id = 'El ID del médico es obligatorio.';
  } else if (!UUID_REGEX.test(data.doctor_id)) {
    errs.doctor_id = 'ID de médico inválido.';
  }

  if (!data.franja_inicio) {
    errs.franja_inicio = 'La fecha y hora de inicio es obligatoria.';
  } else {
    const inicio = new Date(data.franja_inicio);
    if (isNaN(inicio.getTime())) {
      errs.franja_inicio = 'Formato de fecha inválido.';
    } else {
      const now = new Date();
      if (inicio <= now) {
        errs.franja_inicio = 'La cita debe ser en el futuro.';
      }
      const oneYearFromNow = new Date(now);
      oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
      if (inicio > oneYearFromNow) {
        errs.franja_inicio = 'No se puede reservar a más de 1 año en el futuro.';
      }
    }
  }

  if (!data.franja_fin) {
    errs.franja_fin = 'La fecha y hora de fin es obligatoria.';
  } else if (data.franja_inicio) {
    const inicio = new Date(data.franja_inicio);
    const fin = new Date(data.franja_fin);
    if (isNaN(fin.getTime())) {
      errs.franja_fin = 'Formato de fecha inválido.';
    } else if (fin <= inicio) {
      errs.franja_fin = 'La hora de fin debe ser posterior a la de inicio.';
    } else {
      const diffMs = fin.getTime() - inicio.getTime();
      const diffMin = diffMs / (1000 * 60);
      if (diffMin !== 30) {
        errs.franja_fin = 'La duración de la cita debe ser exactamente 30 minutos.';
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
 * Genera un UUID v4 para el header X-Idempotency-Key.
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
