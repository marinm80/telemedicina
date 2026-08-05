<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { getCsrfToken } from '@/lib/appointmentHelpers';
import type { ConsultationMessage, SOAPPayload, SignedNote, AmendmentPayload } from '@/types/api.types';

const props = defineProps<{
  consultationId: string;
  userRole: 'doctor' | 'patient';
}>();

// Get the authenticated user ID from shared Inertia page props
const page = usePage();
const currentUserId = computed(() => (page.props as any).auth?.user?.id);

// State variables
const isLoading = ref(true);
const hasError = ref(false);
const errorMessage = ref('');
const isEmpty = ref(false);

// Patient Clinical File
const patient = reactive({
  id: 'PAC-1092',
  name: 'MarÃ­a FernÃ¡ndez',
  age: 42,
  consultationNumber: 'CON-5582',
  allergies: [
    { id: '1', name: 'Penicilina', severity: 'alta' as const },
    { id: '2', name: 'Polvo', severity: 'baja' as const }
  ],
  conditions: [
    { id: '1', name: 'HipertensiÃ³n Arterial', status: 'activa' as const },
    { id: '2', name: 'Asma leve', status: 'activa' as const },
    { id: '3', name: 'Apendicitis', status: 'resuelta' as const }
  ],
  medications: [
    { id: '1', name: 'LosartÃ¡n', dosage: '50mg c/12h' },
    { id: '2', name: 'Salbutamol', dosage: '2 puffs PRN' }
  ],
  history: [
    { id: '1', date: '10/05/2026', doctor: 'Dr. R. MarÃ­n', summary: 'Control de hipertensiÃ³n. Ajuste de dosis.' },
    { id: '2', date: '15/02/2026', doctor: 'Dra. S. GÃ³mez', summary: 'Consulta por cuadro respiratorio agudo.' }
  ]
});

// Chat state
const messages = ref<ConsultationMessage[]>([]);
const newMessage = ref('');
const isSending = ref(false);
const isPolling = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const pollInterval = ref<number | null>(null);
const connectionStatus = ref<'connecting' | 'connected' | 'error'>('connecting');

// SOAP Note state
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
const isAutoSaving = ref(false);
const lastSaved = ref('');

// Amendments
const amendments = ref<any[]>([]);
const amendmentReason = ref('');
const amendmentContent = ref('');
const isAddingAmendment = ref(false);
const hasConsent = ref(false);

// Scroll chat to bottom helper
const scrollToBottom = async () => {
  await nextTick();
  if (chatContainer.value) {
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
  }
};

// Fetch real messages from API
const fetchMessages = async () => {
  try {
    const response = await fetch(`/api/consultations/${props.consultationId}/messages`, {
      headers: {
        'Accept': 'application/json'
      }
    });
    if (response.ok) {
      const data = await response.json();
      const newMessages = data.data || data.messages || data;
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

// Send message to API
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

// Fetch real SOAP note from API
const fetchNote = async () => {
  try {
    const response = await fetch(`/api/consultations/${props.consultationId}/notes`, {
      headers: {
        'Accept': 'application/json'
      }
    });
    if (response.ok) {
      const data = await response.json();
      soapData.value = {
        symptoms: data.symptoms || '',
        objective: data.objective || '',
        analysis: data.analysis || '',
        plan: data.plan || '',
      };
      if (data.status === 'signed') {
        signedNote.value = data;
      }
      if (data.amendments) {
        amendments.value = data.amendments;
      }
    }
  } catch (error) {
    // A 404 is expected if the note hasn't been saved yet
    console.log('No clinical note found (expected if draft does not exist yet).');
  }
};

// Save draft SOAP note
const saveDraft = async () => {
  if (signedNote.value) {
    signatureError.value = 'No se puede editar una nota firmada (403 Forbidden).';
    return;
  }

  isSavingNote.value = true;
  isAutoSaving.value = true;
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
      body: JSON.stringify({
        symptoms: soapData.value.symptoms,
        objective: soapData.value.objective,
        analysis: soapData.value.analysis,
        plan: soapData.value.plan,
      })
    });
    if (response.ok) {
      const now = new Date();
      lastSaved.value = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
    } else if (response.status === 403) {
      signatureError.value = '403 Forbidden: La nota ya estÃ¡ firmada.';
    } else {
      signatureError.value = 'Error al guardar borrador.';
    }
  } catch (error) {
    signatureError.value = 'Error al guardar borrador.';
  } finally {
    isSavingNote.value = false;
    isAutoSaving.value = false;
  }
};

