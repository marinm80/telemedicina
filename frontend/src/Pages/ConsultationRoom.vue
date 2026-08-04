<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { getCsrfToken } from '@/lib/appointmentHelpers';
import type { ConsultationMessage, SOAPPayload, SignedNote, AmendmentPayload } from '@/types/api.types';

const props = defineProps<{
  consultationId: string;
  userRole: 'doctor' | 'patient';
}>();

// Chat state
const messages = ref<ConsultationMessage[]>([]);
const newMessage = ref('');
const isSending = ref(false);
const isPolling = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const pollInterval = ref<number | null>(null);
const connectionStatus = ref<'connecting' | 'connected' | 'error'>('connecting');

// SOAP state (doctor only)
const isSoapPanelOpen = ref(true);
const soapData = ref<SOAPPayload>({
  symptoms: '',
  objective: '',
  analysis: '',
  plan: '',
});
const isSavingNote = ref(false);
const isSigningNote = ref(false);
const signedNote = ref<SignedNote | null>(null);
const signatureError = ref('');

// Amendments
const amendmentReason = ref('');
const amendmentContent = ref('');
const isAddingAmendment = ref(false);

const scrollToBottom = async () => {
  await nextTick();
  if (chatContainer.value) {
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
  }
};

const fetchMessages = async () => {
  try {
    const response = await fetch(`/api/consultations/${props.consultationId}/messages`, {
      headers: {
        'Accept': 'application/json'
      }
    });
    if (response.ok) {
      const data = await response.json();
      const newMessages = data.messages || data;
      if (JSON.stringify(messages.value) !== JSON.stringify(newMessages)) {
         messages.value = newMessages;
         scrollToBottom();
      }
      connectionStatus.value = 'connected';
    } else {
      connectionStatus.value = 'error';
    }
  } catch (error) {
    connectionStatus.value = 'error';
  }
};

const startPolling = () => {
  isPolling.value = true;
  fetchMessages();
  pollInterval.value = window.setInterval(fetchMessages, 5000);
};

const stopPolling = () => {
  isPolling.value = false;
  if (pollInterval.value !== null) {
    clearInterval(pollInterval.value);
    pollInterval.value = null;
  }
};

