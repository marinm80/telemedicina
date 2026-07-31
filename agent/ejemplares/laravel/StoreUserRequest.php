<?php
declare(strict_types=1);
/**
 * ====================================================================
 * EJEMPLAR CANÓNICO — Form Request (Laravel 11)
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE EJEMPLAR
 * La validación vive acá, NUNCA en el controlador. Y `authorize()` es parte de
 * la frontera: la autorización se decide antes de validar.
 *
 * ALTERNATIVA DESCARTADA
 * `$request->validate([...])` dentro del controlador. Engorda el controlador y
 * hace que la misma regla se repita en store() y update().
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorización explícita. Devolver true "por ahora" es cómo se filtran
        // los agujeros de permisos: si no aplica, se escribe por qué.
        return $this->user()?->can('create', \App\Models\User::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'name'  => ['required', 'string', 'min:2', 'max:120'],
            'role'  => ['sometimes', Rule::in(['admin', 'user'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => \is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }
}
