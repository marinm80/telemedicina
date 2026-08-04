<!--
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
-->
<template>
  <AppLayout>
    <div class="consultation">
      <!-- Loading State -->
      <div v-if="isLoading" class="consultation__loading">
        <SpinnerLoader />
        <p>{{ translate('consultation.loading') || 'Cargando sala de consulta...' }}</p>
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
                {{ patient.age }} años • ID: {{ patient.id }} • Consulta #{{ patient.consultationNumber }}
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
          <section class="chat">
            <header class="chat__header">
              <h3><i class="pi pi-comments"></i> Chat de Sesión</h3>
            </header>
            <div class="chat__messages" ref="chatMessagesRef">
              <div 
                v-for="msg in chatMessages" 
                :key="msg.id" 
                :class="['chat__message', msg.senderId === doctorId ? 'chat__message--self' : 'chat__message--other']"
              >
                <div class="chat__message-header">
                  <span class="chat__message-sender">{{ msg.senderName }}</span>
                  <span class="chat__message-time">{{ msg.timestamp }}</span>
                </div>
                <div class="chat__message-text">{{ msg.text }}</div>
              </div>
            </div>
            <div class="chat__input-area">
              <input 
                v-model="newMessage" 
                type="text" 
                class="chat__input" 
                placeholder="Escribe un mensaje..."
                @keyup.enter="sendMessage"
              />
              <button class="chat__send-btn" @click="sendMessage">
                <i class="pi pi-send"></i>
              </button>
            </div>
          </section>

          <!-- BOTTOM: SOAP Note Editor -->
          <section class="soap">
            <header class="soap__header">
              <h3><i class="pi pi-file-edit"></i> Nota SOAP</h3>
              <span v-if="isAutoSaving" class="soap__autosave">
                <i class="pi pi-sync pi-spin"></i> Guardando...
              </span>
              <span v-else-if="lastSaved" class="soap__autosave soap__autosave--success">
                <i class="pi pi-check"></i> Guardado {{ lastSaved }}
              </span>
            </header>

            <div class="soap__fields">
              <div class="soap__field">
                <label for="soap-s">S - Subjetivo</label>
                <textarea 
                  id="soap-s" 
                  v-model="soapData.subjetivo" 
                  :readonly="isSigned"
                  :class="{'soap__textarea--signed': isSigned}"
                  placeholder="Motivo de consulta, síntomas referidos por el paciente..."
                ></textarea>
              </div>
              <div class="soap__field">
                <label for="soap-o">O - Objetivo</label>
                <textarea 
                  id="soap-o" 
                  v-model="soapData.objetivo" 
                  :readonly="isSigned"
                  :class="{'soap__textarea--signed': isSigned}"
                  placeholder="Signos vitales, hallazgos de exploración física..."
                ></textarea>
              </div>
              <div class="soap__field">
                <label for="soap-a">A - Análisis</label>
                <textarea 
                  id="soap-a" 
                  v-model="soapData.analisis" 
                  :readonly="isSigned"
                  :class="{'soap__textarea--signed': isSigned}"
                  placeholder="Diagnóstico presuntivo, evaluación clínica..."
                ></textarea>
              </div>
              <div class="soap__field">
                <label for="soap-p">P - Plan</label>
                <textarea 
                  id="soap-p" 
                  v-model="soapData.plan" 
                  :readonly="isSigned"
                  :class="{'soap__textarea--signed': isSigned}"
                  placeholder="Tratamiento, estudios solicitados, seguimiento..."
                ></textarea>
              </div>
            </div>

            <div v-if="!isSigned" class="soap__actions">
              <label class="soap__consent">
                <input type="checkbox" v-model="hasConsent" />
                <span>Confirmo el consentimiento informado del paciente.</span>
              </label>
              <div class="soap__buttons">
                <button class="btn btn--secondary" @click="saveDraft">
                  Guardar Borrador
                </button>
                <button 
                  class="btn btn--primary" 
                  :disabled="!hasConsent || !isFormValid" 
                  @click="signNote"
                >
                  <i class="pi pi-lock"></i> FIRMAR NOTA CLÍNICA
                </button>
              </div>
            </div>

            <!-- Integrity Banner -->
            <div v-else class="soap__integrity-banner">
              <div class="banner-icon">
                <i class="pi pi-shield"></i>
              </div>
              <div class="banner-content">
                <strong>Nota Clínica Firmada Digitalmente</strong>
                <p>Médico: {{ doctorId }} | Fecha: {{ signedTimestamp }}</p>
                <p class="hash-text">SHA-256: {{ mockHash }}</p>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick, watch, inject } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SpinnerLoader from '@/components/ui/SpinnerLoader.vue';
