/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       modules/users / repository
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * La ÚNICA capa que habla con la base de datos. Aísla Prisma del resto: si
 * mañana un cliente impone MySQL o SQL Server, se reescribe esta clase y el
 * servicio no se toca. Es el patrón Repository del Manual, sección 2.
 *
 * ALTERNATIVA DESCARTADA
 * Usar Prisma directo desde el servicio. Funciona, pero acopla la lógica de
 * negocio al ORM y hace imposible probar el servicio sin base de datos.
 *
 * SELECCIÓN EXPLÍCITA DE CAMPOS
 * `select` en lugar de traer la fila completa. Evita filtrar columnas sensibles
 * por accidente cuando alguien agregue una columna nueva a la tabla.
 */
import type { Db } from '../../shared/db/client.js';
import type { CreateUserInput, UpdateUserInput, ListUsersQuery } from './users.schema.js';

const CAMPOS_PUBLICOS = {
  id: true, email: true, name: true, role: true, createdAt: true,
} as const;

export class UsersRepository {
  /** Inyección por constructor: explícita, sin contenedor, sin magia. */
  constructor(private readonly db: Db) {}

  async findById(id: string) {
    return this.db.user.findUnique({ where: { id }, select: CAMPOS_PUBLICOS });
  }

  async findByEmail(email: string) {
    return this.db.user.findUnique({ where: { email }, select: CAMPOS_PUBLICOS });
  }

  /**
   * Paginación y total en UNA transacción de lectura, no en dos consultas
   * sueltas: así el conteo y la página corresponden al mismo instante.
   */
  async list(query: ListUsersQuery) {
    const where = query.search
      ? {
          OR: [
            { name: { contains: query.search, mode: 'insensitive' as const } },
            { email: { contains: query.search, mode: 'insensitive' as const } },
          ],
        }
      : {};

    const [items, total] = await this.db.$transaction([
      this.db.user.findMany({
        where,
        select: CAMPOS_PUBLICOS,
        orderBy: { createdAt: 'desc' },
        skip: (query.page - 1) * query.perPage,
        take: query.perPage,
      }),
      this.db.user.count({ where }),
    ]);

    return { items, total };
  }

  async create(data: CreateUserInput) {
    return this.db.user.create({ data, select: CAMPOS_PUBLICOS });
  }

  async update(id: string, data: UpdateUserInput) {
    return this.db.user.update({ where: { id }, data, select: CAMPOS_PUBLICOS });
  }

  async delete(id: string): Promise<void> {
    await this.db.user.delete({ where: { id } });
  }
}
