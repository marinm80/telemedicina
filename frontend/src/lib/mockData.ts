/**
 * ====================================================================
 * Datos mock para preview — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Datos realistas para las pantallas de preview. Independiente de
 * apiClient.mock.ts (que sigue la forma exacta del contrato).
 * Estos datos son para presentación visual.
 */

export interface DoctorProfile {
  id: string;
  name: string;
  specialty: string;
  rating: number;
  reviews_count: number;
  available_slots_today: number;
  next_available: string;
  timezone: string;
}

export interface AppointmentDisplay {
  id: string;
  doctor_name: string;
  doctor_specialty: string;
  franja_inicio: string;
  franja_fin: string;
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed';
  created_at: string;
}

export function getInitials(name: string): string {
  return name
    .split(' ')
    .filter((w) => w[0] && w[0] === w[0].toUpperCase())
    .slice(0, 2)
    .map((w) => w[0])
    .join('');
}

/**
 * Color de fondo del avatar derivado del nombre (determinístico).
 * Usa tonos del sistema de diseño para consistencia visual.
 */
const AVATAR_COLORS = [
  '#1D4ED8', '#15803D', '#B91C1C', '#854D0E',
  '#7C3AED', '#0E7490', '#BE185D', '#1E3A8A',
];

export function getAvatarColor(name: string): string {
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length]!;
}

export const SPECIALTIES = [
  'Cardiología', 'Dermatología', 'Pediatría',
  'Neurología', 'Traumatología', 'Psiquiatría',
];

export const mockDoctors: DoctorProfile[] = [
  {
    id: '550e8400-e29b-41d4-a716-446655440001',
    name: 'Dra. María García',
    specialty: 'Cardiología',
    rating: 4.8,
    reviews_count: 124,
    available_slots_today: 3,
    next_available: '2026-08-04T14:00:00Z',
    timezone: 'America/Argentina/Buenos_Aires',
  },
  {
    id: '550e8400-e29b-41d4-a716-446655440002',
    name: 'Dr. Alejandro Ruiz',
    specialty: 'Dermatología',
    rating: 4.6,
    reviews_count: 89,
    available_slots_today: 5,
    next_available: '2026-08-03T16:00:00Z',
    timezone: 'America/Tegucigalpa',
  },
  {
    id: '550e8400-e29b-41d4-a716-446655440003',
    name: 'Dra. Lucía Fernández',
    specialty: 'Pediatría',
    rating: 4.9,
    reviews_count: 203,
    available_slots_today: 0,
    next_available: '2026-08-05T09:00:00Z',
    timezone: 'America/Mexico_City',
  },
  {
    id: '550e8400-e29b-41d4-a716-446655440004',
    name: 'Dr. Carlos Mendoza',
    specialty: 'Neurología',
    rating: 4.7,
    reviews_count: 67,
    available_slots_today: 2,
    next_available: '2026-08-03T18:00:00Z',
    timezone: 'America/Bogota',
  },
  {
    id: '550e8400-e29b-41d4-a716-446655440005',
    name: 'Dra. Valentina Torres',
    specialty: 'Traumatología',
    rating: 4.5,
    reviews_count: 156,
    available_slots_today: 4,
    next_available: '2026-08-04T10:00:00Z',
    timezone: 'America/Santiago',
  },
  {
    id: '550e8400-e29b-41d4-a716-446655440006',
    name: 'Dr. Roberto Sánchez',
    specialty: 'Psiquiatría',
    rating: 4.9,
    reviews_count: 98,
    available_slots_today: 1,
    next_available: '2026-08-03T20:00:00Z',
    timezone: 'America/Lima',
  },
];

export const mockAppointments: AppointmentDisplay[] = [
  {
    id: '770e8400-e29b-41d4-a716-446655442201',
    doctor_name: 'Dra. María García',
    doctor_specialty: 'Cardiología',
    franja_inicio: '2026-08-03T14:00:00Z',
    franja_fin: '2026-08-03T14:30:00Z',
    status: 'confirmed',
    created_at: '2026-08-01T10:00:00Z',
  },
  {
    id: '770e8400-e29b-41d4-a716-446655442202',
    doctor_name: 'Dr. Alejandro Ruiz',
    doctor_specialty: 'Dermatología',
    franja_inicio: '2026-08-04T16:00:00Z',
    franja_fin: '2026-08-04T16:30:00Z',
    status: 'pending',
    created_at: '2026-08-02T15:00:00Z',
  },
  {
    id: '770e8400-e29b-41d4-a716-446655442203',
    doctor_name: 'Dra. Lucía Fernández',
    doctor_specialty: 'Pediatría',
    franja_inicio: '2026-08-01T09:00:00Z',
    franja_fin: '2026-08-01T09:30:00Z',
    status: 'completed',
    created_at: '2026-07-28T12:00:00Z',
  },
  {
    id: '770e8400-e29b-41d4-a716-446655442204',
    doctor_name: 'Dr. Carlos Mendoza',
    doctor_specialty: 'Neurología',
    franja_inicio: '2026-07-30T18:00:00Z',
    franja_fin: '2026-07-30T18:30:00Z',
    status: 'cancelled',
    created_at: '2026-07-25T08:00:00Z',
  },
  {
    id: '770e8400-e29b-41d4-a716-446655442205',
    doctor_name: 'Dra. Valentina Torres',
    doctor_specialty: 'Traumatología',
    franja_inicio: '2026-08-05T10:00:00Z',
    franja_fin: '2026-08-05T10:30:00Z',
    status: 'confirmed',
    created_at: '2026-08-03T09:00:00Z',
  },
];

export const STATUS_CONFIG: Record<string, { label: string; cssClass: string; icon: string }> = {
  pending:   { label: 'Pendiente',   cssClass: 'status--pending',   icon: 'pi-clock' },
  confirmed: { label: 'Confirmada',  cssClass: 'status--confirmed', icon: 'pi-check-circle' },
  cancelled: { label: 'Cancelada',   cssClass: 'status--cancelled', icon: 'pi-times-circle' },
  completed: { label: 'Completada',  cssClass: 'status--completed', icon: 'pi-check-square' },
};

