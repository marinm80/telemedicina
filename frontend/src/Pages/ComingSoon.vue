<!-- AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev -->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props {
  title?: string;
  description?: string;
}

withDefaults(defineProps<Props>(), {
  title: 'Función en desarrollo',
  description: 'Esta sección estará disponible próximamente. Estamos trabajando para ofrecerte la mejor experiencia.',
});
</script>

<template>
  <Head :title="title" />
  
  <AppLayout>
    <div class="coming-soon-container">
      <div class="content-wrapper">
        <div class="icon-container">
          <i class="pi pi-wrench icon"></i>
        </div>
        
        <h1 class="title">{{ title }}</h1>
        <p class="description">{{ description }}</p>
        
        <Link href="/admin" class="btn-primary">
          <i class="pi pi-arrow-left"></i>
          Volver al inicio
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.coming-soon-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 80vh;
  padding: var(--spacing-xl, 2rem);
  text-align: center;
}

.content-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  max-width: 600px;
  background-color: var(--color-surface, #ffffff);
  padding: var(--spacing-xxl, 3rem);
  border-radius: var(--radius-lg, 1rem);
  box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06));
  border: 1px solid var(--color-border, #e5e7eb);
}

.icon-container {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 120px;
  height: 120px;
  background-color: var(--color-primary-light, #e0f2f1);
  color: var(--color-primary, #0d9488);
  border-radius: 50%;
  margin-bottom: var(--spacing-xl, 2rem);
  animation: float 3s ease-in-out infinite;
  box-shadow: var(--shadow-sm, 0 1px 2px 0 rgba(0, 0, 0, 0.05));
}

.icon {
  font-size: 3.5rem;
}

.title {
  font-family: var(--font-heading, 'Outfit', sans-serif);
  font-size: 2.25rem;
  font-weight: 700;
  color: var(--color-text-main, #111827);
  margin-bottom: var(--spacing-md, 1rem);
  margin-top: 0;
}

.description {
  font-family: var(--font-body, sans-serif);
  font-size: 1.125rem;
  line-height: 1.6;
  color: var(--color-text-muted, #6b7280);
  margin-bottom: var(--spacing-xl, 2rem);
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-sm, 0.5rem);
  padding: var(--spacing-md, 1rem) var(--spacing-xl, 2rem);
  background-color: var(--color-primary, #0d9488);
  color: var(--color-on-primary, #ffffff);
  font-family: var(--font-body, sans-serif);
  font-weight: 600;
  font-size: 1rem;
  text-decoration: none;
  border-radius: var(--radius-md, 0.5rem);
  transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: var(--shadow-sm, 0 1px 2px 0 rgba(0, 0, 0, 0.05));
}

.btn-primary:hover {
  background-color: var(--color-primary-dark, #0f766e);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06));
}

.btn-primary:active {
  transform: translateY(0);
}

@keyframes float {
  0% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-12px);
  }
  100% {
    transform: translateY(0px);
  }
}
</style>
