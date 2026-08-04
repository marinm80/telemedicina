<?php
declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Actions\Appointments\GetDoctorSlotsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class BookingController extends Controller
{
    /**
     * Mostrar la vista del Wizard de Reserva de Citas.
     */
    public function create(string $doctorProfileId, Request $request, GetDoctorSlotsAction $getDoctorSlotsAction): Response
    {
        $doctor = DB::table('v_doctor_directory')
            ->where('doctor_profile_id', $doctorProfileId)
            ->first();

        if (!$doctor) {
            abort(404, 'Médico especialista no encontrado o no disponible.');
        }

        $specialtiesList = DB::table('doctor_specialties')
            ->join('specialties', 'specialties.id', '=', 'doctor_specialties.specialty_id')
            ->where('doctor_specialties.doctor_profile_id', $doctorProfileId)
            ->pluck('specialties.name')
            ->toArray();

        $selectedDate = $request->input('date', now()->format('Y-m-d'));

        $availableSlots = $getDoctorSlotsAction->handle($doctor->user_id, $selectedDate);

        return Inertia::render('Appointments/BookingWizard', [
            'doctor' => [
                'user_id'           => $doctor->user_id,
                'doctor_profile_id' => $doctor->doctor_profile_id,
                'name'              => $doctor->name,
                'last_name'         => $doctor->last_name,
                'consultation_fee'  => (float) $doctor->consultation_fee,
                'university'        => $doctor->university,
                'specialties'       => $specialtiesList,
            ],
            'selected_date'   => $selectedDate,
            'available_slots' => $availableSlots,
        ]);
    }
}
