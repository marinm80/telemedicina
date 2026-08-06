<!--
  ====================================================================
  FloatingAssistant — Agente conversacional inteligente sin LLM
  State machine + emergency detection + triage + demo message preview
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
<template>
  <div class="floating-assistant">
    <!-- Demo Message Preview Modal -->
    <Transition name="fade">
      <div v-if="showDemoModal" class="demo-modal-overlay" @click.self="showDemoModal = false">
        <div class="demo-modal">
          <div class="demo-modal__header">
            <div class="demo-modal__badge">📧 Mensaje de demostración — no enviado</div>
            <button class="demo-modal__close" @click="showDemoModal = false"><i class="pi pi-times"></i></button>
          </div>
          <div class="demo-modal__subject">
            <strong>Asunto:</strong> {{ demoMessage?.subject }}
          </div>
          <div class="demo-modal__recipients">
            <div><strong>Para:</strong> {{ demoMessage?.recipientPatient }} (paciente)</div>
            <div><strong>CC:</strong> {{ demoMessage?.recipientDoctor }} (médico)</div>
          </div>
          <div class="demo-modal__body" v-html="demoMessage?.bodyHtml"></div>
          <div class="demo-modal__actions">
            <button class="demo-btn demo-btn--copy" @click="copyDemoText">
              <i class="pi pi-copy"></i> {{ copyLabel }}
            </button>
            <button class="demo-btn demo-btn--download" @click="downloadDemoText">
              <i class="pi pi-download"></i> Descargar .txt
            </button>
            <button class="demo-btn demo-btn--send" disabled title="Requiere integración de email configurada">
              <i class="pi pi-send"></i> Enviar real (demo)
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Chat Panel -->
    <Transition name="slide-up">
      <div v-if="isOpen" class="chat-panel">
        <header class="chat-header">
          <div class="chat-header-title">
            <i class="pi pi-sparkles"></i>
            <span>Asistente Salvia</span>
            <span class="chat-header-badge" v-if="agentCtx.emergencyDetected">🚨</span>
          </div>
          <div class="chat-header-actions">
            <button v-if="currentStateId !== 'WELCOME' && currentStateId !== 'IDLE'" class="chat-restart" @click="restartFlow" title="Reiniciar conversación">
              <i class="pi pi-refresh"></i>
            </button>
            <button class="chat-close" @click="toggleChat">
              <i class="pi pi-times"></i>
            </button>
          </div>
        </header>

        <div class="chat-messages" ref="messagesContainer">
          <div v-for="(msg, index) in messages" :key="index" :class="['message-wrapper', msg.role]">
            <div :class="['message-bubble', { 'message-bubble--emergency': msg.isEmergency }]">
              <p v-html="msg.text"></p>
            </div>
          </div>

          <!-- Quick options -->
          <div v-if="currentQuickOptions.length > 0 && !isTyping" class="interactive-block">
            <div class="chips-grid">
              <button
                v-for="opt in currentQuickOptions"
                :key="opt.value"
                :class="['chip-btn', { 'chip-btn--danger': opt.value === 'yes_emergency' }]"
                @click="handleQuickOption(opt)"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>

          <!-- Specialty selection (special interactive) -->
          <div v-if="currentStateId === 'SELECT_SPECIALTY'" class="interactive-block">
            <div class="chips-grid">
              <button v-for="sp in specialties" :key="sp.id" class="chip-btn" @click="selectSpecialty(sp)">
                {{ sp.name }}
              </button>
            </div>
          </div>

          <!-- Doctor selection -->
          <div v-if="currentStateId === 'SELECT_DOCTOR'" class="interactive-block">
            <div v-if="matchingDoctors.length === 0" class="empty-notice">No hay médicos disponibles para esta especialidad.</div>
            <div v-else class="doctor-cards">
              <button v-for="doc in matchingDoctors" :key="doc.doctor_profile_id" class="doctor-card" @click="selectDoctor(doc)">
                <div class="doctor-avatar" :style="{ backgroundColor: avatarColor(doc.full_name) }">{{ initials(doc.full_name) }}</div>
                <div class="doctor-info">
                  <strong>{{ doc.full_name }}</strong>
                  <small>{{ doc.specialties.join(', ') }}</small>
                </div>
              </button>
            </div>
          </div>

          <!-- Date selection -->
          <div v-if="currentStateId === 'SELECT_DATE'" class="interactive-block">
            <input type="date" class="date-input" :min="minDate" :max="maxDate" v-model="selectedDate" @change="selectDate" />
          </div>

          <!-- Slot selection -->
          <div v-if="currentStateId === 'SELECT_SLOT'" class="interactive-block">
            <div v-if="loadingSlots" class="loading-dots"><span></span><span></span><span></span></div>
            <div v-else-if="availableSlots.length === 0" class="empty-notice">
              No hay horarios disponibles para esta fecha.
              <button class="chip-btn" @click="transitionTo('SELECT_DATE'); addMessage('assistant', '¿Qué otro día te conviene?')">Elegir otra fecha</button>
            </div>
            <div v-else class="slots-grid">
              <button v-for="slot in availableSlots" :key="slot.start" class="slot-btn" :class="{ 'slot-btn--unavailable': !slot.available }" :disabled="!slot.available" @click="selectSlot(slot)">
                {{ slot.local_start }}
              </button>
            </div>
          </div>

          <!-- Confirmation card -->
          <div v-if="currentStateId === 'CONFIRMATION'" class="interactive-block">
            <div class="confirm-card">
              <div class="confirm-row"><strong>Médico:</strong> {{ agentCtx.bookingData.doctorName }}</div>
              <div class="confirm-row"><strong>Especialidad:</strong> {{ agentCtx.bookingData.specialtyName }}</div>
              <div class="confirm-row"><strong>Fecha:</strong> {{ agentCtx.bookingData.fecha }}</div>
              <div class="confirm-row"><strong>Hora:</strong> {{ agentCtx.bookingData.slotLocalTime }}</div>
              <div class="confirm-row"><strong>Motivo:</strong> {{ agentCtx.patientData.motivo }}</div>
              <div v-if="agentCtx.patientData.symptomsSeverity" class="confirm-row"><strong>Severidad:</strong> {{ agentCtx.patientData.symptomsSeverity }}</div>
              <div v-if="agentCtx.patientData.allergies" class="confirm-row"><strong>Alergias:</strong> {{ agentCtx.patientData.allergies }}</div>
              <div v-if="holdSeconds > 0" class="hold-timer" :class="{ 'hold-timer--warning': holdSeconds < 60 }">
                ⏱️ Reserva temporal: <strong>{{ formatHoldTime(holdSeconds) }}</strong>
              </div>
              <div class="confirm-actions">
                <button class="confirm-btn confirm-btn--yes" @click="confirmBooking" :disabled="submitting">
                  {{ submitting ? 'Agendando...' : '✅ Confirmar Cita' }}
                </button>
                <button class="confirm-btn confirm-btn--no" @click="cancelBooking">❌ Cancelar</button>
              </div>
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isTyping" class="message-wrapper assistant">
            <div class="message-bubble typing-indicator"><span></span><span></span><span></span></div>
          </div>
        </div>

        <footer class="chat-footer">
          <!-- Escalation link -->
          <div v-if="currentStateId !== 'EMERGENCY_STOP' && currentStateId !== 'ESCALATE_HUMAN'" class="chat-escalate">
            <button class="escalate-link" @click="escalateToHuman">🙋 Hablar con un agente humano</button>
          </div>
          <form @submit.prevent="handleUserInput" class="chat-input-form">
            <input type="text" v-model="userInput" :placeholder="currentPlaceholder" class="chat-input" :disabled="isTyping || !currentInputEnabled" />
            <button type="submit" class="chat-send" :disabled="!userInput.trim() || isTyping || !currentInputEnabled">
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
import { ref, reactive, computed, nextTick, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  createStateMachine, createAgentContext, createAuditLog, logAudit,
  detectEmergency,
  type AgentStateId, type AgentContext, type QuickOption, type AuditEntry,
} from '@/lib/agentStateMachine';
import { generateDemoMessage, type DemoMessage } from '@/lib/agentDemoMessage';
import { evaluateTriage, getTriageLabel } from '@/lib/agentTriageRules';
import { scoreAndSortSlots, getTopRecommendations, buildRecommendationHtml } from '@/lib/agentSlotScoring';

