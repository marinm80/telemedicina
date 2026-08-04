<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Clinical\AddNoteAmendmentAction;
use App\Actions\Clinical\SaveConsultationNoteDraftAction;
use App\Actions\Clinical\SignConsultationNoteAction;
use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\NoteAlreadySignedException;
use App\Exceptions\NoteNotFoundException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ConsultationNoteController extends Controller
{
    /**
     * Guardar o actualizar borrador de nota SOAP (RF-15).
     */
    public function storeDraft(Request $request, string $id, SaveConsultationNoteDraftAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para redactar notas clínicas.'
            ], 401);
        }

        $validated = $request->validate([
            'symptoms'  => ['required', 'string', 'max:2000'],
            'objective' => ['required', 'string', 'max:2000'],
            'analysis'  => ['required', 'string', 'max:2000'],
            'plan'      => ['required', 'string', 'max:2000'],
        ]);

        try {
            $note = $action->handle(
                $id,
                $user->id,
                $user->role,
                $validated
            );

            return response()->json([
                'id'              => $note->id,
                'consultation_id' => $note->consultation_id,
                'symptoms'        => $note->symptoms,
                'objective'       => $note->objective,
                'analysis'        => $note->analysis,
                'plan'            => $note->plan,
                'status'          => $note->status,
                'pdf_status'      => $note->pdf_status,
                'updated_at'      => $note->updated_at?->toIso8601String(),
            ], 200);
        } catch (ConsultationNotFoundException $e) {
            return response()->json([
                'error_code' => 'CONSULTATION_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_NOTE_ACCESS',
                'message'    => $e->getMessage()
            ], 403);
        } catch (NoteAlreadySignedException $e) {
            return response()->json([
                'error_code' => 'NOTE_ALREADY_SIGNED',
                'message'    => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Firmar electrónicamente la nota SOAP (RF-16).
     */
    public function sign(Request $request, string $id, SignConsultationNoteAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para firmar notas clínicas.'
            ], 401);
        }

        try {
            $note = $action->handle(
                $id,
                $user->id,
                $user->role,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'id'                => $note->id,
                'consultation_id'   => $note->consultation_id,
                'status'            => $note->status,
                'content_hash'      => $note->content_hash,
                'signed_by'         => $note->signed_by,
                'signed_at'         => $note->signed_at?->toIso8601String(),
                'signed_ip'         => $note->signed_ip,
                'signed_user_agent' => $note->signed_user_agent,
            ], 200);
        } catch (ConsultationNotFoundException | NoteNotFoundException $e) {
            return response()->json([
                'error_code' => 'NOTE_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_NOTE_SIGN',
                'message'    => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Añadir enmienda médica a una nota firmada (RF-17).
     */
    public function addAmendment(Request $request, string $id, AddNoteAmendmentAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para emitir enmiendas.'
            ], 401);
        }

        $validated = $request->validate([
            'reason'  => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $amendment = $action->handle(
                $id,
                $user->id,
                $user->role,
                $validated['reason'],
                $validated['content']
            );

            return response()->json([
                'id'                   => $amendment->id,
                'consultation_note_id' => $amendment->consultation_note_id,
                'author_id'            => $amendment->author_id,
                'reason'               => $amendment->reason,
                'content'              => $amendment->content,
                'created_at'           => $amendment->created_at?->toIso8601String(),
            ], 201);
        } catch (ConsultationNotFoundException | NoteNotFoundException $e) {
            return response()->json([
                'error_code' => 'NOTE_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_AMENDMENT',
                'message'    => $e->getMessage()
            ], 403);
        } catch (InvalidAppointmentStatusException $e) {
            return response()->json([
                'error_code' => 'INVALID_NOTE_STATUS',
                'message'    => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Consultar nota clínica y sus enmiendas (RF-15/RF-16/RF-17).
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para consultar notas clínicas.'
            ], 401);
        }

        if ($user->role === 'agent') {
            return response()->json([
                'error_code' => 'AGENT_ACCESS_FORBIDDEN',
                'message'    => 'El agente administrativo no tiene acceso a notas clínicas.'
            ], 403);
        }

        $consultation = Consultation::with('appointment')->find($id);
        if (!$consultation) {
            $consultation = Consultation::where('appointment_id', $id)->first();
        }

        if (!$consultation) {
            return response()->json([
                'error_code' => 'CONSULTATION_NOT_FOUND',
                'message'    => 'Consulta médica no encontrada.'
            ], 404);
        }

        $note = ConsultationNote::with('amendments')->where('consultation_id', $consultation->id)->first();
        if (!$note) {
            return response()->json([
                'error_code' => 'NOTE_NOT_FOUND',
                'message'    => 'No existe nota clínica para esta consulta.'
            ], 404);
        }

        // Si el usuario es el paciente y la nota está en borrador (draft), es confidencial (404/403)
        $appointment = $consultation->appointment;
        if ($appointment && $appointment->patient_id === $user->id && $note->status !== 'signed') {
            return response()->json([
                'error_code' => 'NOTE_NOT_FOUND',
                'message'    => 'No existe nota clínica disponible para esta consulta.'
            ], 404);
        }

        return response()->json([
            'id'              => $note->id,
            'consultation_id' => $note->consultation_id,
            'symptoms'        => $note->symptoms,
            'objective'       => $note->objective,
            'analysis'        => $note->analysis,
            'plan'            => $note->plan,
            'status'          => $note->status,
            'content_hash'    => $note->content_hash,
            'signed_by'       => $note->signed_by,
            'signed_at'       => $note->signed_at?->toIso8601String(),
            'amendments'      => $note->amendments->map(fn ($a) => [
                'id'         => $a->id,
                'author_id'  => $a->author_id,
                'reason'     => $a->reason,
                'content'    => $a->content,
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ], 200);
    }

    /**
     * Descargar o consultar el PDF de la nota clínica (RF-18).
     */
    public function downloadPdf(string $id): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para descargar el PDF clínico.'
            ], 401);
        }

        if ($user->role === 'agent') {
            return response()->json([
                'error_code' => 'AGENT_ACCESS_FORBIDDEN',
                'message'    => 'El agente administrativo no tiene acceso a documentos clínicos.'
            ], 403);
        }

        $consultation = Consultation::with('appointment')->find($id);
        if (!$consultation) {
            $consultation = Consultation::where('appointment_id', $id)->first();
        }

        if (!$consultation) {
            return response()->json([
                'error_code' => 'CONSULTATION_NOT_FOUND',
                'message'    => 'Consulta médica no encontrada.'
            ], 404);
        }

        $appointment = $consultation->appointment;
        if ($appointment) {
            $isPatient = ($appointment->patient_id === $user->id);
            $isDoctor  = ($appointment->doctor_id === $user->id);
            $isAdmin   = ($user->role === 'admin');

            if (!$isPatient && !$isDoctor && !$isAdmin) {
                return response()->json([
                    'error_code' => 'UNAUTHORIZED_PDF_ACCESS',
                    'message'    => 'No tiene permisos para acceder al PDF de esta consulta.'
                ], 404);
            }
        }

        $note = ConsultationNote::where('consultation_id', $consultation->id)->first();
        if (!$note || $note->status !== 'signed') {
            return response()->json([
                'error_code' => 'PDF_NOT_READY',
                'message'    => 'El PDF aún no está listo o la nota no ha sido firmada.'
            ], 404);
        }

        if ($note->pdf_status !== 'pdf_ready' || !$note->pdf_path) {
            return response()->json([
                'error_code' => 'PDF_PENDING',
                'message'    => 'El PDF se encuentra en proceso de generación asíncrona.'
            ], 425);
        }

        $fullPath = storage_path('app/' . $note->pdf_path);
        if (!file_exists($fullPath)) {
            return response()->json([
                'error_code' => 'PDF_FILE_NOT_FOUND',
                'message'    => 'Archivo PDF no encontrado en el almacenamiento.'
            ], 404);
        }

        return response()->download($fullPath, 'nota_clinica_' . $consultation->id . '.pdf');
    }

    /**
     * Firmar acuse de recibo de la nota clínica por parte del paciente (RF-19).
     */
    public function acknowledge(Request $request, string $id, \App\Actions\Clinical\AcknowledgeConsultationNoteAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error_code' => 'UNAUTHENTICATED',
                'message'    => 'Debe iniciar sesión para firmar el acuse de recibo.'
            ], 401);
        }

        try {
            $note = $action->handle(
                $id,
                $user->id,
                $user->role
            );

            return response()->json([
                'id'              => $note->id,
                'consultation_id' => $note->consultation_id,
                'status'          => $note->status,
                'acknowledged_at' => $note->acknowledged_at?->toIso8601String(),
            ], 200);
        } catch (ConsultationNotFoundException | NoteNotFoundException $e) {
            return response()->json([
                'error_code' => 'NOTE_NOT_FOUND',
                'message'    => $e->getMessage()
            ], 404);
        } catch (UnauthorizedCancellationException $e) {
            return response()->json([
                'error_code' => 'UNAUTHORIZED_ACKNOWLEDGED',
                'message'    => $e->getMessage()
            ], 403);
        } catch (InvalidAppointmentStatusException $e) {
            return response()->json([
                'error_code' => 'INVALID_NOTE_STATUS',
                'message'    => $e->getMessage()
            ], 422);
        }
    }
}
