/**
 * ====================================================================
 * Datos simulados de API — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Datos copiados textualmente de los JSON de ejemplo de
 * docs/API_CONTRACTS.md. Cada mock referencia su sección del contrato.
 * Delay aleatorio 200–800ms para ejercitar los estados de carga.
 */
import type {
  AvailabilityResponse,
  Appointment,
  CancelledAppointment,
} from '@/types/api.types';

/** Simula latencia de red aleatoria (200–800ms). */
function simulateLatency(): Promise<void> {
  const ms = 200 + Math.random() * 600;
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Mock de disponibilidad.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 1 → Respuesta 200 OK
 */
export const mockAvailability: AvailabilityResponse = {
  doctor_id: '550e8400-e29b-41d4-a716-446655440000',
  date: '2026-08-03',
  timezone: 'America/Argentina/Buenos_Aires',
  slots: [
    {
      start: '2026-08-03T14:00:00Z',
      end: '2026-08-03T14:30:00Z',
      local_start: '08:00 AM',
      local_end: '08:30 AM',
      available: true,
    },
    {
      start: '2026-08-03T14:30:00Z',
      end: '2026-08-03T15:00:00Z',
      local_start: '08:30 AM',
      local_end: '09:00 AM',
      available: false,
    },
  ],
};

/**
 * Mock de cita creada.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 2 → Respuesta 201 Created
 */
export const mockAppointment: Appointment = {
  id: '770e8400-e29b-41d4-a716-446655442222',
  patient_id: '660e8400-e29b-41d4-a716-446655441111',
  doctor_id: '550e8400-e29b-41d4-a716-446655440000',
  franja: '[2026-08-03 14:00:00+00, 2026-08-03 14:30:00+00)',
  status: 'pending',
  created_at: '2026-07-31T16:00:00Z',
};

/**
 * Mock de cancelación.
 * Fuente: API_CONTRACTS.md §3 → Endpoint 5 → Respuesta 200 OK
 */
export const mockCancelledAppointment: CancelledAppointment = {
  id: '770e8400-e29b-41d4-a716-446655442222',
  status: 'cancelled',
  cancelled_by: '660e8400-e29b-41d4-a716-446655441111',
  cancellation_reason: 'Paciente reporta imposibilidad de conexión por viaje.',
  refund_processed: false,
};

export { simulateLatency };
