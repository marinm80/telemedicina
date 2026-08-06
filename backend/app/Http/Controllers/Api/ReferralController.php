<?php
/**
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 */
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ReferralController extends Controller
{
    /**
     * Create a referral
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $validated = $request->validate([
            'consultation_id' => ['required', 'uuid'],
            'specialty_name' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3'],
            'priority' => ['required', 'string', Rule::in(['normal', 'urgente'])],
            'referred_doctor_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
        ]);

        $db = DB::connection('pgsql_admin');
        
        $consultation = $db->table('consultations')->where('id', $validated['consultation_id'])->first();
        if (!$consultation) return response()->json(['message' => 'Consulta no encontrada.'], 404);

        $appointment = $db->table('appointments')->where('id', $consultation->appointment_id)->first();
        if (!$appointment || $appointment->doctor_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Resolve specialty_id from catalog
        $specialty = $db->table('specialties')
            ->whereRaw('LOWER(name) = LOWER(?)', [$validated['specialty_name']])
            ->first();

        $referral = Referral::create([
            'consultation_id' => $validated['consultation_id'],
            'referring_doctor_id' => $user->id,
            'patient_id' => $appointment->patient_id,
            'specialty_id' => $specialty?->id,
            'specialty_name' => $validated['specialty_name'],
            'reason' => $validated['reason'],
            'priority' => $validated['priority'],
            'referred_doctor_id' => $validated['referred_doctor_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $referral->load('specialty')], 201);
    }

    /**
     * List referrals
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $query = Referral::with(['consultation', 'referringDoctor', 'patient', 'referredDoctor', 'specialty']);

        if ($user->role === 'admin') {
            $query->setConnection('pgsql_admin');
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        if ($request->has('consultation_id')) {
            $query->where('consultation_id', $request->input('consultation_id'));
        }

        $referrals = $query->get();

        return response()->json(['data' => $referrals], 200);
    }

    /**
     * Update referral status
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'accepted', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        $query = Referral::query();
        if ($user->role === 'admin') {
            $query->setConnection('pgsql_admin');
        }

        $referral = $query->find($id);

        if (!$referral) {
            return response()->json(['message' => 'Referencia no encontrada.'], 404);
        }

        if ($user->role !== 'admin' && $referral->referring_doctor_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $referral->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $referral->notes,
        ]);

        return response()->json(['data' => $referral], 200);
    }
}