const sendMessage = async () => {
  if (!newMessage.value.trim() || isSending.value) return;
  
  isSending.value = true;
  try {
    const csrfToken = await getCsrfToken();
    const response = await fetch(`/api/consultations/${props.consultationId}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ content: newMessage.value })
    });
    
    if (response.ok) {
      newMessage.value = '';
      await fetchMessages();
    }
  } catch (error) {
    console.error('Error sending message:', error);
  } finally {
    isSending.value = false;
  }
};

const saveDraft = async () => {
  if (signedNote.value) {
     signatureError.value = 'Cannot edit a signed note (403 Forbidden).';
     return;
  }
  
  isSavingNote.value = true;
  signatureError.value = '';
  try {
    const csrfToken = await getCsrfToken();
    const response = await fetch(`/api/consultations/${props.consultationId}/notes`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(soapData.value)
    });
    if (!response.ok) {
        if (response.status === 403) {
            signatureError.value = '403 Forbidden: Note is already signed.';
        } else {
            signatureError.value = 'Error saving draft.';
        }
    }
  } catch (error) {
    signatureError.value = 'Error saving draft.';
  } finally {
    isSavingNote.value = false;
  }
};

const signNote = async () => {
  if (!confirm('Are you sure you want to sign this note? This action is irreversible.')) return;
  
  isSigningNote.value = true;
  signatureError.value = '';
  try {
    const csrfToken = await getCsrfToken();
    const response = await fetch(`/api/consultations/${props.consultationId}/notes/sign`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      signedNote.value = data;
    } else {
      signatureError.value = 'Error signing note.';
    }
  } catch (error) {
    signatureError.value = 'Error signing note.';
  } finally {
    isSigningNote.value = false;
  }
};

const addAmendment = async () => {
  if (!amendmentReason.value.trim() || !amendmentContent.value.trim() || isAddingAmendment.value) return;
  
  isAddingAmendment.value = true;
  try {
    const csrfToken = await getCsrfToken();
    const payload: AmendmentPayload = {
      reason: amendmentReason.value,
      content: amendmentContent.value
    };
    
    const response = await fetch(`/api/consultations/${props.consultationId}/notes/amendments`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });
    
    if (response.ok) {
      amendmentReason.value = '';
      amendmentContent.value = '';
      alert('Amendment added successfully');
    }
  } catch (error) {
    console.error('Error adding amendment:', error);
  } finally {
    isAddingAmendment.value = false;
  }
};

onMounted(() => {
  startPolling();
});

onUnmounted(() => {
  stopPolling();
});
</script>

<template>
  <AppLayout>
    <div class="consultation-room">
      <div class="consultation-room__chat-section">
        <header class="chat-header">
          <h2>Live Consultation Chat</h2>
          <div class="chat-header__status">
            <span v-if="connectionStatus === 'connecting'" class="status-indicator status-indicator--connecting">
              <i class="pi pi-spin pi-spinner"></i> Connecting...
            </span>
            <span v-else-if="connectionStatus === 'connected'" class="status-indicator status-indicator--connected">
              <i class="pi pi-check-circle"></i> Connected
            </span>
            <span v-else class="status-indicator status-indicator--error">
              <i class="pi pi-exclamation-circle"></i> Connection Error
            </span>
          </div>
        </header>

        <div class="chat-messages" ref="chatContainer">
          <div v-for="msg in messages" :key="msg.id" 
               class="message-bubble" 
               :class="{'message-bubble--mine': msg.sender_id === 'me'}">
            <div class="message-bubble__content">{{ msg.content }}</div>
            <div class="message-bubble__meta">{{ new Date(msg.created_at || Date.now()).toLocaleTimeString() }}</div>
          </div>
        </div>

        <form @submit.prevent="sendMessage" class="chat-input-form">
          <textarea 
            v-model="newMessage" 
            placeholder="Type your message here..." 
            class="chat-input-form__textarea"
            @keydown.enter.prevent="sendMessage"
          ></textarea>
          <button type="submit" class="chat-input-form__send-btn" :disabled="isSending || !newMessage.trim()">
            <i class="pi" :class="isSending ? 'pi-spin pi-spinner' : 'pi-send'"></i>
            Send
          </button>
        </form>
      </div>

      <aside v-if="props.userRole === 'doctor'" class="consultation-room__soap-panel">
        <header class="soap-header">
          <h3>Clinical Notes (SOAP)</h3>
          <button @click="isSoapPanelOpen = !isSoapPanelOpen" class="soap-header__toggle">
            <i class="pi" :class="isSoapPanelOpen ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
          </button>
        </header>

        <div v-show="isSoapPanelOpen" class="soap-content">
          <div v-if="signatureError" class="soap-alert soap-alert--error">
            <i class="pi pi-exclamation-triangle"></i> {{ signatureError }}
          </div>

          <div v-if="signedNote" class="soap-alert soap-alert--signed">
            <i class="pi pi-lock"></i> Note Signed
            <p class="soap-alert__hash">Hash: {{ signedNote.content_hash }}</p>
          </div>

          <div class="soap-field">
            <label for="soap-symptoms">Symptoms (S)</label>
            <textarea id="soap-symptoms" v-model="soapData.symptoms" :disabled="!!signedNote"></textarea>
          </div>
          <div class="soap-field">
            <label for="soap-objective">Objective (O)</label>
            <textarea id="soap-objective" v-model="soapData.objective" :disabled="!!signedNote"></textarea>
          </div>
          <div class="soap-field">
            <label for="soap-analysis">Analysis (A)</label>
            <textarea id="soap-analysis" v-model="soapData.analysis" :disabled="!!signedNote"></textarea>
          </div>
          <div class="soap-field">
            <label for="soap-plan">Plan (P)</label>
            <textarea id="soap-plan" v-model="soapData.plan" :disabled="!!signedNote"></textarea>
          </div>

          <div class="soap-actions" v-if="!signedNote">
            <button @click="saveDraft" class="btn-draft" :disabled="isSavingNote">
              <i class="pi" :class="isSavingNote ? 'pi-spin pi-spinner' : 'pi-save'"></i> Save Draft
            </button>
            <button @click="signNote" class="btn-sign" :disabled="isSigningNote">
              <i class="pi" :class="isSigningNote ? 'pi-spin pi-spinner' : 'pi-file-edit'"></i> Sign Note
            </button>
          </div>

          <div v-else class="amendment-section">
            <h4>Add Amendment</h4>
            <div class="soap-field">
              <label>Reason</label>
              <input type="text" v-model="amendmentReason" />
            </div>
            <div class="soap-field">
              <label>Content</label>
              <textarea v-model="amendmentContent"></textarea>
            </div>
            <button @click="addAmendment" class="btn-amend" :disabled="isAddingAmendment || !amendmentReason || !amendmentContent">
              <i class="pi" :class="isAddingAmendment ? 'pi-spin pi-spinner' : 'pi-plus'"></i> Add Amendment
            </button>
          </div>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>

<style scoped>
.consultation-room {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4, 1rem);
  height: calc(100vh - 100px);
}

@media (min-width: 768px) {
  .consultation-room {
    flex-direction: row;
  }
}

.consultation-room__chat-section {
  flex: 2;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-gray-200, #e5e7eb);
  border-radius: var(--border-radius-md, 0.5rem);
  background: white;
  overflow: hidden;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-4, 1rem);
  background-color: var(--color-gray-50, #f9fafb);
  border-bottom: 1px solid var(--color-gray-200, #e5e7eb);
}

.chat-header h2 {
  margin: 0;
  font-size: var(--text-lg, 1.125rem);
  color: var(--color-gray-900, #111827);
}

.status-indicator {
  font-size: var(--text-sm, 0.875rem);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.status-indicator--connecting { color: var(--color-warning-600, #d97706); }
.status-indicator--connected { color: var(--color-success-600, #059669); }
.status-indicator--error { color: var(--color-danger-600, #dc2626); }

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-4, 1rem);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3, 0.75rem);
}

.message-bubble {
  max-width: 75%;
  padding: var(--spacing-3, 0.75rem);
  border-radius: var(--border-radius-md, 0.5rem);
  background-color: var(--color-gray-100, #f3f4f6);
  align-self: flex-start;
}

.message-bubble--mine {
  background-color: var(--color-primary-100, #e0e7ff);
  align-self: flex-end;
}

.message-bubble__content {
  font-size: var(--text-base, 1rem);
  color: var(--color-gray-800, #1f2937);
  white-space: pre-wrap;
}

.message-bubble__meta {
  font-size: var(--text-xs, 0.75rem);
  color: var(--color-gray-500, #6b7280);
  margin-top: 0.25rem;
  text-align: right;
}

.chat-input-form {
  display: flex;
  padding: var(--spacing-4, 1rem);
  gap: var(--spacing-3, 0.75rem);
  border-top: 1px solid var(--color-gray-200, #e5e7eb);
  background-color: white;
}

.chat-input-form__textarea {
  flex: 1;
  resize: none;
  border: 1px solid var(--color-gray-300, #d1d5db);
  border-radius: var(--border-radius-sm, 0.375rem);
  padding: var(--spacing-2, 0.5rem);
  font-family: inherit;
}

.chat-input-form__send-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0 var(--spacing-4, 1rem);
  background-color: var(--color-primary-600, #4f46e5);
  color: white;
  border: none;
  border-radius: var(--border-radius-sm, 0.375rem);
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.2s;
}

.chat-input-form__send-btn:disabled {
  background-color: var(--color-gray-400, #9ca3af);
  cursor: not-allowed;
}

.chat-input-form__send-btn:not(:disabled):hover {
  background-color: var(--color-primary-700, #4338ca);
}

.consultation-room__soap-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-gray-200, #e5e7eb);
  border-radius: var(--border-radius-md, 0.5rem);
  background: white;
  overflow-y: auto;
}

.soap-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-4, 1rem);
  background-color: var(--color-gray-50, #f9fafb);
  border-bottom: 1px solid var(--color-gray-200, #e5e7eb);
  position: sticky;
  top: 0;
}

.soap-header h3 {
  margin: 0;
  font-size: var(--text-lg, 1.125rem);
  color: var(--color-gray-900, #111827);
}

.soap-header__toggle {
  background: none;
  border: none;
  color: var(--color-gray-600, #4b5563);
  cursor: pointer;
}

.soap-content {
  padding: var(--spacing-4, 1rem);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4, 1rem);
}

.soap-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.soap-field label {
  font-weight: 500;
  font-size: var(--text-sm, 0.875rem);
  color: var(--color-gray-700, #374151);
}

.soap-field textarea,
.soap-field input {
  width: 100%;
  border: 1px solid var(--color-gray-300, #d1d5db);
  border-radius: var(--border-radius-sm, 0.375rem);
  padding: var(--spacing-2, 0.5rem);
  font-family: inherit;
  resize: vertical;
  min-height: 80px;
}
.soap-field input {
  min-height: auto;
}

.soap-field textarea:disabled,
.soap-field input:disabled {
  background-color: var(--color-gray-100, #f3f4f6);
  cursor: not-allowed;
}

.soap-actions {
  display: flex;
  gap: var(--spacing-3, 0.75rem);
  margin-top: var(--spacing-2, 0.5rem);
}

.soap-actions button,
.amendment-section button {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: var(--spacing-2, 0.5rem) var(--spacing-4, 1rem);
  border: none;
  border-radius: var(--border-radius-sm, 0.375rem);
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.2s;
}

.soap-actions button:disabled,
.amendment-section button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-draft {
  background-color: var(--color-gray-200, #e5e7eb);
  color: var(--color-gray-800, #1f2937);
}

.btn-sign {
  background-color: var(--color-danger-600, #dc2626);
  color: white;
}

.btn-amend {
  background-color: var(--color-primary-600, #4f46e5);
  color: white;
  width: 100%;
  margin-top: 1rem;
}

.soap-alert {
  padding: var(--spacing-3, 0.75rem);
  border-radius: var(--border-radius-sm, 0.375rem);
  font-size: var(--text-sm, 0.875rem);
  display: flex;
  align-items: flex-start;
  flex-direction: column;
  gap: 0.25rem;
}

.soap-alert i {
  margin-right: 0.5rem;
}

.soap-alert--error {
  background-color: var(--color-danger-50, #fef2f2);
  color: var(--color-danger-700, #b91c1c);
  border: 1px solid var(--color-danger-200, #fecaca);
}

.soap-alert--signed {
  background-color: var(--color-success-50, #ecfdf5);
  color: var(--color-success-700, #047857);
  border: 1px solid var(--color-success-200, #a7f3d0);
}

.soap-alert__hash {
  margin: 0;
  font-family: monospace;
  word-break: break-all;
  font-size: 0.75rem;
  opacity: 0.8;
  padding-left: 1.5rem;
}

.amendment-section {
  border-top: 1px solid var(--color-gray-200, #e5e7eb);
  padding-top: var(--spacing-4, 1rem);
  margin-top: var(--spacing-2, 0.5rem);
}

.amendment-section h4 {
  margin: 0 0 var(--spacing-3, 0.75rem) 0;
  font-size: var(--text-base, 1rem);
  color: var(--color-gray-800, #1f2937);
}
</style>
