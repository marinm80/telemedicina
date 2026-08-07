/**
 * ====================================================================
 * Configuración de Vitest — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Extiende la configuración de Vite (alias @/) y agrega happy-dom
 * como entorno de prueba para simular el DOM sin navegador.
 */
import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config.ts';

const resolvedViteConfig = typeof viteConfig === 'function'
  ? viteConfig({ command: 'serve', mode: 'test' })
  : viteConfig;

export default mergeConfig(resolvedViteConfig, defineConfig({
  test: {
    environment: 'happy-dom',
    include: ['src/**/*.test.ts'],
  },
}));
