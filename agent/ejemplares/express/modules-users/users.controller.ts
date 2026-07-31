/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       modules/users / controller
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * Traduce HTTP a llamadas de servicio y de vuelta. Nada más.
 *
 * REGLAS QUE ESTE ARCHIVO NO PUEDE VIOLAR (verificadas automáticamente):
 *   1. No accede a la base de datos.
 *   2. Ningún método pasa de ~20 líneas.
 *   3. No contiene lógica de negocio: si hay un `if` con una regla, va al servicio.
 *
 * Si un método crece, la señal no es "refactorizar el controlador": es que algo
 * está en la capa equivocada.
 *
 * Los métodos son arrow properties para que `this` quede ligado al pasarlos como
 * handler a Express, sin necesidad de `.bind(this)` en cada ruta.
 */
import type { Request, Response } from 'express';
import type { UsersService } from './users.service.js';
import type { CreateUserInput, UpdateUserInput, ListUsersQuery } from './users.schema.js';

export class UsersController {
  constructor(private readonly service: UsersService) {}

  list = async (req: Request, res: Response): Promise<void> => {
    const result = await this.service.list(req.query as unknown as ListUsersQuery);
    res.status(200).json(result);
  };

  getById = async (req: Request, res: Response): Promise<void> => {
    const user = await this.service.getById(req.params.id as string);
    res.status(200).json({ data: user });
  };

  create = async (req: Request, res: Response): Promise<void> => {
    const user = await this.service.create(req.body as CreateUserInput);
    res.status(201).location(`/api/users/${user.id}`).json({ data: user });
  };

  update = async (req: Request, res: Response): Promise<void> => {
    const user = await this.service.update(req.params.id as string, req.body as UpdateUserInput);
    res.status(200).json({ data: user });
  };

  remove = async (req: Request, res: Response): Promise<void> => {
    await this.service.delete(req.params.id as string);
    res.status(204).send();
  };
}