import ErrorFallback from '@/components/ui/ErrorFallback.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

// Mock dependencies injection to bypass potential missing items
const translate = inject('i18nKey', (key: string) => '') as (key: string) => string;
import { useAppState } from '@/composables/useAppState';
const fetcherMock = async (signal: AbortSignal) => [] as any[];
const { estado, error: appError, estaVacio, cargar } = useAppState(fetcherMock);

// Interfaces
interface Allergy {
  id: string;
  name: string;
  severity: 'alta' | 'media' | 'baja';
}

interface Condition {
  id: string;
  name: string;
  status: 'activa' | 'resuelta';
}

interface Medication {
  id: string;
  name: string;
  dosage: string;
}

interface HistoryItem {
  id: string;
  date: string;
  doctor: string;
  summary: string;
}

interface Patient {
  id: string;
  name: string;
  age: number;
  consultationNumber: string;
  allergies: Allergy[];
  conditions: Condition[];
  medications: Medication[];
  history: HistoryItem[];
}

interface ChatMessage {
  id: string;
  senderId: string;
  senderName: string;
  text: string;
  timestamp: string;
}

interface SoapNote {
  subjetivo: string;
  objetivo: string;
  analisis: string;
  plan: string;
}

// State variables
const isLoading = ref(true);
const hasError = ref(false);
const errorMessage = ref('');
const isEmpty = ref(false);

const doctorId = ref('DR-7842');

const patient = reactive<Patient>({
  id: 'PAC-1092',
  name: 'María Fernández',
  age: 42,
  consultationNumber: 'CON-5582',
  allergies: [
    { id: '1', name: 'Penicilina', severity: 'alta' },
    { id: '2', name: 'Polvo', severity: 'baja' }
  ],
  conditions: [
    { id: '1', name: 'Hipertensión Arterial', status: 'activa' },
    { id: '2', name: 'Asma leve', status: 'activa' },
    { id: '3', name: 'Apendicitis', status: 'resuelta' }
  ],
  medications: [
    { id: '1', name: 'Losartán', dosage: '50mg c/12h' },
    { id: '2', name: 'Salbutamol', dosage: '2 puffs PRN' }
  ],
  history: [
    { id: '1', date: '10/05/2026', doctor: 'Dr. R. Marín', summary: 'Control de hipertensión. Ajuste de dosis.' },
    { id: '2', date: '15/02/2026', doctor: 'Dra. S. Gómez', summary: 'Consulta por cuadro respiratorio agudo.' }
  ]
});

// Chat state
const chatMessages = ref<ChatMessage[]>([
  { id: 'm1', senderId: 'PAC-1092', senderName: 'María Fernández', text: 'Buenos días doctor, ya estoy lista.', timestamp: '10:00 AM' },
  { id: 'm2', senderId: 'DR-7842', senderName: 'Dr. Rafael', text: 'Buenos días María, ¿cómo te has sentido con el medicamento?', timestamp: '10:02 AM' }
]);
const newMessage = ref('');
const chatMessagesRef = ref<HTMLElement | null>(null);

// SOAP State
const soapData = reactive<SoapNote>({
  subjetivo: '',
  objetivo: '',
  analisis: '',
  plan: ''
});

const isAutoSaving = ref(false);
const lastSaved = ref('');
let saveTimeout: number;

const hasConsent = ref(false);
const isSigned = ref(false);
const signedTimestamp = ref('');
const mockHash = ref('');

const isFormValid = computed(() => {
  return soapData.subjetivo.trim() !== '' && 
         soapData.objetivo.trim() !== '' && 
         soapData.analisis.trim() !== '' && 
         soapData.plan.trim() !== '';
});

// Lifecycle
onMounted(() => {
  initConsultation();
});

// Methods
const initConsultation = () => {
  isLoading.value = true;
  hasError.value = false;
  
  // Simulate data fetch
  setTimeout(() => {
    isLoading.value = false;
    scrollToBottom();
  }, 1000);
};

const scrollToBottom = async () => {
  await nextTick();
  if (chatMessagesRef.value) {
    chatMessagesRef.value.scrollTop = chatMessagesRef.value.scrollHeight;
  }
};

