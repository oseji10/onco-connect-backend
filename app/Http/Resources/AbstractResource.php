<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbstractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'subTheme' => $this->sub_theme,
            'presentationType' => $this->presentation_type,
            'keywords' => $this->keywords,
            'body' => $this->body,
            'wordCount' => $this->word_count,
            'authors' => AbstractAuthorResource::collection($this->whenLoaded('authors')),
            'status' => $this->status,
            'averageScore' => $this->average_score !== null ? (float) $this->average_score : null,
            'reviewers' => ReviewAssignmentResource::collection($this->whenLoaded('assignments')),
            'submittedAt' => optional($this->submitted_at)->toIso8601String(),
        ];
    }
}