<!--
  ====================================================================
  AppLayout — Shell principal de la aplicación
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE COMPONENTE
  Shell de tres franjas: DemoBanner (top) + contenido (main) + AppFooter.
  Estructura de UI_PROTOTYPE.md §1:
    AppLayout (Cintillo de Demo + Sidebar + Footer de Créditos)

  El sidebar no se implementa en esta entrega (fuera de alcance):
  las rutas de Inertia y la navegación dependen del backend.
  La estructura del layout ya lo soporta con el slot lateral.
-->
<script setup lang="ts">
import DemoBanner from './DemoBanner.vue';
import AppFooter from './AppFooter.vue';
</script>

<template>
  <div class="app-layout">
    <DemoBanner />

    <div class="app-layout__body">
      <!-- Sidebar se agrega cuando las rutas de Inertia estén listas -->
      <main class="app-layout__main">
        <slot />
      </main>
    </div>

    <AppFooter />
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
  background-color: var(--color-surface-50);
}

.app-layout__body {
  display: flex;
  flex: 1;
}

.app-layout__main {
  flex: 1;
  padding: var(--spacing-6);
  max-width: 80rem;
  margin: 0 auto;
  width: 100%;
}
</style>
