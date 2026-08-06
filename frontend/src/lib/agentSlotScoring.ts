/**
 * ====================================================================
 * Agent Slot Scoring — Ranking inteligente de horarios sin LLM
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Sistema de puntuación para ordenar los slots disponibles por
 * mejor coincidencia con las preferencias del paciente.
 * No usa LLM: scoring puramente algorítmico.
 */

export interface ScoredSlot {
  start: string;
  end: string;
  local_start: string;
  local_end: string;
  available: boolean;
  score: number;
  reasons: string[];
}

export interface SlotScoringContext {
  preferredTimeOfDay?: 'mañana' | 'tarde' | 'noche' | '';
  urgency: 'leve' | 'moderado' | 'severo' | '';
}

// ── Scoring Factors ──

const WEIGHTS = {
  TIME_PREFERENCE: 30,    // Matches preferred time of day
  PROXIMITY: 20,          // Closer to now (for urgent cases)
  MORNING_BONUS: 10,      // Slight bonus for morning slots (most productive)
  AVAILABLE: 100,         // Must be available
} as const;

/**
 * Determines time of day category for a given hour
 */
function getTimeOfDay(hour: number): 'mañana' | 'tarde' | 'noche' {
  if (hour >= 6 && hour < 12) return 'mañana';
  if (hour >= 12 && hour < 18) return 'tarde';
  return 'noche';
}

/**
 * Scores a single slot
 */
function scoreSlot(
  slot: { start: string; end: string; local_start: string; local_end: string; available: boolean },
  context: SlotScoringContext,
  now: Date,
): ScoredSlot {
  let score = 0;
  const reasons: string[] = [];

  // Base: must be available
  if (!slot.available) {
    return { ...slot, score: -1, reasons: ['No disponible'] };
  }
  score += WEIGHTS.AVAILABLE;

  // Parse slot time
  const slotDate = new Date(slot.start);
  const slotHour = slotDate.getHours();
  const slotTimeOfDay = getTimeOfDay(slotHour);

  // 1. Time preference match
  if (context.preferredTimeOfDay && context.preferredTimeOfDay === slotTimeOfDay) {
    score += WEIGHTS.TIME_PREFERENCE;
    reasons.push(`Coincide con tu preferencia de ${context.preferredTimeOfDay}`);
  }

  // 2. Proximity bonus for urgent cases
  if (context.urgency === 'severo' || context.urgency === 'moderado') {
    const hoursUntil = (slotDate.getTime() - now.getTime()) / (1000 * 60 * 60);
    if (hoursUntil > 0 && hoursUntil < 48) {
      const proximityScore = Math.round(WEIGHTS.PROXIMITY * (1 - hoursUntil / 48));
      score += Math.max(0, proximityScore);
      if (proximityScore > 10) reasons.push('Horario próximo (prioritario por urgencia)');
    }
  }

  // 3. Morning slight bonus
  if (slotTimeOfDay === 'mañana') {
    score += WEIGHTS.MORNING_BONUS;
    reasons.push('Horario matutino');
  }

  return { ...slot, score, reasons };
}

/**
 * Scores and sorts all available slots
 * Returns slots sorted by score (highest first), then by time
 */
export function scoreAndSortSlots(
  slots: Array<{ start: string; end: string; local_start: string; local_end: string; available: boolean }>,
  context: SlotScoringContext,
): ScoredSlot[] {
  const now = new Date();
  const scored = slots.map(s => scoreSlot(s, context, now));

  return scored.sort((a, b) => {
    // Available first
    if (a.available !== b.available) return a.available ? -1 : 1;
    // Then by score descending
    if (b.score !== a.score) return b.score - a.score;
    // Then by time ascending
    return new Date(a.start).getTime() - new Date(b.start).getTime();
  });
}

/**
 * Gets the top N recommended slots with reasons
 */
export function getTopRecommendations(
  scoredSlots: ScoredSlot[],
  limit = 3,
): ScoredSlot[] {
  return scoredSlots.filter(s => s.available).slice(0, limit);
}

/**
 * Generates a recommendation summary HTML for the chat
 */
export function buildRecommendationHtml(top: ScoredSlot[]): string {
  if (top.length === 0) return '';

  const items = top.map((slot, i) => {
    const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉';
    const reasonsText = slot.reasons.length > 0
      ? `<span style="font-size: 0.75rem; color: #6B7280;">${slot.reasons.join(' · ')}</span>`
      : '';
    return `<div style="padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
      ${medal} <strong>${slot.local_start}</strong> ${reasonsText}
    </div>`;
  }).join('');

  return `<div style="background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 8px; padding: 10px; margin-top: 6px; font-size: 0.85rem;">
    <strong>💡 Horarios recomendados:</strong>
    ${items}
  </div>`;
}
