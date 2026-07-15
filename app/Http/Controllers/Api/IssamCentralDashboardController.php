<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;

class IssamCentralDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // keep your existing dashboard method here
        return response()->json([]);
    }

    public function detail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', 'string'], // overview | incident | supervisor
            'title' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'supervisorId' => ['nullable', 'integer'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $type = $validated['type'];
        $title = $validated['title'] ?? null;
        $category = $validated['category'] ?? null;
        $supervisorId = $validated['supervisorId'] ?? null;

        try {
            return match ($type) {
                'overview' => $this->handleOverviewDetail($date, $title),
                'incident' => $this->handleIncidentDetail($date, $category),
                'supervisor' => $this->handleSupervisorDetail($date, $supervisorId),
                default => response()->json([
                    'message' => 'Unsupported detail type supplied.',
                ], 422),
            };
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: 'Unable to load dashboard detail.',
            ], 500);
        }
    }

    protected function handleOverviewDetail(string $date, ?string $title): JsonResponse
    {
        $normalized = strtolower(trim((string) $title));

        return match ($normalized) {
            // ── Accredited / total ──────────────────────────────────────
            'total participants',
            'total accredited participants' => $this->totalParticipantsDetail($date),

            // ── Registered participants (broader than accredited) ───────
            'registered participants' => $this->registeredParticipantsDetail($date),

            // ── Merged gender breakdown cards ───────────────────────────
            'accredited by gender' => $this->accreditedGenderBreakdownDetail($date),
            'present by gender'    => $this->presentGenderBreakdownDetail($date),

            // ── Accredited by gender (individual, kept for backwards compat) ──
            'accredited male'   => $this->accreditedByGenderDetail($date, 'male'),
            'accredited female' => $this->accreditedByGenderDetail($date, 'female'),

            // ── Present ─────────────────────────────────────────────────
            'present today',
            'present for date',
            'present participants',
            'total present for selected date' => $this->presentParticipantsDetail($date),

            // ── Absent ──────────────────────────────────────────────────
            'absent today',
            'absent for date',
            'absent participants',
            'total absent for selected date' => $this->absentParticipantsDetail($date),

            // ── Attendance % ─────────────────────────────────────────────
            'attendance %',
            'attendance percentage',
            'attendance for date' => $this->attendancePercentageDetail($date),

            // ── Present by gender (individual, kept for backwards compat) ──
            'males present' => $this->presentByGenderDetail($date, 'male'),
            'females present' => $this->presentByGenderDetail($date, 'female'),

            // ── Late ─────────────────────────────────────────────────────
            'late arrivals',
            'late arrivals for date' => $this->lateArrivalsDetail($date),

            // ── Incidents ────────────────────────────────────────────────
            'incidents today',
            'incidents for date' => $this->incidentsTodayDetail($date),

            'open incidents',
            'open incidents for date' => $this->openIncidentsDetail($date),

            // ── Meals ────────────────────────────────────────────────────
            'meals served',
            'meals served for date' => $this->mealsServedDetail($date),

            'meals (unique)',
            'unique meals served',
            'meals unique' => $this->uniqueMealsServedDetail($date),

            // ── Abstracts ────────────────────────────────────────────────
            'abstracts submitted' => $this->abstractsSubmittedDetail($date),
            'abstracts accepted'  => $this->abstractsAcceptedDetail($date),
            'abstracts rejected',
            'rejected abstracts' => $this->rejectedAbstractsDetail($date),
            'poster presentations' => $this->posterPresentationsDetail($date),
            'oral presentations'   => $this->oralPresentationsDetail($date),

            default => response()->json([
                'message' => "No detail handler configured for overview title: {$title}",
            ], 422),
        };
    }

    // ── Merged gender-breakdown handlers ───────────────────────────────────────

    protected function accreditedGenderBreakdownDetail(string $date): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $rows = DB::table('attendees as a')
            ->join('event_passes as ep', 'a.attendeeId', '=', 'ep.attendeeId')
            ->select(
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.gender',
                'ep.serialNumber',
                DB::raw('UPPER(a.stateOfResidence) as state'),
                'a.photoUrl'
            )
            ->where('a.isRegistered', 1)
            ->where('a.eventId', $eventId)
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get();

        $maleCount = $rows->filter(
            fn ($r) => in_array(strtolower(trim((string) $r->gender)), ['male', 'm'])
        )->count();

        $femaleCount = $rows->filter(
            fn ($r) => in_array(strtolower(trim((string) $r->gender)), ['female', 'f'])
        )->count();

        return response()->json([
            'title'   => 'Accredited Participants — Gender Breakdown',
            'date'    => $date,
            'summary' => [
                'total'       => $rows->count(),
                'maleCount'   => $maleCount,
                'femaleCount' => $femaleCount,
            ],
            'columns' => [
                ['key' => 'photoUrl',     'label' => 'Passport'],
                ['key' => 'uniqueId',     'label' => 'Participant ID'],
                ['key' => 'fullName',     'label' => 'Full Name'],
                ['key' => 'gender',       'label' => 'Gender'],
                ['key' => 'phone',        'label' => 'Phone Number'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
                ['key' => 'state',        'label' => 'State'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function presentGenderBreakdownDetail(string $date): JsonResponse
    {
        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.passId', '=', 'da.eventPassId')
            ->leftJoin('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.gender',
                'a.photoUrl',
                'ep.serialNumber',
                'da.attendanceDate',
                DB::raw('UPPER(a.stateOfResidence) as state'),
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as markedBy")
            )
            ->whereDate('da.attendanceDate', $date)
            ->where('a.isRegistered', 1)
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get();

        $maleCount = $rows->filter(
            fn ($r) => in_array(strtolower(trim((string) $r->gender)), ['male', 'm'])
        )->count();

        $femaleCount = $rows->filter(
            fn ($r) => in_array(strtolower(trim((string) $r->gender)), ['female', 'f'])
        )->count();

        return response()->json([
            'title'   => 'Present Today — Gender Breakdown',
            'date'    => $date,
            'summary' => [
                'total'       => $rows->count(),
                'maleCount'   => $maleCount,
                'femaleCount' => $femaleCount,
            ],
            'columns' => [
                ['key' => 'uniqueId',       'label' => 'Participant ID'],
                ['key' => 'fullName',       'label' => 'Full Name'],
                ['key' => 'gender',         'label' => 'Gender'],
                ['key' => 'phone',          'label' => 'Phone Number'],
                ['key' => 'serialNumber',   'label' => 'Serial Number'],
                ['key' => 'state',          'label' => 'State'],
                ['key' => 'attendanceDate', 'label' => 'Attendance Date'],
            ],
            'rows' => $rows,
        ]);
    }

    // ── Registered participants (broader than accredited) ──────────────────────

    protected function registeredParticipantsDetail(string $date): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Uses the attendees.isAccredited flag directly, rather than
        // inferring accreditation from an event_passes join — it's the
        // more reliable source of truth for this specific status column.
        $rows = DB::table('attendees as a')
            ->select(
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.gender',
                DB::raw('UPPER(a.stateOfResidence) as state'),
                'a.photoUrl',
                DB::raw("CASE WHEN a.isAccredited = 1 THEN 'Accredited' ELSE 'Pending' END as accreditationStatus")
            )
            ->where('a.isRegistered', 1)
            ->where('a.eventId', $eventId)
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get();

        return response()->json([
            'title'   => 'Registered Participants',
            'date'    => $date,
            'summary' => [
                'totalRegistered' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'photoUrl',            'label' => 'Passport'],
                ['key' => 'uniqueId',            'label' => 'Participant ID'],
                ['key' => 'fullName',            'label' => 'Full Name'],
                ['key' => 'gender',              'label' => 'Gender'],
                ['key' => 'phone',               'label' => 'Phone Number'],
                ['key' => 'state',               'label' => 'State'],
                ['key' => 'accreditationStatus', 'label' => 'Accreditation Status'],
            ],
            'rows' => $rows,
        ]);
    }

    // ── Abstracts ────────────────────────────────────────────────────────────────

    protected function abstractsSubmittedDetail(string $date): JsonResponse
    {
        // NOTE: adjust these column keys if your AbstractSubmission schema
        // uses different field names for the author/title.
        $rows = AbstractSubmission::orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'id'                  => $row->id,
                'title'               => $row->title ?? '-',
                'correspondingAuthor' => $row->corresponding_author ?? $row->author_name ?? '-',
                'presentationType'    => $row->presentation_type ?? '-',
                'status'              => $row->status,
                'submittedAt'         => optional($row->created_at)->format('d M Y') ?? '-',
            ]);

        return response()->json([
            'title'   => 'Abstracts Submitted',
            'date'    => $date,
            'summary' => [
                'submittedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'id',                  'label' => 'Abstract ID'],
                ['key' => 'title',               'label' => 'Title'],
                ['key' => 'correspondingAuthor', 'label' => 'Author'],
                ['key' => 'presentationType',    'label' => 'Presentation Type'],
                ['key' => 'status',              'label' => 'Status'],
                ['key' => 'submittedAt',         'label' => 'Submitted'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function abstractsAcceptedDetail(string $date): JsonResponse
    {
        $rows = AbstractSubmission::where('status', 'accepted')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'id'                  => $row->id,
                'title'               => $row->title ?? '-',
                'correspondingAuthor' => $row->corresponding_author ?? $row->author_name ?? '-',
                'presentationType'    => $row->presentation_type ?? '-',
                'submittedAt'         => optional($row->created_at)->format('d M Y') ?? '-',
            ]);

        return response()->json([
            'title'   => 'Abstracts Accepted',
            'date'    => $date,
            'summary' => [
                'acceptedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'id',                  'label' => 'Abstract ID'],
                ['key' => 'title',               'label' => 'Title'],
                ['key' => 'correspondingAuthor', 'label' => 'Author'],
                ['key' => 'presentationType',    'label' => 'Presentation Type'],
                ['key' => 'submittedAt',         'label' => 'Submitted'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function rejectedAbstractsDetail(string $date): JsonResponse
    {
        $rows = AbstractSubmission::where('status', 'rejected')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'id'                  => $row->id,
                'title'               => $row->title ?? '-',
                'correspondingAuthor' => $row->corresponding_author ?? $row->author_name ?? '-',
                'presentationType'    => $row->presentation_type ?? '-',
                'status'              => $row->status,
                'submittedAt'         => optional($row->created_at)->format('d M Y') ?? '-',
            ]);

        return response()->json([
            'title'   => 'Rejected Abstracts',
            'date'    => $date,
            'summary' => [
                'rejectedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'id',                 'label' => 'Abstract ID'],
                ['key' => 'title',              'label' => 'Title'],
                ['key' => 'correspondingAuthor','label' => 'Author'],
                ['key' => 'presentationType',   'label' => 'Presentation Type'],
                ['key' => 'submittedAt',        'label' => 'Submitted'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function posterPresentationsDetail(string $date): JsonResponse
    {
        $rows = AbstractSubmission::where('status', 'accepted')
            ->where('presentation_type', 'Poster')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'id'                  => $row->id,
                'title'               => $row->title ?? '-',
                'correspondingAuthor' => $row->corresponding_author ?? $row->author_name ?? '-',
                'submittedAt'         => optional($row->created_at)->format('d M Y') ?? '-',
            ]);

        return response()->json([
            'title'   => 'Poster Presentations',
            'date'    => $date,
            'summary' => [
                'posterCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'id',                  'label' => 'Abstract ID'],
                ['key' => 'title',               'label' => 'Title'],
                ['key' => 'correspondingAuthor', 'label' => 'Author'],
                ['key' => 'submittedAt',         'label' => 'Submitted'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function oralPresentationsDetail(string $date): JsonResponse
    {
        $rows = AbstractSubmission::where('status', 'accepted')
            ->where('presentation_type', 'Oral')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => [
                'id'                  => $row->id,
                'title'               => $row->title ?? '-',
                'correspondingAuthor' => $row->corresponding_author ?? $row->author_name ?? '-',
                'submittedAt'         => optional($row->created_at)->format('d M Y') ?? '-',
            ]);

        return response()->json([
            'title'   => 'Oral Presentations',
            'date'    => $date,
            'summary' => [
                'oralCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'id',                  'label' => 'Abstract ID'],
                ['key' => 'title',               'label' => 'Title'],
                ['key' => 'correspondingAuthor', 'label' => 'Author'],
                ['key' => 'submittedAt',         'label' => 'Submitted'],
            ],
            'rows' => $rows,
        ]);
    }

    // ── Existing individual-gender handlers (kept for backwards compatibility) ──

    protected function accreditedByGenderDetail(string $date, string $gender): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;
        $rows = DB::table('attendees as a')
            ->join('event_passes as ep', 'a.attendeeId', '=', 'ep.attendeeId')
            ->select(
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'ep.serialNumber',
                DB::raw('UPPER(a.stateOfResidence) as state'),
                'a.photoUrl'
            )
            ->where('a.isRegistered', 1)
            ->where('a.eventId', $eventId)
            ->whereRaw("TRIM(LOWER(a.gender)) IN (?, ?)", [$gender, substr($gender, 0, 1)])
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get();

        $label = ucfirst($gender);

        return response()->json([
            'title' => "Accredited {$label}",
            'date'  => $date,
            'summary' => ['total' => $rows->count()],
            'columns' => [
                ['key' => 'photoUrl',      'label' => 'Passport'],
                ['key' => 'uniqueId',      'label' => 'Participant ID'],
                ['key' => 'fullName',      'label' => 'Full Name'],
                ['key' => 'phone',         'label' => 'Phone Number'],
                ['key' => 'serialNumber',  'label' => 'Serial Number'],
                ['key' => 'state',         'label' => 'State'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function presentByGenderDetail(string $date, string $gender): JsonResponse
    {
        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.passId', '=', 'da.eventPassId')
            ->select(
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.photoUrl',
                'ep.serialNumber',
                'da.attendanceDate',
                DB::raw('UPPER(a.stateOfResidence) as state')
            )
            ->whereDate('da.attendanceDate', $date)
            ->where('a.isRegistered', 1)
            ->whereRaw("TRIM(LOWER(a.gender)) IN (?, ?)", [$gender, substr($gender, 0, 1)])
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get()
            ->map(fn($r) => [
                'attendeeId'     => $r->uniqueId,
                'fullName'       => $r->fullName,
                'phone'          => $r->phone,
                'photoUrl'       => $r->photoUrl,
                'serialNumber'   => $r->serialNumber,
                'attendanceDate' => $r->attendanceDate,
                'state'          => $r->state,
            ]);

        $label = ucfirst($gender) . 's';

        return response()->json([
            'title'   => "{$label} Present",
            'date'    => $date,
            'summary' => ['presentCount' => $rows->count()],
            'columns' => [
                ['key' => 'attendeeId',     'label' => 'Participant ID'],
                ['key' => 'fullName',       'label' => 'Full Name'],
                ['key' => 'phone',          'label' => 'Phone Number'],
                ['key' => 'serialNumber',   'label' => 'Serial Number'],
                ['key' => 'state',          'label' => 'State'],
                ['key' => 'attendanceDate', 'label' => 'Attendance Date'],
            ],
            'rows' => $rows,
        ]);
    }

    public function attendanceTrend(Request $request): JsonResponse
    {
        $periodStart = Carbon::parse($request->query('periodStart', '2026-03-24'))->toDateString();
        $periodEnd   = Carbon::parse($request->query('periodEnd',   '2026-03-30'))->toDateString();

        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->where('a.isRegistered', 1)
            ->whereBetween(DB::raw('DATE(da.attendanceDate)'), [$periodStart, $periodEnd])
            ->select(
                DB::raw('DATE(da.attendanceDate) as date'),
                DB::raw('COUNT(DISTINCT da.attendeeId) as present')
            )
            ->groupBy(DB::raw('DATE(da.attendanceDate)'))
            ->orderBy('date')
            ->get();

        $total = DB::table('attendees')
            ->where('isRegistered', 1)
            ->count();

        $trend = $rows->map(fn($r) => [
            'date'    => Carbon::parse($r->date)->format('d M'),
            'present' => (int) $r->present,
            'absent'  => max($total - (int) $r->present, 0),
        ]);

        return response()->json(['data' => $trend]);
    }

    protected function handleIncidentDetail(string $date, ?string $category): JsonResponse
    {
        // Incidents are not attendee-linked — always event-wide
        $rows = DB::table('incidents as ir')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ir.attendeeId')
            ->leftJoin('users as u', 'u.id', '=', 'ir.reportedBy')
            ->select(
                'ir.incidentId',
                'ir.category',
                'ir.severity',
                'ir.status',
                'ir.description',
                'ir.occurredAt',
                DB::raw("COALESCE(CONCAT(a.firstName, ' ', a.lastName), '-') as participantName"),
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as reportedBy")
            )
            ->whereDate('ir.occurredAt', $date)
            ->when($category, fn ($q) => $q->where('ir.category', $category))
            ->orderByDesc('ir.incidentId')
            ->get()
            ->map(fn ($row) => [
                'incidentId' => $row->incidentId,
                'category' => $row->category,
                'severity' => $row->severity,
                'status' => $row->status,
                'description' => $row->description,
                'occurredAt' => $row->occurredAt,
                'participantName' => $row->participantName,
                'reportedBy' => $row->reportedBy,
            ])
            ->values();

        return response()->json([
            'title' => $category ? "Incident Detail - {$category}" : 'Incident Detail',
            'date' => $date,
            'summary' => [
                'total' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'incidentId', 'label' => 'Incident ID'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'reportedBy', 'label' => 'Reported By'],
                ['key' => 'occurredAt', 'label' => 'Date'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function handleSupervisorDetail(string $date, ?int $supervisorId): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'da.attendanceDate',
                'a.attendeeId',
                DB::raw("CONCAT(a.firstName, ' ', a.lastName) as fullName"),
                'a.phoneNumber as phone',
                DB::raw("CONCAT(u.firstName, ' ', u.lastName) as supervisorName")
            )
            ->whereDate('da.attendanceDate', $date)
            ->where('eventId', $activeEvent->eventId)
            ->when($supervisorId, fn ($q) => $q->where('u.id', $supervisorId))
            ->orderByDesc('da.attendanceId')
            ->get()
            ->map(fn ($row) => [
                'attendanceId' => $row->attendanceId,
                'attendanceDate' => $row->attendanceDate,
                'attendeeId' => $row->attendeeId,
                'fullName' => $row->fullName,
                'phone' => $row->phone,
                'supervisorName' => $row->supervisorName,
            ])
            ->values();

        return response()->json([
            'title' => 'Sub-CL Attendance Detail',
            'date' => $date,
            'summary' => [
                'totalScans' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'attendanceId', 'label' => 'Scan ID'],
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Participant Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'supervisorName', 'label' => 'Sub-CL'],
                ['key' => 'attendanceDate', 'label' => 'Date'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function totalParticipantsDetail(string $date): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $rows = DB::table('attendees as a')
            ->join('event_passes as ep', 'a.attendeeId', '=', 'ep.attendeeId')
            ->select(
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.gender',
                'ep.serialNumber',
                'a.photoUrl',
                'a.stateOfResidence as state'
            )
            ->where('a.isRegistered', 1)
            ->where('a.eventId', $activeEvent->eventId)
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get();

        return response()->json([
            'title' => 'Total Participants',
            'date' => $date,
            'summary' => [
                'totalParticipants' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'photoUrl', 'label' => 'Passport'],
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'state', 'label' => 'State'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function presentParticipantsDetail(string $date): JsonResponse
    {
        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('colors as c', 'c.colorId', '=', 'a.colorId')
            ->join('event_passes as ep', 'ep.passId', '=', 'da.eventPassId')
            ->leftJoin('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.gender',
                'a.photoUrl',
                'ep.serialNumber',
                'da.attendanceDate',
                DB::raw('UPPER(a.stateOfResidence) as state'),
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as markedBy")
            )
            ->whereDate('da.attendanceDate', $date)
            ->where('a.isRegistered', 1)
            ->orderBy('a.firstName')
            ->orderBy('a.lastName')
            ->get()
            ->values()
            ->map(fn ($row) => [
                'attendanceId' => $row->attendanceId,
                'attendeeId' => $row->uniqueId,
                'fullName' => $row->fullName,
                'phone' => $row->phone,
                'gender' => $row->gender,
                'serialNumber' => $row->serialNumber,
                'photoUrl' => $row->photoUrl,
                'attendanceDate' => $row->attendanceDate,
                'markedBy' => $row->markedBy,
                'state' => $row->state,
            ]);

        return response()->json([
            'title' => 'Present Participants',
            'date' => $date,
            'summary' => [
                'presentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
                ['key' => 'state', 'label' => 'State'],
                ['key' => 'attendanceDate', 'label' => 'Attendance Date'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function absentParticipantsDetail(string $date)
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }
        
        $eventId = $activeEvent->eventId;
        
        // Debug: Check what's in daily_attendances for this event
        $allAttendances = DB::table('daily_attendances')
            ->where('eventId', $eventId)
            ->select('attendanceDate', DB::raw('COUNT(*) as count'))
            ->groupBy('attendanceDate')
            ->get();
        
        \Log::info('All attendance dates for event:', ['dates' => $allAttendances]);
        \Log::info('Searching for date:', ['date' => $date]);
        
        // Try multiple date formats
        $presentIds = DB::table('daily_attendances')
            ->where('eventId', $eventId)
            ->where(function($query) use ($date) {
                $query->whereDate('attendanceDate', $date)
                      ->orWhere('attendanceDate', $date)
                      ->orWhere(DB::raw('DATE(attendanceDate)'), $date);
            })
            ->pluck('attendeeId')
            ->unique()
            ->toArray();

        \Log::info('Present IDs found:', ['count' => count($presentIds), 'ids' => $presentIds]);

        // Get all registered attendees for this event who were NOT present
        $rows = DB::table('attendees as a')
            ->leftJoin('colors', 'colors.colorId', '=', 'a.colorId')
            ->leftJoin('sub_cls', 'sub_cls.subClId', '=', 'a.subClId')
            ->leftJoin('users', 'users.id', '=', 'sub_cls.userId')
            ->leftJoin('event_passes as ep', function($join) use ($eventId) {
                $join->on('ep.attendeeId', '=', 'a.attendeeId')
                     ->where('ep.eventId', '=', $eventId);
            })
            ->select(
                'a.attendeeId',
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                'a.gender',
                'a.photoUrl',
                'ep.serialNumber',
                'colors.colorName',
                DB::raw('UPPER(colors.colorName) as color'),
                DB::raw("CONCAT(users.firstName, ' ', users.lastName) as subclName"),
                DB::raw('UPPER(a.stateOfResidence) as state'),
            )
            ->where('a.eventId', $eventId)
            ->where('a.isRegistered', 1);

        if (!empty($presentIds)) {
            $rows->whereNotIn('a.attendeeId', $presentIds);
        }

        $rows = $rows->orderBy('a.firstName')->orderBy('a.lastName')->get();

        return response()->json([
            'title' => 'Absent Participants',
            'date' => $date,
            'debug' => [
                'eventId' => $eventId,
                'dateSearched' => $date,
                'availableDates' => $allAttendances,
                'presentCount' => count($presentIds),
            ],
            'summary' => [
                'absentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
                ['key' => 'state', 'label' => 'State'],
                ['key' => 'color', 'label' => 'Color'],
                ['key' => 'subclName', 'label' => 'Sub CL'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function attendancePercentageDetail(string $date): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }
        
        $eventId = $activeEvent->eventId ?? $activeEvent->id;
        $totalParticipants = DB::table('attendees')
            ->where('isRegistered', 1)
            ->count();

        $presentCount = DB::table('daily_attendances')
            ->whereDate('attendanceDate', $date)
            ->distinct('attendeeId')
            ->count('attendeeId');

        $percentage = $totalParticipants > 0
            ? round(($presentCount / $totalParticipants) * 100, 2)
            : 0;

        return response()->json([
            'title' => 'Attendance Percentage',
            'date' => $date,
            'summary' => [
                'totalParticipants' => $totalParticipants,
                'presentCount' => $presentCount,
                'attendancePercentage' => $percentage,
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    protected function lateArrivalsDetail(string $date): JsonResponse
    {
        // Assumes daily_attendances has attendanceTime column
        // Change this threshold as needed
        $cutoff = '09:00:00';

        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->leftJoin('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'a.attendeeId',
                DB::raw("CONCAT(a.firstName, ' ', a.lastName) as fullName"),
                'a.phoneNumber as phone',
                'da.attendanceDate',
                'da.attendanceTime',
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as markedBy")
            )
            ->whereDate('da.attendanceDate', $date)
            ->whereTime('da.attendanceTime', '>', $cutoff)
            ->orderBy('da.attendanceTime')
            ->get();

        return response()->json([
            'title' => 'Late Arrivals',
            'date' => $date,
            'summary' => [
                'lateCount' => $rows->count(),
                'cutoffTime' => $cutoff,
            ],
            'columns' => [
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'attendanceDate', 'label' => 'Date'],
                ['key' => 'attendanceTime', 'label' => 'Time'],
                ['key' => 'markedBy', 'label' => 'Marked By'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function incidentsTodayDetail(string $date): JsonResponse
    {
        // Incidents are not attendee-linked — always event-wide
        $rows = DB::table('incidents as ir')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ir.attendeeId')
            ->select(
                'ir.incidentId',
                'ir.category',
                'ir.status',
                'ir.severity',
                'ir.description',
                'ir.occurredAt',
                DB::raw("COALESCE(CONCAT(a.firstName, ' ', a.lastName), '-') as participantName")
            )
            ->whereDate('ir.occurredAt', $date)
            ->orderByDesc('ir.incidentId')
            ->get();

        return response()->json([
            'title' => 'Incidents Today',
            'date' => $date,
            'summary' => [
                'incidentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'incidentId', 'label' => 'Incident ID'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'occurredAt', 'label' => 'Date'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function openIncidentsDetail(string $date): JsonResponse
    {
        // Incidents are not attendee-linked — always event-wide
        $rows = DB::table('incidents as ir')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ir.attendeeId')
            ->select(
                'ir.incidentId',
                'ir.category',
                'ir.status',
                'ir.severity',
                'ir.description',
                'ir.occurredAt',
                DB::raw("COALESCE(CONCAT(a.firstName, ' ', a.lastName), '-') as participantName")
            )
            ->where('ir.status', 'open')
            ->whereDate('ir.occurredAt', '<=', $date)
            ->orderByDesc('ir.incidentId')
            ->get();

        return response()->json([
            'title' => 'Open Incidents',
            'date' => $date,
            'summary' => [
                'openIncidentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'incidentId', 'label' => 'Incident ID'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'occurredAt', 'label' => 'Date'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function mealsServedDetail(string $date): JsonResponse
    {
        // Assumes meal_redemptions table exists
        $rows = DB::table('meal_redemptions as mr')
            ->join('attendees as a', 'a.attendeeId', '=', 'mr.attendeeId')
            ->leftJoin('meals as m', 'm.mealId', '=', 'mr.mealId')
            ->select(
                'mr.redemptionId',
                'a.attendeeId',
                DB::raw("CONCAT(a.firstName, ' ', a.lastName) as fullName"),
                'a.phoneNumber as phone',
                DB::raw("COALESCE(m.title, '-') as mealTitle"),
                'mr.redeemedAt'
            )
            ->whereDate('mr.redeemedAt', $date)
            ->orderByDesc('mr.redemptionId')
            ->get();

        return response()->json([
            'title' => 'Meals Served',
            'date' => $date,
            'summary' => [
                'mealServedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'redemptionId', 'label' => 'Redemption ID'],
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'mealTitle', 'label' => 'Meal'],
                ['key' => 'redeemedAt', 'label' => 'Served At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function genderSplitDetail(string $date): JsonResponse
    {
        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->select(
                DB::raw("
                    CASE
                        WHEN LOWER(a.gender) IN ('male', 'm') THEN 'Male'
                        WHEN LOWER(a.gender) IN ('female', 'f') THEN 'Female'
                        ELSE 'Other'
                    END as gender
                "),
                DB::raw('COUNT(DISTINCT da.attendeeId) as participantCount')
            )
            ->whereDate('da.attendanceDate', $date)
            ->where('a.isRegistered', 1)
            ->groupBy('gender')
            ->orderBy('gender')
            ->get();

        return response()->json([
            'title' => 'Gender Split',
            'date' => $date,
            'summary' => [
                'totalParticipants' => $rows->sum('participantCount'),
                'maleCount' => (int) ($rows->firstWhere('gender', 'Male')->participantCount ?? 0),
                'femaleCount' => (int) ($rows->firstWhere('gender', 'Female')->participantCount ?? 0),
            ],
            'columns' => [
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'participantCount', 'label' => 'Participant Count'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function uniqueMealsServedDetail(string $date): JsonResponse
    {
        $rows = DB::table('meal_redemptions as mr')
            ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
            ->join('attendees as a', 'a.attendeeId', '=', 'ep.attendeeId')
            ->select(
                'a.uniqueId',
                DB::raw("UPPER(CONCAT(a.firstName, ' ', a.lastName)) as fullName"),
                'a.phoneNumber as phone',
                DB::raw('COUNT(mr.redemptionId) as mealCount')
            )
            ->whereDate('mr.redeemedAt', $date)
            ->where('a.isRegistered', 1)
            ->groupBy('a.attendeeId', 'a.firstName', 'a.lastName', 'a.phoneNumber')
            ->orderByDesc('mealCount')
            ->get();

        return response()->json([
            'title' => 'Meals (Unique)',
            'date' => $date,
            'summary' => [
                'uniqueParticipantCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'mealCount', 'label' => 'Meals Collected'],
            ],
            'rows' => $rows,
        ]);
    }
}