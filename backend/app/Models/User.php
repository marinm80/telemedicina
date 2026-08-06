<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuids;

    /**
     * Columnas que app_runtime puede leer vía GRANT SELECT de columna.
     * password y remember_token están FUERA: el acceso privilegiado pasa
     * por fn_user_for_auth (SECURITY DEFINER), no por SELECT directo.
     *
     * Un SELECT * fallaría con "permission denied" por el GRANT columna.
     * Este scope fuerza SELECT explícito en TODA consulta Eloquent.
     */
    private const SELECTABLE_COLUMNS = [
        'id', 'name', 'last_name', 'email', 'timezone',
        'email_verified_at', 'is_active',
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('safe_columns', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $builder->select(self::SELECTABLE_COLUMNS);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'timezone',
        'email_verified_at',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean'
        ];
    }

    /**
     * Relación con los roles del usuario.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    /**
     * Relación con el perfil de paciente.
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class, 'user_id');
    }

    /**
     * Relación con el perfil de médico.
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class, 'user_id');
    }

    /**
     * Helper para verificar rol de negocio.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName;
    }

    /**
     * Getter dinámico para el rol principal.
     * Usa pgsql_admin para bypass RLS que bloquea user_roles.
     */
    public function getRoleAttribute(): string
    {
        static $cache = [];
        if (isset($cache[$this->id])) {
            return $cache[$this->id];
        }

        $role = \Illuminate\Support\Facades\DB::connection('pgsql_admin')
            ->table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->value('roles.name');

        $cache[$this->id] = $role ?: 'patient';
        return $cache[$this->id];
    }
}
