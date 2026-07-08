<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scores' => [
                'significance' => $this->significance,
                'relevance' => $this->relevance,
                'originality' => $this->originality,
            ],
            'average' => (float) $this->average,
            'comment' => $this->comment,
            'recommendedRejectionReason' => $this->recommended_rejection_reason,
            'submittedAt' => optional($this->submitted_at)->toIso8601String(),
        ];
    }
}