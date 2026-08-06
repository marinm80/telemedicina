/**
 * ====================================================================
 * Agent State Machine — Motor declarativo sin LLM
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * State machine para el flujo conversacional del agente de agendamiento.
 * Cada estado define: mensaje a mostrar, opciones rápidas, validaciones,
 * y la transición al siguiente estado basada en la entrada del usuario.
 */

// ── Types ──

export type AgentStateId =
  | 'WELCOME'
  | 'EMERGENCY_CHECK'
  | 'EMERGENCY_STOP'
  | 'COLLECT_MOTIVO'
  | 'COLLECT_SYMPTOMS_ONSET'
  | 'COLLECT_SYMPTOMS_SEVERITY'
  | 'COLLECT_SYMPTOMS_DURATION'
  | 'COLLECT_ALLERGIES'
  | 'COLLECT_MEDICATIONS'
  | 'TRIAGE_DECISION'
  | 'SUGGEST_PRESENCIAL'
  | 'SELECT_MODALITY'
  | 'TIME_PREFERENCE'
  | 'SELECT_SPECIALTY'
  | 'SELECT_DOCTOR'
  | 'SELECT_DATE'
  | 'SELECT_SLOT'
  | 'CONFIRMATION'
  | 'BOOKING_SUCCESS'
  | 'DEMO_PREVIEW'
  | 'ESCALATE_HUMAN'
  | 'IDLE';

export interface QuickOption {
  label: string;
  value: string;
  icon?: string;
}

export interface AgentState {
  id: AgentStateId;
  message: string;
  quickOptions?: QuickOption[];
  inputEnabled?: boolean;
  inputPlaceholder?: string;
  validate?: (input: string) => string | null; // returns error message or null
  next: (input: string, context: AgentContext) => AgentStateId;
}

export interface PatientData {
  motivo: string;
  symptomsOnset: string;
  symptomsSeverity: 'leve' | 'moderado' | 'severo' | '';
  symptomsDuration: string;
  allergies: string;
  currentMedications: string;
}

export interface AgentContext {
  patientData: PatientData;
  bookingData: {
    specialtyId: string;
    specialtyName: string;
    doctorId: string;
    doctorProfileId: string;
    doctorName: string;
    fecha: string;
    slotStart: string;
    slotEnd: string;
    slotLocalTime: string;
  };
  preferredTimeOfDay: 'mañana' | 'tarde' | 'noche' | '';
  modality: 'teleconsulta' | 'presencial' | '';
  emergencyDetected: boolean;
  triageResult: 'teleconsulta' | 'presencial' | 'emergencia' | 'escalado' | '';
  userName: string;
}

// ── Emergency Keywords Detection ──

const EMERGENCY_KEYWORDS = [
  'dolor torácico', 'dolor en el pecho', 'dolor pecho', 'chest pain',
  'no puedo respirar', 'dificultad para respirar', 'dificultad respirar',
  'asfixia', 'ahogando', 'ahogo',
  'pérdida de conciencia', 'desmayo', 'inconsciente', 'convulsiones',
  'sangrado abundante', 'hemorragia', 'sangre mucho',
  'derrame cerebral', 'cara caída', 'brazo dormido', 'no puedo hablar',
  'infarto', 'ataque cardiaco', 'ataque al corazón',
  'suicidio', 'suicidarme', 'quiero morir', 'autolesión',
  'sobredosis', 'envenenamiento', 'intoxicación grave',
];

export function detectEmergency(text: string): boolean {
  const lower = text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  return EMERGENCY_KEYWORDS.some(kw => {
    const normalizedKw = kw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return lower.includes(normalizedKw);
  });
}

// ── Severity Detection (for triage) ──

export function detectSeverity(text: string): 'leve' | 'moderado' | 'severo' {
  const lower = text.toLowerCase();
  if (['severo', 'grave', 'insoportable', 'muy fuerte', 'intenso', 'terrible', '10', '9', '8'].some(k => lower.includes(k))) return 'severo';
  if (['moderado', 'medio', 'regular', 'molesto', '5', '6', '7'].some(k => lower.includes(k))) return 'moderado';
  return 'leve';
}

