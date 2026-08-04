/**
 * ====================================================================
 * Helpers de Agenda — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Utilidades para parsear y validar datos del contrato de
 * RF-08 Configuración de Agenda y Bloqueos.
 *
 * El backend devuelve `franja` como un timerange de PostgreSQL:
 *   "[08:00:00, 12:00:00)"
 * Estas funciones extraen inicio/fin para display y formatean
 * la entrada del usuario para el API.
 */

// ── Tipos del contrato real ────────────────────────────────────────────

/** Franja recurrente — respuesta de GET/POST /api/schedules */
export interface Schedule {
  id: string;
  doctor_profile_id: string;
  day_of_week: number;  // 1-7 (1=Lunes, 7=Domingo) — validación del API
  franja: string;       // "[08:00:00, 12:00:00)" — timerange de PostgreSQL
  slot_duration: number; // minutos (10-120, default 30)
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

/** Bloqueo puntual — respuesta de GET/POST /api/schedule-blocks */
export interface ScheduleBlock {
  id: string;
  doctor_profile_id: string;
  blocked_date: string;  // "2026-08-15" — Y-m-d
  franja: string;        // "[09:00:00, 11:00:00)" — timerange de PostgreSQL
  reason: string;        // max 255
  created_at: string;
  updated_at: string;
}

/** Resultado de DELETE /api/schedules/{id} */
export interface DeleteScheduleResult {
  deleted: boolean;
  affected_appointments_count: number;
  affected_appointments: Array<{
    id: string;
    patient_id: string;
    franja: string;
    status: string;
  }>;
}

// ── Parsing de franja ──────────────────────────────────────────────────

export interface ParsedFranja {
  inicio: string;  // "08:00" — para display (sin segundos)
  fin: string;     // "12:00" — para display (sin segundos)
}

/**
 * Parsea un timerange de PostgreSQL a inicio/fin legibles.
 *
 * Formatos aceptados:
 *   "[08:00:00, 12:00:00)"   — timerange estándar
 *   "[08:00:00,12:00:00)"    — sin espacio
 *
 * @throws Error si el formato no es reconocido
 */
export function parseFranja(franja: string): ParsedFranja {
  // Pattern: [HH:MM:SS, HH:MM:SS) or [HH:MM:SS,HH:MM:SS)
  const match = franja.match(/^\[(\d{2}:\d{2}):\d{2},\s*(\d{2}:\d{2}):\d{2}\)$/);
  if (!match) {
    throw new Error(`Formato de franja no reconocido: "${franja}"`);
  }
  return {
    inicio: match[1],
    fin: match[2],
  };
}

// ── Formateo para el API ───────────────────────────────────────────────

/**
 * Convierte "HH:MM" del input time a "HH:MM:SS" para el API.
 * El API valida date_format:H:i:s.
 */
export function timeToApi(hhmm: string): string {
  if (/^\d{2}:\d{2}$/.test(hhmm)) {
    return `${hhmm}:00`;
  }
  return hhmm;
}

// ── Validación client-side (espeja el servidor) ────────────────────────

export interface ScheduleValidationErrors {
  day_of_week?: string;
  inicio?: string;
  fin?: string;
  slot_duration?: string;
}

export interface BlockValidationErrors {
  blocked_date?: string;
  inicio?: string;
  fin?: string;
  reason?: string;
}

/**
 * Valida los campos de crear franja recurrente.
 * Espeja las reglas del controlador:
 *   day_of_week: required, integer, between:1,7
 *   inicio: required, date_format:H:i:s
 *   fin: required, date_format:H:i:s, after:inicio
 *   slot_duration: nullable, integer, min:10, max:120
 */
export function validateSchedule(data: {
  day_of_week: number;
  inicio: string;
  fin: string;
  slot_duration: number | null;
}): ScheduleValidationErrors {
  const errs: ScheduleValidationErrors = {};

  if (!Number.isInteger(data.day_of_week) || data.day_of_week < 1 || data.day_of_week > 7) {
    errs.day_of_week = 'Selecciona un día válido (Lunes a Domingo).';
  }

  if (!data.inicio) {
    errs.inicio = 'La hora de inicio es obligatoria.';
  }

  if (!data.fin) {
    errs.fin = 'La hora de fin es obligatoria.';
  }

  if (data.inicio && data.fin && data.inicio >= data.fin) {
    errs.fin = 'La hora de fin debe ser posterior a la de inicio.';
  }

  if (data.slot_duration !== null) {
    if (!Number.isInteger(data.slot_duration) || data.slot_duration < 10 || data.slot_duration > 120) {
      errs.slot_duration = 'La duración debe ser entre 10 y 120 minutos.';
    }
  }

  return errs;
}

/**
 * Valida los campos de crear bloqueo puntual.
 * Espeja las reglas del controlador:
 *   blocked_date: required, date_format:Y-m-d
 *   inicio: required, date_format:H:i:s
 *   fin: required, date_format:H:i:s, after:inicio
 *   reason: required, string, max:255
 */
export function validateBlock(data: {
  blocked_date: string;
  inicio: string;
  fin: string;
  reason: string;
}): BlockValidationErrors {
  const errs: BlockValidationErrors = {};

  if (!data.blocked_date) {
    errs.blocked_date = 'La fecha es obligatoria.';
  } else if (!/^\d{4}-\d{2}-\d{2}$/.test(data.blocked_date)) {
    errs.blocked_date = 'Formato de fecha inválido (YYYY-MM-DD).';
  }

  if (!data.inicio) {
    errs.inicio = 'La hora de inicio es obligatoria.';
  }

  if (!data.fin) {
    errs.fin = 'La hora de fin es obligatoria.';
  }

  if (data.inicio && data.fin && data.inicio >= data.fin) {
    errs.fin = 'La hora de fin debe ser posterior a la de inicio.';
  }

  if (!data.reason.trim()) {
    errs.reason = 'El motivo es obligatorio.';
  } else if (data.reason.length > 255) {
    errs.reason = 'El motivo no puede exceder 255 caracteres.';
  }

  return errs;
}

// ── Labels de días ─────────────────────────────────────────────────────

/** Días de la semana según el contrato (1=Lunes, 7=Domingo) */
export const DAYS = [
  { id: 1, label: 'Lunes', short: 'Lun' },
  { id: 2, label: 'Martes', short: 'Mar' },
  { id: 3, label: 'Miércoles', short: 'Mié' },
  { id: 4, label: 'Jueves', short: 'Jue' },
  { id: 5, label: 'Viernes', short: 'Vie' },
  { id: 6, label: 'Sábado', short: 'Sáb' },
  { id: 7, label: 'Domingo', short: 'Dom' },
] as const;
