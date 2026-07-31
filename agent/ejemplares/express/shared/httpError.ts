/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       shared / lib
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * Un servicio necesita señalar "este usuario no existe" o "este email ya está
 * tomado" SIN saber que existe HTTP. Estas clases son el vocabulario intermedio:
 * el servicio lanza `new NotFoundError('Usuario')`, y el manejador de errores
 * (única capa que conoce HTTP) lo traduce a un 404.
 *
 * ALTERNATIVA DESCARTADA
 * Que el servicio devuelva `res.status(404)`. Eso acopla la lógica de negocio al
 * transporte y vuelve imposible probar el servicio sin levantar un servidor.
 *
 * `isOperational` distingue el error esperado (el usuario pidió algo que no está)
 * del bug inesperado. El primero se registra como warn; el segundo como error,
 * con traza, porque hay que ir a arreglarlo.
 */
export abstract class AppError extends Error {
  abstract readonly statusCode: number;
  readonly isOperational = true;

  constructor(message: string, readonly details?: unknown) {
    super(message);
    this.name = new.target.name;
    Error.captureStackTrace?.(this, new.target);
  }
}

export class BadRequestError extends AppError {
  readonly statusCode = 400;
}

export class UnauthorizedError extends AppError {
  readonly statusCode = 401;
  constructor(message = 'No autenticado') { super(message); }
}

export class ForbiddenError extends AppError {
  readonly statusCode = 403;
  constructor(message = 'No autorizado') { super(message); }
}

export class NotFoundError extends AppError {
  readonly statusCode = 404;
  constructor(recurso = 'Recurso') { super(`${recurso} no encontrado`); }
}

/** 409: el estado actual impide la operación. Ej.: email ya registrado. */
export class ConflictError extends AppError {
  readonly statusCode = 409;
}

/** 422: la forma es válida pero el contenido viola una regla de negocio. */
export class UnprocessableError extends AppError {
  readonly statusCode = 422;
}
