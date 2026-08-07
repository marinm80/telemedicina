<script setup lang="ts">
import { ref, nextTick } from 'vue';
import type { PublicAssistantResponse, PublicAssistantDoctor } from '@/types/api.types';
import { getCsrfToken } from '@/lib/appointmentHelpers';

const emit = defineEmits<{
  (e: 'scrollToDirectory'): void;
}>();

// ── TYPES ─────────────────────────────────────────────────────────────
interface ChatMessage {
  id: number;
  role: 'user' | 'agent';
  text?: string;
  specialty?: string;
  specialtyIcon?: string;
  doctors?: Array<{
    name: string;
    specialty: string;
    university: string;
    years: string;
    price: string;
  }>;
  slots?: string[];
  info?: Array<{ label: string; value: string }>;
  authGate?: boolean;
}

// ── LOCAL DATA ────────────────────────────────────────────────────────
const DOCTORS = [
  { id: 'ag', name: 'Dra. Ana García', specialty: 'Cardiología', university: 'U. Nacional Autónoma de Honduras', cert: 'Certificada en cardiología preventiva', years: '12 años de experiencia', price: '45 US$ / consulta', slots: ['Hoy 16:40', 'Hoy 18:10', 'Mañana 09:20', 'Mañana 11:00'] },
  { id: 'ft', name: 'Dr. Fernando Torres', specialty: 'Medicina Interna', university: 'U. Central de Venezuela', cert: 'Certificado en medicina preventiva', years: '18 años de experiencia', price: '38 US$ / consulta', slots: ['Hoy 15:00', 'Hoy 17:30', 'Mañana 08:40', 'Mañana 12:15'] },
  { id: 'rh', name: 'Dr. Roberto Hernández', specialty: 'Neurología', university: 'U. de Chile', cert: 'Certificado en trastornos del movimiento', years: '20 años de experiencia', price: '52 US$ / consulta', slots: ['Mañana 10:00', 'Mañana 13:20', 'Jue 09:00', 'Jue 16:00'] },
  { id: 'cm', name: 'Dr. Carlos Mendoza', specialty: 'Dermatología', university: 'U. de Buenos Aires', cert: 'Certificado en dermatoscopia digital', years: '8 años de experiencia', price: '40 US$ / consulta', slots: ['Hoy 17:00', 'Mañana 10:30', 'Mañana 15:45', 'Jue 11:10'] },
  { id: 'ml', name: 'Dra. María López', specialty: 'Pediatría', university: 'U. de Costa Rica', cert: 'Subespecialidad en neonatología', years: '15 años de experiencia', price: '35 US$ / consulta', slots: ['Hoy 14:20', 'Hoy 16:00', 'Mañana 09:00', 'Mañana 11:40'] },
  { id: 'lr', name: 'Dra. Laura Ramírez', specialty: 'Psiquiatría', university: 'U. de Guadalajara', cert: 'Terapia cognitivo-conductual', years: '10 años de experiencia', price: '48 US$ / consulta', slots: ['Mañana 08:00', 'Mañana 14:00', 'Jue 10:20', 'Vie 09:40'] }
];

const SPECIALTIES = [
  { name: 'Cardiología', icon: '🫀', count: 14, from: '45 US$' },
  { name: 'Medicina Interna', icon: '🩺', count: 22, from: '38 US$' },
  { name: 'Dermatología', icon: '🧴', count: 11, from: '40 US$' },
  { name: 'Pediatría', icon: '🧒', count: 18, from: '35 US$' },
  { name: 'Neurología', icon: '🧠', count: 9, from: '52 US$' },
  { name: 'Psiquiatría', icon: '🌱', count: 12, from: '48 US$' }
];

const CLINIC = {
  address: 'Av. Los Próceres 1240, Torre Médica Salvia, piso 4 · Tegucigalpa',
  hours: 'Lun a Vie 7:00–19:00 · Sáb 8:00–14:00 · Dom cerrado',
  phone: '+504 2230-4400 · WhatsApp +504 9880-1122',
  insurers: 'Palig, Ficohsa Seguros, Mapfre y BUPA Latinoamérica',
  urgent: 'Urgencias 24/7 en planta baja, sin cita previa'
};

