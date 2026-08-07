<!--
  ====================================================================
  PublicAssistant — Chat widget del asistente informativo
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
  RF-23 Asistente Informativo (Landing)
  Endpoint: POST /api/assistant/public
  ====================================================================
-->
<script setup lang="ts">
import { ref, nextTick } from 'vue';
import type { PublicAssistantResponse, PublicAssistantDoctor } from '@/types/api.types';
import { formatUSD } from '@/lib/currency';

const isOpen = ref(false);
const query = ref('');
const isLoading = ref(false);
const messages = ref<{ role: 'user' | 'assistant'; text: string; doctors?: PublicAssistantDoctor[] }[]>([]);
const chatBodyRef = ref<HTMLDivElement | null>(null);

function toggleChat() {
  isOpen.value = !isOpen.value;
}

async function sendMessage() {
  const q = query.value.trim();
  if (!q || isLoading.value) return;

  messages.value.push({ role: 'user', text: q });
  query.value = '';
  isLoading.value = true;
  await scrollToBottom();

  try {
    const res = await fetch('/api/assistant/public', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ query: q }),
    });

    if (res.ok) {
      const data: PublicAssistantResponse = await res.json();
      messages.value.push({
        role: 'assistant',
        text: data.reply,
        doctors: data.doctors && data.doctors.length > 0 ? data.doctors : undefined,
      });
    } else {
      const errData = await res.json().catch(() => ({ message: null }));
      messages.value.push({
        role: 'assistant',
        text: errData.message || 'Lo siento, no pude procesar tu consulta en este momento. Intenta de nuevo.',
      });
    }
  } catch (err) {
    console.error('Error de red al conectar con /api/assistant/public:', err);
    messages.value.push({
      role: 'assistant',
      text: 'Error de conexión con el servidor. Verifica que el servicio backend esté en ejecución.',
    });
  } finally {
    isLoading.value = false;
    await scrollToBottom();
  }
}

async function scrollToBottom() {
  await nextTick();
  if (chatBodyRef.value) {
    chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
  }
}
</script>

<template>
  <!-- Floating trigger -->
  <button type="button" class="pub-assist__trigger" @click="toggleChat" :aria-label="isOpen ? 'Cerrar asistente' : 'Abrir asistente'">
    <i :class="isOpen ? 'pi pi-times' : 'pi pi-comments'" aria-hidden="true" />
  </button>

  <!-- Chat panel -->
  <Transition name="pub-assist-slide">
    <div v-if="isOpen" class="pub-assist">
      <header class="pub-assist__header">
        <div class="pub-assist__avatar">
          <i class="pi pi-android" aria-hidden="true" />
        </div>
        <div>
          <h3 class="pub-assist__title">Asistente Virtual</h3>
          <p class="pub-assist__subtitle">Pregunta sobre especialistas y disponibilidad</p>
        </div>
        <button type="button" class="pub-assist__close" @click="toggleChat" aria-label="Cerrar">
          <i class="pi pi-times" aria-hidden="true" />
        </button>
      </header>

      <div ref="chatBodyRef" class="pub-assist__body">
        <!-- Welcome -->
        <div v-if="messages.length === 0" class="pub-assist__welcome">
          <p>¡Hola! Soy tu asistente médico inteligente. Puedo ayudarte a encontrar especialistas, consultar disponibilidad y responder tus dudas.</p>
          <div class="pub-assist__suggestions">
            <button type="button" class="pub-assist__suggestion" @click="query = '¿Qué especialidades tienen?'; sendMessage()">
              ¿Qué especialidades tienen?
            </button>
            <button type="button" class="pub-assist__suggestion" @click="query = '¿Cuál es el horario de atención?'; sendMessage()">
              ¿Cuál es el horario de atención?
            </button>
            <button type="button" class="pub-assist__suggestion" @click="query = '¿Dónde están ubicados?'; sendMessage()">
              ¿Dónde están ubicados?
            </button>
            <button type="button" class="pub-assist__suggestion" @click="query = '¿Cómo agendar una cita?'; sendMessage()">
              ¿Cómo agendar una cita?
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div
          v-for="(msg, i) in messages"
          :key="i"
          :class="['pub-assist__msg', msg.role === 'user' ? 'pub-assist__msg--user' : 'pub-assist__msg--bot']"
        >
          <p class="pub-assist__msg-text">{{ msg.text }}</p>
          <!-- Doctor cards -->
          <div v-if="msg.doctors" class="pub-assist__doctors">
            <div v-for="doc in msg.doctors" :key="doc.user_id" class="pub-assist__doctor-card">
              <strong>{{ doc.name }}</strong>
              <span>{{ doc.description }}</span>
              <span class="pub-assist__doctor-fee">{{ formatUSD(doc.consultation_fee) }}</span>
            </div>
          </div>
        </div>

        <!-- Typing indicator -->
        <div v-if="isLoading" class="pub-assist__typing">
          <span /><span /><span />
        </div>
      </div>

      <div class="pub-assist__footer">
        <input
          v-model="query"
          type="text"
          class="pub-assist__input"
          placeholder="Escribe tu pregunta..."
          @keydown.enter.prevent="sendMessage"
        />
        <button type="button" class="pub-assist__send" :disabled="!query.trim() || isLoading" @click="sendMessage">
          <i class="pi pi-send" aria-hidden="true" />
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.pub-assist__trigger {
  position: fixed;
  bottom: var(--spacing-5);
  right: var(--spacing-5);
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary-700), var(--color-primary-500));
  border: none;
  color: white;
  font-size: 1.4rem;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
  z-index: 1000;
  transition: transform var(--transition-fast), box-shadow var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pub-assist__trigger:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.pub-assist {
  position: fixed;
  bottom: calc(56px + var(--spacing-5) + var(--spacing-3));
  right: var(--spacing-5);
  width: 440px;
  max-height: 620px;
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-lg);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  z-index: 999;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.pub-assist__header {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background: linear-gradient(135deg, var(--color-primary-700), var(--color-primary-600));
  color: white;
}

