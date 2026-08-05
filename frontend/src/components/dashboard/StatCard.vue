<!--
  ====================================================================
  StatCard — KPI stat card component
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="stat-card">
    <div class="stat-icon-wrapper" :style="{ backgroundColor: iconBg }">
      <i class="pi" :class="icon" />
    </div>
    <div class="stat-content">
      <div class="stat-label">{{ label }}</div>
      <div class="stat-value">{{ value }}</div>
      <div v-if="trend" class="stat-trend" :class="trendClass">
        <i class="pi" :class="trendIcon" />
        {{ trend }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  icon: string;
  label: string;
  value: string | number;
  trend?: string;
  trendType?: 'positive' | 'negative' | 'neutral';
  iconBg?: string;
}

const props = withDefaults(defineProps<Props>(), {
  trendType: 'neutral',
  iconBg: 'var(--color-surface-100, #E3EFE9)',
});

const trendClass = computed(() => `trend-${props.trendType}`);
const trendIcon = computed(() => {
  if (props.trendType === 'positive') return 'pi-arrow-up';
  if (props.trendType === 'negative') return 'pi-arrow-down';
  return 'pi-minus';
});
</script>

<style scoped>
.stat-card {
  background-color: var(--color-surface-0, #ffffff);
  border: 1px solid var(--color-border-warm, #EDE4D8);
  border-radius: var(--radius-lg, 12px);
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 20px;
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon-wrapper i {
  font-size: 24px;
  color: var(--color-primary-600, #0E5D52);
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-label {
  text-transform: uppercase;
  font-size: var(--text-xs, 12px);
  letter-spacing: 0.05em;
  color: var(--color-text-muted-teal, #8FA39D);
}

.stat-value {
  font-family: var(--font-heading, sans-serif);
  font-size: var(--text-3xl, 36px);
  color: var(--color-text-dark, #17302B);
  font-weight: var(--font-bold, 700);
  line-height: 1.2;
}

.stat-trend {
  font-size: var(--text-sm, 13px);
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.stat-trend i {
  font-size: 10px;
}

.trend-positive { color: var(--color-success-600, #2E9E6B); }
.trend-negative { color: var(--color-error-600, #B34A2A); }
.trend-neutral { color: var(--color-text-subtle, #5F7A73); }
</style>
