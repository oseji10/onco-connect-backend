<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'affiliation' => $this->affiliation,
            'status' => $this->status,
            'assignedCount' => $this->assignments_count ?? $this->assignments()->count(),
            'completedCount' => $this->completed_assignments_count
                ?? $this->assignments()->where('status', 'submitted')->count(),
        ];
    }
}