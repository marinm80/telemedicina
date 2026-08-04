#!/usr/bin/env node
/**
 * ====================================================================
 * Verificación automática de contraste WCAG AA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Lee tokens.css, calcula el ratio de cada par declarado, y falla si
 * alguno baja del umbral. Esto es una BARRERA, no vigilancia manual.
 *
 * Uso:
 *   node scripts/check-contrast.mjs
 *
 * Umbrales WCAG 2.1:
 *   - text:      ratio ≥ 4.5:1 (1.4.3 nivel AA, texto normal)
 *   - large-text: ratio ≥ 3:1  (1.4.3 nivel AA, texto ≥ 18pt)
 *   - non-text:  ratio ≥ 3:1  (1.4.11, bordes e iconos de componentes)
 */

import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const TOKENS_PATH = resolve(__dirname, '../src/assets/styles/tokens.css');

// ---------------------------------------------------------------------------
// Lógica de contraste WCAG 2.1
// ---------------------------------------------------------------------------

/** Convierte hex (#RRGGBB) a componentes RGB [0–255]. */
function hexToRgb(hex) {
  const h = hex.replace('#', '');
  return [
    parseInt(h.substring(0, 2), 16),
    parseInt(h.substring(2, 4), 16),
    parseInt(h.substring(4, 6), 16),
  ];
}

