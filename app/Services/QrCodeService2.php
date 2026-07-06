<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealTicket;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function generateForTicket(MealTicket $ticket): array
    {
        $meal = $ticket->meal ?? Meal::findOrFail($ticket->mealId);

        $folder = "qrcodes/meals/{$meal->mealId}";
        $fileName = "{$ticket->token}.png";
        $relativePath = "{$folder}/{$fileName}";

        // Keep payload simple and secure.
        // Scanner should submit only the token to backend.
        $payload = $ticket->token;

        // $png = QrCode::format('png')
        //     ->size(400)
        //     ->margin(1)
        //     ->generate($payload);
        $png = QrCode::format('png')
    ->size(400)
    ->margin(1)
    ->errorCorrection('H')
    ->generate($payload);

        Storage::disk('public')->put($relativePath, $png);

        $publicUrl = Storage::disk('public')->url($relativePath);

        $ticket->update([
            'qr_payload' => $payload,
            'qrPath' => $relativePath,
            'qrUrl' => $relativePath,
        ]);

        return [
            'qrPath' => $relativePath,
            'qrUrl' => $publicUrl,
            'qrPayload' => $payload,
        ];
    }

    public function regenerateForTicket(MealTicket $ticket): array
    {
        if ($ticket->qrPath && Storage::disk('public')->exists($ticket->qrPath)) {
            Storage::disk('public')->delete($ticket->qrPath);
        }

        return $this->generateForTicket($ticket->fresh());
    }
}