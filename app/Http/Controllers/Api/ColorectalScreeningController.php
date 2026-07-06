<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreColorectalScreeningRequest;
use App\Models\ColorectalScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class ColorectalScreeningController extends Controller
{
    public function store(StoreColorectalScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = ColorectalScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$request->validated(),
                'visitId' => $visit->visitId,
            ]
        );

        return response()->json([
            'message' => 'Colorectal screening saved successfully',
            'screening' => $screening,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        return response()->json([
            'screening' => $visit->colorectalScreening,
        ]);
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() || !$user->partner()  && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}