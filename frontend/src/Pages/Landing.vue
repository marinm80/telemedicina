<!--
  ====================================================================
  Landing — Página pública sin sesión
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  Diseño orientado a portafolio: Hero con asistente AI + paciente,
  dos CTAs (asistente vs tradicional), tarjetas de beneficios,
  directorio de especialistas.
-->
<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import PublicAssistant from '@/components/PublicAssistant.vue';
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
</script>

<template>
  <LandingLayout>
    <!-- ===== HERO ===== -->
    <section class="hero">
      <div class="hero__inner">
        <!-- Ilustración médica (izquierda) -->
        <div class="hero__visual-left">
          <img
            src="/images/hero-doctor.jpg"
            alt="Doctora profesional con tablet de gestión médica"
            class="hero__visual-left-img"
            width="280"
            height="370"
          />
        </div>

        <!-- Contenido central -->
        <div class="hero__content">
          <h1 class="hero__title">
            RESERVA TU CITA MÉDICA EN MINUTOS CON NUESTRO
            <span class="hero__title--accent">ASISTENTE INTELIGENTE.</span>
          </h1>
          <p class="hero__subtitle">
            Nuestro sistema agéntico simplifica y agiliza el proceso para que
            encuentres al médico adecuado, rápido.
          </p>
          <div class="hero__actions">
            <button type="button" class="hero__cta hero__cta--primary" @click="scrollToDirectory">
              <i class="pi pi-bolt" aria-hidden="true" />
              Empezar Reservación con Asistente
            </button>
            <button type="button" class="hero__cta hero__cta--outline" @click="scrollToDirectory">
              <i class="pi pi-search" aria-hidden="true" />
              Buscar Métodos Tradicionales
            </button>
          </div>
        </div>

        <!-- Ilustración paciente (derecha) -->
        <div class="hero__illustration">
          <img
            src="/images/hero-patient.jpg"
            alt="Paciente reservando una cita médica desde su teléfono"
            class="hero__illustration-img"
            width="320"
            height="320"
          />
          <span class="hero__agentic-badge">
            <i class="pi pi-microchip-ai" aria-hidden="true" />
            agentic
          </span>
        </div>
      </div>
    </section>

    <!-- ===== BENEFICIOS ===== -->
    <section class="benefits">
      <div class="benefits__inner">
        <article class="benefit-card">
          <div class="benefit-card__icon-wrap benefit-card__icon-wrap--teal">
            <i class="pi pi-clock" aria-hidden="true" />
          </div>
          <h3 class="benefit-card__title">Atención 24/7</h3>
          <p class="benefit-card__text">Tu asistente siempre disponible</p>
        </article>

        <article class="benefit-card">
          <div class="benefit-card__icon-wrap benefit-card__icon-wrap--green">
            <i class="pi pi-search" aria-hidden="true" />
          </div>
          <h3 class="benefit-card__title">Selección Inteligente</h3>
          <p class="benefit-card__text">Encuentra el especialista ideal para tus síntomas</p>
        </article>

        <article class="benefit-card">
          <div class="benefit-card__icon-wrap benefit-card__icon-wrap--mint">
            <i class="pi pi-sync" aria-hidden="true" />
          </div>
          <h3 class="benefit-card__title">Sincronización Instantánea</h3>
          <p class="benefit-card__text">Recordatorios, cambios y confirmaciones al instante</p>
        </article>
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

    <!-- ===== ASISTENTE INFORMATIVO — estado vacío honesto ===== -->
    <!--
      Este es el LUGAR donde irá el asistente informativo.
      NO simula conversación. Un asistente falso que parece real
      es peor que un espacio declarado.

      Cuando exista, el asistente:
      - Explica las especialidades disponibles
      - Guía al visitante hacia el registro
      - Informa que para agendar hay que iniciar sesión
      - NO escribe nada — no es un chatbot clínico
    -->
    <section class="agent-section">
      <div class="agent-section__container">
        <div class="agent-card">
          <div class="agent-card__icon-wrapper">
            <i class="pi pi-info-circle agent-card__icon" aria-hidden="true" />
          </div>
          <h2 class="agent-card__title">Asistente Informativo</h2>
          <p class="agent-card__text">
            Aquí podrás consultar información sobre nuestras especialidades,
            horarios de atención y cómo prepararte para tu cita.
          </p>
          <ul class="agent-card__list">
            <li>
              <i class="pi pi-check" aria-hidden="true" />
              Orientación sobre especialidades médicas
            </li>
            <li>
              <i class="pi pi-check" aria-hidden="true" />
              Guía para crear tu cuenta
            </li>
            <li>
              <i class="pi pi-check" aria-hidden="true" />
              Información sobre el proceso de reserva
            </li>
          </ul>
          <div class="agent-card__empty">
            <span class="agent-card__badge">En desarrollo</span>
            <p class="agent-card__notice">
              Para agendar una cita, <a href="/register" class="agent-card__link">crea tu cuenta</a>
              o <a href="/login" class="agent-card__link">inicia sesión</a>.
            </p>
          </div>
        </div>
      </div>
    </section>
  </LandingLayout>

  <!-- RF-23 Asistente Informativo (Landing) -->
  <PublicAssistant />
