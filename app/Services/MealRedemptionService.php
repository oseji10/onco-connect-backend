<?php

namespace App\Services;

use App\Models\User;
use App\Models\ScanLog;
use App\Models\MealTicket;
use App\Models\MealSession;
use App\Models\MealRedemption;
use App\Models\FoodSupply;
use App\Models\FoodDistribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MealRedemptionService
{
    public function redeem(string $token, ?User $scanner = null, ?string $deviceName = null): array
    {
        return DB::transaction(function () use ($token, $scanner, $deviceName) {
            $ticket = MealTicket::with('meal')
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if (!$ticket) {
                ScanLog::create([
                    'token' => $token,
                    'scanResult' => 'invalid',
                    'message' => 'Ticket not found',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid ticket.',
                    'data' => [
                        'status' => 'invalid',
                    ],
                ];
            }

            $meal = $ticket->meal;
            $now = now();

            Log::info($meal);

            $mealStart = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $meal->mealDate . ' ' . $meal->startTime,
                config('app.timezone')
            );

            $mealEnd = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $meal->mealDate . ' ' . $meal->endTime,
                config('app.timezone')
            );

            if ($meal->status !== 'active' || $now->lt($mealStart) || $now->gt($mealEnd)) {
                ScanLog::create([
                    'mealTicketId' => $ticket->mealTicketId,
                    'mealId' => $meal->mealId,
                    'token' => $token,
                    'scanResult' => 'outside_window',
                    'message' => 'Meal is not redeemable at this time',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Ticket is outside the meal window.',
                    'data' => [
                        'status' => 'outside_window',
                        'meal' => $meal->title,
                        'meal_status' => $meal->status,
                    ],
                ];
            }

            if ($ticket->status === 'void') {
                ScanLog::create([
                    'mealTicketId' => $ticket->mealTicketId,
                    'mealId' => $meal->mealId,
                    'token' => $token,
                    'scanResult' => 'void',
                    'message' => 'Ticket has been voided',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Ticket has been voided.',
                    'data' => [
                        'status' => 'void',
                        'meal' => $meal->title,
                    ],
                ];
            }

            if ($ticket->status === 'redeemed') {
                ScanLog::create([
                    'mealTicketId' => $ticket->mealTicketId,
                    'mealId' => $meal->mealId,
                    'token' => $token,
                    'scanResult' => 'already_redeemed',
                    'message' => 'Ticket already redeemed',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Ticket already redeemed.',
                    'meal' => $meal->title,
                    'redeemedAt' => $ticket->redeemed_at,
                    'data' => [
                        'status' => 'redeemed',
                        'meal' => $meal->title,
                        'redeemed_at' => $ticket->redeemed_at,
                    ],
                ];
            }

            // ==========================================
            // NEW: FOOD DISTRIBUTION TRACKING
            // ==========================================
            
            // Find the corresponding meal session (if exists)
            $mealSession = MealSession::where('title', $meal->title)
                ->where('mealDate', $meal->mealDate)
                ->where('status', 'active')
                ->first();

            $foodDistributionData = null;

            if ($mealSession) {
                // Try to distribute food from inventory
                $foodResult = $this->distributeFoodFromInventory(
                    $ticket,
                    $mealSession,
                    $scanner,
                    $deviceName
                );

                if (!$foodResult['success']) {
                    // Food not available - log but continue with ticket redemption
                    Log::warning('Food distribution failed during redemption', [
                        'ticket' => $ticket->ticketId,
                        'reason' => $foodResult['message']
                    ]);
                    
                    $foodDistributionData = [
                        'foodAvailable' => false,
                        'message' => $foodResult['message']
                    ];
                } else {
                    $foodDistributionData = [
                        'foodAvailable' => true,
                        'foodItem' => $foodResult['data']['foodItem'] ?? null,
                        'vendorName' => $foodResult['data']['vendorName'] ?? null,
                        'remainingStock' => $foodResult['data']['remainingStock'] ?? null,
                    ];
                }
            }

            // Update ticket status
            $ticket->update([
                'status' => 'redeemed',
                'redeemedAt' => $now,
                'redeemedBy' => $scanner?->id,
                'lastScannedAt' => $now,
            ]);

            $meal->increment('redeemedCount');

            ScanLog::create([
                'mealTicketId' => $ticket->mealTicketId,
                'mealId' => $meal->mealId,
                'token' => $token,
                'scanResult' => 'valid',
                'message' => 'Ticket redeemed successfully',
                'scannedBy' => $scanner?->id,
                'deviceName' => $deviceName,
                'ipAddress' => request()->ip(),
            ]);

            $responseData = [
                'status' => 'redeemed',
                'meal' => $meal->title,
                'redeemed_at' => $now,
            ];

            // Add food distribution info if available
            if ($foodDistributionData) {
                $responseData = array_merge($responseData, $foodDistributionData);
            }

            return [
                'success' => true,
                'message' => 'Ticket redeemed successfully.',
                'meal' => $meal->title,
                'ticketId' => $ticket->ticketId,
                'redeemed_at' => $now,
                'data' => $responseData,
            ];
        });
    }

    /**
     * Attempt to distribute food from inventory when ticket is scanned
     */
    private function distributeFoodFromInventory(
        MealTicket $ticket,
        MealSession $mealSession,
        ?User $scanner,
        ?string $deviceName
    ): array {
        // Get attendee ID from ticket
        $attendeeId = $ticket->attendeeId ?? null;

        if (!$attendeeId) {
            return [
                'success' => false,
                'message' => 'No attendee assigned to this ticket.',
            ];
        }

        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return [
                'success' => false,
                'message' => 'No active event found.',
            ];
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Verify meal session belongs to active event
        if ($mealSession->eventId !== $eventId) {
            return [
                'success' => false,
                'message' => 'Meal session does not belong to active event.',
            ];
        }

        // Check if food already distributed for this session today
        $existingDistribution = FoodDistribution::where('attendeeId', $attendeeId)
            ->where('mealSessionId', $mealSession->mealSessionId)
            ->where('eventId', $eventId)
            ->whereDate('created_at', today())
            ->first();

        if ($existingDistribution) {
            return [
                'success' => false,
                'message' => 'Food already distributed for this session today.',
                'data' => [
                    'status' => 'already_distributed',
                ],
            ];
        }

        // Find available food supply for this meal session
        $supply = FoodSupply::where('mealSessionId', $mealSession->mealSessionId)
            ->where('eventId', $eventId)
            ->where('quantityRemaining', '>', 0)
            ->orderBy('supplyDate', 'asc')
            ->first();

        if (!$supply) {
            return [
                'success' => false,
                'message' => 'No food available for this meal session.',
                'data' => [
                    'status' => 'out_of_stock',
                ],
            ];
        }

        // Deduct from inventory
        $supply->decrement('quantityRemaining');
        $supply->increment('quantityDistributed');

        // Record food distribution
        FoodDistribution::create([
            'attendeeId' => $attendeeId,
            'mealSessionId' => $mealSession->mealSessionId,
            'eventId' => $eventId,
            'foodSupplyId' => $supply->supplyId,
            'ticketId' => $ticket->ticketId,
            'distributedBy' => $scanner?->id,
            'deviceName' => $deviceName,
        ]);

        // Also create MealRedemption record
        MealRedemption::create([
            'mealSessionId' => $mealSession->mealSessionId,
            'passId' => $ticket->passId ?? null,
            'redeemedBy' => $scanner?->id,
            'deviceName' => $deviceName,
            'redeemedAt' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Food distributed successfully.',
            'data' => [
                'foodItem' => $supply->foodItem,
                'vendorName' => $supply->vendorName,
                'remainingStock' => $supply->quantityRemaining,
            ],
        ];
    }
}