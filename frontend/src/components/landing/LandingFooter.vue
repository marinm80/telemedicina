<script setup lang="ts">
import { ref } from 'vue';

const showNotes = ref(false);

const notes = [
  { name: 'ReceptionChat.vue', desc: 'Chat público del landing: solo informa (dirección, horarios, especialidades, precios, seguros, exámenes). Nunca agenda.', props: 'initialMessage, quickReplies[]', states: 'info → auth-gate; typing (900 ms simulado); intención no reconocida (fallback informativo)' },
  { name: 'ChatMessage.vue', desc: 'Burbuja polimórfica según tipo de payload.', props: 'role (agent|user), text, specialty, doctors[], info[], authGate', states: 'user / agent / tarjeta de especialidad / lista de médicos / tabla de datos / muro de cuenta' },
  { name: 'AuthGateCard.vue', desc: 'Bloque que corta el flujo de reserva y lleva a Crear cuenta / Iniciar sesión.', props: 'reason', states: 'invitado (visible), autenticado (oculto)' },
  { name: 'BookingAgent (dentro del sistema)', desc: 'Segundo agente, ya autenticado: recoge síntomas, elige especialidad y confirma. Fuera del alcance de este landing.', props: 'user, symptoms', states: 'intake → especialidad → médico → horario → confirmada' },
  { name: 'DoctorMiniCard.vue', desc: 'Tarjeta compacta dentro del chat.', props: 'doctor{ id, name, specialty, university, years, price, nextSlot }', states: 'default, hover, seleccionada, sin disponibilidad' },
  { name: 'SpecialtyCard.vue', desc: 'Celda del grid de especialidades; al pulsar inyecta un mensaje en el chat.', props: 'name, icon, count, priceFrom', states: 'default, hover, activa, sin médicos disponibles' },
  { name: 'DoctorCard.vue', desc: 'Tarjeta grande del directorio con foto, universidad y certificación.', props: 'doctor, onBook', states: 'default, hover, agenda llena' },
  { name: 'SlotPicker', desc: 'Chips de horario renderizados como payload del mensaje.', props: 'slots[] (strings o ISO)', states: 'disponible, seleccionado, ocupado' },
  { name: 'Motor de intención', desc: 'INTENTS: array de { keywords[], reply, info[] | doctors | priceList } + BOOK_KW para detectar intención de reserva. Sustituible por backend/LLM sin tocar la UI.', props: 'text → { reply, payload }', states: 'match informativo, intención de reserva → auth gate, fallback' },
  { name: 'Auth', desc: 'Login y registro discretos en el header; el agente del landing nunca agenda: cualquier intento abre el muro de cuenta.', props: '—', states: 'invitado, autenticado, requerido-para-agendar' }
];
</script>

<template>
  <footer class="max-w-5xl mx-auto px-4 py-8 flex items-center justify-between gap-5 flex-wrap border-t border-salvia-cardBorder mt-10">
    <div class="text-sm text-salvia-secondary">Salvia · Prototipo de portafolio · Datos de demostración</div>
    <button
      type="button"
      @click="showNotes = !showNotes"
      class="border border-salvia-cardBorder bg-transparent text-salvia-secondary text-xs font-semibold px-4 py-2.5 rounded-full cursor-pointer hover:text-salvia-primary hover:border-salvia-primary transition-all duration-200"
    >
      {{ showNotes ? 'Ocultar notas de handoff' : 'Notas de handoff (Vue)' }}
    </button>
  </footer>

  <!-- Handoff Notes section -->
  <section v-if="showNotes" class="max-w-5xl mx-auto px-4 pb-16">
    <div class="background bg-[#FFFDF9] border border-dashed border-[#CFE3DA] rounded-3xl p-6 md:p-10">
      <h3 class="margin m-0 mb-1.5 font-serif font-normal text-3xl">Notas para el handoff en Vue</h3>
      <p class="margin m-0 mb-7 text-sm text-salvia-secondary max-w-[60ch]">Componentes, props y estados tal como están implementados en este prototipo.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="n in notes" :key="n.name" class="bg-white border border-salvia-cardBorder rounded-2xl p-4.5">
          <div class="font-mono text-xs font-bold text-salvia-primary">{{ n.name }}</div>
          <div class="text-sm text-salvia-dark mt-2 leading-relaxed">{{ n.desc }}</div>
          <div class="text-xs text-salvia-secondary mt-2.5 leading-relaxed"><strong class="text-salvia-dark">Props:</strong> {{ n.props }}</div>
          <div class="text-xs text-salvia-secondary mt-1.5 leading-relaxed"><strong class="text-salvia-dark">Estados:</strong> {{ n.states }}</div>
        </div>
      </div>
    </div>
  </section>
</template>
