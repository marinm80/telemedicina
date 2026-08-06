<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Http\Controllers;

use App\Actions\Appointments\ApproveRescheduleRequestAction;
use App\Actions\Appointments\BookAppointmentAction;
use App\Actions\Appointments\CancelAppointmentAction;
use App\Actions\Appointments\CreateRescheduleRequestAction;
use App\Actions\Appointments\GetDoctorAvailabilityAction;
use App\Actions\Appointments\RejectRescheduleRequestAction;
use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\PatientSlotCollisionException;
use App\Exceptions\RescheduleCollisionException;
use App\Exceptions\RescheduleRequestNotFoundException;
use App\Exceptions\SlotCollisionException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AppointmentController extends Controller
{
    /**
     * Store a newly created appointment.
     */
    public function store(BookAppointmentRequest $request, BookAppointmentAction $action): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');
        
        try {
            $appointment = $action->handle(
                $request->validated(),
                (string) $idempotencyKey
            );
            return AppointmentResource::make($appointment)
                ->response()
                ->setStatusCode(201);
        } catch (PatientSlotCollisionException $e) {
            return response()->json([
                'error_code' => 'PATIENT_SLOT_COLLISION',
                'message'    => $e->getMessage(),
            ], 409);
        } catch (SlotCollisionException $e) {
            return response()->json([
                'error_code' => 'SLOT_COLLISION',
                'message'    => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Get availability for a doctor on a specific date.
     */
    public function availability(AvailabilityRequest $request, string $doctorId, GetDoctorAvailabilityAction $action): JsonResponse
    {
        $date = $request->string('date')->toString();

        $availability = $action->handle($doctorId, $date);

        return response()->json($availability);
    }

    /**
     * Cancel an existing appointment (RF-25).
     */
    public function cancel(Request $request, string $id, CancelAppointmentAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para cancelar una cita.'
            ], 401);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $action->handle($id, $user->id, $user->role, $validated['reason'] ?? null);

            return response()->json([
                'data' => [
                    'id'                  => $result['appointment']->id,
                    'status'              => $result['appointment']->status,
                    'cancelled_by'        => $result['appointment']->cancelled_by,
                    'cancellation_reason' => $result['appointment']->cancellation_reason,
                    'refund_percentage'   => $result['refund_percentage'],
                    'refund_status'       => $result['refund_status'],
                ]
            ], 200);
        } catch (AppointmentNotFoundException $e) {
            return response()->json([
                'error_code' => 'APPOINTMENT_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_CANCELLATION',
                'message'    => $e->getMessage()
            ], 403);
        } catch (InvalidAppointmentStatusException $e) {
            return response()->json([
                'error_code' => 'INVALID_APPOINTMENT_STATUS',
                'message'    => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Solicitar la reprogramación de una cita médica (RF-11).
     */
    public function rescheduleRequest(Request $request, string $id, CreateRescheduleRequestAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para solicitar reprogramación.'
            ], 401);
        }

        $validated = $request->validate([
            'nueva_franja_inicio' => ['required', 'date'],
            'nueva_franja_fin'    => ['required', 'date', 'after:nueva_franja_inicio'],
            'motivo'              => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $rescheduleRequest = $action->handle(
                $id,
                $user->id,
                $user->role,
                $validated['nueva_franja_inicio'],
                $validated['nueva_franja_fin'],
                $validated['motivo'] ?? null
            );

            return response()->json([
                'id'               => $rescheduleRequest->id,
                'appointment_id'   => $rescheduleRequest->appointment_id,
                'status'           => $rescheduleRequest->status,
                'requested_by'     => $rescheduleRequest->requested_by,
                'requested_franja' => $rescheduleRequest->requested_franja,
                'reason'           => $rescheduleRequest->reason,
            ], 201);
        } catch (AppointmentNotFoundException $e) {
            return response()->json([
                'error_code' => 'APPOINTMENT_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_RESCHEDULE',
                'message'    => $e->getMessage()
            ], 404);
        } catch (InvalidAppointmentStatusException $e) {
            return response()->json([
                'error_code' => 'INVALID_APPOINTMENT_STATUS',
                'message'    => $e->getMessage()
            ], 403);
        } catch (RescheduleCollisionException $e) {
            $errorCode = str_contains($e->getMessage(), 'pendiente') ? 'RESCHEDULE_ALREADY_PENDING' : 'SLOT_ALREADY_BOOKED';
            return response()->json([
                'error_code' => $errorCode,
                'message'    => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Aprobar la solicitud de reprogramación (RF-11).
     */
    public function rescheduleApprove(string $id, ApproveRescheduleRequestAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para aprobar reprogramaciones.'
            ], 401);
        }

        // El agente NO puede aprobar (solo médico o admin)
        if ($user->role === 'agent') {
            return response()->json([
                'error_code' => 'AGENT_CANNOT_APPROVE_RESCHEDULE',
                'message'    => 'El agente administrativo no puede aprobar reprogramaciones.'
            ], 403);
        }

        try {
            $result = $action->handle($id, $user->id, $user->role);

            return response()->json([
                'reschedule_request' => [
                    'id'          => $result['reschedule_request']->id,
                    'status'      => $result['reschedule_request']->status,
                    'resolved_by' => $result['reschedule_request']->resolved_by,
                    'resolved_at' => $result['reschedule_request']->resolved_at?->toIso8601String(),
                ],
                'cita_original_cancelada' => [
                    'id'                  => $result['original_appointment']->id,
                    'status'              => $result['original_appointment']->status,
                    'cancellation_reason' => $result['original_appointment']->cancellation_reason,
                ],
                'nueva_cita_confirmada' => [
                    'id'         => $result['new_appointment']->id,
                    'patient_id' => $result['new_appointment']->patient_id,
                    'doctor_id'  => $result['new_appointment']->doctor_id,
                    'franja'     => $result['new_appointment']->franja,
                    'status'     => $result['new_appointment']->status,
                ]
            ], 200);
        } catch (AppointmentNotFoundException | RescheduleRequestNotFoundException $e) {
            return response()->json([
                'error_code' => 'APPOINTMENT_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_RESCHEDULE_APPROVAL',
                'message'    => $e->getMessage()
            ], 403);
        } catch (RescheduleCollisionException $e) {
            return response()->json([
                'error_code' => 'SLOT_ALREADY_BOOKED',
                'message'    => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Rechazar la solicitud de reprogramación (RF-11).
     */
    public function rescheduleReject(Request $request, string $id, RejectRescheduleRequestAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para rechazar reprogramaciones.'
            ], 401);
        }

        $validated = $request->validate([
            'motivo_rechazo' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $rescheduleRequest = $action->handle($id, $user->id, $user->role, $validated['motivo_rechazo'] ?? null);

            return response()->json([
                'id'               => $rescheduleRequest->id,
                'status'           => $rescheduleRequest->status,
                'rejection_reason' => $rescheduleRequest->rejection_reason,
                'resolved_by'      => $rescheduleRequest->resolved_by,
                'resolved_at'      => $rescheduleRequest->resolved_at?->toIso8601String(),
            ], 200);
        } catch (AppointmentNotFoundException | RescheduleRequestNotFoundException $e) {
            return response()->json([
                'error_code' => 'APPOINTMENT_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_RESCHEDULE_REJECTION',
                'message'    => $e->getMessage()
            ], 403);
        }
    }
}