// ── Types ──
interface Message {
  role: 'assistant' | 'user';
  text: string;
  isEmergency?: boolean;
}

interface SlotInfo {
  start: string; end: string; local_start: string; local_end: string; available: boolean;
}

// ── State Machine ──
const stateMachine = createStateMachine();
const agentCtx = reactive<AgentContext>(createAgentContext());
const auditLog = ref<AuditEntry[]>(createAuditLog());
const currentStateId = ref<AgentStateId>('WELCOME');

// ── UI State ──
const isOpen = ref(false);
const isTyping = ref(false);
const userInput = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const loadingSlots = ref(false);
const submitting = ref(false);
const selectedDate = ref('');
const availableSlots = ref<SlotInfo[]>([]);
const showDemoModal = ref(false);
const demoMessage = ref<DemoMessage | null>(null);
const copyLabel = ref('Copiar texto');
const holdSeconds = ref(0);
const holdTimerInterval = ref<number>(0);

const messages = ref<Message[]>([]);

// ── Inertia shared data ──
const page = usePage();
const specialties = computed(() => (page.props as any).booking?.specialties || []);
const allDoctors = computed(() => (page.props as any).booking?.doctors || []);
const authUser = computed(() => (page.props as any).auth?.user);

const matchingDoctors = computed(() => {
  if (!agentCtx.bookingData.specialtyName) return allDoctors.value;
  return allDoctors.value.filter((d: any) => d.specialties.includes(agentCtx.bookingData.specialtyName));
});

