<?php
declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AppointmentController extends Controller
{
    /**
     * Lista de citas médicas del usuario autenticado (filtradas automáticamente por RLS).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $db = DB::connection('pgsql_admin');

        $query = $db->table('appointments as a')
            ->select([
                'a.id',
                'a.patient_id',
                DB::raw("u_pat.name || ' ' || u_pat.last_name AS patient_name"),
                'a.doctor_id',
                DB::raw("u_doc.name || ' ' || u_doc.last_name AS doctor_name"),
                DB::raw("doc_specs.specialties AS doctor_specialty"),
                DB::raw("lower(a.franja) AS franja_start"),
                DB::raw("upper(a.franja) AS franja_end"),
                'a.status',
                'a.cancelled_by',
                'a.cancellation_reason',
            ])
            ->join('users as u_pat', 'u_pat.id', '=', 'a.patient_id')
            ->join('users as u_doc', 'u_doc.id', '=', 'a.doctor_id')
            ->leftJoinSub(
                $db->table('doctor_profiles as dp2')
                    ->join('doctor_specialties as ds2', 'ds2.doctor_profile_id', '=', 'dp2.id')
                    ->join('specialties as s2', 's2.id', '=', 'ds2.specialty_id')
                    ->select('dp2.user_id', DB::raw("string_agg(s2.name, ', ' ORDER BY s2.name) AS specialties"))
                    ->groupBy('dp2.user_id'),
                'doc_specs',
                'doc_specs.user_id', '=', 'a.doctor_id'
            );

        if ($user && $user->role === 'patient') {
            $query->where('a.patient_id', $user->id);
        } elseif ($user && $user->role === 'doctor') {
            $query->where('a.doctor_id', $user->id);
        }

        if ($status = $request->input('status')) {
            $query->where('a.status', $status);
        }

        $appointments = $query->orderByRaw("lower(a.franja) DESC")
            ->get()
            ->map(function ($app) {
                $consultation = DB::connection('pgsql_admin')->table('consultations')->where('appointment_id', $app->id)->first();
                
                return [
                    'id'                  => $app->id,
                    'patient_id'          => $app->patient_id,
                    'patient_name'        => $app->patient_name,
                    'doctor_id'           => $app->doctor_id,
                    'doctor_name'         => $app->doctor_name,
                    'doctor_specialty'    => $app->doctor_specialty,
                    'franja_start'        => $app->franja_start,
                    'franja_end'          => $app->franja_end,
                    'status'              => $app->status,
                    'cancelled_by'        => $app->cancelled_by,
                    'cancellation_reason' => $app->cancellation_reason,
                    'consultation_id'     => $consultation?->id,
                    'can_cancel'          => in_array($app->status, ['pending', 'confirmed'], true),
                    'can_reschedule'      => in_array($app->status, ['pending', 'confirmed'], true),
                ];
            });

        return Inertia::render('Appointments/MyAppointments', [
            'appointments' => $appointments,
            'filters'      => $request->only(['status']),
        ]);
    }
}