const sendMessage = () => {
  if (newMessage.value.trim() === '') return;
  
  const now = new Date();
  const timeString = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
  
  chatMessages.value.push({
    id: `m${Date.now()}`,
    senderId: doctorId.value,
    senderName: 'Dr. Rafael',
    text: newMessage.value,
    timestamp: timeString
  });
  
  newMessage.value = '';
  scrollToBottom();
  
  // Simulate patient response
  setTimeout(() => {
    chatMessages.value.push({
      id: `m${Date.now()}`,
      senderId: patient.id,
      senderName: patient.name,
      text: 'Entendido, doctor.',
      timestamp: `${new Date().getHours()}:${new Date().getMinutes().toString().padStart(2, '0')}`
    });
    scrollToBottom();
  }, 3000);
};

// Auto-save logic
watch(soapData, () => {
  if (isSigned.value) return;
  
  isAutoSaving.value = true;
  clearTimeout(saveTimeout);
  
  saveTimeout = window.setTimeout(() => {
    isAutoSaving.value = false;
    const now = new Date();
    lastSaved.value = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
  }, 1500);
}, { deep: true });

const saveDraft = () => {
  isAutoSaving.value = true;
  setTimeout(() => {
    isAutoSaving.value = false;
    const now = new Date();
    lastSaved.value = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
  }, 500);
};

const signNote = () => {
  if (!hasConsent.value || !isFormValid.value) return;
  
  isSigned.value = true;
  const now = new Date();
  signedTimestamp.value = now.toLocaleString();
  mockHash.value = Array.from({length: 64}, () => Math.floor(Math.random()*16).toString(16)).join('');
};

</script>

