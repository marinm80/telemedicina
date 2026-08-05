<!--
  ====================================================================
  FloatingAssistant — Agente conversacional para agendamiento de citas
  AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
  ====================================================================
-->
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
              <p v-html="msg.text"></p>
            </div>
          </div>

          <!-- Interactive elements based on booking step -->

          <!-- Specialty selection -->
          <div v-if="bookingStep === 'especialidad'" class="interactive-block">
            <div class="chips-grid">
              <button
                v-for="sp in specialties"
                :key="sp.id"
                class="chip-btn"
                @click="selectSpecialty(sp)"
              >
                {{ sp.name }}
              </button>
            </div>
          </div>

          <!-- Doctor selection -->
          <div v-if="bookingStep === 'doctor'" class="interactive-block">
            <div v-if="matchingDoctors.length === 0" class="empty-notice">
              No hay médicos disponibles para esta especialidad.
            </div>
            <div v-else class="doctor-cards">
              <button
                v-for="doc in matchingDoctors"
                :key="doc.doctor_profile_id"
                class="doctor-card"
                @click="selectDoctor(doc)"
              >
                <div class="doctor-avatar" :style="{ backgroundColor: avatarColor(doc.full_name) }">
                  {{ initials(doc.full_name) }}
                </div>
                <div class="doctor-info">
                  <strong>{{ doc.full_name }}</strong>
                  <small>{{ doc.specialties.join(', ') }}</small>
                </div>
              </button>
            </div>
          </div>

          <!-- Date selection -->
          <div v-if="bookingStep === 'fecha'" class="interactive-block">
            <input
              type="date"
              class="date-input"
              :min="minDate"
              :max="maxDate"
              v-model="selectedDate"
              @change="selectDate"
            />
          </div>

          <!-- Slot selection -->
          <div v-if="bookingStep === 'horario'" class="interactive-block">
            <div v-if="loadingSlots" class="loading-dots">
              <span></span><span></span><span></span>
            </div>
            <div v-else-if="availableSlots.length === 0" class="empty-notice">
              No hay horarios disponibles para esta fecha. Intenta otro día.
              <button class="chip-btn" @click="bookingStep = 'fecha'; addMessage('assistant', '¿Qué otro día te conviene?')">
                Elegir otra fecha
              </button>
            </div>
            <div v-else class="slots-grid">
              <button
                v-for="slot in availableSlots"
                :key="slot.start"
                class="slot-btn"
                :class="{ 'slot-btn--unavailable': !slot.available }"
                :disabled="!slot.available"
                @click="selectSlot(slot)"
              >
                {{ slot.local_start }}
              </button>
            </div>
          </div>

          <!-- Confirmation -->
          <div v-if="bookingStep === 'confirmacion'" class="interactive-block">
            <div class="confirm-card">
              <div class="confirm-row"><strong>Médico:</strong> {{ booking.doctorName }}</div>
              <div class="confirm-row"><strong>Fecha:</strong> {{ booking.fecha }}</div>
              <div class="confirm-row"><strong>Hora:</strong> {{ booking.slotLocalTime }}</div>
              <div class="confirm-row"><strong>Motivo:</strong> {{ booking.motivo }}</div>
              <div class="confirm-actions">
                <button class="confirm-btn confirm-btn--yes" @click="confirmBooking" :disabled="submitting">
                  {{ submitting ? 'Agendando...' : '✅ Confirmar Cita' }}
                </button>
                <button class="confirm-btn confirm-btn--no" @click="cancelBooking">
                  ❌ Cancelar
                </button>
              </div>
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isTyping" class="message-wrapper assistant">
            <div class="message-bubble typing-indicator">
              <span></span><span></span><span></span>
            </div>
          </div>
        </div>

        <footer class="chat-footer">
          <form @submit.prevent="handleUserInput" class="chat-input-form">
            <input
              type="text"
              v-model="userInput"
              :placeholder="inputPlaceholder"
              class="chat-input"
              :disabled="isTyping || isInputDisabled"
            />
            <button type="submit" class="chat-send" :disabled="!userInput.trim() || isTyping || isInputDisabled">
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

