<script setup lang="ts">
const emit = defineEmits<{
  (e: 'selectSpecialty', name: string): void;
}>();

const specialties = [
  { name: 'Cardiología', icon: '🫀', count: 14, from: '45 US$' },
  { name: 'Medicina Interna', icon: '🩺', count: 22, from: '38 US$' },
  { name: 'Dermatología', icon: '🧴', count: 11, from: '40 US$' },
  { name: 'Pediatría', icon: '🧒', count: 18, from: '35 US$' },
  { name: 'Neurología', icon: '🧠', count: 9, from: '52 US$' },
  { name: 'Psiquiatría', icon: '🌱', count: 12, from: '48 US$' },
  { name: 'Traumatología', icon: '🦴', count: 10, from: '42 US$' },
  { name: 'Oftalmología', icon: '👁️', count: 7, from: '44 US$' }
];
</script>

<template>
  <section id="especialidades" class="py-12 lg:py-20">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-8 px-4 max-w-5xl mx-auto">
      <div>
        <div class="text-[12.5px] tracking-widest uppercase text-salvia-primary font-bold">Especialidades</div>
        <h2 class="m-0 mt-3 font-serif font-normal text-3xl sm:text-4xl lg:text-5xl leading-tight tracking-tight">Doce áreas, un mismo asistente</h2>
      </div>
      <p class="m-0 max-w-[34ch] text-sm lg:text-base leading-relaxed text-salvia-secondary">
        Pulsa una especialidad y la recepción te dice cuántos médicos hay, horarios y precio de consulta.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 px-4 max-w-5xl mx-auto">
      <button
        v-for="s in specialties"
        :key="s.name"
        type="button"
        @click="emit('selectSpecialty', s.name)"
        class="text-left font-family-inherit cursor-pointer bg-white border border-salvia-cardBorder rounded-2xl p-5 flex flex-col gap-4 transition-all duration-300 hover:translate-y-[-3px] hover:shadow-lg hover:border-emerald-100"
      >
        <div class="w-11 h-11 rounded-xl bg-salvia-badge flex items-center justify-center text-xl">{{ s.icon }}</div>
        <div>
          <div class="text-base font-bold tracking-tight">{{ s.name }}</div>
          <div class="text-xs text-salvia-secondary mt-1">{{ s.count }} médicos</div>
        </div>
        <div class="text-[12.5px] font-semibold text-salvia-primary">Desde {{ s.from }}</div>
      </button>
    </div>
  </section>
</template>
