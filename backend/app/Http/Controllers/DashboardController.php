<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * @author AI Assistant
 */
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
        $data = [
            'total_users'                => 0,
            'pending_doctor_approvals'   => 0,
            'monthly_appointments_count' => 0,
            'total_revenue'              => 0.0,
            'pending_doctors'            => [],
            'chart_appointments_by_day'  => [],
            'recent_activity'            => [],
        ];

        try {
            $data['total_users'] = DB::table('users')->count();
            $data['pending_doctor_approvals'] = DB::table('doctor_profiles')->where('status', 'pending')->count();
            $data['monthly_appointments_count'] = DB::table('appointments')->where('created_at', '>=', now()->startOfMonth())->count();
            $data['total_revenue'] = (float) DB::table('payments')->where('status', 'completed')->sum('amount');

            $data['pending_doctors'] = DB::table('doctor_profiles')
                ->join('users', 'doctor_profiles.user_id', '=', 'users.id')
                ->leftJoin('specialties', 'doctor_profiles.specialty_id', '=', 'specialties.id')
                ->where('doctor_profiles.status', 'pending')
                ->select([
                    'users.id',
                    'users.name',
                    'users.last_name',
                    'doctor_profiles.specialty_id',
                    'specialties.name as specialty_name',
                    'doctor_profiles.license_number',
                    'doctor_profiles.status',
                    'doctor_profiles.created_at'
                ])
                ->get();

            $chartData = DB::table('appointments')
                ->select(DB::raw("date_trunc('day', lower(franja)) as day"), DB::raw("count(*) as count"))
                ->whereRaw("lower(franja) >= now() - interval '7 days'")
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $data['chart_appointments_by_day'] = $chartData->map(function ($item) {
                return [
                    'day' => \Carbon\Carbon::parse($item->day)->format('D'),
                    'count' => $item->count,
                ];
            });

        } catch (Throwable $e) {
            // Ignorar errores de tablas faltantes
        }

        return Inertia::render('Dashboard/AdminDashboard', $data);
    }

    private function doctorDashboard($user): Response
    {
        $data = [
            'profile_status'             => 'pending',
            'today_appointments'         => [],
            'active_patients_count'      => 0,
            'chart_consultations_by_day' => [],
            'pending_tasks'              => [],
            'month_earnings'             => 0.0,
        ];

        try {
            $profile = DB::table('doctor_profiles')->where('user_id', $user->id)->first();
            $data['profile_status'] = $profile?->status ?? 'pending';

            $appointmentsQuery = DB::table('appointments')
                ->join('patient_profiles', 'appointments.patient_id', '=', 'patient_profiles.id')
                ->join('users', 'patient_profiles.user_id', '=', 'users.id')
                ->whereDate(DB::raw("lower(appointments.franja)"), now()->toDateString());
            
            try {
                // Intentar con reason
                $data['today_appointments'] = (clone $appointmentsQuery)
                    ->select([
                        'appointments.id',
                        'appointments.status',
                        'appointments.reason',
                        DB::raw("lower(appointments.franja) as franja_start"),
                        DB::raw("upper(appointments.franja) as franja_end"),
                        'users.name as patient_name',
                        'users.last_name as patient_last_name'
                    ])
                    ->get();
            } catch (Throwable $e) {
                // Sin reason
                $data['today_appointments'] = (clone $appointmentsQuery)
                    ->select([
                        'appointments.id',
                        'appointments.status',
                        DB::raw("lower(appointments.franja) as franja_start"),
                        DB::raw("upper(appointments.franja) as franja_end"),
                        'users.name as patient_name',
                        'users.last_name as patient_last_name'
                    ])
                    ->get();
            }

            $data['active_patients_count'] = DB::table('appointments')
                ->distinct('patient_id')
                ->where('status', '!=', 'cancelled')
                ->count('patient_id');

            $chartData = DB::table('appointments')
                ->select(DB::raw("date_trunc('day', lower(franja)) as day"), DB::raw("count(*) as count"))
                ->whereRaw("lower(franja) >= date_trunc('week', now())") // Lunes a domingo de la semana actual
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $data['chart_consultations_by_day'] = $chartData->map(function ($item) {
                return [
                    'day' => \Carbon\Carbon::parse($item->day)->format('D'),
                    'count' => $item->count,
                ];
            });

            $data['pending_tasks'] = DB::table('consultation_notes')
                ->where('status', 'draft')
                ->get();

            $data['month_earnings'] = (float) DB::table('commissions')
                ->where('status', 'released')
                ->sum('doctor_earning');

        } catch (Throwable $e) {
            // Ignorar errores
        }

        return Inertia::render('Dashboard/DoctorDashboard', $data);
    }

    private function patientDashboard($user): Response
    {
        $data = [
            'upcoming_appointments'        => [],
            'active_prescriptions'         => [],
            'chart_consultations_by_month' => [],
            'past_consultations_count'     => 0,
            'active_prescriptions_count'   => 0,
        ];

        try {
            $data['upcoming_appointments'] = DB::table('appointments')
                ->join('doctor_profiles', 'appointments.doctor_id', '=', 'doctor_profiles.id')
                ->join('users', 'doctor_profiles.user_id', '=', 'users.id')
                ->leftJoin('specialties', 'doctor_profiles.specialty_id', '=', 'specialties.id')
                ->select([
                    'appointments.id',
                    'appointments.status',
                    DB::raw("lower(appointments.franja) as franja_start"),
                    DB::raw("upper(appointments.franja) as franja_end"),
                    'users.name as doctor_name',
                    'users.last_name as doctor_last_name',
                    'specialties.name as specialty_name'
                ])
                ->whereRaw("upper(appointments.franja) >= ?", [now()->toIso8601String()])
                ->orderByRaw("lower(appointments.franja) ASC")
                ->get();

            $data['active_prescriptions'] = DB::table('patient_medications')->get();
            $data['active_prescriptions_count'] = DB::table('patient_medications')->count();
            $data['past_consultations_count'] = DB::table('consultations')->count();

            $chartData = DB::table('appointments')
                ->select(DB::raw("date_trunc('month', lower(franja)) as month"), DB::raw("count(*) as count"))
                ->whereRaw("lower(franja) >= now() - interval '7 months'")
                ->groupBy('month')
                ->orderBy('month')
                ->get();
            
            $data['chart_consultations_by_month'] = $chartData->map(function ($item) {
                return [
                    'month' => \Carbon\Carbon::parse($item->month)->format('M'),
                    'count' => $item->count,
                ];
            });

        } catch (Throwable $e) {
            // Ignore
        }

        return Inertia::render('Dashboard/PatientDashboard', $data);
    }

    private function agentDashboard(): Response
    {
        $data = [
            'pending_appointments_count' => 0,
            'unassigned_requests_count'  => 0,
            'active_doctors_count'       => 0,
            'recent_appointments'        => [],
            'today_appointments_count'   => 0,
        ];

        try {
            $data['pending_appointments_count'] = DB::table('appointments')->where('status', 'pending')->count();
            $data['active_doctors_count'] = DB::table('v_doctor_directory')->count();
            $data['today_appointments_count'] = DB::table('appointments')
                ->whereDate(DB::raw("lower(franja)"), now()->toDateString())
                ->count();

            $data['recent_appointments'] = DB::table('appointments')
                ->join('patient_profiles', 'appointments.patient_id', '=', 'patient_profiles.id')
                ->join('users as patient_users', 'patient_profiles.user_id', '=', 'patient_users.id')
                ->join('doctor_profiles', 'appointments.doctor_id', '=', 'doctor_profiles.id')
                ->join('users as doctor_users', 'doctor_profiles.user_id', '=', 'doctor_users.id')
                ->select([
                    'appointments.id',
                    'appointments.status',
                    DB::raw("lower(appointments.franja) as franja_start"),
                    DB::raw("upper(appointments.franja) as franja_end"),
                    'patient_users.name as patient_name',
                    'patient_users.last_name as patient_last_name',
                    'doctor_users.name as doctor_name',
                    'doctor_users.last_name as doctor_last_name',
                ])
                ->orderByRaw("lower(appointments.franja) DESC")
                ->limit(10)
                ->get();

        } catch (Throwable $e) {
            // Ignore
        }

        return Inertia::render('Dashboard/AgentDashboard', $data);
    }
}
