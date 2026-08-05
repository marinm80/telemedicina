/**
 * ====================================================================
 * Preset de PrimeVue — Plataforma de Telemedicina "Salvia"
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Mapeo de la paleta Salvia (dark teal / sage / warm cream) al sistema
 * de temas PrimeVue 4 (Aura). Los componentes PrimeVue consumen el
 * preset; los componentes propios consumen var(--color-*) de tokens.css.
 */
import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

/** Escala de superficie Salvia (warm neutrals en lugar de slate) */
const SURFACE = {
  0:   '#FFFFFF',
  50:  '#FAF9F5',
  100: '#F4F1EA',
  200: '#EDE4D8',
  300: '#E8DFD3',
  400: '#C4B9AB',
  500: '#8FA39D',
  600: '#5F7A73',
  700: '#3D5A52',
  800: '#17302B',
  900: '#0E2420',
  950: '#091815',
} as const;

export const telemedicinaPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '#E8F5F2',
      100: '#C8E6DE',
      200: '#A0D4C5',
      300: '#73BFAA',
      400: '#4FA997',
      500: '#2E9E6B',
      600: '#0E5D52',
      700: '#0B4D44',
      800: '#093E37',
      900: '#17302B',
      950: '#091815',
    },
    colorScheme: {
      light: {
        primary: {
          color: '{primary.600}',
          inverseColor: '#FFFFFF',
          hoverColor: '{primary.700}',
          activeColor: '{primary.900}',
        },
        highlight: {
          background: '{primary.50}',
          focusBackground: '{primary.100}',
          color: '{primary.600}',
          focusColor: '{primary.700}',
        },
        surface: SURFACE,
      },
    },
  },
});
