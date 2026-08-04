/**
 * ====================================================================
 * Barrera de referencias a RF — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Parsea la tabla de RF de docs/PRD.md (fuente de verdad, solo lectura)
 * y verifica que toda referencia RF-NN en frontend/PLAN.md:
 *   1. Use un número que exista en el PRD.
 *   2. Lleve el título EXACTO del PRD al lado.
 *   3. No aparezca como número suelto sin título.
 *
 * Falla con exit 1 si encuentra errores.
 * Se suma a "verify" en package.json, al lado de check-contrast.
 *
 * REGLA: el PRD está en docs/, fuera del territorio del frontend.
 * Se LEE, nunca se escribe. Si el script encuentra un error, el arreglo
 * va en PLAN.md, jamás en el PRD.
 */

import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
const PRD_PATH = resolve(ROOT, '..', 'docs', 'PRD.md');
const PLAN_PATH = resolve(ROOT, 'PLAN.md');

// ── 1. Parsear la tabla de RF del PRD ──────────────────────────────────────

const prdContent = readFileSync(PRD_PATH, 'utf-8');

/**
 * Extrae el mapa RF-NN → título de la tabla del PRD.
 * Busca filas con patrón: | RF-NN | Título | ...
 */
function parseRFTable(content) {
  const rfMap = new Map();
  const lines = content.split('\n');

  for (const line of lines) {
    // Match table rows: | RF-NN | Title | ...
    const match = line.match(/^\|\s*(RF-\d+)\s*\|\s*([^|]+?)\s*\|/);
    if (match) {
      const id = match[1].trim();
      const title = match[2].trim();
      rfMap.set(id, title);
    }
  }

  return rfMap;
}

const rfMap = parseRFTable(prdContent);

if (rfMap.size === 0) {
  console.error('\n  ❌ No se encontró ningún RF en docs/PRD.md\n');
  process.exit(1);
}

// ── 2. Buscar referencias RF-NN en PLAN.md ─────────────────────────────────

const planContent = readFileSync(PLAN_PATH, 'utf-8');
const planLines = planContent.split('\n');

const errors = [];

for (let i = 0; i < planLines.length; i++) {
  const line = planLines[i];
  const lineNum = i + 1;

  // Find all RF-NN references in this line
  // Pattern: RF-NN possibly followed by a title
  const rfRefs = [...line.matchAll(/RF-(\d+)/g)];

  for (const ref of rfRefs) {
    const fullId = `RF-${ref[1]}`;
    const charIndex = ref.index;

    // Check 1: Does this RF number exist in the PRD?
    if (!rfMap.has(fullId)) {
      errors.push({
        line: lineNum,
        id: fullId,
        type: 'NOT_IN_PRD',
        message: `${fullId} no existe en la tabla de RF del PRD.`,
      });
      continue;
    }

    const prdTitle = rfMap.get(fullId);

    // Check 2: Is there a title after the RF number?
    // Look at what follows RF-NN in the line
    const afterRef = line.slice(charIndex + fullId.length);

    // The title should follow immediately after RF-NN with a space
    // Accept patterns like:
    //   RF-09 Reserva de Citas sin Solapamiento
    //   RF-09 Reserva de Citas sin Solapamiento (something)
    //   RF-09 Reserva de Citas sin Solapamiento,
    // But NOT:
    //   RF-09 escritura cierre   (not the title)
    //   RF-09,                   (no title)
    //   RF-09 |                  (in a table, check what's between pipes)

    // Extract the text that follows the RF-NN up to the next delimiter
    // Delimiters: comma, pipe, period, end of line, another RF-
    const titleMatch = afterRef.match(/^\s+([^,|.\n]+)/);

    if (!titleMatch) {
      errors.push({
        line: lineNum,
        id: fullId,
        type: 'NO_TITLE',
        message: `${fullId} aparece sin título al lado. Debe ser: "${fullId} ${prdTitle}"`,
      });
      continue;
    }

    const citedText = titleMatch[1].trim();

    // Check 3: Does the cited title match the PRD title exactly?
    // The cited text should START with the PRD title
    if (!citedText.startsWith(prdTitle)) {
      errors.push({
        line: lineNum,
        id: fullId,
        type: 'TITLE_MISMATCH',
        message: `${fullId} título incorrecto.\n       Citado:  "${citedText}"\n       PRD:     "${prdTitle}"`,
      });
    }
  }
}

// ── 3. Reporte ─────────────────────────────────────────────────────────────

console.log('');
console.log('  Verificación de referencias RF');
console.log(`  Fuente de verdad: docs/PRD.md (${rfMap.size} RF definidos)`);
console.log('  Archivo auditado: frontend/PLAN.md');
console.log('');

if (errors.length === 0) {
  console.log('  ✅ Todas las referencias RF son correctas.\n');
  process.exit(0);
} else {
  console.log(`  ❌ ${errors.length} error(es) encontrado(s):\n`);
  for (const err of errors) {
    const icon =
      err.type === 'NOT_IN_PRD' ? '🚫' :
      err.type === 'NO_TITLE' ? '⚠️' :
      '❌';
    console.log(`  ${icon} Línea ${err.line}: ${err.message}`);
    console.log('');
  }
  process.exit(1);
}
