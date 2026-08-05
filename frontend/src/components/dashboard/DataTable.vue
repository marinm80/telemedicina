<!--
  ====================================================================
  DataTable — Lightweight data table with filtering
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="data-table-container">
    <div v-if="filters && filters.length" class="table-filters">
      <button 
        v-for="filter in filters" 
        :key="filter.key"
        class="filter-pill"
        :class="{ active: activeFilter === filter.key }"
        @click="$emit('filter-change', filter.key)"
      >
        {{ filter.label }}
        <span v-if="filter.count !== undefined" class="filter-count">({{ filter.count }})</span>
      </button>
    </div>

    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th 
              v-for="col in columns" 
              :key="col.key"
              :style="{ textAlign: col.align || 'left' }"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody v-if="rows.length > 0">
          <tr v-for="(row, index) in rows" :key="index">
            <td 
              v-for="col in columns" 
              :key="col.key"
              :style="{ textAlign: col.align || 'left' }"
            >
              <slot :name="'cell-' + col.key" :row="row" :value="row[col.key]">
                {{ row[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr>
            <td :colspan="columns.length">
              <div class="empty-state">
                <i class="pi" :class="emptyIcon" />
                <p>{{ emptyMessage }}</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Column {
  key: string;
  label: string;
  align?: 'left' | 'center' | 'right';
}

interface Filter {
  key: string;
  label: string;
  count?: number;
}

interface Props {
  columns: Column[];
  rows: Record<string, any>[];
  filters?: Filter[];
  activeFilter?: string;
  emptyIcon?: string;
  emptyMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
  emptyIcon: 'pi-inbox',
  emptyMessage: 'No hay datos para mostrar',
});

defineEmits(['filter-change']);
</script>

<style scoped>
.data-table-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
}

.table-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-pill {
  border: none;
  background-color: var(--color-surface-50, #F4F1EA);
  color: var(--color-text-subtle, #5F7A73);
  padding: 6px 16px;
  border-radius: var(--radius-full, 999px);
  font-size: var(--text-sm, 13px);
  font-weight: var(--font-medium, 500);
  cursor: pointer;
  transition: all var(--transition-fast, 0.2s) ease;
  display: flex;
  align-items: center;
  gap: 6px;
}

.filter-pill:hover {
  background-color: var(--color-surface-100, #E3EFE9);
}

.filter-pill.active {
  background-color: var(--color-text-dark, #17302B);
  color: var(--color-surface-0, #ffffff);
}

.filter-count {
  font-size: 0.9em;
  opacity: 0.8;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--color-border-warm, #EDE4D8);
  border-radius: var(--radius-lg, 12px);
  background-color: var(--color-surface-0, #ffffff);
}

.data-table {
  width: 100%;
  min-width: 600px;
  border-collapse: collapse;
}

.data-table th {
  text-transform: uppercase;
  font-size: var(--text-xs, 12px);
  letter-spacing: 0.05em;
  color: var(--color-text-muted-teal, #8FA39D);
  padding: 16px 20px;
  border-bottom: 2px solid var(--color-border-warm, #EDE4D8);
  font-weight: var(--font-semibold, 600);
}

.data-table td {
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border-warm, #EDE4D8);
  font-size: var(--text-sm, 14px);
  color: var(--color-text-dark, #17302B);
}

.data-table tbody tr {
  transition: background-color var(--transition-fast, 0.2s) ease;
}

.data-table tbody tr:hover {
  background-color: var(--color-surface-50, #FAF9F5);
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  color: var(--color-text-muted-teal, #8FA39D);
}

.empty-state i {
  font-size: 32px;
  margin-bottom: 12px;
}

.empty-state p {
  margin: 0;
  font-size: var(--text-sm, 14px);
}
</style>
