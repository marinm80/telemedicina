<!--
  ====================================================================
  AlertCard — Urgent alert card
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="alert-card" :class="`severity-${severity}`">
    <div v-if="severity === 'critical'" class="pulse-ring"></div>
    
    <div class="alert-content">
      <h4 class="alert-title">{{ title }}</h4>
      <p class="alert-subtitle">{{ subtitle }}</p>
    </div>

    <div v-if="actionText" class="alert-action-container">
      <Link v-if="actionHref" :href="actionHref" class="alert-btn">
        {{ actionText }}
      </Link>
      <button v-else class="alert-btn" @click="$emit('action')">
        {{ actionText }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
  title: string;
  subtitle: string;
  actionText?: string;
  actionHref?: string;
  severity?: 'warning' | 'critical';
}

withDefaults(defineProps<Props>(), {
  severity: 'warning',
});

defineEmits(['action']);
</script>

<style scoped>
.alert-card {
  border-radius: var(--radius-lg, 12px);
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  position: relative;
  overflow: hidden;
}

.alert-card.severity-warning {
  background-color: var(--color-alert-light, #FBEAE3);
  border: 1px solid var(--color-alert, #B34A2A);
}

.alert-card.severity-critical {
  background-color: var(--color-alert-light, #FBEAE3);
  border: 2px solid var(--color-alert, #B34A2A);
}

.alert-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  z-index: 1;
}

.alert-title {
  margin: 0;
  font-size: var(--text-base, 15px);
  font-weight: var(--font-bold, 700);
  color: var(--color-alert, #B34A2A);
}

.alert-subtitle {
  margin: 0;
  font-size: var(--text-sm, 13px);
  color: var(--color-text-subtle, #5F7A73);
}

.alert-action-container {
  z-index: 1;
}

.alert-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-text-dark, #17302B);
  color: var(--color-surface-0, #ffffff);
  border: none;
  border-radius: var(--radius-full, 999px);
  padding: 8px 16px;
  font-size: var(--text-sm, 13px);
  font-weight: var(--font-medium, 500);
  cursor: pointer;
  text-decoration: none;
  transition: background-color var(--transition-fast, 0.2s) ease;
  white-space: nowrap;
}

.alert-btn:hover {
  background-color: var(--color-primary-700, #0a453d);
}

.pulse-ring {
  position: absolute;
  top: 50%;
  left: 10%;
  transform: translate(-50%, -50%);
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background-color: var(--color-alert, #B34A2A);
  opacity: 0.1;
  animation: pulse 2s infinite ease-out;
  pointer-events: none;
}

@keyframes pulse {
  0% {
    transform: translate(-50%, -50%) scale(0.5);
    opacity: 0.2;
  }
  100% {
    transform: translate(-50%, -50%) scale(1.5);
    opacity: 0;
  }
}
</style>
