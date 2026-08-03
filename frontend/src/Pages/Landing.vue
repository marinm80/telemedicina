<!--
  ====================================================================
  Landing — Página pública sin sesión
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  Tres secciones: Hero + Directorio de Especialistas + Agente (placeholder)
  Layout propio (LandingLayout), no usa AppLayout.
-->
<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useAppState } from '@/composables/useAppState';
import { mockPublicDoctors, getInitials, getAvatarColor } from '@/lib/mockData';
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

function scrollToDirectory() {
  document.getElementById('directorio')?.scrollIntoView({ behavior: 'smooth' });
}

function formatFee(fee: number): string {
  return new Intl.NumberFormat('es', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(fee);
}

const SPECIALTIES = computed(() => {
  const set = new Set(doctors.value.map((d) => d.specialty));
  return [...set].sort();
});
</script>

<template>
  <LandingLayout>
    <!-- ===== HERO ===== -->
    <section class="hero">
      <div class="hero__content">
        <h1 class="hero__title">
          Plataforma de
          <span class="hero__title--accent">Telemedicina</span>
        </h1>
        <p class="hero__subtitle">
          Consultas médicas seguras, privadas y accesibles desde cualquier lugar.
          Conectamos pacientes con especialistas certificados en tiempo real.
        </p>
        <div class="hero__actions">
          <button type="button" class="hero__cta hero__cta--primary" @click="scrollToDirectory">
            <i class="pi pi-users" aria-hidden="true" />
            Ver Especialistas
          </button>
          <button type="button" class="hero__cta hero__cta--secondary" disabled>
            <i class="pi pi-sign-in" aria-hidden="true" />
            Iniciar Sesión
          </button>
        </div>
        <div class="hero__badges">
          <span class="hero__badge">
            <i class="pi pi-shield" aria-hidden="true" />
            WCAG AA
          </span>
          <span class="hero__badge">
            <i class="pi pi-lock" aria-hidden="true" />
            Datos encriptados
          </span>
          <span class="hero__badge">
            <i class="pi pi-verified" aria-hidden="true" />
            Notas firmadas SHA-256
          </span>
        </div>
      </div>
    </section>

    <!-- ===== DIRECTORIO DE ESPECIALISTAS ===== -->
    <section id="directorio" class="directory-section">
      <div class="directory-section__container">
        <h2 class="directory-section__title">Nuestros Especialistas</h2>
        <p class="directory-section__subtitle">
          Profesionales certificados listos para atenderte
        </p>

        <SpinnerLoader v-if="estado === 'cargando'" variant="card" :lines="6" />

        <ErrorFallback
          v-else-if="estado === 'error'"
          :message="error ?? 'Error al cargar el directorio'"
          :on-retry="() => cargar()"
        />

        <EmptyState
          v-else-if="estaVacio"
          message="No hay especialistas disponibles en este momento."
        />

        <div v-else-if="estado === 'listo'" class="directory-section__grid">
          <article v-for="doctor in doctors" :key="doctor.id" class="doc-card">
            <div class="doc-card__header">
              <div
                class="doc-card__avatar"
                :style="{ backgroundColor: getAvatarColor(`${doctor.name} ${doctor.last_name}`) }"
              >
                {{ getInitials(`${doctor.name} ${doctor.last_name}`) }}
              </div>
              <div class="doc-card__identity">
                <h3 class="doc-card__name">
                  {{ doctor.name }} {{ doctor.last_name }}
                </h3>
                <span class="doc-card__specialty">{{ doctor.specialty }}</span>
              </div>
            </div>

            <p class="doc-card__description">{{ doctor.description }}</p>

            <div class="doc-card__details">
              <div class="doc-card__detail">
                <i class="pi pi-building" aria-hidden="true" />
                <span>{{ doctor.university }}</span>
              </div>
              <div class="doc-card__detail">
                <i class="pi pi-history" aria-hidden="true" />
                <span>{{ doctor.years_experience }} años de experiencia</span>
              </div>
            </div>

            <div class="doc-card__footer">
              <span class="doc-card__fee">
                {{ formatFee(doctor.consultation_fee) }}
                <small>/ consulta</small>
              </span>
              <button type="button" class="doc-card__cta" disabled>
                Ver disponibilidad
              </button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ===== AGENTE CONVERSACIONAL (placeholder) ===== -->
    <section class="agent-section">
      <div class="agent-section__container">
        <div class="agent-card">
          <div class="agent-card__icon-wrapper">
            <i class="pi pi-comments agent-card__icon" aria-hidden="true" />
          </div>
          <h2 class="agent-card__title">Asistente Virtual</h2>
          <p class="agent-card__text">
            Próximamente podrás consultar con nuestro asistente inteligente para
            orientarte sobre especialidades, horarios y preparación para tu cita.
          </p>
          <span class="agent-card__badge">Próximamente</span>
          <button type="button" class="agent-card__cta" disabled>
            <i class="pi pi-comments" aria-hidden="true" />
            Iniciar conversación
          </button>
        </div>
      </div>
    </section>
  </LandingLayout>
</template>

<style scoped>
/* ===== HERO ===== */
.hero {
  background: linear-gradient(135deg, var(--color-primary-700) 0%, var(--color-primary-900) 100%);
  color: var(--color-surface-0);
  padding: var(--spacing-6) var(--spacing-4);
  text-align: center;
}

@media (min-width: 768px) {
  .hero { padding: 5rem var(--spacing-6); }
}

.hero__content {
  max-width: 48rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-5);
}

