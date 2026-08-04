<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Clinical\SubmitPreConsultationAction;
use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\PreConsultationAlreadyExistsException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PreConsultationForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PreConsultationController extends Controller
{
    /**
     * Guardar el cuestionario pre-consulta para una cita (RF-13).
     */
    public function store(Request $request, string $id, SubmitPreConsultationAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para enviar el cuestionario pre-consulta.'
            ], 401);
        }

        $validated = $request->validate([
            'motivo'    => ['required', 'string', 'max:1000'],
            'sintomas'  => ['required', 'string', 'max:2000'],
            'form_data' => ['nullable', 'array'],
        ]);

        try {
            $form = $action->handle(
                $id,
                $user->id,
                $user->role,
                $validated['motivo'],
                $validated['sintomas'],
                $validated['form_data'] ?? null
            );

            return response()->json([
                'id'             => $form->id,
                'appointment_id' => $form->appointment_id,
                'motivo'         => $form->motivo,
                'sintomas'       => $form->sintomas,
                'form_data'      => $form->form_data,
                'created_at'     => $form->created_at?->toIso8601String(),
            ], 201);
        } catch (AppointmentNotFoundException $e) {
            return response()->json([
                'error_code' => 'APPOINTMENT_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_PRE_CONSULTATION',
                'message'    => $e->getMessage()
            ], 403);
        } catch (InvalidAppointmentStatusException $e) {
            return response()->json([
                'error_code' => 'INVALID_APPOINTMENT_STATUS',
                'message'    => $e->getMessage()
            ], 422);
        } catch (PreConsultationAlreadyExistsException $e) {
            return response()->json([
                'error_code' => 'PRE_CONSULTATION_ALREADY_EXISTS',
                'message'    => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Obtener el cuestionario pre-consulta de una cita (RF-13).
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para consultar el cuestionario.'
            ], 401);
        }

        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json([
                'error_code' => 'APPOINTMENT_NOT_FOUND',
                'message'    => 'Cita médica no encontrada.'
            ], 404);
        }

        $form = PreConsultationForm::where('appointment_id', $id)->first();
        if (!$form) {
            return response()->json([
                'error_code' => 'PRE_CONSULTATION_NOT_FOUND',
                'message'    => 'No existe cuestionario pre-consulta registrado para esta cita.'
            ], 404);
        }

        return response()->json([
            'id'             => $form->id,
            'appointment_id' => $form->appointment_id,
            'motivo'         => $form->motivo,
            'sintomas'       => $form->sintomas,
            'form_data'      => $form->form_data,
            'created_at'     => $form->created_at?->toIso8601String(),
        ], 200);
    }
}
