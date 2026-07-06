<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCervicalScreeningRequest;
use App\Models\CervicalScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class CervicalScreeningController extends Controller
{
    public function store(StoreCervicalScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = CervicalScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$request->validated(),
                'visitId' => $visit->visitId,
            ]
        );

        return response()->json([
            'message' => 'Cervical screening saved successfully',
            'screening' => $screening,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        return response()->json([
            'screening' => $visit->cervicalScreening,
        ]);
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}