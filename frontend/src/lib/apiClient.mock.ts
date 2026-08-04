/**
 * ====================================================================
 * Datos simulados de API — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Datos que reflejan las formas reales del backend.
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
 * Fuente: GET /api/availability
 */
export const mockAvailability: AvailabilityResponse = {
  doctor_id: '550e8400-e29b-41d4-a716-446655440000',
  date: '2026-08-03',
  timezone: 'America/Santiago',
  available_slots: [
    {
      start: '2026-08-03T10:00:00-03:00',
      end: '2026-08-03T10:30:00-03:00',
      available: true,
    },
    {
      start: '2026-08-03T10:30:00-03:00',
      end: '2026-08-03T11:00:00-03:00',
      available: false,
    },
  ],
};

/**
 * Mock de cita creada.
 * Fuente: POST /api/appointments → 201 Created
 */
export const mockAppointment: Appointment = {
  id: '770e8400-e29b-41d4-a716-446655442222',
  patient_id: '660e8400-e29b-41d4-a716-446655441111',
  doctor_id: '550e8400-e29b-41d4-a716-446655440000',
  start_time: '2026-08-03T10:00:00-03:00',
  end_time: '2026-08-03T10:30:00-03:00',
  status: 'pending',
  created_at: '2026-07-31T16:00:00Z',
};

/**
 * Mock de cancelación.
 * Fuente: POST /api/appointments/{id}/cancel → 200 OK
 */
export const mockCancelledAppointment: CancelledAppointment = {
  appointment_id: '770e8400-e29b-41d4-a716-446655442222',
  status: 'cancelled',
  refund_percentage: 100,
  refund_status: 'full_refund',
};

export { simulateLatency };
