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
    manifest: true,
    rollupOptions: {
      input: path.resolve(import.meta.dirname, './src/main.ts'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    watch: {
      usePolling: true,
      interval: 1000,
    },
  }
});