// ── State Definitions ──

export function createStateMachine(): Record<AgentStateId, AgentState> {
  return {
    WELCOME: {
      id: 'WELCOME',
      message: `👋 Hola, soy el asistente de <strong>Salvia</strong>.
        <div style="background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 10px; padding: 10px; margin-top: 8px; font-size: 0.82rem;">
          ⚠️ <strong>Aviso importante:</strong> Si crees que estás en una <strong>emergencia médica</strong>, llama al <strong>911</strong> ahora. Este servicio no sustituye atención de urgencia.
        </div>`,
      quickOptions: [
        { label: '📅 Agendar cita', value: 'agendar', icon: 'pi-calendar' },
        { label: '🔍 Buscar médico', value: 'buscar', icon: 'pi-search' },
        { label: '❓ Tengo dudas', value: 'dudas', icon: 'pi-question-circle' },
      ],
      inputEnabled: true,
      inputPlaceholder: 'Escribe qué necesitas...',
      next: (input: string, ctx: AgentContext) => {
        if (detectEmergency(input)) {
          ctx.emergencyDetected = true;
          return 'EMERGENCY_STOP';
        }
        const bookingKeywords = ['agendar', 'cita', 'turno', 'reservar', 'consulta', 'doctor', 'médico', 'medico'];
        if (bookingKeywords.some(k => input.toLowerCase().includes(k)) || input === 'agendar') {
          return 'EMERGENCY_CHECK';
        }
        return 'IDLE';
      },
    },

    EMERGENCY_CHECK: {
      id: 'EMERGENCY_CHECK',
      message: `Antes de continuar, necesito descartar una emergencia.
        <br><br><strong>¿Presentas alguno de estos síntomas?</strong>
        <div style="font-size: 0.82rem; margin-top: 6px; color: #991B1B;">
          • Dolor fuerte en el pecho<br>
          • Dificultad severa para respirar<br>
          • Pérdida de conciencia o convulsiones<br>
          • Sangrado abundante que no para<br>
          • Debilidad súbita en cara/brazo, dificultad para hablar
        </div>`,
      quickOptions: [
        { label: '✅ No, ninguno', value: 'no_emergency' },
        { label: '🚨 Sí, necesito ayuda urgente', value: 'yes_emergency' },
      ],
      inputEnabled: false,
      next: (input: string, ctx: AgentContext) => {
        if (input === 'yes_emergency') {
          ctx.emergencyDetected = true;
          return 'EMERGENCY_STOP';
        }
        return 'COLLECT_MOTIVO';
      },
    },

    EMERGENCY_STOP: {
      id: 'EMERGENCY_STOP',
      message: `🚨 <strong>EMERGENCIA DETECTADA</strong>
        <div style="background: #FEE2E2; border: 2px solid #DC2626; border-radius: 10px; padding: 14px; margin-top: 8px;">
          <strong style="font-size: 1.1rem; color: #991B1B;">Llama al 911 AHORA</strong>
          <br><br>
          <div style="font-size: 0.85rem; color: #7F1D1D;">
            Los síntomas que describes requieren atención médica <strong>inmediata</strong>.
            <br><br>
            📞 <strong>Emergencias: 911</strong><br>
            🏥 Acude a la sala de urgencias más cercana<br>
            💊 Si alguien está contigo, solicita ayuda
          </div>
          <br>
          <div style="font-size: 0.78rem; color: #9CA3AF; border-top: 1px solid #FCA5A5; padding-top: 6px;">
            Este servicio de telemedicina <strong>no puede</strong> atender emergencias.
            El flujo de agendamiento ha sido detenido por seguridad.
          </div>
        </div>`,
      quickOptions: [],
      inputEnabled: false,
      next: () => 'EMERGENCY_STOP', // Terminal state
    },

    COLLECT_MOTIVO: {
      id: 'COLLECT_MOTIVO',
      message: '¡Perfecto! Vamos a agendar tu cita. 📅<br><br>Primero, <strong>¿cuál es el motivo de tu consulta?</strong> Descríbelo brevemente.',
      inputEnabled: true,
      inputPlaceholder: 'Ej: dolor de cabeza frecuente, control general...',
      validate: (input: string) => input.trim().length < 3 ? 'Por favor describe el motivo con al menos unas palabras.' : null,
      next: (input: string, ctx: AgentContext) => {
        ctx.patientData.motivo = input;
        if (detectEmergency(input)) {
          ctx.emergencyDetected = true;
          return 'EMERGENCY_STOP';
        }
        return 'COLLECT_SYMPTOMS_ONSET';
      },
    },

    COLLECT_SYMPTOMS_ONSET: {
      id: 'COLLECT_SYMPTOMS_ONSET',
      message: 'Entendido. Para ayudarte mejor, necesito algunos datos clínicos.<br><br>📋 <strong>¿Cuándo comenzaron los síntomas?</strong>',
      quickOptions: [
        { label: 'Hoy', value: 'hoy' },
        { label: 'Hace unos días', value: 'dias' },
        { label: 'Hace semanas', value: 'semanas' },
        { label: 'Hace meses', value: 'meses' },
        { label: 'Es un control / chequeo', value: 'control' },
      ],
      inputEnabled: true,
      inputPlaceholder: 'Ej: hace 3 días...',
      next: (input: string, ctx: AgentContext) => {
        ctx.patientData.symptomsOnset = input;
        if (input === 'control') return 'SELECT_DOCTOR'; // skip symptoms
        return 'COLLECT_SYMPTOMS_SEVERITY';
      },
    },

    COLLECT_SYMPTOMS_SEVERITY: {
      id: 'COLLECT_SYMPTOMS_SEVERITY',
      message: '<strong>¿Cómo calificarías la intensidad?</strong>',
      quickOptions: [
        { label: '🟢 Leve — molestia menor', value: 'leve' },
        { label: '🟡 Moderado — afecta mi rutina', value: 'moderado' },
        { label: '🔴 Severo — muy intenso', value: 'severo' },
      ],
      inputEnabled: false,
      next: (input: string, ctx: AgentContext) => {
        ctx.patientData.symptomsSeverity = input as any;
        return 'COLLECT_SYMPTOMS_DURATION';
      },
    },

    COLLECT_SYMPTOMS_DURATION: {
      id: 'COLLECT_SYMPTOMS_DURATION',
      message: '<strong>¿Los síntomas son constantes o van y vienen?</strong>',
      quickOptions: [
        { label: 'Constantes', value: 'constantes' },
        { label: 'Intermitentes', value: 'intermitentes' },
        { label: 'Solo en ciertos momentos', value: 'momentos' },
      ],
      inputEnabled: true,
      inputPlaceholder: 'O describe con más detalle...',
      next: (input: string, ctx: AgentContext) => {
        ctx.patientData.symptomsDuration = input;
        return 'COLLECT_ALLERGIES';
      },
    },

    COLLECT_ALLERGIES: {
      id: 'COLLECT_ALLERGIES',
      message: '<strong>¿Tienes alergias conocidas?</strong> (medicamentos, alimentos, etc.)',
      quickOptions: [
        { label: 'No tengo alergias', value: 'ninguna' },
      ],
      inputEnabled: true,
      inputPlaceholder: 'Ej: penicilina, mariscos...',
      next: (input: string, ctx: AgentContext) => {
        ctx.patientData.allergies = input === 'ninguna' ? 'Ninguna conocida' : input;
        return 'COLLECT_MEDICATIONS';
      },
    },

    COLLECT_MEDICATIONS: {
      id: 'COLLECT_MEDICATIONS',
      message: '<strong>¿Tomas algún medicamento actualmente?</strong>',
      quickOptions: [
        { label: 'No tomo medicamentos', value: 'ninguno' },
      ],
      inputEnabled: true,
      inputPlaceholder: 'Ej: losartán 50mg, metformina...',
      next: (input: string, ctx: AgentContext) => {
        ctx.patientData.currentMedications = input === 'ninguno' ? 'Ninguno' : input;
        return 'TRIAGE_DECISION';
      },
    },

    TRIAGE_DECISION: {
      id: 'TRIAGE_DECISION',
      message: '', // dynamically generated by FloatingAssistant
      quickOptions: [],
      inputEnabled: false,
      next: (_input: string, ctx: AgentContext) => {
        // Delegate to triage rules engine (imported by FloatingAssistant)
        // The actual evaluation happens in the component; here we just
        // check what was set in ctx.triageResult
        if (ctx.triageResult === 'emergencia') return 'EMERGENCY_STOP';
        if (ctx.triageResult === 'presencial') return 'SUGGEST_PRESENCIAL';
        if (ctx.triageResult === 'escalado') return 'ESCALATE_HUMAN';
        return 'SELECT_MODALITY';
      },
    },

    SUGGEST_PRESENCIAL: {
      id: 'SUGGEST_PRESENCIAL',
      message: `⚠️ Según tus respuestas, los síntomas son <strong>severos y recientes</strong>.
        <br><br>Te recomendamos acudir a un centro médico para evaluación presencial.
        <br><br>Sin embargo, puedes agendar una teleconsulta si lo prefieres.`,
      quickOptions: [
        { label: '🏥 Entendido, buscaré atención presencial', value: 'presencial' },
        { label: '📱 Quiero teleconsulta de todas formas', value: 'teleconsulta' },
      ],
      inputEnabled: false,
      next: (input: string) => {
        if (input === 'presencial') return 'IDLE';
        return 'SELECT_DOCTOR';
      },
    },

    SELECT_MODALITY: {
      id: 'SELECT_MODALITY',
      message: '🏥 <strong>¿Cómo prefieres tu consulta?</strong>',
      quickOptions: [
        { label: '💻 Teleconsulta (remota)', value: 'teleconsulta' },
        { label: '🏢 Presencial (en sitio)', value: 'presencial' },
      ],
      inputEnabled: false,
      next: (input: string, ctx: AgentContext) => {
        ctx.modality = input as 'teleconsulta' | 'presencial';
        return 'TIME_PREFERENCE';
      },
    },

    TIME_PREFERENCE: {
      id: 'TIME_PREFERENCE',
      message: '⏰ <strong>¿Tienes preferencia de horario?</strong>',
      quickOptions: [
        { label: '🌅 Mañana (6-12h)', value: 'mañana' },
        { label: '☀️ Tarde (12-18h)', value: 'tarde' },
        { label: '🌙 Noche (18-22h)', value: 'noche' },
        { label: '🤷 Sin preferencia', value: '' },
      ],
      inputEnabled: false,
      next: (input: string, ctx: AgentContext) => {
        ctx.preferredTimeOfDay = input as any;
        return 'SELECT_DOCTOR';
      },
    },

    SELECT_SPECIALTY: {
      id: 'SELECT_SPECIALTY',
      message: '🏥 <strong>¿Qué especialidad necesitas?</strong> Selecciona una opción:',
      inputEnabled: false,
      next: () => 'SELECT_DOCTOR',
    },

    SELECT_DOCTOR: {
      id: 'SELECT_DOCTOR',
      message: 'Te conectaremos con un <strong>médico de Medicina General</strong> que evaluará tu caso. Si es necesario, te referirá a un especialista.',
      inputEnabled: false,
      next: () => 'SELECT_DATE',
    },

    SELECT_DATE: {
      id: 'SELECT_DATE',
      message: '<strong>¿Qué día te gustaría agendar?</strong> Selecciona una fecha:',
      inputEnabled: false,
      next: () => 'SELECT_SLOT',
    },

    SELECT_SLOT: {
      id: 'SELECT_SLOT',
      message: '', // dynamically set
      inputEnabled: false,
      next: () => 'CONFIRMATION',
    },

    CONFIRMATION: {
      id: 'CONFIRMATION',
      message: 'Revisa los datos de tu cita y confirma:',
      inputEnabled: false,
      next: () => 'BOOKING_SUCCESS',
    },

    BOOKING_SUCCESS: {
      id: 'BOOKING_SUCCESS',
      message: '', // dynamically set
      inputEnabled: false,
      next: () => 'DEMO_PREVIEW',
    },

    DEMO_PREVIEW: {
      id: 'DEMO_PREVIEW',
      message: '', // shows demo email modal
      inputEnabled: true,
      inputPlaceholder: '¿Necesitas algo más?',
      next: (input: string) => {
        if (detectEmergency(input)) return 'EMERGENCY_STOP';
        const bookingKeywords = ['agendar', 'cita', 'turno', 'reservar', 'otra'];
        if (bookingKeywords.some(k => input.toLowerCase().includes(k))) return 'EMERGENCY_CHECK';
        return 'IDLE';
      },
    },

    ESCALATE_HUMAN: {
      id: 'ESCALATE_HUMAN',
      message: `🙋 <strong>Transferencia a agente humano</strong>
        <div style="background: #DBEAFE; border: 1px solid #3B82F6; border-radius: 10px; padding: 12px; margin-top: 8px; font-size: 0.85rem;">
          Tu caso ha sido registrado. Un miembro del equipo se pondrá en contacto contigo a la brevedad.
          <br><br>📧 También puedes escribirnos a <strong>soporte@salvia.health</strong>
        </div>`,
      inputEnabled: true,
      inputPlaceholder: '¿Necesitas algo más?',
      next: () => 'IDLE',
    },

    IDLE: {
      id: 'IDLE',
      message: `Puedo ayudarte con:
        <br>• <strong>Agendar citas</strong> con especialistas
        <br>• <strong>Buscar médicos</strong> por especialidad
        <br>• <strong>Información</strong> sobre horarios y disponibilidad
        <br><br>Escribe "agendar cita" para comenzar, o hazme cualquier pregunta. 😊`,
      inputEnabled: true,
      inputPlaceholder: 'Escribe tu mensaje...',
      next: (input: string, ctx: AgentContext) => {
        if (detectEmergency(input)) {
          ctx.emergencyDetected = true;
          return 'EMERGENCY_STOP';
        }
        const bookingKeywords = ['agendar', 'cita', 'turno', 'reservar', 'consulta', 'doctor', 'médico', 'medico'];
        if (bookingKeywords.some(k => input.toLowerCase().includes(k))) {
          return 'EMERGENCY_CHECK';
        }
        if (['ayuda', 'humano', 'agente', 'persona', 'operador'].some(k => input.toLowerCase().includes(k))) {
          return 'ESCALATE_HUMAN';
        }
        return 'IDLE';
      },
    },
  };
}

// ── Context Factory ──

export function createAgentContext(userName?: string): AgentContext {
  return {
    patientData: {
      motivo: '',
      symptomsOnset: '',
      symptomsSeverity: '',
      symptomsDuration: '',
      allergies: '',
      currentMedications: '',
    },
    bookingData: {
      specialtyId: '',
      specialtyName: '',
      doctorId: '',
      doctorProfileId: '',
      doctorName: '',
      fecha: '',
      slotStart: '',
      slotEnd: '',
      slotLocalTime: '',
    },
    preferredTimeOfDay: '',
    modality: '',
    emergencyDetected: false,
    triageResult: '',
    userName: userName || '',
  };
}

// ── Audit Logger ──

export interface AuditEntry {
  timestamp: string;
  state: AgentStateId;
  action: string;
  details?: string;
}

export function createAuditLog(): AuditEntry[] {
  return [];
}

export function logAudit(log: AuditEntry[], state: AgentStateId, action: string, details?: string) {
  log.push({
    timestamp: new Date().toISOString(),
    state,
    action,
    details,
  });
}
