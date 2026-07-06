<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealTicket;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TicketQrController extends Controller
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {
    }

    public function show(MealTicket $ticket): JsonResponse
    {
        if (!$ticket->qrPath || !Storage::disk('public')->exists($ticket->qrPath)) {
            $this->qrCodeService->generateForTicket($ticket);
            $ticket->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ticketId' => $ticket->id,
                'serialNumber' => $ticket->serial_number,
                'token' => $ticket->token,
                'qrUrl' => $ticket->qrUrl,
                'qrPath' => $ticket->qrPath,
            ],
        ]);
    }

    public function regenerate(MealTicket $ticket): JsonResponse
    {
        $result = $this->qrCodeService->regenerateForTicket($ticket);

        return response()->json([
            'success' => true,
            'message' => 'QR code regenerated successfully.',
            'data' => [
                'ticketId' => $ticket->id,
                'qrUrl' => $result['qrUrl'],
                'qrPath' => $result['qrPath'],
            ],
        ]);
    }

    public function download(MealTicket $ticket)
    {
        if (!$ticket->qrPath || !Storage::disk('public')->exists($ticket->qrPath)) {
            $this->qrCodeService->generateForTicket($ticket);
            $ticket->refresh();
        }

        $absolutePath = Storage::disk('public')->path($ticket->qrPath);
        $fileName = ($ticket->serialNumber ?: $ticket->token) . '.png';

        return response()->download($absolutePath, $fileName);
    }
}