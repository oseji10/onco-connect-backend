<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\EventPassGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class EventPassController extends Controller
{
    public function __construct(
        protected EventPassGeneratorService $eventPassGeneratorService
    ) {
    }

    public function index(Request $request, Event $event): JsonResponse
    {
        $query = $event->passes()->latest('passId');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('passCode', 'like', "%{$search}%")
                    ->orWhere('serialNumber', 'like', "%{$search}%");
            });
        }

        $passes = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $passes,
        ]);
    }

    public function generate(Request $request, Event $event): JsonResponse
    {
       $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // if ($event->passes()->exists()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Passes have already been generated for this event.',
        //     ], 422);
        // }

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50000'],
        ]);

        $this->eventPassGeneratorService->generate($event, $validated['count']);

        $event->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Event passes generated successfully.',
            'data' => [
                'eventId' => $event->eventId,
                'passCount' => $event->passCount,
            ],
        ], 201);
    }

    public function show(EventPass $pass): JsonResponse
    {
        $pass->load(['event']);

        return response()->json([
            'success' => true,
            'data' => $pass,
        ]);
    }

    public function void(Request $request, EventPass $pass): JsonResponse
    {
       $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $pass->update([
            'status' => 'void',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pass voided successfully.',
            'data' => $pass->fresh(),
        ]);
    }

    public function qr(EventPass $pass): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'passId' => $pass->passId,
                'serialNumber' => $pass->serialNumber,
                'passCode' => $pass->passCode,
                'qrUrl' => $pass->qrUrl,
                'qrPath' => $pass->qrPath,
            ],
        ]);
    }

    public function downloadQr(EventPass $pass)
    {
        if (!$pass->qrPath || !Storage::disk('public')->exists($pass->qrPath)) {
            return response()->json([
                'success' => false,
                'message' => 'QR file not found.',
            ], 404);
        }

        $absolutePath = Storage::disk('public')->path($pass->qrPath);
        $fileName = ($pass->serialNumber ?: $pass->passCode) . '.png';

        return response()->download($absolutePath, $fileName);
    }


public function downloadPdf(Event $event)
{
    set_time_limit(520);
    ini_set('max_execution_time', 120);
    ini_set('memory_limit', '512M');

    $passes = $event->passes()->orderBy('passId')->get();

    if ($passes->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No passes found for this event.',
        ], 404);
    }

    $rows = $passes->chunk(5); // 5 per row

    $pdf = Pdf::loadView('pdf.event-passes', [
        'event' => $event,
        'passes' => $passes,
        'rows' => $rows,
    ])->setPaper('a4', 'portrait');

    return $pdf->download("event-{$event->eventId}-passes.pdf");
}
}