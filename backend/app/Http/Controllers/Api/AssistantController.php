<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Assistant\ClinicalAssistantAction;
use App\Actions\Assistant\PublicAssistantAction;
use App\Exceptions\AssistantDisabledDuringConsultationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AssistantController extends Controller
{
    /**
     * Asistente Informativo en landing pública (RF-23).
     * Lectura pura de v_doctor_directory con 0 escrituras.
     */
    public function publicAssistant(Request $request, PublicAssistantAction $action): JsonResponse
    {
        $validated = $request->validate([
            'query'     => ['required', 'string', 'max:1000'],
            'specialty' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $action->handle(
            $validated['query'],
            $validated['specialty'] ?? null
        );

        return response()->json($result, 200);
    }

    /**
     * Asistente Clínico en dashboard del paciente (RF-24).
     * Retorna 409 Conflict si la cita está en estado in_progress.
     */
    public function clinicalAssistant(Request $request, ClinicalAssistantAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para consultar al Asistente Clínico.'
            ], 401);
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $action->handle(
                $user->id,
                $validated['query']
            );

            return response()->json($result, 200);
        } catch (AssistantDisabledDuringConsultationException $e) {
            return response()->json([
                'error_code' => 'ASSISTANT_DISABLED_DURING_CONSULTATION',
                'message'    => $e->getMessage()
            ], 409);
        }
    }
}
