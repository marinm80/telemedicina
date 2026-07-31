/**
 * ====================================================================
 * EJEMPLAR CANÓNICO — Componente React 19
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE EJEMPLAR
 * 1. Los CUATRO ESTADOS OBLIGATORIOS: cargando, error, vacío, listo.
 *    Acá el estado es una unión discriminada, así que TypeScript OBLIGA a
 *    cubrirlos todos: olvidarse de uno no compila.
 * 2. Cero lógica: todo en el hook.
 * 3. Props tipadas con interface, valores por defecto en los parámetros.
 *
 * ALTERNATIVA DESCARTADA
 * Cuatro booleanos (isLoading, isError, isEmpty...). Permiten estados
 * imposibles y obligan a un árbol de condicionales que nadie mantiene.
 */
import type { JSX } from 'react';
import { useUsers } from './useUsers';

interface UserListProps {
  titulo?: string;
  puedeCrear?: boolean;
  onSeleccionar?: (id: string) => void;
}

export default function UserList({
  titulo = 'Usuarios',
  puedeCrear = false,
  onSeleccionar,
}: UserListProps): JSX.Element {
  const { estado, recargar } = useUsers();

  return (
    <section className="rounded-lg border border-slate-200 bg-white p-4">
      <header className="mb-3 flex items-center justify-between">
        <h2 className="text-lg font-semibold text-slate-900">{titulo}</h2>
        {puedeCrear && (
          <button type="button" className="rounded bg-teal-700 px-3 py-1 text-sm text-white">
            Nuevo
          </button>
        )}
      </header>

      {/* 1. CARGANDO */}
      {estado.fase === 'cargando' && (
        <div className="space-y-2" aria-busy="true">
          {[0, 1, 2].map((n) => (
            <div key={n} className="h-10 animate-pulse rounded bg-slate-100" />
          ))}
        </div>
      )}

      {/* 2. ERROR — con reintento */}
      {estado.fase === 'error' && (
        <div role="alert" className="rounded border border-red-200 bg-red-50 p-3">
          <p className="text-sm text-red-800">{estado.mensaje}</p>
          <button
            type="button"
            className="mt-2 text-sm font-medium text-red-900 underline"
            onClick={() => void recargar()}
          >
            Reintentar
          </button>
        </div>
      )}

      {/* 3. VACÍO */}
      {estado.fase === 'listo' && estado.items.length === 0 && (
        <p className="py-6 text-center text-sm text-slate-500">Todavía no hay usuarios.</p>
      )}

      {/* 4. LISTO */}
      {estado.fase === 'listo' && estado.items.length > 0 && (
        <ul className="divide-y divide-slate-100">
          {estado.items.map((user) => (
            <li
              key={user.id}
              className="flex cursor-pointer items-center justify-between py-2 hover:bg-slate-50"
              onClick={() => onSeleccionar?.(user.id)}
            >
              <span className="text-sm font-medium text-slate-900">{user.name}</span>
              <span className="text-xs text-slate-500">{user.email}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
