/**
 * ====================================================================
 * Validación del Login — Plataforma de Telemedicina
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Reglas que ESPEJAN las del servidor (LoginRequest):
 *   email    → required | string | email
 *   password → required | string
 *
 * Extraído del SFC para ser testeable sin montar el componente.
 * La validación del cliente NO reemplaza al servidor. Las dos existen.
 */

export interface LoginValidationErrors {
  email?: string;
  password?: string;
}

/**
 * Valida los campos del formulario de login en el cliente.
 * Espeja las reglas del LoginRequest del servidor.
 */
export function validateLoginClient(email: string, password: string): LoginValidationErrors {
  const errs: LoginValidationErrors = {};

  if (!email.trim()) {
    errs.email = 'El correo electrónico es obligatorio.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
    errs.email = 'Ingresa un correo electrónico válido.';
  }

  if (!password) {
    errs.password = 'La contraseña es obligatoria.';
  }

  return errs;
}

/**
 * Mensaje de error de credenciales genérico.
 * NUNCA revela si el correo existe. El mismo texto para:
 * - correo inexistente
 * - contraseña incorrecta
 * - cuenta bloqueada
 *
 * Exportado como constante para que el test pueda verificar
 * que el mismo string se usa en todos los caminos.
 */
export const CREDENTIAL_ERROR =
  'Las credenciales ingresadas no son válidas. Verifica tu correo y contraseña.';
