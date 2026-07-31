/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       modules/users / routes
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * El cableado del módulo, en un solo lugar visible. Acá se ve de un vistazo:
 * qué rutas existen, qué valida cada una y qué permisos exige. Es el archivo que
 * se lee primero en el Gate 4.
 *
 * ARMADO EXPLÍCITO DE DEPENDENCIAS (composition root del módulo)
 * repo -> service -> controller, a mano y con tipos. Sin contenedor de inyección,
 * sin decoradores, sin resolución en tiempo de ejecución. Es más verboso y es
 * deliberado: se puede seguir con el dedo, y eso es exactamente lo que hace
 * auditable un código que no escribiste.
 */
import { Router } from 'express';
import { prisma } from '../../shared/db/client.js';
import { validate } from '../../shared/middleware/validate.js';
import { UsersRepository } from './users.repo.js';
import { UsersService } from './users.service.js';
import { UsersController } from './users.controller.js';
import {
  CreateUserSchema, UpdateUserSchema, UserIdParamSchema, ListUsersQuerySchema,
} from './users.schema.js';

export function createUsersRouter(): Router {
  const repo = new UsersRepository(prisma);
  const service = new UsersService(repo);
  const controller = new UsersController(service);

  const router = Router();

  router.get('/', validate({ query: ListUsersQuerySchema }), controller.list);
  router.get('/:id', validate({ params: UserIdParamSchema }), controller.getById);
  router.post('/', validate({ body: CreateUserSchema }), controller.create);
  router.patch('/:id',
    validate({ params: UserIdParamSchema, body: UpdateUserSchema }), controller.update);
  router.delete('/:id', validate({ params: UserIdParamSchema }), controller.remove);

  // Ejemplo de ruta protegida (requireAuth es un marcador, ver su archivo):
  // router.delete('/:id', requireAuth, requireRole('admin'), ..., controller.remove);

  return router;
}
