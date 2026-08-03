<!--
  ====================================================================
  LandingLayout — Layout para páginas públicas sin sesión
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  NO reutiliza AppLayout. No hay sidebar, no hay navegación de usuario.
  Solo DemoBanner + contenido + AppFooter.
-->
<script setup lang="ts">
import DemoBanner from './DemoBanner.vue';
import AppFooter from './AppFooter.vue';
</script>

<template>
  <div class="landing-layout">
    <DemoBanner />
    <main class="landing-layout__main">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>

<style scoped>
.landing-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: var(--color-surface-50);
}

.landing-layout__main {
  flex: 1;
}
</style>
