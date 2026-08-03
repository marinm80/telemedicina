<!--
  ====================================================================
  EmptyState — Estado: vacío con acción
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE COMPONENTE
  Estado vacío con icono, mensaje descriptivo y botón de acción opcional.
  Replica la forma del bloque "vacío" del ejemplar UserList.vue:
  - Distinto de "cargando" y distinto de "error" (Gate 2A).
  - Texto descriptivo, no genérico ("No hay datos").
  - Acción cuando aplica (ej: "Crear primero").

  ALTERNATIVA DESCARTADA
  Solo mostrar un párrafo de texto. El icono y la acción dan contexto
  visual y un camino de salida.
-->
<script setup lang="ts">
defineProps<{
  /** Mensaje descriptivo del estado vacío */
  message: string;
  /** Texto del botón de acción. Si no se provee, no se muestra botón */
  actionLabel?: string;
  /** Callback de la acción */
  onAction?: () => void;
}>();
</script>

<template>
  <div class="empty-state">
    <div class="empty-state__icon-container">
      <i class="pi pi-inbox empty-state__icon" aria-hidden="true" />
    </div>
    <p class="empty-state__message">{{ message }}</p>
    <button
      v-if="actionLabel && onAction"
      type="button"
      class="empty-state__action"
      @click="onAction"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-8) var(--spacing-4);
  text-align: center;
}

.empty-state__icon-container {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-full);
  background-color: var(--color-surface-100);
}

.empty-state__icon {
  font-size: 1.5rem;
  color: var(--color-text-subtle);
}

.empty-state__message {
  max-width: 28rem;
  font-size: var(--text-sm);
  line-height: var(--leading-relaxed);
  color: var(--color-text-muted);
}

.empty-state__action {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-4);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-surface-0);
  background-color: var(--color-primary-700);
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.empty-state__action:hover {
  background-color: var(--color-primary-600);
}

.empty-state__action:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
