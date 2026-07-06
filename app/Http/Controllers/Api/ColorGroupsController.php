<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ColorGroupsController extends Controller
{
    /**
     * Get all color groups with participant counts
     */
    public function index(Request $request): JsonResponse
    {
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
        $eventId = $activeEvent->eventId;
        $colors = DB::table('colors as c')
            ->leftJoin('attendees as a', function ($join) use ($eventId) {
                $join->on('a.colorId', '=', 'c.colorId')
                    ->where('a.eventId', $eventId)
                    ->where('a.isRegistered', 1);
            })
            ->where('c.eventId', $eventId)
            ->select(
                'c.colorId',
                'c.colorName',
                'c.hexCode',
                'c.capacity',
                DB::raw('COUNT(DISTINCT a.attendeeId) as participantCount'),
                DB::raw('COUNT(DISTINCT CASE WHEN TRIM(LOWER(a.gender)) IN ("male", "m") THEN a.attendeeId END) as maleCount'),
                DB::raw('COUNT(DISTINCT CASE WHEN TRIM(LOWER(a.gender)) IN ("female", "f") THEN a.attendeeId END) as femaleCount'),
                DB::raw('COUNT(DISTINCT a.subClId) as subClCount')
            )
            ->groupBy('c.colorId', 'c.colorName', 'c.hexCode', 'c.capacity')
            ->orderBy('c.colorName')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Color groups retrieved successfully.',
            'data'    => $colors,
        ]);
    }

    /**
     * Get all Sub-CLs for a specific color
     */
    public function getSubCLs(Request $request, int $colorId): JsonResponse
    {
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
        $eventId = $activeEvent->eventId;
        $subCLs = DB::table('sub_cls as sc')
            ->leftJoin('attendees as a', function ($join) use ($eventId) {
                $join->on('a.subClId', '=', 'sc.subClId')
                    ->where('a.eventId', $eventId)
                    ->where('a.isRegistered', 1);
            })
            ->leftJoin('users as u', 'u.id', '=', 'sc.userId')
            ->where('sc.eventId', $eventId)
            ->where('sc.colorId', $colorId)
            ->select(
                'sc.subClId',
                'sc.state',
                'sc.lga',
                'sc.ward',
                DB::raw('COUNT(DISTINCT a.attendeeId) as participantCount'),
                'u.id as supervisorId',
                'u.firstName as supervisorFirstName',
                'u.lastName as supervisorLastName',
                'u.phoneNumber as supervisorPhone'
            )
            ->groupBy('sc.subClId', 'sc.state', 'sc.lga', 'sc.ward', 'u.id', 'u.firstName', 'u.lastName', 'u.phoneNumber')
            ->orderBy('sc.state')
            ->orderBy('sc.lga')
            ->get()
            ->map(function ($row) {
                return [
                    'subClId'          => $row->subClId,
                    'state'            => $row->state,
                    'lga'              => $row->lga,
                    'ward'             => $row->ward,
                    'participantCount' => (int) $row->participantCount,
                    'supervisor'       => $row->supervisorId ? [
                        'id'          => $row->supervisorId,
                        'firstName'   => $row->supervisorFirstName,
                        'lastName'    => $row->supervisorLastName,
                        'phoneNumber' => $row->supervisorPhone,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Sub-CLs retrieved successfully.',
            'data'    => $subCLs,
        ]);
    }

    /**
     * Get participants for a specific color (optionally filtered by Sub-CL)
     */
    public function getParticipants(Request $request,  int $colorId): JsonResponse
    {
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
        $perPage = $request->input('per_page', 20);
        $subClId = $request->input('subClId');
        $search  = $request->input('search');
        $gender  = $request->input('gender');

        $query = DB::table('attendees as a')
            ->leftJoin('sub_cls as sc', 'sc.subClId', '=', 'a.subClId')
            ->leftJoin('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('a.eventId', $activeEvent->eventId)
            ->where('a.colorId', $colorId)
            ->where('a.isRegistered', 1);

        // Filter by Sub-CL if provided
        if ($subClId) {
            $query->where('a.subClId', $subClId);
        }

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('a.fullName', 'like', "%{$search}%")
                  ->orWhere('a.uniqueId', 'like', "%{$search}%")
                  ->orWhere('a.phone', 'like', "%{$search}%")
                  ->orWhere('a.email', 'like', "%{$search}%");
            });
        }

        // Gender filter
        if ($gender && in_array($gender, ['male', 'female'])) {
            if ($gender === 'male') {
                $query->whereRaw('TRIM(LOWER(a.gender)) IN ("male", "m")');
            } else {
                $query->whereRaw('TRIM(LOWER(a.gender)) IN ("female", "f")');
            }
        }

        $query->select(
            'a.attendeeId',
            'a.uniqueId',
            'a.fullName',
            'a.phone',
            'a.email',
            'a.gender',
            'a.age',
            'a.state',
            'a.lga',
            'a.ward',
            'a.community',
            'a.photoUrl',
            'a.isRegistered',
            'a.subClId',
            'sc.state as subClState',
            'sc.lga as subClLga',
            'sc.ward as subClWard',
            'ep.serialNumber'
        )
        ->orderBy('a.fullName');

        $participants = $query->paginate($perPage);

        // Transform the data to include subcl object
        $participants->getCollection()->transform(function ($participant) {
            $data = (array) $participant;
            
            if ($participant->subClId) {
                $data['subcl'] = [
                    'subClId' => $participant->subClId,
                    'state'   => $participant->subClState,
                    'lga'     => $participant->subClLga,
                    'ward'    => $participant->subClWard,
                ];
            } else {
                $data['subcl'] = null;
            }

            // Remove the separate subcl fields
            unset($data['subClState'], $data['subClLga'], $data['subClWard']);

            return $data;
        });

        return response()->json([
            'success' => true,
            'message' => 'Participants retrieved successfully.',
            'data'    => $participants,
        ]);
    }
}