const INTENTS = [
  {
    kw: ['dónde', 'donde', 'dirección', 'direccion', 'ubicac', 'llegar', 'parqueo', 'estacion'],
    reply: 'Estamos en el centro de la ciudad, con parqueo propio en los dos primeros niveles.',
    info: [
      { label: 'Dirección', value: CLINIC.address },
      { label: 'Parqueo', value: 'Gratis las primeras 2 horas con validación en recepción' },
      { label: 'Referencia', value: 'Frente al Parque La Concordia, entrada por calle lateral' }
    ]
  },
  {
    kw: ['horario', 'hora', 'abren', 'cierran', 'abierto', 'sábado', 'sabado', 'domingo', 'fin de semana'],
    reply: 'Estos son nuestros horarios de atención:',
    info: [
      { label: 'Consulta', value: CLINIC.hours },
      { label: 'Laboratorio', value: 'Lun a Sáb 6:30–12:00, en ayunas sin cita' },
      { label: 'Urgencias', value: CLINIC.urgent }
    ]
  },
  {
    kw: ['teléfono', 'telefono', 'llamar', 'whatsapp', 'contacto', 'correo'],
    reply: 'Puedes contactarnos por teléfono o WhatsApp en horario de consulta.',
    info: [
      { label: 'Teléfono', value: CLINIC.phone },
      { label: 'Correo', value: 'recepcion@salvia.hn' },
      { label: 'Respuesta', value: 'Menos de 20 s por chat, hasta 2 h por correo' }
    ]
  },
  {
    kw: ['seguro', 'aseguradora', 'cobertura', 'póliza', 'poliza'],
    reply: 'Trabajamos con las principales aseguradoras del país.',
    info: [
      { label: 'Convenios', value: CLINIC.insurers },
      { label: 'Reembolso', value: 'Emitimos factura y informe clínico el mismo día' },
      { label: 'Sin seguro', value: 'Tarifa privada desde 35 US$ por consulta' }
    ]
  },
  {
    kw: ['precio', 'costo', 'cuesta', 'tarifa', 'cuánto', 'cuanto', 'pago', 'pagar'],
    reply: 'El precio depende de la especialidad. Estas son las tarifas privadas vigentes:',
    priceList: true
  },
  {
    kw: ['especialidad', 'especialista', 'médicos', 'medicos', 'doctores', 'atienden', 'tienen'],
    reply: 'Tenemos 12 especialidades con médicos certificados. Algunos de nuestros profesionales:',
    doctors: 3
  },
  {
    kw: ['examen', 'laboratorio', 'análisis', 'analisis', 'rayos', 'ultrasonido', 'imagen'],
    reply: 'Contamos con laboratorio e imagenología en el mismo edificio.',
    info: [
      { label: 'Laboratorio', value: 'Hematología, química sanguínea, perfiles hormonales' },
      { label: 'Imagen', value: 'Rayos X, ultrasonido y mamografía digital' },
      { label: 'Resultados', value: 'En tu cuenta, entre 4 y 24 horas' }
    ]
  }
];

const BOOK_KW = ['cita', 'agendar', 'agenda', 'reservar', 'reserva', 'turno', 'consulta con', 'me duele', 'dolor', 'síntoma', 'sintoma', 'necesito ver'];
const QUICK_START = ['¿Dónde están ubicados?', '¿Qué horarios tienen?', '¿Qué especialidades atienden?', '¿Aceptan mi seguro?'];

// ── REACTION STATES ──────────────────────────────────────────────────
const messages = ref<ChatMessage[]>([
  { id: 1, role: 'agent', text: 'Hola 👋 Soy la recepción de Salvia. Puedo darte dirección, horarios, especialidades, precios y convenios de seguro. Para agendar una cita necesitarás tu cuenta.' }
]);
const input = ref('');
const typing = ref(false);
const quick = ref<string[]>(QUICK_START);
const heroChips = QUICK_START.map(q => ({
  label: q,
  onSelect: () => sendMessage(q)
}));
const chatBodyRef = ref<HTMLDivElement | null>(null);

let seq = 2;
let timers: ReturnType<typeof setTimeout>[] = [];

