<?php

namespace App\Services;

use App\Models\EventPass;
use App\Models\MealRedemption;
use App\Models\MealSession;
use App\Models\FoodSupply;
use App\Models\FoodDistribution;
use App\Models\ScanLog;
use App\Models\User;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventRedemptionService
{
    public function redeem(string $token, ?User $scanner = null, ?string $deviceName = null): array
    {
        return DB::transaction(function () use ($token, $scanner, $deviceName) {
            $pass = EventPass::where('passCode', $token)
                ->orWhere('qrPayload', $token)
                ->lockForUpdate()
                ->first();

            if (!$pass) {
                ScanLog::create([
                    'token' => $token,
                    'scanResult' => 'invalid',
                    'message' => 'Pass not found',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid pass.',
                    'data' => ['status' => 'invalid'],
                ];
            }

            if ($pass->status === 'void') {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'void',
                    'message' => 'Pass is void',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Pass has been voided.',
                    'data' => ['status' => 'void'],
                ];
            }

            $now = now();

            // Get active event
            $activeEvent = Event::where('status', 'active')
                ->orderByDesc('created_at')
                ->first();

            if (!$activeEvent) {
                return [
                    'success' => false,
                    'message' => 'No active event found.',
                    'data' => ['status' => 'no_active_event'],
                ];
            }
            
            $eventId = $activeEvent->eventId ?? $activeEvent->id;

            // Get active meal session for the event
            $mealSession = MealSession::where('eventId', $eventId)
                ->where('status', 'active')
                ->orderBy('mealDate')
                ->orderBy('startTime')
                ->lockForUpdate()
                ->first();

            if (!$mealSession) {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'no_active_meal',
                    'message' => 'No active meal session',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'No active meal session.',
                    'data' => ['status' => 'no_active_meal'],
                ];
            }

            // Check meal time window
            $mealStart = Carbon::parse($mealSession->mealDate . ' ' . $mealSession->startTime);
            $mealEnd = Carbon::parse($mealSession->mealDate . ' ' . $mealSession->endTime);

            if ($now->lt($mealStart) || $now->gt($mealEnd)) {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'mealSessionId' => $mealSession->mealSessionId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'outside_window',
                    'message' => 'Meal session is outside redemption window',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Pass is outside the meal window.',
                    'data' => [
                        'status' => 'outside_window',
                        'mealSession' => $mealSession->title,
                    ],
                ];
            }

            // Check if already redeemed for this meal session
            $alreadyRedeemed = MealRedemption::where('mealSessionId', $mealSession->mealSessionId)
                ->where('passId', $pass->passId)
                ->first();

            if ($alreadyRedeemed) {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'mealSessionId' => $mealSession->mealSessionId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'already_redeemed',
                    'message' => 'Pass already redeemed for this meal session',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Pass already redeemed for this meal.',
                    'data' => [
                        'status' => 'already_redeemed',
                        'mealSession' => $mealSession->title,
                        'redeemedAt' => $alreadyRedeemed->redeemedAt,
                    ],
                ];
            }

            // ==========================================
            // NEW: FOOD DISTRIBUTION TRACKING
            // ==========================================
            
            $foodDistributionData = null;

            // Try to distribute food from inventory
            $foodResult = $this->distributeFoodFromInventory(
                $pass,
                $mealSession,
                $eventId,
                $scanner,
                $deviceName
            );

            if (!$foodResult['success']) {
                // Food not available - log but continue with pass redemption
                Log::warning('Food distribution failed during redemption', [
                    'pass' => $pass->passId,
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

            // Create meal redemption record
            MealRedemption::create([
                'mealSessionId' => $mealSession->mealSessionId,
                'passId' => $pass->passId,
                'redeemedAt' => $now,
                'redeemedBy' => $scanner?->id,
                'deviceName' => $deviceName,
            ]);

            $mealSession->increment('redeemedCount');

            ScanLog::create([
                'eventId' => $pass->eventId,
                'mealSessionId' => $mealSession->mealSessionId,
                'passId' => $pass->passId,
                'token' => $token,
                'scanResult' => 'valid',
                'message' => 'QR scanned successfully',
                'scannedBy' => $scanner?->id,
                'deviceName' => $deviceName,
                'ipAddress' => request()->ip(),
            ]);

            $responseData = [
                'status' => 'redeemed',
                'eventId' => $pass->eventId,
                'mealSessionId' => $mealSession->mealSessionId,
                'mealSession' => $mealSession->title,
                'mealDate' => $mealSession->mealDate,
                'redeemedAt' => $now->format('g:i A'),
            ];

            // Add food distribution info if available
            if ($foodDistributionData) {
                $responseData = array_merge($responseData, $foodDistributionData);
            }

            return [
                'success' => true,
                'message' => 'QR scanned successfully.',
                'data' => $responseData,
            ];
        });
    }

    /**
     * Attempt to distribute food from inventory when pass is scanned
     */
    private function distributeFoodFromInventory(
        EventPass $pass,
        MealSession $mealSession,
        int $eventId,
        ?User $scanner,
        ?string $deviceName
    ): array {
        // Get attendee ID from pass
        $attendeeId = $pass->attendeeId ?? null;

        if (!$attendeeId) {
            return [
                'success' => false,
                'message' => 'No attendee assigned to this pass.',
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
                    'distributedAt' => $existingDistribution->created_at->format('g:i A'),
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
            'ticketId' => null, // EventPass doesn't have ticketId
            'distributedBy' => $scanner?->id,
            'deviceName' => $deviceName,
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