</template>

<style scoped>
/* ===== HERO ===== */
.hero {
  background: linear-gradient(135deg, #eaf6f6 0%, #f0faf5 40%, #f5f9f0 100%);
  padding: var(--spacing-6) var(--spacing-4);
  overflow: hidden;
  position: relative;
}

.hero__inner {
  display: grid;
  grid-template-columns: 1fr 1.4fr 1fr;
  align-items: center;
  gap: var(--spacing-5);
  max-width: 72rem;
  margin: 0 auto;
}

/* Left visual column */
.hero__visual-left {
  display: flex;
  align-items: center;
  justify-content: center;
}

.hero__visual-left-img {
  width: 100%;
  max-width: 16rem;
  height: auto;
  border-radius: var(--radius-xl);
  object-fit: cover;
  filter: drop-shadow(0 8px 24px rgba(0, 128, 128, 0.15));
}

/* Content column */
.hero__content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.hero__title {
  font-family: var(--font-heading);
  font-size: clamp(1.5rem, 3vw, 2.25rem);
  font-weight: var(--font-black);
  color: var(--color-text-strong);
  line-height: var(--leading-tight);
  margin: 0;
  letter-spacing: -0.02em;
}

.hero__title--accent {
  color: var(--color-primary-700);
}

.hero__subtitle {
  font-size: var(--text-base);
  color: var(--color-text-muted);
  line-height: var(--leading-relaxed);
  margin: 0;
  max-width: 36rem;
}

.hero__actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
  max-width: 24rem;
}

.hero__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-5);
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  font-family: var(--font-body);
  border-radius: var(--radius-full);
  cursor: pointer;
  transition: all var(--transition-fast);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hero__cta--primary {
  background: linear-gradient(135deg, var(--color-primary-700) 0%, var(--color-primary-600) 100%);
  color: var(--color-surface-0);
  border: none;
  box-shadow: 0 4px 14px rgba(0, 128, 128, 0.3);
}

.hero__cta--primary:hover {
  box-shadow: 0 6px 20px rgba(0, 128, 128, 0.4);
  transform: translateY(-1px);
}

.hero__cta--outline {
  background: transparent;
  color: var(--color-text-strong);
  border: 2px solid var(--color-text-strong);
}

.hero__cta--outline:hover {
  background-color: var(--color-surface-100);
}

.hero__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* Illustration column */
.hero__illustration {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}

.hero__illustration-img {
  width: 100%;
  max-width: 18rem;
  height: auto;
  border-radius: var(--radius-lg);
  object-fit: cover;
  filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.1));
}

.hero__agentic-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-1);
  padding: var(--spacing-1) var(--spacing-3);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-bold);
  letter-spacing: 0.05em;
  margin-top: var(--spacing-2);
  box-shadow: 0 2px 8px rgba(0, 128, 128, 0.3);
}

/* Responsive hero */
@media (max-width: 900px) {
  .hero__inner {
    grid-template-columns: 1fr;
    text-align: center;
  }

  .hero__visual-left { order: 1; }
  .hero__content { order: 2; align-items: center; }
  .hero__illustration { order: 3; }
  .hero__actions { align-items: center; }
}

/* ===== BENEFITS ===== */
.benefits {
  padding: var(--spacing-6) var(--spacing-4);
  background-color: var(--color-surface-0);
}

.benefits__inner {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-4);
  max-width: 64rem;
  margin: 0 auto;
}

.benefit-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-5) var(--spacing-4);
  background-color: var(--color-surface-50);
  border-radius: var(--radius-xl);
  text-align: center;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.benefit-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
}

