<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\CertificateDownload;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin: who downloaded their certificates, and how many.
 * Reads the rows written by CertificateDownloadLogger. Protect with your admin middleware.
 */
class CertificateDownloadAdminController extends Controller
{
    private function event(): ?Event
    {
        return Event::where('status', 'active')->first();
    }

    private function noEvent(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
    }

    /** GET /certificates/downloads/stats */
    public function stats(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $id   = $event->eventId;
        $base = fn () => CertificateDownload::where('eventId', $id);

        $total  = $base()->count();
        $unique = $base()->distinct()->count('attendeeId');
        $today  = $base()->whereDate('created_at', today())->count();

        $byType = $base()->select('type', DB::raw('count(*) as c'))->groupBy('type')->pluck('c', 'type');

        $uniqueByType = $base()
            ->select('type', DB::raw('count(distinct attendeeId) as c'))
            ->groupBy('type')
            ->pluck('c', 'type');

        $bySource = $base()->select('source', DB::raw('count(*) as c'))->groupBy('source')->pluck('c', 'source');

        // Last 14 days, zero-filled so the chart has no gaps
        $from   = today()->subDays(13);
        $perDay = $base()
            ->where('created_at', '>=', $from)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $daily = [];
        for ($i = 0; $i < 14; $i++) {
            $date    = $from->copy()->addDays($i)->toDateString();
            $daily[] = ['date' => $date, 'count' => (int) $perDay->get($date, 0)];
        }

        $eligible = Attendee::where('eventId', $id)->where('certificateEligible', true)->count();
        $last     = $base()->max('created_at');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'total'            => $total,
                'unique'           => $unique,
                'today'            => $today,
                'eligible'         => $eligible,
                'notYetDownloaded' => max($eligible - $unique, 0),
                'lastDownloadAt'   => $last ? Carbon::parse($last)->toIso8601String() : null,
                'byType'           => $byType,
                'uniqueByType'     => $uniqueByType,
                'bySource'         => $bySource,
                'daily'            => $daily,
            ],
        ]);
    }

    /** GET /certificates/downloads?search=&type=&source=&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $query = CertificateDownload::query()
            ->join('attendees', 'attendees.attendeeId', '=', 'certificate_downloads.attendeeId')
            ->where('certificate_downloads.eventId', $event->eventId)
            ->select([
                'certificate_downloads.id',
                'certificate_downloads.type',
                'certificate_downloads.source',
                'certificate_downloads.ipAddress',
                'certificate_downloads.userAgent',
                'certificate_downloads.created_at',
                'attendees.attendeeId',
                'attendees.title',
                'attendees.firstName',
                'attendees.lastName',
                'attendees.otherNames',
                'attendees.uniqueId',
                'attendees.email',
            ])
            ->orderByDesc('certificate_downloads.created_at')
            ->orderByDesc('certificate_downloads.id');

        if ($request->filled('type')) {
            $query->where('certificate_downloads.type', $request->query('type'));
        }

        if ($request->filled('source')) {
            $query->where('certificate_downloads.source', $request->query('source'));
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            foreach (preg_split('/\s+/', $search) as $word) {
                $query->where(function ($w) use ($word) {
                    $w->where('attendees.firstName', 'like', "%{$word}%")
                      ->orWhere('attendees.lastName', 'like', "%{$word}%")
                      ->orWhere('attendees.uniqueId', 'like', "%{$word}%")
                      ->orWhere('attendees.email', 'like', "%{$word}%");
                });
            }
        }

        $page = $query->paginate(min($request->integer('per_page', 15), 100));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'total'     => $page->total(),
                'downloads' => $page->getCollection()->map(fn ($r) => [
                    'id'         => $r->id,
                    'attendeeId' => $r->attendeeId,
                    'fullName'   => trim(implode(' ', array_filter([$r->title, $r->firstName, $r->lastName, $r->otherNames]))),
                    'uniqueId'   => $r->uniqueId,
                    'email'      => $r->email,
                    'type'       => $r->type,
                    'source'     => $r->source,
                    'device'     => $this->device($r->userAgent),
                    'ipAddress'  => $r->ipAddress,
                    'createdAt'  => Carbon::parse($r->created_at)->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    private function device(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);

        if ($ua === '') {
            return 'Unknown';
        }

        return preg_match('/mobile|android|iphone|ipad/', $ua) ? 'Mobile' : 'Desktop';
    }
}