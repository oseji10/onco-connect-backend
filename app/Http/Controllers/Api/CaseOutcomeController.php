<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertCaseOutcomeRequest;
use App\Models\CaseOutcome;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class CaseOutcomeController extends Controller
{
    public function show(Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        return response()->json([
            'outcome' => $client->outcome,
        ]);
    }

    public function upsert(UpsertCaseOutcomeRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        $outcome = CaseOutcome::updateOrCreate(
            ['clientId' => $client->clientId],
            [
                ...$request->validated(),
                'clientId' => $client->clientId,
                'updatedBy' => auth('api')->id(),
            ]
        );

        return response()->json([
            'message' => 'Case outcome saved successfully',
            'outcome' => $outcome,
        ]);
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && $client->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this client');
        }
    }
}