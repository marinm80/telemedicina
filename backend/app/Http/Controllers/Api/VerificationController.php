<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultationNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class VerificationController extends Controller
{
    /**
     * Verificación pública de autenticidad de nota clínica mediante hash SHA-256 (RF-18).
     */
    public function verifyNote(string $hash): JsonResponse
    {
        // Contexto de lectura pública del sistema para verificación RLS
        DB::statement("SET app.current_user_role = 'admin'");

        $note = ConsultationNote::with('amendments')
            ->where('content_hash', $hash)
            ->where('status', 'signed')
            ->first();

        if (!$note) {
            return response()->json([
                'valid'      => false,
                'error_code' => 'INVALID_CLINICAL_HASH',
                'message'    => 'No existe una nota clínica firmada válida que corresponda con este hash SHA-256.'
            ], 404);
        }

        return response()->json([
            'valid'            => true,
            'content_hash'     => $note->content_hash,
            'status'           => $note->status,
            'signed_by'        => $note->signed_by,
            'signed_at'        => $note->signed_at?->toIso8601String(),
            'acknowledged_at'  => $note->acknowledged_at?->toIso8601String(),
            'amendments_count' => $note->amendments->count(),
            'verified_at'      => now()->toIso8601String(),
        ], 200);
    }
}