// ── Computed from current state ──
const currentState = computed(() => stateMachine[currentStateId.value]);
const currentQuickOptions = computed(() => {
  // Don't show quick options for states with custom interactive UI
  if (['SELECT_SPECIALTY', 'SELECT_DOCTOR', 'SELECT_DATE', 'SELECT_SLOT', 'CONFIRMATION', 'TRIAGE_DECISION'].includes(currentStateId.value)) return [];
  return currentState.value?.quickOptions || [];
});
const currentInputEnabled = computed(() => {
  if (['SELECT_SPECIALTY', 'SELECT_DOCTOR', 'SELECT_DATE', 'SELECT_SLOT', 'CONFIRMATION'].includes(currentStateId.value)) return false;
  return currentState.value?.inputEnabled ?? false;
});
const currentPlaceholder = computed(() => currentState.value?.inputPlaceholder || 'Escribe tu mensaje...');

const minDate = computed(() => {
  const d = new Date(); d.setDate(d.getDate() + 1); return d.toISOString().split('T')[0];
});
const maxDate = computed(() => {
  const d = new Date(); d.setDate(d.getDate() + 60); return d.toISOString().split('T')[0];
});

// ── Helpers ──
function addMessage(role: 'assistant' | 'user', text: string, isEmergency = false) {
  messages.value.push({ role, text, isEmergency });
  scrollToBottom();
}

async function scrollToBottom() {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
}

function initials(name: string): string {
  return name.split(' ').map(w => w.charAt(0)).slice(0, 2).join('').toUpperCase();
}

function avatarColor(name: string): string {
  const colors = ['#1D4ED8', '#15803D', '#B91C1C', '#854D0E', '#7C3AED', '#0E7490', '#BE185D'];
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return colors[Math.abs(hash) % colors.length]!;
}

