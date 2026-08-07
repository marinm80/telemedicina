<?php
declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class BookingController extends Controller
{
    /**
     * Mostrar la vista del Wizard de Reserva de Citas.
     *
     * BookingWizard.vue maneja su propio fetch de disponibilidad
     * (GET /api/availability) y de creación de cita (POST /api/appointments)
     * — acá solo se le pasa el médico preseleccionado en la forma que su
     * prop `doctors: PublicDoctor[]` espera. Con exactamente 1 elemento,
     * el componente salta directo al paso 2 (ver BookingWizard.vue onMounted).
     */
    public function create(string $doctorProfileId): Response
    {
        $doctor = DB::table('v_doctor_directory')
            ->where('doctor_profile_id', $doctorProfileId)
            ->first();

        if (!$doctor) {
            abort(404, 'Médico especialista no encontrado o no disponible.');
        }

        $specialty = DB::table('doctor_specialties')
            ->join('specialties', 'specialties.id', '=', 'doctor_specialties.specialty_id')
            ->where('doctor_specialties.doctor_profile_id', $doctorProfileId)
            ->orderBy('specialties.name')
            ->value('specialties.name');

        return Inertia::render('Appointments/BookingWizard', [
            'doctors' => [[
                'id'                => $doctor->user_id,
                'name'              => $doctor->name,
                'last_name'         => $doctor->last_name,
                'description'       => $doctor->description,
                'university'        => $doctor->university,
                'years_experience'  => $doctor->years_experience,
                'consultation_fee'  => (float) $doctor->consultation_fee,
                'specialty'         => $specialty ?? 'Medicina General',
                'photo_url'         => $doctor->photo_path ? Storage::disk('public')->url($doctor->photo_path) : null,
            ]],
        ]);
    }
}
