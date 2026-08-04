/**
 * ====================================================================
 * Pruebas de componentes de estado — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Cada componente verifica:
 * - Que renderiza su contenido correctamente.
 * - Que tiene los atributos de accesibilidad requeridos.
 *
 * SpinnerLoader: aria-busy="true", role="status"
 * ErrorFallback: role="alert"
 * EmptyState:    renderiza mensaje y acción opcional
 */
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SpinnerLoader from './SpinnerLoader.vue';
import ErrorFallback from './ErrorFallback.vue';
import EmptyState from './EmptyState.vue';

// ============================================================================
// SpinnerLoader
// ============================================================================
describe('SpinnerLoader', () => {
  it('renderiza con aria-busy="true"', () => {
    const wrapper = mount(SpinnerLoader);
    expect(wrapper.attributes('aria-busy')).toBe('true');
  });

  it('tiene role="status"', () => {
    const wrapper = mount(SpinnerLoader);
    expect(wrapper.attributes('role')).toBe('status');
  });

  it('contiene texto sr-only "Cargando…"', () => {
    const wrapper = mount(SpinnerLoader);
    const srOnly = wrapper.find('.spinner-loader__sr-only');
    expect(srOnly.exists()).toBe(true);
    expect(srOnly.text()).toBe('Cargando…');
  });

  it('renderiza el número de líneas pedido', () => {
    const wrapper = mount(SpinnerLoader, { props: { lines: 5 } });
    const lines = wrapper.findAll('.spinner-loader__line');
    expect(lines).toHaveLength(5);
  });

  it('usa 3 líneas por defecto', () => {
    const wrapper = mount(SpinnerLoader);
    const lines = wrapper.findAll('.spinner-loader__line');
    expect(lines).toHaveLength(3);
  });

  it('aplica la clase de variante card', () => {
    const wrapper = mount(SpinnerLoader, { props: { variant: 'card' } });
    expect(wrapper.classes()).toContain('spinner-loader--card');
  });

  it('aplica la clase de variante list por defecto', () => {
    const wrapper = mount(SpinnerLoader);
    expect(wrapper.classes()).toContain('spinner-loader--list');
  });
});

// ============================================================================
// ErrorFallback
// ============================================================================
describe('ErrorFallback', () => {
  it('tiene role="alert"', () => {
    const wrapper = mount(ErrorFallback, {
      props: { message: 'Error de prueba' },
    });
    expect(wrapper.attributes('role')).toBe('alert');
  });

  it('renderiza el mensaje de error', () => {
    const wrapper = mount(ErrorFallback, {
      props: { message: 'Fallo de red' },
    });
    expect(wrapper.text()).toContain('Fallo de red');
  });

  it('muestra botón de reintento cuando se provee onRetry', () => {
    const onRetry = vi.fn();
    const wrapper = mount(ErrorFallback, {
      props: { message: 'Error', onRetry },
    });
    const btn = wrapper.find('button');
    expect(btn.exists()).toBe(true);
    expect(btn.text()).toContain('Reintentar');
  });

  it('llama onRetry al hacer clic en el botón', async () => {
    const onRetry = vi.fn();
    const wrapper = mount(ErrorFallback, {
      props: { message: 'Error', onRetry },
    });
    await wrapper.find('button').trigger('click');
    expect(onRetry).toHaveBeenCalledOnce();
  });

  it('no muestra botón de reintento sin onRetry', () => {
    const wrapper = mount(ErrorFallback, {
      props: { message: 'Error' },
    });
    expect(wrapper.find('button').exists()).toBe(false);
  });
});

// ============================================================================
// EmptyState
// ============================================================================
describe('EmptyState', () => {
  it('renderiza el mensaje', () => {
    const wrapper = mount(EmptyState, {
      props: { message: 'No hay datos.' },
    });
    expect(wrapper.text()).toContain('No hay datos.');
  });

  it('muestra botón de acción cuando se proveen actionLabel y onAction', () => {
    const onAction = vi.fn();
    const wrapper = mount(EmptyState, {
      props: { message: 'Vacío', actionLabel: 'Crear', onAction },
    });
    const btn = wrapper.find('button');
    expect(btn.exists()).toBe(true);
    expect(btn.text()).toBe('Crear');
  });

  it('llama onAction al hacer clic en el botón', async () => {
    const onAction = vi.fn();
    const wrapper = mount(EmptyState, {
      props: { message: 'Vacío', actionLabel: 'Crear', onAction },
    });
    await wrapper.find('button').trigger('click');
    expect(onAction).toHaveBeenCalledOnce();
  });

  it('no muestra botón sin actionLabel', () => {
    const wrapper = mount(EmptyState, {
      props: { message: 'Vacío' },
    });
    expect(wrapper.find('button').exists()).toBe(false);
  });

  it('no muestra botón con actionLabel pero sin onAction', () => {
    const wrapper = mount(EmptyState, {
      props: { message: 'Vacío', actionLabel: 'Crear' },
    });
    expect(wrapper.find('button').exists()).toBe(false);
  });

  it('tiene icono decorativo oculto de lectores de pantalla', () => {
    const wrapper = mount(EmptyState, {
      props: { message: 'Vacío' },
    });
    const icon = wrapper.find('i.pi-inbox');
    expect(icon.exists()).toBe(true);
    expect(icon.attributes('aria-hidden')).toBe('true');
  });
});
