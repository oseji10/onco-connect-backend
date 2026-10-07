<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin: download event passes as print-ready badge sheets (10 per A4 page, portrait, 99 x 56 mm each,
 * black and white). Split into batches so big events don't hit time/memory limits.
 * Protect with your admin middleware.
 */
class PassPrintController extends Controller
{
    private const PER_SHEET = 10;

    public function __construct(protected QrCodeService $qrCodeService) {}

    private function event(): ?Event
    {
        return Event::where('status', 'active')->first();
    }

    private function query(Event $event, Request $request)
    {
        $type  = $request->query('type', 'Physical');  // Physical | Virtual | all
        $group = $request->query('group', 'all');      // all | vip | regular

        $query = Attendee::where('eventId', $event->eventId)->whereHas('pass')->with('pass');

        if (in_array($type, ['Physical', 'Virtual'], true)) {
            $query->where('participationType', $type);
        }

        // VIP guests are stored as VIP attendees too (isVip = true), so "vip" includes them.
        if ($group === 'vip') {
            $query->where('isVip', true);
        } elseif ($group === 'regular') {
            $query->where('isVip', false);
        }

        // Print a single person's badge (e.g. one VIP or one guest)
        if ($request->filled('attendeeId')) {
            $query->where('attendeeId', (int) $request->query('attendeeId'));
        }

        // Print one VIP together with all of their guests
        $hostId = $request->filled('hostId') ? (int) $request->query('hostId') : null;
        if ($hostId) {
            $query->where(function ($w) use ($hostId) {
                $w->where('attendeeId', $hostId)->orWhere('vipHostId', $hostId);
            });
        }

        // VIP prints keep each VIP's guests right behind them (ids are VIP-date-001, VIP-date-001-G1, ...).
        if ($hostId || $group === 'vip') {
            return $query->orderBy('uniqueId')->orderBy('attendeeId');
        }

        // Everyone else: alphabetical, easier to hand out at the registration table.
        return $query->orderBy('lastName')->orderBy('firstName')->orderBy('attendeeId');
    }

    /** Whole sheets only, between 10 and 200 badges per file. */
    private function size(Request $request): int
    {
        $size = max(self::PER_SHEET, min(200, (int) $request->query('size', 80)));

        return (int) (ceil($size / self::PER_SHEET) * self::PER_SHEET);
    }

    /** GET /passes/print/summary?type=&group=&size= */
    public function summary(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
        }

        $total = $this->query($event, $request)->count();
        $size  = $this->size($request);

        $batches = [];
        for ($i = 1; $i <= (int) ceil($total / $size); $i++) {
            $from = ($i - 1) * $size + 1;
            $to   = min($i * $size, $total);
            $batches[] = ['batch' => $i, 'from' => $from, 'to' => $to, 'count' => $to - $from + 1];
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => ['total' => $total, 'size' => $size, 'batches' => $batches],
        ]);
    }

    /** GET /passes/print/download?type=&group=&size=&batch=&attendeeId=&hostId= : PDF */
    public function download(Request $request)
    {
        $event = $this->event();
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
        }

        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        $size  = $this->size($request);
        $batch = max(1, (int) $request->query('batch', 1));

        $attendees = $this->query($event, $request)->skip(($batch - 1) * $size)->take($size)->get();

        if ($attendees->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No passes match these filters.'], 404);
        }

        $sheets = $attendees
            ->map(fn (Attendee $a) => $this->badge($a))
            ->values()
            ->chunk(self::PER_SHEET)
            ->map(fn ($chunk) => $chunk->values());

        $pdf = Pdf::loadView('pdf.badge-sheet', [
            'sheets'    => $sheets,
            'eventName' => config('certificate.event_name') ?: ($event->name ?? $event->title ?? ''),
        ])->setPaper('a4', 'portrait');

        $type  = strtolower($request->query('type', 'Physical'));
        $group = $request->query('group', 'all');

        return $pdf->download("passes-{$type}-{$group}-batch-{$batch}.pdf");
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function badge(Attendee $a): array
    {
        $pass  = $a->pass;
        $name  = mb_strtoupper(trim(implode(' ', array_filter([$a->title, $a->firstName, $a->lastName]))));
        $len   = mb_strlen($name);
        $isVip = (bool) $a->isVip;

        return [
            'name'     => $name,
            'size'     => $len <= 20 ? 20 : ($len <= 36 ? 17 : ($len <= 60 ? 14 : 12)), // (the 10-up template sizes names itself)
            'org'      => $a->organizationName ? Str::limit(mb_strtoupper($a->organizationName), 70) : null,
            'uniqueId' => $a->uniqueId,
            'serial'   => $pass?->serialNumber,
            'label'    => $isVip ? 'VIP' : ($a->category ? Str::headline($a->category) : 'Participant'),
            'color'    => $isVip ? '#b45309' : ($a->participationType === 'Virtual' ? '#1d4ed8' : '#166534'), // unused: passes print black and white
            'qr'       => $pass ? $this->qrDataUri($pass) : null,
        ];
    }

    /** Embed the pass's existing QR image; create it first if it is missing (e.g. VIP passes). */
    private function qrDataUri(EventPass $pass): ?string
    {
        $file = $this->findQrFile($pass);

        if (!$file) {
            try {
                $this->qrCodeService->generateForEventPass($pass);
                $pass->refresh();
                $file = $this->findQrFile($pass);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($file) {
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'svg'         => 'image/svg+xml',
                'jpg', 'jpeg' => 'image/jpeg',
                default       => 'image/png',
            };

            return "data:{$mime};base64," . base64_encode(file_get_contents($file));
        }

        // Last resort: draw a QR of the pass code ourselves (the scanner resolves passCode),
        // when the simple-qrcode package happens to be installed.
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            try {
                $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(300)->margin(1)->generate($pass->passCode);

                return 'data:image/svg+xml;base64,' . base64_encode((string) $svg);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return null;
    }

    /**
     * Find the QR image on disk without assuming a column name: look at every attribute whose
     * name contains "qr" and accept the first value that resolves to a real file.
     */
    private function findQrFile(EventPass $pass): ?string
    {
        foreach ($pass->getAttributes() as $key => $value) {
            if (!is_string($value) || $value === '' || stripos($key, 'qr') === false) {
                continue;
            }

            // "qr/a.png", "storage/qr/a.png" or "https://host/storage/qr/a.png"
            $relative = ltrim(preg_replace('#^https?://[^/]+#', '', $value), '/');
            $relative = preg_replace('#^storage/#', '', $relative);

            $candidates = [
                Storage::disk('public')->path($relative),
                storage_path('app/' . $relative),
                public_path($relative),
                $value,
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}