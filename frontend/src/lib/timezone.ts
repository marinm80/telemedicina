/**
 * ====================================================================
 * Zona horaria por usuario — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Cero dependencias. Usa Intl.DateTimeFormat nativo con opción timeZone.
 * El backend manda instantes UTC (timestamptz). El frontend los presenta
 * en la zona del usuario. No hay aritmética de fechas en el frontend.
 *
 * ALTERNATIVA DESCARTADA
 * Luxon (~70kB). Se justifica para aritmética dentro de una zona, y esa
 * aritmética vive en PostgreSQL. Una dependencia se justifica cuando hace
 * algo que la plataforma no puede, no cuando es más cómoda.
 */

/**
 * Formatea un instante UTC en la zona horaria del usuario.
 *
 * @param isoUtc - Instante en formato ISO 8601 UTC (ej: "2026-08-03T14:00:00Z")
 * @param timezone - Zona IANA del usuario (ej: "America/Argentina/Buenos_Aires")
 * @param options - Opciones de Intl.DateTimeFormat (por defecto: fecha + hora cortas)
 */
export function formatInUserTimezone(
  isoUtc: string,
  timezone: string,
  options?: Intl.DateTimeFormatOptions,
): string {
  const date = new Date(isoUtc);
  const defaultOptions: Intl.DateTimeFormatOptions = {
    timeZone: timezone,
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  };
  return new Intl.DateTimeFormat('es', options ?? defaultOptions).format(date);
}

/**
 * Devuelve una etiqueta legible con el offset calculado para un instante dado.
 * El offset cambia con horario de verano, por eso se necesita el instante.
 *
 * @param timezone - Zona IANA (ej: "America/Argentina/Buenos_Aires")
 * @param atInstant - Instante ISO 8601 para calcular el offset (por defecto: ahora)
 * @returns Etiqueta legible, ej: "America/Argentina/Buenos_Aires (UTC-3)"
 */
export function getUserTimezoneLabel(
  timezone: string,
  atInstant?: string,
): string {
  const date = atInstant ? new Date(atInstant) : new Date();

  // Intl.DateTimeFormat con timeZoneName: 'shortOffset' da el offset real
  // para ese instante (respeta DST).
  const formatter = new Intl.DateTimeFormat('en', {
    timeZone: timezone,
    timeZoneName: 'shortOffset',
  });

  const parts = formatter.formatToParts(date);
  const offsetPart = parts.find((p) => p.type === 'timeZoneName');
  const offset = offsetPart?.value ?? 'UTC';

  return `${timezone} (${offset})`;
}

/**
 * Sugiere la zona horaria del navegador para prellenar el campo de registro.
 *
 * ÚNICO uso legítimo de la zona del navegador. El nombre hace de barrera:
 * no es para mostrar datos clínicos — es para prellenar un campo en el
 * formulario de registro. Después del registro, la zona sale de
 * users.timezone (decisión 7).
 */
export function suggestTimezoneForRegistration(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone;
}
