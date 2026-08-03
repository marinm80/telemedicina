<!--
  ====================================================================
  DemoBanner — Cintillo de modo demo (PORTAFOLIO)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE COMPONENTE
  Cintillo superior fijo que marca la app como DEMO de portafolio.
  Siempre visible. Enlace al portafolio del autor.
  Texto de UI_PROTOTYPE.md §3 wireframe: "Modo de Demostración Activo."
-->
<script setup lang="ts">
import { inject } from 'vue';
import { i18nKey } from '@/i18n/plugin';

const t = inject(i18nKey)!;
</script>

<template>
  <div class="demo-banner" role="banner">
    <span class="demo-banner__text">
      <i class="pi pi-info-circle" aria-hidden="true" />
      {{ t('demo.banner') }}
    </span>
    <a
      href="https://rafaelmarin.dev"
      target="_blank"
      rel="noopener noreferrer"
      class="demo-banner__link"
    >
      {{ t('demo.portfolio_link') }}
      <i class="pi pi-external-link" aria-hidden="true" />
    </a>
  </div>
</template>

<style scoped>
.demo-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-3);
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  color: var(--color-warning-800);
  background-color: var(--color-warning-50);
  border-bottom: 1px solid var(--color-surface-200);
}

.demo-banner__text {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
}

.demo-banner__link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--color-primary-700);
  text-decoration: none;
  font-weight: var(--font-semibold);
  transition: color var(--transition-fast);
}

.demo-banner__link:hover {
  color: var(--color-primary-600);
  text-decoration: underline;
}

.demo-banner__link:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}
</style>
