<?php
declare(strict_types=1);

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AgendaController extends Controller
{
    /**
     * Panel de administración de agenda del médico autenticado.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // RLS filtra automáticamente doctor_profiles por user_id
        $profile = DB::table('doctor_profiles')->where('user_id', $user->id)->first();

        if (!$profile) {
            abort(403, 'Debes ser un médico registrado para acceder a la agenda.');
        }

        $schedules = DB::table('schedules')
            ->select([
                'id',
                'day_of_week',
                DB::raw("franja::text as franja"),
                'slot_duration',
            ])
            ->where('doctor_profile_id', $profile->id)
            ->whereNull('deleted_at')
            ->orderBy('day_of_week', 'asc')
            ->get();

        $scheduleBlocks = DB::table('schedule_blocks')
            ->select([
                'id',
                'blocked_date',
                DB::raw("franja::text as franja"),
                'reason',
            ])
            ->where('doctor_profile_id', $profile->id)
            ->orderBy('blocked_date', 'asc')
            ->get();

        return Inertia::render('Appointments/AgendaManager', [
            'doctor_profile'  => [
                'id'               => $profile->id,
                'status'           => $profile->status,
                'rejection_reason' => $profile->rejection_reason,
            ],
            'schedules' => $schedules,
            'blocks'    => $scheduleBlocks,
        ]);
    }
}
