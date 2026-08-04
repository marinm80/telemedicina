<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    /**
     * Renderizar el dashboard principal según el rol del usuario autenticado (Opción B).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role ?? 'patient';

        return match ($role) {
            'admin'  => $this->adminDashboard(),
            'doctor' => $this->doctorDashboard($user),
            'agent'  => $this->agentDashboard(),
            default  => $this->patientDashboard($user),
        };
    }

    private function adminDashboard(): Response
    {
        $allowedUserCols = ['id', 'name', 'last_name', 'email', 'timezone', 'is_active', 'created_at', 'updated_at'];

        return Inertia::render('Dashboard/AdminDashboard', [
            'total_users'                => DB::table('users')->count(),
            'pending_doctor_approvals'   => DB::table('doctor_profiles')->where('status', 'pending')->count(),
            'monthly_appointments_count' => DB::table('appointments')->where('created_at', '>=', now()->startOfMonth())->count(),
            'total_revenue'              => (float) DB::table('payments')->where('status', 'completed')->sum('amount'),
        ]);
    }

    private function doctorDashboard($user): Response
    {
        $profile = DB::table('doctor_profiles')->where('user_id', $user->id)->first();

        // RLS filtra automáticamente las citas pertenientes al médico autenticado
        $todayAppointments = DB::table('appointments')
            ->select(['id', 'patient_id', 'doctor_id', 'status', DB::raw("lower(franja) as franja_start"), DB::raw("upper(franja) as franja_end")])
            ->whereDate(DB::raw("lower(franja)"), now()->toDateString())
            ->get();

        // RLS filtra automáticamente las notas en borrador
        $pendingNotesCount = DB::table('consultation_notes')
            ->where('status', 'draft')
            ->count();

        $monthEarnings = (float) DB::table('commissions')
            ->where('status', 'released')
            ->sum('doctor_earning');

        return Inertia::render('Dashboard/DoctorDashboard', [
            'profile_status'     => $profile?->status ?? 'pending',
            'today_appointments' => $todayAppointments,
            'pending_notes_count'=> $pendingNotesCount,
            'month_earnings'     => $monthEarnings,
        ]);
    }

    private function patientDashboard($user): Response
    {
        // RLS filtra automáticamente las citas pertenecientes al paciente autenticado
        $upcomingAppointments = DB::table('appointments')
            ->select(['id', 'patient_id', 'doctor_id', 'status', DB::raw("lower(franja) as franja_start"), DB::raw("upper(franja) as franja_end")])
            ->whereRaw("upper(franja) >= ?", [now()->toIso8601String()])
            ->orderByRaw("lower(franja) ASC")
            ->get();

        // RLS filtra automáticamente las consultas y medicamentos del paciente
        $pastConsultationsCount = DB::table('consultations')->count();
        $activePrescriptionsCount = DB::table('patient_medications')->count();

        return Inertia::render('Dashboard/PatientDashboard', [
            'upcoming_appointments'      => $upcomingAppointments,
            'past_consultations_count'   => $pastConsultationsCount,
            'active_prescriptions_count' => $activePrescriptionsCount,
        ]);
    }

    private function agentDashboard(): Response
    {
        $pendingAppointmentsCount = DB::table('appointments')->where('status', 'pending')->count();
        $activeDoctorsCount = DB::table('v_doctor_directory')->count();

        $recentAppointments = DB::table('appointments')
            ->select(['id', 'patient_id', 'doctor_id', 'status', DB::raw("lower(franja) as franja_start"), DB::raw("upper(franja) as franja_end")])
            ->orderByRaw("lower(franja) DESC")
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard/AgentDashboard', [
            'pending_appointments_count' => $pendingAppointmentsCount,
            'unassigned_requests_count'  => 0,
            'active_doctors_count'       => $activeDoctorsCount,
            'recent_appointments'        => $recentAppointments,
        ]);
    }
}
