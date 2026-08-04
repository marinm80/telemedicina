<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\ConsultationNote;
use App\Services\PdfGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class GenerateClinicalNotePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $consultationNoteId
    ) {}

    public function handle(PdfGeneratorService $pdfService): void
    {
        DB::statement("SET app.current_user_role = 'admin'");

        $note = ConsultationNote::with(['consultation.appointment', 'amendments'])->find($this->consultationNoteId);
        if (!$note || $note->status !== 'signed') {
            return;
        }

        $pdfContent = $pdfService->generatePdfContent($note);

        $dir = storage_path('app/private/pdfs');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $relativePath = 'private/pdfs/note_' . $note->id . '.pdf';
        $fullPath     = storage_path('app/' . $relativePath);

        File::put($fullPath, $pdfContent);

        $note->update([
            'pdf_status' => 'pdf_ready',
            'pdf_path'   => $relativePath,
        ]);
    }
}
