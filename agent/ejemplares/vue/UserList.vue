<!--
  ====================================================================
  EJEMPLAR CANÓNICO — Componente Vue 3 (SFC, script setup, TypeScript)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================

  QUÉ FIJA ESTE EJEMPLAR
  1. Los CUATRO ESTADOS OBLIGATORIOS: cargando, vacío, error, listo.
     Un componente que solo dibuja el caso feliz está incompleto y no pasa
     el Gate 2A. Es el olvido más frecuente del código generado por IA.
  2. Cero lógica de negocio: todo delegado al composable.
  3. Props tipadas con `defineProps<T>()`, sin objetos de opciones.
  4. Cancelación de la petición al desmontar.

  ALTERNATIVA DESCARTADA
  Options API. Composition API con `script setup` es lo idiomático en Vue 3
  y es lo que hace posible extraer la lógica a composables.
-->
<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue';
import { useUsers } from './useUsers';

const props = withDefaults(
  defineProps<{
    titulo?: string;
    puedeCrear?: boolean;
  }>(),
  { titulo: 'Usuarios', puedeCrear: false },
);

const emit = defineEmits<{
  seleccionar: [id: string];
}>();

const { items, estado, error, estaVacio, cargar } = useUsers();

const controlador = new AbortController();
onMounted(() => void cargar(controlador.signal));
onBeforeUnmount(() => controlador.abort());
</script>

<template>
  <section class="rounded-lg border border-slate-200 bg-white p-4">
    <header class="mb-3 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-900">{{ props.titulo }}</h2>
      <button v-if="props.puedeCrear" type="button" class="rounded bg-teal-700 px-3 py-1 text-sm text-white">
        Nuevo
      </button>
    </header>

    <!-- 1. CARGANDO -->
    <div v-if="estado === 'cargando'" class="space-y-2" aria-busy="true">
      <div v-for="n in 3" :key="n" class="h-10 animate-pulse rounded bg-slate-100" />
    </div>

    <!-- 2. ERROR — con acción de reintento, no solo el mensaje -->
    <div v-else-if="estado === 'error'" role="alert" class="rounded border border-red-200 bg-red-50 p-3">
      <p class="text-sm text-red-800">{{ error }}</p>
      <button type="button" class="mt-2 text-sm font-medium text-red-900 underline" @click="cargar()">
        Reintentar
      </button>
    </div>

    <!-- 3. VACÍO — distinto de "cargando" y distinto de "error" -->
    <p v-else-if="estaVacio" class="py-6 text-center text-sm text-slate-500">
      Todavía no hay usuarios.
    </p>

    <!-- 4. LISTO -->
    <ul v-else class="divide-y divide-slate-100">
      <li
        v-for="user in items"
        :key="user.id"
        class="flex cursor-pointer items-center justify-between py-2 hover:bg-slate-50"
        @click="emit('seleccionar', user.id)"
      >
        <span class="text-sm font-medium text-slate-900">{{ user.name }}</span>
        <span class="text-xs text-slate-500">{{ user.email }}</span>
      </li>
    </ul>
  </section>
</template>
