<!--
  ====================================================================
  ClinicalAssistant — Panel lateral de asistente clínico
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  RF-24 Asistente Clínico (Dashboard)
  Endpoint: POST /api/assistant/clinical
  
  REGLA UI_PROTOTYPE.md §8.3:
  "El Asistente Clínico de la IA NUNCA debe superponerse al chat médico.
  Durante consultas activas, la UI debe interceptar el código HTTP 409
  ASSISTANT_DISABLED_DURING_CONSULTATION y ocultar el panel del asistente."
  ====================================================================
-->
<script setup lang="ts">
import { ref, nextTick, watch } from 'vue';
import type { ClinicalAssistantResponse } from '@/types/api.types';
import { getCsrfToken } from '@/lib/appointmentHelpers';

const props = withDefaults(defineProps<{
  isConsultationActive?: boolean;
}>(), {
  isConsultationActive: false,
});

const isOpen = ref(false);
const query = ref('');
const isLoading = ref(false);
const isDisabled = ref(false);
const messages = ref<{ role: 'user' | 'assistant'; text: string }[]>([]);
const bodyRef = ref<HTMLDivElement | null>(null);

// Auto-hide during active consultation
watch(() => props.isConsultationActive, (active) => {
  if (active) {
    isOpen.value = false;
    isDisabled.value = true;
  } else {
    isDisabled.value = false;
  }
});

function toggle() {
  if (isDisabled.value) return;
  isOpen.value = !isOpen.value;
}

async function send() {
  const q = query.value.trim();
  if (!q || isLoading.value) return;

  messages.value.push({ role: 'user', text: q });
  query.value = '';
  isLoading.value = true;
  await scrollDown();

  try {
    const res = await fetch('/api/assistant/clinical', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({ query: q }),
    });

    if (res.ok) {
      const data: ClinicalAssistantResponse = await res.json();
      messages.value.push({ role: 'assistant', text: data.reply });
    } else if (res.status === 409) {
      // ASSISTANT_DISABLED_DURING_CONSULTATION
      isDisabled.value = true;
      isOpen.value = false;
      messages.value.push({
        role: 'assistant',
        text: 'El asistente no está disponible durante una consulta activa.',
      });
    } else {
      messages.value.push({ role: 'assistant', text: 'Error al procesar tu consulta.' });
    }
  } catch {
    messages.value.push({ role: 'assistant', text: 'Error de conexión.' });
  } finally {
    isLoading.value = false;
    await scrollDown();
  }
}

async function scrollDown() {
  await nextTick();
  if (bodyRef.value) bodyRef.value.scrollTop = bodyRef.value.scrollHeight;
}
</script>

<template>
  <!-- Toggle button -->
  <button
    type="button"
    class="clin-assist__toggle"
    :class="{ 'clin-assist__toggle--disabled': isDisabled }"
    :disabled="isDisabled"
    :title="isDisabled ? 'Deshabilitado durante consulta' : 'Asistente Clínico IA'"
    @click="toggle"
  >
    <i class="pi pi-sparkles" aria-hidden="true" />
  </button>

  <!-- Side panel -->
  <Transition name="clin-slide">
    <aside v-if="isOpen && !isDisabled" class="clin-assist">
      <header class="clin-assist__header">
        <i class="pi pi-sparkles" aria-hidden="true" />
        <span>Asistente Clínico IA</span>
        <button type="button" class="clin-assist__close" @click="toggle" aria-label="Cerrar">
          <i class="pi pi-times" aria-hidden="true" />
        </button>
      </header>

      <div ref="bodyRef" class="clin-assist__body">
        <div v-if="messages.length === 0" class="clin-assist__empty">
          <i class="pi pi-info-circle" aria-hidden="true" />
          <p>Pregunta sobre medicamentos, preparación para exámenes, y más.</p>
        </div>

        <div
          v-for="(msg, i) in messages"
          :key="i"
          :class="['clin-assist__msg', msg.role === 'user' ? 'clin-assist__msg--user' : 'clin-assist__msg--bot']"
        >
          {{ msg.text }}
        </div>

        <div v-if="isLoading" class="clin-assist__typing">
          <span /><span /><span />
        </div>
      </div>

      <div class="clin-assist__input-area">
        <input
          v-model="query"
          type="text"
          class="clin-assist__input"
          placeholder="¿Debo ayunar antes del examen?"
          @keydown.enter.prevent="send"
        />
        <button type="button" class="clin-assist__send" :disabled="!query.trim() || isLoading" @click="send">
          <i class="pi pi-send" aria-hidden="true" />
        </button>
      </div>
    </aside>
  </Transition>