// Sign SOAP note
const signNote = async () => {
  if (!hasConsent.value || !isFormValid.value) return;
  if (!confirm('Â¿EstÃ¡ seguro de que desea firmar esta nota? Esta acciÃ³n es irreversible.')) return;

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
      signatureError.value = 'Error al firmar nota clÃ­nica.';
    }
  } catch (error) {
    signatureError.value = 'Error al firmar nota clÃ­nica.';
  } finally {
    isSigningNote.value = false;
  }
};

// Add amendment to signed note
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
      const data = await response.json();
      amendments.value.push(data);
      amendmentReason.value = '';
      amendmentContent.value = '';
    } else {
      alert('Error al aÃ±adir la enmienda.');
    }
  } catch (error) {
    console.error('Error adding amendment:', error);
  } finally {
    isAddingAmendment.value = false;
  }
};

// Polling controls
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

// Check if form fields are filled
const isFormValid = computed(() => {
  return (soapData.value.symptoms || '').trim() !== '' &&
         (soapData.value.objective || '').trim() !== '' &&
         (soapData.value.analysis || '').trim() !== '' &&
         (soapData.value.plan || '').trim() !== '';
});

// Initial load
const initConsultation = async () => {
  isLoading.value = true;
  hasError.value = false;
  try {
    await Promise.all([
      fetchMessages(),
      fetchNote()
    ]);
  } catch (error) {
    hasError.value = true;
    errorMessage.value = 'Error al cargar la consulta mÃ©dica.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  initConsultation();
  startPolling();
});

onUnmounted(() => {
  stopPolling();
});
</script>

