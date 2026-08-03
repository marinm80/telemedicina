/**
 * ====================================================================
 * Pruebas de useAppState — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Mínimo exigido por el humano (4 pruebas):
 * 1. Pasa por los 4 estados: inicial → cargando → listo.
 * 2. Maneja error: inicial → cargando → error.
 * 3. Cancela con AbortError sin cambiar a estado error.
 * 4. Expone estaVacio correctamente (listo + items vacíos).
 */
import { describe, it, expect, vi } from 'vitest';
import { useAppState } from './useAppState';

interface ItemDePrueba {
  id: string;
  nombre: string;
}

describe('useAppState', () => {
  it('pasa por los 4 estados: inicial → cargando → listo', async () => {
    const datos: ItemDePrueba[] = [
      { id: '1', nombre: 'Primero' },
      { id: '2', nombre: 'Segundo' },
    ];
    const fetcher = vi.fn<(signal: AbortSignal) => Promise<ItemDePrueba[]>>();
    fetcher.mockResolvedValue(datos);

    const { estado, items, cargar } = useAppState<ItemDePrueba>(fetcher);

    // Estado inicial
    expect(estado.value).toBe('inicial');
    expect(items.value).toEqual([]);

    // Al llamar cargar(), pasa a cargando de forma síncrona
    const promesa = cargar();
    expect(estado.value).toBe('cargando');

    // Al resolver, pasa a listo con los datos
    await promesa;
    expect(estado.value).toBe('listo');
    expect(items.value).toEqual(datos);
    expect(fetcher).toHaveBeenCalledOnce();
  });

  it('maneja error: inicial → cargando → error', async () => {
    const fetcher = vi.fn<(signal: AbortSignal) => Promise<ItemDePrueba[]>>();
    fetcher.mockRejectedValue(new Error('Fallo de red'));

    const { estado, error, cargar } = useAppState<ItemDePrueba>(fetcher);

    expect(estado.value).toBe('inicial');

    const promesa = cargar();
    expect(estado.value).toBe('cargando');

    await promesa;
    expect(estado.value).toBe('error');
    expect(error.value).toBe('Fallo de red');
  });

  it('cancela con AbortError sin cambiar a estado error', async () => {
    const abortError = new DOMException('The operation was aborted', 'AbortError');
    const fetcher = vi.fn<(signal: AbortSignal) => Promise<ItemDePrueba[]>>();
    fetcher.mockRejectedValue(abortError);

    const { estado, error, cargar } = useAppState<ItemDePrueba>(fetcher);

    await cargar();

    // AbortError no es un error de la aplicación: es una navegación.
    // El estado se queda en cargando (el componente ya se desmontó).
    expect(estado.value).toBe('cargando');
    expect(error.value).toBeNull();
  });

  it('expone estaVacio correctamente (listo + items vacíos)', async () => {
    const fetcher = vi.fn<(signal: AbortSignal) => Promise<ItemDePrueba[]>>();
    fetcher.mockResolvedValue([]);

    const { estaVacio, estado, items, cargar } = useAppState<ItemDePrueba>(fetcher);

    // Antes de cargar: inicial, no vacío (vacío requiere estado listo)
    expect(estaVacio.value).toBe(false);
    expect(estado.value).toBe('inicial');

    await cargar();

    // Después de cargar con array vacío: listo + sin items = vacío
    expect(estado.value).toBe('listo');
    expect(items.value).toEqual([]);
    expect(estaVacio.value).toBe(true);
  });
});
