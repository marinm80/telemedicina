/**
 * ====================================================================
 * Cliente de API tipado — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * REGLA ESTRUCTURAL: ningún componente hace fetch. Toda llamada de red
 * pasa por este módulo. Los componentes llaman funciones tipadas de acá,
 * nunca fetch() directamente.
 *
 * Hoy: devuelve datos simulados de apiClient.mock.ts.
 * Mañana: hace fetch() real a la API Laravel.
 * El switch se controla con VITE_USE_MOCKS. La interfaz pública no cambia.
 */
import type {
  AvailabilityResponse,
  Appointment,
  CreateAppointmentPayload,
  CancelledAppointment,
} from '@/types/api.types';
import {
  mockAvailability,
  mockAppointment,
  mockCancelledAppointment,
  simulateLatency,
} from './apiClient.mock';

const USE_MOCKS = import.meta.env.VITE_USE_MOCKS !== 'false';

/**
 * Consulta la disponibilidad de un médico para una fecha.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 1
 */
export async function getAvailability(
  doctorId: string,
  date: string,
  signal?: AbortSignal,
): Promise<AvailabilityResponse> {
  if (USE_MOCKS) {
    await simulateLatency();
    signal?.throwIfAborted();
    return { ...mockAvailability, doctor_id: doctorId, date };
  }

  const res = await fetch(`/api/doctors/${doctorId}/availability?date=${date}`, {
    signal,
    credentials: 'include',
  });
  if (!res.ok) throw await buildApiError(res);
  return (await res.json()) as AvailabilityResponse;
}

/**
 * Reserva un slot de cita.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 2
 */
export async function createAppointment(
  payload: CreateAppointmentPayload,
  idempotencyKey: string,
  signal?: AbortSignal,
): Promise<Appointment> {
  if (USE_MOCKS) {
    await simulateLatency();
    signal?.throwIfAborted();
    return { ...mockAppointment, ...payload };
  }

  const res = await fetch('/api/appointments', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Idempotency-Key': idempotencyKey,
    },
    body: JSON.stringify(payload),
    signal,
    credentials: 'include',
  });
  if (!res.ok) throw await buildApiError(res);
  return (await res.json()) as Appointment;
}

/**
 * Cancela una cita existente.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 5
 */
export async function cancelAppointment(
  appointmentId: string,
  reason: string,
  signal?: AbortSignal,
): Promise<CancelledAppointment> {
  if (USE_MOCKS) {
    await simulateLatency();
    signal?.throwIfAborted();
    return {
      ...mockCancelledAppointment,
      appointment_id: appointmentId,
    };
  }

  const res = await fetch(`/api/appointments/${appointmentId}/cancel`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ reason }),
    signal,
    credentials: 'include',
  });
  if (!res.ok) throw await buildApiError(res);
  return (await res.json()) as CancelledAppointment;
}

// ---------------------------------------------------------------------------
// Manejo de errores
// ---------------------------------------------------------------------------

/**
 * Construye un Error con el mensaje del servidor.
 * Respeta la forma del error definida en API_CONTRACTS.md §Formato de Error.
 */
async function buildApiError(res: Response): Promise<Error> {
  const body = (await res.json().catch(() => null)) as
    | { message?: string; error_code?: string }
    | null;
  return new Error(body?.message ?? `Error ${res.status}`);
}
