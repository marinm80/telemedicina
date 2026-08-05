<!-- AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev -->
<template>
  <div class="floating-assistant">
    <!-- Chat Panel -->
    <Transition name="slide-up">
      <div v-if="isOpen" class="chat-panel">
        <header class="chat-header">
          <div class="chat-header-title">
            <i class="pi pi-sparkles"></i>
            <span>Asistente Salvia</span>
          </div>
          <button class="chat-close" @click="toggleChat">
            <i class="pi pi-times"></i>
          </button>
        </header>

        <div class="chat-messages" ref="messagesContainer">
          <div v-for="(msg, index) in messages" :key="index" :class="['message-wrapper', msg.role]">
            <div class="message-bubble">
              <p>{{ msg.text }}</p>
            </div>
          </div>
          
          <div v-if="showQuickActions" class="quick-actions">
            <button class="quick-action-btn" @click="navigate('/directory')">
              📅 Agendar cita
            </button>
            <button class="quick-action-btn" @click="navigate('/directory')">
              🔍 Buscar médico
            </button>
            <button class="quick-action-btn" @click="navigate('/appointments')">
              📋 Mis citas
            </button>
          </div>

          <div v-if="isTyping" class="message-wrapper assistant">
            <div class="message-bubble typing-indicator">
              <span></span><span></span><span></span>
            </div>
          </div>
        </div>

        <footer class="chat-footer">
          <form @submit.prevent="sendMessage" class="chat-input-form">
            <input 
              type="text" 
              v-model="newMessage" 
              placeholder="Escribe tu mensaje..."
              class="chat-input"
              :disabled="isTyping"
            />
            <button type="submit" class="chat-send" :disabled="!newMessage.trim() || isTyping">
              <i class="pi pi-send"></i>
            </button>
          </form>
        </footer>
      </div>
    </Transition>

    <!-- Floating Button -->
    <button class="floating-btn" :class="{ 'is-open': isOpen }" @click="toggleChat">
      <i class="pi" :class="isOpen ? 'pi-times' : 'pi-comment'"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue';

interface Message {
  role: 'assistant' | 'user';
  text: string;
}

const isOpen = ref(false);
const isTyping = ref(false);
const newMessage = ref('');
const messagesContainer = ref<HTMLElement | null>(null);

const messages = ref<Message[]>([
  {
    role: 'assistant',
    text: 'Hola 👋 Soy el asistente virtual de Salvia. ¿En qué puedo ayudarte? Puedo agendar citas, buscar especialistas o resolver dudas sobre tus consultas.'
  }
]);

const showQuickActions = ref(true);

const toggleChat = () => {
  isOpen.value = !isOpen.value;
};

const scrollToBottom = async () => {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
};

const navigate = (url: string) => {
  isOpen.value = false;
  window.location.href = url;
};

const sendMessage = () => {
  if (!newMessage.value.trim()) return;

  messages.value.push({ role: 'user', text: newMessage.value });
  newMessage.value = '';
  showQuickActions.value = false;
  scrollToBottom();
  
  isTyping.value = true;
  
  setTimeout(() => {
    isTyping.value = false;
    messages.value.push({ 
      role: 'assistant', 
      text: 'Gracias por tu mensaje. Nuestro equipo de soporte te responderá pronto. Mientras tanto, puedes usar las opciones rápidas para gestionar tus citas.' 
    });
    showQuickActions.value = true;
    scrollToBottom();
  }, 1500);
};
</script>

<style scoped>
.floating-assistant {
  position: fixed;
  right: var(--spacing-6, 24px);
  bottom: var(--spacing-6, 24px);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--spacing-4, 16px);
}

