<!--
  ====================================================================
  SpinnerLoader — Estado: cargando (skeleton configurable)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE COMPONENTE
  Skeleton animado con shimmer para el estado "cargando".
  Replica la forma del bloque "cargando" del ejemplar UserList.vue:
  - aria-busy="true" para accesibilidad.
  - Variantes para diferentes contextos (lista, tarjeta, formulario).
  - Animación sutil que no distrae.

  ALTERNATIVA DESCARTADA
  Spinner circular genérico. Los skeletons dan mejor contexto visual
  del contenido que se está cargando, y evitan layout shift.
-->
<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Número de líneas skeleton a mostrar */
    lines?: number;
    /** Contexto visual: cambia anchos y alturas de las líneas */
    variant?: 'card' | 'list' | 'form';
  }>(),
  { lines: 3, variant: 'list' },
);
</script>

<template>
  <div
    :class="['spinner-loader', `spinner-loader--${variant}`]"
    aria-busy="true"
    role="status"
  >
    <span class="spinner-loader__sr-only">Cargando…</span>
    <div
      v-for="n in lines"
      :key="n"
      class="spinner-loader__line"
      :style="{ animationDelay: `${(n - 1) * 120}ms` }"
    />
  </div>
</template>

<style scoped>
.spinner-loader {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.spinner-loader__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.spinner-loader__line {
  border-radius: var(--radius-md);
  background: linear-gradient(
    90deg,
    var(--color-surface-100) 25%,
    var(--color-surface-200) 50%,
    var(--color-surface-100) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
}

/* --- Variante: list (por defecto) --- */
.spinner-loader--list .spinner-loader__line {
  height: 2.5rem;
}

/* --- Variante: card --- */
.spinner-loader--card {
  gap: var(--spacing-3);
}

.spinner-loader--card .spinner-loader__line {
  height: 4rem;
  border-radius: var(--radius-lg);
}

/* --- Variante: form --- */
.spinner-loader--form .spinner-loader__line {
  height: 2.25rem;
}

.spinner-loader--form .spinner-loader__line:nth-child(odd) {
  width: 40%;
  height: 0.875rem;
  border-radius: var(--radius-sm);
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
