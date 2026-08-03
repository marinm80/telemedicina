<!--
  ====================================================================
  ErrorFallback — Estado: error con reintento
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE COMPONENTE
  Panel de error con mensaje y botón de reintento.
  Replica la forma del bloque "error" del ejemplar UserList.vue:
  - role="alert" para accesibilidad.
  - Botón de reintento, no solo el mensaje (Gate 2A).

  ALTERNATIVA DESCARTADA
  Solo mostrar el mensaje sin acción. Un error sin reintento obliga
  al usuario a recargar la página completa.
-->
<script setup lang="ts">
defineProps<{
  /** Mensaje de error legible para el usuario */
  message: string;
  /** Callback de reintento. Si no se provee, no se muestra el botón */
  onRetry?: () => void;
}>();
</script>

<template>
  <div class="error-fallback" role="alert">
    <div class="error-fallback__header">
      <i class="pi pi-exclamation-triangle error-fallback__icon" aria-hidden="true" />
      <p class="error-fallback__message">{{ message }}</p>
    </div>
    <button
      v-if="onRetry"
      type="button"
      class="error-fallback__retry"
      @click="onRetry"
    >
      <i class="pi pi-refresh" aria-hidden="true" />
      Reintentar
    </button>
  </div>
</template>

<style scoped>
.error-fallback {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--spacing-3);
  padding: var(--spacing-4);
  border: 1px solid var(--color-error-100);
  border-left: 4px solid var(--color-error-600);
  border-radius: var(--radius-md);
  background-color: var(--color-error-50);
}

.error-fallback__header {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-2);
}

.error-fallback__icon {
  flex-shrink: 0;
  margin-top: 2px;
  font-size: var(--text-lg);
  color: var(--color-error-600);
}

.error-fallback__message {
  font-size: var(--text-sm);
  line-height: var(--leading-normal);
  color: var(--color-error-700);
}

.error-fallback__retry {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-1) var(--spacing-3);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-error-700);
  background-color: transparent;
  border: 1px solid var(--color-error-700);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background-color var(--transition-fast), color var(--transition-fast);
}

.error-fallback__retry:hover {
  background-color: var(--color-error-100);
  color: var(--color-error-700);
}

.error-fallback__retry:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
