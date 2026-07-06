<?php

namespace App\Services;

use App\Models\EventPass;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function generateForEventPass(EventPass $pass): array
    {
        $event = $pass->event;
        $folder = "qrcodes/events/{$event->eventId}";
        $fileName = "{$pass->passCode}.png";
        $relativePath = "{$folder}/{$fileName}";

        $payload = $pass->passCode;

        $png = QrCode::format('png')
            ->size(400)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($payload);

        Storage::disk('public')->put($relativePath, $png);

        $publicUrl = Storage::disk('public')->url($relativePath);

        $pass->update([
            'qrPayload' => $payload,
            'qrPath' => $relativePath,
            'qrUrl' => $relativePath,
        ]);

        return [
            'qrPath' => $relativePath,
            'qrUrl' => $relativePath,
            'qrPayload' => $payload,
        ];
    }
}


