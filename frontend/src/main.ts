/**
 * ====================================================================
 * Punto de entrada — Plataforma de Telemedicina (Frontend)
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import { telemedicinaPreset } from '@/config/primevue.preset';
import { i18nPlugin } from '@/i18n/plugin';

import 'primeicons/primeicons.css';

// Fuentes autohospedadas — cero peticiones a terceros.
// Solo los pesos usados, subset latino.
import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-500.css';
import '@fontsource/inter/latin-600.css';
import '@fontsource/inter/latin-700.css';
import '@fontsource/outfit/latin-500.css';
import '@fontsource/outfit/latin-600.css';
import '@fontsource/outfit/latin-700.css';

import '@/assets/styles/base.css';

createInertiaApp({
  resolve: (name: string) => {
    const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue', { eager: true });
    const page = pages[`./Pages/${name}.vue`];
    if (!page) {
      throw new Error(`Page not found: ./Pages/${name}.vue`);
    }
    return page.default;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(PrimeVue, {
        theme: {
          preset: telemedicinaPreset,
          options: {
            darkModeSelector: false,
          },
        },
      })
      .use(i18nPlugin)
      .mount(el);
  },
});
