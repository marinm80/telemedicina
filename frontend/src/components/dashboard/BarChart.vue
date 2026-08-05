<!--
  ====================================================================
  BarChart — Simple bar chart using pure CSS
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="bar-chart-card">
    <div class="chart-header">
      <div class="chart-titles">
        <h3 class="chart-title">{{ title }}</h3>
        <p v-if="subtitle" class="chart-subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="total !== undefined" class="chart-total">{{ total }}</div>
    </div>
    
    <div class="chart-area">
      <div 
        v-for="(item, index) in data" 
        :key="index"
        class="chart-bar-container"
      >
        <span class="bar-value">{{ item.value }}</span>
        <div 
          class="bar-rect" 
          :style="{ height: getBarHeight(item.value), backgroundColor: color }"
        ></div>
        <span class="bar-label">{{ item.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface DataPoint {
  label: string;
  value: number;
}

interface Props {
  data: DataPoint[];
  color?: string;
  title: string;
  subtitle?: string;
  total?: number | string;
}

const props = withDefaults(defineProps<Props>(), {
  color: 'var(--color-primary-600, #0E5D52)',
});

const maxValue = computed(() => {
  if (!props.data.length) return 0;
  return Math.max(...props.data.map(d => d.value));
});

function getBarHeight(value: number) {
  if (maxValue.value === 0) return '0%';
  const percentage = (value / maxValue.value) * 100;
  return `${percentage}%`;
}
</script>

<style scoped>
.bar-chart-card {
  background-color: var(--color-surface-0, #ffffff);
  border: 1px solid var(--color-border-warm, #EDE4D8);
  border-radius: var(--radius-lg, 12px);
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.chart-title {
  margin: 0;
  font-weight: var(--font-semibold, 600);
  font-size: var(--text-base, 15px);
  color: var(--color-text-dark, #17302B);
}

.chart-subtitle {
  margin: 4px 0 0;
  font-size: var(--text-xs, 12px);
  color: var(--color-text-muted-teal, #8FA39D);
}

.chart-total {
  font-family: var(--font-heading, sans-serif);
  font-size: var(--text-2xl, 28px);
  font-weight: var(--font-bold, 700);
  color: var(--color-text-dark, #17302B);
}

.chart-area {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  min-height: 160px;
  gap: 8px;
  padding-top: 20px;
}

.chart-bar-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  height: 100%;
  flex: 1;
}

.bar-value {
  font-size: 11px;
  color: var(--color-text-muted-teal, #8FA39D);
  margin-bottom: 4px;
}

.bar-rect {
  width: 36px;
  border-radius: 6px 6px 0 0;
  transition: height var(--transition-normal, 0.3s) ease;
  min-height: 4px;
}

.bar-label {
  font-size: var(--text-xs, 12px);
  color: var(--color-text-subtle, #5F7A73);
  margin-top: 8px;
  text-align: center;
}
</style>