/** Luminancia relativa según WCAG 2.1 §1.4.1. */
function relativeLuminance([r, g, b]) {
  const [rs, gs, bs] = [r, g, b].map((c) => {
    const s = c / 255;
    return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

/** Ratio de contraste entre dos colores hex. */
function contrastRatio(hex1, hex2) {
  const l1 = relativeLuminance(hexToRgb(hex1));
  const l2 = relativeLuminance(hexToRgb(hex2));
  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);
  return (lighter + 0.05) / (darker + 0.05);
}

// ---------------------------------------------------------------------------
// Parsing de tokens.css
// ---------------------------------------------------------------------------

/** Extrae todos los pares --nombre: #RRGGBB del archivo CSS. */
function parseTokens(css) {
  const tokens = {};
  const regex = /--([a-z0-9-]+):\s*(#[0-9A-Fa-f]{6})/g;
  let match;
  while ((match = regex.exec(css)) !== null) {
    tokens[match[1]] = match[2].toUpperCase();
  }
  return tokens;
}

// ---------------------------------------------------------------------------
// Lista declarada de pares — la superficie de auditoría de contraste
// ---------------------------------------------------------------------------
// Cada par tiene:
//   fg:    nombre del token de primer plano (o hex directo para pruebas)
//   bg:    nombre del token de fondo
//   type:  'text' (4.5:1) | 'large-text' (3:1) | 'non-text' (3:1)
//   label: descripción legible del par

const PAIRS = [
  // --- Texto sobre fondos ---
  { fg: 'color-text-strong', bg: 'color-surface-0', type: 'text',     label: 'Texto cuerpo / blanco' },
  { fg: 'color-text-muted',  bg: 'color-surface-0', type: 'text',     label: 'Texto secundario / blanco' },
  { fg: 'color-text-subtle', bg: 'color-surface-0', type: 'text',     label: 'Texto tenue / blanco' },

  // --- Botón principal ---
  { fg: 'color-surface-0',   bg: 'color-primary-700', type: 'text',   label: 'Blanco / botón primario' },

  // --- Paneles de estado ---
  { fg: 'color-surface-0',     bg: 'color-error-600',   type: 'text', label: 'Blanco / error-600' },
  { fg: 'color-error-700',     bg: 'color-error-50',    type: 'text', label: 'Error-700 / panel error' },
  { fg: 'color-surface-0',     bg: 'color-success-700', type: 'text', label: 'Blanco / success-700' },
  { fg: 'color-success-800',   bg: 'color-success-50',  type: 'text', label: 'Success-800 / panel success' },
  { fg: 'color-warning-800',   bg: 'color-warning-50',  type: 'text', label: 'Warning-800 / panel warning' },
  { fg: 'color-info-text',     bg: 'color-info-bg',     type: 'text', label: 'Info-text / info-bg' },

  // --- No-texto: bordes y foco (WCAG 1.4.11, umbral 3:1) ---
  { fg: 'color-border',     bg: 'color-surface-0', type: 'non-text',  label: 'Borde control / blanco' },
  { fg: 'color-focus-ring', bg: 'color-surface-0', type: 'non-text',  label: 'Anillo foco / blanco' },

  // --- Cobertura de tokens faltantes (clasificados 2026-08-03) ---

  // primary-600: color de enlace en hover (base.css a:hover)
  { fg: 'color-primary-600', bg: 'color-surface-0',   type: 'text',     label: 'Enlace hover / blanco' },
  // primary-500: color de icono decorativo de acento sobre blanco
  { fg: 'color-primary-500', bg: 'color-surface-0',   type: 'non-text', label: 'Icono acento / blanco' },
  // primary-900: fondo de botón presionado con texto blanco (preset: activeColor)
  { fg: 'color-surface-0',   bg: 'color-primary-900', type: 'text',     label: 'Blanco / botón presionado' },
  // primary-50: fondo de highlight/selección con texto de marca
  { fg: 'color-primary-700', bg: 'color-primary-50',  type: 'text',     label: 'Texto marca / highlight' },
  // primary-100: fondo de hover highlight con texto de marca
  { fg: 'color-primary-700', bg: 'color-primary-100', type: 'text',     label: 'Texto marca / hover highlight' },
  // error-100: fondo error alterno con texto error
  { fg: 'color-error-700',   bg: 'color-error-100',   type: 'text',     label: 'Error-700 / fondo error claro' },
  // warning-800: borde e icono del panel de advertencia sobre warning-50.
  // warning-600 NO sirve como borde (2.84 < 3:1): se usa warning-800.
  { fg: 'color-warning-800', bg: 'color-warning-50',  type: 'non-text', label: 'Warning-800 borde / panel warning' },
  // surface-50: fondo de página con texto cuerpo
  { fg: 'color-text-strong', bg: 'color-surface-50',  type: 'text',     label: 'Texto cuerpo / fondo página' },
  // surface-100: fondo de tarjeta/panel con texto cuerpo
  { fg: 'color-text-strong', bg: 'color-surface-100', type: 'text',     label: 'Texto cuerpo / fondo tarjeta' },

  // --- Tokens que DEBEN estar en un par (no exentos) ---

  // warning-600: fondo sólido de badge de advertencia. El texto sobre este
  // fondo es text-strong (#0F172A), NO blanco (blanco/warning-600 = 2.94, FALLA).
  { fg: 'color-text-strong', bg: 'color-warning-600', type: 'text',       label: 'Texto fuerte / badge warning' },
  // surface-200: separador visual decorativo entre secciones.
  // WCAG 1.4.11 aplica a "componentes de UI y objetos gráficos", no a
  // divisores decorativos que no transmiten información. Umbral 1:1.
  { fg: 'color-surface-200', bg: 'color-surface-0',   type: 'decorative', label: 'Separador decorativo / blanco' },

  // --- Familia Clinical ---
  { fg: 'color-clinical-accent', bg: 'color-surface-0', type: 'text', label: 'Clinical accent / blanco' },
  { fg: 'color-clinical-danger', bg: 'color-surface-0', type: 'text', label: 'Clinical danger / blanco' },
  { fg: 'color-clinical-warning', bg: 'color-surface-0', type: 'text', label: 'Clinical warning / blanco' },
  { fg: 'color-surface-0', bg: 'color-clinical-warning', type: 'text', label: 'Blanco / clinical warning' },
  { fg: 'color-clinical-warning', bg: 'color-clinical-warning-bg', type: 'text', label: 'Clinical warning / background' },
  { fg: 'color-clinical-danger', bg: 'color-clinical-danger-bg', type: 'text', label: 'Clinical danger / background' },
];

// ---------------------------------------------------------------------------
// Tokens exentos de verificación de contraste — ELIMINADO
// ---------------------------------------------------------------------------
// DECISIÓN: no hay exenciones. Todo token --color-* debe estar en algún par.
// Si se declara un token sin uso de contraste (p.ej. divisor decorativo),
// la forma de documentarlo es un par explícito con su contexto, no una
// lista de bypass.

// ---------------------------------------------------------------------------
// Ejecución
// ---------------------------------------------------------------------------

const THRESHOLDS = { 'text': 4.5, 'large-text': 3, 'non-text': 3, 'decorative': 1 };

const css = readFileSync(TOKENS_PATH, 'utf-8');
const tokens = parseTokens(css);

let failures = 0;

// --- Aserción 1: ratios de contraste ---

console.log('\n  Verificación de contraste WCAG AA');
console.log('  Fuente: src/assets/styles/tokens.css\n');
console.log('  ' + 'Par'.padEnd(46) + 'Ratio'.padStart(7) + '  Umbral  Estado');
console.log('  ' + '\u2500'.repeat(70));

for (const pair of PAIRS) {
  const fgHex = pair.fg.startsWith('#') ? pair.fg : tokens[pair.fg];
  const bgHex = pair.bg.startsWith('#') ? pair.bg : tokens[pair.bg];

  if (!fgHex || !bgHex) {
    console.log(`  \u26A0  Token no encontrado: ${!fgHex ? pair.fg : pair.bg}`);
    failures++;
    continue;
  }

  const ratio = contrastRatio(fgHex, bgHex);
  const threshold = THRESHOLDS[pair.type];
  const passed = ratio >= threshold;

  if (!passed) failures++;

  const icon = passed ? '\u2705' : '\u274C';
  const status = passed ? 'PASA' : 'FALLA';

  console.log(
    `  ${icon} ${pair.label.padEnd(44)}${ratio.toFixed(2).padStart(7)}  ${String(threshold + ':1').padEnd(6)}  ${status}`
  );
}

console.log('  ' + '\u2500'.repeat(70));

// --- Aserción 2: cobertura de tokens ---
// Todo token --color-* debe aparecer en algún par de PAIRS.
// Sin exenciones: si no está en un par, es un token sin auditar y falla.

const colorTokens = Object.keys(tokens).filter((name) => name.startsWith('color-'));

const coveredTokens = new Set();
for (const pair of PAIRS) {
  if (!pair.fg.startsWith('#')) coveredTokens.add(pair.fg);
  if (!pair.bg.startsWith('#')) coveredTokens.add(pair.bg);
}

const uncovered = colorTokens.filter(
  (name) => !coveredTokens.has(name),
);

console.log('\n  Cobertura de tokens --color-*');
console.log('  ' + '\u2500'.repeat(70));
console.log(`  Total: ${colorTokens.length}  |  En pares: ${coveredTokens.size}  |  Sin auditar: ${uncovered.length}`);

if (uncovered.length > 0) {
  console.log('\n  \u274C Tokens --color-* sin par de contraste:\n');
  for (const name of uncovered) {
    console.log(`     --${name}: ${tokens[name]}`);
  }
  failures += uncovered.length;
}

console.log('  ' + '\u2500'.repeat(70));

// --- Resultado final ---

if (failures > 0) {
  console.log(`\n  \u274C ${failures} falla(s) en total.\n`);
  process.exit(1);
} else {
  console.log(`\n  \u2705 Todos los pares pasan y todos los tokens están cubiertos.\n`);
  process.exit(0);
}