async function scrollDown() {
  await nextTick();
  if (chatBodyRef.value) {
    chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
  }
}

function pushMessage(msg: Omit<ChatMessage, 'id'>) {
  messages.value.push({ id: seq++, ...msg });
  scrollDown();
}

function resetChat() {
  timers.forEach(clearTimeout);
  timers = [];
  seq = 2;
  messages.value = [{ id: 1, role: 'agent', text: 'Empecemos de nuevo. ¿Qué necesitas saber de la clínica?' }];
  input.value = '';
  typing.value = false;
  quick.value = QUICK_START;
  scrollDown();
}

// Specialty click integration
function fromSpecialty(sName: string) {
  const matchSpecialty = SPECIALTIES.find(s => s.name.toLowerCase() === sName.toLowerCase());
  const icon = matchSpecialty?.icon || '🩺';
  const count = matchSpecialty?.count || 10;
  const from = matchSpecialty?.from || '35 US$';

  pushMessage({ role: 'user', text: 'Cuéntame de ' + sName.toLowerCase() });
  typing.value = true;
  quick.value = [];
  scrollDown();

  const t = setTimeout(() => {
    typing.value = false;
    quick.value = ['Quiero agendar una cita'];
    
    const list = DOCTORS.filter(d => d.specialty.toLowerCase() === sName.toLowerCase()).map(d => ({
      name: d.name,
      specialty: d.specialty,
      university: d.university,
      years: d.years,
      price: d.price
    }));

    pushMessage({
      role: 'agent',
      text: 'En ' + sName.toLowerCase() + ' tenemos ' + count + ' médicos certificados. Consulta desde ' + from + '.',
      specialty: sName,
      specialtyIcon: icon,
      info: [{ label: 'Atención', value: CLINIC.hours }, { label: 'Consulta', value: 'Desde ' + from }],
      doctors: list.length ? list : undefined
    });
    scrollDown();
  }, 900);
  timers.push(t);
}

// Expose so parent can trigger
defineExpose({
  fromSpecialty
});

async function sendMessage(text: string) {
  const q = text.trim();
  if (!q) return;

  pushMessage({ role: 'user', text: q });
  input.value = '';
  typing.value = true;
  quick.value = [];
  await scrollDown();

  const tQuery = q.toLowerCase();
  const intent = INTENTS.find(i => i.kw.some(k => tQuery.includes(k)));
  const wantsBooking = BOOK_KW.some(k => tQuery.includes(k));

  const t = setTimeout(async () => {
    typing.value = false;
    
    if (intent && !(wantsBooking && !intent.priceList)) {
      const msg: Omit<ChatMessage, 'id'> = { role: 'agent', text: intent.reply };
      
      if (intent.info) {
        msg.info = intent.info;
      }
      if (intent.doctors) {
        msg.doctors = DOCTORS.slice(0, intent.doctors).map(d => ({
          name: d.name,
          specialty: d.specialty,
          university: d.university,
          years: d.years,
          price: d.price
        }));
      }
      if (intent.priceList) {
        msg.info = SPECIALTIES.slice(0, 6).map(s => ({ label: s.name, value: 'Desde ' + s.from }));
      }
      
      pushMessage(msg);
      quick.value = ['Quiero agendar una cita'];
      await scrollDown();
    } else if (wantsBooking) {
      pushMessage({ role: 'agent', text: 'Con gusto te ayudo a reservar.' });
      pushMessage({ role: 'agent', authGate: true });
      quick.value = ['¿Qué horarios tienen?', '¿Aceptan mi seguro?'];
      await scrollDown();
    } else {
      // Fallback: Real assistant call
      try {
        const res = await fetch('/api/assistant/public', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
          },
          body: JSON.stringify({ query: q }),
        });

        if (res.ok) {
          const data: PublicAssistantResponse = await res.json();
          pushMessage({
            role: 'agent',
            text: data.reply,
            doctors: data.doctors && data.doctors.length > 0 ? data.doctors.map(d => ({
              name: d.name,
              specialty: d.description.split(' · ')[0] || 'Médico',
              university: d.description.split(' · ')[1] || 'Universidad',
              years: 'Especialista',
              price: `$${d.consultation_fee}`
            })) : undefined
          });
        } else {
          pushMessage({
            role: 'agent',
            text: 'Puedo ayudarte con dirección, horarios, especialidades, precios, convenios de seguro y exámenes. ¿Sobre qué te informo?',
            info: [{ label: 'Dirección', value: CLINIC.address }, { label: 'Horario', value: CLINIC.hours }]
          });
          quick.value = QUICK_START;
        }
      } catch {
        pushMessage({
          role: 'agent',
          text: 'Puedo ayudarte con dirección, horarios, especialidades, precios, convenios de seguro y exámenes. ¿Sobre qué te informo?',
          info: [{ label: 'Dirección', value: CLINIC.address }, { label: 'Horario', value: CLINIC.hours }]
        });
        quick.value = QUICK_START;
      }
      await scrollDown();
    }
  }, 900);
  timers.push(t);
}

