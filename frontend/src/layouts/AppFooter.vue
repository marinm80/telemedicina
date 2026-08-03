<!--
  ====================================================================
  AppFooter — Footer de créditos
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE COMPONENTE
  Footer de créditos para perfil PORTAFOLIO. Siempre visible en el
  borde inferior del shell. Texto sencillo con enlace al autor.
-->
<script setup lang="ts">
import { inject } from 'vue';
import { i18nKey } from '@/i18n/plugin';

const t = inject(i18nKey)!;
</script>

<template>
  <footer class="app-footer">
    <p class="app-footer__text">
      {{ t('footer.credits') }} ·
      <a
        href="https://rafaelmarin.dev"
        target="_blank"
        rel="noopener noreferrer"
        class="app-footer__link"
      >
        {{ t('footer.author') }}
      </a>
    </p>
  </footer>
</template>

<style scoped>
.app-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-4) var(--spacing-6);
  border-top: 1px solid var(--color-surface-200);
  background-color: var(--color-surface-0);
}

.app-footer__text {
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
}

.app-footer__link {
  color: var(--color-primary-700);
  text-decoration: none;
  font-weight: var(--font-medium);
  transition: color var(--transition-fast);
}

.app-footer__link:hover {
  color: var(--color-primary-600);
  text-decoration: underline;
}

.app-footer__link:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}
</style>
