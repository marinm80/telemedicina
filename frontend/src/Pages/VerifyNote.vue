<!--
  ====================================================================
  VerifyNote — Verificación pública de nota clínica firmada
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  RF-18 Generación de PDF y QR Clínico
  Ruta: /verify/note/:hash (pública, sin autenticación)
  Endpoint: GET /verify/note/{hash}
  ====================================================================
-->
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import type { NoteVerification, NoteVerificationValid } from '@/types/api.types';

const props = defineProps<{
  hash: string;
}>();

const estado = ref<'cargando' | 'listo' | 'error'>('cargando');
const result = ref<NoteVerification | null>(null);
const errorMsg = ref('');

const isValid = (r: NoteVerification): r is NoteVerificationValid => r.valid === true;

onMounted(async () => {
  try {
    const res = await fetch(`/verify/note/${props.hash}`, {
      headers: { 'Accept': 'application/json' },
    });

    if (res.ok) {
      result.value = await res.json();
      estado.value = 'listo';
    } else if (res.status === 404) {
      result.value = await res.json();
      estado.value = 'listo';
    } else {
      errorMsg.value = 'Error al verificar el documento.';
      estado.value = 'error';
    }
  } catch {
    errorMsg.value = 'Error de red. Verifica tu conexión.';
    estado.value = 'error';
  }
});

function formatDate(iso: string): string {
  return new Intl.DateTimeFormat('es', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(new Date(iso));
}
</script>

<template>
  <LandingLayout>
    <section class="verify">
      <div class="verify__container">
        <h1 class="verify__heading">
          <i class="pi pi-shield" aria-hidden="true" />
          Verificación de Documento Clínico
        </h1>

        <SpinnerLoader v-if="estado === 'cargando'" />

        <div v-else-if="estado === 'error'" class="verify__card verify__card--error" role="alert">
          <i class="pi pi-times-circle verify__icon verify__icon--error" aria-hidden="true" />
          <p class="verify__message">{{ errorMsg }}</p>
        </div>

        <!-- Valid note -->
        <div
          v-else-if="result && isValid(result)"
          class="verify__card verify__card--valid"
        >
          <div class="verify__badge verify__badge--valid">
            <i class="pi pi-check-circle" aria-hidden="true" />
            Documento Verificado
          </div>

          <p class="verify__intro">
            Este documento clínico es auténtico y su integridad ha sido verificada
            mediante firma electrónica SHA-256.
          </p>

          <dl class="verify__details">
            <div class="verify__detail-row">
              <dt>Estado</dt>
              <dd>
                <span class="verify__status-pill verify__status-pill--signed">
                  {{ result.status === 'signed' ? 'Firmado' : result.status }}
                </span>
              </dd>
            </div>
            <div class="verify__detail-row">
              <dt>Hash de Integridad</dt>
              <dd class="verify__hash">{{ result.content_hash }}</dd>
            </div>
            <div class="verify__detail-row">
              <dt>Firmado el</dt>
              <dd>{{ formatDate(result.signed_at) }}</dd>
            </div>
            <div v-if="result.acknowledged_at" class="verify__detail-row">
              <dt>Acuse de recibo</dt>
              <dd>{{ formatDate(result.acknowledged_at) }}</dd>
            </div>
            <div class="verify__detail-row">
              <dt>Enmiendas</dt>
              <dd>{{ result.amendments_count }}</dd>
            </div>
            <div class="verify__detail-row">
              <dt>Verificado el</dt>
              <dd>{{ formatDate(result.verified_at) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Invalid note -->
        <div
          v-else-if="result && !isValid(result)"
          class="verify__card verify__card--invalid"
        >
          <div class="verify__badge verify__badge--invalid">
            <i class="pi pi-exclamation-triangle" aria-hidden="true" />
            Documento No Válido
          </div>
          <p class="verify__message">{{ result.message }}</p>
          <p class="verify__code">Código: {{ result.error_code }}</p>
        </div>
      </div>
    </section>
  </LandingLayout>
</template>

<style scoped>
.verify {
  padding: var(--spacing-8) var(--spacing-4);
  background-color: var(--color-surface-50);
  min-height: 60vh;
}

.verify__container {
  max-width: 40rem;
  margin: 0 auto;
}

.verify__heading {
  font-family: var(--font-heading);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  color: var(--color-text-strong);
  text-align: center;
  margin: 0 0 var(--spacing-6) 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
}

.verify__heading i {
  color: var(--color-primary-700);
  font-size: var(--text-2xl);
}

.verify__card {
  padding: var(--spacing-5);
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-surface-200);
  background-color: var(--color-surface-0);
}

.verify__card--valid {
  border-color: var(--color-success-200);
}

.verify__card--invalid {
  border-color: var(--color-warning-200);
}

.verify__card--error {
  border-color: var(--color-danger-200);
  text-align: center;
}

.verify__badge {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-4);
  border-radius: var(--radius-full);
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  margin-bottom: var(--spacing-4);
}

.verify__badge--valid {
  background-color: var(--color-success-50);
  color: var(--color-success-800);
}

.verify__badge--invalid {
  background-color: var(--color-warning-50);
  color: var(--color-warning-800);
}

.verify__intro {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-relaxed);
  margin: 0 0 var(--spacing-4) 0;
}

.verify__details {
  margin: 0;
}

.verify__detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: var(--spacing-3) 0;
  border-bottom: 1px solid var(--color-surface-100);
  gap: var(--spacing-3);
}

.verify__detail-row:last-child {
  border-bottom: none;
}

.verify__detail-row dt {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-strong);
  flex-shrink: 0;
}

.verify__detail-row dd {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
  text-align: right;
  word-break: break-all;
}

.verify__hash {
  font-family: var(--font-mono, monospace);
  font-size: var(--text-xs);
}

.verify__status-pill {
  display: inline-block;
  padding: var(--spacing-1) var(--spacing-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
}

.verify__status-pill--signed {
  background-color: var(--color-success-50);
  color: var(--color-success-800);
}

.verify__icon {
  font-size: 2rem;
  margin-bottom: var(--spacing-3);
}

.verify__icon--error {
  color: var(--color-danger-600);
}

.verify__message {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin: 0;
}

.verify__code {
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  font-family: var(--font-mono, monospace);
  margin: var(--spacing-2) 0 0 0;
}
</style>
