<!--
  ====================================================================
  Landing — Página pública sin sesión (Salvia redesign)
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  RF-23 Asistente Informativo (Landing)
  ====================================================================
-->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import LandingHero from '@/components/landing/LandingHero.vue';
import LandingBenefits from '@/components/landing/LandingBenefits.vue';
import LandingSpecialties from '@/components/landing/LandingSpecialties.vue';
import LandingDoctors from '@/components/landing/LandingDoctors.vue';
import LandingHowItWorks from '@/components/landing/LandingHowItWorks.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import { useAppState } from '@/composables/useAppState';
import { mockPublicDoctors } from '@/lib/mockData';
import type { PublicDoctor } from '@/types/public.types';

const fetcher = async (signal: AbortSignal): Promise<PublicDoctor[]> => {
  await new Promise((resolve) => setTimeout(resolve, 1000));
  signal.throwIfAborted();
  return [...mockPublicDoctors];
};

const { items: doctors, estado, error, estaVacio, cargar } = useAppState<PublicDoctor>(fetcher);
const controller = new AbortController();

onMounted(() => {
  cargar(controller.signal);
});

onUnmounted(() => {
  controller.abort();
});

const heroRef = ref<InstanceType<typeof LandingHero> | null>(null);

function handleSelectSpecialty(name: string) {
  // Trigger specialty query on the chatbot
  heroRef.value?.fromSpecialty(name);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function handleBookDoctor(name: string) {
  // Trigger booking flow on the chatbot
  heroRef.value?.fromSpecialty(name);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function scrollToDirectory() {
  document.getElementById('directorio')?.scrollIntoView({ behavior: 'smooth' });
}
</script>

<template>
  <LandingLayout>
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10">
      <!-- T-002: LandingHero component (contains the chatbot Reception) -->
      <LandingHero
        ref="heroRef"
        @selectSpecialty="handleSelectSpecialty"
        @scrollToDirectory="scrollToDirectory"
      />

      <!-- T-003: LandingBenefits component -->
      <LandingBenefits />

      <!-- T-004: LandingSpecialties component -->
      <LandingSpecialties @selectSpecialty="handleSelectSpecialty" />

      <!-- T-005: LandingDoctors component -->
      <LandingDoctors
        :doctors="doctors"
        :estado="estado"
        :error="error"
        :esta-vacio="estaVacio"
        :on-retry="() => cargar()"
        @book-doctor="handleBookDoctor"
      />

      <!-- T-006: LandingHowItWorks component -->
      <LandingHowItWorks />

      <!-- T-007: LandingFooter component -->
      <LandingFooter />
    </div>
  </LandingLayout>
</template>
