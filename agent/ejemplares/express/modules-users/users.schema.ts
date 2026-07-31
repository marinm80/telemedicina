/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * PORTFOLIO:   https://rafaelmarin.dev
 * STACK:       Express 5 + TypeScript + Prisma + PostgreSQL
 * LAYER:       modules/users / schema
 * DESCRIPTION: Developed as a professional-level practical project.
 * ====================================================================
 */
/**
 * QUÉ PROBLEMA RESUELVE
 * La frontera del módulo. Define qué forma tienen los datos que entran y salen,
 * y los TIPOS SE DERIVAN DEL ESQUEMA con `z.infer`. Así la validación en tiempo
 * de ejecución y el tipo en tiempo de compilación no pueden desincronizarse.
 *
 * ALTERNATIVA DESCARTADA
 * Escribir la `interface` a mano y validar por separado. Es la misma información
 * dos veces: tarde o temprano una queda vieja y el tipo miente.
 */
import { z } from 'zod';

export const CreateUserSchema = z.object({
  email: z.string().trim().toLowerCase().email('Email inválido'),
  name: z.string().trim().min(2, 'El nombre debe tener al menos 2 caracteres').max(120),
  role: z.enum(['admin', 'user']).default('user'),
});

export const UpdateUserSchema = CreateUserSchema.partial().refine(
  (data) => Object.keys(data).length > 0,
  { message: 'Se requiere al menos un campo para actualizar' },
);

export const UserIdParamSchema = z.object({
  id: z.string().uuid('El id debe ser un UUID'),
});

export const ListUsersQuerySchema = z.object({
  page: z.coerce.number().int().min(1).default(1),
  perPage: z.coerce.number().int().min(1).max(100).default(20),
  search: z.string().trim().min(1).optional(),
});

/** Lo que se devuelve al cliente. Nunca la fila cruda de la base. */
export const UserResponseSchema = z.object({
  id: z.string().uuid(),
  email: z.string().email(),
  name: z.string(),
  role: z.enum(['admin', 'user']),
  createdAt: z.date(),
});

export type CreateUserInput = z.infer<typeof CreateUserSchema>;
export type UpdateUserInput = z.infer<typeof UpdateUserSchema>;
export type ListUsersQuery = z.infer<typeof ListUsersQuerySchema>;
export type UserResponse = z.infer<typeof UserResponseSchema>;
