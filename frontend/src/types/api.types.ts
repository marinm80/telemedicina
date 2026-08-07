/**
 * ====================================================================
 * Tipos de API — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Tipos derivados de los contratos reales del backend.
 * Cada tipo lleva un comentario con el RF y endpoint de origen.
 */

// ── Availability (RF-08 Configuración de Agenda y Bloqueos) ────────────

/**
 * Slot de disponibilidad de un médico para una fecha.
 * Fuente: GET /api/availability → available_slots[]
 */
export interface Slot {
  start: string;       // ISO 8601 con offset, ej: "2026-09-01T10:00:00-03:00"
  end: string;         // ISO 8601 con offset
  available: boolean;
}

/**
 * Respuesta de disponibilidad de un médico.
 * Fuente: GET /api/availability?doctor_id=&date=&timezone=
 */
export interface AvailabilityResponse {
  doctor_id: string;
  date: string;           // formato YYYY-MM-DD
  timezone: string;       // zona IANA, ej: "America/Santiago"
  slots: Slot[];
}

// ── Appointments (RF-09 Reserva de Citas sin Solapamiento) ─────────────

/**
 * Cita médica (appointment).
 * Fuente: POST /api/appointments → 201 Created
 */
export interface Appointment {
  id: string;
  patient_id: string;
  doctor_id: string;
  start_time: string;
  end_time: string;
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed' | 'in_progress';
  created_at: string;
}

/**
 * Payload para crear una cita.
 * Fuente: POST /api/appointments
 * Header requerido: Idempotency-Key: uuid-v4
 * Nota: patient_id lo infiere el backend de la sesión autenticada.
 */
export interface CreateAppointmentPayload {
  doctor_id: string;
  start_time: string;    // ISO 8601 con offset
  end_time: string;      // ISO 8601 con offset
}

// ── Cancel (RF-25 Cancelación de Citas y Reembolsos) ───────────────────

/**
 * Respuesta de cancelación de cita.
 * Fuente: POST /api/appointments/{id}/cancel → 200 OK
 */
export interface CancelledAppointment {
  appointment_id: string;
  status: 'cancelled';
  refund_status: string;        // 'full_refund' o 'no_refund'
  refund_percentage: number;    // 100 o 0
}

// ── Reschedule (RF-11 Solicitud y Aprobación de Reprogramación) ────────

export interface RescheduleRequestPayload {
  requested_start: string;  // ISO 8601
  requested_end: string;    // ISO 8601
  reason: string;
}

export interface RescheduleResponse {
  id: string;
  status: 'pending';
}

// ── Pre-Consultation (RF-13 Cuestionario Pre-consulta) ─────────────────

export interface PreConsultationPayload {
  general_info: {
    full_name: string;
    birth_date: string;
    phone: string;
  };
  current_symptoms: {
    symptoms: string;
    onset_date: string;
    pain_level: number;    // 1-10
  };
  medical_history: {
    chronic_diseases: string[];
    allergies: string[];
  };
  family_history: {
    hereditary_diseases: string[];
  };
  lifestyle: {
    smoking: string;
    alcohol: string;
  };
  reproductive_data: Record<string, unknown>;
  warning_signs: string[];
  additional_docs: string[];
}

// ── Consultation Chat (RF-14 Consulta por Chat en Tiempo Real) ─────────

export interface ConsultationMessage {
  id: string;
  sender_id: string;
  content: string;
  created_at?: string;
}

// ── SOAP Notes (RF-15/16/17) ───────────────────────────────────────────

export interface SOAPPayload {
  symptoms: string;     // Subjetivo
  objective: string;    // Objetivo
  analysis: string;     // Análisis
  plan: string;         // Plan
}

export interface SignedNote {
  id: string;
  content_hash: string;   // SHA-256 hex
  status: 'draft' | 'signed';
  signed_at: string | null;
  symptoms: string;
  objective: string;
  analysis: string;
  plan: string;
}

export interface AmendmentPayload {
  reason: string;
  content: string;
}

export interface Amendment {
  id: string;
  reason: string;
  content: string;
  created_at: string;
}

// ── Note Verification (RF-18 Generación de PDF y QR Clínico) ───────────

export interface NoteVerificationValid {
  valid: true;
  content_hash: string;
  status: 'signed';
  signed_by: string;
  signed_at: string;
  acknowledged_at: string | null;
  amendments_count: number;
  verified_at: string;
}

export interface NoteVerificationInvalid {
  valid: false;
  error_code: string;
  message: string;
}

export type NoteVerification = NoteVerificationValid | NoteVerificationInvalid;

// ── Public Assistant (RF-23 Asistente Informativo Landing) ─────────────

export interface PublicAssistantRequest {
  query: string;
  specialty?: string;
}

export interface PublicAssistantDoctor {
  user_id: string;
  name: string;
  description: string;
  university: string;
  years_experience: number;
  consultation_fee: number;
}

export interface PublicAssistantResponse {
  reply: string;
  suggested_action: string;
  doctors: PublicAssistantDoctor[];
}

// ── Clinical Assistant (RF-24 Asistente Clínico Dashboard) ─────────────

export interface ClinicalAssistantRequest {
  query: string;
}

export interface ClinicalAssistantResponse {
  reply: string;
  status: 'active';
}
