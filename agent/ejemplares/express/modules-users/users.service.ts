/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       modules/users / service
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * Toda la lógica de negocio del módulo. La regla de oro de este archivo:
 *
 *     NO recibe ni devuelve `req` / `res`. NO sabe que HTTP existe.
 *
 * Eso es lo que permite probarlo sin levantar el servidor (ver users.test.ts) y
 * es lo que la barrera de dependency-cruiser hace cumplir automáticamente.
 *
 * ALTERNATIVA DESCARTADA
 * Poner esto en el controlador. Es el camino directo al "fat controller" de 600
 * líneas: imposible de probar, imposible de reutilizar desde un job de cola.
 */
import { ConflictError, NotFoundError } from '../../shared/lib/httpError.js';
import type { UsersRepository } from './users.repo.js';
import type { CreateUserInput, UpdateUserInput, ListUsersQuery } from './users.schema.js';

export class UsersService {
  constructor(private readonly repo: UsersRepository) {}

  async getById(id: string) {
    const user = await this.repo.findById(id);
    if (!user) throw new NotFoundError('Usuario');
    return user;
  }

  async list(query: ListUsersQuery) {
    const { items, total } = await this.repo.list(query);
    return {
      items,
      meta: {
        page: query.page,
        perPage: query.perPage,
        total,
        totalPages: Math.max(1, Math.ceil(total / query.perPage)),
      },
    };
  }

  /**
   * OJO — CONDICIÓN DE CARRERA CONOCIDA (Manual de Patrones, sección 1.3).
   * Esta comprobación previa es un patrón "verificar y después escribir": entre
   * el findByEmail y el create, otra petición puede insertar el mismo email.
   *
   * La comprobación existe solo para dar un mensaje de error claro. La defensa
   * REAL es la restricción UNIQUE del esquema, y el errorHandler traduce el
   * P2002 de Prisma a un 409. Nunca al revés: una validación en código se puede
   * olvidar, una restricción del esquema no.
   */
  async create(input: CreateUserInput) {
    const existente = await this.repo.findByEmail(input.email);
    if (existente) throw new ConflictError('El email ya está registrado');
    return this.repo.create(input);
  }

  async update(id: string, input: UpdateUserInput) {
    await this.getById(id);              // 404 si no existe
    return this.repo.update(id, input);
  }

  async delete(id: string): Promise<void> {
    await this.getById(id);
    await this.repo.delete(id);
  }
}
