/**
 * ====================================================================
 * Tipos comunes — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Tipos compartidos entre módulos. EstadoCarga es el tipo central que
 * gobierna los 4 estados obligatorios (inicial, cargando, listo, error).
 * Copiado del ejemplar canónico useUsers.ts.
 */

/** Los cuatro estados son OBLIGATORIOS. No existe "solo el caso feliz". */
export type EstadoCarga = 'inicial' | 'cargando' | 'listo' | 'error';
