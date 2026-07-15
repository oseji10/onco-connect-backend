<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function issamCentral(Request $request): JsonResponse
    {
        // ── Resolve active event ──────────────────────────────────────────────
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'data'    => null,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // ── Date & period ─────────────────────────────────────────────────────
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $selectedDateString = $selectedDate->toDateString();

        $programme = $activeEvent->title    ?? 'ICW 2026';
        $venue     = $activeEvent->location ?? 'Abuja';

        $periodStart = Carbon::parse($activeEvent->startDate ?? $request->query('periodStart', '2026-03-24'))->toDateString();
        $periodEnd   = Carbon::parse($activeEvent->endDate   ?? $request->query('periodEnd',   '2026-03-30'))->toDateString();

        // ── Core counts (scoped to active event) ─────────────────────────────

        $totalParticipants = DB::table('attendees as a')
            ->whereExists(function ($query) use ($eventId) {
                $query->select(DB::raw(1))
                    ->from('event_passes as ep')
                    ->whereColumn('ep.attendeeId', 'a.attendeeId')
                    ->where('ep.eventId', $eventId);
            })
            ->where('a.isRegistered', 1)
            ->count();

        // Broader than "accredited" — every completed registration for this
        // event, whether or not the attendee has been issued a badge/pass
        // yet. If your registration flow doesn't gate on `eventId` directly
        // on the attendees table, adjust this scope accordingly.
        $totalRegistered = DB::table('attendees')
            ->where('eventId', $eventId)
            // ->where('isRegistered', 1)
            ->count();

        $presentForDate = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDateString)
            // ->where('a.isRegistered', 1)
            ->distinct()
            ->count('da.attendeeId');

        $absentForDate     = max($totalParticipants - $presentForDate, 0);
        $attendancePercent = $totalParticipants > 0
            ? round(($presentForDate / $totalParticipants) * 100, 1)
            : 0;

        // Incidents are not attendee-linked — always event-wide
        $incidentsForDate = DB::table('incidents')
            ->where('eventId', $eventId)
            ->whereDate('reportedAt', $selectedDateString)
            ->count();

        $openIncidents = DB::table('incidents')
            ->where('eventId', $eventId)
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $mealsServedForDate = DB::table('meal_redemptions as mr')
            ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
            ->join('attendees as a', 'a.attendeeId', '=', 'ep.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('mr.created_at', $selectedDateString)
            // ->where('a.isRegistered', 1)
            ->distinct('ep.attendeeId')
            ->count('ep.attendeeId');

        // Gender split — present on selected date
        $genderSplit = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDateString)
            // ->where('a.isRegistered', 1)
            ->select(
                DB::raw("
                    CASE
                        WHEN TRIM(LOWER(a.gender)) IN ('male', 'm')   THEN 'male'
                        WHEN TRIM(LOWER(a.gender)) IN ('female', 'f') THEN 'female'
                        ELSE 'other'
                    END as gender
                "),
                DB::raw('COUNT(DISTINCT da.attendeeId) as count')
            )
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // Registered gender split — all accredited attendees
        $registeredGenderSplit = DB::table('attendees as a')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            // ->where('a.isRegistered', 1)
            ->select(
                DB::raw("
                    CASE
                        WHEN TRIM(LOWER(a.gender)) IN ('male', 'm')   THEN 'male'
                        WHEN TRIM(LOWER(a.gender)) IN ('female', 'f') THEN 'female'
                        ELSE 'other'
                    END as gender
                "),
                DB::raw('COUNT(DISTINCT a.attendeeId) as count')
            )
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // Abstract submission metrics.
        // NOTE: AbstractSubmission has no eventId column (the abstract module
        // was built standalone, not tied to the events table), so these are
        // global counts rather than scoped to $eventId like everything else
        // on this dashboard. If you later run multiple events/years through
        // this same abstracts table, this will need a scope added.
        $abstractsSubmitted = AbstractSubmission::count();

        $abstractsAccepted = AbstractSubmission::where('status', 'accepted')->count();

        $abstractsRejected = AbstractSubmission::where('status', 'rejected')->count();

        $posterCount = AbstractSubmission::where('status', 'accepted')
            ->where('presentation_type', 'Poster')
            ->count();

        $oralCount = AbstractSubmission::where('status', 'accepted')
            ->where('presentation_type', 'Oral')
            ->count();

        // ── Overview stats ────────────────────────────────────────────────────
        $overviewStats = [
            ['title' => 'Total Accredited Participants', 'value' => (string) $totalParticipants],

            [
                'title' => 'Registered Participants',
                'value' => (string) $totalRegistered,
                'note'  => 'All registrations, incl. not-yet-accredited',
            ],

            [
                'title'            => 'Accredited Male',
                'value'            => (string) ($registeredGenderSplit['male'] ?? 0),
                'note'             => 'Registered attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-black-100 dark:bg-black-800/40',
                'iconClassName'    => 'w-5 h-5 text-black-700 dark:text-black-100',
            ],
            [
                'title'            => 'Accredited Female',
                'value'            => (string) ($registeredGenderSplit['female'] ?? 0),
                'note'             => 'Registered attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-pink-100 dark:bg-pink-800/40',
                'iconClassName'    => 'w-5 h-5 text-pink-700 dark:text-pink-100',
            ],

            ['title' => 'Total Present for Selected Date', 'value' => (string) $presentForDate],
            ['title' => 'Total Absent for Selected Date',  'value' => (string) $absentForDate],
            ['title' => 'Attendance %',                    'value' => number_format($attendancePercent, 1) . '%'],

            [
                'title'            => 'Males Present',
                'value'            => (string) ($genderSplit['male'] ?? 0),
                'note'             => 'Present attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-blue-100 dark:bg-blue-800/40',
                'iconClassName'    => 'w-5 h-5 text-blue-700 dark:text-blue-100',
            ],
            [
                'title'            => 'Females Present',
                'value'            => (string) ($genderSplit['female'] ?? 0),
                'note'             => 'Present attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-pink-100 dark:bg-pink-800/40',
                'iconClassName'    => 'w-5 h-5 text-pink-700 dark:text-pink-100',
            ],

            ['title' => 'Incidents for Date',      'value' => (string) $incidentsForDate],
            ['title' => 'Open Incidents',           'value' => (string) $openIncidents],
            ['title' => 'Meals (Unique)',           'value' => (string) $mealsServedForDate],

            // Abstract submission metrics
            [
                'title' => 'Abstracts Submitted',
                'value' => (string) $abstractsSubmitted,
                'note'  => 'All submissions to date',
            ],
            [
                'title' => 'Abstracts Accepted',
                'value' => (string) $abstractsAccepted,
                'note'  => $abstractsSubmitted > 0
                    ? round(($abstractsAccepted / $abstractsSubmitted) * 100) . '% of submissions'
                    : null,
            ],
            [
                'title' => 'Abstracts Rejected',
                'value' => (string) $abstractsRejected,
                'note'  => $abstractsSubmitted > 0
                    ? round(($abstractsRejected / $abstractsSubmitted) * 100) . '% of submissions'
                    : null,
            ],
            [
                'title' => 'Poster Presentations',
                'value' => (string) $posterCount,
                'note'  => 'Accepted, poster format',
            ],
            [
                'title' => 'Oral Presentations',
                'value' => (string) $oralCount,
                'note'  => 'Accepted, oral format',
            ],
        ];

        // ── Sub-sections ──────────────────────────────────────────────────────
        $supervisorRows   = []; // Removed supervisor/Sub-CL tracking
        $incidentSnapshot = $this->buildIncidentSnapshot($selectedDateString, $eventId);
        $coordinatorNotes = $this->buildCoordinatorNotes($openIncidents, $selectedDateString);

        return response()->json([
            'data' => [
                'dashboardDate'    => $selectedDate->format('d M Y'),
                'dayName'          => $selectedDate->format('l'),
                'programme'        => $programme,
                'venue'            => $venue,
                'period'           => Carbon::parse($periodStart)->format('d-M-Y') . ' to ' . Carbon::parse($periodEnd)->format('d-M-Y'),
                'overviewStats'    => $overviewStats,
                'supervisorRows'   => $supervisorRows,
                'incidentSnapshot' => $incidentSnapshot,
                'coordinatorNotes' => $coordinatorNotes,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function buildIncidentSnapshot(string $selectedDate, int $eventId): array
    {
        // Incidents are not attendee-linked — always event-wide
        return DB::table('incidents')
            ->select('category', DB::raw('COUNT(*) as total'))
            ->where('eventId', $eventId)
            ->whereDate('reportedAt', $selectedDate)
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count'    => (int) $row->total,
            ])
            ->toArray();
    }

    protected function buildCoordinatorNotes(int $openIncidents, string $selectedDate): array
    {
        $notes = [];

        // Removed Sub-CL low attendance tracking

        if ($openIncidents > 0) {
            $notes[] = "There are {$openIncidents} open incidents currently.";
        }

        if (empty($notes)) {
            $notes[] = 'Operations stable for selected date.';
        }

        return $notes;
    }
}