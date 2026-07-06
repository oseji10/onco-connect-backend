<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPass;
use Illuminate\Support\Facades\DB;

class EventPassGeneratorService
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {
    }

    public function generate(Event $event, int $count): void
    {
        DB::transaction(function () use ($event, $count) {
            $existingCount = $event->passes()->count();

            for ($i = 0; $i < $count; $i++) {
                $pass = EventPass::create([
                    'eventId' => $event->eventId,
                    'passCode' => bin2hex(random_bytes(16)),
                    'serialNumber' => strtoupper(
                        'ICW-' . str_pad((string) ($existingCount + $i + 1), 4, '0', STR_PAD_LEFT)
                    ),
                    'status' => 'active',
                ]);

                $this->qrCodeService->generateForEventPass($pass);
            }
        });
    }
}