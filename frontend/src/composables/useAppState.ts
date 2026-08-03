/**
 * ====================================================================
 * Composable genérico de estado — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Copia la forma exacta del ejemplar canónico useUsers.ts.
 *
 * DELTA CONTRA EL EJEMPLAR:
 * - Se generaliza el tipo: <T> en lugar de User[].
 * - El fetcher se recibe como argumento en lugar de estar hardcodeado.
 * - Todo lo demás (cancelación con AbortSignal, manejo de AbortError,
 *   forma del error, estaVacio) es idéntico.
 *
 * REGLA: si un componente tiene más de dos `ref` o cualquier llamada
 * de red, esa lógica va a un composable. Sin excepción.
 */
import { ref, shallowRef, computed } from 'vue';
import type { EstadoCarga } from '@/types/common.types';

/**
 * Composable genérico que encapsula los 4 estados obligatorios:
 * inicial → cargando → listo | error.
 *
 * @param fetcher — función asíncrona que recibe un AbortSignal y
 *   devuelve los datos. Es el único punto de contacto con la red.
 */
export function useAppState<T>(fetcher: (signal: AbortSignal) => Promise<T[]>) {
  const items = shallowRef<T[]>([]);
  const estado = ref<EstadoCarga>('inicial');
  const error = ref<string | null>(null);

  const estaVacio = computed(
    () => estado.value === 'listo' && items.value.length === 0,
  );

  async function cargar(signal?: AbortSignal): Promise<void> {
    estado.value = 'cargando';
    error.value = null;
    try {
      const internalSignal = signal ?? new AbortController().signal;
      items.value = await fetcher(internalSignal);
      estado.value = 'listo';
    } catch (e) {
      // Cancelar no es un error: es una navegación.
      if (e instanceof DOMException && e.name === 'AbortError') return;
      error.value = e instanceof Error ? e.message : 'Error desconocido';
      estado.value = 'error';
    }
  }

  return { items, estado, error, estaVacio, cargar };
}