// ── Types ──
interface Message {
  role: 'assistant' | 'user';
  text: string;
}

interface SlotInfo {
  start: string;
  end: string;
  local_start: string;
  local_end: string;
  available: boolean;
}

type BookingStep = 'idle' | 'motivo' | 'especialidad' | 'doctor' | 'fecha' | 'horario' | 'confirmacion' | 'done';

// ── State ──
const isOpen = ref(false);
const isTyping = ref(false);
const userInput = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const bookingStep = ref<BookingStep>('idle');
const loadingSlots = ref(false);
const submitting = ref(false);
const selectedDate = ref('');
const availableSlots = ref<SlotInfo[]>([]);

const messages = ref<Message[]>([
  {
    role: 'assistant',
    text: 'Hola 👋 Soy el asistente de <strong>Salvia</strong>. Puedo ayudarte a agendar citas, buscar médicos y responder tus dudas. ¿Qué necesitas?'
  }
]);

const booking = reactive({
  motivo: '',
  specialtyId: '',
  specialtyName: '',
  doctorId: '',
  doctorProfileId: '',
  doctorName: '',
  fecha: '',
  slotStart: '',
  slotEnd: '',
  slotLocalTime: '',
});

// ── Inertia shared data ──
const page = usePage();
const specialties = computed(() => (page.props as any).booking?.specialties || []);
const allDoctors = computed(() => (page.props as any).booking?.doctors || []);
const authUser = computed(() => (page.props as any).auth?.user);

const matchingDoctors = computed(() => {
  if (!booking.specialtyName) return allDoctors.value;
  return allDoctors.value.filter((d: any) =>
    d.specialties.includes(booking.specialtyName)
  );
});

// ── Computed ──
const minDate = computed(() => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().split('T')[0];
});

const maxDate = computed(() => {
  const d = new Date();
  d.setDate(d.getDate() + 60);
  return d.toISOString().split('T')[0];
});

const isInputDisabled = computed(() => {
  return ['especialidad', 'doctor', 'fecha', 'horario', 'confirmacion', 'done'].includes(bookingStep.value);
});

const inputPlaceholder = computed(() => {
  if (bookingStep.value === 'motivo') return 'Escribe el motivo de tu consulta...';
  if (isInputDisabled.value) return 'Selecciona una opción arriba...';
  return 'Escribe tu mensaje...';
});