</template>

<style scoped>
.clin-assist__toggle {
  position: fixed;
  bottom: var(--spacing-5);
  right: var(--spacing-5);
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary-600), var(--color-primary-800));
  border: none;
  color: white;
  font-size: 1.2rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  z-index: 900;
  transition: transform var(--transition-fast), opacity var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}

.clin-assist__toggle:hover:not(:disabled) {
  transform: scale(1.1);
}

.clin-assist__toggle--disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.clin-assist {
  position: fixed;
  top: 0;
  right: 0;
  width: 340px;
  height: 100vh;
  background-color: var(--color-surface-0);
  border-left: 1px solid var(--color-surface-200);
  box-shadow: -4px 0 16px rgba(0, 0, 0, 0.08);
  z-index: 899;
  display: flex;
  flex-direction: column;
}

.clin-assist__header {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-4);
  background: linear-gradient(135deg, var(--color-primary-700), var(--color-primary-600));
  color: white;
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  font-family: var(--font-heading);
}

.clin-assist__close {
  margin-left: auto;
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  font-size: 1rem;
  padding: var(--spacing-1);
}

.clin-assist__body {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-3);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.clin-assist__empty {
  text-align: center;
  padding: var(--spacing-6) var(--spacing-3);
  color: var(--color-text-subtle);
}

.clin-assist__empty i {
  font-size: 2rem;
  margin-bottom: var(--spacing-2);
  display: block;
}

.clin-assist__empty p {
  font-size: var(--text-sm);
  margin: 0;
}

.clin-assist__msg {
  padding: var(--spacing-2) var(--spacing-3);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  line-height: var(--leading-relaxed);
  max-width: 90%;
}

.clin-assist__msg--user {
  align-self: flex-end;
  background-color: var(--color-primary-700);
  color: white;
}

.clin-assist__msg--bot {
  align-self: flex-start;
  background-color: var(--color-surface-100);
  color: var(--color-text-strong);
}

.clin-assist__typing {
  display: flex;
  gap: 4px;
  padding: var(--spacing-2);
}

.clin-assist__typing span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: var(--color-text-subtle);
  animation: clin-bounce 1.2s infinite ease-in-out;
}

.clin-assist__typing span:nth-child(2) { animation-delay: 0.2s; }
.clin-assist__typing span:nth-child(3) { animation-delay: 0.4s; }

@keyframes clin-bounce {
  0%, 80%, 100% { opacity: 0.3; }
  40% { opacity: 1; }
}

.clin-assist__input-area {
  display: flex;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  border-top: 1px solid var(--color-surface-200);
}

.clin-assist__input {
  flex: 1;
  padding: var(--spacing-2);
  border: 1px solid var(--color-surface-300);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
}

.clin-assist__input:focus {
  outline: none;
  border-color: var(--color-primary-500);
}

.clin-assist__send {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background-color: var(--color-primary-700);
  border: none;
  color: white;
  cursor: pointer;
}

.clin-assist__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Transition */
.clin-slide-enter-active,
.clin-slide-leave-active {
  transition: transform 0.3s ease;
}

.clin-slide-enter-from,
.clin-slide-leave-to {
  transform: translateX(100%);
}

@media (max-width: 640px) {
  .clin-assist {
    width: 100%;
  }
}
</style>
