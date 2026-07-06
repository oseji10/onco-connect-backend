<?php

namespace App\Services;

use App\Models\User;
use App\Models\ScanLog;
use App\Models\MealTicket;
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
            // $mealStart = Carbon::parse($meal->mealDate . ' ' . $meal->startTime);
            // $mealEnd = Carbon::parse($meal->mealDate . ' ' . $meal->endTime);
\Log::info($meal);
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

            return [
                'success' => true,
                'message' => 'Ticket redeemed successfully.',
                'meal' => $meal->title,
                'ticketId' => $ticket->ticketId,
                'redeemed_at' => $now,
                'data' => [
                    'status' => 'redeemed',
                    'meal' => $meal->title,
                    'redeemed_at' => $now,
                ],
            ];
        });
    }
}
