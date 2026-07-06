<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RedeemScanRequest;
use App\Http\Requests\ValidateScanRequest;
use App\Models\MealTicket;
use App\Models\ScanLog;
use App\Services\MealRedemptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ScannerController extends Controller
{
    public function __construct(
        protected MealRedemptionService $mealRedemptionService
    ) {
    }

    public function validateTicket(ValidateScanRequest $request): JsonResponse
    {
        $scanner = $request->user();

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $token = trim($request->token);

        $ticket = MealTicket::query()
            ->with('meal')
            ->where('token', $token)
            ->first();

        if (!$ticket) {
            ScanLog::create([
                'token' => $token,
                'scanResult' => 'invalid',
                'message' => 'Ticket not found during validation',
                'scannedBy' => $scanner->id,
                'deviceName' => $request->deviceName,
                'ipAddress' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid ticket.',
                'data' => [
                    'status' => 'invalid',
                ],
            ], 404);
        }

        $meal = $ticket->meal;
        $now = now();
        $mealStart = Carbon::parse($meal->mealDate . ' ' . $meal->startTime);
        $mealEnd = Carbon::parse($meal->mealDate . ' ' . $meal->endTime);

        if ($meal->status !== 'active' || $now->lt($mealStart) || $now->gt($mealEnd)) {
            ScanLog::create([
                'mealTicketId' => $ticket->id,
                'mealId' => $meal->mealId,
                'token' => $token,
                'scanResult' => 'outside_window',
                'message' => 'Meal is not redeemable at this time',
                'scannedBy' => $scanner->id,
                'deviceName' => $request->deviceName,
                'ipAddress' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ticket is outside the meal window.',
                'data' => [
                    'status' => 'outside_window',
                    'meal' => $meal->title,
                    'meal_status' => $meal->status,
                ],
            ], 422);
        }

        if ($ticket->status === 'void') {
            ScanLog::create([
                'mealTicketId' => $ticket->id,
                'mealId' => $meal->mealId,
                'token' => $token,
                'scanResult' => 'void',
                'message' => 'Ticket has been voided',
                'scannedBy' => $scanner->id,
                'deviceName' => $request->deviceName,
                'ipAddress' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ticket has been voided.',
                'data' => [
                    'status' => 'void',
                    'meal' => $meal->title,
                ],
            ], 422);
        }

        if ($ticket->status === 'redeemed') {
            ScanLog::create([
                'mealTicketId' => $ticket->id,
                'meal_id' => $meal->id,
                'token' => $token,
                'scanResult' => 'already_redeemed',
                'message' => 'Ticket already redeemed during validation',
                'scannedBy' => $scanner->id,
                'deviceName' => $request->deviceName,
                'ipAddress' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ticket already redeemed.',
                'data' => [
                    'status' => 'redeemed',
                    'meal' => $meal->title,
                    'redeemed_at' => $ticket->redeemed_at,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket is valid.',
            'data' => [
                'status' => 'unused',
                'ticketId' => $ticket->ticketId,
                'serialNumber' => $ticket->serialNumber,
                'meal' => [
                    'mealId' => $meal->mealId,
                    'title' => $meal->title,
                    'date' => $meal->meal_date,
                    'startTime' => $meal->startTime,
                    'endTime' => $meal->endTime,
                    'location' => $meal->location,
                ],
            ],
        ]);
    }

    public function redeem(RedeemScanRequest $request): JsonResponse
    {
        $scanner = auth()->user();

        if (!$scanner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $result = $this->mealRedemptionService->redeem(
            token: trim($request->token),
            scanner: $scanner,
            deviceName: $request->deviceName
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }
}