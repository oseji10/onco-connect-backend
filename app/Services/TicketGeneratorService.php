<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealTicket;
use Illuminate\Support\Facades\DB;

class TicketGeneratorService
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {
    }

    public function generate(Meal $meal, int $count): void
    {
        DB::transaction(function () use ($meal, $count) {
            $createdTickets = [];

            $existingCount = $meal->tickets()->count();

            for ($i = 0; $i < $count; $i++) {
                $token = bin2hex(random_bytes(16));

                $ticket = MealTicket::create([
                    'mealId' => $meal->mealId,
                    'token' => $token,
                    'serialNumber' => strtoupper(
                        'ML-' . str_pad((string) ($existingCount + $i + 1), 6, '0', STR_PAD_LEFT)
                    ),
                    'qrPayload' => $token,
                    'status' => 'unused',
                ]);

                $createdTickets[] = $ticket;
            }

            foreach ($createdTickets as $ticket) {
                $this->qrCodeService->generateForTicket($ticket);
            }

            $meal->update([
                'ticketCount' => $meal->tickets()->count(),
            ]);
        });
    }
}