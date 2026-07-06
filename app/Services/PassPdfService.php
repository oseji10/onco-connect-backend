<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\EventPass;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PassPdfService
{
    public function generate(Attendee $attendee, EventPass $pass): string
    {
        $event = $pass->event;

        // Build absolute path to the QR code image and base64-encode it
        // so DOMPDF can embed it without filesystem path issues
        $qrAbsPath = Storage::disk('public')->path($pass->qrPath);
        $qrBase64  = base64_encode(file_get_contents($qrAbsPath));
        $qrDataUri = 'data:image/png;base64,' . $qrBase64;

        $categoryLabel = $this->formatCategory($attendee->category);
        $fullName      = trim("{$attendee->title} {$attendee->firstName} {$attendee->lastName}");

        $pdf = Pdf::loadView('pdf.event-pass', [
            'event'         => $event,
            'pass'          => $pass,
            'attendee'      => $attendee,
            'fullName'      => $fullName,
            'categoryLabel' => $categoryLabel,
            'qrDataUri'     => $qrDataUri,
        ])->setPaper([0, 0, 255, 405], 'portrait'); // ~90mm × 143mm in points

        // $folder   = "qr_code/icw/2026/{$event->eventId}";
        // $fileName = "{$pass->passCode}.pdf";
        // $path     = "{$folder}/{$fileName}";

        // Storage::disk('public')->put($path, $pdf->output());

        // // Persist PDF path on the pass record
        // $pass->update(['pdfPath' => $path]);

        // return $path;
        // $pdf = Pdf::loadView('pdfs.event-pass', [...])
        // ->setPaper([0, 0, 255, 405], 'portrait');

    return $pdf->output();
    }

    private function formatCategory(string $category): string
    {
        return match ($category) {
            'healthcare_professional' => 'Healthcare Professional',
            'cancer_survivor'         => 'Cancer Survivor',
            'development_partner'     => 'Development Partner',
            'student'                 => 'Student',
            'researcher'              => 'Researcher',
            'general_public'          => 'General Public',
            'government_official'     => 'Government Official',
            default                   => 'Participant',
        };
    }
}