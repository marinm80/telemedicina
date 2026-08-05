<!--
  ====================================================================
  AppFooter — Global application footer
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <footer class="app-footer">
    <span class="footer-text">© {{ year }} Salvia — Plataforma de Telemedicina</span>
    <span class="footer-version">v1.0.0</span>
  </footer>
</template>

<script setup lang="ts">
const year = new Date().getFullYear();
</script>

<style scoped>
.app-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  font-size: 12px;
  color: var(--color-text-muted, #8FA39D);
  border-top: 1px solid var(--color-border, #EDE4D8);
  background-color: var(--color-surface-0, #FFFFFF);
}

.footer-version {
  opacity: 0.6;
}
</style>
