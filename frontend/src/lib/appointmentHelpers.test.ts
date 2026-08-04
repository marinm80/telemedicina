/**
 * ====================================================================
 * Pruebas de appointmentHelpers — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Pruebas de los contratos:
 *   RF-09 Reserva de Citas sin Solapamiento — validateBooking
 *   RF-25 Cancelación de Citas y Reembolsos — validateCancel, refundLabel
 *   RF-11 Solicitud y Aprobación de Reprogramación — validateReschedule
 *
 * La validación client-side espeja el FormRequest del backend.
 * NO reemplaza la validación del servidor.
 */
import { describe, it, expect, vi, afterEach } from 'vitest';
import {
  validateBooking,
  validateCancel,
  validateReschedule,
  refundLabel,
} from './appointmentHelpers';

// ── Helpers de test ────────────────────────────────────────────────────

const VALID_UUID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

function futureISO(minutesFromNow: number): string {
  const d = new Date(Date.now() + minutesFromNow * 60_000);
  return d.toISOString();
}

// ============================================================================
// validateBooking
// ============================================================================
describe('validateBooking', () => {
  afterEach(() => { vi.useRealTimers(); });

  const validData = () => {
    const base = Date.now() + 60 * 60_000; // 1 hora en el futuro
    return {
      doctor_id: VALID_UUID,
      start_time: new Date(base).toISOString(),
      end_time: new Date(base + 30 * 60_000).toISOString(),
    };
  };

  it('no produce errores con datos válidos', () => {
    const errs = validateBooking(validData());
    expect(Object.keys(errs)).toHaveLength(0);
  });

  // doctor_id
  it('rechaza doctor_id vacío', () => {
    const errs = validateBooking({ ...validData(), doctor_id: '' });
    expect(errs.doctor_id).toBeDefined();
  });

  it('rechaza doctor_id no UUID', () => {
    const errs = validateBooking({ ...validData(), doctor_id: '123' });
    expect(errs.doctor_id).toBeDefined();
  });

  // start_time
  it('rechaza start_time vacío', () => {
    const errs = validateBooking({ ...validData(), start_time: '' });
    expect(errs.start_time).toBeDefined();
  });

  it('rechaza start_time en el pasado', () => {
    const past = new Date(Date.now() - 60_000).toISOString();
    const errs = validateBooking({
      ...validData(),
      start_time: past,
      end_time: new Date(new Date(past).getTime() + 30 * 60_000).toISOString(),
    });
    expect(errs.start_time).toBeDefined();
  });

  it('rechaza start_time a más de 1 año', () => {
    const twoYears = new Date();
    twoYears.setFullYear(twoYears.getFullYear() + 2);
    const errs = validateBooking({
      ...validData(),
      start_time: twoYears.toISOString(),
      end_time: new Date(twoYears.getTime() + 30 * 60_000).toISOString(),
    });
    expect(errs.start_time).toBeDefined();
  });

  // end_time
  it('rechaza end_time vacío', () => {
    const errs = validateBooking({ ...validData(), end_time: '' });
    expect(errs.end_time).toBeDefined();
  });

  it('rechaza end_time antes de start_time', () => {
    const data = validData();
    const errs = validateBooking({ ...data, end_time: data.start_time });
    expect(errs.end_time).toBeDefined();
  });

  it('rechaza duración distinta de 30 minutos (45 min)', () => {
    const base = Date.now() + 60 * 60_000;
    const errs = validateBooking({
      ...validData(),
      start_time: new Date(base).toISOString(),
      end_time: new Date(base + 45 * 60_000).toISOString(),
    });
    expect(errs.end_time).toBeDefined();
    expect(errs.end_time).toContain('30 minutos');
  });

  it('rechaza duración de 15 minutos', () => {
    const base = Date.now() + 60 * 60_000;
    const errs = validateBooking({
      ...validData(),
      start_time: new Date(base).toISOString(),
      end_time: new Date(base + 15 * 60_000).toISOString(),
    });
    expect(errs.end_time).toBeDefined();
  });

  it('acepta exactamente 30 minutos', () => {
    const errs = validateBooking(validData());
    expect(errs.end_time).toBeUndefined();
  });
});

// ============================================================================
// validateCancel
// ============================================================================
describe('validateCancel', () => {
  it('no produce errores con reason vacío (nullable)', () => {
    const errs = validateCancel({ reason: '' });
    expect(Object.keys(errs)).toHaveLength(0);
  });

  it('no produce errores con reason válido', () => {
    const errs = validateCancel({ reason: 'Cambio de planes' });
    expect(Object.keys(errs)).toHaveLength(0);
  });

  it('acepta reason de exactamente 500 caracteres', () => {
    const errs = validateCancel({ reason: 'a'.repeat(500) });
    expect(errs.reason).toBeUndefined();
  });

  it('rechaza reason mayor a 500 caracteres', () => {
    const errs = validateCancel({ reason: 'a'.repeat(501) });
    expect(errs.reason).toBeDefined();
  });
});

// ============================================================================
// validateReschedule
// ============================================================================
describe('validateReschedule', () => {
  const validReschedule = () => {
    const base = Date.now() + 60 * 60_000;
    return {
      requested_start: new Date(base).toISOString(),
      requested_end: new Date(base + 30 * 60_000).toISOString(),
      reason: 'Cambio de turno laboral',
    };
  };

  it('no produce errores con datos válidos', () => {
    const errs = validateReschedule(validReschedule());
    expect(Object.keys(errs)).toHaveLength(0);
  });

  it('rechaza requested_start vacío', () => {
    const errs = validateReschedule({ ...validReschedule(), requested_start: '' });
    expect(errs.requested_start).toBeDefined();
  });

  it('rechaza requested_start en el pasado', () => {
    const past = new Date(Date.now() - 60_000).toISOString();
    const errs = validateReschedule({ ...validReschedule(), requested_start: past });
    expect(errs.requested_start).toBeDefined();
  });

  it('rechaza duración distinta de 30 minutos', () => {
    const base = Date.now() + 60 * 60_000;
    const errs = validateReschedule({
      ...validReschedule(),
      requested_start: new Date(base).toISOString(),
      requested_end: new Date(base + 45 * 60_000).toISOString(),
    });
    expect(errs.requested_end).toBeDefined();
  });

  it('rechaza reason vacío', () => {
    const errs = validateReschedule({ ...validReschedule(), reason: '' });
    expect(errs.reason).toBeDefined();
  });

  it('rechaza reason mayor a 500 caracteres', () => {
    const errs = validateReschedule({ ...validReschedule(), reason: 'a'.repeat(501) });
    expect(errs.reason).toBeDefined();
  });
});

// ============================================================================
// refundLabel
// ============================================================================
describe('refundLabel', () => {
  it('genera texto para reembolso completo', () => {
    const label = refundLabel('full_refund', 100);
    expect(label).toContain('100%');
    expect(label).toContain('completo');
  });

  it('genera texto para sin reembolso', () => {
    const label = refundLabel('no_refund', 0);
    expect(label).toContain('Sin reembolso');
    expect(label).toContain('24h');
  });

  it('devuelve el status crudo si no es conocido', () => {
    const label = refundLabel('partial_refund', 50);
    expect(label).toBe('partial_refund');
  });
});
