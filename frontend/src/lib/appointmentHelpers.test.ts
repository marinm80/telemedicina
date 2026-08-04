/**
 * ====================================================================
 * Pruebas de appointmentHelpers — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Pruebas de los contratos:
 *   RF-09 Reserva de Citas sin Solapamiento — validateBooking
 *   RF-25 Cancelación de Citas y Reembolsos — validateCancel, refundLabel
 *
 * La validación client-side espeja el FormRequest del backend.
 * NO reemplaza la validación del servidor.
 */
import { describe, it, expect, vi, afterEach } from 'vitest';
import {
  validateBooking,
  validateCancel,
  refundLabel,
} from './appointmentHelpers';

// ── Helpers de test ────────────────────────────────────────────────────

const VALID_UUID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
const VALID_UUID_2 = 'b1ffcd00-0d1c-4fa9-ac7e-7cc0ce491b22';

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
      patient_id: VALID_UUID,
      doctor_id: VALID_UUID_2,
      franja_inicio: new Date(base).toISOString(),
      franja_fin: new Date(base + 30 * 60_000).toISOString(),
    };
  };

  it('no produce errores con datos válidos', () => {
    const errs = validateBooking(validData());
    expect(Object.keys(errs)).toHaveLength(0);
  });

  // patient_id
  it('rechaza patient_id vacío', () => {
    const errs = validateBooking({ ...validData(), patient_id: '' });
    expect(errs.patient_id).toBeDefined();
  });

  it('rechaza patient_id no UUID', () => {
    const errs = validateBooking({ ...validData(), patient_id: 'not-a-uuid' });
    expect(errs.patient_id).toBeDefined();
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

  // franja_inicio
  it('rechaza franja_inicio vacío', () => {
    const errs = validateBooking({ ...validData(), franja_inicio: '' });
    expect(errs.franja_inicio).toBeDefined();
  });

  it('rechaza franja_inicio en el pasado', () => {
    const past = new Date(Date.now() - 60_000).toISOString();
    const errs = validateBooking({
      ...validData(),
      franja_inicio: past,
      franja_fin: new Date(new Date(past).getTime() + 30 * 60_000).toISOString(),
    });
    expect(errs.franja_inicio).toBeDefined();
  });

  it('rechaza franja_inicio a más de 1 año', () => {
    const twoYears = new Date();
    twoYears.setFullYear(twoYears.getFullYear() + 2);
    const errs = validateBooking({
      ...validData(),
      franja_inicio: twoYears.toISOString(),
      franja_fin: new Date(twoYears.getTime() + 30 * 60_000).toISOString(),
    });
    expect(errs.franja_inicio).toBeDefined();
  });

  // franja_fin
  it('rechaza franja_fin vacío', () => {
    const errs = validateBooking({ ...validData(), franja_fin: '' });
    expect(errs.franja_fin).toBeDefined();
  });

  it('rechaza franja_fin antes de franja_inicio', () => {
    const data = validData();
    const errs = validateBooking({ ...data, franja_fin: data.franja_inicio });
    expect(errs.franja_fin).toBeDefined();
  });

  it('rechaza duración distinta de 30 minutos (45 min)', () => {
    const errs = validateBooking({
      ...validData(),
      franja_fin: futureISO(60 + 45),
    });
    expect(errs.franja_fin).toBeDefined();
    expect(errs.franja_fin).toContain('30 minutos');
  });

  it('rechaza duración de 15 minutos', () => {
    const errs = validateBooking({
      ...validData(),
      franja_fin: futureISO(60 + 15),
    });
    expect(errs.franja_fin).toBeDefined();
  });

  it('acepta exactamente 30 minutos', () => {
    const errs = validateBooking(validData());
    expect(errs.franja_fin).toBeUndefined();
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
