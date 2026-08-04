import { describe, it, expect } from 'vitest';
import { validateRegisterClient } from './registerValidation';

describe('validateRegisterClient', () => {
  it('detecta campos vacíos', () => {
    const errs = validateRegisterClient('', '', '', '', '');
    expect(errs.name).toBe('El nombre es obligatorio.');
    expect(errs.last_name).toBe('El apellido es obligatorio.');
    expect(errs.email).toBe('El correo electrónico es obligatorio.');
    expect(errs.password).toBe('La contraseña es obligatoria.');
    expect(errs.password_confirmation).toBe('Debe confirmar su contraseña.');
  });

  it('detecta contraseñas cortas o que no coinciden', () => {
    const errs = validateRegisterClient('Juan', 'Pérez', 'juan@example.com', '12345', '123456');
    expect(errs.password).toBe('La contraseña debe tener al menos 8 caracteres.');
    expect(errs.password_confirmation).toBe('Las contraseñas no coinciden.');
  });

  it('retorna sin errores para datos válidos', () => {
    const errs = validateRegisterClient('Juan', 'Pérez', 'juan@example.com', 'password123', 'password123');
    expect(Object.keys(errs).length).toBe(0);
  });
});
