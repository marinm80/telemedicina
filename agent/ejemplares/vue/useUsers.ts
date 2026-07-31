/**
 * ====================================================================
 * EJEMPLAR CANÓNICO — Composable Vue 3
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE EJEMPLAR
 * Toda la lógica del componente vive acá: fetching, estados, errores.
 * El componente queda como presentación pura.
 *
 * REGLA: si un componente tiene más de dos `ref` o cualquier llamada de red,
 * esa lógica va a un composable. Sin excepción.
 *
 * ALTERNATIVA DESCARTADA
 * Poner el fetch dentro del componente con onMounted. Funciona hasta que dos
 * componentes necesitan lo mismo y aparece el copiar-pegar.
 */
import { ref, shallowRef, computed } from 'vue';

export interface User {
  id: string;
  email: string;
  name: string;
  role: 'admin' | 'user';
}

/** Los cuatro estados son OBLIGATORIOS. No existe "solo el caso feliz". */
export type EstadoCarga = 'inicial' | 'cargando' | 'listo' | 'error';

export function useUsers() {
  const items = shallowRef<User[]>([]);
  const estado = ref<EstadoCarga>('inicial');
  const error = ref<string | null>(null);

  const estaVacio = computed(() => estado.value === 'listo' && items.value.length === 0);

  async function cargar(signal?: AbortSignal): Promise<void> {
    estado.value = 'cargando';
    error.value = null;
    try {
      const res = await fetch('/api/users', { signal, credentials: 'include' });

      // El contrato del Gate 2C define la forma del error: se respeta acá.
      if (!res.ok) {
        const cuerpo = (await res.json().catch(() => null)) as
          | { error?: { message?: string } }
          | null;
        throw new Error(cuerpo?.error?.message ?? `Error ${res.status}`);
      }

      const json = (await res.json()) as { items: User[] };
      items.value = json.items;
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
