/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       shared / middleware
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * Ningún dato entra al servicio sin pasar por un esquema. Esta fábrica de
 * middleware valida body, params y query, y REEMPLAZA el valor por el
 * resultado parseado — que ya está tipado y con los defaults aplicados.
 *
 * ALTERNATIVA DESCARTADA
 * Validar dentro del controlador con `if`. Ensucia el controlador, se olvida en
 * algún endpoint, y no da tipos.
 */
import type { Request, Response, NextFunction, RequestHandler } from 'express';
import type { ZodType } from 'zod';

interface Schemas {
  body?: ZodType;
  params?: ZodType;
  query?: ZodType;
}

export function validate(schemas: Schemas): RequestHandler {
  return (req: Request, _res: Response, next: NextFunction): void => {
    // Un throw acá lo captura el errorHandler: ZodError -> 422.
    if (schemas.params) req.params = schemas.params.parse(req.params) as Request['params'];
    if (schemas.query) Object.assign(req.query, schemas.query.parse(req.query));
    if (schemas.body) req.body = schemas.body.parse(req.body);
    next();
  };
}
