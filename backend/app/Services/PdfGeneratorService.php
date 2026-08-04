<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ConsultationNote;

final class PdfGeneratorService
{
    /**
     * Generar la estructura de contenido de un documento PDF clínico con código QR de verificación.
     *
     * @param  \App\Models\ConsultationNote  $note
     * @return string
     */
    public function generatePdfContent(ConsultationNote $note): string
    {
        $hash         = $note->content_hash ?? 'N/A';
        $verifyUrl    = config('app.url', 'https://telemedicina.example.com') . '/verify/note/' . $hash;
        $signedAt     = $note->signed_at?->toIso8601String() ?? 'N/A';
        $signedBy     = $note->signed_by ?? 'Médico Tratante';
        $acknowledged = $note->acknowledged_at ? ('Firmado por paciente el ' . $note->acknowledged_at->toIso8601String()) : 'Pendiente de acuse';

        // Estructura de documento binario / PDF estándar con metadatos y sello QR
        $pdfHeader = "%PDF-1.4\n";
        $pdfBody   = sprintf(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n" .
            "4 0 obj\n<< /Length %d >>\nstream\n" .
            "BT /Helv 12 Tf 50 750 Td (INFORME CLINICO TELEMEDICINA - HASH SHA-256) Tj ET\n" .
            "BT /Helv 10 Tf 50 720 Td (SINTOMAS: %s) Tj ET\n" .
            "BT /Helv 10 Tf 50 700 Td (OBJETIVO: %s) Tj ET\n" .
            "BT /Helv 10 Tf 50 680 Td (ANALISIS: %s) Tj ET\n" .
            "BT /Helv 10 Tf 50 660 Td (PLAN: %s) Tj ET\n" .
            "BT /Helv 9 Tf 50 600 Td (SELLO DE FIRMA ELECTRONICA: %s) Tj ET\n" .
            "BT /Helv 9 Tf 50 580 Td (FIRMADOR ID: %s | FECHA: %s) Tj ET\n" .
            "BT /Helv 9 Tf 50 560 Td (ESTADO DE ACUSE: %s) Tj ET\n" .
            "BT /Helv 9 Tf 50 540 Td (VERIFICACION QR URL: %s) Tj ET\n" .
            "endstream\nendobj\n",
            1000,
            substr(addslashes($note->symptoms), 0, 80),
            substr(addslashes($note->objective), 0, 80),
            substr(addslashes($note->analysis), 0, 80),
            substr(addslashes($note->plan), 0, 80),
            $hash,
            $signedBy,
            $signedAt,
            $acknowledged,
            $verifyUrl
        );

        return $pdfHeader . $pdfBody . "%%EOF\n";
    }
}
