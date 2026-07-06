<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLiverScreeningRequest;
use App\Models\LiverScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class LiverScreeningController extends Controller
{
    public function store(StoreLiverScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = LiverScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$request->validated(),
                'visitId' => $visit->visitId,
            ]
        );

        return response()->json([
            'message' => 'Liver screening saved successfully',
            'screening' => $screening,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        return response()->json([
            'screening' => $visit->liverScreening,
        ]);
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() || !$user->partner() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}