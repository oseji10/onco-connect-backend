<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reviewerId' => $this->reviewer_id,
            'reviewerName' => $this->reviewer->name,
            'reviewerEmail' => $this->reviewer->email,
            'status' => $this->status,
            'review' => $this->whenLoaded('review', function () {
                return $this->review ? new ReviewResource($this->review) : null;
            }),
        ];
    }
}