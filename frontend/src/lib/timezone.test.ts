/**
 * ====================================================================
 * Pruebas de timezone — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Pruebas obligatorias:
 * 1. Formatear un instante en dos zonas distintas y verificar el resultado.
 * 2. Usar fechas fijas, no Date.now().
 * 3. Un caso en día de cambio de horario (DST transition).
 * 4. getUserTimezoneLabel devuelve la etiqueta con offset.
 * 5. suggestTimezoneForRegistration devuelve un string IANA.
 */
import { describe, it, expect } from 'vitest';
import {
  formatInUserTimezone,
  getUserTimezoneLabel,
  suggestTimezoneForRegistration,
} from './timezone';

describe('formatInUserTimezone', () => {
  // Instante fijo: 2026-08-03T18:00:00Z (UTC)
  const INSTANT = '2026-08-03T18:00:00Z';

  it('formatea en Buenos Aires (UTC-3) correctamente', () => {
    const result = formatInUserTimezone(INSTANT, 'America/Argentina/Buenos_Aires', {
      timeZone: 'America/Argentina/Buenos_Aires',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
    // 18:00 UTC = 15:00 en Argentina (UTC-3, sin DST)
    expect(result).toBe('15:00');
  });

  it('formatea en Tokio (UTC+9) correctamente', () => {
    const result = formatInUserTimezone(INSTANT, 'Asia/Tokyo', {
      timeZone: 'Asia/Tokyo',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
    // 18:00 UTC = 03:00 del día siguiente en Tokio (UTC+9)
    expect(result).toBe('03:00');
  });

  it('formatea el mismo instante en dos zonas con resultado diferente', () => {
    const opts = (tz: string): Intl.DateTimeFormatOptions => ({
      timeZone: tz,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });

    const buenosAires = formatInUserTimezone(INSTANT, 'America/Argentina/Buenos_Aires', opts('America/Argentina/Buenos_Aires'));
    const tokyo = formatInUserTimezone(INSTANT, 'Asia/Tokyo', opts('Asia/Tokyo'));

    expect(buenosAires).not.toBe(tokyo);
  });

  it('usa opciones por defecto cuando no se proveen', () => {
    const result = formatInUserTimezone(INSTANT, 'UTC');
    // El formato por defecto incluye fecha y hora
    expect(result).toBeTruthy();
    expect(typeof result).toBe('string');
    expect(result.length).toBeGreaterThan(0);
  });

  // =========================================================================
  // Caso en día de cambio de horario (DST transition)
  // =========================================================================
  // En EE.UU. (Eastern), el cambio de horario de verano (spring forward)
  // ocurre el segundo domingo de marzo. En 2026: 8 de marzo.
  // A las 02:00 EST se salta a 03:00 EDT.
  //
  // Instante justo antes del salto: 2026-03-08T06:59:00Z = 01:59 EST (UTC-5)
  // Instante justo después del salto: 2026-03-08T07:01:00Z = 03:01 EDT (UTC-4)
  // =========================================================================
  it('respeta el cambio de horario (DST spring forward — New York)', () => {
    const beforeDST = '2026-03-08T06:59:00Z';
    const afterDST  = '2026-03-08T07:01:00Z';

    const optNY = (tz: string): Intl.DateTimeFormatOptions => ({
      timeZone: tz,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });

    const before = formatInUserTimezone(beforeDST, 'America/New_York', optNY('America/New_York'));
    const after  = formatInUserTimezone(afterDST, 'America/New_York', optNY('America/New_York'));

    // Antes del salto: 01:59 EST
    expect(before).toBe('01:59');
    // Después del salto: 03:01 EDT (02:00-02:59 no existe)
    expect(after).toBe('03:01');
  });

  it('una zona sin DST no cambia offset (Buenos Aires)', () => {
    // Argentina no tiene DST desde 2009
    const winter = '2026-07-15T12:00:00Z'; // Invierno sur
    const summer = '2026-01-15T12:00:00Z'; // Verano sur

    const opt = (tz: string): Intl.DateTimeFormatOptions => ({
      timeZone: tz,
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });

    const winterResult = formatInUserTimezone(winter, 'America/Argentina/Buenos_Aires', opt('America/Argentina/Buenos_Aires'));
    const summerResult = formatInUserTimezone(summer, 'America/Argentina/Buenos_Aires', opt('America/Argentina/Buenos_Aires'));

    // Ambos deben ser la misma hora (UTC-3 siempre)
    expect(winterResult).toBe('09:00');
    expect(summerResult).toBe('09:00');
  });
});

describe('getUserTimezoneLabel', () => {
  it('devuelve etiqueta con zona y offset', () => {
    const label = getUserTimezoneLabel('America/Argentina/Buenos_Aires', '2026-08-03T12:00:00Z');
    expect(label).toContain('America/Argentina/Buenos_Aires');
    // El offset debe estar presente: (GMT-3) o similar
    expect(label).toMatch(/\(.*-3.*\)/);
  });

  it('devuelve offset diferente para zona con DST en invierno vs verano', () => {
    const winter = getUserTimezoneLabel('America/New_York', '2026-01-15T12:00:00Z'); // EST
    const summer = getUserTimezoneLabel('America/New_York', '2026-07-15T12:00:00Z'); // EDT

    // Ambos deben tener la zona, pero offsets diferentes
    expect(winter).toContain('America/New_York');
    expect(summer).toContain('America/New_York');
    expect(winter).not.toBe(summer);
  });
});

describe('suggestTimezoneForRegistration', () => {
  it('devuelve un string IANA válido', () => {
    const tz = suggestTimezoneForRegistration();
    expect(typeof tz).toBe('string');
    // Un string IANA contiene "/"
    expect(tz).toContain('/');
    expect(tz.length).toBeGreaterThan(0);
  });
});