.hero__title {
  font-family: var(--font-heading);
  font-size: var(--text-3xl);
  font-weight: var(--font-bold);
  line-height: var(--leading-tight);
  margin: 0;
}

@media (min-width: 768px) {
  .hero__title { font-size: var(--text-4xl); }
}

.hero__title--accent {
  display: block;
  color: var(--color-primary-50);
}

.hero__subtitle {
  font-size: var(--text-lg);
  line-height: var(--leading-relaxed);
  color: var(--color-primary-100);
  max-width: 36rem;
  margin: 0;
}

.hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-3);
  justify-content: center;
}

.hero__cta {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-5);
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--text-base);
  font-weight: var(--font-semibold);
  font-family: var(--font-body);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.hero__cta--primary {
  background-color: var(--color-surface-0);
  color: var(--color-primary-700);
}

.hero__cta--primary:hover {
  background-color: var(--color-primary-50);
}

.hero__cta--secondary {
  background-color: transparent;
  color: var(--color-surface-0);
  border: 1px solid var(--color-primary-100);
}

.hero__cta--secondary:hover:not(:disabled) {
  background-color: rgba(255, 255, 255, 0.1);
}

.hero__cta:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.hero__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

.hero__badges {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-3);
  justify-content: center;
  margin-top: var(--spacing-2);
}

.hero__badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px var(--spacing-3);
  background-color: rgba(255, 255, 255, 0.15);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  color: var(--color-primary-50);
}

/* ===== DIRECTORIO ===== */
.directory-section {
  padding: 4rem var(--spacing-4);
  background-color: var(--color-surface-0);
}

.directory-section__container {
  max-width: 72rem;
  margin: 0 auto;
}

.directory-section__title {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  text-align: center;
  margin: 0 0 var(--spacing-1);
}

.directory-section__subtitle {
  font-size: var(--text-base);
  color: var(--color-text-muted);
  text-align: center;
  margin: 0 0 var(--spacing-6);
}

.directory-section__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-4);
}

@media (min-width: 640px) {
  .directory-section__grid { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
  .directory-section__grid { grid-template-columns: repeat(3, 1fr); }
}

/* ===== TARJETA DE DOCTOR ===== */
.doc-card {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
  padding: var(--spacing-5);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.doc-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
}

.doc-card__header {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
}

.doc-card__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-full);
  color: var(--color-surface-0);
  font-size: var(--text-lg);
  font-weight: var(--font-bold);
  flex-shrink: 0;
}

.doc-card__identity {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.doc-card__name {
  font-family: var(--font-heading);
  font-size: var(--text-base);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  margin: 0;
}

.doc-card__specialty {
  display: inline-block;
  padding: 2px var(--spacing-2);
  background-color: var(--color-primary-50);
  color: var(--color-primary-700);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  align-self: flex-start;
}

.doc-card__description {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-normal);
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.doc-card__details {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.doc-card__detail {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
}

.doc-card__detail i {
  font-size: var(--text-xs);
  color: var(--color-primary-500);
}

.doc-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: var(--spacing-3);
  border-top: 1px solid var(--color-surface-200);
}

.doc-card__fee {
  font-size: var(--text-base);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
}

.doc-card__fee small {
  font-size: var(--text-xs);
  font-weight: var(--font-regular);
  color: var(--color-text-subtle);
}

.doc-card__cta {
  padding: var(--spacing-2) var(--spacing-3);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.doc-card__cta:hover:not(:disabled) {
  background-color: var(--color-primary-600);
}

.doc-card__cta:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.doc-card__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* ===== AGENTE (placeholder) ===== */
.agent-section {
  padding: 4rem var(--spacing-4);
  background-color: var(--color-surface-50);
}

.agent-section__container {
  max-width: 36rem;
  margin: 0 auto;
}

.agent-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-4);
  padding: var(--spacing-6);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  text-align: center;
}

.agent-card__icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 4rem;
  height: 4rem;
  border-radius: var(--radius-full);
  background-color: var(--color-primary-50);
}

.agent-card__icon {
  font-size: var(--text-2xl);
  color: var(--color-primary-700);
}

.agent-card__title {
  font-family: var(--font-heading);
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.agent-card__text {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-normal);
  max-width: 28rem;
  margin: 0;
}

.agent-card__badge {
  display: inline-flex;
  align-items: center;
  padding: 4px var(--spacing-3);
  background-color: var(--color-warning-50);
  color: var(--color-warning-800);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
}

.agent-card__cta {
  padding: var(--spacing-2) var(--spacing-4);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.agent-card__cta:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.agent-card__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}
</style>
