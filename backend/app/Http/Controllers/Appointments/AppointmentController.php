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
        $allowedUserCols = ['id', 'name', 'last_name', 'email'];

        $query = DB::table('appointments as a')
            ->select([
                'a.id',
                'a.patient_id',
                DB::raw("u_pat.name || ' ' || u_pat.last_name AS patient_name"),
                'a.doctor_id',
                DB::raw("u_doc.name || ' ' || u_doc.last_name AS doctor_name"),
                DB::raw("lower(a.franja) AS franja_start"),
                DB::raw("upper(a.franja) AS franja_end"),
                'a.status',
                'a.cancelled_by',
                'a.cancellation_reason',
            ])
            ->join('users as u_pat', 'u_pat.id', '=', 'a.patient_id')
            ->join('users as u_doc', 'u_doc.id', '=', 'a.doctor_id');

        if ($status = $request->input('status')) {
            $query->where('a.status', $status);
        }

        $appointments = $query->orderByRaw("lower(a.franja) DESC")
            ->get()
            ->map(function ($app) {
                $consultation = DB::table('consultations')->where('appointment_id', $app->id)->first();
                
                return [
                    'id'                  => $app->id,
                    'patient_id'          => $app->patient_id,
                    'patient_name'        => $app->patient_name,
                    'doctor_id'           => $app->doctor_id,
                    'doctor_name'         => $app->doctor_name,
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