function formatHoldTime(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${s.toString().padStart(2, '0')}`;
}

function clearHoldTimer() {
  if (holdTimerInterval.value) {
    clearInterval(holdTimerInterval.value);
    holdTimerInterval.value = 0;
  }
  holdSeconds.value = 0;
}

function simulateTyping(responseText: string, delay = 800, isEmergency = false) {
  isTyping.value = true;
  scrollToBottom();
  setTimeout(() => {
    isTyping.value = false;
    addMessage('assistant', responseText, isEmergency);
  }, delay);
}

// ── State transitions ──
function transitionTo(stateId: AgentStateId) {
  currentStateId.value = stateId;
  logAudit(auditLog.value, stateId, 'TRANSITION');

  const state = stateMachine[stateId];
  if (!state) return;

  // Auto-execute TRIAGE_DECISION (no message, just rules)
  if (stateId === 'TRIAGE_DECISION') {
    const triageMsg = buildTriageMessage();
    simulateTyping(triageMsg, 1200);
    setTimeout(() => {
      const nextState = state.next('', agentCtx);
      transitionTo(nextState);
    }, 1500);
    return;
  }

  // Show state message
  if (state.message) {
    const isEmg = stateId === 'EMERGENCY_STOP';
    simulateTyping(state.message, isEmg ? 300 : 800, isEmg);
    if (isEmg) logAudit(auditLog.value, stateId, 'EMERGENCY_DETECTED', 'Flujo detenido por seguridad');
  }
}

function buildTriageMessage(): string {
  // Use the real triage rules engine
  const evaluation = evaluateTriage(agentCtx.patientData);
  agentCtx.triageResult = evaluation.result;
  const label = getTriageLabel(evaluation.result);
  logAudit(auditLog.value, 'TRIAGE_DECISION', 'TRIAGE_EVALUATED', `Rule: ${evaluation.matchedRule.id} → ${evaluation.result}`);

  const { symptomsSeverity, symptomsOnset, motivo } = agentCtx.patientData;
  return `📊 <strong>Evaluación clínica completada</strong>
    <div style="background: ${label.bgColor}; border: 1px solid ${label.color}30; border-radius: 8px; padding: 12px; margin-top: 6px; font-size: 0.82rem;">
      <div>📋 Motivo: ${motivo}</div>
      <div>⏱️ Inicio: ${symptomsOnset}</div>
      <div>📈 Severidad: ${symptomsSeverity || 'N/A'}</div>
      <div>💊 Alergias: ${agentCtx.patientData.allergies}</div>
      <div>💊 Medicación: ${agentCtx.patientData.currentMedications}</div>
      <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed ${label.color}30;">
        ${label.icon} <strong>${label.label}</strong>
      </div>
      <div style="font-size: 0.78rem; color: #6B7280; margin-top: 4px;">
        Regla: ${evaluation.matchedRule.id} — ${evaluation.reason}
      </div>
    </div>`;
}

// ── User Input Handler ──
function handleUserInput() {
  const text = userInput.value.trim();
  if (!text) return;
  addMessage('user', text);
  userInput.value = '';

  const state = stateMachine[currentStateId.value];
  if (!state) return;

  // Validate if validator exists
  if (state.validate) {
    const error = state.validate(text);
    if (error) {
      simulateTyping(`⚠️ ${error}`, 400);
      return;
    }
  }

  // Emergency check on every text input
  if (detectEmergency(text) && currentStateId.value !== 'EMERGENCY_STOP') {
    agentCtx.emergencyDetected = true;
    logAudit(auditLog.value, currentStateId.value, 'EMERGENCY_KEYWORD', text);
    transitionTo('EMERGENCY_STOP');
    return;
  }

  logAudit(auditLog.value, currentStateId.value, 'USER_INPUT', text);
  const nextState = state.next(text, agentCtx);
  transitionTo(nextState);
}

// ── Quick option handler ──
function handleQuickOption(opt: QuickOption) {
  addMessage('user', opt.label);
  logAudit(auditLog.value, currentStateId.value, 'QUICK_OPTION', opt.value);

  const state = stateMachine[currentStateId.value];
  const nextState = state.next(opt.value, agentCtx);
  transitionTo(nextState);
}

// ── Booking step handlers (same as before but using state machine) ──
function selectSpecialty(sp: any) {
  agentCtx.bookingData.specialtyId = sp.id;
  agentCtx.bookingData.specialtyName = sp.name;
  addMessage('user', `🏥 ${sp.name}`);
  logAudit(auditLog.value, 'SELECT_SPECIALTY', 'SELECTED', sp.name);

  const docs = matchingDoctors.value;
  if (docs.length === 0) {
    simulateTyping('No hay médicos disponibles para esa especialidad. <strong>¿Deseas elegir otra?</strong>');
  } else {
    transitionTo('SELECT_DOCTOR');
    simulateTyping(`Tenemos <strong>${docs.length} médico(s)</strong> de ${sp.name}. <strong>¿Con cuál deseas agendar?</strong>`);
  }
}

function selectDoctor(doc: any) {
  agentCtx.bookingData.doctorId = doc.user_id;
  agentCtx.bookingData.doctorProfileId = doc.doctor_profile_id;
  agentCtx.bookingData.doctorName = doc.full_name;
  addMessage('user', `👨‍⚕️ ${doc.full_name}`);
  logAudit(auditLog.value, 'SELECT_DOCTOR', 'SELECTED', doc.full_name);
  transitionTo('SELECT_DATE');
  simulateTyping('Excelente elección. <strong>¿Qué día te gustaría agendar?</strong>');
}

async function selectDate() {
  if (!selectedDate.value) return;
  agentCtx.bookingData.fecha = selectedDate.value;
  addMessage('user', `📅 ${selectedDate.value}`);
  transitionTo('SELECT_SLOT');
  addMessage('assistant', 'Consultando horarios disponibles...');
  loadingSlots.value = true;
  scrollToBottom();

  try {
    const tz = authUser.value?.timezone || 'UTC';
    const res = await fetch(`/api/doctors/${agentCtx.bookingData.doctorId}/availability?date=${selectedDate.value}&timezone=${tz}`, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    availableSlots.value = data.slots || [];
    loadingSlots.value = false;
    messages.value.pop(); // remove "consultando" msg

    const available = availableSlots.value.filter(s => s.available);
    if (available.length > 0) {
      // Score and sort slots using the scoring engine
      const scoringCtx = {
        preferredTimeOfDay: agentCtx.preferredTimeOfDay,
        urgency: agentCtx.patientData.symptomsSeverity,
      };
      const scored = scoreAndSortSlots(availableSlots.value, scoringCtx);
      availableSlots.value = scored; // replace with sorted

      const top = getTopRecommendations(scored, 3);
      const recoHtml = buildRecommendationHtml(top);

      addMessage('assistant', `Hay <strong>${available.length} horario(s) disponibles</strong>.${recoHtml}<br><strong>¿Cuál prefieres?</strong>`);
    } else {
      addMessage('assistant', 'No hay horarios disponibles para esa fecha.');
    }
  } catch {
    loadingSlots.value = false;
    messages.value.pop();
    addMessage('assistant', '⚠️ No pude consultar la disponibilidad. Intenta con otra fecha.');
    transitionTo('SELECT_DATE');
    selectedDate.value = '';
  }
}

function selectSlot(slot: SlotInfo) {
  agentCtx.bookingData.slotStart = slot.start;
  agentCtx.bookingData.slotEnd = slot.end;
  agentCtx.bookingData.slotLocalTime = `${slot.local_start} - ${slot.local_end}`;
  addMessage('user', `🕐 ${slot.local_start}`);
  transitionTo('CONFIRMATION');
  logAudit(auditLog.value, 'SELECT_SLOT', 'HOLD_STARTED', `Slot: ${slot.local_start}`);

  // Start 5-minute hold timer
  holdSeconds.value = 300;
  clearInterval(holdTimerInterval.value);
  holdTimerInterval.value = setInterval(() => {
    holdSeconds.value--;
    if (holdSeconds.value <= 0) {
      clearInterval(holdTimerInterval.value);
      logAudit(auditLog.value, 'CONFIRMATION', 'HOLD_EXPIRED');
      addMessage('assistant', '⏰ <strong>El tiempo de reserva ha expirado.</strong> El horario puede haber sido tomado por otro paciente. Selecciona un nuevo horario.');
      transitionTo('SELECT_DATE');
      selectedDate.value = '';
    }
  }, 1000) as unknown as number;

  simulateTyping(`Revisa los datos de tu cita y confirma:
    <div style="background: #FEF3C7; border-radius: 6px; padding: 6px 10px; margin-top: 6px; font-size: 0.78rem; color: #92400E;">
      ⏱️ Horario reservado temporalmente — tienes <strong>5 minutos</strong> para confirmar.
    </div>`, 600);
}

async function confirmBooking() {
  clearHoldTimer();
  submitting.value = true;
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
  const idempotencyKey = crypto.randomUUID();

  try {
    const res = await fetch('/api/appointments', {
      method: 'POST', credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '', 'X-Idempotency-Key': idempotencyKey, 'Idempotency-Key': idempotencyKey,
      },
      body: JSON.stringify({
        patient_id: authUser.value?.id,
        doctor_id: agentCtx.bookingData.doctorId,
        franja_inicio: agentCtx.bookingData.slotStart,
        franja_fin: agentCtx.bookingData.slotEnd,
      }),
    });

    submitting.value = false;

    if (res.ok) {
      transitionTo('DEMO_PREVIEW');
      logAudit(auditLog.value, 'BOOKING_SUCCESS', 'BOOKED', `Doctor: ${agentCtx.bookingData.doctorName}`);

      const dateObj = new Date(agentCtx.bookingData.slotStart);
      const dateFormatted = dateObj.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

      const summaryHtml = `🎉 <strong>¡Cita agendada exitosamente!</strong>
        <div style="background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 10px; padding: 14px; margin-top: 10px;">
          <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <div>👨‍⚕️ <strong>Doctor:</strong> ${agentCtx.bookingData.doctorName}</div>
            <div>🏥 <strong>Especialidad:</strong> ${agentCtx.bookingData.specialtyName}</div>
            <div>📅 <strong>Fecha:</strong> ${dateFormatted}</div>
            <div>🕐 <strong>Horario:</strong> ${agentCtx.bookingData.slotLocalTime}</div>
            <div>📋 <strong>Motivo:</strong> ${agentCtx.patientData.motivo}</div>
            <div style="margin-top: 4px; padding-top: 6px; border-top: 1px dashed #86EFAC;">
              ✅ <strong>Estado:</strong> <span style="color: #065F46;">Pendiente de confirmación</span>
            </div>
          </div>
        </div>`;

      addMessage('assistant', summaryHtml);

      // Generate demo message
      agentCtx.userName = authUser.value?.name || 'Paciente';
      demoMessage.value = generateDemoMessage(agentCtx);
      logAudit(auditLog.value, 'DEMO_PREVIEW', 'DEMO_MSG_GENERATED', 'Mensaje de demostración generado (no enviado)');

      // Pre-visit checklist
      setTimeout(() => {
        addMessage('assistant', `📋 <strong>Checklist pre-visita:</strong>
          <div style="background: #F0F9FF; border: 1px solid #93C5FD; border-radius: 8px; padding: 12px; margin-top: 6px; font-size: 0.82rem;">
            <div style="display: flex; flex-direction: column; gap: 6px;">
              <div>☐ Completar formulario pre-consulta</div>
              <div>☐ Tener a mano documentos de identidad y seguro</div>
              <div>☐ Preparar lista de medicamentos actuales</div>
              <div>☐ Verificar cámara y micrófono (10 min antes)</div>
              <div>☐ Buscar un lugar tranquilo con buena conexión</div>
              <div>☐ Tener sus estudios o resultados previos disponibles</div>
            </div>
          </div>`);
      }, 1200);

      // Demo message button + nav
      setTimeout(() => {
        addMessage('assistant', `📧 <strong>Vista previa del mensaje de confirmación:</strong>
          <br><button onclick="document.dispatchEvent(new CustomEvent('show-demo-modal'))" style="background: #0E5D52; color: #FFF; border: none; border-radius: 8px; padding: 8px 16px; margin-top: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem;">
            📨 Ver mensaje de demostración
          </button>
          <br><br>📌 Puedes ver tus citas en <strong>"Mis Citas"</strong> en el menú lateral.<br>¿Necesitas algo más?`);
      }, 2500);
    } else if (res.status === 409) {
      addMessage('assistant', '⚠️ Ese horario acaba de ser ocupado por otro paciente.');
      transitionTo('SELECT_DATE');
      selectedDate.value = '';
    } else {
      const data = await res.json().catch(() => ({}));
      addMessage('assistant', `⚠️ ${data.message || 'Error al crear la cita'}. Intenta de nuevo.`);
      transitionTo('SELECT_DATE');
      selectedDate.value = '';
    }
  } catch {
    submitting.value = false;
    addMessage('assistant', '⚠️ Error de conexión. Intenta de nuevo.');
  }
}

function cancelBooking() {
  clearHoldTimer();
  addMessage('user', 'Cancelar');
  logAudit(auditLog.value, 'CONFIRMATION', 'CANCELLED');
  restartFlow();
  addMessage('assistant', 'No hay problema. Si necesitas agendar más adelante, aquí estaré. 😊');
}

function escalateToHuman() {
  logAudit(auditLog.value, currentStateId.value, 'ESCALATE_HUMAN');
  transitionTo('ESCALATE_HUMAN');
}

function restartFlow() {
  Object.assign(agentCtx, createAgentContext(authUser.value?.name));
  selectedDate.value = '';
  availableSlots.value = [];
  currentStateId.value = 'WELCOME';
  messages.value = [];
  transitionTo('WELCOME');
}

// ── Chat toggle ──
function toggleChat() {
  isOpen.value = !isOpen.value;
  if (isOpen.value && messages.value.length === 0) {
    agentCtx.userName = authUser.value?.name || '';
    transitionTo('WELCOME');
  }
}

function startBookingFlow() {
  isOpen.value = true;
  if (currentStateId.value !== 'WELCOME' && currentStateId.value !== 'IDLE') return;
  transitionTo('EMERGENCY_CHECK');
}

defineExpose({ startBookingFlow });

// ── Demo modal via CustomEvent ──
if (typeof document !== 'undefined') {
  document.addEventListener('show-demo-modal', () => {
    showDemoModal.value = true;
  });
}

function copyDemoText() {
  if (!demoMessage.value) return;
  navigator.clipboard.writeText(demoMessage.value.bodyPlainText).then(() => {
    copyLabel.value = '✅ Copiado!';
    setTimeout(() => { copyLabel.value = 'Copiar texto'; }, 2000);
  });
}

function downloadDemoText() {
  if (!demoMessage.value) return;
  const blob = new Blob([demoMessage.value.bodyPlainText], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'confirmacion-cita-salvia.txt';
  a.click();
  URL.revokeObjectURL(url);
  logAudit(auditLog.value, 'DEMO_PREVIEW', 'DEMO_MSG_DOWNLOADED');
}

watch(messages, () => scrollToBottom(), { deep: true });
</script>

<style scoped>
/* ── Floating button ── */
.floating-assistant {
  position: fixed; right: var(--spacing-6, 24px); bottom: var(--spacing-6, 24px);
  z-index: 9999; display: flex; flex-direction: column; align-items: flex-end; gap: var(--spacing-4, 16px);
}
.floating-btn {
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary, #0E5D52), #148071);
  color: #FFF; border: none; display: flex; align-items: center; justify-content: center;
  font-size: 24px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(14, 93, 82, 0.4);
  transition: transform 0.3s, box-shadow 0.3s;
  animation: pulse-ring 2s infinite;
}
.floating-btn:hover { transform: scale(1.08); box-shadow: 0 6px 20px rgba(14, 93, 82, 0.5); }
.floating-btn.is-open { animation: none; background: #4B5563; }

@keyframes pulse-ring {
  0% { box-shadow: 0 0 0 0 rgba(14, 93, 82, 0.4); }
  70% { box-shadow: 0 0 0 12px rgba(14, 93, 82, 0); }
  100% { box-shadow: 0 0 0 0 rgba(14, 93, 82, 0); }
}

/* ── Chat panel ── */
.chat-panel {
  width: 400px; height: 560px;
  background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(16px);
  border: 1px solid rgba(0,0,0,0.08); border-radius: 16px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.12);
  display: flex; flex-direction: column; overflow: hidden;
}
.chat-header {
  background: linear-gradient(135deg, var(--color-primary, #0E5D52), #148071);
  color: #FFF; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center;
}
.chat-header-title { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.chat-header-badge { font-size: 0.7rem; animation: pulse 1s infinite; }
.chat-header-actions { display: flex; gap: 4px; }
.chat-close, .chat-restart { background: transparent; border: none; color: #FFF; cursor: pointer; padding: 4px; opacity: 0.8; }
.chat-close:hover, .chat-restart:hover { opacity: 1; }

/* ── Messages ── */
.chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.message-wrapper { display: flex; width: 100%; }
.message-wrapper.assistant { justify-content: flex-start; }
.message-wrapper.user { justify-content: flex-end; }
.message-bubble {
  max-width: 85%; padding: 10px 14px; border-radius: 12px;
  font-size: 0.88rem; line-height: 1.5; word-wrap: break-word;
}
.message-bubble p { margin: 0; }
.assistant .message-bubble { background: #F3F4F6; color: #1F2937; border-bottom-left-radius: 2px; }
.user .message-bubble { background: var(--color-primary, #0E5D52); color: #FFF; border-bottom-right-radius: 2px; }
.message-bubble--emergency { background: #FEE2E2 !important; border: 1px solid #FCA5A5; }

/* ── Interactive blocks ── */
.interactive-block { padding: 8px 0; width: 100%; }
.chips-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.chip-btn {
  padding: 6px 14px; border: 1px solid var(--color-primary, #0E5D52);
  background: #FFF; color: var(--color-primary, #0E5D52);
  border-radius: 20px; font-size: 0.82rem; cursor: pointer; transition: all 0.2s;
}
.chip-btn:hover { background: var(--color-primary, #0E5D52); color: #FFF; }
.chip-btn--danger { border-color: #DC2626; color: #DC2626; }
.chip-btn--danger:hover { background: #DC2626; color: #FFF; }

.doctor-cards { display: flex; flex-direction: column; gap: 8px; }
.doctor-card {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px;
  background: #FFF; border: 1px solid #E5E7EB; border-radius: 10px;
  cursor: pointer; transition: all 0.2s; text-align: left; width: 100%;
}
.doctor-card:hover { border-color: var(--color-primary, #0E5D52); box-shadow: 0 2px 8px rgba(14, 93, 82, 0.15); }
.doctor-avatar {
  width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
  color: #FFF; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}
.doctor-info { display: flex; flex-direction: column; }
.doctor-info strong { font-size: 0.85rem; color: #111827; }
.doctor-info small { font-size: 0.75rem; color: #6B7280; }

.date-input { width: 100%; padding: 10px 14px; border: 1px solid #D1D5DB; border-radius: 10px; font-size: 0.9rem; outline: none; }
.date-input:focus { border-color: var(--color-primary, #0E5D52); }

.slots-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.slot-btn {
  padding: 8px 4px; border: 1px solid var(--color-primary, #0E5D52); background: #FFF;
  color: var(--color-primary, #0E5D52); border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer;
}
.slot-btn:hover:not(:disabled) { background: var(--color-primary, #0E5D52); color: #FFF; }
.slot-btn--unavailable { opacity: 0.35; cursor: not-allowed; border-color: #D1D5DB; color: #9CA3AF; }

.confirm-card { background: #FFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px; }
.confirm-row { padding: 4px 0; font-size: 0.85rem; color: #374151; border-bottom: 1px solid #F3F4F6; }
.confirm-row:last-of-type { border-bottom: none; }
.hold-timer {
  padding: 8px 10px; margin-top: 8px; border-radius: 6px;
  background: #FEF3C7; color: #92400E; font-size: 0.82rem; text-align: center;
  transition: all 0.3s;
}
.hold-timer--warning {
  background: #FEE2E2; color: #991B1B; animation: hold-pulse 1s infinite;
}
@keyframes hold-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}
.confirm-actions { display: flex; gap: 8px; margin-top: 12px; }
.confirm-btn { flex: 1; padding: 10px; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.confirm-btn--yes { background: var(--color-primary, #0E5D52); color: #FFF; }
.confirm-btn--yes:hover:not(:disabled) { filter: brightness(1.1); }
.confirm-btn--yes:disabled { opacity: 0.6; cursor: not-allowed; }
.confirm-btn--no { background: #FEE2E2; color: #991B1B; }
.confirm-btn--no:hover { background: #FECACA; }

.empty-notice { text-align: center; padding: 12px; font-size: 0.85rem; color: #6B7280; }

.loading-dots { display: flex; justify-content: center; gap: 6px; padding: 16px; }
.loading-dots span { width: 8px; height: 8px; background: var(--color-primary, #0E5D52); border-radius: 50%; animation: bounce-dot 1.4s infinite ease-in-out both; }
.loading-dots span:nth-child(1) { animation-delay: -0.32s; }
.loading-dots span:nth-child(2) { animation-delay: -0.16s; }
@keyframes bounce-dot { 0%, 80%, 100% { transform: scale(0.4); } 40% { transform: scale(1); } }

/* ── Footer ── */
.chat-footer { padding: 8px 12px; background: #FFF; border-top: 1px solid #E5E7EB; }
.chat-escalate { text-align: center; margin-bottom: 6px; }
.escalate-link {
  background: none; border: none; color: var(--color-text-muted, #6B7280);
  font-size: 0.75rem; cursor: pointer; text-decoration: underline; opacity: 0.7;
}
.escalate-link:hover { opacity: 1; color: var(--color-primary, #0E5D52); }
.chat-input-form { display: flex; gap: 8px; }
.chat-input {
  flex: 1; padding: 8px 14px; border: 1px solid #D1D5DB; border-radius: 20px;
  outline: none; font-size: 0.88rem; background: #FFF;
}
.chat-input:focus { border-color: var(--color-primary, #0E5D52); }
.chat-input:disabled { background: #F9FAFB; cursor: not-allowed; }
.chat-send {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--color-primary, #0E5D52); color: #FFF;
  border: none; display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.chat-send:hover:not(:disabled) { filter: brightness(1.1); }
.chat-send:disabled { background: #D1D5DB; cursor: not-allowed; }

/* ── Typing ── */
.typing-indicator { display: flex; gap: 4px; align-items: center; height: 24px; padding: 0 14px !important; }
.typing-indicator span { display: block; width: 6px; height: 6px; background: #6B7280; border-radius: 50%; animation: typing 1.4s infinite ease-in-out both; }
.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
@keyframes typing { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

/* ── Demo Modal ── */
.demo-modal-overlay {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;
  z-index: 10000; backdrop-filter: blur(4px);
}
.demo-modal {
  width: 640px; max-height: 85vh; background: #FFF; border-radius: 16px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.25); overflow-y: auto;
}
.demo-modal__header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px; border-bottom: 1px solid #E5E7EB; background: #FFFBEB;
}
.demo-modal__badge {
  font-size: 0.85rem; font-weight: 600; color: #92400E;
  background: #FEF3C7; padding: 4px 12px; border-radius: 8px;
}
.demo-modal__close { background: none; border: none; cursor: pointer; font-size: 1rem; color: #6B7280; }
.demo-modal__subject { padding: 12px 20px; font-size: 0.9rem; border-bottom: 1px solid #F3F4F6; }
.demo-modal__recipients { padding: 8px 20px; font-size: 0.82rem; color: #6B7280; border-bottom: 1px solid #F3F4F6; }
.demo-modal__body { padding: 20px; }
.demo-modal__actions { display: flex; gap: 8px; padding: 16px 20px; border-top: 1px solid #E5E7EB; flex-wrap: wrap; }
.demo-btn {
  padding: 8px 16px; border-radius: 8px; font-size: 0.82rem; font-weight: 600;
  border: 1px solid #E5E7EB; cursor: pointer; display: flex; align-items: center; gap: 5px;
  transition: all 0.2s;
}
.demo-btn--copy { background: #F9FAFB; color: #374151; }
.demo-btn--copy:hover { background: #E5E7EB; }
.demo-btn--download { background: #0E5D52; color: #FFF; border-color: #0E5D52; }
.demo-btn--download:hover { filter: brightness(1.1); }
.demo-btn--send { background: #F9FAFB; color: #9CA3AF; cursor: not-allowed; opacity: 0.5; }

/* ── Transitions ── */
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(20px) scale(0.9); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

@media (max-width: 480px) {
  .chat-panel { position: fixed; bottom: 0; right: 0; width: 100%; height: 100%; border-radius: 0; }
  .floating-assistant { right: 16px; bottom: 16px; }
  .demo-modal { width: 95%; margin: 0 10px; }
}
</style>
