<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateMealTicketsRequest;
use App\Models\Meal;
use App\Models\MealTicket;
use App\Services\TicketGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;


class MealTicketController extends Controller
{
    public function __construct(
        protected TicketGeneratorService $ticketGeneratorService
    ) {
    }

    public function generate(GenerateMealTicketsRequest $request, Meal $meal): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($meal->tickets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tickets have already been generated for this meal.',
            ], 422);
        }

        $count = (int) $request->input('count', $meal->ticketCount ?: 0);

        if ($count < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket count must be greater than zero.',
            ], 422);
        }

        $this->ticketGeneratorService->generate($meal, $count);

        $meal->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Tickets and QR codes generated successfully.',
            'data' => [
                'mealId' => $meal->id,
                'ticketCount' => $meal->tickets()->count(),
            ],
        ], 201);
    }

    public function index(Request $request, Meal $meal): JsonResponse
    {
        $query = $meal->tickets()->latest('mealTicketId');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('token', 'like', "%{$search}%")
                  ->orWhere('serialNumber', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    public function show(MealTicket $ticket): JsonResponse
    {
        $ticket->load(['meal', 'redeemer']);

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    public function void(Request $request, MealTicket $ticket): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($ticket->status === 'redeemed') {
            return response()->json([
                'success' => false,
                'message' => 'Redeemed tickets cannot be voided.',
            ], 422);
        }

        $ticket->update([
            'status' => 'void',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket voided successfully.',
            'data' => $ticket->fresh(),
        ]);
    }



public function downloadZip(Meal $meal)
{
    $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
    $tickets = $meal->tickets()->get();

    if ($tickets->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No tickets found for this meal.',
        ], 404);
    }

    $zipFileName = 'meal-' . $meal->mealId . '-tickets.zip';
    $zipPath = storage_path('app/temp/' . $zipFileName);

    if (!file_exists(storage_path('app/temp'))) {
        mkdir(storage_path('app/temp'), 0777, true);
    }

    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to create ZIP file.',
        ], 500);
    }

    foreach ($tickets as $ticket) {
        if ($ticket->qrPath && Storage::disk('public')->exists($ticket->qrPath)) {
            $absolutePath = Storage::disk('public')->path($ticket->qrPath);
            $entryName = ($ticket->serial_number ?: $ticket->token) . '.png';
            $zip->addFile($absolutePath, $entryName);
        }
    }

    $zip->close();

    return response()->download($zipPath)->deleteFileAfterSend(true);
}


public function downloadPdf(Meal $meal)
{
    $tickets = $meal->tickets()->get();

    if ($tickets->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No tickets found for this meal.',
        ], 404);
    }

    $pdf = Pdf::loadView('pdf.meal-tickets', [
        'meal' => $meal,
        'tickets' => $tickets,
    ])->setPaper('a4', 'portrait');

    return $pdf->download("meal-{$meal->mealId}-tickets.pdf");
}

}