function handleSearch() {
  sendMessage(input.value);
}
</script>

<template>
  <section class="flex flex-col lg:flex-row gap-8 lg:gap-14 items-stretch py-10 lg:py-16">
    <!-- Left Column: Content -->
    <div class="flex-1 min-w-[320px] flex flex-col justify-center gap-6">
      <div class="inline-flex align-items-center gap-2 self-start bg-salvia-badge text-salvia-primary px-4 py-2 rounded-full text-xs font-semibold">
        <span class="inline-block w-2 height-2 rounded-full bg-salvia-primary animate-pulse"></span>
        Recepción abierta ahora · responde en 20 s
      </div>

      <h1 class="m-0 font-serif font-normal text-4xl sm:text-5xl lg:text-7xl leading-none tracking-tight">
        Pregunta lo que<br>
        necesites saber.<br>
        <span class="italic text-salvia-primary">Te respondemos ya.</span>
      </h1>

      <p class="m-0 max-w-[46ch] text-base lg:text-lg leading-relaxed text-salvia-secondary">
        Nuestra recepción digital resuelve dudas al instante: dirección, horarios, especialidades, precios y seguros. Cuando quieras reservar, entra con tu cuenta y el asistente recoge tus síntomas y agenda por ti.
      </p>

      <div class="flex flex-col gap-3">
        <div class="text-xs tracking-wider uppercase text-salvia-textSubtle font-bold">Pregúntale a la recepción</div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="chip in heroChips"
            :key="chip.label"
            type="button"
            @click="chip.onSelect"
            class="border border-salvia-cardBorder bg-white text-salvia-dark text-sm font-medium px-4 py-2.5 rounded-full cursor-pointer hover:border-salvia-primary hover:bg-salvia-badge hover:text-salvia-primary transition-all duration-200"
          >
            {{ chip.label }}
          </button>
        </div>
      </div>

      <div class="flex gap-7 flex-wrap pt-2 border-t border-salvia-cardBorder">
        <div class="pt-5">
          <div class="font-serif text-3xl leading-none">20 s</div>
          <div class="text-xs text-salvia-secondary mt-1.5">Respuesta del asistente</div>
        </div>
        <div class="pt-5">
          <div class="font-serif text-3xl leading-none">12</div>
          <div class="text-xs text-salvia-secondary mt-1.5">Especialidades</div>
        </div>
        <div class="pt-5">
          <div class="font-serif text-3xl leading-none">100 %</div>
          <div class="text-xs text-salvia-secondary mt-1.5">Médicos certificados</div>
        </div>
      </div>
    </div>

    <!-- Right Column: Interactive Chat -->
    <div class="flex-1 min-w-[320px] relative">
      <div class="absolute inset-y-0 right-0 -bottom-3.5 -right-3.5 w-[62%] h-[74%] rounded-3xl rounded-tr-[120px] bg-salvia-badge -z-10"></div>
      <div class="relative flex flex-col gap-4">
        <!-- Tiny visual avatars -->
        <div class="flex gap-3 items-stretch h-64">
          <div class="flex-1 h-full rounded-3xl rounded-bl-[90px] overflow-hidden">
            <img src="/images/hero-doctor.jpg" alt="Doctora Salvia" class="w-full h-full object-cover" />
          </div>
          <div class="flex-none w-32 h-full rounded-3xl rounded-tr-[90px] overflow-hidden">
            <img src="/images/hero-patient.jpg" alt="Paciente Salvia" class="w-full h-full object-cover" />
          </div>
        </div>

        <!-- Chat Container -->
        <div class="bg-white border border-salvia-cardBorder rounded-[26px] shadow-xl overflow-hidden flex flex-col h-[520px]">
          <!-- Chat Header -->
          <div class="flex items-center gap-3 px-5 py-4 border-b border-salvia-cardBorder bg-white">
            <div class="w-9 h-9 rounded-xl bg-salvia-primary flex items-center justify-center">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FAF5EE" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v3"></path>
                <rect x="4" y="6" width="16" height="12" rx="4"></rect>
                <circle cx="9" cy="12" r="1.3" fill="#FAF5EE" stroke="none"></circle>
                <circle cx="15" cy="12" r="1.3" fill="#FAF5EE" stroke="none"></circle>
                <path d="M2 11v3M22 11v3"></path>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold">Recepción Salvia</div>
              <div class="text-xs text-salvia-secondary flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                En línea · información y horarios
              </div>
            </div>
            <button
              type="button"
              @click="resetChat"
              class="border border-salvia-cardBorder bg-salvia-bg text-salvia-secondary text-xs font-semibold px-3 py-2 rounded-full cursor-pointer hover:text-salvia-orange hover:border-salvia-orange transition-colors"
            >
              Reiniciar
            </button>
          </div>

          <!-- Chat Body -->
          <div ref="chatBodyRef" class="chatscroll flex-1 overflow-y-auto p-5 flex flex-col gap-4 bg-gradient-to-b from-[#FFFDF9] to-white">
            <div v-for="m in messages" :key="m.id" class="flex flex-col gap-2.5 animate-[riseIn_0.32s_ease_both]">
              <!-- User Message -->
              <div v-if="m.role === 'user'" class="self-end max-w-[82%] bg-salvia-dark text-salvia-bg px-4 py-3 rounded-3xl rounded-br-md text-sm leading-relaxed">
                {{ m.text }}
              </div>

              <!-- Agent Text Message -->
              <div v-if="m.role === 'agent' && m.text" class="self-start max-w-[88%] bg-[#F4F1EA] text-salvia-dark px-4 py-3 rounded-3xl rounded-bl-md text-sm leading-relaxed">
                {{ m.text }}
              </div>

              <!-- Specialty Card -->
              <div v-if="m.specialty" class="self-start flex items-center gap-3 bg-salvia-badge border border-[#CFE3DA] p-3 rounded-2xl">
                <div class="text-2xl leading-none">{{ m.specialtyIcon }}</div>
                <div>
                  <div class="text-[11px] tracking-widest uppercase text-salvia-primary font-bold">Información de la especialidad</div>
                  <div class="text-base font-bold mt-0.5">{{ m.specialty }}</div>
                </div>
              </div>

              <!-- Doctors List -->
              <div v-if="m.doctors" class="flex flex-col gap-2.5">
                <div v-for="(d, idx) in m.doctors" :key="idx" class="border border-salvia-cardBorder bg-white rounded-2xl p-3.5 flex gap-3 items-start">
                  <div class="w-11 h-11 rounded-full bg-salvia-badge text-salvia-primary flex items-center justify-center font-bold text-sm">
                    {{ d.name.split(' ').slice(1).map(n => n[0]).join('') }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold">{{ d.name }}</div>
                    <div class="text-xs text-salvia-primary font-semibold">{{ d.specialty }}</div>
                    <div class="text-[12px] text-salvia-secondary mt-1.5 leading-relaxed">{{ d.university }} · {{ d.years }}</div>
                    <div class="flex items-center gap-2 mt-2 flex-wrap">
                      <span class="text-[12px] font-bold text-salvia-primary bg-salvia-badge px-2.5 py-1 rounded-full">{{ d.price }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Slots Picker -->
              <div v-if="m.slots" class="flex flex-wrap gap-2">
                <button
                  v-for="s in m.slots"
                  :key="s"
                  type="button"
                  @click="emit('scrollToDirectory')"
                  class="border border-[#CFE3DA] bg-white text-salvia-primary text-sm font-semibold px-3 py-2 rounded-xl cursor-pointer hover:bg-salvia-primary hover:text-white hover:border-salvia-primary transition-all"
                >
                  {{ s }}
                </button>
              </div>

              <!-- Info Table -->
              <div v-if="m.info" class="border border-salvia-cardBorder bg-[#FFFDF9] rounded-2xl p-4 flex flex-col gap-3">
                <div v-for="row in m.info" :key="row.label" class="flex gap-3 items-baseline justify-between">
                  <div class="text-[11px] tracking-widest uppercase text-salvia-textSubtle font-bold flex-none">{{ row.label }}</div>
                  <div class="text-sm text-salvia-dark text-right leading-relaxed">{{ row.value }}</div>
                </div>
              </div>

              <!-- Auth Gate Banner -->
              <div v-if="m.authGate" class="border border-[#CFE3DA] bg-[#F3FAF5] rounded-2xl p-4.5">
                <div class="flex items-center gap-2 text-salvia-primary text-xs font-bold tracking-widest uppercase">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="10" width="16" height="10" rx="3"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                  </svg>
                  Se necesita cuenta
                </div>
                <div class="text-sm leading-relaxed mt-2.5 text-salvia-dark">
                  Puedo darte toda la información que necesites, pero para reservar una cita hay que entrar con tu cuenta. Ahí dentro el asistente ya recoge tus síntomas y te propone médico y horario.
                </div>
                <div class="flex gap-2.5 mt-4 flex-wrap">
                  <a href="/register" class="border-none bg-salvia-primary text-white text-xs font-bold px-4 py-2.5 rounded-full hover:bg-salvia-dark transition-colors">Crear cuenta</a>
                  <a href="/login" class="border border-[#CFE3DA] bg-transparent text-salvia-primary text-xs font-semibold px-4 py-2.5 rounded-full hover:bg-salvia-badge transition-colors">Ya tengo cuenta</a>
                </div>
              </div>
            </div>

            <!-- Typing Dot Indicator -->
            <div v-if="typing" class="self-start flex gap-1.5 bg-[#F4F1EA] px-4 py-3.5 rounded-3xl rounded-bl-md">
              <span class="w-1.5 h-1.5 rounded-full bg-salvia-secondary animate-[dot_1.3s_infinite]"></span>
              <span class="w-1.5 h-1.5 rounded-full bg-salvia-secondary animate-[dot_1.3s_0.18s_infinite]"></span>
              <span class="w-1.5 h-1.5 rounded-full bg-salvia-secondary animate-[dot_1.3s_0.36s_infinite]"></span>
            </div>
          </div>

          <!-- Chat Footer Form -->
          <div class="border-t border-salvia-cardBorder p-3.5 bg-white">
            <div v-if="quick.length > 0" class="flex gap-2 overflow-x-auto pb-3 chatscroll">
              <button
                v-for="q in quick"
                :key="q"
                type="button"
                @click="sendMessage(q)"
                class="flex-none border border-salvia-cardBorder bg-salvia-bg text-salvia-dark text-xs font-medium px-3.5 py-2 rounded-full cursor-pointer whitespace-nowrap hover:border-salvia-primary hover:color-salvia-primary transition-all"
              >
                {{ q }}
              </button>
            </div>
            <form @submit.prevent="handleSearch" class="flex items-center gap-2 bg-salvia-bg border border-salvia-cardBorder rounded-full p-1.5 pl-4">
              <input
                v-model="input"
                placeholder="Pregunta por horarios, precios, ubicación…"
                class="flex-1 min-w-0 border-none outline-none bg-transparent text-sm text-salvia-dark py-2.5"
                :disabled="typing"
              />
              <button
                type="submit"
                :disabled="!input.trim() || typing"
                class="flex-none w-10 h-10 border-none rounded-full bg-salvia-dark text-salvia-bg cursor-pointer flex items-center justify-center hover:bg-salvia-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h13M13 6l6 6-6 6"></path>
                </svg>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@keyframes dot {
  0%, 60%, 100% { opacity: .25; transform: translateY(0); }
  30% { opacity: 1; transform: translateY(-3px); }
}
@keyframes riseIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: none; }
}
</style>
