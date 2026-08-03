/**
 * ====================================================================
 * Preset de PrimeVue — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE ARCHIVO
 * Un único lugar para mapear los tokens de diseño (tokens.css) al sistema
 * de temas de PrimeVue 4 (Aura). Los componentes PrimeVue consumen el
 * preset; los componentes propios consumen var(--color-*) de tokens.css.
 *
 * REGLA: cambiar de preset (Aura → Lara) cuesta cambiar ESTE archivo.
 * Si cuesta más, algo se acopló mal.
 *
 * ALTERNATIVA DESCARTADA
 * Dejar Aura sin sobrescribir. Produce una paleta desalineada con los
 * tokens propios: dos fuentes de verdad para los mismos colores.
 *
 * NOTA: los valores de primary-200 a primary-400 y primary-800/950 no
 * fueron definidos explícitamente por el humano. Se completan con la
 * misma escala Tailwind CSS blue de donde salen los demás stops, para
 * que PrimeVue tenga la escala completa. Si estos valores necesitan
 * cambiar, se cambian ACÁ — no en cuarenta componentes.
 */
import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

/** Escala completa de superficie (Tailwind slate) */
const SURFACE = {
  0:   '#FFFFFF',
  50:  '#F8FAFC',
  100: '#F1F5F9',
  200: '#E2E8F0',
  300: '#CBD5E1',
  400: '#94A3B8',
  500: '#64748B',
  600: '#475569',
  700: '#334155',
  800: '#1E293B',
  900: '#0F172A',
  950: '#020617',
} as const;

export const telemedicinaPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '#EFF6FF',
      100: '#DBEAFE',
      200: '#BFDBFE',   // Completado: escala Tailwind blue
      300: '#93C5FD',   // Completado: escala Tailwind blue
      400: '#60A5FA',   // Completado: escala Tailwind blue
      500: '#3B82F6',
      600: '#2563EB',
      700: '#1D4ED8',
      800: '#1E40AF',   // Completado: escala Tailwind blue
      900: '#1E3A8A',
      950: '#172554',   // Completado: escala Tailwind blue
    },
    colorScheme: {
      light: {
        primary: {
          color: '{primary.700}',
          inverseColor: '#FFFFFF',
          hoverColor: '{primary.600}',
          activeColor: '{primary.900}',
        },
        highlight: {
          background: '{primary.50}',
          focusBackground: '{primary.100}',
          color: '{primary.700}',
          focusColor: '{primary.800}',
        },
        surface: SURFACE,
      },
    },
  },
});