// ── Helpers ──
function addMessage(role: 'assistant' | 'user', text: string) {
  messages.value.push({ role, text });
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

function resetBooking() {
  bookingStep.value = 'idle';
  Object.assign(booking, {
    motivo: '', specialtyId: '', specialtyName: '',
    doctorId: '', doctorProfileId: '', doctorName: '',
    fecha: '', slotStart: '', slotEnd: '', slotLocalTime: '',
  });
  selectedDate.value = '';
  availableSlots.value = [];
}

// ── Chat toggle ──
function toggleChat() {
  isOpen.value = !isOpen.value;
}

// Opens chat and starts booking flow (called from sidebar CTA)
function startBookingFlow() {
  isOpen.value = true;
  if (bookingStep.value !== 'idle') return; // already in flow
  bookingStep.value = 'motivo';
  addMessage('assistant', '¡Perfecto! Vamos a agendar tu cita. 📅<br><br>Primero, <strong>¿cuál es el motivo de tu consulta?</strong>');
}

// Expose for parent (AppLayout) to call
defineExpose({ startBookingFlow });

// ── User input handler ──
function handleUserInput() {
  const text = userInput.value.trim();
  if (!text) return;

  addMessage('user', text);
  userInput.value = '';

  if (bookingStep.value === 'idle') {
    // Check if user wants to book
    const bookingKeywords = ['agendar', 'cita', 'turno', 'reservar', 'consulta', 'doctor', 'médico', 'medico'];
    const wantsBooking = bookingKeywords.some(k => text.toLowerCase().includes(k));

    if (wantsBooking) {
      bookingStep.value = 'motivo';
      simulateTyping('¡Claro! Vamos a agendar tu cita. 📅<br><br>Primero, <strong>¿cuál es el motivo de tu consulta?</strong>');
    } else {
      // General info response
      simulateTyping('Puedo ayudarte con:<br>• <strong>Agendar citas</strong> con especialistas<br>• <strong>Buscar médicos</strong> por especialidad<br>• <strong>Información</strong> sobre horarios y disponibilidad<br><br>Escribe "agendar cita" para comenzar, o hazme cualquier pregunta. 😊');
    }
    return;
  }

  if (bookingStep.value === 'motivo') {
    booking.motivo = text;
    bookingStep.value = 'especialidad';
    simulateTyping('Entendido. <strong>¿Qué especialidad necesitas?</strong> Selecciona una opción:');
    return;
  }
}

function simulateTyping(responseText: string, delay = 1000) {
  isTyping.value = true;
  scrollToBottom();
  setTimeout(() => {
    isTyping.value = false;
    addMessage('assistant', responseText);
  }, delay);
}

// ── Booking step handlers ──
function selectSpecialty(sp: any) {
  booking.specialtyId = sp.id;
  booking.specialtyName = sp.name;
  addMessage('user', `🏥 ${sp.name}`);

  const docs = matchingDoctors.value;
  if (docs.length === 0) {
    bookingStep.value = 'especialidad';
    simulateTyping('No hay médicos disponibles para esa especialidad en este momento. <strong>¿Deseas elegir otra?</strong>');
  } else {
    bookingStep.value = 'doctor';
    simulateTyping(`Tenemos <strong>${docs.length} médico(s)</strong> de ${sp.name}. <strong>¿Con cuál deseas agendar?</strong>`);
  }
}

function selectDoctor(doc: any) {
  booking.doctorId = doc.user_id;
  booking.doctorProfileId = doc.doctor_profile_id;
  booking.doctorName = doc.full_name;
  addMessage('user', `👨‍⚕️ ${doc.full_name}`);
  bookingStep.value = 'fecha';
  simulateTyping(`Excelente elección. <strong>¿Qué día te gustaría agendar?</strong> Selecciona una fecha:`);
}

async function selectDate() {
  if (!selectedDate.value) return;
  booking.fecha = selectedDate.value;
  addMessage('user', `📅 ${selectedDate.value}`);
  bookingStep.value = 'horario';
  addMessage('assistant', 'Consultando horarios disponibles...');
  loadingSlots.value = true;
  scrollToBottom();

  try {
    const res = await fetch(`/api/doctors/${booking.doctorId}/availability?date=${selectedDate.value}&timezone=${authUser.value?.timezone || 'UTC'}`, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    availableSlots.value = data.slots || [];
    loadingSlots.value = false;

    // Remove the "consultando" message
    messages.value.pop();

    if (availableSlots.value.filter(s => s.available).length > 0) {
      const count = availableSlots.value.filter(s => s.available).length;
      addMessage('assistant', `Hay <strong>${count} horario(s) disponibles</strong> el ${selectedDate.value}. <strong>¿Cuál prefieres?</strong>`);
    } else {
      addMessage('assistant', 'No hay horarios disponibles para esa fecha.');
    }
  } catch (err) {
    loadingSlots.value = false;
    messages.value.pop();
    addMessage('assistant', '⚠️ No pude consultar la disponibilidad. Intenta con otra fecha.');
    bookingStep.value = 'fecha';
    selectedDate.value = '';
  }
}

function selectSlot(slot: SlotInfo) {
  booking.slotStart = slot.start;
  booking.slotEnd = slot.end;
  booking.slotLocalTime = `${slot.local_start} - ${slot.local_end}`;
  addMessage('user', `🕐 ${slot.local_start}`);
  bookingStep.value = 'confirmacion';
  simulateTyping('Perfecto. Revisa los datos de tu cita y confirma:', 600);
}

async function confirmBooking() {
  submitting.value = true;
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
  const idempotencyKey = crypto.randomUUID();

  try {
    const res = await fetch('/api/appointments', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
        'X-Idempotency-Key': idempotencyKey,
        'Idempotency-Key': idempotencyKey,
      },
      body: JSON.stringify({
        patient_id: authUser.value?.id,
        doctor_id: booking.doctorId,
        franja_inicio: booking.slotStart,
        franja_fin: booking.slotEnd,
      }),
    });

    submitting.value = false;

    if (res.ok) {
      bookingStep.value = 'done';
      
      // Format date for display
      const dateObj = new Date(booking.slotStart);
      const dateFormatted = dateObj.toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });

      const summaryHtml = `
        🎉 <strong>¡Cita agendada exitosamente!</strong>
        <div style="background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 10px; padding: 14px; margin-top: 10px;">
          <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.88rem;">
            <div>👨‍⚕️ <strong>Doctor:</strong> ${booking.doctorName}</div>
            <div>🏥 <strong>Especialidad:</strong> ${booking.specialtyName}</div>
            <div>📅 <strong>Fecha:</strong> ${dateFormatted}</div>
            <div>🕐 <strong>Horario:</strong> ${booking.slotLocalTime}</div>
            <div>📋 <strong>Motivo:</strong> ${booking.reason}</div>
            <div style="margin-top: 4px; padding-top: 6px; border-top: 1px dashed #86EFAC;">
              ✅ <strong>Estado:</strong> <span style="color: #065F46;">Pendiente de confirmación</span>
            </div>
          </div>
        </div>
        <br>📌 Puedes ver y gestionar tus citas en <strong>"Mis Citas"</strong> en el menú lateral.
        <br><br>¿Necesitas algo más?
      `.trim();

      addMessage('assistant', summaryHtml);
    } else if (res.status === 409) {
      addMessage('assistant', '⚠️ Ese horario acaba de ser ocupado por otro paciente. Vamos a buscar otro horario.');
      bookingStep.value = 'fecha';
      selectedDate.value = '';
    } else {
      const data = await res.json().catch(() => ({}));
      const errorMsg = data.message || 'Error al crear la cita';
      addMessage('assistant', `⚠️ ${errorMsg}. Intenta de nuevo.`);
      bookingStep.value = 'fecha';
      selectedDate.value = '';
    }
  } catch (err) {
    submitting.value = false;
    addMessage('assistant', '⚠️ Error de conexión. Intenta de nuevo.');
    bookingStep.value = 'confirmacion';
  }
}

