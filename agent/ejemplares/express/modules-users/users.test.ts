/**
 * ====================================================================
 * PROJECT:     Esqueleto Express OOP — SDD v3.2
 * AUTHOR:      Rafael Marín
 * LAYER:       modules/users / test
 * ====================================================================
 *
 * QUÉ DEMUESTRA ESTE ARCHIVO
 * Que el servicio se puede probar SIN base de datos y SIN servidor HTTP, porque
 * no conoce ninguna de las dos cosas. El repositorio se reemplaza por un doble.
 *
 * Si algún día un test de servicio necesita levantar Express o Postgres, es
 * síntoma de que la regla de capas se rompió.
 *
 * ESTE ES EL PATRÓN DE PRUEBAS CONGELADO: los módulos nuevos lo copian.
 * Los casos límite los define el humano (Gate 3.5); implementarlos en código
 * es lo que se delega.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { UsersService } from './users.service.js';
import type { UsersRepository } from './users.repo.js';
import { ConflictError, NotFoundError } from '../../shared/lib/httpError.js';

const usuarioDemo = {
  id: '11111111-1111-4111-8111-111111111111',
  email: 'ana@ejemplo.com',
  name: 'Ana',
  role: 'user' as const,
  createdAt: new Date('2026-01-01'),
};

/**
 * Doble del repositorio. El tipo se deriva de la clase real, así que si mañana el
 * repositorio cambia una firma, este archivo deja de compilar — que es
 * exactamente lo que queremos: el test avisa antes que el runtime.
 */
type RepoFalso = { [K in keyof UsersRepository]: ReturnType<typeof vi.fn> };

function crearRepoFalso(): RepoFalso {
  return {
    findById: vi.fn(),
    findByEmail: vi.fn(),
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
  };
}

describe('UsersService', () => {
  let repo: RepoFalso;
  let service: UsersService;

  beforeEach(() => {
    repo = crearRepoFalso();
    service = new UsersService(repo as unknown as UsersRepository);
  });

  describe('getById', () => {
    it('devuelve el usuario cuando existe', async () => {
      repo.findById.mockResolvedValue(usuarioDemo);
      await expect(service.getById(usuarioDemo.id)).resolves.toEqual(usuarioDemo);
    });

    it('lanza NotFoundError cuando no existe', async () => {
      repo.findById.mockResolvedValue(null);
      await expect(service.getById('otro-id')).rejects.toBeInstanceOf(NotFoundError);
    });
  });

  describe('create', () => {
    it('crea el usuario cuando el email está libre', async () => {
      repo.findByEmail.mockResolvedValue(null);
      repo.create.mockResolvedValue(usuarioDemo);

      const creado = await service.create({ email: usuarioDemo.email, name: 'Ana', role: 'user' });

      expect(creado).toEqual(usuarioDemo);
      expect(repo.create).toHaveBeenCalledOnce();
    });

    it('lanza ConflictError si el email ya está registrado', async () => {
      repo.findByEmail.mockResolvedValue(usuarioDemo);

      await expect(
        service.create({ email: usuarioDemo.email, name: 'Otra', role: 'user' }),
      ).rejects.toBeInstanceOf(ConflictError);

      // Lo importante: no se intentó escribir.
      expect(repo.create).not.toHaveBeenCalled();
    });
  });

  describe('list', () => {
    it('calcula totalPages correctamente', async () => {
      repo.list.mockResolvedValue({ items: [usuarioDemo], total: 45 });
      const r = await service.list({ page: 1, perPage: 20 });
      expect(r.meta).toEqual({ page: 1, perPage: 20, total: 45, totalPages: 3 });
    });

    it('devuelve totalPages 1 cuando no hay resultados, no 0', async () => {
      repo.list.mockResolvedValue({ items: [], total: 0 });
      const r = await service.list({ page: 1, perPage: 20 });
      expect(r.meta.totalPages).toBe(1);
    });
  });

  describe('update', () => {
    it('lanza NotFoundError antes de intentar escribir', async () => {
      repo.findById.mockResolvedValue(null);
      await expect(service.update('inexistente', { name: 'X' })).rejects.toBeInstanceOf(NotFoundError);
      expect(repo.update).not.toHaveBeenCalled();
    });
  });
});
