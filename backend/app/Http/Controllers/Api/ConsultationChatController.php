<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Clinical\SendConsultationMessageAction;
use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ConsultationChatController extends Controller
{
    /**
     * Enviar un mensaje al chat clínico de la consulta (RF-14).
     */
    public function store(Request $request, string $id, SendConsultationMessageAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para enviar mensajes.'
            ], 401);
        }

        // Bloqueo explícito de agentes
        if ($user->role === 'agent') {
            return response()->json([
                'error_code' => 'AGENT_ACCESS_FORBIDDEN',
                'message'    => 'El agente administrativo no tiene permitido enviar mensajes en el chat clínico.'
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $message = $action->handle(
                $id,
                $user->id,
                $user->role,
                $validated['content']
            );

            return response()->json([
                'id'              => $message->id,
                'consultation_id' => $message->consultation_id,
                'sender_id'       => $message->sender_id,
                'content'         => $message->content,
                'created_at'      => $message->created_at?->toIso8601String(),
            ], 201);
        } catch (ConsultationNotFoundException $e) {
            return response()->json([
                'error_code' => 'CONSULTATION_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_CHAT_ACCESS',
                'message'    => $e->getMessage()
            ], 403);
        } catch (InvalidAppointmentStatusException $e) {
            return response()->json([
                'error_code' => 'INVALID_APPOINTMENT_STATUS',
                'message'    => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Obtener el historial de mensajes del chat clínico de la consulta (RF-14).
     */
    public function index(string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para leer los mensajes del chat.'
            ], 401);
        }

        if ($user->role === 'agent') {
            return response()->json([
                'error_code' => 'AGENT_ACCESS_FORBIDDEN',
                'message'    => 'El agente administrativo no tiene acceso al chat clínico.'
            ], 403);
        }

        $consultation = Consultation::with('appointment')->find($id);
        if (!$consultation) {
            $consultation = Consultation::where('appointment_id', $id)->first();
        }

        if (!$consultation) {
            return response()->json([
                'error_code' => 'CONSULTATION_NOT_FOUND',
                'message'    => 'Consulta médica no encontrada.'
            ], 404);
        }

        $appointment = $consultation->appointment;
        if ($appointment) {
            $isPatient = ($appointment->patient_id === $user->id);
            $isDoctor  = ($appointment->doctor_id === $user->id);
            $isAdmin   = ($user->role === 'admin');

            if (!$isPatient && !$isDoctor && !$isAdmin) {
                return response()->json([
                    'error_code' => 'UNAUTHORIZED_CHAT_ACCESS',
                    'message'    => 'No tiene permisos para ver los mensajes de esta consulta.'
                ], 404);
            }
        }

        $messages = ConsultationMessage::where('consultation_id', $consultation->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $messages->map(fn (ConsultationMessage $msg) => [
                'id'              => $msg->id,
                'consultation_id' => $msg->consultation_id,
                'sender_id'       => $msg->sender_id,
                'content'         => $msg->content,
                'created_at'      => $msg->created_at?->toIso8601String(),
            ])
        ], 200);
    }
}
