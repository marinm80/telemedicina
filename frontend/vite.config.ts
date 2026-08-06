import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  build: {
    outDir: path.resolve(import.meta.dirname, '../backend/public/build'),
    emptyOutDir: true,
    // string, no boolean: Vite 5+ escribe en <outDir>/.vite/manifest.json
    // por defecto si manifest es `true`. Laravel busca <outDir>/manifest.json
    // directo, sin la subcarpeta .vite/ — con un string se lo forzamos ahí.
    manifest: 'manifest.json',
    rollupOptions: {
      input: path.resolve(import.meta.dirname, './src/main.ts'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    host: true,
    origin: 'http://localhost:5173',
    proxy: {
      '/api': {
        target: process.env.VITE_BACKEND_URL || 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      },
      '/verify': {
        target: process.env.VITE_BACKEND_URL || 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      },
    },
    watch: {
      usePolling: true,
      interval: 1000,
    },
  }
});
