<!--
  ====================================================================
  DemoBanner — Shows a banner when running in demo/dev environment
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div v-if="isDemo" class="demo-banner">
    <i class="pi pi-info-circle"></i>
    <span>Entorno de demostración — Los datos mostrados son ficticios.</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const isDemo = computed(() => {
  return import.meta.env.DEV || import.meta.env.VITE_DEMO === 'true';
});
</script>

<style scoped>
.demo-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 6px 16px;
  background-color: var(--color-alert-bg, #FBEAE3);
  color: var(--color-alert-dark, #B34A2A);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-align: center;
  z-index: 1000;
}

.demo-banner i {
  font-size: 14px;
}
</style>
