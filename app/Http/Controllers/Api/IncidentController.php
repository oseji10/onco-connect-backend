<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Incident;
use App\Models\IncidentUpdate;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    protected function ensureIncidentAccess(): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403));
        }
    }

    protected function generateIncidentCode(): string
    {
        $datePart = now()->format('Ymd');
        $countToday = Incident::whereDate('created_at', now()->toDateString())->count() + 1;
        return 'INC-' . $datePart . '-' . str_pad((string) $countToday, 4, '0', STR_PAD_LEFT);
    }

    protected function formatIncident(Incident $incident): array
    {
        return [
            'incidentId' => $incident->incidentId,
            'incidentCode' => $incident->incidentCode,
            'title' => $incident->title,
            'description' => $incident->description,
            'category' => $incident->category,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'location' => $incident->location,
            'occurredAt' => optional($incident->occurredAt)?->toDateTimeString(),
            'reportedAt' => optional($incident->reportedAt)?->toDateTimeString(),
            'resolvedAt' => optional($incident->resolvedAt)?->toDateTimeString(),
            'resolutionNote' => $incident->resolutionNote,
            'isAnonymous' => (bool) $incident->isAnonymous,

            'reporter' => $incident->reporter ? [
                'id' => $incident->reporter->id,
                'name' => $incident->reporter->name,
                'email' => $incident->reporter->email ?? null,
            ] : null,

            'assignee' => $incident->assignee ? [
                'id' => $incident->assignee->id,
                'name' => $incident->assignee->name,
                'email' => $incident->assignee->email ?? null,
            ] : null,

            'attendee' => $incident->attendee ? [
                'attendeeId' => $incident->attendee->attendeeId,
                'fullName' => trim(($incident->attendee->firstName ?? '') . ' ' . ($incident->attendee->lastName ?? '')),
                'uniqueId' => $incident->attendee->uniqueId,
                'phone' => $incident->attendee->phone,
            ] : null,

            'room' => $incident->room ? [
                'roomId' => $incident->room->roomId,
                'name' => $incident->room->name,
                'code' => $incident->room->code,
                'building' => $incident->room->building,
            ] : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $severity = trim((string) $request->query('severity', ''));
        $category = trim((string) $request->query('category', ''));
        $assignedTo = $request->query('assignedTo');
        $attendeeId = $request->query('attendeeId');
        $roomId = $request->query('roomId');

        $query = Incident::with(['reporter', 'assignee', 'attendee', 'room'])
            ->where('eventId', $activeEvent->eventId);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($severity !== '') {
            $query->where('severity', $severity);
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if (!empty($assignedTo)) {
            $query->where('assignedTo', $assignedTo);
        }

        if (!empty($attendeeId)) {
            $query->where('attendeeId', $attendeeId);
        }

        if (!empty($roomId)) {
            $query->where('roomId', $roomId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('incidentCode', 'LIKE', "%{$search}%")
                    ->orWhere('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('location', 'LIKE', "%{$search}%")
                    ->orWhereHas('attendee', function ($attendeeQuery) use ($search) {
                        $attendeeQuery->where('uniqueId', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%")
                            ->orWhereRaw(
                                "CONCAT(COALESCE(firstName, ''), ' ', COALESCE(lastName, '')) LIKE ?",
                                ["%{$search}%"]
                            );
                    });
            });
        }

        $incidents = $query
            ->orderByRaw("CASE severity 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END")
            ->latest('incidentId')
            ->get()
            ->map(fn ($incident) => $this->formatIncident($incident))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Incidents retrieved successfully.',
            'data' => $incidents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category' => [
                'required',
                Rule::in(['medical', 'security', 'misconduct', 'room', 'lost_found', 'access', 'attendance', 'facility', 'other']),
            ],
            'severity' => [
                'required',
                Rule::in(['low', 'medium', 'high', 'critical']),
            ],
            'attendeeId' => ['nullable', 'integer', 'exists:attendees,attendeeId'],
            'roomId' => ['nullable', 'integer', 'exists:rooms,roomId'],
            'location' => ['nullable', 'string', 'max:255'],
            'occurredAt' => ['nullable', 'date'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'isAnonymous' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated, $activeEvent) {
            if (!empty($validated['attendeeId'])) {
                $attendee = Attendee::findOrFail($validated['attendeeId']);
                if ((int) $attendee->eventId !== (int) $activeEvent->eventId) {
                    throw ValidationException::withMessages([
                        'attendeeId' => ['Selected attendee does not belong to this event.'],
                    ]);
                }
            }

            if (!empty($validated['roomId'])) {
                $room = Room::findOrFail($validated['roomId']);
                if ((int) $room->eventId !== (int) $activeEvent->eventId) {
                    throw ValidationException::withMessages([
                        'roomId' => ['Selected room does not belong to this event.'],
                    ]);
                }
            }

            $incident = Incident::create([
                'eventId' => $activeEvent->eventId,
                'incidentCode' => $this->generateIncidentCode(),
                'title' => trim($validated['title']),
                'description' => trim($validated['description']),
                'category' => $validated['category'],
                'severity' => $validated['severity'],
                'status' => 'open',
                'reportedBy' => Auth::id(),
                'assignedTo' => $validated['assignedTo'] ?? null,
                'attendeeId' => $validated['attendeeId'] ?? null,
                'roomId' => $validated['roomId'] ?? null,
                'location' => $validated['location'] ?? null,
                'occurredAt' => $validated['occurredAt'] ?? null,
                'reportedAt' => now(),
                'isAnonymous' => (bool) ($validated['isAnonymous'] ?? false),
            ]);

            IncidentUpdate::create([
                'incidentId' => $incident->incidentId,
                'updatedBy' => Auth::id(),
                'oldStatus' => null,
                'newStatus' => 'open',
                'note' => 'Incident reported.',
            ]);

            $incident->load(['reporter', 'assignee', 'attendee', 'room']);

            return response()->json([
                'success' => true,
                'message' => 'Incident reported successfully.',
                'data' => $this->formatIncident($incident),
            ], 201);
        });
    }

    public function show(Incident $incident): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        if ((int) $incident->eventId !== (int) $activeEvent->eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Incident does not belong to the active event.',
            ], 404);
        }

        $incident->load([
            'reporter',
            'assignee',
            'attendee',
            'room',
            'updates.updater',
        ]);

        $updates = $incident->updates
            ->sortByDesc('updateId')
            ->values()
            ->map(function ($update) {
                return [
                    'updateId' => $update->updateId,
                    'oldStatus' => $update->oldStatus,
                    'newStatus' => $update->newStatus,
                    'note' => $update->note,
                    'createdAt' => optional($update->created_at)?->toDateTimeString(),
                    'updatedBy' => $update->updater ? [
                        'id' => $update->updater->id,
                        'name' => $update->updater->name,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Incident retrieved successfully.',
            'data' => array_merge(
                $this->formatIncident($incident),
                ['updates' => $updates]
            ),
        ]);
    }

    public function updateStatus(Request $request, Incident $incident): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        if ((int) $incident->eventId !== (int) $activeEvent->eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Incident does not belong to the active event.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['open', 'in_progress', 'resolved', 'closed', 'escalated']),
            ],
            'note' => ['required', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $incident) {
            $oldStatus = $incident->status;
            $newStatus = $validated['status'];

            $incident->status = $newStatus;

            if ($newStatus === 'resolved' && !$incident->resolvedAt) {
                $incident->resolvedAt = now();
            }

            if (in_array($newStatus, ['open', 'in_progress', 'escalated']) && $oldStatus === 'resolved') {
                $incident->resolvedAt = null;
            }

            $incident->save();

            IncidentUpdate::create([
                'incidentId' => $incident->incidentId,
                'updatedBy' => Auth::id(),
                'oldStatus' => $oldStatus,
                'newStatus' => $newStatus,
                'note' => trim($validated['note']),
            ]);

            $incident->load(['reporter', 'assignee', 'attendee', 'room']);

            return response()->json([
                'success' => true,
                'message' => 'Incident status updated successfully.',
                'data' => $this->formatIncident($incident),
            ]);
        });
    }

    public function assign(Request $request, Incident $incident): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        if ((int) $incident->eventId !== (int) $activeEvent->eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Incident does not belong to the active event.',
            ], 404);
        }

        $validated = $request->validate([
            'assignedTo' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $incident) {
            $incident->assignedTo = $validated['assignedTo'];
            $incident->save();

            IncidentUpdate::create([
                'incidentId' => $incident->incidentId,
                'updatedBy' => Auth::id(),
                'oldStatus' => null,
                'newStatus' => $incident->status,
                'note' => trim($validated['note'] ?? 'Incident assigned.'),
            ]);

            $incident->load(['reporter', 'assignee', 'attendee', 'room']);

            return response()->json([
                'success' => true,
                'message' => 'Incident assigned successfully.',
                'data' => $this->formatIncident($incident),
            ]);
        });
    }

    public function resolve(Request $request, Incident $incident): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        if ((int) $incident->eventId !== (int) $activeEvent->eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Incident does not belong to the active event.',
            ], 404);
        }

        $validated = $request->validate([
            'resolutionNote' => ['required', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $incident) {
            $oldStatus = $incident->status;

            $incident->status = 'resolved';
            $incident->resolvedAt = now();
            $incident->resolutionNote = trim($validated['resolutionNote']);
            $incident->save();

            IncidentUpdate::create([
                'incidentId' => $incident->incidentId,
                'updatedBy' => Auth::id(),
                'oldStatus' => $oldStatus,
                'newStatus' => 'resolved',
                'note' => trim($validated['note'] ?? 'Incident resolved.'),
            ]);

            $incident->load(['reporter', 'assignee', 'attendee', 'room']);

            return response()->json([
                'success' => true,
                'message' => 'Incident resolved successfully.',
                'data' => $this->formatIncident($incident),
            ]);
        });
    }

    public function addUpdate(Request $request, Incident $incident): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $this->ensureIncidentAccess();

        if ((int) $incident->eventId !== (int) $activeEvent->eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Incident does not belong to the active event.',
            ], 404);
        }

        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $update = IncidentUpdate::create([
            'incidentId' => $incident->incidentId,
            'updatedBy' => Auth::id(),
            'oldStatus' => null,
            'newStatus' => $incident->status,
            'note' => trim($validated['note']),
        ]);

        $update->load('updater');

        return response()->json([
            'success' => true,
            'message' => 'Incident update added successfully.',
            'data' => [
                'updateId' => $update->updateId,
                'oldStatus' => $update->oldStatus,
                'newStatus' => $update->newStatus,
                'note' => $update->note,
                'createdAt' => optional($update->created_at)?->toDateTimeString(),
                'updatedBy' => $update->updater ? [
                    'id' => $update->updater->id,
                    'name' => $update->updater->name,
                ] : null,
            ],
        ], 201);
    }
}