import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig(({ command }) => ({
  // Laravel sirve el build compilado desde /build/, no desde la raíz del
  // sitio. Sin esto, Vite asume base:'/' y las url() generadas para
  // assets referenciados desde CSS (p.ej. @font-face de primeicons)
  // quedan como /assets/... en vez de /build/assets/..., y 404ean —
  // los íconos se ven como el glifo de "carácter desconocido" del
  // navegador. Los <script>/<link> de entrada no se ven afectados:
  // esos los resuelve @vite() de Laravel leyendo el manifest, no esto.
  //
  // SOLO en build: el server de dev (comando 'serve') sirve todo desde
  // su propia raíz — app.blade.php en modo local pide los módulos
  // directo a http://localhost:5173/src/main.ts, sin prefijo /build/.
  // Aplicar esto también en dev rompe el dev server (404 en todo).
  base: command === 'build' ? '/build/' : '/',
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
}));
