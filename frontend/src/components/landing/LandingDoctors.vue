<script setup lang="ts">
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { getInitials, getAvatarColor } from '@/lib/mockData';
import type { PublicDoctor } from '@/types/public.types';

defineProps<{
  doctors: PublicDoctor[];
  estado: 'cargando' | 'listo' | 'error' | 'vacio' | string;
  error: string | null;
  estaVacio: boolean;
  onRetry: () => void;
}>();

const emit = defineEmits<{
  (e: 'bookDoctor', name: string): void;
}>();

function formatFee(fee: number): string {
  return new Intl.NumberFormat('es', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(fee);
}
</script>

<template>
  <section id="directorio" class="py-12 lg:py-20 bg-slate-50 border-t border-b border-salvia-cardBorder">
    <div class="px-4 max-w-5xl mx-auto mb-10">
      <div class="text-[12.5px] tracking-widest uppercase text-salvia-primary font-bold">Médicos</div>
      <h2 class="m-0 mt-3 font-serif font-normal text-3xl sm:text-4xl lg:text-5xl leading-tight tracking-tight">Perfiles verificados, formación comprobada</h2>
    </div>

    <div class="px-4 max-w-5xl mx-auto">
      <SpinnerLoader v-if="estado === 'cargando'" variant="card" :lines="6" />

      <ErrorFallback
        v-else-if="estado === 'error'"
        :message="error ?? 'Error al cargar el directorio'"
        :on-retry="onRetry"
      />

      <EmptyState
        v-else-if="estaVacio"
        message="No hay especialistas disponibles en este momento."
      />

      <div v-else-if="estado === 'listo'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <article
          v-for="doctor in doctors"
          :key="doctor.id"
          class="bg-white border border-salvia-cardBorder rounded-3xl overflow-hidden flex flex-col transition-all duration-300 hover:translate-y-[-4px] hover:shadow-lg"
        >
          <!-- Foto si existe, si no banner de iniciales -->
          <div class="h-60 flex items-center justify-center relative overflow-hidden" :style="!doctor.photo_url ? { backgroundColor: getAvatarColor(`${doctor.name} ${doctor.last_name}`) + '22' } : {}">
            <img
              v-if="doctor.photo_url"
              :src="doctor.photo_url"
              :alt="`${doctor.name} ${doctor.last_name}`"
              class="w-full h-full object-cover object-top"
            />
            <div
              v-else
              class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-lg"
              :style="{ backgroundColor: getAvatarColor(`${doctor.name} ${doctor.last_name}`) }"
            >
              {{ getInitials(`${doctor.name} ${doctor.last_name}`) }}
            </div>
            <span class="absolute top-4 right-4 bg-white/80 text-salvia-primary text-xs font-semibold px-2.5 py-1 rounded-full border border-salvia-cardBorder">
              Verificado
            </span>
          </div>

          <div class="p-5 flex flex-col gap-4 flex-1">
            <div>
              <h3 class="text-lg font-bold text-salvia-dark leading-tight">
                {{ doctor.name }} {{ doctor.last_name }}
              </h3>
              <span class="text-xs text-salvia-primary font-semibold mt-1 inline-block">{{ doctor.specialty }}</span>
            </div>

            <p class="text-sm text-salvia-secondary leading-relaxed m-0 line-clamp-2">{{ doctor.description }}</p>

            <div class="flex flex-col gap-2 text-xs text-salvia-secondary">
              <div class="flex gap-2 items-center">
                <i class="pi pi-building text-slate-400" aria-hidden="true" />
                <span>{{ doctor.university }}</span>
              </div>
              <div class="flex gap-2 items-center">
                <i class="pi pi-history text-slate-400" aria-hidden="true" />
                <span>{{ doctor.years_experience }} años de experiencia</span>
              </div>
            </div>

            <div class="mt-auto flex items-center justify-between gap-3 pt-4 border-t border-slate-100">
              <div>
                <div class="text-base font-bold text-salvia-dark">{{ formatFee(doctor.consultation_fee) }}</div>
                <div class="text-xs text-salvia-secondary mt-0.5">por consulta</div>
              </div>
              <button
                type="button"
                @click="emit('bookDoctor', doctor.name + ' ' + doctor.last_name)"
                class="inline-flex items-center gap-2 border-none bg-salvia-dark text-salvia-bg text-xs font-bold px-4 py-2.5 rounded-full hover:bg-salvia-primary transition-colors cursor-pointer"
              >
                <i class="pi pi-calendar-plus" aria-hidden="true" />
                Agendar
              </button>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>
