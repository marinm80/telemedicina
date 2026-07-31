/**
 * ====================================================================
 * EJEMPLAR CANÓNICO — Hook React 19
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE EJEMPLAR
 * El equivalente exacto del composable de Vue: la lógica sale del componente.
 * Es el patrón número uno del Manual de Patrones, sección 3.
 *
 * ALTERNATIVA DESCARTADA
 * useEffect + useState suelto dentro del componente. Y para datos de servidor
 * en un proyecto real, TanStack Query hace esto mejor: este ejemplar existe
 * para fijar la FORMA, no para reimplementar una caché.
 *
 * UN SOLO useState CON UN OBJETO, no cuatro sueltos: así el estado no puede
 * quedar en una combinación imposible como "cargando y con error a la vez".
 */
import { useCallback, useEffect, useRef, useState } from 'react';

export interface User {
  id: string;
  email: string;
  name: string;
  role: 'admin' | 'user';
}

type Estado =
  | { fase: 'cargando' }
  | { fase: 'error'; mensaje: string }
  | { fase: 'listo'; items: User[] };

export function useUsers() {
  const [estado, setEstado] = useState<Estado>({ fase: 'cargando' });
  const abortRef = useRef<AbortController | null>(null);

  const cargar = useCallback(async (): Promise<void> => {
    abortRef.current?.abort();
    const controlador = new AbortController();
    abortRef.current = controlador;

    setEstado({ fase: 'cargando' });
    try {
      const res = await fetch('/api/users', {
        signal: controlador.signal,
        credentials: 'include',
      });

      if (!res.ok) {
        const cuerpo = (await res.json().catch(() => null)) as
          | { error?: { message?: string } }
          | null;
        throw new Error(cuerpo?.error?.message ?? `Error ${res.status}`);
      }

      const json = (await res.json()) as { items: User[] };
      setEstado({ fase: 'listo', items: json.items });
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      setEstado({ fase: 'error', mensaje: e instanceof Error ? e.message : 'Error desconocido' });
    }
  }, []);

  useEffect(() => {
    void cargar();
    return () => abortRef.current?.abort();
  }, [cargar]);

  return { estado, recargar: cargar };
}