<style scoped>
.consultation {
  display: flex;
  flex-direction: column;
  height: 100%;
  width: 100%;
  background-color: var(--color-background-soft, #f8f9fa);
  color: var(--color-text-primary, #333);
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
  background-color: var(--color-surface, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

@media (max-width: 992px) {
  .consultation__left-panel {
    flex: none;
    height: 400px; /* Fixed height on mobile for scrollable content */
  }
}

.patient-header {
  display: flex;
  align-items: center;
  padding: var(--spacing-4, 16px);
  background-color: var(--color-primary-light, #e3f2fd);
  border-bottom: 1px solid var(--color-border, #e0e0e0);
}

.patient-header__avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background-color: var(--color-primary, #1976d2);
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
  color: var(--color-text-secondary, #666);
}

.patient-accordions {
  overflow-y: auto;
  flex: 1;
}

.accordion {
  border-bottom: 1px solid var(--color-border, #e0e0e0);
}

.accordion__summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  cursor: pointer;
  font-weight: 600;
  list-style: none;
  background-color: var(--color-surface, #fff);
  transition: background-color var(--transition-fast, 0.2s);
}

.accordion__summary:hover {
  background-color: var(--color-background-soft, #f8f9fa);
}

.accordion__summary::-webkit-details-marker {
  display: none;
}

.accordion__summary i {
  margin-right: var(--spacing-2, 8px);
  color: var(--color-primary, #1976d2);
}

.accordion__icon {
  transition: transform 0.3s ease;
  color: var(--color-text-secondary, #666) !important;
}

details[open] .accordion__icon {
  transform: rotate(180deg);
}

.accordion__content {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  background-color: var(--color-background-soft, #f8f9fa);
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
  border-bottom: 1px dashed var(--color-border, #e0e0e0);
}

.item-list__item:last-child {
  border-bottom: none;
}

.item-list__name {
  font-weight: 500;
}

.item-list__meta {
  font-size: 0.85rem;
  color: var(--color-text-secondary, #666);
}

.badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.badge--alta { background-color: var(--color-error-light, #ffebee); color: var(--color-error, #d32f2f); }
.badge--media { background-color: var(--color-warning-light, #fff8e1); color: var(--color-warning, #f57f17); }
.badge--baja { background-color: var(--color-success-light, #e8f5e9); color: var(--color-success, #388e3c); }
.badge--activa { background-color: var(--color-error-light, #ffebee); color: var(--color-error, #d32f2f); }
.badge--resuelta { background-color: var(--color-success-light, #e8f5e9); color: var(--color-success, #388e3c); }

.history-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.history-list__item {
  padding: var(--spacing-2, 8px) 0;
  border-bottom: 1px dashed var(--color-border, #e0e0e0);
}

.history-list__item:last-child {
  border-bottom: none;
}

.history-list__header {
  display: flex;
  justify-content: space-between;
  margin-bottom: var(--spacing-1, 4px);
  font-size: 0.85rem;
  color: var(--color-text-secondary, #666);
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
  min-width: 0; /* Prevent flex overflow */
}

/* Chat Section */
.chat {
  background-color: var(--color-surface, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  display: flex;
  flex-direction: column;
  height: 40%;
  min-height: 250px;
}

.chat__header {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-bottom: 1px solid var(--color-border, #e0e0e0);
}

.chat__header h3 {
  margin: 0;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: var(--spacing-2, 8px);
}

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
  background-color: var(--color-primary-light, #e3f2fd);
  border-bottom-right-radius: 0;
}

.chat__message--other {
  align-self: flex-start;
  background-color: var(--color-background-soft, #f8f9fa);
  border: 1px solid var(--color-border, #e0e0e0);
  border-bottom-left-radius: 0;
}

.chat__message-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--spacing-3, 12px);
  margin-bottom: var(--spacing-1, 4px);
  font-size: 0.75rem;
  color: var(--color-text-secondary, #666);
}

.chat__message-sender {
  font-weight: 600;
}

.chat__message-text {
  font-size: 0.95rem;
  line-height: 1.4;
}

.chat__input-area {
  padding: var(--spacing-3, 12px);
  border-top: 1px solid var(--color-border, #e0e0e0);
  display: flex;
  gap: var(--spacing-2, 8px);
}

.chat__input {
  flex: 1;
  padding: var(--spacing-2, 8px) var(--spacing-3, 12px);
  border: 1px solid var(--color-border, #e0e0e0);
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
  background-color: var(--color-primary, #1976d2);
  color: white;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color var(--transition-fast, 0.2s);
}

.chat__send-btn:hover {
  background-color: var(--color-primary-dark, #1565c0);
}

.chat__send-btn:focus-visible {
  outline: 2px solid var(--color-focus-ring, #1976d2);
  outline-offset: 2px;
}

/* SOAP Section */
.soap {
  background-color: var(--color-surface, #ffffff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.1));
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
}

.soap__header {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-bottom: 1px solid var(--color-border, #e0e0e0);
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
  color: var(--color-text-secondary, #666);
  display: flex;
  align-items: center;
  gap: 4px;
}

.soap__autosave--success {
  color: var(--color-success, #388e3c);
}

.soap__fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
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

.soap__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1, 4px);
}

.soap__field label {
  font-weight: 600;
  font-size: 0.9rem;
}

.soap__field textarea {
  flex: 1;
  min-height: 100px;
  padding: var(--spacing-2, 8px);
  border: 1px solid var(--color-border, #e0e0e0);
  border-radius: var(--radius-sm, 4px);
  font-family: inherit;
  resize: none;
}

.soap__field textarea:focus-visible {
  outline: 2px solid var(--color-focus-ring, #1976d2);
  outline-offset: 1px;
  border-color: transparent;
}

.soap__textarea--signed {
  background-color: var(--color-background-soft, #f8f9fa);
  border: 2px dashed var(--color-border, #e0e0e0) !important;
  color: var(--color-text-secondary, #666);
  cursor: not-allowed;
}

.soap__textarea--signed:focus-visible {
  outline: none;
}

.soap__actions {
  padding: var(--spacing-3, 12px) var(--spacing-4, 16px);
  border-top: 1px solid var(--color-border, #e0e0e0);
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
  color: var(--color-primary, #1976d2);
  border: 1px solid var(--color-primary, #1976d2);
}

.btn--secondary:hover:not(:disabled) {
  background-color: var(--color-primary-light, #e3f2fd);
}

.btn--primary {
  background-color: var(--color-primary, #1976d2);
  color: white;
}

.btn--primary:hover:not(:disabled) {
  background-color: var(--color-primary-dark, #1565c0);
}

.soap__integrity-banner {
  margin: var(--spacing-3, 12px) var(--spacing-4, 16px);
  padding: var(--spacing-3, 12px);
  background-color: var(--color-success-light, #e8f5e9);
  border: 1px solid var(--color-success, #388e3c);
  border-radius: var(--radius-md, 8px);
  display: flex;
  align-items: center;
  gap: var(--spacing-3, 12px);
  color: var(--color-success-dark, #1b5e20);
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
</style>
