<!--
  ====================================================================
  AssistantWidget — AI assistant dark card
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="assistant-card">
    <div class="assistant-header">
      <i class="pi pi-sparkles assistant-icon"></i>
      <h3 class="assistant-title">Asistente Salvia</h3>
    </div>
    
    <div class="assistant-message">
      {{ message }}
    </div>

    <div v-if="actions && actions.length" class="assistant-actions">
      <template v-for="(action, index) in actions" :key="index">
        <Link 
          v-if="action.href" 
          :href="action.href" 
          class="assistant-action"
        >
          {{ action.text }}
        </Link>
        <button 
          v-else-if="action.emit" 
          @click="$emit(action.emit)" 
          class="assistant-action"
        >
          {{ action.text }}
        </button>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Action {
  text: string;
  href?: string;
  emit?: string;
}

interface Props {
  message: string;
  actions?: Action[];
}

defineProps<Props>();
</script>

<style scoped>
.assistant-card {
  background-color: var(--color-primary-600, #0E5D52);
  border-radius: var(--radius-lg, 12px);
  padding: 24px;
  color: var(--color-surface-0, #ffffff);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.assistant-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.assistant-icon {
  font-size: 18px;
  color: var(--color-accent, #8FC9B3);
}

.assistant-title {
  margin: 0;
  font-size: var(--text-base, 16px);
  font-weight: var(--font-bold, 700);
}

.assistant-message {
  font-size: var(--text-sm, 14px);
  color: rgba(255, 255, 255, 0.85);
  line-height: 1.6;
}

.assistant-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 8px;
}

.assistant-action {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-accent, #8FC9B3);
  font-size: var(--text-sm, 13px);
  font-weight: var(--font-semibold, 600);
  cursor: pointer;
  text-decoration: none;
  transition: opacity var(--transition-fast, 0.2s) ease;
}

.assistant-action:hover {
  text-decoration: underline;
  opacity: 0.9;
}
</style>
