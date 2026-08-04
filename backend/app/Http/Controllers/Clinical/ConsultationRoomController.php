<?php
declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ConsultationRoomController extends Controller
{
    /**
     * Sala de telemedicina y expediente clínico de la consulta.
     */
    public function show(string $appointmentId, Request $request): Response
    {
        // RLS en appointments verifica si el usuario autenticado es admin, el médico o el paciente de la cita
        $appointment = DB::table('appointments as a')
            ->select([
                'a.id',
                'a.patient_id',
                DB::raw("u_pat.name || ' ' || u_pat.last_name AS patient_name"),
                'a.doctor_id',
                DB::raw("u_doc.name || ' ' || u_doc.last_name AS doctor_name"),
                DB::raw("lower(a.franja) AS franja_start"),
                DB::raw("upper(a.franja) AS franja_end"),
                'a.status',
            ])
            ->join('users as u_pat', 'u_pat.id', '=', 'a.patient_id')
            ->join('users as u_doc', 'u_doc.id', '=', 'a.doctor_id')
            ->where('a.id', $appointmentId)
            ->first();

        if (!$appointment) {
            abort(404, 'Consulta no encontrada o sin privilegios de acceso.');
        }

        // Obtener o crear consulta clínica activa
        $consultation = DB::table('consultations')->where('appointment_id', $appointmentId)->first();
        if (!$consultation) {
            $consultationId = \Illuminate\Support\Str::uuid()->toString();
            DB::table('consultations')->insert([
                'id'             => $consultationId,
                'appointment_id' => $appointmentId,
                'started_at'     => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $consultation = DB::table('consultations')->where('id', $consultationId)->first();
        }

        // RLS filtra automáticamente patient_profiles
        $patientProfile = DB::table('patient_profiles')->where('user_id', $appointment->patient_id)->first();
        $allergies = DB::table('patient_allergies')->where('patient_profile_id', $patientProfile?->id)->get();
        $conditions = DB::table('patient_conditions')->where('patient_profile_id', $patientProfile?->id)->get();
        $medications = DB::table('patient_medications')->where('patient_profile_id', $patientProfile?->id)->get();

        // RLS filtra automáticamente consultation_notes
        $soapNote = DB::table('consultation_notes')->where('consultation_id', $consultation->id)->first();

        return Inertia::render('Clinical/ConsultationRoom', [
            'appointment' => [
                'id'           => $appointment->id,
                'patient_id'   => $appointment->patient_id,
                'patient_name' => $appointment->patient_name,
                'doctor_id'    => $appointment->doctor_id,
                'doctor_name'  => $appointment->doctor_name,
                'status'       => $appointment->status,
                'franja_start' => $appointment->franja_start,
                'franja_end'   => $appointment->franja_end,
            ],
            'consultation' => [
                'id'         => $consultation->id,
                'started_at' => $consultation->started_at,
                'ended_at'   => $consultation->ended_at,
            ],
            'patient_file' => [
                'phone'              => $patientProfile?->phone ?? '',
                'date_of_birth'     => $patientProfile?->date_of_birth ?? '',
                'gender'             => $patientProfile?->gender ?? '',
                'address'            => $patientProfile?->address ?? '',
                'allergies'          => $allergies,
                'conditions'         => $conditions,
                'medications'        => $medications,
                'past_consultations' => [],
            ],
            'soap_note' => [
                'id'          => $soapNote?->id,
                'symptoms'    => $soapNote?->symptoms ?? '',
                'objective'   => $soapNote?->objective ?? '',
                'analysis'    => $soapNote?->analysis ?? '',
                'plan'        => $soapNote?->plan ?? '',
                'status'      => $soapNote?->status ?? 'draft',
                'signed_at'   => $soapNote?->signed_at,
                'sha256_hash' => $soapNote?->sha256_hash,
            ],
        ]);
    }
}
