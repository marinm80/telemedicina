/**
 * ====================================================================
 * Pruebas de scheduleHelpers — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Pruebas del contrato RF-08 Configuración de Agenda y Bloqueos:
 * 1. parseFranja: extrae inicio/fin de timerange PostgreSQL.
 * 2. timeToApi: convierte HH:MM a HH:MM:SS.
 * 3. validateSchedule: espeja reglas del controlador.
 * 4. validateBlock: espeja reglas del controlador.
 */
import { describe, it, expect } from 'vitest';
import {
  parseFranja,
  timeToApi,
  validateSchedule,
  validateBlock,
  DAYS,
} from './scheduleHelpers';

// ============================================================================
// parseFranja
// ============================================================================
describe('parseFranja', () => {
  it('parsea timerange estándar con espacio', () => {
    const result = parseFranja('[08:00:00, 12:00:00)');
    expect(result).toEqual({ inicio: '08:00', fin: '12:00' });
  });

  it('parsea timerange sin espacio', () => {
    const result = parseFranja('[08:00:00,12:00:00)');
    expect(result).toEqual({ inicio: '08:00', fin: '12:00' });
  });

  it('parsea horario nocturno', () => {
    const result = parseFranja('[18:00:00, 22:30:00)');
    expect(result).toEqual({ inicio: '18:00', fin: '22:30' });
  });

  it('parsea medianoche', () => {
    const result = parseFranja('[00:00:00, 06:00:00)');
    expect(result).toEqual({ inicio: '00:00', fin: '06:00' });
  });

  it('lanza error con formato inválido', () => {
    expect(() => parseFranja('08:00-12:00')).toThrow('Formato de franja no reconocido');
  });

  it('lanza error con string vacío', () => {
    expect(() => parseFranja('')).toThrow('Formato de franja no reconocido');
  });
});

// ============================================================================
// timeToApi
// ============================================================================
describe('timeToApi', () => {
  it('agrega :00 a HH:MM', () => {
    expect(timeToApi('08:00')).toBe('08:00:00');
  });

  it('no modifica si ya tiene segundos', () => {
    expect(timeToApi('08:00:00')).toBe('08:00:00');
  });

  it('convierte horario nocturno', () => {
    expect(timeToApi('22:30')).toBe('22:30:00');
  });
});

// ============================================================================
// validateSchedule
// ============================================================================
describe('validateSchedule', () => {
  const valid = { day_of_week: 1, inicio: '08:00', fin: '12:00', slot_duration: 30 };

  it('no produce errores con datos válidos', () => {
    const errs = validateSchedule(valid);
    expect(Object.keys(errs)).toHaveLength(0);
  });

  it('acepta slot_duration null (usa default del servidor)', () => {
    const errs = validateSchedule({ ...valid, slot_duration: null });
    expect(Object.keys(errs)).toHaveLength(0);
  });

  it('rechaza day_of_week 0', () => {
    const errs = validateSchedule({ ...valid, day_of_week: 0 });
    expect(errs.day_of_week).toBeDefined();
  });

  it('rechaza day_of_week 8', () => {
    const errs = validateSchedule({ ...valid, day_of_week: 8 });
    expect(errs.day_of_week).toBeDefined();
  });

  it('acepta day_of_week 7 (Domingo)', () => {
    const errs = validateSchedule({ ...valid, day_of_week: 7 });
    expect(errs.day_of_week).toBeUndefined();
  });

  it('rechaza inicio vacío', () => {
    const errs = validateSchedule({ ...valid, inicio: '' });
    expect(errs.inicio).toBeDefined();
  });

  it('rechaza fin vacío', () => {
    const errs = validateSchedule({ ...valid, fin: '' });
    expect(errs.fin).toBeDefined();
  });

  it('rechaza fin igual a inicio', () => {
    const errs = validateSchedule({ ...valid, inicio: '12:00', fin: '12:00' });
    expect(errs.fin).toBeDefined();
  });

  it('rechaza fin antes de inicio', () => {
    const errs = validateSchedule({ ...valid, inicio: '14:00', fin: '12:00' });
    expect(errs.fin).toBeDefined();
  });

  it('rechaza slot_duration 9 (mínimo 10)', () => {
    const errs = validateSchedule({ ...valid, slot_duration: 9 });
    expect(errs.slot_duration).toBeDefined();
  });

  it('rechaza slot_duration 121 (máximo 120)', () => {
    const errs = validateSchedule({ ...valid, slot_duration: 121 });
    expect(errs.slot_duration).toBeDefined();
  });

  it('acepta slot_duration 10 (mínimo)', () => {
    const errs = validateSchedule({ ...valid, slot_duration: 10 });
    expect(errs.slot_duration).toBeUndefined();
  });

  it('acepta slot_duration 120 (máximo)', () => {
    const errs = validateSchedule({ ...valid, slot_duration: 120 });
    expect(errs.slot_duration).toBeUndefined();
  });
});

// ============================================================================
// validateBlock
// ============================================================================
describe('validateBlock', () => {
  const valid = { blocked_date: '2026-08-15', inicio: '09:00', fin: '11:00', reason: 'Vacaciones' };

  it('no produce errores con datos válidos', () => {
    const errs = validateBlock(valid);
    expect(Object.keys(errs)).toHaveLength(0);
  });

  it('rechaza fecha vacía', () => {
    const errs = validateBlock({ ...valid, blocked_date: '' });
    expect(errs.blocked_date).toBeDefined();
  });

  it('rechaza formato de fecha inválido', () => {
    const errs = validateBlock({ ...valid, blocked_date: '15/08/2026' });
    expect(errs.blocked_date).toBeDefined();
  });

  it('rechaza inicio vacío', () => {
    const errs = validateBlock({ ...valid, inicio: '' });
    expect(errs.inicio).toBeDefined();
  });

  it('rechaza fin antes de inicio', () => {
    const errs = validateBlock({ ...valid, inicio: '14:00', fin: '09:00' });
    expect(errs.fin).toBeDefined();
  });

  it('rechaza reason vacío', () => {
    const errs = validateBlock({ ...valid, reason: '' });
    expect(errs.reason).toBeDefined();
  });

  it('rechaza reason solo espacios', () => {
    const errs = validateBlock({ ...valid, reason: '   ' });
    expect(errs.reason).toBeDefined();
  });

  it('rechaza reason mayor a 255 caracteres', () => {
    const errs = validateBlock({ ...valid, reason: 'a'.repeat(256) });
    expect(errs.reason).toBeDefined();
  });

  it('acepta reason de exactamente 255 caracteres', () => {
    const errs = validateBlock({ ...valid, reason: 'a'.repeat(255) });
    expect(errs.reason).toBeUndefined();
  });
});

// ============================================================================
// DAYS constant
// ============================================================================
describe('DAYS', () => {
  it('tiene 7 días (Lunes a Domingo)', () => {
    expect(DAYS).toHaveLength(7);
  });

  it('empieza en 1 (Lunes) y termina en 7 (Domingo)', () => {
    expect(DAYS[0].id).toBe(1);
    expect(DAYS[0].label).toBe('Lunes');
    expect(DAYS[6].id).toBe(7);
    expect(DAYS[6].label).toBe('Domingo');
  });
});