<template>
  <AppLayout>
    <div class="consultation">
      <!-- Loading State -->
      <div v-if="isLoading" class="consultation__loading">
        <SpinnerLoader />
        <p>Cargando sala de consulta...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="hasError" class="consultation__error">
        <ErrorFallback :message="errorMessage" @retry="initConsultation" />
      </div>

      <!-- Empty State -->
      <div v-else-if="isEmpty" class="consultation__empty">
        <EmptyState message="Sin consulta activa. No hay paciente asignado para este horario." />
      </div>

      <!-- Ready State -->
      <div v-else class="consultation__layout">
        <!-- LEFT PANEL: Patient Clinical File -->
        <aside class="consultation__left-panel">
          <header class="patient-header">
            <div class="patient-header__avatar">
              <i class="pi pi-user"></i>
            </div>
            <div class="patient-header__info">
              <h2 class="patient-header__name">{{ patient.name }}</h2>
              <p class="patient-header__details">
                {{ patient.age }} aÃ±os â€¢ ID: {{ patient.id }} â€¢ Consulta #{{ patient.consultationNumber }}
              </p>
            </div>
          </header>

          <div class="patient-accordions">
            <!-- Alergias -->
            <details class="accordion" open>
              <summary class="accordion__summary">
                <span><i class="pi pi-exclamation-triangle"></i> Alergias</span>
                <i class="pi pi-chevron-down accordion__icon"></i>
              </summary>
              <div class="accordion__content">
                <ul class="item-list">
                  <li v-for="alergy in patient.allergies" :key="alergy.id" class="item-list__item">
                    <span class="item-list__name">{{ alergy.name }}</span>
                    <span :class="['badge', `badge--${alergy.severity}`]">{{ alergy.severity }}</span>
                  </li>
                </ul>
              </div>
            </details>

            <!-- Condiciones -->
            <details class="accordion">
              <summary class="accordion__summary">
                <span><i class="pi pi-heart"></i> Condiciones</span>
                <i class="pi pi-chevron-down accordion__icon"></i>
              </summary>
              <div class="accordion__content">
                <ul class="item-list">
                  <li v-for="condition in patient.conditions" :key="condition.id" class="item-list__item">
                    <span class="item-list__name">{{ condition.name }}</span>
                    <span :class="['badge', `badge--${condition.status}`]">{{ condition.status }}</span>
                  </li>
                </ul>
              </div>
            </details>

            <!-- Medicamentos -->
            <details class="accordion">
              <summary class="accordion__summary">
                <span><i class="pi pi-box"></i> Medicamentos</span>
                <i class="pi pi-chevron-down accordion__icon"></i>
              </summary>
              <div class="accordion__content">
                <ul class="item-list">
                  <li v-for="med in patient.medications" :key="med.id" class="item-list__item">
                    <span class="item-list__name">{{ med.name }}</span>
                    <span class="item-list__meta">{{ med.dosage }}</span>
                  </li>
                </ul>
              </div>
            </details>

            <!-- Consultas Anteriores -->
            <details class="accordion">
              <summary class="accordion__summary">
                <span><i class="pi pi-history"></i> Consultas Anteriores</span>
                <i class="pi pi-chevron-down accordion__icon"></i>
              </summary>
              <div class="accordion__content">
                <ul class="history-list">
                  <li v-for="history in patient.history" :key="history.id" class="history-list__item">
                    <div class="history-list__header">
                      <span class="history-list__date">{{ history.date }}</span>
                      <span class="history-list__doctor">{{ history.doctor }}</span>
                    </div>
                    <p class="history-list__summary">{{ history.summary }}</p>
                  </li>
                </ul>
              </div>
            </details>
          </div>
        </aside>

        <!-- RIGHT PANEL: Live Session -->
        <main class="consultation__right-panel">
          <!-- TOP: Chat Window -->
          <section class="chat" :class="{'chat--full-height': props.userRole === 'patient' && !signedNote}">
            <header class="chat__header">
              <h3><i class="pi pi-comments"></i> Chat de SesiÃ³n</h3>
              <div class="chat-header__status">
                <span v-if="connectionStatus === 'connecting'" class="status-indicator status-indicator--connecting">
                  <i class="pi pi-spin pi-spinner"></i> Conectando...
                </span>
                <span v-else-if="connectionStatus === 'connected'" class="status-indicator status-indicator--connected">
                  <i class="pi pi-check-circle"></i> Conectado
                </span>
                <span v-else class="status-indicator status-indicator--error">
                  <i class="pi pi-exclamation-circle"></i> Error de ConexiÃ³n
                </span>
              </div>
            </header>
            
            <div class="chat__messages" ref="chatContainer">
              <div 
                v-for="msg in messages" 
                :key="msg.id" 
                :class="['chat__message', msg.sender_id === currentUserId ? 'chat__message--self' : 'chat__message--other']"
              >
                <div class="chat__message-header">
                  <span class="chat__message-sender">
                    {{ msg.sender_id === currentUserId ? 'TÃº' : (props.userRole === 'doctor' ? 'Paciente' : 'MÃ©dico') }}
                  </span>
                  <span class="chat__message-time">
                    {{ new Date(msg.created_at || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                  </span>
                </div>
                <div class="chat__message-text">{{ msg.content }}</div>
              </div>
            </div>
            
            <div class="chat__input-area">
              <input 
                v-model="newMessage" 
                type="text" 
                class="chat__input" 
                placeholder="Escribe un mensaje..."
                :disabled="isSending"
                @keyup.enter="sendMessage"
              />
              <button class="chat__send-btn" @click="sendMessage" :disabled="isSending || !newMessage.trim()">
                <i class="pi" :class="isSending ? 'pi-spin pi-spinner' : 'pi-send'"></i>
              </button>
            </div>
          </section>

          <!-- BOTTOM: SOAP Note Editor (Doctor) / Read-only viewer (Patient if Signed) -->
          <section class="soap" v-if="props.userRole === 'doctor' || signedNote">
            <header class="soap__header">
              <h3><i class="pi pi-file-edit"></i> Nota SOAP</h3>
              <div v-if="props.userRole === 'doctor'">
                <span v-if="isAutoSaving" class="soap__autosave">
                  <i class="pi pi-sync pi-spin"></i> Guardando...
                </span>
                <span v-else-if="lastSaved" class="soap__autosave soap__autosave--success">
                  <i class="pi pi-check"></i> Guardado {{ lastSaved }}
                </span>
              </div>
            </header>

            <div class="soap__fields">
              <div v-if="signatureError" class="soap-alert soap-alert--error">
                <i class="pi pi-exclamation-triangle"></i> {{ signatureError }}
              </div>

              <div class="soap__field">
                <label for="soap-s">S - Subjetivo (SÃ­ntomas)</label>
                <textarea 
                  id="soap-s" 
                  v-model="soapData.symptoms" 
                  :disabled="!!signedNote || props.userRole !== 'doctor'"
                  :class="{'soap__textarea--signed': !!signedNote}"
                  placeholder="Motivo de consulta, sÃ­ntomas referidos por el paciente..."
                ></textarea>
              </div>
              <div class="soap__field">
                <label for="soap-o">O - Objetivo</label>
                <textarea 
                  id="soap-o" 
                  v-model="soapData.objective" 
                  :disabled="!!signedNote || props.userRole !== 'doctor'"
                  :class="{'soap__textarea--signed': !!signedNote}"
                  placeholder="Signos vitales, hallazgos de exploraciÃ³n fÃ­sica..."
                ></textarea>
              </div>
              <div class="soap__field">
                <label for="soap-a">A - AnÃ¡lisis</label>
                <textarea 
                  id="soap-a" 
                  v-model="soapData.analysis" 
                  :disabled="!!signedNote || props.userRole !== 'doctor'"
                  :class="{'soap__textarea--signed': !!signedNote}"
                  placeholder="DiagnÃ³stico presuntivo, evaluaciÃ³n clÃ­nica..."
                ></textarea>
              </div>
              <div class="soap__field">
                <label for="soap-p">P - Plan</label>
                <textarea 
                  id="soap-p" 
                  v-model="soapData.plan" 
                  :disabled="!!signedNote || props.userRole !== 'doctor'"
                  :class="{'soap__textarea--signed': !!signedNote}"
                  placeholder="Tratamiento, estudios solicitados, seguimiento..."
                ></textarea>
              </div>
            </div>

            <!-- Doctor signing controls -->
            <div v-if="props.userRole === 'doctor' && !signedNote" class="soap__actions">
              <label class="soap__consent">
                <input type="checkbox" v-model="hasConsent" />
                <span>Confirmo el consentimiento informado del paciente.</span>
              </label>
              <div class="soap__buttons">
                <button class="btn btn--secondary" @click="saveDraft" :disabled="isSavingNote">
                  <i class="pi" :class="isSavingNote ? 'pi-spin pi-spinner' : 'pi-save'"></i> Guardar Borrador
                </button>
                <button 
                  class="btn btn--primary" 
                  :disabled="!hasConsent || !isFormValid || isSigningNote" 
                  @click="signNote"
                >
                  <i class="pi" :class="isSigningNote ? 'pi-spin pi-spinner' : 'pi-lock'"></i> FIRMAR NOTA CLÃNICA
                </button>
              </div>
            </div>

            <!-- Integrity Banner -->
            <div v-if="signedNote" class="soap__integrity-banner">
              <div class="banner-icon">
                <i class="pi pi-shield"></i>
              </div>
              <div class="banner-content">
                <strong>Nota ClÃ­nica Firmada Digitalmente</strong>
                <p>MÃ©dico ID: {{ (signedNote as any).signed_by || 'Firmado' }} | Fecha: {{ signedNote.signed_at ? new Date(signedNote.signed_at).toLocaleString() : '' }}</p>
                <p class="hash-text">SHA-256: {{ signedNote.content_hash }}</p>
              </div>
            </div>

            <!-- Amendments section -->
            <div v-if="signedNote" class="soap__amendments-display">
              <div class="amendments-list" v-if="amendments.length > 0">
                <div v-for="amend in amendments" :key="amend.id" class="amendment-item">
                  <div class="amendment-item__header">
                    <strong>Enmienda ClÃ­nica</strong>
                    <span>{{ new Date(amend.created_at).toLocaleString() }}</span>
                  </div>
                  <p class="amendment-item__reason"><strong>Motivo:</strong> {{ amend.reason }}</p>
                  <p class="amendment-item__content"><strong>Contenido:</strong> {{ amend.content }}</p>
                </div>
              </div>

              <!-- Add amendment (Doctor only) -->
              <div v-if="props.userRole === 'doctor'" class="amendment-section">
                <h4>AÃ±adir Enmienda ClÃ­nica</h4>
                <div class="soap__field">
                  <label for="amendment-reason">Motivo de la enmienda</label>
                  <input 
                    id="amendment-reason" 
                    v-model="amendmentReason" 
                    type="text" 
                    placeholder="Ej. CorrecciÃ³n de dosis de medicamento..."
                  />
                </div>
                <div class="soap__field">
                  <label for="amendment-content">Contenido de la enmienda</label>
                  <textarea 
                    id="amendment-content" 
                    v-model="amendmentContent" 
                    placeholder="Describa el contenido nuevo o corregido..."
                  ></textarea>
                </div>
                <button 
                  class="btn btn--primary btn-amend" 
                  :disabled="isAddingAmendment || !amendmentReason.trim() || !amendmentContent.trim()"
                  @click="addAmendment"
                >
                  <i class="pi" :class="isAddingAmendment ? 'pi-spin pi-spinner' : 'pi-plus-circle'"></i> Registrar Enmienda
                </button>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.consultation {
  display: flex;
  flex-direction: column;
  height: 100%;
  width: 100%;
  background-color: var(--color-surface-50, #f8f9fa);
  color: var(--color-text-strong, #333);
  font-family: var(--font-body, system-ui, sans-serif);
}

.consultation__loading,
.consultation__error,
.consultation__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: var(--spacing-6, 24px);
  text-align: center;
}

.consultation__layout {
  display: flex;
  flex-direction: row;
  height: calc(100vh - 80px); /* Adjust based on AppLayout header */
  gap: var(--spacing-4, 16px);
  padding: var(--spacing-4, 16px);
}

@media (max-width: 992px) {
  .consultation__layout {
    flex-direction: column;
    height: auto;
    min-height: 100vh;
  }
}

/* LEFT PANEL */
.consultation__left-panel {
  flex: 0 0 350px;
  background-color: var(--color-surface-0, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

@media (max-width: 992px) {
  .consultation__left-panel {
    flex: none;
    height: 400px;
  }
}

.patient-header {
  display: flex;
  align-items: center;
  padding: var(--spacing-4, 16px);
  background-color: var(--color-primary-50, #e3f2fd);
  border-bottom: 1px solid var(--color-surface-200, #e0e0e0);
}

.patient-header__avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background-color: var(--color-clinical-accent, #1976d2);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: var(--spacing-3, 12px);
}

.patient-header__info {
  display: flex;
  flex-direction: column;
}

.patient-header__name {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  font-family: var(--font-heading, system-ui, sans-serif);
}

.patient-header__details {
  margin: 0;
  font-size: 0.85rem;
  color: var(--color-text-muted, #666);
}

.patient-accordions {
  overflow-y: auto;
  flex: 1;
}

.accordion {
  border-bottom: 1px solid var(--color-surface-200, #e0e0e0);
}

.accordion__summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  cursor: pointer;
  font-weight: 600;
  list-style: none;
  background-color: var(--color-surface-0, #fff);
  transition: background-color var(--transition-fast, 0.2s);
}

.accordion__summary:hover {
  background-color: var(--color-surface-50, #f8f9fa);
}

.accordion__summary::-webkit-details-marker {
  display: none;
}

.accordion__summary i {
  margin-right: var(--spacing-2, 8px);
  color: var(--color-clinical-accent, #1976d2);
}

.accordion__icon {
  transition: transform 0.3s ease;
  color: var(--color-text-muted, #666) !important;
}

details[open] .accordion__icon {
  transform: rotate(180deg);
}

.accordion__content {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  background-color: var(--color-surface-50, #f8f9fa);
}

/* Lists */
.item-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.item-list__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-2, 8px) 0;
  border-bottom: 1px dashed var(--color-surface-200, #e0e0e0);
}

.item-list__item:last-child {
  border-bottom: none;
}

.item-list__name {
  font-weight: 500;
}

.item-list__meta {
  font-size: 0.85rem;
  color: var(--color-text-muted, #666);
}

.badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.badge--alta { background-color: var(--color-clinical-danger-bg, #ffebee); color: var(--color-clinical-danger, #d32f2f); }
.badge--media { background-color: var(--color-clinical-warning-bg, #fff8e1); color: var(--color-clinical-warning, #854D0E); }
.badge--baja { background-color: var(--color-success-50, #e8f5e9); color: var(--color-success-800, #388e3c); }
.badge--activa { background-color: var(--color-clinical-danger-bg, #ffebee); color: var(--color-clinical-danger, #d32f2f); }
.badge--resuelta { background-color: var(--color-success-50, #e8f5e9); color: var(--color-success-800, #388e3c); }

.history-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.history-list__item {
  padding: var(--spacing-2, 8px) 0;
  border-bottom: 1px dashed var(--color-surface-200, #e0e0e0);
}

.history-list__item:last-child {
  border-bottom: none;
}

.history-list__header {
  display: flex;
  justify-content: space-between;
  margin-bottom: var(--spacing-1, 4px);
  font-size: 0.85rem;
  color: var(--color-text-muted, #666);
}

.history-list__summary {
  margin: 0;
  font-size: 0.9rem;
}

/* RIGHT PANEL */
.consultation__right-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4, 16px);
  min-width: 0;
}

/* Chat Section */
.chat {
  background-color: var(--color-surface-0, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  display: flex;
  flex-direction: column;
  height: 40%;
  min-height: 250px;
}

.chat--full-height {
  height: 100% !important;
}

.chat__header {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-bottom: 1px solid var(--color-surface-200, #e0e0e0);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chat__header h3 {
  margin: 0;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
}

.chat-header__status {
  font-size: 0.85rem;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 4px;
}

.status-indicator--connecting { color: var(--color-clinical-warning, #ca8a04); }
.status-indicator--connected { color: var(--color-success-800, #166534); }
.status-indicator--error { color: var(--color-clinical-danger, #dc2626); }

.chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: var(--spacing-4, 16px);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3, 12px);
}

.chat__message {
  max-width: 80%;
  padding: var(--spacing-2, 8px) var(--spacing-3, 12px);
  border-radius: var(--radius-md, 8px);
}

.chat__message--self {
  align-self: flex-end;
  background-color: var(--color-primary-50, #e3f2fd);
  border-bottom-right-radius: 0;
}

.chat__message--other {
  align-self: flex-start;
  background-color: var(--color-surface-50, #f8f9fa);
  border: 1px solid var(--color-surface-200, #e0e0e0);
  border-bottom-left-radius: 0;
}

.chat__message-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--spacing-3, 12px);
  margin-bottom: var(--spacing-1, 4px);
  font-size: 0.75rem;
  color: var(--color-text-muted, #666);
}

.chat__message-sender {
  font-weight: 600;
}

.chat__message-text {
  font-size: 0.95rem;
  line-height: 1.4;
  word-break: break-word;
}

.chat__input-area {
  padding: var(--spacing-3, 12px);
  border-top: 1px solid var(--color-surface-200, #e0e0e0);
  display: flex;
  gap: var(--spacing-2, 8px);
}

.chat__input {
  flex: 1;
  padding: var(--spacing-2, 8px) var(--spacing-3, 12px);
  border: 1px solid var(--color-border, #64748B);
  border-radius: var(--radius-full, 99px);
  font-family: inherit;
  font-size: 0.95rem;
}

.chat__input:focus-visible {
  outline: 2px solid var(--color-focus-ring, #1976d2);
  outline-offset: 2px;
}

.chat__send-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--color-clinical-accent, #1976d2);
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color var(--transition-fast, 0.2s);
}

.chat__send-btn:hover:not(:disabled) {
  background-color: var(--color-primary-900, #1565c0);
}

.chat__send-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.chat__send-btn:focus-visible {
  outline: 2px solid var(--color-focus-ring, #1976d2);
  outline-offset: 2px;
}

/* SOAP Section */
.soap {
  background-color: var(--color-surface-0, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
}

.soap__header {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-bottom: 1px solid var(--color-surface-200, #e0e0e0);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.soap__header h3 {
  margin: 0;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
}

.soap__autosave {
  font-size: 0.85rem;
  color: var(--color-text-muted, #666);
  display: flex;
  align-items: center;
  gap: 4px;
}

.soap__autosave--success {
  color: var(--color-success-800, #388e3c);
}

.soap__fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: auto;
  gap: var(--spacing-3, 12px);
  padding: var(--spacing-4, 16px);
  flex: 1;
  overflow-y: auto;
}

@media (max-width: 1200px) {
  .soap__fields {
    grid-template-columns: 1fr;
  }
}

.soap-alert {
  grid-column: 1 / -1;
  padding: var(--spacing-3, 12px);
  border-radius: var(--radius-sm, 4px);
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
}

.soap-alert--error {
  background-color: var(--color-clinical-danger-bg, #fef2f2);
  color: var(--color-clinical-danger, #b91c1c);
  border: 1px solid var(--color-clinical-danger-bg);
}

.soap__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1, 4px);
}

.soap__field label {
  font-weight: 600;
  font-size: 0.9rem;
}

.soap__field textarea,
.soap__field input {
  flex: 1;
  min-height: 100px;
  padding: var(--spacing-2, 8px);
  border: 1px solid var(--color-border, #64748B);
  border-radius: var(--radius-sm, 4px);
  font-family: inherit;
  resize: none;
}

.soap__field input {
  min-height: auto;
}

.soap__field textarea:focus-visible,
.soap__field input:focus-visible {
  outline: 2px solid var(--color-focus-ring, #1976d2);
  outline-offset: 1px;
}

.soap__textarea--signed {
  background-color: var(--color-surface-50, #f8f9fa);
  border: 2px dashed var(--color-surface-200, #e0e0e0) !important;
  color: var(--color-text-muted, #666);
  cursor: not-allowed;
}

.soap__textarea--signed:focus-visible {
  outline: none;
}

.soap__actions {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-top: 1px solid var(--color-surface-200, #e0e0e0);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3, 12px);
}

.soap__consent {
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
  font-size: 0.9rem;
  cursor: pointer;
}

.soap__buttons {
  display: flex;
  justify-content: flex-end;
  gap: var(--spacing-3, 12px);
}

.btn {
  padding: var(--spacing-2, 8px) var(--spacing-4, 16px);
  border-radius: var(--radius-md, 8px);
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
  border: none;
  transition: all var(--transition-fast, 0.2s);
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn:focus-visible {
  outline: 2px solid var(--color-focus-ring, #1976d2);
  outline-offset: 2px;
}

.btn--secondary {
  background-color: transparent;
  color: var(--color-clinical-accent, #1976d2);
  border: 1px solid var(--color-clinical-accent, #1976d2);
}

.btn--secondary:hover:not(:disabled) {
  background-color: var(--color-primary-50, #e3f2fd);
}

.btn--primary {
  background-color: var(--color-clinical-accent, #1976d2);
  color: white;
}

.btn--primary:hover:not(:disabled) {
  background-color: var(--color-primary-900, #1565c0);
}

.soap__integrity-banner {
  margin: var(--spacing-3, 12px) var(--spacing-4, 16px);
  padding: var(--spacing-3, 12px);
  background-color: var(--color-success-50, #e8f5e9);
  border: 1px solid var(--color-success-800, #388e3c);
  border-radius: var(--radius-md, 8px);
  display: flex;
  align-items: center;
  gap: var(--spacing-3, 12px);
  color: var(--color-success-800, #1b5e20);
}

.banner-icon {
  font-size: 24px;
}

.banner-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.banner-content strong {
  font-size: 1rem;
}

.banner-content p {
  margin: 0;
  font-size: 0.85rem;
}

.hash-text {
  font-family: monospace;
  font-size: 0.75rem !important;
  word-break: break-all;
  opacity: 0.8;
}

/* Amendments Styling */
.soap__amendments-display {
  padding: var(--spacing-4, 16px);
  border-top: 1px solid var(--color-surface-200, #e0e0e0);
  background-color: var(--color-surface-50, #f8f9fa);
  overflow-y: auto;
}

.amendment-item {
  padding: var(--spacing-3, 12px);
  background-color: var(--color-surface-0, #fff);
  border: 1px solid var(--color-surface-200, #e0e0e0);
  border-radius: var(--radius-md, 8px);
  margin-bottom: var(--spacing-3, 12px);
  box-shadow: var(--shadow-sm);
}

.amendment-item__header {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: var(--color-clinical-accent, #1976d2);
  margin-bottom: var(--spacing-2, 8px);
}

.amendment-item__reason {
  margin: 0 0 4px 0;
  font-size: 0.9rem;
}

.amendment-item__content {
  margin: 0;
  font-size: 0.9rem;
  color: var(--color-text-strong, #333);
}

.amendment-section {
  border-top: 1px solid var(--color-surface-200, #e0e0e0);
  padding-top: var(--spacing-4, 16px);
  margin-top: var(--spacing-4, 16px);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3, 12px);
}

.amendment-section h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.btn-amend {
  width: 100%;
}
</style>