function cancelBooking() {
  addMessage('user', 'Cancelar');
  resetBooking();
  addMessage('assistant', 'No hay problema. Si necesitas agendar más adelante, aquí estaré. 😊');
}

// Watch for scroll
watch(messages, () => scrollToBottom(), { deep: true });
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
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary, #0E5D52), #148071);
  color: #FFFFFF;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  cursor: pointer;
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

.chat-panel {
  width: 380px;
  height: 520px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(16px);
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 16px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.12);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.chat-header {
  background: linear-gradient(135deg, var(--color-primary, #0E5D52), #148071);
  color: #FFF;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chat-header-title { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.chat-close { background: transparent; border: none; color: #FFF; cursor: pointer; padding: 4px; opacity: 0.8; }
.chat-close:hover { opacity: 1; }

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.message-wrapper { display: flex; width: 100%; }
.message-wrapper.assistant { justify-content: flex-start; }
.message-wrapper.user { justify-content: flex-end; }

.message-bubble {
  max-width: 85%;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 0.88rem;
  line-height: 1.5;
  word-wrap: break-word;
}

.message-bubble p { margin: 0; }

.assistant .message-bubble {
  background: #F3F4F6;
  color: #1F2937;
  border-bottom-left-radius: 2px;
}

.user .message-bubble {
  background: var(--color-primary, #0E5D52);
  color: #FFF;
  border-bottom-right-radius: 2px;
}

/* Interactive blocks */
.interactive-block {
  padding: 8px 0;
  width: 100%;
}

.chips-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip-btn {
  padding: 6px 14px;
  border: 1px solid var(--color-primary, #0E5D52);
  background: #FFF;
  color: var(--color-primary, #0E5D52);
  border-radius: 20px;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.2s;
}

.chip-btn:hover {
  background: var(--color-primary, #0E5D52);
  color: #FFF;
}

.doctor-cards {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.doctor-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #FFF;
  border: 1px solid #E5E7EB;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
  width: 100%;
}

.doctor-card:hover {
  border-color: var(--color-primary, #0E5D52);
  box-shadow: 0 2px 8px rgba(14, 93, 82, 0.15);
}

.doctor-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #FFF;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.doctor-info { display: flex; flex-direction: column; }
.doctor-info strong { font-size: 0.85rem; color: #111827; }
.doctor-info small { font-size: 0.75rem; color: #6B7280; }

.date-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #D1D5DB;
  border-radius: 10px;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.2s;
}

.date-input:focus { border-color: var(--color-primary, #0E5D52); }

.slots-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
}

.slot-btn {
  padding: 8px 4px;
  border: 1px solid var(--color-primary, #0E5D52);
  background: #FFF;
  color: var(--color-primary, #0E5D52);
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.slot-btn:hover:not(:disabled) {
  background: var(--color-primary, #0E5D52);
  color: #FFF;
}

.slot-btn--unavailable {
  opacity: 0.35;
  cursor: not-allowed;
  border-color: #D1D5DB;
  color: #9CA3AF;
}

.confirm-card {
  background: #FFF;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  padding: 14px;
}

.confirm-row {
  padding: 4px 0;
  font-size: 0.85rem;
  color: #374151;
  border-bottom: 1px solid #F3F4F6;
}

.confirm-row:last-of-type { border-bottom: none; }

.confirm-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.confirm-btn {
  flex: 1;
  padding: 10px;
  border: none;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.confirm-btn--yes {
  background: var(--color-primary, #0E5D52);
  color: #FFF;
}

.confirm-btn--yes:hover:not(:disabled) { filter: brightness(1.1); }
.confirm-btn--yes:disabled { opacity: 0.6; cursor: not-allowed; }

.confirm-btn--no {
  background: #FEE2E2;
  color: #991B1B;
}

.confirm-btn--no:hover { background: #FECACA; }

.empty-notice {
  text-align: center;
  padding: 12px;
  font-size: 0.85rem;
  color: #6B7280;
}

.loading-dots {
  display: flex;
  justify-content: center;
  gap: 6px;
  padding: 16px;
}

.loading-dots span {
  width: 8px; height: 8px;
  background: var(--color-primary, #0E5D52);
  border-radius: 50%;
  animation: bounce-dot 1.4s infinite ease-in-out both;
}

.loading-dots span:nth-child(1) { animation-delay: -0.32s; }
.loading-dots span:nth-child(2) { animation-delay: -0.16s; }

@keyframes bounce-dot {
  0%, 80%, 100% { transform: scale(0.4); }
  40% { transform: scale(1); }
}

/* Footer */
.chat-footer {
  padding: 10px 12px;
  background: #FFF;
  border-top: 1px solid #E5E7EB;
}

.chat-input-form { display: flex; gap: 8px; }

.chat-input {
  flex: 1;
  padding: 8px 14px;
  border: 1px solid #D1D5DB;
  border-radius: 20px;
  outline: none;
  font-size: 0.88rem;
  background: #FFF;
}

.chat-input:focus { border-color: var(--color-primary, #0E5D52); }
.chat-input:disabled { background: #F9FAFB; cursor: not-allowed; }

.chat-send {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--color-primary, #0E5D52);
  color: #FFF;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.chat-send:hover:not(:disabled) { filter: brightness(1.1); }
.chat-send:disabled { background: #D1D5DB; cursor: not-allowed; }

/* Typing indicator */
.typing-indicator {
  display: flex; gap: 4px; align-items: center; height: 24px;
  padding: 0 14px !important;
}

.typing-indicator span {
  display: block; width: 6px; height: 6px;
  background: #6B7280; border-radius: 50%;
  animation: typing 1.4s infinite ease-in-out both;
}

.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
  0%, 80%, 100% { transform: scale(0); }
  40% { transform: scale(1); }
}

/* Transitions */
.slide-up-enter-active, .slide-up-leave-active {
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.slide-up-enter-from, .slide-up-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.9);
}

/* Responsive */
@media (max-width: 480px) {
  .chat-panel {
    position: fixed;
    bottom: 0; right: 0;
    width: 100%; height: 100%;
    border-radius: 0;
  }
  .floating-assistant { right: 16px; bottom: 16px; }
}
</style>
