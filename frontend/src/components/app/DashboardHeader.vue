<!--
  ====================================================================
  DashboardHeader — Reusable header for dashboard pages
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <header class="dashboard-header">
    <div class="dashboard-header__content">
      <div class="dashboard-header__text">
        <div v-if="eyebrow" class="dashboard-header__eyebrow">{{ eyebrow }}</div>
        <h1 class="dashboard-header__title">{{ title }}</h1>
        <p v-if="subtitle" class="dashboard-header__subtitle">{{ subtitle }}</p>
      </div>

      <div class="dashboard-header__actions">
        <div v-if="statusText" class="dashboard-header__status">
          <span v-if="statusDot" class="status-dot"></span>
          {{ statusText }}
        </div>

        <button 
          v-if="actionText && !actionHref" 
          class="dashboard-header__btn"
          @click="$emit('action-click')"
        >
          {{ actionText }}
        </button>

        <Link 
          v-if="actionText && actionHref" 
          :href="actionHref"
          class="dashboard-header__btn"
        >
          {{ actionText }}
        </Link>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

interface Props {
  eyebrow?: string
  title: string
  subtitle?: string
  statusText?: string
  statusDot?: boolean
  actionText?: string
  actionHref?: string
}

withDefaults(defineProps<Props>(), {
  statusDot: true
})

defineEmits(['action-click'])
</script>

<style scoped>
.dashboard-header {
  margin-bottom: var(--spacing-8, 2rem);
}

.dashboard-header__content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4, 1rem);
}

@media (min-width: 768px) {
  .dashboard-header__content {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.dashboard-header__text {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1, 0.25rem);
}

.dashboard-header__eyebrow {
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 13px;
  color: var(--color-primary-600, #0E5D52);
  font-weight: 700;
  margin-bottom: var(--spacing-1, 0.25rem);
}

.dashboard-header__title {
  font-family: var(--font-heading, sans-serif);
  font-size: clamp(1.5rem, 3vw, 2.25rem);
  color: var(--color-text-dark, #17302B);
  margin: 0;
  line-height: 1.2;
}

.dashboard-header__subtitle {
  color: var(--color-text-muted-teal, #5F7A73);
  max-width: 60ch;
  margin: 0;
  font-size: var(--text-base, 1rem);
}

.dashboard-header__actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-4, 1rem);
  flex-wrap: wrap;
}

.dashboard-header__status {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2, 0.5rem);
  background-color: var(--color-surface-0, #FFFFFF);
  border: 1px solid var(--color-border-warm, #E6E1DA);
  padding: var(--spacing-1, 0.25rem) var(--spacing-3, 0.75rem);
  border-radius: var(--radius-full, 9999px);
  font-size: var(--text-sm, 0.875rem);
  font-weight: 500;
  color: var(--color-text-dark, #17302B);
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-success-500, #22C55E); /* Or a specific variable if available */
}

.dashboard-header__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: var(--color-text-dark, #17302B);
  color: var(--color-surface-50, #FAF5EE);
  padding: var(--spacing-2, 0.5rem) var(--spacing-5, 1.25rem);
  border-radius: var(--radius-full, 9999px);
  font-size: var(--text-sm, 0.875rem);
  font-weight: 600;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: background-color var(--transition-fast, 0.2s);
}

.dashboard-header__btn:hover {
  background-color: var(--color-primary-600, #0E5D52);
}
</style>
