/**
 * ====================================================================
 * Tipos de API — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Tipos derivados de docs/API_CONTRACTS.md. Cada tipo lleva un
 * comentario con la sección del contrato de donde salió, para que
 * la divergencia sea diffeable.
 *
 * NO incluye Schedule ni RescheduleRequest:
 * - Schedule: contrato RF-08 en definición (decisión 9).
 * - RescheduleRequest: el médico ya no reprograma, cancela (decisión 9).
 */

/**
 * Slot de disponibilidad de un médico para una fecha.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 1 → Respuesta 200 OK → slots[]
 */
export interface Slot {
  start: string;       // ISO 8601 UTC, ej: "2026-08-03T14:00:00Z"
  end: string;         // ISO 8601 UTC
  local_start: string; // hora local formateada, ej: "08:00 AM"
  local_end: string;   // hora local formateada
  available: boolean;
}

/**
 * Respuesta de disponibilidad de un médico.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 1 → Respuesta 200 OK
 */
export interface AvailabilityResponse {
  doctor_id: string;
  date: string;        // formato YYYY-MM-DD
  timezone: string;    // zona IANA del médico, ej: "America/Tegucigalpa"
  slots: Slot[];
}

/**
 * Cita médica (appointment).
 * Fuente: API_CONTRACTS.md §3 → Endpoint 2 → Respuesta 201 Created
 */
export interface Appointment {
  id: string;
  patient_id: string;
  doctor_id: string;
  franja: string;      // rango PostgreSQL, ej: "[2026-08-03 14:00:00+00, ...)"
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed';
  created_at: string;  // ISO 8601 UTC
}

/**
 * Payload para crear una cita.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 2 → Cuerpo de Petición
 */
export interface CreateAppointmentPayload {
  patient_id: string;
  doctor_id: string;
  franja_inicio: string; // ISO 8601 UTC
  franja_fin: string;    // ISO 8601 UTC
}

/**
 * Respuesta de cancelación de cita.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 5 → Respuesta 200 OK
 */
export interface CancelledAppointment {
  id: string;
  status: 'cancelled';
  cancelled_by: string;
  cancellation_reason: string;
  refund_processed: boolean;
}
