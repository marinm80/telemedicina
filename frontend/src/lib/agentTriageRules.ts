/**
 * ====================================================================
 * Agent Triage Rules — Motor de reglas clínicas sin LLM
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Reglas declarativas para decidir si un caso es manejable por
 * teleconsulta, requiere atención presencial, o es una emergencia.
 * No usa LLM: cada regla es un predicado puro.
 */

import type { PatientData } from './agentStateMachine';

export type TriageResult = 'teleconsulta' | 'presencial' | 'emergencia' | 'escalado';

export interface TriageRule {
  id: string;
  description: string;
  priority: number; // lower = higher priority
  condition: (data: PatientData) => boolean;
  result: TriageResult;
  reason: string;
}

// ── Rule Definitions (ordered by priority) ──

export const TRIAGE_RULES: TriageRule[] = [
  // Priority 1: Emergency keywords in motivo
  {
    id: 'EMRG-001',
    description: 'Emergency keywords detected in motivo',
    priority: 1,
    condition: (d) => {
      const emergency = ['dolor torácico', 'dolor pecho', 'no puedo respirar',
        'desmayo', 'convulsiones', 'sangrado abundante', 'hemorragia',
        'infarto', 'ataque cardiaco', 'sobredosis', 'envenenamiento'];
      const lower = d.motivo.toLowerCase();
      return emergency.some(k => lower.includes(k));
    },
    result: 'emergencia',
    reason: 'Síntomas de emergencia detectados en el motivo de consulta.',
  },

  // Priority 2: Severe + same-day onset
  {
    id: 'PRES-001',
    description: 'Severe symptoms with today onset',
    priority: 2,
    condition: (d) => d.symptomsSeverity === 'severo' && d.symptomsOnset === 'hoy',
    result: 'presencial',
    reason: 'Síntomas severos de inicio hoy requieren evaluación presencial.',
  },

  // Priority 3: Severe + recent (days)
  {
    id: 'PRES-002',
    description: 'Severe symptoms with days onset',
    priority: 3,
    condition: (d) => d.symptomsSeverity === 'severo' && d.symptomsOnset === 'dias',
    result: 'presencial',
    reason: 'Síntomas severos de pocos días de evolución podrían necesitar examen presencial.',
  },

  // Priority 4: Moderate + same-day + constant
  {
    id: 'PRES-003',
    description: 'Moderate constant symptoms started today',
    priority: 4,
    condition: (d) => d.symptomsSeverity === 'moderado' && d.symptomsOnset === 'hoy' && d.symptomsDuration === 'constantes',
    result: 'presencial',
    reason: 'Síntomas moderados constantes de inicio hoy podrían requerir atención presencial.',
  },

  // Priority 5: Known drug allergy + motivo mentions medication
  {
    id: 'ESCL-001',
    description: 'Patient has drug allergies and mentions medication issues',
    priority: 5,
    condition: (d) => {
      const hasAllergies = d.allergies && d.allergies.toLowerCase() !== 'ninguna conocida';
      const mentionsMeds = ['reacción', 'alergia', 'medicamento', 'efecto adverso', 'hinchazón', 'rash']
        .some(k => d.motivo.toLowerCase().includes(k));
      return !!hasAllergies && mentionsMeds;
    },
    result: 'escalado',
    reason: 'Paciente con alergias conocidas reportando posible reacción. Se recomienda revisión por especialista.',
  },

  // Priority 10: Default — teleconsulta
  {
    id: 'TELE-DEFAULT',
    description: 'Default: suitable for teleconsultation',
    priority: 10,
    condition: () => true,
    result: 'teleconsulta',
    reason: 'Los síntomas reportados son manejables por teleconsulta.',
  },
];

// ── Triage Engine ──

export interface TriageEvaluation {
  result: TriageResult;
  matchedRule: TriageRule;
  reason: string;
  allEvaluations: { ruleId: string; matched: boolean }[];
}

export function evaluateTriage(patientData: PatientData): TriageEvaluation {
  const sorted = [...TRIAGE_RULES].sort((a, b) => a.priority - b.priority);
  const allEvaluations: { ruleId: string; matched: boolean }[] = [];
  let matchedRule: TriageRule | null = null;

  for (const rule of sorted) {
    const matched = rule.condition(patientData);
    allEvaluations.push({ ruleId: rule.id, matched });
    if (matched && !matchedRule) {
      matchedRule = rule;
    }
  }

  // Should always match at least TELE-DEFAULT
  const finalRule = matchedRule || sorted[sorted.length - 1]!;

  return {
    result: finalRule.result,
    matchedRule: finalRule,
    reason: finalRule.reason,
    allEvaluations,
  };
}

// ── Result Labels ──

export function getTriageLabel(result: TriageResult): { icon: string; label: string; color: string; bgColor: string } {
  switch (result) {
    case 'emergencia': return { icon: '🚨', label: 'Emergencia — llame al 911', color: '#991B1B', bgColor: '#FEE2E2' };
    case 'presencial': return { icon: '🏥', label: 'Se recomienda atención presencial', color: '#92400E', bgColor: '#FEF3C7' };
    case 'escalado': return { icon: '🙋', label: 'Se recomienda revisión por especialista', color: '#1E40AF', bgColor: '#DBEAFE' };
    case 'teleconsulta': return { icon: '✅', label: 'Apto para teleconsulta', color: '#065F46', bgColor: '#F0FDF4' };
  }
}
