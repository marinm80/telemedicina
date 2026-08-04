/**
 * ====================================================================
 * registerValidation.ts — Validación de Registro de Pacientes
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

export interface RegisterValidationErrors {
  name?: string;
  last_name?: string;
  email?: string;
  password?: string;
  password_confirmation?: string;
}

export function validateRegisterClient(
  name: string,
  lastName: string,
  email: string,
  password: string,
  passwordConfirmation: string
): RegisterValidationErrors {
  const errors: RegisterValidationErrors = {};

  if (!name || !name.trim()) {
    errors.name = 'El nombre es obligatorio.';
  } else if (name.length > 150) {
    errors.name = 'El nombre no puede exceder los 150 caracteres.';
  }

  if (!lastName || !lastName.trim()) {
    errors.last_name = 'El apellido es obligatorio.';
  } else if (lastName.length > 150) {
    errors.last_name = 'El apellido no puede exceder los 150 caracteres.';
  }

  if (!email || !email.trim()) {
    errors.email = 'El correo electrónico es obligatorio.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
    errors.email = 'Debe ingresar un correo electrónico válido.';
  }

  if (!password) {
    errors.password = 'La contraseña es obligatoria.';
  } else if (password.length < 8) {
    errors.password = 'La contraseña debe tener al menos 8 caracteres.';
  }

  if (!passwordConfirmation) {
    errors.password_confirmation = 'Debe confirmar su contraseña.';
  } else if (password !== passwordConfirmation) {
    errors.password_confirmation = 'Las contraseñas no coinciden.';
  }

  return errors;
}
