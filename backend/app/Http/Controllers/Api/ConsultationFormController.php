<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ConsultationFormController extends Controller
{
    /**
     * Save or update consultation form data (draft).
     */
    public function store(Request $request, string $consultationId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = DB::connection('pgsql_admin');
        $consultation = $db->table('consultations')->where('id', $consultationId)->first();
        if (!$consultation) return response()->json(['message' => 'Consulta no encontrada.'], 404);

        // Check the doctor owns this consultation
        $appointment = $db->table('appointments')->where('id', $consultation->appointment_id)->first();
        if (!$appointment || $appointment->doctor_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'motivo_consulta' => ['nullable', 'string'],
            'sintomas' => ['nullable', 'string'],
            'historial_medico' => ['nullable', 'string'],
            'medicinas_actuales' => ['nullable', 'array'],
            'medicinas_actuales.*.nombre' => ['required', 'string'],
            'medicinas_actuales.*.dosis' => ['nullable', 'string'],
            'medicinas_actuales.*.frecuencia' => ['nullable', 'string'],
            'examen_fisico' => ['nullable', 'string'],
            'diagnostico' => ['nullable', 'string'],
            'plan_tratamiento' => ['nullable', 'string'],
        ]);

        // Start consultation if not started
        if (!$consultation->started_at) {
            $db->table('consultations')->where('id', $consultationId)->update([
                'started_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Check if consultation_notes exists
        $existingNote = $db->table('consultation_notes')->where('consultation_id', $consultationId)->first();

        $noteData = [
            'subjective' => json_encode([
                'motivo_consulta' => $validated['motivo_consulta'] ?? '',
                'sintomas' => $validated['sintomas'] ?? '',
                'historial_medico' => $validated['historial_medico'] ?? '',
                'medicinas_actuales' => $validated['medicinas_actuales'] ?? [],
            ]),
            'objective' => $validated['examen_fisico'] ?? '',
            'assessment' => $validated['diagnostico'] ?? '',
            'plan' => $validated['plan_tratamiento'] ?? '',
            'updated_at' => now(),
        ];

        if ($existingNote) {
            $db->table('consultation_notes')->where('id', $existingNote->id)->update($noteData);
        } else {
            $db->table('consultation_notes')->insert(array_merge($noteData, [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'consultation_id' => $consultationId,
                'author_id' => $user->id,
                'status' => 'draft',
                'created_at' => now(),
            ]));
        }

        return response()->json(['message' => 'Borrador guardado.'], 200);
    }

    /**
     * Archive consultation (sign, complete appointment, optionally schedule follow-up).
     */
    public function archive(Request $request, string $consultationId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = DB::connection('pgsql_admin');
        $consultation = $db->table('consultations')->where('id', $consultationId)->first();
        if (!$consultation) return response()->json(['message' => 'Consulta no encontrada.'], 404);

        $appointment = $db->table('appointments')->where('id', $consultation->appointment_id)->first();
        if (!$appointment || $appointment->doctor_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Save form data first (same as store)
        $this->store($request, $consultationId);

        // Mark consultation as ended
        $db->table('consultations')->where('id', $consultationId)->update([
            'ended_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark appointment as completed
        $db->table('appointments')->where('id', $consultation->appointment_id)->update([
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        // Sign the note
        $db->table('consultation_notes')->where('consultation_id', $consultationId)->update([
            'status' => 'signed',
            'signed_at' => now(),
            'signer_id' => $user->id,
            'updated_at' => now(),
        ]);

        // Schedule follow-up if requested
        $followUp = $request->input('follow_up');
        if ($followUp && isset($followUp['enabled']) && $followUp['enabled']) {
            $weeks = (int) ($followUp['weeks'] ?? 4);
            $followUpDate = now()->addWeeks($weeks);
            // Find the day_of_week for the follow-up date
            $dayOfWeek = (int) $followUpDate->format('w'); // 0=Sun, 6=Sat

            // Get doctor profile
            $profile = $db->table('doctor_profiles')->where('user_id', $user->id)->first();
            if ($profile) {
                // Find a schedule for that day
                $schedule = $db->table('schedules')
                    ->where('doctor_profile_id', $profile->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->whereNull('deleted_at')
                    ->first();

                if ($schedule) {
                    $franja = trim($schedule->franja, '[]()'); 
                    $parts = explode(',', $franja);
                    $startTime = trim($parts[0]);
                    // Use the first slot of the day
                    $franjaStart = $followUpDate->format('Y-m-d') . ' ' . $startTime;
                    $slotDuration = $schedule->slot_duration ?? 30;
                    $franjaEnd = $followUpDate->copy()->setTimeFromTimeString($startTime)->addMinutes($slotDuration)->format('Y-m-d H:i:s');

                    $franjaRange = sprintf('[%s, %s)', $franjaStart, $franjaEnd);

                    try {
                        $db->table('appointments')->insert([
                            'id' => \Illuminate\Support\Str::uuid()->toString(),
                            'patient_id' => $appointment->patient_id,
                            'doctor_id' => $appointment->doctor_id,
                            'franja' => $franjaRange,
                            'status' => 'confirmed',
                            'rescheduled_from' => $appointment->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        // Silently fail if slot is taken — follow-up is best-effort
                    }
                }
            }
        }

        return response()->json(['message' => 'Consulta archivada exitosamente.'], 200);
    }

    /**
     * Get consultation form data for display.
     */
    public function show(string $consultationId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = DB::connection('pgsql_admin');
        $consultation = $db->table('consultations')->where('id', $consultationId)->first();
        if (!$consultation) return response()->json(['message' => 'Consulta no encontrada.'], 404);

        $note = $db->table('consultation_notes')->where('consultation_id', $consultationId)->first();

        $formData = [];
        if ($note) {
            $subjective = json_decode($note->subjective ?? '{}', true) ?: [];
            $formData = [
                'motivo_consulta' => $subjective['motivo_consulta'] ?? '',
                'sintomas' => $subjective['sintomas'] ?? '',
                'historial_medico' => $subjective['historial_medico'] ?? '',
                'medicinas_actuales' => $subjective['medicinas_actuales'] ?? [],
                'examen_fisico' => $note->objective ?? '',
                'diagnostico' => $note->assessment ?? '',
                'plan_tratamiento' => $note->plan ?? '',
                'status' => $note->status,
            ];
        }

        return response()->json(['data' => $formData], 200);
    }
}
