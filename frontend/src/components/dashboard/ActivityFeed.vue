<!--
  ====================================================================
  ActivityFeed — Timeline activity feed component
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="activity-card">
    <h3 class="activity-title">Actividad reciente</h3>
    
    <div class="activity-feed">
      <div 
        v-for="(item, index) in limitedItems" 
        :key="index"
        class="activity-item"
      >
        <div class="activity-indicator">
          <div class="activity-dot"></div>
          <div v-if="index !== limitedItems.length - 1" class="activity-line"></div>
        </div>
        
        <div class="activity-content">
          <p class="activity-text">{{ item.text }}</p>
          <span class="activity-time">{{ item.time }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface ActivityItem {
  text: string;
  time: string;
}

interface Props {
  items: ActivityItem[];
}

const props = defineProps<Props>();

const limitedItems = computed(() => {
  return props.items.slice(0, 5);
});
</script>

<style scoped>
.activity-card {
  background-color: var(--color-surface-0, #ffffff);
  border: 1px solid var(--color-border-warm, #EDE4D8);
  border-radius: var(--radius-lg, 12px);
  padding: 20px;
}

.activity-title {
  margin: 0 0 20px 0;
  font-size: var(--text-base, 15px);
  font-weight: var(--font-semibold, 600);
  color: var(--color-text-dark, #17302B);
}

.activity-feed {
  display: flex;
  flex-direction: column;
}

.activity-item {
  display: flex;
  gap: 16px;
}

.activity-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 12px;
  flex-shrink: 0;
}

.activity-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-accent, #8FC9B3);
  margin-top: 4px;
}

.activity-line {
  width: 2px;
  background-color: var(--color-border-warm, #EDE4D8);
  flex-grow: 1;
  min-height: 24px;
  margin-top: 4px;
  margin-bottom: 4px;
}

.activity-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-bottom: 16px;
}

.activity-item:last-child .activity-content {
  padding-bottom: 0;
}

.activity-text {
  margin: 0;
  font-size: var(--text-sm, 14px);
  color: var(--color-text-dark, #17302B);
  line-height: 1.4;
}

.activity-time {
  font-size: var(--text-xs, 12px);
  color: var(--color-text-muted-teal, #8FA39D);
}
</style>
