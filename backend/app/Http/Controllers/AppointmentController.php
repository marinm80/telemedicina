<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\AvailabilityRequest;
use App\Actions\Appointments\BookAppointmentAction;
use App\Actions\Appointments\GetDoctorAvailabilityAction;
use App\Http\Resources\AppointmentResource;
use Illuminate\Http\JsonResponse;

final class AppointmentController extends Controller
{
    /**
     * Store a newly created appointment.
     */
    public function store(BookAppointmentRequest $request, BookAppointmentAction $action): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');
        
        $appointment = $action->handle(
            $request->validated(),
            (string) $idempotencyKey
        );

        return AppointmentResource::make($appointment)
            ->response()
            ->setStatusCode(201);
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
}
