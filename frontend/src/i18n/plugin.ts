/**
 * ====================================================================
 * Plugin de i18n ligero — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Función de lookup con interpolación básica. Se inyecta globalmente
 * vía app.provide para que cualquier componente pueda usar t().
 *
 * ALTERNATIVA DESCARTADA
 * vue-i18n (50kB+). Agrega complejidad (rutas por idioma, lazy loading,
 * pluralización ICU) que no necesitamos. El PRD dice "español únicamente,
 * con estructura de claves preparada" (DECISIONES_ALCANCE §4). Si algún
 * día se necesita bilingüismo, la estructura de claves permite migrar.
 */
import type { App, InjectionKey } from 'vue';
import es from './es';

type TranslateFunction = (key: string, params?: Record<string, string>) => string;

export const i18nKey: InjectionKey<TranslateFunction> = Symbol('i18n');

/**
 * Función de traducción. Busca la clave en el diccionario y reemplaza
 * placeholders con {nombre}. Si la clave no existe, devuelve la clave
 * misma para hacer visible el faltante.
 */
function translate(key: string, params?: Record<string, string>): string {
  let text = es[key];
  if (text === undefined) return key;

  if (params) {
    for (const [name, value] of Object.entries(params)) {
      text = text.replace(new RegExp(`\\{${name}\\}`, 'g'), value);
    }
  }
  return text;
}

/**
 * Plugin Vue que registra la función t() globalmente.
 * Uso en componentes: const t = inject(i18nKey)!
 */
export const i18nPlugin = {
  install(app: App): void {
    app.provide(i18nKey, translate);
  },
};
