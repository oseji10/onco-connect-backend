<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicationSupply;
use App\Models\MedicationDispensing;
use App\Models\Attendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicationController extends Controller
{
    /**
     * Record new medication supply
     */
    public function recordSupply(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'drugName' => ['required', 'string', 'max:255'],
            'batchNumber' => ['required', 'string', 'max:255'],
            'expiryDate' => ['required', 'date', 'after:today'],
            'quantitySupplied' => ['required', 'integer', 'min:1'],
            'supplyDate' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();

        $supply = MedicationSupply::create([
            'eventId' => $eventId,
            'drugName' => $validated['drugName'],
            'batchNumber' => $validated['batchNumber'],
            'expiryDate' => $validated['expiryDate'],
            'quantitySupplied' => $validated['quantitySupplied'],
            'quantityRemaining' => $validated['quantitySupplied'], // Initially all remaining
            'supplyDate' => $validated['supplyDate'],
            'notes' => $validated['notes'] ?? null,
            'recordedBy' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Medication supply recorded successfully.',
            'data' => $supply,
        ]);
    }

    /**
     * Get medication inventory
     */
    public function getInventory(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Get user scoping
        $user = auth()->user();
        $userSubCl = $user->sub_cl ?? null;

        // Check if user data is scoped
        $isScoped = !empty($userSubCl);
        $scopedAttendeeIds = [];

        if ($isScoped) {
            $scopedAttendeeIds = DB::table('attendees')
                ->where('eventId', $eventId)
                ->where('sub_cl', $userSubCl)
                ->pluck('attendeeId')
                ->toArray();
        }

        // Get supplies grouped by drug name
        $supplies = MedicationSupply::where('eventId', $eventId)
            ->orderBy('drugName')
            ->orderBy('expiryDate')
            ->get();

        $inventory = $supplies->groupBy('drugName')->map(function ($items, $drugName) use ($isScoped, $scopedAttendeeIds) {
            $overallTotal = $items->sum('quantitySupplied');
            
            // Calculate dispensed - filter by scope if needed
            $overallDispensed = $items->sum(function ($supply) use ($isScoped, $scopedAttendeeIds) {
                if (!$isScoped) {
                    return $supply->quantityDispensed;
                }
                
                // Only count dispensing to scoped attendees
                return $supply->dispensings()
                    ->whereIn('attendeeId', $scopedAttendeeIds)
                    ->sum('quantityDispensed');
            });

            $overallRemaining = $items->sum('quantityRemaining');

            // Group by batch
            $byBatches = $items->map(function ($supply) use ($isScoped, $scopedAttendeeIds) {
                $dispensedForBatch = $isScoped 
                    ? $supply->dispensings()->whereIn('attendeeId', $scopedAttendeeIds)->sum('quantityDispensed')
                    : $supply->quantityDispensed;

                return [
                    'supplyId' => $supply->supplyId,
                    'batchNumber' => $supply->batchNumber,
                    'expiryDate' => $supply->expiryDate->format('Y-m-d'),
                    'quantitySupplied' => $supply->quantitySupplied,
                    'quantityDispensed' => $dispensedForBatch,
                    'quantityRemaining' => $supply->quantityRemaining,
                    'isExpired' => $supply->isExpired(),
                    'isExpiringSoon' => $supply->isExpiringSoon(),
                ];
            })->values();

            return [
                'drugName' => $drugName,
                'overallTotal' => $overallTotal,
                'overallDispensed' => $overallDispensed,
                'overallRemaining' => $overallRemaining,
                'byBatches' => $byBatches,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'inventory' => $inventory,
            'scopedToUser' => $isScoped,
        ]);
    }

    /**
     * Get recent medication supplies
     */
    public function getRecentSupplies(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $supplies = MedicationSupply::where('eventId', $eventId)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($supply) {
                return [
                    'supplyId' => $supply->supplyId,
                    'drugName' => $supply->drugName,
                    'batchNumber' => $supply->batchNumber,
                    'expiryDate' => $supply->expiryDate->format('Y-m-d'),
                    'quantitySupplied' => $supply->quantitySupplied,
                    'quantityDispensed' => $supply->quantityDispensed,
                    'quantityRemaining' => $supply->quantityRemaining,
                    'supplyDate' => $supply->supplyDate->format('Y-m-d'),
                    'isExpired' => $supply->isExpired(),
                    'isExpiringSoon' => $supply->isExpiringSoon(),
                    'createdAt' => $supply->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'supplies' => $supplies,
        ]);
    }

    /**
     * Get available medications for dispensing dropdown
     */
    public function getAvailableMedications(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Get medications that are not expired and have remaining quantity
        $medications = MedicationSupply::where('eventId', $eventId)
            ->where('quantityRemaining', '>', 0)
            ->where('expiryDate', '>=', now()->startOfDay())
            ->select('drugName')
            ->selectRaw('SUM(quantityRemaining) as totalRemaining')
            ->selectRaw('MIN(expiryDate) as nearestExpiry')
            ->groupBy('drugName')
            ->orderBy('drugName')
            ->get()
            ->map(function ($item) {
                return [
                    'drugName' => $item->drugName,
                    'totalRemaining' => $item->totalRemaining,
                    'nearestExpiry' => $item->nearestExpiry,
                    'isExpiringSoon' => $item->nearestExpiry <= now()->addDays(30),
                ];
            });

        return response()->json([
            'success' => true,
            'medications' => $medications,
        ]);
    }

    /**
     * Search for attendees/participants
     */
    public function searchAttendees(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2'],
        ]);

        $searchTerm = $validated['search'];

        // Get user scoping
        $user = auth()->user();
        $userSubCl = $user->sub_cl ?? null;

        $query = Attendee::where('eventId', $eventId);

        // Apply user scoping if needed
        if (!empty($userSubCl)) {
            $query->where('sub_cl', $userSubCl);
        }

        // Search by name or attendee ID
        $attendees = $query->where(function ($q) use ($searchTerm) {
            $q->where('fullName', 'like', "%{$searchTerm}%")
              ->orWhere('phone', 'like', "%{$searchTerm}%")
              ->orWhere('attendeeId', 'like', "%{$searchTerm}%");
        })
        ->orderBy('fullName')
        ->limit(10)
        ->get(['attendeeId', 'fullName', 'phone', 'state', 'lga']);

        return response()->json([
            'success' => true,
            'attendees' => $attendees,
        ]);
    }

    /**
     * Dispense medication to participant or non-participant
     */
    public function dispenseMedication(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'isParticipant' => ['required', 'boolean'],
            'attendeeId' => ['required_if:isParticipant,true', 'nullable', 'integer', 'exists:attendees,attendeeId'],
            'recipientName' => ['required_if:isParticipant,false', 'nullable', 'string', 'max:255'],
            'recipientType' => ['required_if:isParticipant,false', 'nullable', 'in:staff,visitor,other'],
            'recipientNotes' => ['nullable', 'string', 'max:500'],
            'drugName' => ['required', 'string', 'max:255'],
            'quantityDispensed' => ['required', 'integer', 'min:1'],
            'symptoms' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify attendee if participant
        if ($validated['isParticipant']) {
            $attendee = Attendee::where('attendeeId', $validated['attendeeId'])
                ->where('eventId', $eventId)
                ->first();

            if (!$attendee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendee not found in active event.',
                ], 404);
            }
        }

        // Find available supply (not expired, has remaining quantity)
        // Use FIFO - oldest expiry first
        $supply = MedicationSupply::where('eventId', $eventId)
            ->where('drugName', $validated['drugName'])
            ->where('quantityRemaining', '>=', $validated['quantityDispensed'])
            ->where('expiryDate', '>=', now()->startOfDay())
            ->orderBy('expiryDate', 'asc')
            ->first();

        if (!$supply) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient medication in inventory or all batches expired.',
            ], 422);
        }

        $user = auth()->user();

        // Create dispensing record
        $dispensing = MedicationDispensing::create([
            'eventId' => $eventId,
            'attendeeId' => $validated['isParticipant'] ? $validated['attendeeId'] : null,
            'supplyId' => $supply->supplyId,
            'drugName' => $validated['drugName'],
            'quantityDispensed' => $validated['quantityDispensed'],
            'recipientName' => !$validated['isParticipant'] ? $validated['recipientName'] : null,
            'recipientType' => !$validated['isParticipant'] ? $validated['recipientType'] : 'participant',
            'recipientNotes' => $validated['recipientNotes'] ?? null,
            'symptoms' => $validated['symptoms'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'dispensedBy' => $user->id,
            'deviceName' => $request->header('User-Agent'),
        ]);

        // Update supply quantities
        $supply->decrement('quantityRemaining', $validated['quantityDispensed']);
        $supply->increment('quantityDispensed', $validated['quantityDispensed']);

        $responseData = [
            'dispensing' => $dispensing,
            'batchNumber' => $supply->batchNumber,
            'remainingInBatch' => $supply->quantityRemaining,
        ];

        if ($validated['isParticipant']) {
            $responseData['attendee'] = $attendee->only(['attendeeId', 'fullName']);
        } else {
            $responseData['recipient'] = [
                'name' => $validated['recipientName'],
                'type' => $validated['recipientType'],
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Medication dispensed successfully.',
            'data' => $responseData,
        ]);
    }

    /**
     * Get attendee's medication history
     */
    public function getAttendeeHistory(Request $request, int $attendeeId): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $attendee = Attendee::where('attendeeId', $attendeeId)
            ->where('eventId', $eventId)
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found.',
            ], 404);
        }

        $history = MedicationDispensing::where('eventId', $eventId)
            ->where('attendeeId', $attendeeId)
            ->with(['supply', 'dispenser'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'dispensingId' => $record->dispensingId,
                    'drugName' => $record->drugName,
                    'quantityDispensed' => $record->quantityDispensed,
                    'symptoms' => $record->symptoms,
                    'instructions' => $record->instructions,
                    'batchNumber' => $record->supply->batchNumber ?? 'N/A',
                    'dispensedBy' => $record->dispenser->name ?? 'N/A',
                    'dispensedAt' => $record->created_at->format('Y-m-d H:i:s'),
                    'recipientType' => $record->recipientType ?? 'participant',
                ];
            });

        return response()->json([
            'success' => true,
            'attendee' => $attendee->only(['attendeeId', 'fullName', 'phoneNumber']),
            'history' => $history,
        ]);
    }

    /**
     * Get all medication dispensing records (for reports)
     */
    public function getAllDispensing(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $dispensingRecords = MedicationDispensing::where('eventId', $eventId)
            ->with(['attendee', 'supply', 'dispenser'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($record) {
                $recipientName = $record->attendeeId 
                    ? ($record->attendee->fullName ?? 'Unknown Participant')
                    : $record->recipientName;

                return [
                    'dispensingId' => $record->dispensingId,
                    'recipientName' => $recipientName,
                    'recipientType' => $record->recipientType ?? 'participant',
                    'drugName' => $record->drugName,
                    'quantityDispensed' => $record->quantityDispensed,
                    'symptoms' => $record->symptoms,
                    'batchNumber' => $record->supply->batchNumber ?? 'N/A',
                    'dispensedBy' => $record->dispenser->name ?? 'N/A',
                    'dispensedAt' => $record->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'records' => $dispensingRecords,
        ]);
    }

    /**
     * Get all recipients with medication history (paginated)
     */
    public function getAllRecipients(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Get all dispensing records
        $query = MedicationDispensing::where('eventId', $eventId)
            ->with(['attendee', 'supply', 'dispenser']);

        // If search term provided, filter
        $searchTerm = $request->input('search');
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('attendee', function ($attendeeQuery) use ($searchTerm) {
                    $attendeeQuery->where('fullName', 'like', "%{$searchTerm}%");
                })
                ->orWhere('recipientName', 'like', "%{$searchTerm}%");
            });
        }

        $records = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                $isParticipant = !empty($record->attendeeId);
                $recipientName = $isParticipant 
                    ? ($record->attendee->fullName ?? 'Unknown')
                    : $record->recipientName;

                return [
                    'dispensingId' => $record->dispensingId,
                    'recipientName' => $recipientName,
                    'recipientType' => $record->recipientType ?? 'participant',
                    'isParticipant' => $isParticipant,
                    'attendeeId' => $record->attendeeId,
                    'uniqueId' => $record->uniqueId,
                    'photo' => $isParticipant ? ($record->attendee->photoUrl ?? null) : null,
                    'phoneNumber' => $isParticipant ? ($record->attendee->phoneNumber ?? null) : null,
                    'state' => $isParticipant ? ($record->attendee->state ?? null) : null,
                    'lga' => $isParticipant ? ($record->attendee->lga ?? null) : null,
                    'recipientNotes' => $record->recipientNotes,
                    'drugName' => $record->drugName,
                    'quantityDispensed' => $record->quantityDispensed,
                    'symptoms' => $record->symptoms,
                    'instructions' => $record->instructions,
                    'batchNumber' => $record->supply->batchNumber ?? 'N/A',
                    'dispensedBy' => $record->dispenser->name ?? 'N/A',
                    'dispensedAt' => $record->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Group by recipient
        $grouped = $records->groupBy('recipientName')->map(function ($items, $name) {
            $first = $items->first();
            return [
                'recipientName' => $name,
                'recipientType' => $first['recipientType'],
                'isParticipant' => $first['isParticipant'],
                'attendeeId' => $first['attendeeId'],
                'uniqueId' => $first['uniqueId'],
                'photo' => $first['photo'],
                'phoneNumber' => $first['phoneNumber'],
                'state' => $first['state'],
                'lga' => $first['lga'],
                'totalDispensings' => $items->count(),
                'lastDispensedAt' => $items->first()['dispensedAt'], // Most recent
                'history' => $items->values(),
            ];
        })->values();

        // Sort by most recent dispensing
        $sorted = $grouped->sortByDesc('lastDispensedAt')->values();

        // Manual pagination
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $total = $sorted->count();
        $lastPage = ceil($total / $perPage);
        
        $paginatedResults = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'recipients' => $paginatedResults,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Search medication history by recipient name (for participants and non-participants)
     */
    public function searchRecipientHistory(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2'],
        ]);

        $searchTerm = $validated['search'];

        // Search in both participants and non-participants
        $query = MedicationDispensing::where('eventId', $eventId)
            ->with(['attendee', 'supply', 'dispenser']);

        $query->where(function ($q) use ($searchTerm) {
            // Search participant names
            $q->whereHas('attendee', function ($attendeeQuery) use ($searchTerm) {
                $attendeeQuery->where('fullName', 'like', "%{$searchTerm}%");
            })
            // Search non-participant names
            ->orWhere('recipientName', 'like', "%{$searchTerm}%");
        });

        $records = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                $isParticipant = !empty($record->attendeeId);
                $recipientName = $isParticipant 
                    ? ($record->attendee->fullName ?? 'Unknown')
                    : $record->recipientName;

                return [
                    'dispensingId' => $record->dispensingId,
                    'recipientName' => $recipientName,
                    'recipientType' => $record->recipientType ?? 'participant',
                    'isParticipant' => $isParticipant,
                    'attendeeId' => $record->attendeeId,
                    'photo' => $isParticipant ? ($record->attendee->photoUrl ?? null) : null,
                    'phoneNumber' => $isParticipant ? ($record->attendee->phoneNumber ?? null) : null,
                    'state' => $isParticipant ? ($record->attendee->state ?? null) : null,
                    'lga' => $isParticipant ? ($record->attendee->lga ?? null) : null,
                    'recipientNotes' => $record->recipientNotes,
                    'drugName' => $record->drugName,
                    'quantityDispensed' => $record->quantityDispensed,
                    'symptoms' => $record->symptoms,
                    'instructions' => $record->instructions,
                    'batchNumber' => $record->supply->batchNumber ?? 'N/A',
                    'dispensedBy' => $record->dispenser->name ?? 'N/A',
                    'dispensedAt' => $record->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Group by recipient
        $grouped = $records->groupBy('recipientName')->map(function ($items, $name) {
            $first = $items->first();
            return [
                'recipientName' => $name,
                'recipientType' => $first['recipientType'],
                'isParticipant' => $first['isParticipant'],
                'attendeeId' => $first['attendeeId'],
                'photo' => $first['photo'],
                'phoneNumber' => $first['phoneNumber'],
                'state' => $first['state'],
                'lga' => $first['lga'],
                'totalDispensings' => $items->count(),
                'history' => $items->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'results' => $grouped,
        ]);
    }

    /**
     * Generate medication report
     */
    public function generateReport(Request $request): JsonResponse
    {
        // Get active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // Get user scoping
        $user = auth()->user();
        $userSubCl = $user->sub_cl ?? null;
        $isScoped = !empty($userSubCl);
        $scopedAttendeeIds = [];

        if ($isScoped) {
            $scopedAttendeeIds = DB::table('attendees')
                ->where('eventId', $eventId)
                ->where('sub_cl', $userSubCl)
                ->pluck('attendeeId')
                ->toArray();
        }

        // Inventory summary
        $inventorySummary = MedicationSupply::where('eventId', $eventId)
            ->selectRaw('
                COUNT(DISTINCT drugName) as total_drugs,
                SUM(quantitySupplied) as total_supplied,
                SUM(quantityDispensed) as total_dispensed,
                SUM(quantityRemaining) as total_remaining
            ')
            ->first();

        // Dispensing summary
        $dispensingQuery = MedicationDispensing::where('eventId', $eventId);
        
        if ($isScoped) {
            $dispensingQuery->whereIn('attendeeId', $scopedAttendeeIds);
        }

        $dispensingSummary = [
            'totalDispensings' => $dispensingQuery->count(),
            'uniqueParticipants' => $dispensingQuery->distinct('attendeeId')->count('attendeeId'),
            'byDrug' => $dispensingQuery
                ->selectRaw('drugName, COUNT(*) as count, SUM(quantityDispensed) as total_quantity')
                ->groupBy('drugName')
                ->orderByDesc('count')
                ->get(),
        ];

        // Top medications
        $topMedications = MedicationDispensing::where('eventId', $eventId)
            ->when($isScoped, function ($q) use ($scopedAttendeeIds) {
                $q->whereIn('attendeeId', $scopedAttendeeIds);
            })
            ->selectRaw('drugName, COUNT(*) as dispensing_count, SUM(quantityDispensed) as total_quantity')
            ->groupBy('drugName')
            ->orderByDesc('dispensing_count')
            ->take(10)
            ->get();

        // Expiring medications
        $expiringMedications = MedicationSupply::where('eventId', $eventId)
            ->where('quantityRemaining', '>', 0)
            ->where('expiryDate', '<=', now()->addDays(30))
            ->where('expiryDate', '>=', now())
            ->orderBy('expiryDate')
            ->get(['drugName', 'batchNumber', 'expiryDate', 'quantityRemaining']);

        // Expired medications
        $expiredMedications = MedicationSupply::where('eventId', $eventId)
            ->where('quantityRemaining', '>', 0)
            ->where('expiryDate', '<', now())
            ->orderBy('expiryDate')
            ->get(['drugName', 'batchNumber', 'expiryDate', 'quantityRemaining']);

        return response()->json([
            'success' => true,
            'report' => [
                'eventId' => $eventId,
                'generatedAt' => now()->toISOString(),
                'scopedToUser' => $isScoped,
                'inventory' => $inventorySummary,
                'dispensing' => $dispensingSummary,
                'topMedications' => $topMedications,
                'expiringMedications' => $expiringMedications,
                'expiredMedications' => $expiredMedications,
            ],
        ]);
    }

    /**
     * Top up existing medication supply
     */
    public function topUpSupply(Request $request, int $supplyId): JsonResponse
    {
        $validated = $request->validate([
            'additionalQuantity' => ['required', 'integer', 'min:1'],
        ]);

        $supply = MedicationSupply::findOrFail($supplyId);

        // Verify belongs to active event
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        if ($supply->eventId !== $eventId) {
            return response()->json([
                'success' => false,
                'message' => 'Supply not found in active event.',
            ], 404);
        }

        // Check if expired
        if ($supply->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot top up expired medication.',
            ], 422);
        }

        // Update quantities
        $supply->increment('quantitySupplied', $validated['additionalQuantity']);
        $supply->increment('quantityRemaining', $validated['additionalQuantity']);

        return response()->json([
            'success' => true,
            'message' => "Added {$validated['additionalQuantity']} units to {$supply->drugName} (Batch: {$supply->batchNumber})",
            'data' => $supply->fresh(),
        ]);
    }

    /**
     * Search for attendees by name, ID, or QR code serial number
     */
    // public function searchAttendees(Request $request)
    // {
    //     $search = $request->input('search');

    //     if (strlen($search) < 2) {
    //         return response()->json([
    //             'message' => 'Search term must be at least 2 characters',
    //             'attendees' => []
    //         ], 400);
    //     }

    //     // Search by name directly in attendees table
    //     $attendeesByName = Attendee::where('full_name', 'LIKE', "%{$search}%")
    //         ->orWhere('attendeeId', 'LIKE', "%{$search}%")
    //         ->limit(20)
    //         ->get(['attendeeId', 'full_name', 'phone_number', 'state', 'lga']);

    //     // Search by QR code/serial number in event_passes table
    //     $eventPasses = EventPass::where('serial_number', 'LIKE', "%{$search}%")
    //         ->orWhere('qr_code', 'LIKE', "%{$search}%")
    //         ->with('attendee:attendeeId,full_name,phone_number,state,lga')
    //         ->limit(20)
    //         ->get();

    //     // Extract attendees from event passes
    //     $attendeesByQr = $eventPasses->map(function ($pass) {
    //         return $pass->attendee;
    //     })->filter()->values();

    //     // Merge and remove duplicates
    //     $allAttendees = $attendeesByName->merge($attendeesByQr)
    //         ->unique('attendeeId')
    //         ->values()
    //         ->take(20);

    //     return response()->json([
    //         'attendees' => $allAttendees
    //     ]);
    // }

    /**
     * Get medical information for a specific attendee
     */
    public function getAttendeeMedicalInfo($attendeeId)
    {
        $attendee = Attendee::where('attendeeId', $attendeeId)->first();

        if (!$attendee) {
            return response()->json([
                'message' => 'Participant not found'
            ], 404);
        }

        $medicalInfo = $attendee->medicalInfo;

        if (!$medicalInfo) {
            // Return default/empty medical info if none exists
            return response()->json([
                'medicalInfo' => [
                    'hasAllergy' => false,
                    'allergyDetails' => null,
                    'hasDrugAllergy' => false,
                    'drugAllergyType' => null,
                    'isPregnant' => false,
                    'pregnancyMonths' => null,
                    'isBreastfeeding' => false,
                    'onMedications' => false,
                    'medicationType' => null,
                    'onBirthControl' => false,
                    'hasSurgicalHistory' => false,
                    'surgicalHistoryDetails' => null,
                    'hasMedicalConditions' => false,
                    'medicalConditionsDetails' => null,
                ]
            ]);
        }

        return response()->json([
            'medicalInfo' => $medicalInfo->toApiResponse()
        ]);
    }

    /**
     * Get medical information by QR code
     */
    public function getAttendeeMedicalInfoByQr($qrCode)
    {
        // Find event pass by QR code
        $eventPass = EventPass::where('serial_number', $qrCode)
                              ->orWhere('qr_code', $qrCode)
                              ->first();

        if (!$eventPass || !$eventPass->attendee) {
            return response()->json([
                'message' => 'Participant not found for this QR code'
            ], 404);
        }

        // Use the regular method with the found attendeeId
        return $this->getAttendeeMedicalInfo($eventPass->attendee->attendeeId);
    }

    /**
     * Get medication history for a specific attendee
     */
    public function getAttendeeMedicationHistory($attendeeId)
    {
        $attendee = Attendee::where('attendeeId', $attendeeId)->first();

        if (!$attendee) {
            return response()->json([
                'message' => 'Participant not found'
            ], 404);
        }

        $history = $attendee->medicationHistory()
            ->with('dispensedByUser:id,name')
            ->get()
            ->map(function ($record) {
                return [
                    'dispensingId' => $record->id,
                    'drugName' => $record->drug_name,
                    'quantityDispensed' => $record->quantity_dispensed,
                    'symptoms' => $record->symptoms,
                    'instructions' => $record->instructions,
                    'batchNumber' => $record->batch_number,
                    'dispensedBy' => $record->dispensedByUser->name ?? 'Unknown',
                    'dispensedAt' => $record->dispensed_at,
                ];
            });

        return response()->json([
            'history' => $history
        ]);
    }

    /**
     * Get available medications
     */
    // public function getAvailableMedications()
    // {
    //     $medications = DB::table('medication_inventory')
    //         ->select(
    //             'drug_name',
    //             DB::raw('SUM(quantity_remaining) as total_remaining'),
    //             DB::raw('MIN(expiry_date) as nearest_expiry')
    //         )
    //         ->where('quantity_remaining', '>', 0)
    //         ->groupBy('drug_name')
    //         ->get()
    //         ->map(function ($med) {
    //             $isExpiringSoon = false;
    //             if ($med->nearest_expiry) {
    //                 $expiryDate = \Carbon\Carbon::parse($med->nearest_expiry);
    //                 $isExpiringSoon = $expiryDate->diffInDays(now()) <= 90; // 3 months
    //             }

    //             return [
    //                 'drugName' => $med->drug_name,
    //                 'totalRemaining' => $med->total_remaining,
    //                 'nearestExpiry' => $med->nearest_expiry,
    //                 'isExpiringSoon' => $isExpiringSoon,
    //             ];
    //         });

    //     return response()->json([
    //         'medications' => $medications
    //     ]);
    // }

    /**
     * Dispense medication
     */
    // public function dispenseMedication(Request $request)
    // {
    //     $validated = $request->validate([
    //         'isParticipant' => 'required|boolean',
    //         'attendeeId' => 'required_if:isParticipant,true|nullable|exists:attendees,attendeeId',
    //         'recipientName' => 'required_if:isParticipant,false|nullable|string|max:255',
    //         'recipientType' => 'required_if:isParticipant,false|nullable|in:staff,visitor,other',
    //         'recipientNotes' => 'nullable|string',
    //         'drugName' => 'required|string|max:255',
    //         'quantityDispensed' => 'required|integer|min:1',
    //         'symptoms' => 'nullable|string',
    //         'instructions' => 'nullable|string',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         // Check available stock
    //         $availableStock = DB::table('medication_inventory')
    //             ->where('drug_name', $validated['drugName'])
    //             ->where('quantity_remaining', '>', 0)
    //             ->orderBy('expiry_date', 'asc')
    //             ->get();

    //         $totalAvailable = $availableStock->sum('quantity_remaining');

    //         if ($totalAvailable < $validated['quantityDispensed']) {
    //             throw ValidationException::withMessages([
    //                 'quantity' => ["Insufficient stock. Only {$totalAvailable} units available."]
    //             ]);
    //         }

    //         // Get medical info if participant (for warnings/logging)
    //         $medicalInfo = null;
    //         if ($validated['isParticipant']) {
    //             $attendee = Attendee::where('attendeeId', $validated['attendeeId'])->first();
    //             $medicalInfo = $attendee->medicalInfo;
    //         }

    //         // Deduct from inventory (FIFO - First to Expire First Out)
    //         $remainingToDispense = $validated['quantityDispensed'];
    //         $batchNumbers = [];

    //         foreach ($availableStock as $batch) {
    //             if ($remainingToDispense <= 0) break;

    //             $quantityFromBatch = min($remainingToDispense, $batch->quantity_remaining);
                
    //             DB::table('medication_inventory')
    //                 ->where('id', $batch->id)
    //                 ->decrement('quantity_remaining', $quantityFromBatch);

    //             $batchNumbers[] = $batch->batch_number;
    //             $remainingToDispense -= $quantityFromBatch;
    //         }

    //         // Create dispensing record
    //         $dispensing = new MedicationDispensing();
    //         $dispensing->is_participant = $validated['isParticipant'];
            
    //         if ($validated['isParticipant']) {
    //             $dispensing->attendeeId = $validated['attendeeId'];
    //         } else {
    //             $dispensing->recipient_name = $validated['recipientName'];
    //             $dispensing->recipient_type = $validated['recipientType'];
    //             $dispensing->recipient_notes = $validated['recipientNotes'] ?? null;
    //         }
            
    //         $dispensing->drug_name = $validated['drugName'];
    //         $dispensing->quantity_dispensed = $validated['quantityDispensed'];
    //         $dispensing->symptoms = $validated['symptoms'] ?? null;
    //         $dispensing->instructions = $validated['instructions'] ?? null;
    //         $dispensing->batch_number = implode(', ', $batchNumbers);
    //         $dispensing->dispensed_by = Auth::id();
    //         $dispensing->dispensed_at = now();
            
    //         // Add medical alert flags if participant
    //         if ($medicalInfo) {
    //             $dispensing->has_drug_allergy = $medicalInfo->has_drug_allergy;
    //             $dispensing->is_pregnant = $medicalInfo->is_pregnant;
    //             $dispensing->has_medical_conditions = $medicalInfo->has_medical_conditions;
    //         }
            
    //         $dispensing->save();

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Medication dispensed successfully',
    //             'dispensing' => $dispensing
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
            
    //         return response()->json([
    //             'message' => 'Failed to dispense medication: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Update or create medical information for an attendee
     */
    public function updateAttendeeMedicalInfo(Request $request, $attendeeId)
    {
        $attendee = Attendee::where('attendeeId', $attendeeId)->first();

        if (!$attendee) {
            return response()->json([
                'message' => 'Participant not found'
            ], 404);
        }

        $validated = $request->validate([
            'hasAllergy' => 'required|boolean',
            'allergyDetails' => 'nullable|string',
            'hasDrugAllergy' => 'required|boolean',
            'drugAllergyType' => 'nullable|string',
            'isPregnant' => 'required|boolean',
            'pregnancyMonths' => 'nullable|string',
            'isBreastfeeding' => 'required|boolean',
            'onMedications' => 'required|boolean',
            'medicationType' => 'nullable|string',
            'onBirthControl' => 'required|boolean',
            'hasSurgicalHistory' => 'required|boolean',
            'surgicalHistoryDetails' => 'nullable|string',
            'hasMedicalConditions' => 'required|boolean',
            'medicalConditionsDetails' => 'nullable|string',
        ]);

        $medicalInfo = ParticipantMedicalInfo::updateOrCreate(
            ['attendeeId' => $attendeeId],
            [
                'has_allergy' => $validated['hasAllergy'],
                'allergy_details' => $validated['allergyDetails'],
                'has_drug_allergy' => $validated['hasDrugAllergy'],
                'drug_allergy_type' => $validated['drugAllergyType'],
                'is_pregnant' => $validated['isPregnant'],
                'pregnancy_months' => $validated['pregnancyMonths'],
                'is_breastfeeding' => $validated['isBreastfeeding'],
                'on_medications' => $validated['onMedications'],
                'medication_type' => $validated['medicationType'],
                'on_birth_control' => $validated['onBirthControl'],
                'has_surgical_history' => $validated['hasSurgicalHistory'],
                'surgical_history_details' => $validated['surgicalHistoryDetails'],
                'has_medical_conditions' => $validated['hasMedicalConditions'],
                'medical_conditions_details' => $validated['medicalConditionsDetails'],
            ]
        );

        return response()->json([
            'message' => 'Medical information updated successfully',
            'medicalInfo' => $medicalInfo->toApiResponse()
        ]);
    }
}

