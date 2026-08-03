<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

final class BookAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            \App\Models\Appointment::class,
            $this->input('patient_id')
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid', 'exists:users,id'],
            'doctor_id' => [
                'required',
                'uuid',
                'exists:users,id',
                Rule::exists('user_roles', 'user_id')->where(function ($query) {
                    $query->where('role_id', function ($sub) {
                        $sub->select('id')->from('roles')->where('name', 'doctor')->limit(1);
                    });
                })
            ],
            'franja_inicio' => ['required', 'date', 'after:now'],
            'franja_fin' => ['required', 'date', 'after:franja_inicio'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $inicioStr = $this->input('franja_inicio');
            $finStr = $this->input('franja_fin');

            if ($inicioStr && $finStr) {
                $inicio = Carbon::parse($inicioStr);
                $fin = Carbon::parse($finStr);

                // Límite de agendamiento futuro (máximo 1 año)
                if ($inicio->gt(Carbon::now()->addYear())) {
                    $validator->errors()->add('franja_inicio', 'La fecha de la cita no puede ser posterior a un año en el futuro.');
                }

                // Duración exacta de 30 minutos
                if ((int) $inicio->diffInMinutes($fin) !== 30) {
                    $validator->errors()->add('franja_fin', 'La duración del slot de cita debe ser de exactamente 30 minutos.');
                }
            }

            // Validar X-Idempotency-Key
            $idempotencyKey = $this->header('X-Idempotency-Key');
            if (empty($idempotencyKey)) {
                $validator->errors()->add('idempotency_key', 'El encabezado X-Idempotency-Key es obligatorio.');
            } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $idempotencyKey)) {
                $validator->errors()->add('idempotency_key', 'El encabezado X-Idempotency-Key debe ser un UUID v4 válido.');
            }
        });
    }
}
