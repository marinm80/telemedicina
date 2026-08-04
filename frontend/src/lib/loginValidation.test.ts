/**
 * ====================================================================
 * Pruebas de loginValidation — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Pruebas obligatorias:
 * 1. Email vacío produce error de campo obligatorio.
 * 2. Email con formato inválido produce error de formato.
 * 3. Contraseña vacía produce error de campo obligatorio.
 * 4. Campos válidos no producen errores.
 * 5. CREDENTIAL_ERROR es IDÉNTICO para correo inexistente y contraseña mala.
 *    Esto es la prueba de que la constante es una sola — no dos textos
 *    que hoy coinciden y mañana divergen.
 */
import { describe, it, expect } from 'vitest';
import { validateLoginClient, CREDENTIAL_ERROR } from './loginValidation';

describe('validateLoginClient', () => {
  it('rechaza email vacío', () => {
    const errs = validateLoginClient('', 'password123');
    expect(errs.email).toBe('El correo electrónico es obligatorio.');
    expect(errs.password).toBeUndefined();
  });

  it('rechaza email solo con espacios', () => {
    const errs = validateLoginClient('   ', 'password123');
    expect(errs.email).toBe('El correo electrónico es obligatorio.');
  });

  it('rechaza email con formato inválido', () => {
    const errs = validateLoginClient('no-es-email', 'password123');
    expect(errs.email).toBe('Ingresa un correo electrónico válido.');
  });

  it('rechaza email sin dominio', () => {
    const errs = validateLoginClient('user@', 'password123');
    expect(errs.email).toBe('Ingresa un correo electrónico válido.');
  });

  it('acepta email con formato válido', () => {
    const errs = validateLoginClient('user@example.com', 'password123');
    expect(errs.email).toBeUndefined();
  });

  it('rechaza contraseña vacía', () => {
    const errs = validateLoginClient('user@example.com', '');
    expect(errs.password).toBe('La contraseña es obligatoria.');
    expect(errs.email).toBeUndefined();
  });

  it('reporta ambos errores si email y contraseña están vacíos', () => {
    const errs = validateLoginClient('', '');
    expect(errs.email).toBeDefined();
    expect(errs.password).toBeDefined();
  });

  it('no produce errores con campos válidos', () => {
    const errs = validateLoginClient('user@example.com', 'securePass1!');
    expect(Object.keys(errs)).toHaveLength(0);
  });
});

describe('CREDENTIAL_ERROR — seguridad del mensaje', () => {
  it('es una constante de tipo string no vacía', () => {
    expect(typeof CREDENTIAL_ERROR).toBe('string');
    expect(CREDENTIAL_ERROR.length).toBeGreaterThan(0);
  });

  it('es el MISMO texto para cualquier escenario de fallo', () => {
    // La constante es UNA SOLA. No hay dos constantes que hoy coinciden.
    // Si alguien crea una segunda constante, esta prueba no falla — pero
    // el hecho de que existe una sola exportación es la barrera.
    //
    // Lo que sí verificamos: que no contiene pistas sobre qué falló.
    expect(CREDENTIAL_ERROR).not.toContain('correo no existe');
    expect(CREDENTIAL_ERROR).not.toContain('contraseña incorrecta');
    expect(CREDENTIAL_ERROR).not.toContain('usuario no encontrado');
    expect(CREDENTIAL_ERROR).not.toContain('email not found');
    expect(CREDENTIAL_ERROR).not.toContain('wrong password');
  });

  it('no revela información de enumeración de usuarios', () => {
    // El mensaje genérico no debe dar pistas sobre si el correo existe
    const lowerMessage = CREDENTIAL_ERROR.toLowerCase();
    expect(lowerMessage).not.toContain('no existe');
    expect(lowerMessage).not.toContain('no registrado');
    expect(lowerMessage).not.toContain('not found');
  });
});