.pub-assist__avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.pub-assist__title {
  font-family: var(--font-heading);
  font-size: var(--text-sm);
  font-weight: var(--font-bold);
  margin: 0;
}

.pub-assist__subtitle {
  font-size: var(--text-xs);
  opacity: 0.8;
  margin: 0;
}

.pub-assist__close {
  margin-left: auto;
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  padding: var(--spacing-1);
  font-size: 1rem;
}

.pub-assist__body {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-3);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
  min-height: 260px;
  max-height: 440px;
}

.pub-assist__welcome {
  text-align: center;
  padding: var(--spacing-3);
}

.pub-assist__welcome p {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-relaxed);
  margin: 0 0 var(--spacing-3) 0;
}

.pub-assist__suggestions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.pub-assist__suggestion {
  padding: var(--spacing-2);
  background-color: var(--color-primary-50);
  border: 1px solid var(--color-primary-500);
  border-radius: var(--radius-md);
  color: var(--color-primary-800);
  font-size: var(--text-xs);
  font-family: var(--font-body);
  cursor: pointer;
  text-align: left;
  transition: background-color var(--transition-fast);
}

.pub-assist__suggestion:hover {
  background-color: var(--color-primary-100);
}

.pub-assist__msg {
  max-width: 85%;
  padding: var(--spacing-2) var(--spacing-3);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  line-height: var(--leading-relaxed);
}

.pub-assist__msg--user {
  align-self: flex-end;
  background-color: var(--color-primary-700);
  color: white;
  border-bottom-right-radius: 4px;
}

.pub-assist__msg--bot {
  align-self: flex-start;
  background-color: var(--color-surface-100);
  color: var(--color-text-strong);
  border-bottom-left-radius: 4px;
}

.pub-assist__msg-text {
  margin: 0;
}

.pub-assist__doctors {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
  margin-top: var(--spacing-2);
}

.pub-assist__doctor-card {
  display: flex;
  flex-direction: column;
  padding: var(--spacing-2);
  background-color: var(--color-surface-0);
  border: 1px solid var(--color-surface-200);
  border-radius: var(--radius-sm);
  font-size: var(--text-xs);
}

.pub-assist__doctor-fee {
  font-weight: var(--font-bold);
  color: var(--color-primary-700);
}

.pub-assist__typing {
  display: flex;
  gap: 4px;
  padding: var(--spacing-2);
}

.pub-assist__typing span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-text-subtle);
  animation: pub-typing 1.2s infinite ease-in-out;
}

.pub-assist__typing span:nth-child(2) { animation-delay: 0.2s; }
.pub-assist__typing span:nth-child(3) { animation-delay: 0.4s; }

@keyframes pub-typing {
  0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
  40% { opacity: 1; transform: scale(1); }
}

.pub-assist__footer {
  display: flex;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  border-top: 1px solid var(--color-surface-200);
}

.pub-assist__input {
  flex: 1;
  padding: var(--spacing-2);
  border: 1px solid var(--color-surface-300);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
}

.pub-assist__input:focus {
  outline: none;
  border-color: var(--color-primary-500);
}

.pub-assist__send {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--color-primary-700);
  border: none;
  color: white;
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.pub-assist__send:hover:not(:disabled) {
  background-color: var(--color-primary-600);
}

.pub-assist__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Transition */
.pub-assist-slide-enter-active,
.pub-assist-slide-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.pub-assist-slide-enter-from,
.pub-assist-slide-leave-to {
  transform: translateY(20px);
  opacity: 0;
}

@media (max-width: 480px) {
  .pub-assist {
    width: calc(100vw - 2 * var(--spacing-3));
    right: var(--spacing-3);
    bottom: calc(56px + var(--spacing-3) + var(--spacing-2));
  }
}
</style>