.floating-btn {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full, 9999px);
  background-color: var(--color-primary, #0E5D52);
  color: var(--color-white, #FFFFFF);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  cursor: pointer;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transition: transform 0.3s ease, background-color 0.3s ease;
  animation: pulse-border 2s infinite;
}

.floating-btn:hover {
  transform: scale(1.05);
  background-color: var(--color-primary-dark, #0a463e);
}

.floating-btn.is-open {
  animation: none;
  background-color: var(--color-gray-600, #4B5563);
}

@keyframes pulse-border {
  0% {
    box-shadow: 0 0 0 0 rgba(14, 93, 82, 0.4);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(14, 93, 82, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(14, 93, 82, 0);
  }
}

.chat-panel {
  width: 360px;
  height: 500px;
  background-color: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--color-gray-200, #E5E7EB);
  border-radius: var(--radius-xl, 1rem);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transform-origin: bottom right;
}

.chat-header {
  background-color: var(--color-primary, #0E5D52);
  color: var(--color-white, #FFFFFF);
  padding: var(--spacing-4, 16px);
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top-left-radius: var(--radius-xl, 1rem);
  border-top-right-radius: var(--radius-xl, 1rem);
}

.chat-header-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
  font-weight: 600;
  font-size: 1rem;
}

.chat-close {
  background: transparent;
  border: none;
  color: var(--color-white, #FFFFFF);
  cursor: pointer;
  font-size: 1rem;
  padding: var(--spacing-1, 4px);
  opacity: 0.8;
  transition: opacity 0.2s;
}

.chat-close:hover {
  opacity: 1;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-4, 16px);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3, 12px);
}

.message-wrapper {
  display: flex;
  width: 100%;
}

.message-wrapper.assistant {
  justify-content: flex-start;
}

.message-wrapper.user {
  justify-content: flex-end;
}

.message-bubble {
  max-width: 85%;
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-radius: var(--radius-lg, 0.5rem);
  font-size: 0.9rem;
  line-height: 1.4;
  word-wrap: break-word;
}

.assistant .message-bubble {
  background-color: var(--color-gray-100, #F3F4F6);
  color: var(--color-gray-800, #1F2937);
  border-bottom-left-radius: 0;
}

.user .message-bubble {
  background-color: var(--color-primary, #0E5D52);
  color: var(--color-white, #FFFFFF);
  border-bottom-right-radius: 0;
}

.message-bubble p {
  margin: 0;
}

.quick-actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2, 8px);
  margin-top: var(--spacing-2, 8px);
}

.quick-action-btn {
  background-color: var(--color-white, #FFFFFF);
  border: 1px solid var(--color-primary-light, #148071);
  color: var(--color-primary, #0E5D52);
  padding: var(--spacing-2, 8px) var(--spacing-3, 12px);
  border-radius: var(--radius-md, 0.375rem);
  font-size: 0.85rem;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s ease;
}

.quick-action-btn:hover {
  background-color: var(--color-primary-light, #148071);
  color: var(--color-white, #FFFFFF);
}

.chat-footer {
  padding: var(--spacing-3, 12px);
  background-color: var(--color-white, #FFFFFF);
  border-top: 1px solid var(--color-gray-200, #E5E7EB);
}

.chat-input-form {
  display: flex;
  gap: var(--spacing-2, 8px);
}

.chat-input {
  flex: 1;
  padding: var(--spacing-2, 8px) var(--spacing-3, 12px);
  border: 1px solid var(--color-gray-300, #D1D5DB);
  border-radius: var(--radius-full, 9999px);
  outline: none;
  font-size: 0.9rem;
  transition: border-color 0.2s;
  background-color: var(--color-white, #FFFFFF);
}

.chat-input:focus {
  border-color: var(--color-primary, #0E5D52);
}

.chat-input:disabled {
  background-color: var(--color-gray-100, #F3F4F6);
  cursor: not-allowed;
}

.chat-send {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full, 9999px);
  background-color: var(--color-primary, #0E5D52);
  color: var(--color-white, #FFFFFF);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.2s;
}

.chat-send:hover:not(:disabled) {
  background-color: var(--color-primary-dark, #0a463e);
}

.chat-send:disabled {
  background-color: var(--color-gray-300, #D1D5DB);
  cursor: not-allowed;
}

.typing-indicator {
  display: flex;
  gap: 4px;
  align-items: center;
  height: 24px;
  padding: 0 var(--spacing-4, 16px) !important;
}

.typing-indicator span {
  display: block;
  width: 6px;
  height: 6px;
  background-color: var(--color-gray-500, #6B7280);
  border-radius: 50%;
  animation: typing 1.4s infinite ease-in-out both;
}

.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
  0%, 80%, 100% { transform: scale(0); }
  40% { transform: scale(1); }
}

/* Animations */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.9);
}

/* Responsive */
@media (max-width: 480px) {
  .chat-panel {
    position: fixed;
    bottom: 0;
    right: 0;
    width: 100%;
    height: 100%;
    border-radius: 0;
  }
  
  .floating-assistant {
    right: var(--spacing-4, 16px);
    bottom: var(--spacing-4, 16px);
  }
}
</style>
