/**
 * ====================================================================
 * Tipos de autenticación — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Tipo mínimo del usuario autenticado. No asume ningún mecanismo de
 * autenticación — es solo la forma del dato que Inertia comparte.
 */

/** Roles del sistema según PRD §3. */
export type UserRole = 'patient' | 'doctor' | 'agent' | 'admin';

/**
 * Usuario autenticado compartido por Inertia como prop de página.
 * La zona horaria se usa para presentar fechas/horas en la zona
 * del usuario, no del navegador (decisión 1).
 */
export interface AuthUser {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  timezone: string; // zona IANA, ej: "America/Argentina/Buenos_Aires"
}