.benefit-card__icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-full);
  font-size: var(--text-xl);
}

.benefit-card__icon-wrap--teal {
  background-color: rgba(0, 128, 128, 0.12);
  color: var(--color-primary-700);
}

.benefit-card__icon-wrap--green {
  background-color: rgba(34, 139, 34, 0.12);
  color: #228B22;
}

.benefit-card__icon-wrap--mint {
  background-color: rgba(46, 204, 113, 0.12);
  color: #2ECC71;
}

.benefit-card__title {
  font-family: var(--font-heading);
  font-size: var(--text-base);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.benefit-card__text {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
  line-height: var(--leading-relaxed);
}

@media (max-width: 700px) {
  .benefits__inner { grid-template-columns: 1fr; }
}

/* ===== DIRECTORIO ===== */
.directory-section {
  padding: var(--spacing-8) var(--spacing-4);
  background-color: var(--color-surface-50);
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
  margin: 0 0 var(--spacing-2) 0;
}

.directory-section__subtitle {
  font-size: var(--text-base);
  color: var(--color-text-muted);
  text-align: center;
  margin: 0 0 var(--spacing-6) 0;
}

.directory-section__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
  gap: var(--spacing-4);
}

/* Doctor card */
.doc-card {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
  padding: var(--spacing-4);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  transition: all var(--transition-fast);
}

.doc-card:hover {
  border-color: var(--color-primary-500);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
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
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-full);
  color: var(--color-surface-0);
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  flex-shrink: 0;
}

.doc-card__identity {
  display: flex;
  flex-direction: column;
}

.doc-card__name {
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.doc-card__specialty {
  font-size: var(--text-xs);
  color: var(--color-primary-700);
  font-weight: var(--font-medium);
}

.doc-card__description {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-relaxed);
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

.doc-card__detail i { font-size: var(--text-xs); }

.doc-card__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: var(--spacing-3);
  border-top: 1px solid var(--color-surface-100);
  margin-top: auto;
}

.doc-card__fee {
  font-size: var(--text-base);
  font-weight: var(--font-bold);
  color: var(--color-primary-700);
}

.doc-card__fee small {
  font-weight: var(--font-normal);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.doc-card__cta {
  padding: var(--spacing-2) var(--spacing-3);
  background-color: var(--color-primary-700);
  color: var(--color-surface-0);
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  font-family: var(--font-body);
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.doc-card__cta:hover:not(:disabled) { background-color: var(--color-primary-600); }
.doc-card__cta:disabled { opacity: 0.6; cursor: not-allowed; }
.doc-card__cta:focus-visible {
  outline: 2px solid var(--color-focus-ring);
  outline-offset: 2px;
}

/* ===== AGENT SECTION ===== */
.agent-section {
  padding: var(--spacing-6) var(--spacing-4);
  background-color: var(--color-surface-0);
}

.agent-section__container {
  max-width: 40rem;
  margin: 0 auto;
}

.agent-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-5);
  background-color: var(--color-surface-50);
  border: 1px dashed var(--color-surface-200);
  border-radius: var(--radius-lg);
  text-align: center;
}

.agent-card__icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  background-color: var(--color-primary-50);
  border-radius: var(--radius-full);
}

.agent-card__icon {
  font-size: 1.5rem;
  color: var(--color-primary-700);
}

.agent-card__title {
  font-family: var(--font-heading);
  font-size: var(--text-lg);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  margin: 0;
}

.agent-card__text {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-relaxed);
  margin: 0;
}

.agent-card__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
  text-align: left;
  width: 100%;
}

.agent-card__list li {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-sm);
  color: var(--color-text-strong);
}

.agent-card__list li i {
  color: var(--color-success-700);
  font-size: var(--text-xs);
}

.agent-card__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-2);
  padding-top: var(--spacing-3);
  border-top: 1px dashed var(--color-surface-200);
  width: 100%;
}

.agent-card__badge {
  display: inline-block;
  padding: var(--spacing-1) var(--spacing-3);
  background-color: var(--color-warning-50);
  color: var(--color-warning-800);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
}

.agent-card__notice {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}

.agent-card__link {
  color: var(--color-primary-700);
  font-weight: var(--font-semibold);
  text-decoration: none;
}

.agent-card__link:hover {
  text-decoration: underline;
}
</style>
